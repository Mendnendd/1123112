<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/app.php';

$auth = new Auth();

// Simple API authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = Database::getInstance();
    $binance = new BinanceAPI();
    $spotAPI = new SpotTradingAPI();
    
    // Check if API credentials are available
    if (!$binance->hasCredentials()) {
        // Return cached data or mock data if no credentials
        echo json_encode([
            'success' => true,
            'prices' => [],
            'timestamp' => time(),
            'cached' => true,
            'message' => 'API credentials not configured'
        ]);
        exit;
    }
    
    // Get active trading pairs
    $pairs = $db->fetchAll("SELECT symbol FROM trading_pairs WHERE enabled = 1 LIMIT 5");
    
    $prices = [];
    $processedCount = 0;
    $maxPairs = 5; // Limit for performance
    
    foreach ($pairs as $pair) {
        if ($processedCount >= $maxPairs) {
            break;
        }
        
        try {
            // Get futures data
            $futuresTicker = $binance->get24hrTicker($pair['symbol']);
            $futuresData = [
                'price' => 0,
                'change' => 0,
                'volume' => 0,
                'high' => 0,
                'low' => 0
            ];
            
            if (!empty($futuresTicker) && is_array($futuresTicker) && isset($futuresTicker[0])) {
                $futuresData = [
                    'price' => (float)$futuresTicker[0]['lastPrice'],
                    'change' => (float)$futuresTicker[0]['priceChangePercent'],
                    'volume' => (float)$futuresTicker[0]['volume'],
                    'high' => (float)($futuresTicker[0]['highPrice'] ?? 0),
                    'low' => (float)($futuresTicker[0]['lowPrice'] ?? 0)
                ];
            }
            
            // Get spot data
            $spotTicker = [];
            try {
                $spotTicker = $spotAPI->getSpotTicker($pair['symbol']);
            } catch (Exception $e) {
                // Skip spot data if not available
                error_log("Spot ticker not available for {$pair['symbol']}: " . $e->getMessage());
            }
            
            $spotData = [
                'price' => 0,
                'change' => 0,
                'volume' => 0,
                'high' => 0,
                'low' => 0
            ];
            
            if (!empty($spotTicker) && is_array($spotTicker) && isset($spotTicker[0])) {
                $spotData = [
                    'price' => (float)$spotTicker[0]['lastPrice'],
                    'change' => (float)$spotTicker[0]['priceChangePercent'],
                    'volume' => (float)$spotTicker[0]['volume'],
                    'high' => (float)($spotTicker[0]['highPrice'] ?? 0),
                    'low' => (float)($spotTicker[0]['lowPrice'] ?? 0)
                ];
            }
            
            $prices[$pair['symbol']] = [
                'futures' => $futuresData,
                'spot' => $spotData,
                'spread' => abs($futuresData['price'] - $spotData['price']),
                'spread_percentage' => $spotData['price'] > 0 ? 
                    (abs($futuresData['price'] - $spotData['price']) / $spotData['price']) * 100 : 0
            ];
            
            $processedCount++;
            
        } catch (Exception $e) {
            error_log("Error fetching enhanced market data for {$pair['symbol']}: " . $e->getMessage());
            // Skip this symbol on timeout/error to avoid blocking other symbols
            if (strpos($e->getMessage(), 'timeout') !== false) {
                continue; // Skip this symbol and continue with others
            } else {
                // Provide fallback data for other errors
                $prices[$pair['symbol']] = [
                    'futures' => ['price' => 0, 'change' => 0, 'volume' => 0, 'high' => 0, 'low' => 0],
                    'spot' => ['price' => 0, 'change' => 0, 'volume' => 0, 'high' => 0, 'low' => 0],
                    'spread' => 0,
                    'spread_percentage' => 0
                ];
            }
        }
    }
    
    // Cache the data
    try {
        $cacheData = json_encode($prices);
        $expiresAt = date('Y-m-d H:i:s', time() + 60); // Cache for 60 seconds
        
        $db->query("
            INSERT INTO market_data_cache (symbol, data_type, data, expires_at) 
            VALUES ('ALL', 'ENHANCED_TICKER', ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)
        ", [$cacheData, $expiresAt]);
    } catch (Exception $e) {
        error_log("Failed to cache market data: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'prices' => $prices,
        'timestamp' => time(),
        'cached' => false
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}