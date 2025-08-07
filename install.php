<?php
/**
 * Enhanced Binance AI Trader Installation Script
 * Version 2.0.0
 * 
 * This script sets up the database and initial configuration
 * for the Enhanced Binance AI Trader system.
 */

// Prevent direct access if already installed
if (file_exists('config/installed.flag')) {
    header('Location: admin/login.php');
    exit;
}

// Error reporting for installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Installation state
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // System requirements check
            $requirements = checkSystemRequirements();
            if ($requirements['passed']) {
                header('Location: install.php?step=2');
                exit;
            } else {
                $errors = $requirements['errors'];
            }
            break;
            
        case 2:
            // Database configuration
            $dbResult = handleDatabaseConfiguration();
            if ($dbResult['success']) {
                header('Location: install.php?step=3');
                exit;
            } else {
                $errors[] = $dbResult['message'];
            }
            break;
            
        case 3:
            // Admin user creation
            $adminResult = handleAdminUserCreation();
            if ($adminResult['success']) {
                header('Location: install.php?step=4');
                exit;
            } else {
                $errors[] = $adminResult['message'];
            }
            break;
            
        case 4:
            // Final setup
            $finalResult = handleFinalSetup();
            if ($finalResult['success']) {
                header('Location: install.php?step=5');
                exit;
            } else {
                $errors[] = $finalResult['message'];
            }
            break;
    }
}

/**
 * Check system requirements
 */
function checkSystemRequirements() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP Version (7.4+)',
            'required' => true,
            'check' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'current' => PHP_VERSION
        ],
        'mysqli' => [
            'name' => 'MySQLi Extension',
            'required' => true,
            'check' => extension_loaded('mysqli'),
            'current' => extension_loaded('mysqli') ? 'Installed' : 'Not installed'
        ],
        'curl' => [
            'name' => 'cURL Extension',
            'required' => true,
            'check' => extension_loaded('curl'),
            'current' => extension_loaded('curl') ? 'Installed' : 'Not installed'
        ],
        'openssl' => [
            'name' => 'OpenSSL Extension',
            'required' => true,
            'check' => extension_loaded('openssl'),
            'current' => extension_loaded('openssl') ? 'Installed' : 'Not installed'
        ],
        'json' => [
            'name' => 'JSON Extension',
            'required' => true,
            'check' => extension_loaded('json'),
            'current' => extension_loaded('json') ? 'Installed' : 'Not installed'
        ],
        'mbstring' => [
            'name' => 'Multibyte String Extension',
            'required' => true,
            'check' => extension_loaded('mbstring'),
            'current' => extension_loaded('mbstring') ? 'Installed' : 'Not installed'
        ],
        'zip' => [
            'name' => 'ZIP Extension',
            'required' => false,
            'check' => extension_loaded('zip'),
            'current' => extension_loaded('zip') ? 'Installed' : 'Not installed'
        ],
        'config_writable' => [
            'name' => 'Config Directory Writable',
            'required' => true,
            'check' => is_writable('config') || (!is_dir('config') && is_writable('.')),
            'current' => is_writable('config') ? 'Writable' : 'Not writable'
        ],
        'logs_writable' => [
            'name' => 'Logs Directory Writable',
            'required' => true,
            'check' => is_writable('logs') || (!is_dir('logs') && is_writable('.')),
            'current' => is_writable('logs') ? 'Writable' : 'Not writable'
        ]
    ];
    
    $passed = true;
    $errors = [];
    
    foreach ($requirements as $key => $req) {
        if ($req['required'] && !$req['check']) {
            $passed = false;
            $errors[] = $req['name'] . ' is required but not available';
        }
    }
    
    return [
        'passed' => $passed,
        'requirements' => $requirements,
        'errors' => $errors
    ];
}

/**
 * Handle database configuration
 */
function handleDatabaseConfiguration() {
    $host = trim($_POST['db_host'] ?? '');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    
    // Validation
    if (empty($host) || empty($name) || empty($user)) {
        return ['success' => false, 'message' => 'Please fill in all required database fields'];
    }
    
    // Test database connection
    try {
        $connection = new mysqli($host, $user, $pass, $name);
        
        if ($connection->connect_error) {
            return ['success' => false, 'message' => 'Database connection failed: ' . $connection->connect_error];
        }
        
        $connection->set_charset('utf8mb4');
        
        // Test if we can create tables
        $testQuery = "CREATE TABLE IF NOT EXISTS test_install (id INT AUTO_INCREMENT PRIMARY KEY)";
        if (!$connection->query($testQuery)) {
            return ['success' => false, 'message' => 'Cannot create tables in database: ' . $connection->error];
        }
        
        // Clean up test table
        $connection->query("DROP TABLE IF EXISTS test_install");
        $connection->close();
        
        // Create database configuration file
        $configContent = generateDatabaseConfig($host, $name, $user, $pass);
        
        if (!file_put_contents('config/database.php', $configContent)) {
            return ['success' => false, 'message' => 'Cannot write database configuration file'];
        }
        
        return ['success' => true, 'message' => 'Database configuration saved successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()];
    }
}

/**
 * Generate database configuration file content
 */
function generateDatabaseConfig($host, $name, $user, $pass) {
    $encryptionKey = bin2hex(random_bytes(32));
    $jwtSecret = bin2hex(random_bytes(32));
    
    return "<?php
// Database Configuration
define('DB_HOST', '" . addslashes($host) . "');
define('DB_NAME', '" . addslashes($name) . "');
define('DB_USER', '" . addslashes($user) . "');
define('DB_PASS', '" . addslashes($pass) . "');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Enhanced Binance AI Trader');
define('APP_VERSION', '2.0.0');
define('TIMEZONE', 'UTC');
define('SESSION_LIFETIME', 3600);

// Security
define('JWT_SECRET', '{$jwtSecret}');
define('ENCRYPTION_KEY', '{$encryptionKey}');
?>";
}

/**
 * Handle admin user creation
 */
function handleAdminUserCreation() {
    if (!file_exists('config/database.php')) {
        return ['success' => false, 'message' => 'Database configuration not found'];
    }
    
    require_once 'config/database.php';
    
    $username = trim($_POST['admin_username'] ?? '');
    $password = $_POST['admin_password'] ?? '';
    $email = trim($_POST['admin_email'] ?? '');
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($password) || empty($email)) {
        return ['success' => false, 'message' => 'Please fill in all required fields'];
    }
    
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match'];
    }
    
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address'];
    }
    
    try {
        // Connect to database
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connection->connect_error) {
            return ['success' => false, 'message' => 'Database connection failed'];
        }
        
        $connection->set_charset('utf8mb4');
        
        // Create database schema
        $schemaResult = createDatabaseSchema($connection);
        if (!$schemaResult['success']) {
            return $schemaResult;
        }
        
        // Create admin user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $connection->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $username, $hashedPassword, $email);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Failed to create admin user: ' . $stmt->error];
        }
        
        $stmt->close();
        $connection->close();
        
        return ['success' => true, 'message' => 'Admin user created successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error creating admin user: ' . $e->getMessage()];
    }
}

/**
 * Create database schema
 */
function createDatabaseSchema($connection) {
    try {
        // Read and execute the enhanced schema
        $schemaFile = 'supabase/migrations/20250806122157_smooth_marsh.sql';
        
        if (!file_exists($schemaFile)) {
            // Fallback to basic schema if enhanced not found
            $schemaFile = 'database/schema.sql';
        }
        
        if (!file_exists($schemaFile)) {
            return ['success' => false, 'message' => 'Database schema file not found'];
        }
        
        $sql = file_get_contents($schemaFile);
        
        // Remove comments and split statements
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon and filter empty statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || strlen($statement) < 10) {
                continue;
            }
            
            // Skip certain statements that might cause issues
            if (stripos($statement, 'SET SQL_MODE') !== false ||
                stripos($statement, 'SET time_zone') !== false ||
                stripos($statement, 'START TRANSACTION') !== false ||
                stripos($statement, 'COMMIT') !== false) {
                continue;
            }
            
            if (!$connection->query($statement)) {
                // Log the error but continue with other statements
                error_log("Schema execution warning: " . $connection->error . " for statement: " . substr($statement, 0, 100));
            }
        }
        
        return ['success' => true, 'message' => 'Database schema created successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Schema creation failed: ' . $e->getMessage()];
    }
}

/**
 * Handle final setup
 */
function handleFinalSetup() {
    try {
        // Create necessary directories
        $directories = ['logs', 'backups', 'config'];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    return ['success' => false, 'message' => "Failed to create {$dir} directory"];
                }
            }
        }
        
        // Create .htaccess files for security
        createSecurityFiles();
        
        // Create installation flag
        if (!file_put_contents('config/installed.flag', date('Y-m-d H:i:s'))) {
            return ['success' => false, 'message' => 'Failed to create installation flag'];
        }
        
        return ['success' => true, 'message' => 'Installation completed successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Final setup failed: ' . $e->getMessage()];
    }
}

/**
 * Create security files
 */
function createSecurityFiles() {
    // Create .htaccess for config directory
    $configHtaccess = "Order Deny,Allow\nDeny from all";
    file_put_contents('config/.htaccess', $configHtaccess);
    
    // Create .htaccess for logs directory
    $logsHtaccess = "Order Deny,Allow\nDeny from all";
    file_put_contents('logs/.htaccess', $logsHtaccess);
    
    // Create .htaccess for backups directory
    $backupsHtaccess = "Order Deny,Allow\nDeny from all";
    file_put_contents('backups/.htaccess', $backupsHtaccess);
    
    // Create index.php files to prevent directory listing
    $indexContent = "<?php\n// Access denied\nheader('HTTP/1.0 403 Forbidden');\nexit;\n?>";
    file_put_contents('config/index.php', $indexContent);
    file_put_contents('logs/index.php', $indexContent);
    file_put_contents('backups/index.php', $indexContent);
}

/**
 * Get system information
 */
function getSystemInfo() {
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Binance AI Trader - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .install-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .step {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            font-weight: 600;
            margin: 0 10px;
            position: relative;
        }
        
        .step.active {
            background: #3b82f6;
            color: white;
        }
        
        .step.completed {
            background: #10b981;
            color: white;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e2e8f0;
            transform: translateY(-50%);
        }
        
        .install-content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-help {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .requirements-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .status-pass {
            color: #059669;
            font-weight: 600;
        }
        
        .status-fail {
            color: #dc2626;
            font-weight: 600;
        }
        
        .status-optional {
            color: #f59e0b;
            font-weight: 600;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .system-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .system-info h4 {
            margin-bottom: 15px;
            color: #374151;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 14px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            color: #64748b;
        }
        
        .info-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            transition: width 0.3s ease;
        }
        
        .completion-card {
            text-align: center;
            padding: 40px 20px;
        }
        
        .completion-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .completion-card h2 {
            color: #059669;
            margin-bottom: 15px;
        }
        
        .completion-card p {
            color: #64748b;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .install-container {
                margin: 10px;
            }
            
            .install-content {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🤖 Enhanced Binance AI Trader</h1>
            <p>Professional AI Trading System Installation</p>
        </div>
        
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
            <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
            <div class="step <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">4</div>
            <div class="step <?php echo $step >= 5 ? 'completed' : ''; ?>">5</div>
        </div>
        
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($step / 5) * 100; ?>%"></div>
        </div>
        
        <div class="install-content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Installation Error:</strong><br>
                    <?php foreach ($errors as $error): ?>
                        • <?php echo htmlspecialchars($error); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php foreach ($success as $msg): ?>
                        <?php echo htmlspecialchars($msg); ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step === 1): ?>
                <!-- Step 1: System Requirements -->
                <h2>Step 1: System Requirements Check</h2>
                <p>We'll verify that your server meets all the requirements for the Enhanced Binance AI Trader.</p>
                
                <?php
                $requirements = checkSystemRequirements();
                $systemInfo = getSystemInfo();
                ?>
                
                <div class="system-info">
                    <h4>System Information</h4>
                    <div class="info-grid">
                        <?php foreach ($systemInfo as $key => $value): ?>
                            <div class="info-item">
                                <span class="info-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                <span class="info-value"><?php echo htmlspecialchars($value); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>Status</th>
                            <th>Current</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requirements['requirements'] as $req): ?>
                            <tr>
                                <td><?php echo $req['name']; ?></td>
                                <td>
                                    <?php if ($req['check']): ?>
                                        <span class="status-pass">✓ Pass</span>
                                    <?php elseif ($req['required']): ?>
                                        <span class="status-fail">✗ Fail</span>
                                    <?php else: ?>
                                        <span class="status-optional">⚠ Optional</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($req['current']); ?></td>
                                <td><?php echo $req['required'] ? 'Required' : 'Optional'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (!$requirements['passed']): ?>
                    <div class="alert alert-error">
                        <strong>Requirements Not Met:</strong><br>
                        Please fix the following issues before continuing:
                        <ul style="margin: 10px 0 0 20px;">
                            <?php foreach ($requirements['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <strong>All Requirements Met!</strong><br>
                        Your server meets all the requirements for the Enhanced Binance AI Trader.
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-actions">
                        <?php if ($requirements['passed']): ?>
                            <button type="submit" class="btn btn-primary">Continue to Database Setup</button>
                        <?php else: ?>
                            <button type="button" onclick="location.reload()" class="btn btn-secondary">Recheck Requirements</button>
                        <?php endif; ?>
                    </div>
                </form>
                
            <?php elseif ($step === 2): ?>
                <!-- Step 2: Database Configuration -->
                <h2>Step 2: Database Configuration</h2>
                <p>Configure your MySQL database connection. Make sure the database exists and the user has full privileges.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                        <div class="form-help">Usually 'localhost' for local installations</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'binance_ai_trader'); ?>" required>
                        <div class="form-help">The database must already exist</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>">
                        <div class="form-help">Leave empty if no password is set</div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="install.php?step=1" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
                    </div>
                </form>
                
            <?php elseif ($step === 3): ?>
                <!-- Step 3: Admin User Creation -->
                <h2>Step 3: Create Admin User</h2>
                <p>Create your administrator account to access the trading dashboard.</p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="admin_username">Admin Username</label>
                        <input type="text" id="admin_username" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" required>
                        <div class="form-help">Choose a secure username for your admin account</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Admin Email</label>
                        <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                        <div class="form-help">Used for account recovery and notifications</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">Admin Password</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                        <div class="form-help">Minimum 8 characters, use a strong password</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <a href="install.php?step=2" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Create Admin User</button>
                    </div>
                </form>
                
            <?php elseif ($step === 4): ?>
                <!-- Step 4: Final Setup -->
                <h2>Step 4: Final Setup</h2>
                <p>Complete the installation by setting up security files and finalizing the configuration.</p>
                
                <div class="alert alert-warning">
                    <strong>Important Security Notes:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>The system will be installed in <strong>testnet mode</strong> by default</li>
                        <li>You can configure your Binance API credentials after installation</li>
                        <li>Always test thoroughly with testnet before using live trading</li>
                        <li>Never risk more than you can afford to lose</li>
                    </ul>
                </div>
                
                <div class="system-info">
                    <h4>Installation Summary</h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Database:</span>
                            <span class="info-value">✓ Configured</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Admin User:</span>
                            <span class="info-value">✓ Created</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Security:</span>
                            <span class="info-value">✓ Ready</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Trading Mode:</span>
                            <span class="info-value">🧪 Testnet</span>
                        </div>
                    </div>
                </div>
                
                <form method="POST">
                    <div class="form-actions">
                        <a href="install.php?step=3" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-success">Complete Installation</button>
                    </div>
                </form>
                
            <?php elseif ($step === 5): ?>
                <!-- Step 5: Installation Complete -->
                <div class="completion-card">
                    <div class="completion-icon">🎉</div>
                    <h2>Installation Complete!</h2>
                    <p>Your Enhanced Binance AI Trader has been successfully installed and configured.</p>
                    
                    <div class="alert alert-success">
                        <strong>Next Steps:</strong>
                        <ol style="margin: 10px 0 0 20px; text-align: left;">
                            <li>Login to the admin dashboard</li>
                            <li>Configure your Binance API credentials in Settings</li>
                            <li>Set up your trading pairs and risk management</li>
                            <li>Test with testnet before enabling live trading</li>
                            <li>Set up the cron job for automated trading</li>
                        </ol>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Cron Job Setup:</strong><br>
                        Add this to your crontab to run the enhanced trading bot every 5 minutes:<br>
                        <code style="background: #f1f5f9; padding: 5px; border-radius: 3px; font-family: monospace;">
                            */5 * * * * /usr/bin/php <?php echo realpath('.'); ?>/cron/enhanced_trading_bot.php
                        </code>
                    </div>
                    
                    <div class="form-actions">
                        <a href="admin/login.php" class="btn btn-success">Access Admin Dashboard</a>
                        <a href="README.md" class="btn btn-secondary" target="_blank">View Documentation</a>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(function(field) {
                        if (!field.value.trim()) {
                            field.style.borderColor = '#ef4444';
                            isValid = false;
                        } else {
                            field.style.borderColor = '#d1d5db';
                        }
                    });
                    
                    // Password confirmation check
                    const password = form.querySelector('#admin_password');
                    const confirmPassword = form.querySelector('#confirm_password');
                    
                    if (password && confirmPassword) {
                        if (password.value !== confirmPassword.value) {
                            confirmPassword.style.borderColor = '#ef4444';
                            alert('Passwords do not match');
                            isValid = false;
                            e.preventDefault();
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields correctly');
                    } else {
                        // Show loading state
                        const submitButton = form.querySelector('button[type="submit"]');
                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.textContent = 'Processing...';
                        }
                    }
                });
            });
            
            // Real-time password validation
            const passwordField = document.getElementById('admin_password');
            const confirmField = document.getElementById('confirm_password');
            
            if (passwordField && confirmField) {
                function validatePasswords() {
                    if (passwordField.value && confirmField.value) {
                        if (passwordField.value === confirmField.value) {
                            confirmField.style.borderColor = '#10b981';
                        } else {
                            confirmField.style.borderColor = '#ef4444';
                        }
                    }
                }
                
                passwordField.addEventListener('input', validatePasswords);
                confirmField.addEventListener('input', validatePasswords);
            }
        });
    </script>
</body>
</html>