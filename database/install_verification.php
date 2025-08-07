<?php
/**
 * Installation Verification Script
 * Run this after installation to verify everything is working
 */

require_once '../config/app.php';

echo "=== Installation Verification ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$issues = [];
$success = [];

try {
    // Test database connection
    echo "1. Testing database connection...\n";
    $db = Database::getInstance();
    $db->query("SELECT 1");
    $success[] = "Database connection successful";
    
    // Check all required tables exist
    echo "2. Checking required tables...\n";
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
        'performance_metrics' => 'Performance analytics',
        'spot_balances' => 'Spot trading balances',
        'notifications' => 'Notification system',
        'trading_strategies' => 'AI trading strategies'
    ];
    
    $existingTables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $existingTables[] = $row[0];
    }
    
    foreach ($requiredTables as $table => $description) {
        if (in_array($table, $existingTables)) {
            $success[] = "Table {$table} exists - {$description}";
        } else {
            $issues[] = "Missing table: {$table} - {$description}";
        }
    }
    
    // Check views
    echo "3. Checking database views...\n";
    $views = ['dashboard_summary', 'portfolio_overview'];
    $existingViews = [];
    
    try {
        $result = $db->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        while ($row = $result->fetch_array()) {
            $existingViews[] = $row[0];
        }
    } catch (Exception $e) {
        $issues[] = "Could not check views: " . $e->getMessage();
    }
    
    foreach ($views as $view) {
        if (in_array($view, $existingViews)) {
            $success[] = "View {$view} exists";
        } else {
            $issues[] = "Missing view: {$view}";
        }
    }
    
    // Check admin user
    echo "4. Checking admin user...\n";
    $adminCount = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users")['count'];
    if ($adminCount > 0) {
        $success[] = "Admin users configured ({$adminCount} users)";
    } else {
        $issues[] = "No admin users found";
    }
    
    // Check trading settings
    echo "5. Checking trading settings...\n";
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    if ($settings) {
        $success[] = "Trading settings configured";
        $success[] = "- Spot trading: " . ($settings['spot_trading_enabled'] ? 'Enabled' : 'Disabled');
        $success[] = "- Futures trading: " . ($settings['futures_trading_enabled'] ? 'Enabled' : 'Disabled');
        $success[] = "- Testnet mode: " . ($settings['testnet_mode'] ? 'Enabled' : 'Disabled');
    } else {
        $issues[] = "Trading settings not configured";
    }
    
    // Check file permissions
    echo "6. Checking file permissions...\n";
    $directories = ['config', 'logs', 'backups'];
    foreach ($directories as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            $success[] = "Directory {$dir} is writable";
        } else {
            $issues[] = "Directory {$dir} is not writable or missing";
        }
    }
    
    // Check installation flag
    echo "7. Checking installation status...\n";
    if (file_exists('config/installed.flag')) {
        $success[] = "Installation flag exists";
    } else {
        $issues[] = "Installation flag missing";
    }
    
    // Summary
    echo "\n=== Verification Summary ===\n";
    echo "✅ Successful checks: " . count($success) . "\n";
    echo "❌ Issues found: " . count($issues) . "\n\n";
    
    if (!empty($success)) {
        echo "SUCCESS:\n";
        foreach ($success as $item) {
            echo "  ✓ {$item}\n";
        }
        echo "\n";
    }
    
    if (!empty($issues)) {
        echo "ISSUES:\n";
        foreach ($issues as $item) {
            echo "  ✗ {$item}\n";
        }
        echo "\n";
    }
    
    if (empty($issues)) {
        echo "🎉 Installation verification completed successfully!\n";
        echo "Your Enhanced Binance AI Trader is ready to use.\n";
    } else {
        echo "⚠️  Installation has issues that need to be resolved.\n";
        echo "Please run the repair script or reinstall.\n";
    }
    
} catch (Exception $e) {
    echo "Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>