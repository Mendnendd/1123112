<?php
// Check if installation is complete
if (!file_exists('config/installed.flag')) {
    header('Location: install.php');
    exit;
}

// Redirect to admin dashboard
header('Location: admin/index.php');
exit;
?>