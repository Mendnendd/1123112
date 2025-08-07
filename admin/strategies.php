<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

$success = '';
$error = '';

// Handle strategy management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'toggle_strategy':
            $strategyId = (int)$_POST['strategy_id'];
            $enabled = isset($_POST['enabled']) ? 1 : 0;
            
            try {
                $db->update('trading_strategies', ['enabled' => $enabled], 'id = ?', [$strategyId]);
                $success = 'Strategy updated successfully.';
            } catch (Exception $e) {
                $error = 'Failed to update strategy: ' . $e->getMessage();
            }
            break;
            
        case 'update_strategy':
            $strategyId = (int)$_POST['strategy_id'];
            $minConfidence = (float)($_POST['min_confidence'] ?? 0.70);
            $maxPositionSize = (float)($_POST['max_position_size'] ?? 100);
            $stopLoss = (float)($_POST['stop_loss_percentage'] ?? 5);
            $takeProfit = (float)($_POST['take_profit_percentage'] ?? 10);
            
            try {
                $db->update('trading_strategies', [
                    'min_confidence' => $minConfidence,
                    'max_position_size' => $maxPositionSize,
                    'stop_loss_percentage' => $stopLoss,
                    'take_profit_percentage' => $takeProfit
                ], 'id = ?', [$strategyId]);
                
                $success = 'Strategy configuration updated successfully.';
            } catch (Exception $e) {
                $error = 'Failed to update strategy: ' . $e->getMessage();
            }
            break;
    }
}

// Get all strategies
$strategies = $db->fetchAll("SELECT * FROM trading_strategies ORDER BY name");

// Get strategy performance data
$strategyPerformance = $db->fetchAll("
    SELECT 
        s.name,
        s.strategy_type,
        s.trading_type,
        COUNT(th.id) as total_trades,
        SUM(CASE WHEN th.profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(th.profit_loss) as total_pnl,
        AVG(th.confidence_score) as avg_confidence
    FROM trading_strategies s
    LEFT JOIN trading_history th ON s.name = th.strategy_used
    WHERE th.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY s.id, s.name
    ORDER BY total_pnl DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Strategies - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/enhanced-dashboard.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>AI Trading Strategies</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Strategy Performance Overview -->
        <div class="performance-grid">
            <?php foreach ($strategyPerformance as $perf): ?>
                <div class="performance-card">
                    <div class="performance-header">
                        <h4><?php echo $perf['name']; ?></h4>
                        <span class="strategy-type <?php echo strtolower($perf['strategy_type']); ?>">
                            <?php echo $perf['strategy_type']; ?>
                        </span>
                    </div>
                    <div class="performance-stats">
                        <div class="stat-row">
                            <span class="stat-label">Total Trades:</span>
                            <span class="stat-value"><?php echo $perf['total_trades']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Win Rate:</span>
                            <span class="stat-value">
                                <?php echo $perf['total_trades'] > 0 ? number_format(($perf['winning_trades'] / $perf['total_trades']) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Total P&L:</span>
                            <span class="stat-value <?php echo ($perf['total_pnl'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($perf['total_pnl'] ?? 0) >= 0 ? '+' : ''; ?>$<?php echo number_format($perf['total_pnl'] ?? 0, 2); ?>
                            </span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Avg Confidence:</span>
                            <span class="stat-value"><?php echo number_format(($perf['avg_confidence'] ?? 0) * 100, 1); ?>%</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Strategy Configuration -->
        <div class="strategies-container">
            <div class="strategies-header">
                <h3>Strategy Configuration</h3>
            </div>
            
            <?php if (empty($strategies)): ?>
                <div class="empty-state">
                    <div class="empty-icon">🎯</div>
                    <h3>No Strategies Found</h3>
                    <p>No AI trading strategies are configured.</p>
                </div>
            <?php else: ?>
                <div class="strategies-list">
                    <?php foreach ($strategies as $strategy): ?>
                        <div class="strategy-card">
                            <div class="strategy-header">
                                <div class="strategy-info">
                                    <h4><?php echo $strategy['name']; ?></h4>
                                    <p><?php echo $strategy['description']; ?></p>
                                    <div class="strategy-badges">
                                        <span class="strategy-type <?php echo strtolower($strategy['strategy_type']); ?>">
                                            <?php echo $strategy['strategy_type']; ?>
                                        </span>
                                        <span class="trading-type <?php echo strtolower($strategy['trading_type']); ?>">
                                            <?php echo $strategy['trading_type']; ?>
                                        </span>
                                        <span class="risk-level <?php echo strtolower($strategy['risk_level']); ?>">
                                            <?php echo $strategy['risk_level']; ?> Risk
                                        </span>
                                    </div>
                                </div>
                                <div class="strategy-toggle">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_strategy">
                                        <input type="hidden" name="strategy_id" value="<?php echo $strategy['id']; ?>">
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="enabled" <?php echo $strategy['enabled'] ? 'checked' : ''; ?> 
                                                   onchange="this.form.submit()">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="strategy-config">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_strategy">
                                    <input type="hidden" name="strategy_id" value="<?php echo $strategy['id']; ?>">
                                    
                                    <div class="config-row">
                                        <div class="config-group">
                                            <label>Min Confidence</label>
                                            <input type="number" name="min_confidence" 
                                                   value="<?php echo $strategy['min_confidence']; ?>" 
                                                   min="0.1" max="1.0" step="0.01">
                                        </div>
                                        <div class="config-group">
                                            <label>Max Position Size</label>
                                            <input type="number" name="max_position_size" 
                                                   value="<?php echo $strategy['max_position_size']; ?>" 
                                                   min="1" step="0.01">
                                        </div>
                                        <div class="config-group">
                                            <label>Stop Loss %</label>
                                            <input type="number" name="stop_loss_percentage" 
                                                   value="<?php echo $strategy['stop_loss_percentage']; ?>" 
                                                   min="1" max="20" step="0.5">
                                        </div>
                                        <div class="config-group">
                                            <label>Take Profit %</label>
                                            <input type="number" name="take_profit_percentage" 
                                                   value="<?php echo $strategy['take_profit_percentage']; ?>" 
                                                   min="1" max="50" step="0.5">
                                        </div>
                                    </div>
                                    
                                    <div class="config-actions">
                                        <button type="submit" class="btn btn-sm btn-primary">Update Strategy</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <style>
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .performance-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 20px;
        }
        
        .performance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .performance-header h4 {
            margin: 0;
            color: #1e293b;
        }
        
        .strategy-type {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .strategy-type.scalping {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .strategy-type.day_trading {
            background: #fef3c7;
            color: #92400e;
        }
        
        .strategy-type.swing {
            background: #dcfce7;
            color: #166534;
        }
        
        .strategy-type.position {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .performance-stats {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 13px;
        }
        
        .stat-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .strategies-container {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .strategies-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .strategies-header h3 {
            margin: 0;
            color: #1e293b;
        }
        
        .strategies-list {
            display: flex;
            flex-direction: column;
        }
        
        .strategy-card {
            border-bottom: 1px solid #e2e8f0;
            padding: 20px;
        }
        
        .strategy-card:last-child {
            border-bottom: none;
        }
        
        .strategy-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .strategy-info h4 {
            margin: 0 0 5px 0;
            color: #1e293b;
        }
        
        .strategy-info p {
            margin: 0 0 10px 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .strategy-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .risk-level {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .risk-level.low {
            background: #dcfce7;
            color: #166534;
        }
        
        .risk-level.medium {
            background: #fef3c7;
            color: #92400e;
        }
        
        .risk-level.high {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .strategy-config {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
        }
        
        .config-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .config-group {
            display: flex;
            flex-direction: column;
        }
        
        .config-group label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
            font-weight: 500;
        }
        
        .config-group input {
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .config-actions {
            text-align: right;
        }
        
        @media (max-width: 768px) {
            .performance-grid {
                grid-template-columns: 1fr;
            }
            
            .config-row {
                grid-template-columns: 1fr;
            }
            
            .strategy-header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</body>
</html>