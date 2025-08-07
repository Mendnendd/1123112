<?php
/**
 * Database Health Check Script
 * Checks database integrity and reports issues
 */

require_once '../config/app.php';

echo "=== Database Health Check ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance();
    
    // Test basic connection
    echo "1. Testing database connection...\n";
    $db->query("SELECT 1");
    echo "   ✓ Database connection successful\n\n";
    
    // Check all tables exist
    echo "2. Checking table structure...\n";
    $requiredTables = [
        'admin_users' => 'User management',
        'trading_settings' => 'System configuration',
        'trading_pairs' => 'Trading pairs configuration',
        'trading_history' => 'Trade execution history',
        'ai_signals' => 'AI analysis signals',
        'balance_history' => 'Account balance tracking',
        'positions' => 'Active positions',
        'system_logs' => 'System logging',
        'api_rate_limits' => 'API usage tracking',
        'performance_metrics' => 'Performance analytics'
    ];
    
    $existingTables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $existingTables[] = $row[0];
    }
    
    foreach ($requiredTables as $table => $description) {
        if (in_array($table, $existingTables)) {
            echo "   ✓ {$table} - {$description}\n";
        } else {
            echo "   ✗ {$table} - MISSING - {$description}\n";
        }
    }
    
    // Check for enhanced tables
    echo "\n3. Checking enhanced features...\n";
    $enhancedTables = [
        'spot_balances' => 'Spot trading balances',
        'notifications' => 'Notification system',
        'trading_strategies' => 'AI trading strategies'
    ];
    
    foreach ($enhancedTables as $table => $description) {
        if (in_array($table, $existingTables)) {
            echo "   ✓ {$table} - {$description}\n";
        } else {
            echo "   ⚠ {$table} - MISSING - {$description} (Enhanced feature)\n";
        }
    }
    
    // Check views
    echo "\n4. Checking database views...\n";
    $views = ['dashboard_summary', 'portfolio_overview'];
    $existingViews = [];
    
    try {
        $result = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        while ($row = $result->fetch_array()) {
            $existingViews[] = $row[0];
        }
    } catch (Exception $e) {
        echo "   ⚠ Could not check views: " . $e->getMessage() . "\n";
    }
    
    foreach ($views as $view) {
        if (in_array($view, $existingViews)) {
            echo "   ✓ {$view} view exists\n";
        } else {
            echo "   ⚠ {$view} view missing\n";
        }
    }
    
    // Check data integrity
    echo "\n5. Checking data integrity...\n";
    
    // Check if admin user exists
    $adminCount = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users")['count'];
    if ($adminCount > 0) {
        echo "   ✓ Admin users configured ({$adminCount} users)\n";
    } else {
        echo "   ⚠ No admin users found\n";
    }
    
    // Check trading settings
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    if ($settings) {
        echo "   ✓ Trading settings configured\n";
        echo "     - Trading enabled: " . ($settings['trading_enabled'] ? 'Yes' : 'No') . "\n";
        echo "     - AI enabled: " . ($settings['ai_enabled'] ? 'Yes' : 'No') . "\n";
        echo "     - Testnet mode: " . ($settings['testnet_mode'] ? 'Yes' : 'No') . "\n";
        if (isset($settings['spot_trading_enabled'])) {
            echo "     - Spot trading: " . ($settings['spot_trading_enabled'] ? 'Yes' : 'No') . "\n";
        }
        if (isset($settings['futures_trading_enabled'])) {
            echo "     - Futures trading: " . ($settings['futures_trading_enabled'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "   ⚠ Trading settings not configured\n";
    }
    
    // Check trading pairs
    $pairCount = $db->fetchOne("SELECT COUNT(*) as count FROM trading_pairs WHERE enabled = 1")['count'];
    if ($pairCount > 0) {
        echo "   ✓ Trading pairs configured ({$pairCount} active pairs)\n";
    } else {
        echo "   ⚠ No active trading pairs\n";
    }
    
    // Check recent activity
    echo "\n6. Checking recent activity...\n";
    
    $recentTrades = $db->fetchOne("SELECT COUNT(*) as count FROM trading_history WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'];
    echo "   - Trades (24h): {$recentTrades}\n";
    
    $recentSignals = $db->fetchOne("SELECT COUNT(*) as count FROM ai_signals WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'];
    echo "   - AI signals (24h): {$recentSignals}\n";
    
    $recentLogs = $db->fetchOne("SELECT COUNT(*) as count FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'];
    echo "   - System logs (24h): {$recentLogs}\n";
    
    $errorLogs = $db->fetchOne("SELECT COUNT(*) as count FROM system_logs WHERE level IN ('ERROR', 'CRITICAL') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'];
    if ($errorLogs > 0) {
        echo "   ⚠ Error logs (24h): {$errorLogs}\n";
    } else {
        echo "   ✓ No recent errors\n";
    }
    
    echo "\n=== Health Check Complete ===\n";
    echo "Database appears to be healthy!\n";
    
} catch (Exception $e) {
    echo "Health check failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>