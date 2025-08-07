<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();

$success = '';
$error = '';

// Handle manual trading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'place_order':
            $symbol = $_POST['symbol'] ?? '';
            $side = $_POST['side'] ?? '';
            $type = $_POST['type'] ?? 'MARKET';
            $quantity = (float)($_POST['quantity'] ?? 0);
            $price = $_POST['price'] ? (float)$_POST['price'] : null;
            
            try {
                $order = $binance->placeOrder($symbol, $side, $type, $quantity, $price);
                
                // Save to database
                $tradeData = [
                    'symbol' => $symbol,
                    'side' => $side,
                    'type' => $type,
                    'quantity' => $quantity,
                    'price' => $price,
                    'executed_price' => $order['avgPrice'] ?? $order['price'] ?? 0,
                    'executed_qty' => $order['executedQty'] ?? $quantity,
                    'status' => $order['status'] ?? 'FILLED',
                    'order_id' => $order['orderId'] ?? null,
                    'client_order_id' => $order['clientOrderId'] ?? null,
                    'notes' => 'Manual Trade'
                ];
                
                $db->insert('trading_history', $tradeData);
                $success = "Order placed successfully: {$side} {$quantity} {$symbol}";
                
            } catch (Exception $e) {
                $error = 'Failed to place order: ' . $e->getMessage();
            }
            break;
            
        case 'close_position':
            $symbol = $_POST['symbol'] ?? '';
            $quantity = (float)($_POST['quantity'] ?? 0);
            $side = $_POST['current_side'] === 'LONG' ? 'SELL' : 'BUY';
            
            try {
                $order = $binance->placeOrder($symbol, $side, 'MARKET', $quantity);
                $success = "Position closed successfully: {$symbol}";
            } catch (Exception $e) {
                $error = 'Failed to close position: ' . $e->getMessage();
            }
            break;
    }
}

// Get trading pairs
$pairs = $db->fetchAll("SELECT * FROM trading_pairs WHERE enabled = 1");

// Get current positions
try {
    $positions = $binance->getPositions();
    $activePositions = array_filter($positions, function($pos) {
        return (float)$pos['positionAmt'] != 0;
    });
} catch (Exception $e) {
    $activePositions = [];
    $error = 'Failed to load positions: ' . $e->getMessage();
}

// Get account balance
try {
    $account = $binance->getAccountInfo();
    
    // Extract balance information safely
    if (is_array($account)) {
        $balance = (float)($account['availableBalance'] ?? 0);
    } else {
        $balance = 0;
        error_log("Account info is not an array in trading: " . print_r($account, true));
    }
    
} catch (Exception $e) {
    $balance = 0;
    error_log("Error getting account balance: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Trading - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Manual Trading</h1>
            <div class="balance-info">
                <span class="balance-label">Available Balance:</span>
                <span class="balance-value">$<?php echo number_format($balance, 2); ?></span>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="trading-grid">
            <!-- Trading Form -->
            <div class="trading-card">
                <div class="card-header">
                    <h3>📈 Place Order</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="place_order">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="symbol">Trading Pair</label>
                                <select id="symbol" name="symbol" required>
                                    <option value="">Select Pair</option>
                                    <?php foreach ($pairs as $pair): ?>
                                        <option value="<?php echo $pair['symbol']; ?>">
                                            <?php echo $pair['symbol']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="side">Side</label>
                                <select id="side" name="side" required>
                                    <option value="BUY">Buy (Long)</option>
                                    <option value="SELL">Sell (Short)</option>
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
                            <button type="submit" class="btn btn-primary">Place Order</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Active Positions -->
            <div class="trading-card">
                <div class="card-header">
                    <h3>📊 Active Positions</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($activePositions)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📈</div>
                            <p>No active positions</p>
                        </div>
                    <?php else: ?>
                        <div class="positions-list">
                            <?php foreach ($activePositions as $position): ?>
                                <div class="position-item">
                                    <div class="position-info">
                                        <div class="position-symbol"><?php echo $position['symbol']; ?></div>
                                        <div class="position-side <?php echo (float)$position['positionAmt'] > 0 ? 'long' : 'short'; ?>">
                                            <?php echo (float)$position['positionAmt'] > 0 ? 'LONG' : 'SHORT'; ?>
                                        </div>
                                        <div class="position-size"><?php echo abs((float)$position['positionAmt']); ?></div>
                                    </div>
                                    <div class="position-pnl">
                                        <div class="pnl-amount <?php echo (float)$position['unRealizedProfit'] >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo (float)$position['unRealizedProfit'] >= 0 ? '+' : ''; ?>$<?php echo number_format((float)$position['unRealizedProfit'], 2); ?>
                                        </div>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="close_position">
                                            <input type="hidden" name="symbol" value="<?php echo $position['symbol']; ?>">
                                            <input type="hidden" name="quantity" value="<?php echo abs((float)$position['positionAmt']); ?>">
                                            <input type="hidden" name="current_side" value="<?php echo (float)$position['positionAmt'] > 0 ? 'LONG' : 'SHORT'; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Close this position?')">
                                                Close
                                            </button>
                                        </form>
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
    </script>
</body>
</html>