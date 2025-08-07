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
    $spotAPI = new SpotTradingAPI();
    
    // Try to get fresh data from API
    try {
        $spotAPI->getSpotAccount(); // This updates balances in database
    } catch (Exception $e) {
        error_log("Failed to update spot balances from API: " . $e->getMessage());
    }
    
    // Get balances from database
    $balances = $db->fetchAll("
        SELECT * FROM spot_balances 
        WHERE total > 0 
        ORDER BY usdt_value DESC
    ");
    
    // Calculate total spot balance
    $totalSpotBalance = 0;
    foreach ($balances as $balance) {
        $totalSpotBalance += (float)$balance['usdt_value'];
    }
    
    echo json_encode([
        'success' => true,
        'balances' => $balances,
        'total_balance_usdt' => $totalSpotBalance,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}