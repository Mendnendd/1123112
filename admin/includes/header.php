<?php
$current_user = $auth->getCurrentUser();

// Get database instance
$db = Database::getInstance();

// Initialize BinanceAPI safely
$binance = null;
$totalBalance = 0;
$totalPnL = 0;
$settings = null;

try {
    $binance = new BinanceAPI();
    
    // Get settings first
    if (!isset($settings)) {
        $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    }
    if (!$settings) {
        // Create default settings if none exist
        $db->insert('trading_settings', [
            'id' => 1,
            'testnet_mode' => 1,
            'trading_enabled' => 0,
            'ai_enabled' => 1,
            'spot_trading_enabled' => 1,
            'futures_trading_enabled' => 1
        ]);
        $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
    }
    
    // Only try to get account info if credentials are configured
    if ($binance && $binance->hasCredentials()) {
        try {
            $balance = $binance->getAccountInfo();
            
            // Use normalized data from getAccountInfo safely
            if (is_array($balance)) {
                $totalBalance = (float)($balance['availableBalance'] ?? 0);
                $totalPnL = (float)($balance['totalUnrealizedProfit'] ?? 0);
            } else {
                $totalBalance = 0;
                $totalPnL = 0;
                error_log("Account balance is not an array in header: " . print_r($balance, true));
            }
        } catch (Exception $e) {
            // API error - credentials might be invalid
            error_log("Header API error: " . $e->getMessage());
            $totalBalance = 0;
            $totalPnL = 0;
        }
    }
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Header API error: " . $e->getMessage());
    $totalBalance = 0;
    $totalPnL = 0;
    $binance = null;
}
?>
<header class="admin-header">
    <div class="header-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
        <div class="logo">
            <span class="logo-icon">🤖</span>
            <span class="logo-text"><?php echo APP_NAME; ?></span>
        </div>
    </div>
    
    <div class="header-right">
        <div class="header-stats">
            <div class="stat-item">
                <span class="stat-label">Balance:</span>
                <span class="stat-value">$<?php echo number_format($totalBalance, 2); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">P&L:</span>
                <span class="stat-value <?php echo $totalPnL >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $totalPnL >= 0 ? '+' : ''; ?>$<?php echo number_format($totalPnL, 2); ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Status:</span>
                <span class="stat-value" id="connection-status">
                    <?php if ($binance && $binance->hasCredentials()): ?>
                        <span style="color: #10b981;">🟢 Connected</span>
                    <?php else: ?>
                        <span style="color: #f59e0b;">🟡 API Not Set</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($binance && !$binance->hasCredentials()): ?>
                <div class="stat-item">
                    <span class="stat-label" style="color: #f59e0b;">⚠️ API Not Configured</span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="user-menu">
            <div class="user-info">
                <span class="user-icon">👤</span>
                <span class="username"><?php echo htmlspecialchars($current_user['username']); ?></span>
            </div>
            <div class="user-dropdown">
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
                <hr>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>