<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$ai = new AIAnalyzer();

// Handle manual signal generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_signal') {
        $symbol = $_POST['symbol'] ?? '';
        
        try {
            $analysis = $ai->analyzeSymbol($symbol);
            $success = "Signal generated for {$symbol}: {$analysis['signal']} (Confidence: " . number_format($analysis['confidence'] * 100, 1) . "%)";
        } catch (Exception $e) {
            $error = 'Failed to generate signal: ' . $e->getMessage();
        }
    }
}

// Get recent signals
$signals = $ai->getRecentSignals(50);

// Get signal statistics
$stats = $ai->getSignalStats();

// Get trading pairs
$pairs = $db->fetchAll("SELECT * FROM trading_pairs WHERE enabled = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Signals - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>AI Trading Signals</h1>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Signal Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🤖</div>
                <div class="stat-content">
                    <h3>Total Signals (24h)</h3>
                    <div class="stat-value"><?php echo $stats['total_signals']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📈</div>
                <div class="stat-content">
                    <h3>Buy Signals</h3>
                    <div class="stat-value positive"><?php echo $stats['buy_signals']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📉</div>
                <div class="stat-content">
                    <h3>Sell Signals</h3>
                    <div class="stat-value negative"><?php echo $stats['sell_signals']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-content">
                    <h3>Avg Confidence</h3>
                    <div class="stat-value"><?php echo number_format($stats['avg_confidence'] * 100, 1); ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="signals-grid">
            <!-- Manual Signal Generation -->
            <div class="signals-card">
                <div class="card-header">
                    <h3>🔍 Generate Signal</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="generate_signal">
                        
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
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Generate AI Signal</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Signals -->
            <div class="signals-card full-width">
                <div class="card-header">
                    <h3>📊 Recent Signals</h3>
                    <button onclick="location.reload()" class="btn btn-sm">Refresh</button>
                </div>
                <div class="card-content">
                    <?php if (empty($signals)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🤖</div>
                            <h3>No Signals Yet</h3>
                            <p>Generate your first AI signal using the form above.</p>
                        </div>
                    <?php else: ?>
                        <div class="signals-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Symbol</th>
                                        <th>Signal</th>
                                        <th>Confidence</th>
                                        <th>Price</th>
                                        <th>RSI</th>
                                        <th>MACD</th>
                                        <th>Score</th>
                                        <th>Executed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($signals as $signal): ?>
                                        <tr>
                                            <td><?php echo date('M j, H:i', strtotime($signal['created_at'])); ?></td>
                                            <td><strong><?php echo $signal['symbol']; ?></strong></td>
                                            <td>
                                                <span class="signal-badge signal-<?php echo strtolower($signal['signal']); ?>">
                                                    <?php echo $signal['signal']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="confidence-display">
                                                    <div class="confidence-bar">
                                                        <div class="confidence-fill" style="width: <?php echo ($signal['confidence'] * 100); ?>%"></div>
                                                    </div>
                                                    <span class="confidence-text"><?php echo number_format($signal['confidence'] * 100, 1); ?>%</span>
                                                </div>
                                            </td>
                                            <td>$<?php echo number_format($signal['price'], 4); ?></td>
                                            <td>
                                                <?php if ($signal['rsi']): ?>
                                                    <span class="rsi-value <?php echo $signal['rsi'] > 70 ? 'overbought' : ($signal['rsi'] < 30 ? 'oversold' : 'neutral'); ?>">
                                                        <?php echo number_format($signal['rsi'], 1); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($signal['macd']): ?>
                                                    <span class="<?php echo $signal['macd'] > 0 ? 'positive' : 'negative'; ?>">
                                                        <?php echo number_format($signal['macd'], 6); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="score-value <?php echo $signal['analysis_score'] > 0 ? 'positive' : ($signal['analysis_score'] < 0 ? 'negative' : 'neutral'); ?>">
                                                    <?php echo $signal['analysis_score']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($signal['executed']): ?>
                                                    <span class="status-badge status-executed">✓ Yes</span>
                                                <?php else: ?>
                                                    <span class="status-badge status-pending">⏳ No</span>
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
        // Auto-refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>