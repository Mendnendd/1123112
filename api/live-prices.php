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
    
    // Get active trading pairs
    $pairs = $db->fetchAll("SELECT symbol FROM trading_pairs WHERE enabled = 1 LIMIT 10");
    
    $prices = [];
    
    foreach ($pairs as $pair) {
        try {
            $ticker = $binance->get24hrTicker($pair['symbol']);
            if (!empty($ticker) && is_array($ticker) && isset($ticker[0])) {
                $prices[$pair['symbol']] = [
                    'price' => (float)$ticker[0]['lastPrice'],
                    'change' => (float)$ticker[0]['priceChangePercent'],
                    'volume' => (float)$ticker[0]['volume'],
                    'high' => (float)($ticker[0]['highPrice'] ?? 0),
                    'low' => (float)($ticker[0]['lowPrice'] ?? 0)
                ];
            } else {
                // Fallback data
                $prices[$pair['symbol']] = [
                    'price' => 0,
                    'change' => 0,
                    'volume' => 0,
                    'high' => 0,
                    'low' => 0
                ];
            }
        } catch (Exception $e) {
            error_log("Error fetching price for {$pair['symbol']}: " . $e->getMessage());
            // Skip this symbol on timeout/error to avoid blocking other symbols
            if (strpos($e->getMessage(), 'timeout') !== false) {
                continue; // Skip this symbol and continue with others
            } else {
                // Provide fallback data for other errors
                $prices[$pair['symbol']] = [
                    'price' => 0,
                    'change' => 0,
                    'volume' => 0,
                    'high' => 0,
                    'low' => 0
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'prices' => $prices,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}