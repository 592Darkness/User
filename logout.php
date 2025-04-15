<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Log the current session state for debugging
error_log("Logout requested. Session before logout: " . json_encode($_SESSION));

// Perform logout
logout();

// Double-check that session is cleared
$_SESSION = array();

// Clear the session cookie again
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Try to start a new session to verify the old one is gone
session_regenerate_id(true);

// Log the session state after logout
error_log("Session after logout: " . json_encode($_SESSION));

// If this is an AJAX request, return JSON
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'You have been successfully logged out.'
    ]);
    exit;
}

// Set flash message and redirect
setFlashMessage('success', 'You have been successfully logged out.');
redirect('index.php');