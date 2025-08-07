<?php
/**
 * Quick Database Repair Script
 * Fixes common installation issues
 */

require_once '../config/app.php';

echo "=== Quick Database Repair ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    
    echo "1. Checking and fixing trading_settings...\n";
    
    // Ensure trading_settings has all required columns
    $columns = $db->fetchAll("SHOW COLUMNS FROM trading_settings");
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = [
        'spot_trading_enabled' => 'tinyint(1) DEFAULT 1',
        'futures_trading_enabled' => 'tinyint(1) DEFAULT 1',
        'max_spot_position_size' => 'decimal(15,8) DEFAULT 50.00000000',
        'ai_confidence_threshold' => 'decimal(3,2) DEFAULT 0.75',
        'max_daily_trades' => 'int(11) DEFAULT 20',
        'max_concurrent_positions' => 'int(11) DEFAULT 5',
        'emergency_stop' => 'tinyint(1) DEFAULT 0'
    ];
    
    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columnNames)) {
            echo "  Adding missing column: {$column}\n";
            $db->query("ALTER TABLE trading_settings ADD COLUMN {$column} {$definition}");
        }
    }
    
    // Ensure default settings exist
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    if (!$settings) {
        echo "2. Creating default trading settings...\n";
        $db->insert('trading_settings', [
            'id' => 1,
            'testnet_mode' => 1,
            'trading_enabled' => 0,
            'ai_enabled' => 1,
            'spot_trading_enabled' => 1,
            'futures_trading_enabled' => 1,
            'max_position_size' => 100,
            'max_spot_position_size' => 50,
            'risk_percentage' => 2,
            'stop_loss_percentage' => 5,
            'take_profit_percentage' => 10,
            'leverage' => 10,
            'margin_type' => 'ISOLATED',
            'ai_confidence_threshold' => 0.75,
            'max_daily_trades' => 20,
            'max_concurrent_positions' => 5,
            'emergency_stop' => 0
        ]);
    }
    
    echo "3. Checking trading_pairs...\n";
    
    // Add missing columns to trading_pairs
    $pairColumns = $db->fetchAll("SHOW COLUMNS FROM trading_pairs");
    $pairColumnNames = array_column($pairColumns, 'Field');
    
    $requiredPairColumns = [
        'base_asset' => 'varchar(10) DEFAULT NULL',
        'quote_asset' => 'varchar(10) DEFAULT NULL',
        'trading_type' => "enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH'",
        'ai_priority' => 'int(11) DEFAULT 1',
        'volatility_score' => 'decimal(5,2) DEFAULT 0.00'
    ];
    
    foreach ($requiredPairColumns as $column => $definition) {
        if (!in_array($column, $pairColumnNames)) {
            echo "  Adding missing column to trading_pairs: {$column}\n";
            $db->query("ALTER TABLE trading_pairs ADD COLUMN {$column} {$definition}");
        }
    }
    
    // Update base_asset and quote_asset for existing pairs
    $db->query("
        UPDATE trading_pairs SET 
            base_asset = SUBSTRING(symbol, 1, LENGTH(symbol) - 4),
            quote_asset = RIGHT(symbol, 4),
            trading_type = 'BOTH'
        WHERE base_asset IS NULL OR base_asset = ''
    ");
    
    echo "4. Creating missing tables...\n";
    
    // Create spot_balances table if missing
    $spotBalancesExists = $db->fetchOne("SHOW TABLES LIKE 'spot_balances'");
    if (!$spotBalancesExists) {
        echo "  Creating spot_balances table...\n";
        $db->query("
            CREATE TABLE `spot_balances` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `asset` varchar(10) NOT NULL,
              `free` decimal(20,8) NOT NULL DEFAULT 0.00000000,
              `locked` decimal(20,8) NOT NULL DEFAULT 0.00000000,
              `total` decimal(20,8) NOT NULL DEFAULT 0.00000000,
              `btc_value` decimal(20,8) DEFAULT 0.00000000,
              `usdt_value` decimal(20,8) DEFAULT 0.00000000,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `asset` (`asset`),
              KEY `updated_at` (`updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
    
    // Create notifications table if missing
    $notificationsExists = $db->fetchOne("SHOW TABLES LIKE 'notifications'");
    if (!$notificationsExists) {
        echo "  Creating notifications table...\n";
        $db->query("
            CREATE TABLE `notifications` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `type` enum('INFO','SUCCESS','WARNING','ERROR','TRADE','SIGNAL') NOT NULL DEFAULT 'INFO',
              `category` enum('SYSTEM','TRADING','AI','SECURITY','PERFORMANCE') DEFAULT 'SYSTEM',
              `title` varchar(255) NOT NULL,
              `message` text NOT NULL,
              `data` text DEFAULT NULL,
              `priority` enum('LOW','NORMAL','HIGH','URGENT') DEFAULT 'NORMAL',
              `read_at` timestamp NULL DEFAULT NULL,
              `expires_at` timestamp NULL DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `user_id` (`user_id`),
              KEY `type` (`type`),
              KEY `category` (`category`),
              KEY `priority` (`priority`),
              KEY `created_at` (`created_at`),
              KEY `read_at` (`read_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
    
    // Create trading_strategies table if missing
    $strategiesExists = $db->fetchOne("SHOW TABLES LIKE 'trading_strategies'");
    if (!$strategiesExists) {
        echo "  Creating trading_strategies table...\n";
        $db->query("
            CREATE TABLE `trading_strategies` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `description` text DEFAULT NULL,
              `strategy_type` enum('SCALPING','DAY_TRADING','SWING','POSITION') DEFAULT 'DAY_TRADING',
              `trading_type` enum('SPOT','FUTURES','BOTH') DEFAULT 'BOTH',
              `enabled` tinyint(1) DEFAULT 1,
              `risk_level` enum('LOW','MEDIUM','HIGH') DEFAULT 'MEDIUM',
              `min_confidence` decimal(3,2) DEFAULT 0.70,
              `max_position_size` decimal(15,8) DEFAULT 100.00000000,
              `stop_loss_percentage` decimal(5,2) DEFAULT 5.00,
              `take_profit_percentage` decimal(5,2) DEFAULT 10.00,
              `indicators_config` text DEFAULT NULL,
              `entry_conditions` text DEFAULT NULL,
              `exit_conditions` text DEFAULT NULL,
              `backtest_results` text DEFAULT NULL,
              `performance_metrics` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `name` (`name`),
              KEY `strategy_type` (`strategy_type`),
              KEY `trading_type` (`trading_type`),
              KEY `enabled` (`enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Insert default strategies
        $defaultStrategies = [
            ['AI Momentum', 'AI-driven momentum trading strategy', 'DAY_TRADING', 'BOTH', 0.75],
            ['Mean Reversion', 'Mean reversion strategy using Bollinger Bands', 'SWING', 'BOTH', 0.70],
            ['Trend Following', 'Long-term trend following strategy', 'POSITION', 'BOTH', 0.80],
            ['Scalping Pro', 'High-frequency scalping strategy', 'SCALPING', 'FUTURES', 0.85]
        ];
        
        foreach ($defaultStrategies as $strategy) {
            $db->insert('trading_strategies', [
                'name' => $strategy[0],
                'description' => $strategy[1],
                'strategy_type' => $strategy[2],
                'trading_type' => $strategy[3],
                'min_confidence' => $strategy[4]
            ]);
        }
    }
    
    echo "5. Creating/updating database views...\n";
    
    // Create dashboard views
    $db->query("
        CREATE OR REPLACE VIEW `dashboard_summary` AS
        SELECT 
            COALESCE((SELECT COUNT(*) FROM positions WHERE position_amt != 0), 0) as active_positions,
            COALESCE((SELECT COUNT(*) FROM trading_history WHERE DATE(created_at) = CURDATE()), 0) as today_trades,
            COALESCE((SELECT SUM(profit_loss) FROM trading_history WHERE DATE(created_at) = CURDATE()), 0) as today_pnl,
            COALESCE((SELECT COUNT(*) FROM ai_signals WHERE DATE(created_at) = CURDATE()), 0) as today_signals,
            COALESCE((SELECT AVG(confidence) FROM ai_signals WHERE DATE(created_at) = CURDATE()), 0) as avg_confidence,
            COALESCE((SELECT total_portfolio_value FROM balance_history ORDER BY created_at DESC LIMIT 1), 0) as portfolio_value,
            COALESCE((SELECT daily_pnl FROM balance_history ORDER BY created_at DESC LIMIT 1), 0) as daily_pnl,
            COALESCE((SELECT COUNT(*) FROM system_logs WHERE level IN ('ERROR', 'CRITICAL') AND DATE(created_at) = CURDATE()), 0) as error_count
    ");
    
    $db->query("
        CREATE OR REPLACE VIEW `portfolio_overview` AS
        SELECT 
            COALESCE(bh.total_portfolio_value, 0) as total_portfolio_value,
            COALESCE(bh.spot_balance_usdt, 0) as spot_balance_usdt,
            COALESCE(bh.futures_balance_usdt, 0) as futures_balance_usdt,
            COALESCE(bh.total_unrealized_pnl, 0) as total_unrealized_pnl,
            COALESCE(bh.daily_pnl, 0) as daily_pnl,
            COALESCE(bh.daily_pnl_percentage, 0) as daily_pnl_percentage,
            COALESCE(COUNT(p.id), 0) as active_positions,
            COALESCE(SUM(CASE WHEN p.trading_type = 'SPOT' THEN p.position_value ELSE 0 END), 0) as spot_position_value,
            COALESCE(SUM(CASE WHEN p.trading_type = 'FUTURES' THEN p.position_value ELSE 0 END), 0) as futures_position_value,
            COALESCE(bh.created_at, NOW()) as last_updated
        FROM (SELECT * FROM balance_history ORDER BY created_at DESC LIMIT 1) bh
        LEFT JOIN positions p ON p.position_amt != 0
        GROUP BY bh.id
    ");
    
    $success[] = "Database views created/updated";
    
    echo "\n=== Repair Summary ===\n";
    if (!empty($issues)) {
        echo "Issues found and fixed:\n";
        foreach ($issues as $issue) {
            echo "  ✓ Fixed: {$issue}\n";
        }
    } else {
        echo "No issues found - database is healthy!\n";
    }
    
    echo "\nDatabase repair completed successfully!\n";
    
} catch (Exception $e) {
    echo "Repair failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>