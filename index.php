<?php
require_once 'config.php';

// Redirect based on auth status
if (is_admin()) {
    header('Location: admin/dashboard.php');
    exit;
} elseif (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: login.php');
    exit;
}
?>
