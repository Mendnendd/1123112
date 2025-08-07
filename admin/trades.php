<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$symbol = $_GET['symbol'] ?? '';
$side = $_GET['side'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where = [];
$params = [];

if ($symbol) {
    $where[] = "symbol = ?";
    $params[] = $symbol;
}

if ($side) {
    $where[] = "side = ?";
    $params[] = $side;
}

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get trades
$trades = $db->fetchAll(
    "SELECT * FROM trading_history {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

// Get total count for pagination
$totalTrades = $db->fetchOne(
    "SELECT COUNT(*) as count FROM trading_history {$whereClause}",
    $params
)['count'];

$totalPages = ceil($totalTrades / $limit);

// Get trading pairs for filter
$pairs = $db->fetchAll("SELECT DISTINCT symbol FROM trading_history ORDER BY symbol");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade History - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Trade History</h1>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="symbol">Symbol</label>
                    <select id="symbol" name="symbol">
                        <option value="">All Symbols</option>
                        <?php foreach ($pairs as $pair): ?>
                            <option value="<?php echo $pair['symbol']; ?>" <?php echo $symbol === $pair['symbol'] ? 'selected' : ''; ?>>
                                <?php echo $pair['symbol']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="side">Side</label>
                    <select id="side" name="side">
                        <option value="">All Sides</option>
                        <option value="BUY" <?php echo $side === 'BUY' ? 'selected' : ''; ?>>Buy</option>
                        <option value="SELL" <?php echo $side === 'SELL' ? 'selected' : ''; ?>>Sell</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="FILLED" <?php echo $status === 'FILLED' ? 'selected' : ''; ?>>Filled</option>
                        <option value="CANCELED" <?php echo $status === 'CANCELED' ? 'selected' : ''; ?>>Canceled</option>
                        <option value="REJECTED" <?php echo $status === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="trades.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Trades Table -->
        <div class="trades-container">
            <?php if (empty($trades)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <h3>No Trades Found</h3>
                    <p>No trading history matches your criteria.</p>
                </div>
            <?php else: ?>
                <div class="trades-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Symbol</th>
                                <th>Side</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Executed Price</th>
                                <th>Status</th>
                                <th>P&L</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trades as $trade): ?>
                                <tr>
                                    <td><?php echo date('M j, Y H:i', strtotime($trade['created_at'])); ?></td>
                                    <td><strong><?php echo $trade['symbol']; ?></strong></td>
                                    <td>
                                        <span class="trade-side <?php echo strtolower($trade['side']); ?>">
                                            <?php echo $trade['side']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $trade['type']; ?></td>
                                    <td><?php echo number_format($trade['quantity'], 6); ?></td>
                                    <td>
                                        <?php if ($trade['price']): ?>
                                            $<?php echo number_format($trade['price'], 4); ?>
                                        <?php else: ?>
                                            Market
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trade['executed_price']): ?>
                                            $<?php echo number_format($trade['executed_price'], 4); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
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
                                        <?php if ($trade['notes']): ?>
                                            <span title="<?php echo htmlspecialchars($trade['notes']); ?>">
                                                <?php echo substr($trade['notes'], 0, 20) . (strlen($trade['notes']) > 20 ? '...' : ''); ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&symbol=<?php echo $symbol; ?>&side=<?php echo $side; ?>&status=<?php echo $status; ?>" class="btn btn-sm">Previous</a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&symbol=<?php echo $symbol; ?>&side=<?php echo $side; ?>&status=<?php echo $status; ?>" class="btn btn-sm">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>