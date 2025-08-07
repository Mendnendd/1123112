<?php

class Auth {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($username, $password) {
        try {
            // Check if user exists and get user data
            $user = $this->db->fetchOne(
                "SELECT * FROM admin_users WHERE username = ?",
                [$username]
            );
            
            if (!$user) {
                $this->logSecurityEvent('LOGIN_FAILED', "Invalid username: {$username}");
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Check if account is locked
            if ($this->isAccountLocked($user)) {
                $this->logSecurityEvent('LOGIN_BLOCKED', "Account locked: {$username}");
                return ['success' => false, 'message' => 'Account temporarily locked due to multiple failed attempts'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->incrementLoginAttempts($user['id']);
                $this->logSecurityEvent('LOGIN_FAILED', "Invalid password for: {$username}");
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Reset login attempts on successful login
            $this->resetLoginAttempts($user['id']);
            
            // Update last login
            $this->db->update(
                'admin_users',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = ?',
                [$user['id']]
            );
            
            // Create session
            $this->createSession($user);
            
            $this->logSecurityEvent('LOGIN_SUCCESS', "Successful login: {$username}");
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed due to system error'];
        }
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        $this->logSecurityEvent('LOGOUT', 'User logged out');
    }
    
    public function isLoggedIn() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'] ?? ''
        ];
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current user data
            $user = $this->db->fetchOne(
                "SELECT password FROM admin_users WHERE id = ?",
                [$userId]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'New password must be at least 8 characters long'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $this->db->update(
                'admin_users',
                ['password' => $hashedPassword],
                'id = ?',
                [$userId]
            );
            
            $this->logSecurityEvent('PASSWORD_CHANGED', "Password changed for user ID: {$userId}");
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }
    
    private function createSession($user) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    
    private function isAccountLocked($user) {
        if ($user['login_attempts'] >= $this->maxLoginAttempts) {
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                return true;
            }
        }
        return false;
    }
    
    private function incrementLoginAttempts($userId) {
        $attempts = $this->db->fetchOne(
            "SELECT login_attempts FROM admin_users WHERE id = ?",
            [$userId]
        )['login_attempts'] + 1;
        
        $lockedUntil = null;
        if ($attempts >= $this->maxLoginAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $this->lockoutTime);
        }
        
        $this->db->update(
            'admin_users',
            [
                'login_attempts' => $attempts,
                'locked_until' => $lockedUntil
            ],
            'id = ?',
            [$userId]
        );
    }
    
    private function resetLoginAttempts($userId) {
        $this->db->update(
            'admin_users',
            [
                'login_attempts' => 0,
                'locked_until' => null
            ],
            'id = ?',
            [$userId]
        );
    }
    
    private function logSecurityEvent($event, $message) {
        try {
            $this->db->insert('system_logs', [
                'level' => 'INFO',
                'message' => "[SECURITY] {$event}: {$message}",
                'context' => json_encode([
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    public function generateCSRFToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}