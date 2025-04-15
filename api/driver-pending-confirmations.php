<?php
/**
 * API endpoint to check for pending payment confirmations
 * Returns rides where payment needs confirmation from the driver
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set Content-Type header to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => true,
    'message' => '',
    'rides' => []
];

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    $response['success'] = false;
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

$driverId = $_SESSION['driver_id'];

// Fetch payment confirmations needed
try {
    $conn = dbConnect();
    
    // Query to get rides that need payment confirmation
    $query = "
        SELECT r.id as ride_id, r.user_id, r.fare, r.final_fare, r.status, r.payment_status,
               u.name as customer_name, u.phone as customer_phone
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE r.driver_id = ?
        AND r.status = 'completed'
        AND (r.payment_status = 'pending' OR r.payment_status = 'customer_confirmed')
        ORDER BY r.completed_at DESC
        LIMIT 5
    ";
    
    // Create the prepared statement
    $stmt = $conn->prepare($query);
    
    // If statement preparation failed, fall back to empty result
    if (!$stmt) {
        error_log("Error preparing query in driver-pending-confirmations.php: " . $conn->error);
        echo json_encode($response);
        exit;
    }
    
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Add any found rides to the response
    while ($row = $result->fetch_assoc()) {
        // Use final_fare if available, otherwise use fare
        $fare = isset($row['final_fare']) && $row['final_fare'] > 0 ? $row['final_fare'] : $row['fare'];
        
        $response['rides'][] = [
            'ride_id' => $row['ride_id'],
            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'fare' => $fare,
            'formatted_fare' => 'G$' . number_format($fare, 2),
            'payment_status' => $row['payment_status']
        ];
    }
    
    $stmt->close();
    
    // Add message based on results
    if (count($response['rides']) > 0) {
        $response['message'] = 'You have ' . count($response['rides']) . ' pending payment' . 
                              (count($response['rides']) > 1 ? 's' : '') . ' to confirm.';
    } else {
        $response['message'] = 'No pending payment confirmations found.';
    }
    
    $conn->close();
    
} catch (Exception $e) {
    // Log error but return empty result to prevent breaking the UI
    error_log("Error in driver-pending-confirmations.php: " . $e->getMessage());
    $response['success'] = true; // Still return success to prevent JS errors
    $response['message'] = 'Error checking for confirmations';
}

echo json_encode($response);
exit;