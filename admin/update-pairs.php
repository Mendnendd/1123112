<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();

$success = '';
$error = '';
$updateResults = null;

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_pairs') {
        try {
            // Get exchange info from Binance
            $exchangeInfo = $binance->makeRequest('/fapi/v1/exchangeInfo');
            
            if (!$exchangeInfo || !isset($exchangeInfo['symbols'])) {
                throw new Exception('Failed to get exchange info from Binance');
            }
            
            $symbols = $exchangeInfo['symbols'];
            
            // Filter for USDT pairs and active symbols
            $usdtPairs = [];
            foreach ($symbols as $symbol) {
                if (isset($symbol['symbol']) && 
                    isset($symbol['quoteAsset']) && 
                    $symbol['quoteAsset'] === 'USDT' &&
                    isset($symbol['status']) && 
                    $symbol['status'] === 'TRADING') {
                    
                    $usdtPairs[] = [
                        'symbol' => $symbol['symbol'],
                        'baseAsset' => $symbol['baseAsset'],
                        'quoteAsset' => $symbol['quoteAsset'],
                        'status' => $symbol['status']
                    ];
                }
            }
            
            // Get 24hr ticker data for volume sorting
            $tickers = $binance->get24hrTicker();
            
            // Create volume map
            $volumeMap = [];
            if ($tickers) {
                foreach ($tickers as $ticker) {
                    if (isset($ticker['symbol']) && isset($ticker['volume'])) {
                        $volumeMap[$ticker['symbol']] = (float)$ticker['volume'];
                    }
                }
            }
            
            // Add volume to pairs and sort
            foreach ($usdtPairs as &$pair) {
                $pair['volume'] = $volumeMap[$pair['symbol']] ?? 0;
            }
            
            // Sort by volume (highest first)
            usort($usdtPairs, function($a, $b) {
                return $b['volume'] <=> $a['volume'];
            });
            
            // Take top 200
            $top200Pairs = array_slice($usdtPairs, 0, 200);
            
            // Get existing pairs
            $existingPairs = $db->fetchAll("SELECT symbol FROM trading_pairs");
            $existingSymbols = array_column($existingPairs, 'symbol');
            
            $added = 0;
            $updated = 0;
            
            foreach ($top200Pairs as $pair) {
                $symbol = $pair['symbol'];
                $baseAsset = $pair['baseAsset'];
                $quoteAsset = $pair['quoteAsset'];
                
                if (in_array($symbol, $existingSymbols)) {
                    // Update existing pair
                    $db->update('trading_pairs', [
                        'base_asset' => $baseAsset,
                        'quote_asset' => $quoteAsset,
                        'trading_type' => 'BOTH',
                        'volatility_score' => round($pair['volume'] / 1000000, 2)
                    ], 'symbol = ?', [$symbol]);
                    $updated++;
                } else {
                    // Add new pair (disabled by default)
                    $db->insert('trading_pairs', [
                        'symbol' => $symbol,
                        'base_asset' => $baseAsset,
                        'quote_asset' => $quoteAsset,
                        'trading_type' => 'BOTH',
                        'enabled' => 0, // Disabled by default for safety
                        'leverage' => 10,
                        'margin_type' => 'ISOLATED',
                        'ai_priority' => 1,
                        'volatility_score' => round($pair['volume'] / 1000000, 2)
                    ]);
                    $added++;
                }
            }
            
            $updateResults = [
                'total_symbols' => count($symbols),
                'usdt_pairs' => count($usdtPairs),
                'top_pairs' => count($top200Pairs),
                'added' => $added,
                'updated' => $updated,
                'top_10' => array_slice($top200Pairs, 0, 10)
            ];
            
            $success = "Successfully updated trading pairs! Added {$added} new pairs and updated {$updated} existing pairs.";
            
            // Log the update
            $db->insert('system_logs', [
                'level' => 'INFO',
                'category' => 'SYSTEM',
                'message' => "[ADMIN] Updated trading pairs database with top 200 symbols from Binance",
                'context' => json_encode($updateResults)
            ]);
            
        } catch (Exception $e) {
            $error = 'Failed to update trading pairs: ' . $e->getMessage();
        }
    }
}

// Get current pair statistics
$pairStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pairs,
        SUM(enabled) as enabled_pairs,
        COUNT(CASE WHEN trading_type = 'SPOT' THEN 1 END) as spot_pairs,
        COUNT(CASE WHEN trading_type = 'FUTURES' THEN 1 END) as futures_pairs,
        COUNT(CASE WHEN trading_type = 'BOTH' THEN 1 END) as both_pairs
    FROM trading_pairs
");

// Get top pairs by volume
$topPairs = $db->fetchAll("
    SELECT * FROM trading_pairs 
    ORDER BY volatility_score DESC, symbol ASC 
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Trading Pairs - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Update Trading Pairs</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Current Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3>Total Pairs</h3>
                    <div class="stat-value"><?php echo $pairStats['total_pairs']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3>Enabled Pairs</h3>
                    <div class="stat-value"><?php echo $pairStats['enabled_pairs']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏪</div>
                <div class="stat-content">
                    <h3>Spot Pairs</h3>
                    <div class="stat-value"><?php echo $pairStats['spot_pairs']; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚡</div>
                <div class="stat-content">
                    <h3>Futures Pairs</h3>
                    <div class="stat-value"><?php echo $pairStats['futures_pairs']; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Update Action -->
        <div class="update-card">
            <div class="card-header">
                <h3>🔄 Update Trading Pairs from Binance</h3>
            </div>
            <div class="card-content">
                <div class="alert alert-warning">
                    <div class="alert-icon">⚠️</div>
                    <div>
                        <strong>Important Notes:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li>This will fetch the top 200 trading symbols from Binance by volume</li>
                            <li>New pairs will be added as <strong>disabled</strong> by default for safety</li>
                            <li>Existing pairs will be updated with latest information</li>
                            <li>You can enable/disable pairs individually after the update</li>
                            <li>This process may take a few minutes</li>
                        </ul>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_pairs">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Update trading pairs from Binance? This may take a few minutes.')">
                            🔄 Update Trading Pairs
                        </button>
                        <a href="pairs.php" class="btn btn-secondary">Manage Pairs</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Update Results -->
        <?php if ($updateResults): ?>
        <div class="results-card">
            <div class="card-header">
                <h3>📊 Update Results</h3>
            </div>
            <div class="card-content">
                <div class="results-grid">
                    <div class="result-item">
                        <span class="result-label">Total Symbols Fetched:</span>
                        <span class="result-value"><?php echo number_format($updateResults['total_symbols']); ?></span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">USDT Pairs Found:</span>
                        <span class="result-value"><?php echo number_format($updateResults['usdt_pairs']); ?></span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Top Pairs Selected:</span>
                        <span class="result-value"><?php echo number_format($updateResults['top_pairs']); ?></span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">New Pairs Added:</span>
                        <span class="result-value positive"><?php echo number_format($updateResults['added']); ?></span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Existing Pairs Updated:</span>
                        <span class="result-value"><?php echo number_format($updateResults['updated']); ?></span>
                    </div>
                </div>
                
                <h4>Top 10 Pairs by Volume:</h4>
                <div class="top-pairs-list">
                    <?php foreach ($updateResults['top_10'] as $index => $pair): ?>
                        <div class="pair-item">
                            <span class="pair-rank"><?php echo $index + 1; ?></span>
                            <span class="pair-symbol"><?php echo $pair['symbol']; ?></span>
                            <span class="pair-volume">Volume: <?php echo number_format($pair['volume']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Current Top Pairs -->
        <div class="pairs-card">
            <div class="card-header">
                <h3>📈 Current Top Pairs by Volume Score</h3>
            </div>
            <div class="card-content">
                <div class="pairs-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Symbol</th>
                                <th>Base Asset</th>
                                <th>Trading Type</th>
                                <th>Volume Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topPairs as $index => $pair): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo $pair['symbol']; ?></strong></td>
                                    <td><?php echo $pair['base_asset']; ?></td>
                                    <td>
                                        <span class="trading-type <?php echo strtolower($pair['trading_type']); ?>">
                                            <?php echo $pair['trading_type']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($pair['volatility_score'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $pair['enabled'] ? 'status-success' : 'status-warning'; ?>">
                                            <?php echo $pair['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <style>
        .update-card, .results-card, .pairs-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
        }
        
        .result-label {
            color: #64748b;
            font-size: 14px;
        }
        
        .result-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .result-value.positive {
            color: #059669;
        }
        
        .top-pairs-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
        }
        
        .pair-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
        }
        
        .pair-rank {
            background: #3b82f6;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }
        
        .pair-symbol {
            font-weight: 600;
            color: #1e293b;
            min-width: 100px;
        }
        
        .pair-volume {
            color: #64748b;
            font-size: 14px;
        }
        
        .pairs-table {
            overflow-x: auto;
        }
        
        .pairs-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .pairs-table th,
        .pairs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .pairs-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</body>
</html>