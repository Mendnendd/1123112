<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$binance = new BinanceAPI();
$success = '';
$error = '';
$results = [];

// Get settings first
$settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
if (!$settings) {
    // Create default settings if none exist
    $db->insert('trading_settings', [
        'id' => 1,
        'testnet_mode' => 1,
        'trading_enabled' => 0,
        'ai_enabled' => 1
    ]);
    $settings = $db->fetchOne("SELECT * FROM trading_settings WHERE id = 1");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_type = $_POST['test_type'] ?? '';
    
    try {
        switch ($test_type) {
            case 'ping':
                // Test ping endpoint
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => ($binance->hasCredentials() && $settings && $settings['testnet_mode'] ? 'https://testnet.binancefuture.com' : 'https://fapi.binance.com') . '/fapi/v1/ping',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $results['ping'] = 'API is reachable';
                    $success = 'Ping test successful';
                } else {
                    throw new Exception("Ping failed with HTTP code: {$httpCode}");
                }
                break;
                
            case 'time':
                // Test time endpoint
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => ($binance->hasCredentials() && $settings && $settings['testnet_mode'] ? 'https://testnet.binancefuture.com' : 'https://fapi.binance.com') . '/fapi/v1/time',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $result = json_decode($response, true);
                    if ($result && isset($result['serverTime'])) {
                        $results['server_time'] = date('Y-m-d H:i:s', (int)($result['serverTime'] / 1000));
                        $success = 'Server time retrieved successfully';
                    } else {
                        throw new Exception('Invalid server time response');
                    }
                } else {
                    throw new Exception("Time request failed with HTTP code: {$httpCode}");
                }
                break;
                
            case 'account':
                if (!$binance->hasCredentials()) {
                    throw new Exception('API credentials not configured');
                }
                $result = $binance->getAccountInfo();
                $results['account'] = $result;
                $success = 'Account info retrieved successfully';
                break;
                
            case 'positions':
                if (!$binance->hasCredentials()) {
                    throw new Exception('API credentials not configured');
                }
                $result = $binance->getPositions();
                $results['positions'] = $result;
                $success = 'Positions retrieved successfully';
                break;
                
            case 'ticker':
                $result = $binance->get24hrTicker('BTCUSDT');
                $results['ticker'] = $result;
                $success = 'Ticker data retrieved successfully';
                break;
                
            case 'test_connection':
                try {
                    if (!$binance->hasCredentials()) {
                        $error = 'API credentials not configured. Please set your Binance API key and secret in Settings first.';
                    } else {
                        $result = $binance->testConnection();
                        if ($result['success']) {
                            $success = 'API connection test successful! ' . $result['message'];
                        } else {
                            $error = 'API connection test failed: ' . $result['message'];
                        }
                    }
                } catch (Exception $e) {
                    $error = 'API connection test failed: ' . $e->getMessage();
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Test failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Binance API Test</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="api-test-grid">
            <div class="test-card">
                <div class="card-header">
                    <h3>🔗 Connection Tests</h3>
                </div>
                <div class="card-content">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="test_type" value="ping">
                        <button type="submit" class="btn btn-primary">Test Ping</button>
                    </form>
                    
                    <form method="POST" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="test_type" value="time">
                        <button type="submit" class="btn btn-primary">Get Server Time</button>
                    </form>
                    
                    <form method="POST" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="test_type" value="ticker">
                        <button type="submit" class="btn btn-primary">Get BTCUSDT Ticker</button>
                    </form>
                </div>
            </div>
            
            <div class="test-card">
                <div class="card-header">
                    <h3>🔐 Authenticated Tests</h3>
                </div>
                <div class="card-content">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="test_type" value="account">
                        <button type="submit" class="btn btn-secondary">Get Account Info</button>
                    </form>
                    
                    <form method="POST" style="display: inline; margin-left: 10px;">
                        <input type="hidden" name="test_type" value="positions">
                        <button type="submit" class="btn btn-secondary">Get Positions</button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="results-card">
                <div class="card-header">
                    <h3>📊 Test Results</h3>
                </div>
                <div class="card-content">
                    <pre><?php echo htmlspecialchars(json_encode($results, JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <style>
        .api-test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .test-card, .results-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .results-card {
            margin-top: 20px;
        }
        
        .results-card pre {
            background: #f8fafc;
            padding: 20px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.4;
        }
    </style>
</body>
</html>