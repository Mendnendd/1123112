<?php
// Check if installation is complete
if (!file_exists(__DIR__ . '/installed.flag')) {
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        // Only redirect if we're not in a CLI environment
        if (php_sapi_name() !== 'cli') {
            header('Location: install.php');
            exit;
        }
    }
}

// Load database configuration
if (file_exists(__DIR__ . '/database.php')) {
    require_once __DIR__ . '/database.php';
} else {
    if (basename($_SERVER['PHP_SELF']) !== 'install.php') {
        // Only die if we're not in CLI environment
        if (php_sapi_name() !== 'cli') {
            die('Database configuration not found. Please run the installation.');
        }
    }
}

// Application constants (only define if not already defined)
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Enhanced Binance AI Trader');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

// Set timezone
date_default_timezone_set(TIMEZONE ?? 'UTC');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
if (defined('DB_HOST')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// Autoload classes
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CSRF protection function
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}