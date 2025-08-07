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
    
    // Get latest portfolio data
    $portfolio = $db->fetchOne("SELECT * FROM portfolio_overview");
    
    if (!$portfolio) {
        // Fallback to balance history
        $portfolio = $db->fetchOne("
            SELECT 
                total_portfolio_value,
                spot_balance_usdt,
                futures_balance_usdt,
                total_unrealized_pnl,
                daily_pnl,
                daily_pnl_percentage,
                created_at as last_updated
            FROM balance_history 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        if (!$portfolio) {
            $portfolio = [
                'total_portfolio_value' => 0,
                'spot_balance_usdt' => 0,
                'futures_balance_usdt' => 0,
                'total_unrealized_pnl' => 0,
                'daily_pnl' => 0,
                'daily_pnl_percentage' => 0,
                'active_positions' => 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        } else {
            // Get active positions count
            $positionsCount = $db->fetchOne("SELECT COUNT(*) as count FROM positions WHERE position_amt != 0")['count'];
            $portfolio['active_positions'] = $positionsCount;
        }
    }
    
    // Get performance stats
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_trades,
            SUM(CASE WHEN profit_loss > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(profit_loss) as total_pnl,
            AVG(profit_loss) as avg_pnl_per_trade
        FROM trading_history 
        WHERE DATE(created_at) = CURDATE()
    ");
    
    if (!$stats) {
        $stats = [
            'total_trades' => 0,
            'winning_trades' => 0,
            'total_pnl' => 0,
            'avg_pnl_per_trade' => 0
        ];
    }
    
    $stats['win_rate'] = $stats['total_trades'] > 0 ? 
        ($stats['winning_trades'] / $stats['total_trades']) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'portfolio' => $portfolio,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}