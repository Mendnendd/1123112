<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$health = new SystemHealth();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'cleanup_logs':
            $days = (int)($_POST['days'] ?? 30);
            $result = $health->cleanupOldLogs($days);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
            break;
    }
}

// Get system status
$systemStatus = $health->getSystemStatus();
$recentErrors = $health->getRecentErrors(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>System Health Monitor</h1>
            <button onclick="location.reload()" class="btn btn-secondary">Refresh Status</button>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Overall Status -->
        <div class="health-overview">
            <div class="health-card overall-status status-<?php echo $systemStatus['overall']; ?>">
                <div class="health-icon">
                    <?php
                    $icons = ['healthy' => '✅', 'warning' => '⚠️', 'critical' => '🚨'];
                    echo $icons[$systemStatus['overall']] ?? '❓';
                    ?>
                </div>
                <div class="health-content">
                    <h2>System Status</h2>
                    <div class="health-status"><?php echo ucfirst($systemStatus['overall']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Component Status -->
        <div class="health-grid">
            <?php foreach ($systemStatus as $component => $status): ?>
                <?php if ($component === 'overall') continue; ?>
                <div class="health-card status-<?php echo $status['status']; ?>">
                    <div class="health-header">
                        <h3><?php echo ucfirst(str_replace('_', ' ', $component)); ?></h3>
                        <div class="health-indicator">
                            <?php
                            $indicators = ['ok' => '✅', 'warning' => '⚠️', 'error' => '❌'];
                            echo $indicators[$status['status']] ?? '❓';
                            ?>
                        </div>
                    </div>
                    <div class="health-message">
                        <?php echo htmlspecialchars($status['message']); ?>
                    </div>
                    
                    <?php if (isset($status['issues'])): ?>
                        <div class="health-issues">
                            <strong>Issues:</strong>
                            <ul>
                                <?php foreach ($status['issues'] as $issue): ?>
                                    <li><?php echo htmlspecialchars($issue); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($status['table_counts'])): ?>
                        <div class="health-details">
                            <strong>Table Records:</strong>
                            <ul>
                                <?php foreach ($status['table_counts'] as $table => $count): ?>
                                    <li><?php echo $table; ?>: <?php echo number_format($count); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Recent Errors -->
        <div class="health-section">
            <div class="section-header">
                <h3>Recent System Errors</h3>
            </div>
            <div class="errors-container">
                <?php if (empty($recentErrors)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">✅</div>
                        <p>No recent errors found</p>
                    </div>
                <?php else: ?>
                    <div class="errors-list">
                        <?php foreach ($recentErrors as $error): ?>
                            <div class="error-item level-<?php echo strtolower($error['level']); ?>">
                                <div class="error-header">
                                    <span class="error-level"><?php echo $error['level']; ?></span>
                                    <span class="error-time"><?php echo date('M j, H:i', strtotime($error['created_at'])); ?></span>
                                </div>
                                <div class="error-message">
                                    <?php echo htmlspecialchars($error['message']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Maintenance Actions -->
        <div class="health-section">
            <div class="section-header">
                <h3>Maintenance Actions</h3>
            </div>
            <div class="maintenance-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="cleanup_logs">
                    <div class="form-group" style="display: inline-block; margin-right: 10px;">
                        <select name="days" style="width: auto;">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Clean up old log entries?')">
                        Clean Up Logs
                    </button>
                </form>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <style>
        .health-overview {
            margin-bottom: 30px;
        }
        
        .health-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .overall-status {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 30px;
        }
        
        .health-icon {
            font-size: 48px;
        }
        
        .health-content h2 {
            margin: 0 0 10px 0;
            color: #1e293b;
        }
        
        .health-status {
            font-size: 24px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-healthy .health-status { color: #059669; }
        .status-warning .health-status { color: #d97706; }
        .status-critical .health-status { color: #dc2626; }
        
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .health-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .health-header h3 {
            margin: 0;
            color: #1e293b;
        }
        
        .health-indicator {
            font-size: 20px;
        }
        
        .health-message {
            color: #64748b;
            margin-bottom: 10px;
        }
        
        .health-issues, .health-details {
            margin-top: 15px;
            font-size: 14px;
        }
        
        .health-issues ul, .health-details ul {
            margin: 5px 0 0 20px;
            color: #64748b;
        }
        
        .health-section {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .section-header h3 {
            margin: 0;
            color: #1e293b;
        }
        
        .errors-container, .maintenance-actions {
            padding: 20px;
        }
        
        .errors-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .error-item {
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #e2e8f0;
        }
        
        .error-item.level-error {
            background: #fef2f2;
            border-left-color: #ef4444;
        }
        
        .error-item.level-critical {
            background: #fef2f2;
            border-left-color: #dc2626;
        }
        
        .error-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .error-level {
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .error-time {
            font-size: 12px;
            color: #64748b;
        }
        
        .error-message {
            color: #374151;
            font-size: 14px;
        }
    </style>
</body>
</html>