<aside class="admin-sidebar" id="sidebar">
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="futures-trading.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'futures-trading.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">💹</span>
                    <span class="nav-text">Futures Trading</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="spot-trading.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'spot-trading.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🏪</span>
                    <span class="nav-text">Spot Trading</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="positions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'positions.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📈</span>
                    <span class="nav-text">Positions</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="trades.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'trades.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Trade History</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="signals.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'signals.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🤖</span>
                    <span class="nav-text">AI Signals</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="strategies.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'strategies.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🎯</span>
                    <span class="nav-text">AI Strategies</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="pairs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'pairs.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Trading Pairs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="update-pairs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'update-pairs.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🔄</span>
                    <span class="nav-text">Update Pairs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="performance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'performance.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Performance</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🔧</span>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'logs.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📝</span>
                    <span class="nav-text">System Logs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="system-health.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'system-health.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🏥</span>
                    <span class="nav-text">System Health</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="api-test.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'api-test.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">🔧</span>
                    <span class="nav-text">API Test</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="backup.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">💾</span>
                    <span class="nav-text">Backup</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="documentation.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'documentation.php' ? 'active' : ''; ?>">
                    <span class="nav-icon">📚</span>
                    <span class="nav-text">Documentation</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="system-status">
            <div class="status-item">
                <span class="status-dot active"></span>
                <span class="status-text">System Online</span>
            </div>
            <div class="version-info">
                Version <?php echo APP_VERSION; ?>
            </div>
        </div>
    </div>
</aside>