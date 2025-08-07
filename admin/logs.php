<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$level = $_GET['level'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($level) {
    $where[] = "level = ?";
    $params[] = $level;
}

if ($search) {
    $where[] = "message LIKE ?";
    $params[] = "%{$search}%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get logs
$logs = $db->fetchAll(
    "SELECT * FROM system_logs {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

// Get total count for pagination
$totalLogs = $db->fetchOne(
    "SELECT COUNT(*) as count FROM system_logs {$whereClause}",
    $params
)['count'];

$totalPages = ceil($totalLogs / $limit);

// Get log level counts
$levelCounts = $db->fetchAll("
    SELECT level, COUNT(*) as count 
    FROM system_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY level
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>System Logs</h1>
        </div>
        
        <!-- Log Level Stats -->
        <div class="stats-grid">
            <?php
            $levelStats = [];
            foreach ($levelCounts as $count) {
                $levelStats[$count['level']] = $count['count'];
            }
            
            $levels = ['INFO', 'WARNING', 'ERROR', 'CRITICAL'];
            $levelIcons = ['INFO' => 'ℹ️', 'WARNING' => '⚠️', 'ERROR' => '❌', 'CRITICAL' => '🚨'];
            $levelColors = ['INFO' => '', 'WARNING' => 'warning', 'ERROR' => 'negative', 'CRITICAL' => 'negative'];
            
            foreach ($levels as $lvl):
                $count = $levelStats[$lvl] ?? 0;
            ?>
                <div class="stat-card">
                    <div class="stat-icon"><?php echo $levelIcons[$lvl]; ?></div>
                    <div class="stat-content">
                        <h3><?php echo $lvl; ?> (24h)</h3>
                        <div class="stat-value <?php echo $levelColors[$lvl]; ?>"><?php echo $count; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label for="level">Level</label>
                    <select id="level" name="level">
                        <option value="">All Levels</option>
                        <option value="DEBUG" <?php echo $level === 'DEBUG' ? 'selected' : ''; ?>>Debug</option>
                        <option value="INFO" <?php echo $level === 'INFO' ? 'selected' : ''; ?>>Info</option>
                        <option value="WARNING" <?php echo $level === 'WARNING' ? 'selected' : ''; ?>>Warning</option>
                        <option value="ERROR" <?php echo $level === 'ERROR' ? 'selected' : ''; ?>>Error</option>
                        <option value="CRITICAL" <?php echo $level === 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search logs...">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="logs.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Logs Table -->
        <div class="logs-container">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📝</div>
                    <h3>No Logs Found</h3>
                    <p>No system logs match your criteria.</p>
                </div>
            <?php else: ?>
                <div class="logs-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Level</th>
                                <th>Message</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-row log-<?php echo strtolower($log['level']); ?>">
                                    <td><?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <span class="log-level level-<?php echo strtolower($log['level']); ?>">
                                            <?php echo $log['level']; ?>
                                        </span>
                                    </td>
                                    <td class="log-message">
                                        <?php echo htmlspecialchars($log['message']); ?>
                                        <?php if ($log['context']): ?>
                                            <details class="log-context">
                                                <summary>Context</summary>
                                                <pre><?php echo htmlspecialchars($log['context']); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $log['ip_address'] ?? '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&level=<?php echo $level; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm">Previous</a>
                        <?php endif; ?>
                        
                        <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&level=<?php echo $level; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>