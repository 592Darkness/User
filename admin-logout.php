<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Check if admin is logged in
if (isset($_SESSION['admin_id'])) {
    // Log the logout event
    error_log("Admin logged out. Admin ID: " . $_SESSION['admin_id'] . ", Username: " . $_SESSION['admin_username']);
    
    // Clear all admin session variables
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_name']);

    // Set a success message
    setFlashMessage('success', 'You have been successfully logged out.');
} else {
    // If not logged in, set a message anyway
    setFlashMessage('info', 'You were not logged in.');
}

// Redirect to the login page
header('Location: admin-login.php');
exit;
