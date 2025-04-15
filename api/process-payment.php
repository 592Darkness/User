<?php
/**
 * API Endpoint for Processing Payments
 * Enhanced version with improved error handling and data validation
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);  // In production, do not display errors
ini_set('log_errors', 1);

// Always set JSON header for API endpoints
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Explicitly require config and functions
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/db.php';

    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401); // Unauthorized
        throw new Exception('Please log in to process a payment.');
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use POST.');
    }

    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }

    error_log("Payment process data: " . json_encode($data));

    // Get booking ID from request
    $rideId = isset($data['ride_id']) ? intval(sanitize($data['ride_id'])) : 0;
    $paymentMethod = isset($data['payment_method']) ? sanitize($data['payment_method']) : 'cash';
    $amount = isset($data['amount']) ? floatval(sanitize($data['amount'])) : 0;

    // Check if booking ID is provided
    if (empty($rideId)) {
        http_response_code(400); // Bad Request
        throw new Exception('Ride ID is required.');
    }

    // Validate payment method
    if (!in_array($paymentMethod, ['cash', 'card', 'wallet'])) {
        http_response_code(400);
        throw new Exception('Invalid payment method. Only cash, card, or wallet is allowed.');
    }

    // Validate amount
    if ($amount <= 0) {
        http_response_code(400);
        throw new Exception('Invalid payment amount. Must be greater than zero.');
    }

    $userId = $_SESSION['user_id'];
    $conn = dbConnect();

    // Begin a transaction for data integrity
    $conn->begin_transaction();

    try {
        // First, verify the ride exists and belongs to the user
        $rideStmt = $conn->prepare("
            SELECT id, user_id, driver_id, status, fare, final_fare, vehicle_type, payment_status 
            FROM rides 
            WHERE id = ? AND user_id = ?
        ");
        $rideStmt->bind_param("ii", $rideId, $userId);
        $rideStmt->execute();
        $rideResult = $rideStmt->get_result();

        if ($rideResult->num_rows === 0) {
            throw new Exception('Ride not found or not authorized.');
        }

        $ride = $rideResult->fetch_assoc();
        $rideStmt->close();

        // Check if the ride status is completed (can only pay for completed rides)
        if ($ride['status'] !== 'completed') {
            throw new Exception("Cannot process payment: ride is not completed (current status: {$ride['status']}).");
        }

        // Check if the ride is already paid
        if ($ride['payment_status'] === 'paid') {
            throw new Exception("This ride has already been paid for.");
        }

        // Use transaction ID or generate a unique one
        $transactionId = uniqid('PAY-') . '-' . time();

        // Record the payment
        $paymentStmt = $conn->prepare("
            INSERT INTO payments (
                user_id, 
                ride_id, 
                amount, 
                payment_method, 
                transaction_id, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $paymentStmt->bind_param("iidss", $userId, $rideId, $amount, $paymentMethod, $transactionId);
        $paymentStmt->execute();
        $paymentId = $conn->insert_id;
        $paymentStmt->close();

        // Update ride to mark as paid
        $updateRideStmt = $conn->prepare("
            UPDATE rides
            SET payment_status = 'paid',
                payment_method = ?,
                payment_completed_at = NOW()
            WHERE id = ?
        ");
        $updateRideStmt->bind_param("si", $paymentMethod, $rideId);
        $updateRideStmt->execute();
        $updateRideStmt->close();

        // Check if the table has a transaction_logs table, create if not
        $tableCheck = $conn->query("SHOW TABLES LIKE 'transaction_logs'");
        if ($tableCheck->num_rows === 0) {
            // Create the table
            $createTableSql = "CREATE TABLE transaction_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                ride_id INT NOT NULL,
                payment_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                transaction_type VARCHAR(50) NOT NULL,
                transaction_id VARCHAR(100) NOT NULL,
                details TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_ride_id (ride_id)
            )";
            $conn->query($createTableSql);
        }

        // Record transaction in logs
        try {
            $logStmt = $conn->prepare("
                INSERT INTO transaction_logs (
                    user_id,
                    ride_id,
                    payment_id,
                    amount,
                    transaction_type,
                    transaction_id,
                    details,
                    created_at
                ) VALUES (?, ?, ?, ?, 'payment', ?, ?, NOW())
            ");

            $details = json_encode([
                'payment_method' => $paymentMethod,
                'status' => 'completed',
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);

            $logStmt->bind_param("iiidss", $userId, $rideId, $paymentId, $amount, $transactionId, $details);
            $logStmt->execute();
            $logStmt->close();
        } catch (Exception $logEx) {
            // Just log the error but don't fail the transaction
            error_log("Error recording transaction log: " . $logEx->getMessage());
        }

        // Commit transaction
        $conn->commit();
        
        // Success response
        $response['success'] = true;
        $response['message'] = 'Payment processed successfully!';
        $response['data'] = [
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'formatted_amount' => 'G$' . number_format($amount, 2),
            'payment_method' => $paymentMethod,
            'ride_id' => $rideId
        ];

    } catch (Exception $e) {
        // Rollback the transaction and re-throw the exception
        if ($conn->ping()) {
            $conn->rollback();
        }
        throw $e;
    }

} catch (Exception $e) {
    // Log the error
    error_log("Payment processing error: " . $e->getMessage());
    
    // Set the error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Set appropriate HTTP status code if not already set
    if (http_response_code() === 200) {
        http_response_code(500);
    }
}

// Close the database connection if still open
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

// Send JSON response
echo json_encode($response);
exit;
?>