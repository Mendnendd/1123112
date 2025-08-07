<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/app.php';

$auth = new Auth();
$ai = new AIAnalyzer();

// Simple API authentication (you might want to implement proper API keys)
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['symbol'])) {
                // Get signals for specific symbol
                $signals = $ai->getSignalsBySymbol($_GET['symbol'], $_GET['limit'] ?? 10);
            } else {
                // Get recent signals
                $signals = $ai->getRecentSignals($_GET['limit'] ?? 20);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $signals
            ]);
            break;
            
        case 'POST':
            // Generate new signal for symbol
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['symbol'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Symbol is required']);
                exit;
            }
            
            $analysis = $ai->analyzeSymbol($input['symbol']);
            
            echo json_encode([
                'success' => true,
                'data' => $analysis
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}