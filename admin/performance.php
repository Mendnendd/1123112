<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Get performance data
$performanceData = $db->fetchAll("
    SELECT * FROM performance_metrics 
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date DESC
");

// Get strategy performance
$strategyPerformance = $db->fetchAll("
    SELECT 
        strategy_used,
        COUNT(*) as total_trades,
        SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(profit_loss) as total_pnl,
        AVG(profit_loss) as avg_pnl,
        AVG(confidence_score) as avg_confidence
    FROM trading_history 
    WHERE strategy_used IS NOT NULL 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY strategy_used
    ORDER BY total_pnl DESC
");

// Get trading type performance
$typePerformance = $db->fetchAll("
    SELECT 
        trading_type,
        COUNT(*) as total_trades,
        SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(profit_loss) as total_pnl,
        AVG(profit_loss) as avg_pnl
    FROM trading_history 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY trading_type
    ORDER BY total_pnl DESC
");

// Calculate overall metrics
$overallMetrics = $db->fetchOne("
    SELECT 
        COUNT(*) as total_trades,
        SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(profit_loss) as total_pnl,
        AVG(profit_loss) as avg_pnl,
        MAX(profit_loss) as best_trade,
        MIN(profit_loss) as worst_trade,
        STDDEV(profit_loss) as pnl_stddev
    FROM trading_history 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

$winRate = $overallMetrics['total_trades'] > 0 ? 
    ($overallMetrics['winning_trades'] / $overallMetrics['total_trades']) * 100 : 0;

// Calculate Sharpe ratio (simplified)
$sharpeRatio = $overallMetrics['pnl_stddev'] > 0 ? 
    $overallMetrics['avg_pnl'] / $overallMetrics['pnl_stddev'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/enhanced-dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Performance Analytics</h1>
            <div class="time-range-selector">
                <select onchange="changeTimeRange(this.value)">
                    <option value="30">Last 30 Days</option>
                    <option value="7">Last 7 Days</option>
                    <option value="90">Last 90 Days</option>
                </select>
            </div>
        </div>
        
        <!-- Overall Performance Stats -->
        <div class="performance-stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3>Total Trades</h3>
                    <div class="stat-value"><?php echo $overallMetrics['total_trades']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <h3>Win Rate</h3>
                    <div class="stat-value"><?php echo number_format($winRate, 1); ?>%</div>
                    <div class="stat-subtitle"><?php echo $overallMetrics['winning_trades']; ?> winning trades</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3>Total P&L</h3>
                    <div class="stat-value <?php echo ($overallMetrics['total_pnl'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($overallMetrics['total_pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($overallMetrics['total_pnl'] ?? 0, 2); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-content">
                    <h3>Avg P&L per Trade</h3>
                    <div class="stat-value <?php echo ($overallMetrics['avg_pnl'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo ($overallMetrics['avg_pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($overallMetrics['avg_pnl'] ?? 0, 2); ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-content">
                    <h3>Best Trade</h3>
                    <div class="stat-value positive">+$<?php echo number_format($overallMetrics['best_trade'] ?? 0, 2); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📉</div>
                <div class="stat-content">
                    <h3>Worst Trade</h3>
                    <div class="stat-value negative">$<?php echo number_format($overallMetrics['worst_trade'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Performance Charts -->
        <div class="performance-charts-grid">
            <div class="chart-card">
                <div class="card-header">
                    <h3>📈 Daily Performance</h3>
                </div>
                <div class="card-content">
                    <canvas id="dailyPerformanceChart" width="400" height="300"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="card-header">
                    <h3>🎯 Strategy Performance</h3>
                </div>
                <div class="card-content">
                    <canvas id="strategyPerformanceChart" width="400" height="300"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="card-header">
                    <h3>🏪 Spot vs Futures</h3>
                </div>
                <div class="card-content">
                    <canvas id="typePerformanceChart" width="400" height="300"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="card-header">
                    <h3>📊 Win Rate Trend</h3>
                </div>
                <div class="card-content">
                    <canvas id="winRateChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Detailed Performance Tables -->
        <div class="performance-tables-grid">
            <!-- Strategy Performance Table -->
            <div class="performance-table-card">
                <div class="card-header">
                    <h3>Strategy Performance Breakdown</h3>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Strategy</th>
                                    <th>Trades</th>
                                    <th>Win Rate</th>
                                    <th>Total P&L</th>
                                    <th>Avg P&L</th>
                                    <th>Avg Confidence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($strategyPerformance as $strategy): ?>
                                    <tr>
                                        <td><strong><?php echo $strategy['strategy_used']; ?></strong></td>
                                        <td><?php echo $strategy['total_trades']; ?></td>
                                        <td><?php echo number_format(($strategy['winning_trades'] / $strategy['total_trades']) * 100, 1); ?>%</td>
                                        <td class="<?php echo $strategy['total_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $strategy['total_pnl'] >= 0 ? '+' : ''; ?>$<?php echo number_format($strategy['total_pnl'], 2); ?>
                                        </td>
                                        <td class="<?php echo $strategy['avg_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $strategy['avg_pnl'] >= 0 ? '+' : ''; ?>$<?php echo number_format($strategy['avg_pnl'], 2); ?>
                                        </td>
                                        <td><?php echo number_format($strategy['avg_confidence'] * 100, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Trading Type Performance Table -->
            <div class="performance-table-card">
                <div class="card-header">
                    <h3>Spot vs Futures Performance</h3>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Trades</th>
                                    <th>Win Rate</th>
                                    <th>Total P&L</th>
                                    <th>Avg P&L</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($typePerformance as $type): ?>
                                    <tr>
                                        <td>
                                            <span class="trading-type <?php echo strtolower($type['trading_type']); ?>">
                                                <?php echo $type['trading_type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $type['total_trades']; ?></td>
                                        <td><?php echo number_format(($type['winning_trades'] / $type['total_trades']) * 100, 1); ?>%</td>
                                        <td class="<?php echo $type['total_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $type['total_pnl'] >= 0 ? '+' : ''; ?>$<?php echo number_format($type['total_pnl'], 2); ?>
                                        </td>
                                        <td class="<?php echo $type['avg_pnl'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo $type['avg_pnl'] >= 0 ? '+' : ''; ?>$<?php echo number_format($type['avg_pnl'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <script>
        // Chart data from PHP
        const performanceData = <?php echo json_encode($performanceData); ?>;
        const strategyData = <?php echo json_encode($strategyPerformance); ?>;
        const typeData = <?php echo json_encode($typePerformance); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializePerformanceCharts();
        });
        
        function initializePerformanceCharts() {
            // Daily Performance Chart
            const dailyCtx = document.getElementById('dailyPerformanceChart').getContext('2d');
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: performanceData.map(item => item.date),
                    datasets: [{
                        label: 'Daily P&L',
                        data: performanceData.map(item => parseFloat(item.total_pnl)),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
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
                    }
                }
            });
            
            // Strategy Performance Chart
            const strategyCtx = document.getElementById('strategyPerformanceChart').getContext('2d');
            new Chart(strategyCtx, {
                type: 'bar',
                data: {
                    labels: strategyData.map(item => item.strategy_used),
                    datasets: [{
                        label: 'Total P&L',
                        data: strategyData.map(item => parseFloat(item.total_pnl)),
                        backgroundColor: strategyData.map(item => 
                            parseFloat(item.total_pnl) >= 0 ? '#10b981' : '#ef4444'
                        )
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
                    }
                }
            });
            
            // Type Performance Chart
            const typeCtx = document.getElementById('typePerformanceChart').getContext('2d');
            new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: typeData.map(item => item.trading_type),
                    datasets: [{
                        data: typeData.map(item => parseFloat(item.total_pnl)),
                        backgroundColor: ['#f59e0b', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Win Rate Trend Chart
            const winRateCtx = document.getElementById('winRateChart').getContext('2d');
            new Chart(winRateCtx, {
                type: 'line',
                data: {
                    labels: performanceData.map(item => item.date),
                    datasets: [{
                        label: 'Win Rate %',
                        data: performanceData.map(item => parseFloat(item.win_rate)),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function changeTimeRange(days) {
            window.location.href = '?days=' + days;
        }
    </script>
    <style>
        .performance-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .performance-charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .performance-tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
        }
        
        .performance-table-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-container th,
        .table-container td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .table-container th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .time-range-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .time-range-selector select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .performance-charts-grid {
                grid-template-columns: 1fr;
            }
            
            .performance-tables-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>