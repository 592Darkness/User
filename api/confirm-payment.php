<?php
/**
 * API Endpoint for Confirming or Disputing Payments
 * Handles payment confirmation from both drivers and passengers
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Set Content-Type header to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if user is logged in (either driver or passenger)
if ((!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) && 
    (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']))) {
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get data from request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$rideId = isset($data['ride_id']) ? intval($data['ride_id']) : 0;
$action = isset($data['action']) ? sanitize($data['action']) : '';
$userType = isset($data['user_type']) ? sanitize($data['user_type']) : '';

if ($rideId <= 0) {
    $response['message'] = 'Invalid ride ID';
    echo json_encode($response);
    exit;
}

if (!in_array($action, ['confirm', 'dispute'])) {
    $response['message'] = 'Invalid action. Must be either "confirm" or "dispute"';
    echo json_encode($response);
    exit;
}

if (!in_array($userType, ['driver', 'passenger'])) {
    $response['message'] = 'Invalid user type. Must be either "driver" or "passenger"';
    echo json_encode($response);
    exit;
}

// Get the appropriate ID based on user type
$userId = ($userType === 'driver') ? $_SESSION['driver_id'] : $_SESSION['user_id'];

// Process the confirmation/dispute
try {
    $conn = dbConnect();
    $conn->begin_transaction();
    
    // First, check if the ride exists and is associated with this user
    $checkQuery = ($userType === 'driver') 
        ? "SELECT id, status, payment_status, user_id, driver_id FROM rides WHERE id = ? AND driver_id = ?"
        : "SELECT id, status, payment_status, user_id, driver_id FROM rides WHERE id = ? AND user_id = ?";
    
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $rideId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        // Ride not found or doesn't belong to this user
        $checkStmt->close();
        $conn->rollback();
        
        $response['message'] = 'Ride not found or not associated with your account';
        echo json_encode($response);
        exit;
    }
    
    $ride = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Check ride status - must be completed
    if ($ride['status'] !== 'completed') {
        $conn->rollback();
        $response['message'] = 'Ride must be completed before confirming payment';
        echo json_encode($response);
        exit;
    }
    
    // Set new payment status based on action and user type
    $newPaymentStatus = '';
    $log_action = '';
    
    if ($action === 'confirm') {
        if ($userType === 'passenger') {
            $newPaymentStatus = 'customer_confirmed';
            $log_action = 'payment_confirmed_by_customer';
        } else { // driver
            $newPaymentStatus = 'confirmed';
            $log_action = 'payment_confirmed_by_driver';
        }
    } else { // dispute
        if ($userType === 'passenger') {
            $newPaymentStatus = 'customer_disputed';
            $log_action = 'payment_disputed_by_customer';
        } else { // driver
            $newPaymentStatus = 'driver_disputed';
            $log_action = 'payment_disputed_by_driver';
        }
    }
    
    // Update the ride payment status
    $updateQuery = "
        UPDATE rides
        SET payment_status = ?
        WHERE id = ?
    ";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $newPaymentStatus, $rideId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Log the payment confirmation/dispute
    $logQuery = "
        INSERT INTO ride_logs (
            ride_id,
            user_id,
            driver_id,
            action,
            details,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ";
    
    $logDetails = json_encode([
        'user_type' => $userType,
        'action' => $action,
        'previous_status' => $ride['payment_status'],
        'new_status' => $newPaymentStatus
    ]);
    
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("iiiss", $rideId, $ride['user_id'], $ride['driver_id'], $log_action, $logDetails);
    $logStmt->execute();
    $logStmt->close();
    
    // If both passenger and driver confirmed, set payment as fully confirmed
    if ($newPaymentStatus === 'confirmed' && $ride['payment_status'] === 'customer_confirmed') {
        // Both sides confirmed - mark as fully confirmed
        $finalUpdateQuery = "
            UPDATE rides
            SET payment_status = 'fully_confirmed'
            WHERE id = ?
        ";
        
        $finalUpdateStmt = $conn->prepare($finalUpdateQuery);
        $finalUpdateStmt->bind_param("i", $rideId);
        $finalUpdateStmt->execute();
        $finalUpdateStmt->close();
        
        // Update driver payment status to approved
        $driverPaymentQuery = "
            UPDATE driver_payments
            SET status = 'approved'
            WHERE ride_id = ? AND driver_id = ?
        ";
        
        $driverPaymentStmt = $conn->prepare($driverPaymentQuery);
        $driverPaymentStmt->bind_param("ii", $rideId, $ride['driver_id']);
        $driverPaymentStmt->execute();
        $driverPaymentStmt->close();
        
        $newPaymentStatus = 'fully_confirmed';
    }
    
    $conn->commit();
    
    // Success response
    $response['success'] = true;
    if ($action === 'confirm') {
        $response['message'] = 'Payment confirmed successfully';
    } else {
        $response['message'] = 'Payment dispute submitted successfully';
    }
    
    $response['data'] = [
        'ride_id' => $rideId,
        'new_status' => $newPaymentStatus,
        'action' => $action
    ];
    
} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error confirming/disputing payment: " . $e->getMessage());
    $response['message'] = 'An error occurred while processing your request';
}

echo json_encode($response);
exit;