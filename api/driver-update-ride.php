<?php
/**
 * API Endpoint for Updating Ride Status
 * Allows a driver to update the status of a ride (arriving, arrived, in_progress, completed)
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

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

$driverId = $_SESSION['driver_id'];

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get ride ID and new status from request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$rideId = isset($data['ride_id']) ? intval($data['ride_id']) : 0;
$newStatus = isset($data['status']) ? sanitize($data['status']) : '';

if ($rideId <= 0) {
    $response['message'] = 'Invalid ride ID';
    echo json_encode($response);
    exit;
}

// Validate status
$validStatuses = ['arriving', 'arrived', 'in_progress', 'completed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    $response['message'] = 'Invalid status';
    echo json_encode($response);
    exit;
}

// Process the status update
try {
    $conn = dbConnect();
    $conn->begin_transaction();
    
    // First, check if the ride belongs to this driver
    $checkQuery = "
        SELECT id, status, driver_id
        FROM rides
        WHERE id = ?
        AND driver_id = ?
        FOR UPDATE
    ";
    
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("ii", $rideId, $driverId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        // Ride not found or doesn't belong to this driver
        $checkStmt->close();
        $conn->rollback();
        
        $response['message'] = 'Ride not found or not assigned to you';
        echo json_encode($response);
        exit;
    }
    
    $ride = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Check status transition
    $currentStatus = $ride['status'];
    $validTransition = true;
    
    switch ($newStatus) {
        case 'arriving':
            $validTransition = in_array($currentStatus, ['confirmed']);
            break;
        case 'arrived':
            $validTransition = in_array($currentStatus, ['confirmed', 'arriving']);
            break;
        case 'in_progress':
            $validTransition = in_array($currentStatus, ['confirmed', 'arriving', 'arrived']);
            break;
        case 'completed':
            $validTransition = in_array($currentStatus, ['in_progress']);
            break;
        case 'cancelled':
            $validTransition = !in_array($currentStatus, ['completed', 'cancelled']);
            break;
    }
    
    if (!$validTransition) {
        $conn->rollback();
        
        $response['message'] = "Invalid status transition from '$currentStatus' to '$newStatus'";
        echo json_encode($response);
        exit;
    }
    
    // Update the ride status
    $updateFields = "status = ?";
    $updateParams = [$newStatus];
    $updateTypes = "s";
    
    // If the ride is completed, set completion time
    if ($newStatus === 'completed') {
        $updateFields .= ", completed_at = NOW()";
    }
    
    // If the ride is cancelled, add reason if provided
    $cancellationReason = isset($data['reason']) ? sanitize($data['reason']) : '';
    if ($newStatus === 'cancelled' && !empty($cancellationReason)) {
        $updateFields .= ", cancellation_reason = ?";
        $updateParams[] = $cancellationReason;
        $updateTypes .= "s";
    }
    
    $updateQuery = "
        UPDATE rides
        SET $updateFields
        WHERE id = ?
    ";
    
    $updateParams[] = $rideId;
    $updateTypes .= "i";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param($updateTypes, ...$updateParams);
    $updateStmt->execute();
    $updateStmt->close();
    
    // If the ride is completed or cancelled, update driver status to available
    if (in_array($newStatus, ['completed', 'cancelled'])) {
        $updateDriverQuery = "
            UPDATE drivers
            SET status = 'available'
            WHERE id = ?
        ";
        
        $updateDriverStmt = $conn->prepare($updateDriverQuery);
        $updateDriverStmt->bind_param("i", $driverId);
        $updateDriverStmt->execute();
        $updateDriverStmt->close();
        
        // Update session status
        $_SESSION['driver']['status'] = 'available';
        
        // If completed, calculate earnings and add to driver's account
        if ($newStatus === 'completed') {
            // Get the fare amount
            $fareQuery = "SELECT fare FROM rides WHERE id = ?";
            $fareStmt = $conn->prepare($fareQuery);
            $fareStmt->bind_param("i", $rideId);
            $fareStmt->execute();
            $fareResult = $fareStmt->get_result();
            $fare = $fareResult->fetch_assoc()['fare'];
            $fareStmt->close();
            
            // Calculate driver's share (e.g., 80% of fare)
            $driverShare = $fare * 0.8;
            
            // Log the payment
            $paymentQuery = "
                INSERT INTO driver_payments (driver_id, ride_id, amount, status, payment_method, description, created_at)
                VALUES (?, ?, ?, 'pending', 'system', 'Earnings from ride #$rideId', NOW())
            ";
            
            $paymentStmt = $conn->prepare($paymentQuery);
            $paymentStmt->bind_param("iid", $driverId, $rideId, $driverShare);
            $paymentStmt->execute();
            $paymentStmt->close();
        }
    }
    
    $conn->commit();
    
    // Get updated ride details for response
    $query = "
        SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.status, r.created_at, r.completed_at,
               u.name as passenger_name, u.id as passenger_id
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $rideId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rideDetails = $result->fetch_assoc();
    $stmt->close();
    
    $conn->close();
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Ride status updated successfully';
    $response['data'] = [
        'ride' => [
            'id' => $rideDetails['id'],
            'status' => $rideDetails['status'],
            'previous_status' => $currentStatus
        ]
    ];
    
    // Add specific messages based on the new status
    switch ($newStatus) {
        case 'arriving':
            $response['data']['message'] = 'You are now en route to pick up the passenger.';
            break;
        case 'arrived':
            $response['data']['message'] = 'You have arrived at the pickup location. Wait for your passenger.';
            break;
        case 'in_progress':
            $response['data']['message'] = 'Ride in progress. Drive safely to the destination.';
            break;
        case 'completed':
            $response['data']['message'] = 'Ride completed successfully! You earned G$' . number_format($driverShare, 2);
            break;
        case 'cancelled':
            $response['data']['message'] = 'Ride has been cancelled. You are now available for new rides.';
            break;
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error updating ride status: " . $e->getMessage());
    $response['message'] = 'An error occurred while updating ride status';
}

echo json_encode($response);
exit;
?>