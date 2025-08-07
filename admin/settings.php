<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_api':
                $apiKey = trim($_POST['api_key'] ?? '');
                $apiSecret = trim($_POST['api_secret'] ?? '');
                $testnet = isset($_POST['testnet']) ? 1 : 0;
                
                try {
                    $updateData = ['testnet_mode' => $testnet];
                    
                    if (!empty($apiKey)) {
                        $updateData['binance_api_key'] = $apiKey;
                    }
                    
                    if (!empty($apiSecret)) {
                        // Encrypt the API secret
                        $key = ENCRYPTION_KEY;
                        $iv = random_bytes(16);
                        $encrypted = openssl_encrypt($apiSecret, 'AES-256-CBC', $key, 0, $iv);
                        $updateData['binance_api_secret'] = base64_encode($iv . $encrypted);
                    }
                    
                    $db->update('trading_settings', $updateData, 'id = ?', [1]);
                    $success = 'API settings updated successfully.';
                    
                } catch (Exception $e) {
                    $error = 'Failed to update API settings: ' . $e->getMessage();
                }
                break;
                
            case 'update_trading':
                $tradingEnabled = isset($_POST['trading_enabled']) ? 1 : 0;
                $aiEnabled = isset($_POST['ai_enabled']) ? 1 : 0;
                $spotEnabled = isset($_POST['spot_trading_enabled']) ? 1 : 0;
                $futuresEnabled = isset($_POST['futures_trading_enabled']) ? 1 : 0;
                $maxPosition = (float)($_POST['max_position_size'] ?? 100);
                $maxSpotPosition = (float)($_POST['max_spot_position_size'] ?? 50);
                $riskPercent = (float)($_POST['risk_percentage'] ?? 2);
                $stopLoss = (float)($_POST['stop_loss_percentage'] ?? 5);
                $takeProfit = (float)($_POST['take_profit_percentage'] ?? 10);
                $leverage = (int)($_POST['leverage'] ?? 10);
                $marginType = $_POST['margin_type'] ?? 'ISOLATED';
                $confidenceThreshold = (float)($_POST['ai_confidence_threshold'] ?? 0.75);
                $maxDailyTrades = (int)($_POST['max_daily_trades'] ?? 20);
                $maxConcurrentPositions = (int)($_POST['max_concurrent_positions'] ?? 5);
                
                try {
                    $db->update('trading_settings', [
                        'trading_enabled' => $tradingEnabled,
                        'ai_enabled' => $aiEnabled,
                        'spot_trading_enabled' => $spotEnabled,
                        'futures_trading_enabled' => $futuresEnabled,
                        'max_position_size' => $maxPosition,
                        'max_spot_position_size' => $maxSpotPosition,
                        'risk_percentage' => $riskPercent,
                        'stop_loss_percentage' => $stopLoss,
                        'take_profit_percentage' => $takeProfit,
                        'leverage' => $leverage,
                        'margin_type' => $marginType,
                        'ai_confidence_threshold' => $confidenceThreshold,
                        'max_daily_trades' => $maxDailyTrades,
                        'max_concurrent_positions' => $maxConcurrentPositions
                    ], 'id = ?', [1]);
                    
                    $success = 'Trading settings updated successfully.';
                    
                } catch (Exception $e) {
                    $error = 'Failed to update trading settings: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_trading':
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                try {
                    $db->update('trading_settings', ['trading_enabled' => $enabled], 'id = ?', [1]);
                    $success = $enabled ? 'AI Trading enabled successfully.' : 'AI Trading disabled successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to toggle trading: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_ai':
                $enabled = isset($_POST['enabled']) ? 1 : 0;
                try {
                    $db->update('trading_settings', ['ai_enabled' => $enabled], 'id = ?', [1]);
                    $success = $enabled ? 'AI Analysis enabled successfully.' : 'AI Analysis disabled successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to toggle AI analysis: ' . $e->getMessage();
                }
                break;
                
            case 'update_portfolio':
                try {
                    $bot = new TradingBot();
                    $bot->updatePositions();
                    $bot->updateBalance();
                    $success = 'Portfolio updated successfully from Binance API.';
                } catch (Exception $e) {
                    $error = 'Failed to update portfolio: ' . $e->getMessage();
                }
                break;
                
            case 'test_connection':
                try {
                    $result = $binance->testConnection();
                    if ($result['success']) {
                        $success = 'API connection test successful!';
                    } else {
                        $error = 'API connection test failed: ' . $result['message'];
                    }
                } catch (Exception $e) {
                    $error = 'API connection test failed: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get current settings
$settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");

// Get API status
$apiStatus = null;
try {
    $binance = new BinanceAPI();
    $apiStatus = $binance->getCredentialsStatus();
} catch (Exception $e) {
    $apiStatus = ['error' => $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>System Settings</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- API Status Card -->
        <?php if ($apiStatus): ?>
            <div class="alert <?php echo isset($apiStatus['error']) || !$apiStatus['has_api_key'] || !$apiStatus['has_api_secret'] ? 'alert-warning' : 'alert-success'; ?>">
                <div class="alert-icon">
                    <?php echo isset($apiStatus['error']) || !$apiStatus['has_api_key'] || !$apiStatus['has_api_secret'] ? '⚠️' : '✅'; ?>
                </div>
                <div>
                    <strong>API Status</strong>
                    <?php if (isset($apiStatus['error'])): ?>
                        <p>Error: <?php echo htmlspecialchars($apiStatus['error']); ?></p>
                    <?php else: ?>
                        <p>
                            API Key: <?php echo $apiStatus['has_api_key'] ? '✅ Configured' : '❌ Not Set'; ?><br>
                            API Secret: <?php echo $apiStatus['has_api_secret'] ? '✅ Configured' : '❌ Not Set'; ?><br>
                            Mode: <?php echo $apiStatus['testnet_mode'] ? '🧪 Testnet' : '🔴 Live Trading'; ?><br>
                            Endpoint: <?php echo htmlspecialchars($apiStatus['base_url']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Quick Controls -->
        <div class="quick-controls-grid">
            <div class="control-card">
                <div class="card-header">
                    <h3>🤖 AI Trading Control</h3>
                </div>
                <div class="card-content">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_trading">
                        <input type="hidden" name="enabled" value="<?php echo $settings['trading_enabled'] ? '0' : '1'; ?>">
                        <button type="submit" class="btn <?php echo $settings['trading_enabled'] ? 'btn-danger' : 'btn-success'; ?>">
                            <?php echo $settings['trading_enabled'] ? '⏹️ Stop AI Trading' : '▶️ Start AI Trading'; ?>
                        </button>
                    </form>
                    <p class="control-status">
                        Status: <span class="<?php echo $settings['trading_enabled'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $settings['trading_enabled'] ? 'Active' : 'Stopped'; ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="control-card">
                <div class="card-header">
                    <h3>🧠 AI Analysis Control</h3>
                </div>
                <div class="card-content">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_ai">
                        <input type="hidden" name="enabled" value="<?php echo $settings['ai_enabled'] ? '0' : '1'; ?>">
                        <button type="submit" class="btn <?php echo $settings['ai_enabled'] ? 'btn-danger' : 'btn-success'; ?>">
                            <?php echo $settings['ai_enabled'] ? '⏹️ Stop AI Analysis' : '▶️ Start AI Analysis'; ?>
                        </button>
                    </form>
                    <p class="control-status">
                        Status: <span class="<?php echo $settings['ai_enabled'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $settings['ai_enabled'] ? 'Active' : 'Stopped'; ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="control-card">
                <div class="card-header">
                    <h3>📊 Portfolio Update</h3>
                </div>
                <div class="card-content">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_portfolio">
                        <button type="submit" class="btn btn-primary">
                            🔄 Update Portfolio
                        </button>
                    </form>
                    <p class="control-status">
                        Sync positions and balance from Binance API
                    </p>
                </div>
            </div>
        </div>
        
        <div class="settings-grid">
            <!-- API Configuration -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>🔑 Binance API Configuration</h3>
                </div>
                <div class="card-content">
                    <div class="alert alert-warning">
                        <div class="alert-icon">ℹ️</div>
                        <div>
                            <strong>Setup Instructions:</strong>
                            <ol style="margin: 10px 0 0 20px;">
                                <li>Go to <a href="https://testnet.binancefuture.com" target="_blank" rel="noopener">Binance Testnet</a> (for testing) or <a href="https://binance.com" target="_blank" rel="noopener">Binance</a> (for live trading)</li>
                                <li>Create an account and enable 2FA</li>
                                <li>Go to API Management and create a new API key</li>
                                <li>Enable "Futures" permissions for the API key</li>
                                <li>Set IP restrictions for security (optional but recommended)</li>
                                <li>Copy the API Key and Secret Key below</li>
                            </ol>
                            <p style="margin-top: 15px;"><strong>Important:</strong></p>
                            <ul style="margin: 5px 0 0 20px;">
                                <li>For testing, use the Binance Testnet which provides fake funds for safe testing</li>
                                <li>Make sure to enable "Futures Trading" permission on your API key</li>
                                <li>If using IP restrictions, add your server's IP address</li>
                                <li>Never share your API keys with anyone</li>
                            </ul>
                        </div>
                    </div>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_api">
                        
                        <div class="form-group">
                            <label for="api_key">API Key</label>
                            <input type="text" id="api_key" name="api_key" 
                                   value="<?php echo htmlspecialchars($settings['binance_api_key'] ?? ''); ?>"
                                   placeholder="Enter your Binance API key">
                        </div>
                        
                        <div class="form-group">
                            <label for="api_secret">API Secret</label>
                            <input type="password" id="api_secret" name="api_secret" 
                                   placeholder="Enter your Binance API secret">
                            <small class="form-help">Leave empty to keep current secret</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="testnet" <?php echo $settings['testnet_mode'] ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Use Testnet (Recommended for testing)
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update API Settings</button>
                            <button type="submit" name="action" value="test_connection" class="btn btn-secondary">Test Connection</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Trading Configuration -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>💹 Trading Configuration</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_trading">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_position_size">Max Position Size (USDT)</label>
                                <input type="number" id="max_position_size" name="max_position_size" 
                                       value="<?php echo $settings['max_position_size']; ?>" 
                                       min="1" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_spot_position_size">Max Spot Position Size (USDT)</label>
                                <input type="number" id="max_spot_position_size" name="max_spot_position_size" 
                                       value="<?php echo $settings['max_spot_position_size'] ?? 50; ?>" 
                                       min="1" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="risk_percentage">Risk Percentage (%)</label>
                                <input type="number" id="risk_percentage" name="risk_percentage" 
                                       value="<?php echo $settings['risk_percentage']; ?>" 
                                       min="0.1" max="10" step="0.1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="ai_confidence_threshold">AI Confidence Threshold</label>
                                <input type="number" id="ai_confidence_threshold" name="ai_confidence_threshold" 
                                       value="<?php echo $settings['ai_confidence_threshold'] ?? 0.75; ?>" 
                                       min="0.1" max="1.0" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="stop_loss_percentage">Stop Loss (%)</label>
                                <input type="number" id="stop_loss_percentage" name="stop_loss_percentage" 
                                       value="<?php echo $settings['stop_loss_percentage']; ?>" 
                                       min="1" max="20" step="0.5" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="take_profit_percentage">Take Profit (%)</label>
                                <input type="number" id="take_profit_percentage" name="take_profit_percentage" 
                                       value="<?php echo $settings['take_profit_percentage']; ?>" 
                                       min="1" max="50" step="0.5" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_daily_trades">Max Daily Trades</label>
                                <input type="number" id="max_daily_trades" name="max_daily_trades" 
                                       value="<?php echo $settings['max_daily_trades'] ?? 20; ?>" 
                                       min="1" max="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_concurrent_positions">Max Concurrent Positions</label>
                                <input type="number" id="max_concurrent_positions" name="max_concurrent_positions" 
                                       value="<?php echo $settings['max_concurrent_positions'] ?? 5; ?>" 
                                       min="1" max="20" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leverage">Leverage</label>
                                <select id="leverage" name="leverage" required>
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $settings['leverage'] == $i ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>x
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="margin_type">Margin Type</label>
                                <select id="margin_type" name="margin_type" required>
                                    <option value="ISOLATED" <?php echo $settings['margin_type'] === 'ISOLATED' ? 'selected' : ''; ?>>Isolated</option>
                                    <option value="CROSSED" <?php echo $settings['margin_type'] === 'CROSSED' ? 'selected' : ''; ?>>Crossed</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="spot_trading_enabled" <?php echo ($settings['spot_trading_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Enable Spot Trading
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="futures_trading_enabled" <?php echo ($settings['futures_trading_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Enable Futures Trading
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="trading_enabled" <?php echo $settings['trading_enabled'] ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Enable Automated Trading
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="ai_enabled" <?php echo $settings['ai_enabled'] ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Enable AI Analysis
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Trading Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Security Warning -->
        <div class="alert alert-warning">
            <div class="alert-icon">⚠️</div>
            <div>
                <strong>Security Notice</strong>
                <p>Keep your API keys secure and never share them. Use testnet for development and testing. Only enable live trading when you're confident in your strategy.</p>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <style>
        .quick-controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .control-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .control-status {
            margin-top: 10px;
            font-size: 14px;
            color: #64748b;
        }
        
        .status-active {
            color: #059669;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #dc2626;
            font-weight: 600;
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-success:hover {
            background: #047857;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
    </style>
</body>
</html>