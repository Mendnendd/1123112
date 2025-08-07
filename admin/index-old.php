<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();
$ai = new AIAnalyzer();

// Get dashboard data
$stats = [
    'total_trades' => $db->fetchOne("SELECT COUNT(*) as count FROM trading_history")['count'],
    'winning_trades' => $db->fetchOne("SELECT COUNT(*) as count FROM trading_history WHERE profit_loss > 0")['count'],
    'total_pnl' => $db->fetchOne("SELECT SUM(profit_loss) as total FROM trading_history")['total'] ?? 0,
    'active_positions' => $db->fetchOne("SELECT COUNT(*) as count FROM positions")['count'],
    'recent_signals' => $db->fetchOne("SELECT COUNT(*) as count FROM ai_signals WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count']
];

$stats['win_rate'] = $stats['total_trades'] > 0 ? ($stats['winning_trades'] / $stats['total_trades']) * 100 : 0;

// Get recent activity
$recent_trades = $db->fetchAll("SELECT * FROM trading_history ORDER BY created_at DESC LIMIT 5");
$recent_signals = $db->fetchAll("SELECT * FROM ai_signals ORDER BY created_at DESC LIMIT 5");
$positions = $db->fetchAll("SELECT * FROM positions ORDER BY unrealized_pnl DESC");

// Get live market data for charts
$chartData = [];
$priceData = [];
try {
    $activePairs = $db->fetchAll("SELECT symbol FROM trading_pairs WHERE enabled = 1 LIMIT 5");
    foreach ($activePairs as $pair) {
        try {
            $klines = $binance->getKlines($pair['symbol'], '1h', 24);
            if (!empty($klines) && is_array($klines)) {
                $chartData[$pair['symbol']] = array_map(function($kline) {
                    return [
                        'time' => $kline[0],
                        'open' => (float)$kline[1],
                        'high' => (float)$kline[2],
                        'low' => (float)$kline[3],
                        'close' => (float)$kline[4],
                        'volume' => (float)$kline[5]
                    ];
                }, $klines);
            }
        } catch (Exception $e) {
            error_log("Error getting klines for {$pair['symbol']}: " . $e->getMessage());
        }
        
        // Get current price
        try {
            $ticker = $binance->get24hrTicker($pair['symbol']);
            if (!empty($ticker) && is_array($ticker) && isset($ticker[0])) {
                $priceData[$pair['symbol']] = [
                    'price' => (float)$ticker[0]['lastPrice'],
                    'change' => (float)$ticker[0]['priceChangePercent'],
                    'volume' => (float)$ticker[0]['volume']
                ];
            }
        } catch (Exception $e) {
            error_log("Error getting ticker for {$pair['symbol']}: " . $e->getMessage());
            // Provide fallback data
            $priceData[$pair['symbol']] = [
                'price' => 0,
                'change' => 0,
                'volume' => 0
            ];
        }
    }
} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
}

// Auto-update portfolio data
try {
    if ($binance->hasCredentials()) {
        $bot = new TradingBot();
        $bot->updatePositions();
        $bot->updateBalance();
    }
} catch (Exception $e) {
    error_log("Auto portfolio update error: " . $e->getMessage());
}

// Get balance data for chart with error handling
try {
    $balanceHistory = $db->fetchAll("
        SELECT DATE(created_at) as date, 
               AVG(total_wallet_balance) as balance,
               AVG(total_unrealized_pnl) as pnl
        FROM balance_history 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
} catch (Exception $e) {
    error_log("Balance history error: " . $e->getMessage());
    $balanceHistory = [];
}

// Ensure we have some default data for charts if empty
if (empty($balanceHistory)) {
    $balanceHistory = [
        [
            'date' => date('Y-m-d', strtotime('-7 days')),
            'balance' => 1000,
            'pnl' => 0
        ],
        [
            'date' => date('Y-m-d'),
            'balance' => 1000,
            'pnl' => 0
        ]
    ];
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
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Trading Dashboard</h1>
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
                    <span class="status-dot <?php echo $settings['testnet_mode'] ? 'warning' : 'active'; ?>"></span>
                    Mode: <?php echo $settings['testnet_mode'] ? 'Testnet' : 'Live'; ?>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>Total P&L</h3>
                    <div class="stat-value <?php echo $stats['total_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $stats['total_pnl'] >= 0 ? '+' : ''; ?>$<?php echo number_format($stats['total_pnl'], 2); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3>Win Rate</h3>
                    <div class="stat-value"><?php echo number_format($stats['win_rate'], 1); ?>%</div>
                    <div class="stat-subtitle"><?php echo $stats['winning_trades']; ?>/<?php echo $stats['total_trades']; ?> trades</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-content">
                    <h3>Active Positions</h3>
                    <div class="stat-value"><?php echo $stats['active_positions']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🤖</div>
                <div class="stat-content">
                    <h3>AI Signals (24h)</h3>
                    <div class="stat-value"><?php echo $stats['recent_signals']; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Live Charts Section -->
        <div class="charts-section">
            <div class="charts-grid">
                <!-- Balance Chart -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>📈 Account Balance (30 Days)</h3>
                        <button onclick="refreshBalanceChart()" class="btn btn-sm">Refresh</button>
                    </div>
                    <div class="card-content">
                        <canvas id="balanceChart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Live Prices -->
                <div class="chart-card">
                    <div class="card-header">
                        <h3>💹 Live Prices</h3>
                        <button onclick="refreshPrices()" class="btn btn-sm">Refresh</button>
                    </div>
                    <div class="card-content">
                        <div id="livePrices" class="live-prices-grid">
                            <?php foreach ($priceData as $symbol => $data): ?>
                                <div class="price-item" data-symbol="<?php echo $symbol; ?>">
                                    <div class="price-symbol"><?php echo $symbol; ?></div>
                                    <div class="price-value">$<?php echo number_format($data['price'], 4); ?></div>
                                    <div class="price-change <?php echo $data['change'] >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php echo $data['change'] >= 0 ? '+' : ''; ?><?php echo number_format($data['change'], 2); ?>%
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Position Performance Chart -->
            <div class="chart-card full-width">
                <div class="card-header">
                    <h3>📊 Active Positions Performance</h3>
                    <button onclick="refreshPositionsChart()" class="btn btn-sm">Refresh</button>
                </div>
                <div class="card-content">
                    <canvas id="positionsChart" width="800" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- Recent Trades -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Trades</h3>
                    <a href="trades.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($recent_trades)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p>No trades yet</p>
                        </div>
                    <?php else: ?>
                        <div class="trades-list">
                            <?php foreach ($recent_trades as $trade): ?>
                                <div class="trade-item">
                                    <div class="trade-info">
                                        <span class="trade-symbol"><?php echo $trade['symbol']; ?></span>
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
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- AI Signals -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent AI Signals</h3>
                    <a href="signals.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($recent_signals)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🤖</div>
                            <p>No AI signals yet</p>
                        </div>
                    <?php else: ?>
                        <div class="signals-list">
                            <?php foreach ($recent_signals as $signal): ?>
                                <div class="signal-item">
                                    <div class="signal-info">
                                        <span class="signal-symbol"><?php echo $signal['symbol']; ?></span>
                                        <span class="signal-type <?php echo strtolower($signal['signal']); ?>">
                                            <?php echo $signal['signal']; ?>
                                        </span>
                                    </div>
                                    <div class="signal-meta">
                                        <div class="confidence-bar">
                                            <div class="confidence-fill" style="width: <?php echo ($signal['confidence'] * 100); ?>%"></div>
                                        </div>
                                        <span class="confidence-text"><?php echo number_format($signal['confidence'] * 100, 1); ?>%</span>
                                        <span class="signal-time"><?php echo date('H:i', strtotime($signal['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Active Positions -->
            <div class="dashboard-card full-width">
                <div class="card-header">
                    <h3>Active Positions</h3>
                    <a href="positions.php" class="btn btn-sm">Manage All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($positions)): ?>
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
                                        <th>Side</th>
                                        <th>Size</th>
                                        <th>Entry Price</th>
                                        <th>Mark Price</th>
                                        <th>P&L</th>
                                        <th>P&L %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($positions as $position): ?>
                                        <tr>
                                            <td><strong><?php echo $position['symbol']; ?></strong></td>
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
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <script>
        // Chart data from PHP
        const balanceData = <?php echo json_encode($balanceHistory); ?>;
        const positionsData = <?php echo json_encode($positions); ?>;
        const priceData = <?php echo json_encode($priceData); ?>;
        
        // Initialize charts
        let balanceChart, positionsChart;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            startLiveUpdates();
        });
        
        function initializeCharts() {
            // Balance Chart
            const balanceCtx = document.getElementById('balanceChart').getContext('2d');
            balanceChart = new Chart(balanceCtx, {
                type: 'line',
                data: {
                    labels: balanceData.map(item => item.date),
                    datasets: [{
                        label: 'Balance',
                        data: balanceData.map(item => parseFloat(item.balance)),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'P&L',
                        data: balanceData.map(item => parseFloat(item.pnl)),
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
            
            // Positions Chart
            const positionsCtx = document.getElementById('positionsChart').getContext('2d');
            positionsChart = new Chart(positionsCtx, {
                type: 'bar',
                data: {
                    labels: positionsData.map(pos => pos.symbol),
                    datasets: [{
                        label: 'Unrealized P&L',
                        data: positionsData.map(pos => parseFloat(pos.unrealized_pnl)),
                        backgroundColor: positionsData.map(pos => 
                            parseFloat(pos.unrealized_pnl) >= 0 ? '#10b981' : '#ef4444'
                        ),
                        borderColor: positionsData.map(pos => 
                            parseFloat(pos.unrealized_pnl) >= 0 ? '#059669' : '#dc2626'
                        ),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        
        function startLiveUpdates() {
            // Update prices every 10 seconds
            setInterval(refreshPrices, 10000);
            
            // Update charts every 30 seconds
            setInterval(function() {
                refreshBalanceChart();
                refreshPositionsChart();
            }, 30000);
        }
        
        function refreshPrices() {
            fetch('api/live-prices.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updatePriceDisplay(data.prices);
                    }
                })
                .catch(error => console.error('Error fetching prices:', error));
        }
        
        function updatePriceDisplay(prices) {
            Object.keys(prices).forEach(symbol => {
                const priceItem = document.querySelector(`[data-symbol="${symbol}"]`);
                if (priceItem) {
                    const priceValue = priceItem.querySelector('.price-value');
                    const priceChange = priceItem.querySelector('.price-change');
                    
                    if (priceValue) {
                        priceValue.textContent = '$' + parseFloat(prices[symbol].price).toFixed(4);
                    }
                    
                    if (priceChange) {
                        const change = parseFloat(prices[symbol].change);
                        priceChange.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
                        priceChange.className = 'price-change ' + (change >= 0 ? 'positive' : 'negative');
                    }
                }
            });
        }
        
        function refreshBalanceChart() {
            fetch('api/balance-history.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && balanceChart) {
                        balanceChart.data.labels = data.history.map(item => item.date);
                        balanceChart.data.datasets[0].data = data.history.map(item => parseFloat(item.balance));
                        balanceChart.data.datasets[1].data = data.history.map(item => parseFloat(item.pnl));
                        balanceChart.update();
                    }
                })
                .catch(error => console.error('Error fetching balance history:', error));
        }
        
        function refreshPositionsChart() {
            fetch('api/positions.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && positionsChart) {
                        positionsChart.data.labels = data.positions.map(pos => pos.symbol);
                        positionsChart.data.datasets[0].data = data.positions.map(pos => parseFloat(pos.unrealized_pnl));
                        positionsChart.data.datasets[0].backgroundColor = data.positions.map(pos => 
                            parseFloat(pos.unrealized_pnl) >= 0 ? '#10b981' : '#ef4444'
                        );
                        positionsChart.update();
                    }
                })
                .catch(error => console.error('Error fetching positions:', error));
        }
    </script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>