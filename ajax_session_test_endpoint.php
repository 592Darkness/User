<?php
// ajax_session_test_endpoint.php

// 1. Absolutely first line: Include config for session handling
// This ensures session parameters are set correctly before session_start()
require_once 'includes/config.php'; // Make sure the path is correct

// 2. Set JSON header immediately to prevent HTML output issues
header('Content-Type: application/json');

// 3. Perform a simple session action to test read/write
$timestamp = time();
// Write a value to the session
$_SESSION['ajax_test_time'] = $timestamp;

// Read values from the session (including admin_id if set)
$isAdmin = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET';
$sessionTestTime = $_SESSION['ajax_test_time'] ?? 'NOT SET'; // Read back the value we just set

// 4. Prepare JSON response
$response = [
    'success' => true,
    'message' => 'AJAX session test endpoint reached successfully.',
    'session_id' => session_id(), // Current session ID
    'current_timestamp' => $timestamp, // Timestamp from this request
    'session_ajax_test_time' => $sessionTestTime, // Value read back from session
    'session_admin_id' => $isAdmin, // Check if admin ID is present
    'session_data_snapshot' => $_SESSION // Send back all current session data for debugging
];

// 5. Echo JSON and exit cleanly
// Ensure no other output happens before or after this
echo json_encode($response);
exit;
?>
