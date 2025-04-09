<?php
/**
 * API Endpoint for Processing Payments
 * Production-ready implementation with proper security and database transactions
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

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    $data = $_POST;
}

// Validate required fields
$rideId = isset($data['ride_id']) ? intval(sanitize($data['ride_id'])) : 0;
$paymentMethod = isset($data['payment_method']) ? sanitize($data['payment_method']) : 'cash';
$amount = isset($data['amount']) ? floatval(sanitize($data['amount'])) : 0;

if ($rideId <= 0) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid ride ID';
    echo json_encode($response);
    exit;
}

if ($amount <= 0) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid payment amount';
    echo json_encode($response);
    exit;
}

// Validate payment method
$validPaymentMethods = ['cash', 'card', 'bank_transfer', 'mobile_money', 'wallet'];
if (!in_array($paymentMethod, $validPaymentMethods)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid payment method';
    echo json_encode($response);
    exit;
}

try {
    $conn = dbConnect();
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Verify the ride exists and belongs to the current user
    $userId = $_SESSION['user_id'];
    $rideStmt = $conn->prepare("
        SELECT r.id, r.driver_id, r.fare, r.final_fare, r.status, 
               r.vehicle_type, d.name as driver_name
        FROM rides r
        LEFT JOIN drivers d ON r.driver_id = d.id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $rideStmt->bind_param("ii", $rideId, $userId);
    $rideStmt->execute();
    $rideResult = $rideStmt->get_result();
    
    if ($rideResult->num_rows === 0) {
        $rideStmt->close();
        $conn->rollback();
        http_response_code(404); // Not Found
        $response['message'] = 'Ride not found or not authorized';
        echo json_encode($response);
        exit;
    }
    
    $ride = $rideResult->fetch_assoc();
    $rideStmt->close();
    
    // Verify ride status is completed
    if ($ride['status'] !== 'completed') {
        $conn->rollback();
        http_response_code(400); // Bad Request
        $response['message'] = 'Payment can only be processed for completed rides';
        echo json_encode($response);
        exit;
    }
    
    // Check if payment already exists for this ride
    $checkPaymentStmt = $conn->prepare("
        SELECT id, status FROM payments 
        WHERE ride_id = ? AND user_id = ? AND status = 'completed'
    ");
    $checkPaymentStmt->bind_param("ii", $rideId, $userId);
    $checkPaymentStmt->execute();
    $checkPaymentResult = $checkPaymentStmt->get_result();
    
    if ($checkPaymentResult->num_rows > 0) {
        $existingPayment = $checkPaymentResult->fetch_assoc();
        $checkPaymentStmt->close();
        $conn->rollback();
        
        http_response_code(409); // Conflict
        $response['message'] = 'Payment has already been processed for this ride';
        $response['data'] = [
            'payment_id' => $existingPayment['id'],
            'status' => $existingPayment['status']
        ];
        echo json_encode($response);
        exit;
    }
    $checkPaymentStmt->close();
    
    // Generate a transaction ID
    $transactionId = strtoupper(uniqid('PAY-')) . '-' . time();
    
    // Record the payment
    $paymentStmt = $conn->prepare("
        INSERT INTO payments 
        (user_id, ride_id, driver_id, amount, payment_method, transaction_id, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
    ");
    $paymentStmt->bind_param("iiidss", $userId, $rideId, $ride['driver_id'], $amount, $paymentMethod, $transactionId);
    $paymentStmt->execute();
    $paymentId = $conn->insert_id;
    $paymentStmt->close();
    
    // Update ride to mark payment as completed
    $updateRideStmt = $conn->prepare("
        UPDATE rides 
        SET payment_status = 'completed', payment_id = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateRideStmt->bind_param("ii", $paymentId, $rideId);
    $updateRideStmt->execute();
    $updateRideStmt->close();
    
    // If driver exists, update driver payment record
    if (!empty($ride['driver_id'])) {
        // Calculate driver's share (80% of fare)
        $driverShare = $amount * 0.8;
        
        // Add driver payment record
        $driverPaymentStmt = $conn->prepare("
            INSERT INTO driver_payments 
            (driver_id, ride_id, payment_id, amount, status, payment_method, description, created_at) 
            VALUES (?, ?, ?, ?, 'pending', 'system', CONCAT('Payment for ride #', ?), NOW())
        ");
        $driverPaymentStmt->bind_param("iiidd", $ride['driver_id'], $rideId, $paymentId, $driverShare, $rideId);
        $driverPaymentStmt->execute();
        $driverPaymentStmt->close();
    }
    
    // Record payment in transaction log
    $logStmt = $conn->prepare("
        INSERT INTO transaction_logs
        (user_id, driver_id, ride_id, payment_id, amount, transaction_type, transaction_id, details, created_at)
        VALUES (?, ?, ?, ?, ?, 'payment', ?, ?, NOW())
    ");
    
    $details = json_encode([
        'payment_method' => $paymentMethod,
        'status' => 'completed',
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $logStmt->bind_param("iiiidss", $userId, $ride['driver_id'], $rideId, $paymentId, $amount, $transactionId, $details);
    $logStmt->execute();
    $logStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Payment processed successfully';
    $response['data'] = [
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'status' => 'completed',
        'amount' => $amount,
        'formatted_amount' => 'G$' . number_format($amount, 2),
        'payment_method' => $paymentMethod,
        'ride_id' => $rideId,
        'driver_name' => $ride['driver_name'] ?? 'N/A'
    ];
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Payment processing error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['message'] = 'An error occurred while processing payment';
}

echo json_encode($response);
exit;