<?php
/**
 * Database Repair Script
 * Run this script to fix common database issues
 */

require_once '../config/app.php';

echo "Starting database repair...\n";

try {
    $db = Database::getInstance();
    
    // Check if all required tables exist
    $requiredTables = [
        'admin_users',
        'trading_settings', 
        'trading_pairs',
        'trading_history',
        'ai_signals',
        'balance_history',
        'positions',
        'system_logs',
        'api_rate_limits',
        'performance_metrics'
    ];
    
    $existingTables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $existingTables[] = $row[0];
    }
    
    echo "Found " . count($existingTables) . " existing tables.\n";
    
    $missingTables = array_diff($requiredTables, $existingTables);
    
    if (!empty($missingTables)) {
        echo "Missing tables: " . implode(', ', $missingTables) . "\n";
        echo "Running enhanced schema to create missing tables...\n";
        
        // Execute the enhanced schema
        $schemaFile = 'enhanced_schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            
            // Split and execute SQL statements
            $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));
            
            foreach ($statements as $statement) {
                if (empty($statement) || strlen(trim($statement)) < 5) {
                    continue;
                }
                
                try {
                    $db->query($statement);
                } catch (Exception $e) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
            
            echo "Schema execution completed.\n";
        }
    }
    
    // Check and fix missing columns
    echo "Checking for missing columns...\n";
    
    // Fix trading_settings columns
    try {
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
                echo "Adding missing column: trading_settings.{$column}\n";
                $db->query("ALTER TABLE trading_settings ADD COLUMN {$column} {$definition}");
            }
        }
    } catch (Exception $e) {
        echo "Error checking trading_settings columns: " . $e->getMessage() . "\n";
    }
    
    // Ensure default settings exist
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    if (!$settings) {
        echo "Creating default trading settings...\n";
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
    
    // Check if default trading pairs exist
    $pairCount = $db->fetchOne("SELECT COUNT(*) as count FROM trading_pairs")['count'];
    if ($pairCount == 0) {
        echo "Adding default trading pairs...\n";
        $defaultPairs = [
            ['BTCUSDT', 'BTC', 'USDT'],
            ['ETHUSDT', 'ETH', 'USDT'],
            ['BNBUSDT', 'BNB', 'USDT'],
            ['ADAUSDT', 'ADA', 'USDT'],
            ['DOTUSDT', 'DOT', 'USDT'],
            ['LINKUSDT', 'LINK', 'USDT'],
            ['LTCUSDT', 'LTC', 'USDT'],
            ['XRPUSDT', 'XRP', 'USDT']
        ];
        
        foreach ($defaultPairs as $pair) {
            $db->insert('trading_pairs', [
                'symbol' => $pair[0],
                'base_asset' => $pair[1],
                'quote_asset' => $pair[2],
                'trading_type' => 'BOTH',
                'enabled' => 1
            ]);
        }
    }
    
    // Create views if they don't exist
    echo "Creating/updating database views...\n";
    
    try {
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
        
        echo "Database views created successfully.\n";
    } catch (Exception $e) {
        echo "Warning: Could not create views: " . $e->getMessage() . "\n";
    }
    
    echo "Database repair completed successfully!\n";
    
} catch (Exception $e) {
    echo "Database repair failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>