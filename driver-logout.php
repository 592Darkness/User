<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Clear driver-specific session variables
if (isset($_SESSION['driver_id'])) {
    unset($_SESSION['driver_id']);
}

if (isset($_SESSION['driver'])) {
    unset($_SESSION['driver']);
}

// Optional: Clear the entire session
// session_destroy();

setFlashMessage('success', 'You have been successfully logged out.');

// Redirect to the driver login page
redirect('driver-login.php');
exit;
?>