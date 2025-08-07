<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$success = '';
$error = '';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'backup_database':
            try {
                $backupFile = createDatabaseBackup();
                $success = "Database backup created successfully: {$backupFile}";
            } catch (Exception $e) {
                $error = 'Database backup failed: ' . $e->getMessage();
            }
            break;
            
        case 'backup_files':
            try {
                $backupFile = createFilesBackup();
                $success = "Files backup created successfully: {$backupFile}";
            } catch (Exception $e) {
                $error = 'Files backup failed: ' . $e->getMessage();
            }
            break;
    }
}

function createDatabaseBackup() {
    $backupDir = '../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = 'database_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($filepath)
    );
    
    exec($command, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception('mysqldump command failed');
    }
    
    return $filename;
}

function createFilesBackup() {
    $backupDir = '../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = 'files_backup_' . date('Y-m-d_H-i-s') . '.zip';
    $filepath = $backupDir . '/' . $filename;
    
    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Cannot create zip file');
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('..'),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(realpath('..')) + 1);
            
            // Skip backup directory and logs
            if (strpos($relativePath, 'backups') === 0 || strpos($relativePath, 'logs') === 0) {
                continue;
            }
            
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    $zip->close();
    return $filename;
}

// Get existing backups
$backups = [];
$backupDir = '../backups';
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($backupDir . '/' . $file)) {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backupDir . '/' . $file),
                'date' => filemtime($backupDir . '/' . $file)
            ];
        }
    }
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Backup & Restore</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="backup-grid">
            <div class="backup-card">
                <div class="card-header">
                    <h3>💾 Create Backup</h3>
                </div>
                <div class="card-content">
                    <form method="POST" style="margin-bottom: 15px;">
                        <input type="hidden" name="action" value="backup_database">
                        <button type="submit" class="btn btn-primary">Backup Database</button>
                        <small class="form-help">Creates a SQL dump of the database</small>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="backup_files">
                        <button type="submit" class="btn btn-secondary">Backup Files</button>
                        <small class="form-help">Creates a ZIP archive of all project files</small>
                    </form>
                </div>
            </div>
            
            <div class="backup-card">
                <div class="card-header">
                    <h3>📋 Existing Backups</h3>
                </div>
                <div class="card-content">
                    <?php if (empty($backups)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <p>No backups found</p>
                        </div>
                    <?php else: ?>
                        <div class="backups-list">
                            <?php foreach ($backups as $backup): ?>
                                <div class="backup-item">
                                    <div class="backup-info">
                                        <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                                        <div class="backup-meta">
                                            <?php echo date('M j, Y H:i', $backup['date']); ?> • 
                                            <?php echo formatBytes($backup['size']); ?>
                                        </div>
                                    </div>
                                    <div class="backup-actions">
                                        <a href="../backups/<?php echo urlencode($backup['name']); ?>" 
                                           class="btn btn-sm btn-secondary" download>Download</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="alert alert-warning">
            <div class="alert-icon">⚠️</div>
            <div>
                <strong>Important Notes:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>Database backups require mysqldump to be available on the system</li>
                    <li>File backups may take time for large projects</li>
                    <li>Store backups in a secure location</li>
                    <li>Test restore procedures regularly</li>
                </ul>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <style>
        .backup-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .backup-card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .backups-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .backup-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8fafc;
            border-radius: 6px;
        }
        
        .backup-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .backup-meta {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
</body>
</html>

<?php
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}
?>