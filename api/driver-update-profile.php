<?php
/**
 * API Endpoint for Driver Profile Update
 * Updates the driver's profile information in the database
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    setFlashMessage('error', 'You must be logged in to update your profile.');
    redirect('../driver-login.php');
    exit;
}

$driverId = $_SESSION['driver_id'];
$currentDriver = $_SESSION['driver'];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Invalid request method.');
    redirect('../driver-dashboard.php?tab=profile');
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    setFlashMessage('error', 'Security validation failed. Please try again.');
    redirect('../driver-dashboard.php?tab=profile');
    exit;
}

// Get form data
$name = isset($_POST['name']) ? sanitize($_POST['name']) : $currentDriver['name'];
$email = isset($_POST['email']) ? sanitize($_POST['email']) : $currentDriver['email'];
$phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : $currentDriver['phone'];

// Get notification preferences
$notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
$notifySms = isset($_POST['notify_sms']) ? 1 : 0;
$notifyApp = isset($_POST['notify_app']) ? 1 : 0;

// Get password change info
$currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

// Validate required fields
$errors = [];
if (empty($name)) {
    $errors[] = 'Name is required.';
}

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is not valid.';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required.';
}

// Only validate passwords if the user is trying to change them
$passwordChanged = false;
if (!empty($currentPassword) || !empty($newPassword)) {
    if (empty($currentPassword)) {
        $errors[] = 'Current password is required to change your password.';
    }
    
    if (empty($newPassword)) {
        $errors[] = 'New password cannot be empty.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    
    if (empty($errors)) {
        // Verify current password
        try {
            $conn = dbConnect();
            $stmt = $conn->prepare("SELECT password FROM drivers WHERE id = ?");
            $stmt->bind_param("i", $driverId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $driver = $result->fetch_assoc();
                
                if (!password_verify($currentPassword, $driver['password'])) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $passwordChanged = true;
                }
            } else {
                $errors[] = 'Driver account not found.';
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = 'Failed to verify password: ' . $e->getMessage();
        }
    }
}

if (!empty($errors)) {
    setFlashMessage('error', implode(' ', $errors));
    redirect('../driver-dashboard.php?tab=profile');
    exit;
}

// Update profile in database
try {
    $conn = dbConnect();
    $conn->begin_transaction();
    
    // Update basic profile information
    $stmt = $conn->prepare("UPDATE drivers SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $phone, $driverId);
    $stmt->execute();
    $stmt->close();
    
    // Update password if changed
    if ($passwordChanged) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE drivers SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $driverId);
        $stmt->execute();
        $stmt->close();
    }
    
    // Update notification preferences
    $prefStmt = $conn->prepare("SELECT id FROM driver_preferences WHERE driver_id = ?");
    $prefStmt->bind_param("i", $driverId);
    $prefStmt->execute();
    $result = $prefStmt->get_result();
    $prefStmt->close();
    
    if ($result->num_rows > 0) {
        $updateStmt = $conn->prepare("UPDATE driver_preferences SET notify_email = ?, notify_sms = ?, notify_app = ?, updated_at = NOW() WHERE driver_id = ?");
        $updateStmt->bind_param("iiii", $notifyEmail, $notifySms, $notifyApp, $driverId);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        $insertStmt = $conn->prepare("INSERT INTO driver_preferences (driver_id, notify_email, notify_sms, notify_app, created_at) VALUES (?, ?, ?, ?, NOW())");
        $insertStmt->bind_param("iiii", $driverId, $notifyEmail, $notifySms, $notifyApp);
        $insertStmt->execute();
        $insertStmt->close();
    }
    
    $conn->commit();
    
    // Update session with new info
    $_SESSION['driver'] = array_merge($currentDriver, [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'preferences' => [
            'notify_email' => $notifyEmail,
            'notify_sms' => $notifySms,
            'notify_app' => $notifyApp
        ]
    ]);
    
    setFlashMessage('success', 'Your profile has been updated successfully.');
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating driver profile: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while updating your profile. Please try again later.');
}

// Redirect back to the profile tab
redirect('../driver-dashboard.php?tab=profile');
exit;
?>