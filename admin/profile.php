<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance();
$user = $auth->getCurrentUser();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $email = trim($_POST['email'] ?? '');
                
                if (empty($email)) {
                    $error = 'Email is required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    try {
                        $db->update(
                            'admin_users',
                            ['email' => $email],
                            'id = ?',
                            [$user['id']]
                        );
                        $success = 'Profile updated successfully.';
                        $_SESSION['email'] = $email;
                    } catch (Exception $e) {
                        $error = 'Failed to update profile: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'All password fields are required.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'New password must be at least 8 characters long.';
                } else {
                    $result = $auth->changePassword($user['id'], $currentPassword, $newPassword);
                    if ($result['success']) {
                        $success = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
        }
    }
}

// Get updated user data
$userData = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>User Profile</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <!-- Profile Information -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>👤 Profile Information</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($userData['username']); ?>" disabled>
                            <small class="form-help">Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Last Login</label>
                            <input type="text" value="<?php echo $userData['last_login'] ? date('M j, Y H:i', strtotime($userData['last_login'])) : 'Never'; ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Account Created</label>
                            <input type="text" value="<?php echo date('M j, Y H:i', strtotime($userData['created_at'])); ?>" disabled>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="settings-card">
                <div class="card-header">
                    <h3>🔒 Change Password</h3>
                </div>
                <div class="card-content">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small class="form-help">Minimum 8 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Account Security -->
        <div class="settings-card">
            <div class="card-header">
                <h3>🛡️ Account Security</h3>
            </div>
            <div class="card-content">
                <div class="security-info">
                    <div class="security-item">
                        <div class="security-label">Login Attempts</div>
                        <div class="security-value"><?php echo $userData['login_attempts']; ?></div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-label">Account Status</div>
                        <div class="security-value">
                            <?php if ($userData['locked_until'] && strtotime($userData['locked_until']) > time()): ?>
                                <span class="status-badge status-warning">Locked until <?php echo date('M j, H:i', strtotime($userData['locked_until'])); ?></span>
                            <?php else: ?>
                                <span class="status-badge status-success">Active</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="security-item">
                        <div class="security-label">Session Security</div>
                        <div class="security-value">
                            <span class="status-badge status-success">Secure</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>