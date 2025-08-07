<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_pair':
                $pairId = (int)$_POST['pair_id'];
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                
                try {
                    $db->update('trading_pairs', ['enabled' => $enabled], 'id = ?', [$pairId]);
                    $success = 'Trading pair updated successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to update trading pair: ' . $e->getMessage();
                }
                break;
                
            case 'add_pair':
                $symbol = strtoupper(trim($_POST['symbol'] ?? ''));
                $leverage = (int)($_POST['leverage'] ?? 10);
                $marginType = $_POST['margin_type'] ?? 'ISOLATED';
                
                if (empty($symbol)) {
                    $error = 'Symbol is required.';
                } else {
                    try {
                        $db->insert('trading_pairs', [
                            'symbol' => $symbol,
                            'enabled' => 1,
                            'leverage' => $leverage,
                            'margin_type' => $marginType
                        ]);
                        $success = "Trading pair {$symbol} added successfully.";
                    } catch (Exception $e) {
                        $error = 'Failed to add trading pair: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_pair':
                $pairId = (int)$_POST['pair_id'];
                
                try {
                    $db->delete('trading_pairs', 'id = ?', [$pairId]);
                    $success = 'Trading pair deleted successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to delete trading pair: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get all trading pairs
$pairs = $db->fetchAll("SELECT * FROM trading_pairs ORDER BY symbol");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Pairs - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Trading Pairs Management</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="pairs-grid">
            <!-- Add New Pair -->
            <div class="pairs-card">
                <div class="card-header">
                    <h3>➕ Add Trading Pair</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_pair">
                        
                        <div class="form-group">
                            <label for="symbol">Symbol</label>
                            <input type="text" id="symbol" name="symbol" placeholder="BTCUSDT" required>
                            <small class="form-help">Enter the trading pair symbol (e.g., BTCUSDT, ETHUSDT)</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leverage">Leverage</label>
                                <select id="leverage" name="leverage">
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i === 10 ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>x
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="margin_type">Margin Type</label>
                                <select id="margin_type" name="margin_type">
                                    <option value="ISOLATED">Isolated</option>
                                    <option value="CROSSED">Crossed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Add Pair</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Trading Pairs List -->
            <div class="pairs-card full-width">
                <div class="card-header">
                    <h3>📊 Active Trading Pairs</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($pairs)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📊</div>
                            <h3>No Trading Pairs</h3>
                            <p>Add your first trading pair using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="pairs-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Status</th>
                                        <th>Leverage</th>
                                        <th>Margin Type</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pairs as $pair): ?>
                                        <tr>
                                            <td><strong><?php echo $pair['symbol']; ?></strong></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_pair">
                                                    <input type="hidden" name="pair_id" value="<?php echo $pair['id']; ?>">
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" name="enabled" <?php echo $pair['enabled'] ? 'checked' : ''; ?> 
                                                               onchange="this.form.submit()">
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                </form>
                                            </td>
                                            <td><?php echo $pair['leverage']; ?>x</td>
                                            <td><?php echo $pair['margin_type']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($pair['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_pair">
                                                    <input type="hidden" name="pair_id" value="<?php echo $pair['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" 
                                                            onclick="return confirm('Delete <?php echo $pair['symbol']; ?>?')">
                                                        Delete
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
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>