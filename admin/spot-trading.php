<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$spotAPI = new SpotTradingAPI();

// Get settings
$settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
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

$success = '';
$error = '';

// Handle spot trading actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'place_spot_order':
            $symbol = $_POST['symbol'] ?? '';
            $side = $_POST['side'] ?? '';
            $type = $_POST['type'] ?? 'MARKET';
            $quantity = (float)($_POST['quantity'] ?? 0);
            $price = $_POST['price'] ? (float)$_POST['price'] : null;
            
            try {
                $order = $spotAPI->placeSpotOrder($symbol, $side, $type, $quantity, $price);
                $success = "Spot order placed successfully: {$side} {$quantity} {$symbol}";
                
            } catch (Exception $e) {
                $error = 'Failed to place spot order: ' . $e->getMessage();
            }
            break;
            
        case 'cancel_spot_order':
            $symbol = $_POST['symbol'] ?? '';
            $orderId = $_POST['order_id'] ?? '';
            
            try {
                $result = $spotAPI->cancelSpotOrder($symbol, $orderId);
                $success = "Spot order cancelled successfully: {$symbol}";
            } catch (Exception $e) {
                $error = 'Failed to cancel spot order: ' . $e->getMessage();
            }
            break;
    }
}

// Get spot trading pairs
try {
    $spotPairs = $db->fetchAll("SELECT * FROM trading_pairs WHERE (trading_type = 'SPOT' OR trading_type = 'BOTH') AND enabled = 1");
} catch (Exception $e) {
    error_log("Error getting spot pairs: " . $e->getMessage());
    // Fallback to all enabled pairs
    $spotPairs = $db->fetchAll("SELECT * FROM trading_pairs WHERE enabled = 1");
}

// Get spot balances
try {
    $spotBalances = $db->fetchAll("SELECT * FROM spot_balances WHERE total > 0 ORDER BY usdt_value DESC");
} catch (Exception $e) {
    $spotBalances = [];
    $error = 'Failed to load spot balances: ' . $e->getMessage();
}

// Get open spot orders
try {
    $openOrders = $spotAPI->getSpotOpenOrders();
} catch (Exception $e) {
    $openOrders = [];
    error_log("Error getting open spot orders: " . $e->getMessage());
}

// Get recent spot trades
$recentSpotTrades = $db->fetchAll("
    SELECT * FROM trading_history 
    WHERE trading_type = 'SPOT' 
    ORDER BY created_at DESC 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot Trading - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/enhanced-dashboard.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Spot Trading</h1>
            <div class="trading-status">
                <span class="status-dot <?php echo $settings['spot_trading_enabled'] ? 'active' : 'inactive'; ?>"></span>
                Spot Trading: <?php echo $settings['spot_trading_enabled'] ? 'Enabled' : 'Disabled'; ?>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="spot-trading-grid">
            <!-- Spot Balances -->
            <div class="trading-card">
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
                        <div class="spot-balances-list">
                            <?php foreach ($spotBalances as $balance): ?>
                                <div class="balance-item">
                                    <div class="balance-info">
                                        <div class="balance-asset"><?php echo $balance['asset']; ?></div>
                                        <div class="balance-amounts">
                                            <span class="balance-free">Free: <?php echo number_format($balance['free'], 6); ?></span>
                                            <span class="balance-locked">Locked: <?php echo number_format($balance['locked'], 6); ?></span>
                                        </div>
                                    </div>
                                    <div class="balance-value">
                                        <div class="balance-total"><?php echo number_format($balance['total'], 6); ?></div>
                                        <div class="balance-usdt">$<?php echo number_format($balance['usdt_value'], 2); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Spot Order Form -->
            <div class="trading-card">
                <div class="card-header">
                    <h3>📈 Place Spot Order</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="place_spot_order">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="symbol">Trading Pair</label>
                                <select id="symbol" name="symbol" required>
                                    <option value="">Select Pair</option>
                                    <?php foreach ($spotPairs as $pair): ?>
                                        <option value="<?php echo $pair['symbol']; ?>">
                                            <?php echo $pair['symbol']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="side">Side</label>
                                <select id="side" name="side" required>
                                    <option value="BUY">Buy</option>
                                    <option value="SELL">Sell</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="type">Order Type</label>
                                <select id="type" name="type" required>
                                    <option value="MARKET">Market</option>
                                    <option value="LIMIT">Limit</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <input type="number" id="quantity" name="quantity" step="0.000001" required>
                            </div>
                        </div>
                        
                        <div class="form-group" id="price-group" style="display: none;">
                            <label for="price">Price (USDT)</label>
                            <input type="number" id="price" name="price" step="0.01">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Place Spot Order</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Open Spot Orders -->
            <div class="trading-card full-width">
                <div class="card-header">
                    <h3>📋 Open Spot Orders</h3>
                    <button onclick="location.reload()" class="btn btn-sm">Refresh</button>
                </div>
                <div class="card-content">
                    <?php if (empty($openOrders)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <p>No open spot orders</p>
                        </div>
                    <?php else: ?>
                        <div class="orders-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Filled</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($openOrders as $order): ?>
                                        <tr>
                                            <td><strong><?php echo $order['symbol']; ?></strong></td>
                                            <td>
                                                <span class="trade-side <?php echo strtolower($order['side']); ?>">
                                                    <?php echo $order['side']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $order['type']; ?></td>
                                            <td><?php echo number_format($order['origQty'], 6); ?></td>
                                            <td>
                                                <?php if ($order['price'] > 0): ?>
                                                    $<?php echo number_format($order['price'], 4); ?>
                                                <?php else: ?>
                                                    Market
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($order['executedQty'], 6); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, H:i', $order['time'] / 1000); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="cancel_spot_order">
                                                    <input type="hidden" name="symbol" value="<?php echo $order['symbol']; ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['orderId']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" 
                                                            onclick="return confirm('Cancel this order?')">
                                                        Cancel
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Spot Trades -->
            <div class="trading-card full-width">
                <div class="card-header">
                    <h3>📊 Recent Spot Trades</h3>
                    <a href="trades.php?trading_type=SPOT" class="btn btn-sm">View All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($recentSpotTrades)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <p>No spot trades yet</p>
                        </div>
                    <?php else: ?>
                        <div class="trades-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>P&L</th>
                                        <th>Strategy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSpotTrades as $trade): ?>
                                        <tr>
                                            <td><?php echo date('M j, H:i', strtotime($trade['created_at'])); ?></td>
                                            <td><strong><?php echo $trade['symbol']; ?></strong></td>
                                            <td>
                                                <span class="trade-side <?php echo strtolower($trade['side']); ?>">
                                                    <?php echo $trade['side']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($trade['quantity'], 6); ?></td>
                                            <td>$<?php echo number_format($trade['executed_price'] ?? $trade['price'], 4); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($trade['status']); ?>">
                                                    <?php echo $trade['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($trade['profit_loss'] !== null): ?>
                                                    <span class="<?php echo $trade['profit_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                                        <?php echo $trade['profit_loss'] >= 0 ? '+' : ''; ?>$<?php echo number_format($trade['profit_loss'], 2); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($trade['strategy_used']): ?>
                                                    <span class="strategy-badge"><?php echo $trade['strategy_used']; ?></span>
                                                <?php else: ?>
                                                    Manual
                                                <?php endif; ?>
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
        // Show/hide price field based on order type
        document.getElementById('type').addEventListener('change', function() {
            const priceGroup = document.getElementById('price-group');
            const priceInput = document.getElementById('price');
            
            if (this.value === 'LIMIT') {
                priceGroup.style.display = 'block';
                priceInput.required = true;
            } else {
                priceGroup.style.display = 'none';
                priceInput.required = false;
            }
        });
        
        function refreshSpotBalances() {
            fetch('../api/spot-balances.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Simple refresh for now
                    }
                })
                .catch(error => console.error('Error refreshing spot balances:', error));
        }
    </script>
    <style>
        .spot-trading-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .trading-card.full-width {
            grid-column: 1 / -1;
        }
        
        .spot-balances-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .balance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .balance-asset {
            font-weight: 700;
            color: #1e293b;
            font-size: 16px;
        }
        
        .balance-amounts {
            display: flex;
            flex-direction: column;
            gap: 2px;
            font-size: 12px;
            color: #64748b;
        }
        
        .balance-total {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .balance-usdt {
            font-weight: 600;
            color: #059669;
            font-size: 13px;
        }
        
        .orders-table {
            overflow-x: auto;
        }
        
        .orders-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        .orders-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .trading-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .spot-trading-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>