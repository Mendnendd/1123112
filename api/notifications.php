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

// Simple API authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$user = $auth->getCurrentUser();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get unread notifications
        $notifications = $db->fetchAll("
            SELECT * FROM notifications 
            WHERE (user_id IS NULL OR user_id = ?) 
            AND read_at IS NULL 
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC
            LIMIT 10
        ", [$user['id']]);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'mark_read':
                $notificationId = $input['notification_id'] ?? 0;
                if ($notificationId) {
                    $db->update(
                        'notifications',
                        ['read_at' => date('Y-m-d H:i:s')],
                        'id = ? AND (user_id IS NULL OR user_id = ?)',
                        [$notificationId, $user['id']]
                    );
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
                }
                break;
                
            case 'mark_all_read':
                $db->update(
                    'notifications',
                    ['read_at' => date('Y-m-d H:i:s')],
                    '(user_id IS NULL OR user_id = ?) AND read_at IS NULL',
                    [$user['id']]
                );
                echo json_encode(['success' => true]);
                break;
                
            case 'create':
                $title = $input['title'] ?? '';
                $message = $input['message'] ?? '';
                $type = $input['type'] ?? 'INFO';
                $category = $input['category'] ?? 'SYSTEM';
                $priority = $input['priority'] ?? 'NORMAL';
                
                if ($title && $message) {
                    $db->insert('notifications', [
                        'user_id' => $user['id'],
                        'type' => $type,
                        'category' => $category,
                        'title' => $title,
                        'message' => $message,
                        'priority' => $priority
                    ]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Title and message required']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                break;
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}