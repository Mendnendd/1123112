<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();

// Get positions from Binance
try {
    $positions = $binance->getPositions();
    $activePositions = array_filter($positions, function($pos) {
        return (float)$pos['positionAmt'] != 0;
    });
} catch (Exception $e) {
    $activePositions = [];
    $error = 'Failed to load positions: ' . $e->getMessage();
}

// Update positions in database
try {
    $bot = new TradingBot();
    $bot->updatePositions();
} catch (Exception $e) {
    // Silent fail for database update
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Positions - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Active Positions</h1>
            <button onclick="location.reload()" class="btn btn-secondary">Refresh</button>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="positions-container">
            <?php if (empty($activePositions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📈</div>
                    <h3>No Active Positions</h3>
                    <p>You don't have any open positions at the moment.</p>
                    <a href="trading.php" class="btn btn-primary">Start Trading</a>
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
                                <th>Unrealized P&L</th>
                                <th>ROE %</th>
                                <th>Margin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activePositions as $position): ?>
                                <?php
                                $positionAmt = (float)$position['positionAmt'];
                                $side = $positionAmt > 0 ? 'LONG' : 'SHORT';
                                $size = abs($positionAmt);
                                $entryPrice = (float)$position['entryPrice'];
                                $markPrice = (float)$position['markPrice'];
                                $unrealizedPnl = (float)$position['unRealizedProfit'];
                                $percentage = (float)$position['percentage'];
                                $isolatedMargin = (float)$position['isolatedMargin'];
                                ?>
                                <tr>
                                    <td><strong><?php echo $position['symbol']; ?></strong></td>
                                    <td>
                                        <span class="position-side <?php echo strtolower($side); ?>">
                                            <?php echo $side; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($size, 6); ?></td>
                                    <td>$<?php echo number_format($entryPrice, 4); ?></td>
                                    <td>$<?php echo number_format($markPrice, 4); ?></td>
                                    <td class="<?php echo $unrealizedPnl >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php echo $unrealizedPnl >= 0 ? '+' : ''; ?>$<?php echo number_format($unrealizedPnl, 2); ?>
                                    </td>
                                    <td class="<?php echo $percentage >= 0 ? 'positive' : 'negative'; ?>">
                                        <?php echo $percentage >= 0 ? '+' : ''; ?><?php echo number_format($percentage, 2); ?>%
                                    </td>
                                    <td>$<?php echo number_format($isolatedMargin, 2); ?></td>
                                    <td>
                                        <form method="POST" action="trading.php" style="display: inline;">
                                            <input type="hidden" name="action" value="close_position">
                                            <input type="hidden" name="symbol" value="<?php echo $position['symbol']; ?>">
                                            <input type="hidden" name="quantity" value="<?php echo $size; ?>">
                                            <input type="hidden" name="current_side" value="<?php echo $side; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" 
                                                    onclick="return confirm('Close position for <?php echo $position['symbol']; ?>?')">
                                                Close
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Position Summary -->
                <div class="position-summary">
                    <?php
                    $totalPnl = array_sum(array_map(function($pos) {
                        return (float)$pos['unRealizedProfit'];
                    }, $activePositions));
                    
                    $totalMargin = array_sum(array_map(function($pos) {
                        return (float)$pos['isolatedMargin'];
                    }, $activePositions));
                    ?>
                    <div class="summary-card">
                        <h4>Total Unrealized P&L</h4>
                        <div class="summary-value <?php echo $totalPnl >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $totalPnl >= 0 ? '+' : ''; ?>$<?php echo number_format($totalPnl, 2); ?>
                        </div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Margin Used</h4>
                        <div class="summary-value">$<?php echo number_format($totalMargin, 2); ?></div>
                    </div>
                    <div class="summary-card">
                        <h4>Active Positions</h4>
                        <div class="summary-value"><?php echo count($activePositions); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <script>
        // Auto-refresh every 10 seconds
        setInterval(function() {
            location.reload();
        }, 10000);
    </script>
</body>
</html>