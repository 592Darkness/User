<?php
/**
 * API Endpoint for Driver Status Update
 * Updates the driver's status in the database (available/busy)
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    setFlashMessage('error', 'You must be logged in to update your status.');
    redirect('../driver-login.php');
    exit;
}

$driverId = $_SESSION['driver_id'];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Invalid request method.');
    redirect('../driver-dashboard.php');
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Security validation failed. Please try again.');
    redirect('../driver-dashboard.php');
    exit;
}

// Get the new status from the form
$newStatus = isset($_POST['status']) ? sanitize($_POST['status']) : null;

// Validate the status
if (!in_array($newStatus, ['available', 'busy'])) {
    setFlashMessage('error', 'Invalid status value.');
    redirect('../driver-dashboard.php');
    exit;
}

// Update the status in the database
try {
    $conn = dbConnect();
    $stmt = $conn->prepare("UPDATE drivers SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $driverId);
    
    if ($stmt->execute()) {
        // Update the session variable
        $_SESSION['driver']['status'] = $newStatus;
        
        // Set success message
        if ($newStatus === 'available') {
            setFlashMessage('success', 'You are now online and available for rides.');
        } else {
            setFlashMessage('success', 'You are now offline and will not receive ride requests.');
        }
    } else {
        setFlashMessage('error', 'Failed to update status: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error updating driver status: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred. Please try again later.');
}

// Redirect back to the dashboard
redirect('../driver-dashboard.php');
exit;
?>