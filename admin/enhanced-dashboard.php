<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();
$spotAPI = new SpotTradingAPI();

// Initialize enhanced AI safely
$enhancedAI = null;
try {
    $enhancedAI = new EnhancedAIAnalyzer();
} catch (Exception $e) {
    error_log("Failed to initialize EnhancedAIAnalyzer in enhanced-dashboard: " . $e->getMessage());
    $enhancedAI = null;
}

// Get enhanced dashboard data
$dashboardData = $db->fetchOne("SELECT * FROM dashboard_summary");
$portfolioData = $db->fetchOne("SELECT * FROM portfolio_overview");

// Get recent enhanced signals
$recentSignals = $db->fetchAll("
    SELECT * FROM ai_signals 
    ORDER BY created_at DESC 
    LIMIT 10
");

// Get performance metrics
$performanceMetrics = $db->fetchAll("
    SELECT * FROM performance_metrics 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date DESC
");

// Get active positions with enhanced data
$activePositions = $db->fetchAll("
    SELECT * FROM positions 
    WHERE position_amt != 0 
    ORDER BY unrealized_pnl DESC
");

// Get recent trades with enhanced data
$recentTrades = $db->fetchAll("
    SELECT * FROM trading_history 
    ORDER BY created_at DESC 
    LIMIT 10
");

// Get spot balances
try {
    $spotBalances = $db->fetchAll("
        SELECT * FROM spot_balances 
        WHERE total > 0 
        ORDER BY usdt_value DESC
    ");
} catch (Exception $e) {
    $spotBalances = [];
}

// Get notifications
$notifications = $db->fetchAll("
    SELECT * FROM notifications 
    WHERE read_at IS NULL 
    ORDER BY priority DESC, created_at DESC 
    LIMIT 5
");

// Get market data for charts
$chartData = [];
$priceData = [];
try {
    $activePairs = $db->fetchAll("SELECT symbol FROM trading_pairs WHERE enabled = 1 LIMIT 8");
    foreach ($activePairs as $pair) {
        try {
            // Get both spot and futures data
            $futuresKlines = $binance->getKlines($pair['symbol'], '1h', 24);
            $spotKlines = $spotAPI->getSpotKlines($pair['symbol'], '1h', 24);
            
            if (!empty($futuresKlines)) {
                $chartData[$pair['symbol']]['futures'] = array_map(function($kline) {
                    return [
                        'time' => $kline[0],
                        'open' => (float)$kline[1],
                        'high' => (float)$kline[2],
                        'low' => (float)$kline[3],
                        'close' => (float)$kline[4],
                        'volume' => (float)$kline[5]
                    ];
                }, $futuresKlines);
            }
            
            if (!empty($spotKlines)) {
                $chartData[$pair['symbol']]['spot'] = array_map(function($kline) {
                    return [
                        'time' => $kline[0],
                        'open' => (float)$kline[1],
                        'high' => (float)$kline[2],
                        'low' => (float)$kline[3],
                        'close' => (float)$kline[4],
                        'volume' => (float)$kline[5]
                    ];
                }, $spotKlines);
            }
        } catch (Exception $e) {
            error_log("Error getting chart data for {$pair['symbol']}: " . $e->getMessage());
        }
        
        // Get current prices
        try {
            $futuresTicker = $binance->get24hrTicker($pair['symbol']);
            $spotTicker = $spotAPI->getSpotTicker($pair['symbol']);
            
            $priceData[$pair['symbol']] = [
                'futures' => [
                    'price' => (float)($futuresTicker[0]['lastPrice'] ?? 0),
                    'change' => (float)($futuresTicker[0]['priceChangePercent'] ?? 0),
                    'volume' => (float)($futuresTicker[0]['volume'] ?? 0)
                ],
                'spot' => [
                    'price' => (float)($spotTicker[0]['lastPrice'] ?? 0),
                    'change' => (float)($spotTicker[0]['priceChangePercent'] ?? 0),
                    'volume' => (float)($spotTicker[0]['volume'] ?? 0)
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting price data for {$pair['symbol']}: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
}

// Get settings
$settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Dashboard - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/enhanced-dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Enhanced Trading Dashboard</h1>
            <div class="status-indicators">
                <div class="status-item">
                    <span class="status-dot <?php echo $settings['trading_enabled'] ? 'active' : 'inactive'; ?>"></span>
                    Trading: <?php echo $settings['trading_enabled'] ? 'Active' : 'Paused'; ?>
                </div>
                <div class="status-item">
                    <span class="status-dot <?php echo $settings['ai_enabled'] ? 'active' : 'inactive'; ?>"></span>
                    AI: <?php echo $settings['ai_enabled'] ? 'Enabled' : 'Disabled'; ?>
                </div>
                <div class="status-item">
                    <span class="status-dot <?php echo $settings['spot_trading_enabled'] ? 'active' : 'inactive'; ?>"></span>
                    Spot: <?php echo $settings['spot_trading_enabled'] ? 'Enabled' : 'Disabled'; ?>
                </div>
                <div class="status-item">
                    <span class="status-dot <?php echo $settings['futures_trading_enabled'] ? 'active' : 'inactive'; ?>"></span>
                    Futures: <?php echo $settings['futures_trading_enabled'] ? 'Enabled' : 'Disabled'; ?>
                </div>
                <div class="status-item">
                    <span class="status-dot <?php echo $settings['testnet_mode'] ? 'warning' : 'active'; ?>"></span>
                    Mode: <?php echo $settings['testnet_mode'] ? 'Testnet' : 'Live'; ?>
                </div>
            </div>
        </div>
        
        <!-- Enhanced Stats Grid -->
        <div class="enhanced-stats-grid">
            <div class="stat-card portfolio-value">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>Total Portfolio Value</h3>
                    <div class="stat-value">$<?php echo number_format($portfolioData['total_portfolio_value'] ?? 0, 2); ?></div>
                    <div class="stat-change <?php echo ($portfolioData['daily_pnl'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($portfolioData['daily_pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($portfolioData['daily_pnl'] ?? 0, 2); ?> 
                        (<?php echo number_format($portfolioData['daily_pnl_percentage'] ?? 0, 2); ?>%)
                    </div>
                </div>
            </div>
            
            <div class="stat-card spot-balance">
                <div class="stat-icon">🏪</div>
                <div class="stat-content">
                    <h3>Spot Balance</h3>
                    <div class="stat-value">$<?php echo number_format($portfolioData['spot_balance_usdt'] ?? 0, 2); ?></div>
                    <div class="stat-subtitle">Available for spot trading</div>
                </div>
            </div>
            
            <div class="stat-card futures-balance">
                <div class="stat-icon">⚡</div>
                <div class="stat-content">
                    <h3>Futures Balance</h3>
                    <div class="stat-value">$<?php echo number_format($portfolioData['futures_balance_usdt'] ?? 0, 2); ?></div>
                    <div class="stat-subtitle">Available for futures trading</div>
                </div>
            </div>
            
            <div class="stat-card unrealized-pnl">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3>Unrealized P&L</h3>
                    <div class="stat-value <?php echo ($portfolioData['total_unrealized_pnl'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($portfolioData['total_unrealized_pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($portfolioData['total_unrealized_pnl'] ?? 0, 2); ?>
                    </div>
                    <div class="stat-subtitle"><?php echo $portfolioData['active_positions'] ?? 0; ?> active positions</div>
                </div>
            </div>
            
            <div class="stat-card today-trades">
                <div class="stat-icon">🔄</div>
                <div class="stat-content">
                    <h3>Today's Trades</h3>
                    <div class="stat-value"><?php echo $dashboardData['today_trades'] ?? 0; ?></div>
                    <div class="stat-subtitle <?php echo ($dashboardData['today_pnl'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        P&L: <?php echo ($dashboardData['today_pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($dashboardData['today_pnl'] ?? 0, 2); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card ai-signals">
                <div class="stat-icon">🤖</div>
                <div class="stat-content">
                    <h3>AI Signals (24h)</h3>
                    <div class="stat-value"><?php echo $dashboardData['today_signals'] ?? 0; ?></div>
                    <div class="stat-subtitle">Avg Confidence: <?php echo number_format(($dashboardData['avg_confidence'] ?? 0) * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <!-- Notifications Bar -->
        <?php if (!empty($notifications)): ?>
        <div class="notifications-bar">
            <div class="notifications-header">
                <h3>🔔 Recent Notifications</h3>
                <button onclick="markAllAsRead()" class="btn btn-sm">Mark All Read</button>
            </div>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item priority-<?php echo strtolower($notification['priority']); ?>">
                        <div class="notification-content">
                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        </div>
                        <div class="notification-time">
                            <?php echo date('H:i', strtotime($notification['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Enhanced Charts Section -->
        <div class="enhanced-charts-section">
            <div class="charts-grid">
                <!-- Portfolio Performance Chart -->
                <div class="chart-card full-width">
                    <div class="card-header">
                        <h3>📈 Portfolio Performance (30 Days)</h3>
                        <div class="chart-controls">
                            <button onclick="toggleChartType('portfolio')" class="btn btn-sm">Toggle View</button>
                            <button onclick="refreshPortfolioChart()" class="btn btn-sm">Refresh</button>
                        </div>
                    </div>
                    <div class="card-content">
                        <canvas id="portfolioChart" width="800" height="300"></canvas>
                    </div>
                </div>
                
                <!-- Market Overview -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>💹 Market Overview</h3>
                        <button onclick="refreshMarketData()" class="btn btn-sm">Refresh</button>
                    </div>
                    <div class="card-content">
                        <div id="marketOverview" class="market-overview-grid">
                            <?php foreach ($priceData as $symbol => $data): ?>
                                <div class="market-item" data-symbol="<?php echo $symbol; ?>">
                                    <div class="market-symbol"><?php echo $symbol; ?></div>
                                    <div class="market-prices">
                                        <div class="price-row">
                                            <span class="price-label">Futures:</span>
                                            <span class="price-value">$<?php echo number_format($data['futures']['price'], 4); ?></span>
                                            <span class="price-change <?php echo $data['futures']['change'] >= 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo $data['futures']['change'] >= 0 ? '+' : ''; ?><?php echo number_format($data['futures']['change'], 2); ?>%
                                            </span>
                                        </div>
                                        <div class="price-row">
                                            <span class="price-label">Spot:</span>
                                            <span class="price-value">$<?php echo number_format($data['spot']['price'], 4); ?></span>
                                            <span class="price-change <?php echo $data['spot']['change'] >= 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo $data['spot']['change'] >= 0 ? '+' : ''; ?><?php echo number_format($data['spot']['change'], 2); ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- AI Signals Performance -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>🎯 AI Signals Performance</h3>
                        <button onclick="refreshSignalsChart()" class="btn btn-sm">Refresh</button>
                    </div>
                    <div class="card-content">
                        <canvas id="signalsChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Enhanced Dashboard Grid -->
        <div class="enhanced-dashboard-grid">
            <!-- Active Positions -->
            <div class="dashboard-card positions-card">
                <div class="card-header">
                    <h3>📈 Active Positions</h3>
                    <a href="positions.php" class="btn btn-sm">Manage All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($activePositions)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📈</div>
                            <p>No active positions</p>
                        </div>
                    <?php else: ?>
                        <div class="positions-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Type</th>
                                        <th>Side</th>
                                        <th>Size</th>
                                        <th>Entry</th>
                                        <th>Current</th>
                                        <th>P&L</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activePositions as $position): ?>
                                        <tr>
                                            <td><strong><?php echo $position['symbol']; ?></strong></td>
                                            <td>
                                                <span class="trading-type <?php echo strtolower($position['trading_type']); ?>">
                                                    <?php echo $position['trading_type']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="position-side <?php echo strtolower($position['side']); ?>">
                                                    <?php echo $position['side']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo abs($position['position_amt']); ?></td>
                                            <td>$<?php echo number_format($position['entry_price'], 2); ?></td>
                                            <td>$<?php echo number_format($position['mark_price'], 2); ?></td>
                                            <td class="<?php echo $position['unrealized_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo $position['unrealized_pnl'] >= 0 ? '+' : ''; ?>$<?php echo number_format($position['unrealized_pnl'], 2); ?>
                                            </td>
                                            <td class="<?php echo $position['percentage'] >= 0 ? 'positive' : 'negative'; ?>">
                                                <?php echo $position['percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($position['percentage'], 2); ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Enhanced AI Signals -->
            <div class="dashboard-card signals-card">
                <div class="card-header">
                    <h3>🤖 Recent AI Signals</h3>
                    <a href="signals.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($recentSignals)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🤖</div>
                            <p>No AI signals yet</p>
                        </div>
                    <?php else: ?>
                        <div class="signals-list">
                            <?php foreach ($recentSignals as $signal): ?>
                                <div class="signal-item">
                                    <div class="signal-info">
                                        <span class="signal-symbol"><?php echo $signal['symbol']; ?></span>
                                        <span class="signal-type <?php echo strtolower($signal['signal']); ?>">
                                            <?php echo $signal['signal']; ?>
                                        </span>
                                        <span class="signal-strength <?php echo strtolower($signal['strength']); ?>">
                                            <?php echo $signal['strength']; ?>
                                        </span>
                                        <span class="trading-type <?php echo strtolower($signal['trading_type']); ?>">
                                            <?php echo $signal['trading_type']; ?>
                                        </span>
                                    </div>
                                    <div class="signal-meta">
                                        <div class="confidence-display">
                                            <div class="confidence-bar">
                                                <div class="confidence-fill" style="width: <?php echo ($signal['confidence'] * 100); ?>%"></div>
                                            </div>
                                            <span class="confidence-text"><?php echo number_format($signal['confidence'] * 100, 1); ?>%</span>
                                        </div>
                                        <span class="signal-time"><?php echo date('H:i', strtotime($signal['created_at'])); ?></span>
                                        <?php if ($signal['executed']): ?>
                                            <span class="executed-badge">✓</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Spot Balances -->
            <div class="dashboard-card balances-card">
                <div class="card-header">
                    <h3>🏪 Spot Balances</h3>
                    <button onclick="refreshSpotBalances()" class="btn btn-sm">Refresh</button>
                </div>
                <div class="card-content">
                    <?php if (empty($spotBalances)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🏪</div>
                            <p>No spot balances</p>
                        </div>
                    <?php else: ?>
                        <div class="balances-list">
                            <?php foreach ($spotBalances as $balance): ?>
                                <div class="balance-item">
                                    <div class="balance-info">
                                        <span class="balance-asset"><?php echo $balance['asset']; ?></span>
                                        <span class="balance-amount"><?php echo number_format($balance['total'], 6); ?></span>
                                    </div>
                                    <div class="balance-value">
                                        $<?php echo number_format($balance['usdt_value'], 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Trades -->
            <div class="dashboard-card trades-card">
                <div class="card-header">
                    <h3>📋 Recent Trades</h3>
                    <a href="trades.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($recentTrades)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p>No trades yet</p>
                        </div>
                    <?php else: ?>
                        <div class="trades-list">
                            <?php foreach ($recentTrades as $trade): ?>
                                <div class="trade-item">
                                    <div class="trade-info">
                                        <span class="trade-symbol"><?php echo $trade['symbol']; ?></span>
                                        <span class="trading-type <?php echo strtolower($trade['trading_type']); ?>">
                                            <?php echo $trade['trading_type']; ?>
                                        </span>
                                        <span class="trade-side <?php echo strtolower($trade['side']); ?>">
                                            <?php echo $trade['side']; ?>
                                        </span>
                                        <span class="trade-quantity"><?php echo $trade['quantity']; ?></span>
                                    </div>
                                    <div class="trade-meta">
                                        <span class="trade-pnl <?php echo ($trade['profit_loss'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo ($trade['profit_loss'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($trade['profit_loss'] ?? 0, 2); ?>
                                        </span>
                                        <span class="trade-time"><?php echo date('M j, H:i', strtotime($trade['created_at'])); ?></span>
                                        <?php if ($trade['strategy_used']): ?>
                                            <span class="strategy-badge"><?php echo $trade['strategy_used']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/enhanced-dashboard.js"></script>
    <script>
        // Chart data from PHP
        const performanceData = <?php echo json_encode($performanceMetrics); ?>;
        const portfolioData = <?php echo json_encode($portfolioData); ?>;
        const priceData = <?php echo json_encode($priceData); ?>;
        const chartData = <?php echo json_encode($chartData); ?>;
        
        // Initialize enhanced dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeEnhancedCharts();
            startEnhancedLiveUpdates();
        });
        
        function initializeEnhancedCharts() {
            // Portfolio Performance Chart
            const portfolioCtx = document.getElementById('portfolioChart').getContext('2d');
            new Chart(portfolioCtx, {
                type: 'line',
                data: {
                    labels: performanceData.map(item => item.date),
                    datasets: [{
                        label: 'Portfolio Value',
                        data: performanceData.map(item => parseFloat(item.ending_balance)),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Daily P&L',
                        data: performanceData.map(item => parseFloat(item.total_pnl)),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
            
            // AI Signals Performance Chart
            const signalsCtx = document.getElementById('signalsChart').getContext('2d');
            new Chart(signalsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Executed', 'Pending', 'Successful'],
                    datasets: [{
                        data: [
                            performanceData.reduce((sum, item) => sum + parseInt(item.ai_signals_executed), 0),
                            performanceData.reduce((sum, item) => sum + parseInt(item.ai_signals_generated) - parseInt(item.ai_signals_executed), 0),
                            performanceData.reduce((sum, item) => sum + parseInt(item.winning_trades), 0)
                        ],
                        backgroundColor: ['#3b82f6', '#f59e0b', '#10b981'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        function startEnhancedLiveUpdates() {
            // Update market data every 10 seconds
            setInterval(refreshMarketData, 10000);
            
            // Update portfolio data every 30 seconds
            setInterval(refreshPortfolioData, 30000);
            
            // Update notifications every 60 seconds
            setInterval(refreshNotifications, 60000);
        }
        
        function refreshMarketData() {
            fetch('../api/enhanced-market-data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMarketDisplay(data.prices);
                    }
                })
                .catch(error => console.error('Error fetching market data:', error));
        }
        
        function refreshPortfolioData() {
            fetch('../api/portfolio-data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updatePortfolioDisplay(data.portfolio);
                    }
                })
                .catch(error => console.error('Error fetching portfolio data:', error));
        }
        
        function refreshSpotBalances() {
            fetch('../api/spot-balances.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateSpotBalancesDisplay(data.balances);
                    }
                })
                .catch(error => console.error('Error fetching spot balances:', error));
        }
        
        function refreshNotifications() {
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationsDisplay(data.notifications);
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }
        
        function markAllAsRead() {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'mark_all_read' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector('.notifications-bar').style.display = 'none';
                }
            })
            .catch(error => console.error('Error marking notifications as read:', error));
        }
        
        function updateMarketDisplay(prices) {
            Object.keys(prices).forEach(symbol => {
                const marketItem = document.querySelector(`[data-symbol="${symbol}"]`);
                if (marketItem) {
                    // Update futures price
                    const futuresPrice = marketItem.querySelector('.price-row:first-child .price-value');
                    const futuresChange = marketItem.querySelector('.price-row:first-child .price-change');
                    
                    if (futuresPrice) {
                        futuresPrice.textContent = '$' + parseFloat(prices[symbol].futures.price).toFixed(4);
                    }
                    
                    if (futuresChange) {
                        const change = parseFloat(prices[symbol].futures.change);
                        futuresChange.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
                        futuresChange.className = 'price-change ' + (change >= 0 ? 'positive' : 'negative');
                    }
                    
                    // Update spot price
                    const spotPrice = marketItem.querySelector('.price-row:last-child .price-value');
                    const spotChange = marketItem.querySelector('.price-row:last-child .price-change');
                    
                    if (spotPrice) {
                        spotPrice.textContent = '$' + parseFloat(prices[symbol].spot.price).toFixed(4);
                    }
                    
                    if (spotChange) {
                        const change = parseFloat(prices[symbol].spot.change);
                        spotChange.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
                        spotChange.className = 'price-change ' + (change >= 0 ? 'positive' : 'negative');
                    }
                }
            });
        }
        
        // Auto-refresh dashboard every 60 seconds
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>