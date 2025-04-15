<?php
/**
 * API Endpoint for Cancelling a Ride
 * Supports cancellation for various ride stages with comprehensive logic
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);  // Change to 0 in production

// Prevent any output before headers are sent
ob_start();

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

    // Check if this is an API request with an endpoint
    $endpoint = isset($_GET['endpoint']) ? sanitize($_GET['endpoint']) : '';
    $method = $_SERVER['REQUEST_METHOD'];

    // If an endpoint is specified but not 'cancel-ride', throw an error
    if (!empty($endpoint) && $endpoint !== 'cancel-ride') {
        throw new Exception("Unhandled endpoint: $endpoint");
    }

    // Check if user is logged in
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        http_response_code(401); // Unauthorized
        throw new Exception('You must be logged in to cancel a ride.');
    }

    // Check if request method is POST
    if ($method !== 'POST') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use POST.');
    }

    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }

    // Get booking ID from request
    $bookingId = isset($data['booking_id']) ? intval(sanitize($data['booking_id'])) : 0;

    // Check if booking ID is provided
    if (empty($bookingId)) {
        http_response_code(400); // Bad Request
        throw new Exception('Booking ID is required.');
    }

    $userId = $currentUser['id'];
    $conn = dbConnect();

    // Begin a transaction for data integrity
    $conn->begin_transaction();

    try {
        // First, verify the ride exists and belongs to the user
        $rideStmt = $conn->prepare("
            SELECT id, status, driver_id, pickup, dropoff, fare 
            FROM rides 
            WHERE id = ? AND user_id = ?
        ");
        $rideStmt->bind_param("ii", $bookingId, $userId);
        $rideStmt->execute();
        $rideResult = $rideStmt->get_result();

        if ($rideResult->num_rows === 0) {
            $rideStmt->close();
            http_response_code(404); // Not Found
            throw new Exception('Booking not found or not authorized.');
        }

        $ride = $rideResult->fetch_assoc();
        $rideStmt->close();

        // Define allowed statuses for cancellation
        $cancelableStatuses = ['searching', 'confirmed', 'arriving', 'scheduled'];

        // Check if ride can be cancelled
        if (!in_array($ride['status'], $cancelableStatuses)) {
            http_response_code(400); // Bad Request
            throw new Exception("This ride cannot be cancelled because it is currently {$ride['status']}.");
        }

        // Check table structure and modify query accordingly
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM rides");
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        // Prepare update query based on available columns
        $updateQuery = "UPDATE rides SET status = 'cancelled'";
        if (in_array('final_fare', $columns)) {
            $updateQuery .= ", final_fare = 0";
        }
        $updateQuery .= " WHERE id = ?";

        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $bookingId);
        $updateStmt->execute();
        $updateStmt->close();

        // If a driver was assigned, update their status back to available
        if (!empty($ride['driver_id'])) {
            $driverStmt = $conn->prepare("
                UPDATE drivers 
                SET status = 'available' 
                WHERE id = ?
            ");
            $driverStmt->bind_param("i", $ride['driver_id']);
            $driverStmt->execute();
            $driverStmt->close();
        }

        // Log the cancellation
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs (
                ride_id, 
                user_id, 
                driver_id, 
                action, 
                details, 
                created_at
            ) VALUES (
                ?, ?, ?, 'cancelled', ?, NOW()
            )
        ");

        $details = json_encode([
            'cancelled_at' => date('Y-m-d H:i:s'),
            'previous_status' => $ride['status'],
            'by_user' => true,
            'pickup' => $ride['pickup'],
            'dropoff' => $ride['dropoff']
        ]);

        $driverId = $ride['driver_id'] ?? null;
        $logStmt->bind_param("iiss", $bookingId, $userId, $driverId, $details);
        $logStmt->execute();
        $logStmt->close();

        // Commit the transaction
        $conn->commit();

        // Check if user has a rewards program
        $pointsStmt = $conn->prepare("SELECT points FROM reward_points WHERE user_id = ?");
        $pointsStmt->bind_param("i", $userId);
        $pointsStmt->execute();
        $pointsResult = $pointsStmt->get_result();

        $currentPoints = 0;
        if ($pointsResult->num_rows > 0) {
            $pointsData = $pointsResult->fetch_assoc();
            $currentPoints = $pointsData['points'];
        }
        $pointsStmt->close();

        // Set success response
        $response['success'] = true;
        $response['message'] = 'Ride cancelled successfully.';
        $response['data'] = [
            'booking_id' => $bookingId,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'points' => $currentPoints
        ];

        $conn->close();
    } catch (Exception $transactionError) {
        // Rollback the transaction on error
        $conn->rollback();
        $conn->close();
        throw $transactionError;
    }

} catch (Exception $e) {
    // Log the error
    error_log("API cancel-ride error: " . $e->getMessage());
    
    // Set the error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Set appropriate HTTP status code if not already set
    if (http_response_code() === 200) {
        http_response_code(500);
    }
}

// Clear output buffer and send JSON response
ob_end_clean();
echo json_encode($response);
exit;
?>