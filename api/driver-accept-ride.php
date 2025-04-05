<?php
/**
 * API Endpoint for Accepting a Ride
 * Allows a driver to accept an available ride
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
$driver = $_SESSION['driver'];

// Check if driver is available
if ($driver['status'] !== 'available') {
    $response['message'] = 'You must be online to accept rides';
    $response['data'] = ['status' => $driver['status']];
    echo json_encode($response);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get ride ID from request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$rideId = isset($data['ride_id']) ? intval($data['ride_id']) : 0;

if ($rideId <= 0) {
    $response['message'] = 'Invalid ride ID';
    echo json_encode($response);
    exit;
}

// Process the ride acceptance
try {
    $conn = dbConnect();
    $conn->begin_transaction();
    
    // First, check if the ride is still available
    $checkQuery = "
        SELECT id, status, driver_id, vehicle_type
        FROM rides
        WHERE id = ?
        AND status = 'searching'
        AND driver_id IS NULL
        FOR UPDATE
    ";
    
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $rideId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        // Ride is not available
        $checkStmt->close();
        $conn->rollback();
        
        $response['message'] = 'This ride is no longer available';
        echo json_encode($response);
        exit;
    }
    
    $ride = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Check if the vehicle type matches
    if ($ride['vehicle_type'] !== $driver['vehicle_type']) {
        $conn->rollback();
        
        $response['message'] = 'This ride requires a different vehicle type';
        echo json_encode($response);
        exit;
    }
    
    // Update the ride with driver information
    $updateQuery = "
        UPDATE rides
        SET driver_id = ?, status = 'confirmed'
        WHERE id = ?
    ";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ii", $driverId, $rideId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Update driver status to busy
    $updateDriverQuery = "
        UPDATE drivers
        SET status = 'busy'
        WHERE id = ?
    ";
    
    $updateDriverStmt = $conn->prepare($updateDriverQuery);
    $updateDriverStmt->bind_param("i", $driverId);
    $updateDriverStmt->execute();
    $updateDriverStmt->close();
    
    // Update session status
    $_SESSION['driver']['status'] = 'busy';
    
    // Get ride details for response
    $detailsQuery = "
        SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.status, r.created_at,
               u.name as passenger_name, u.id as passenger_id, u.phone as passenger_phone
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ";
    
    $detailsStmt = $conn->prepare($detailsQuery);
    $detailsStmt->bind_param("i", $rideId);
    $detailsStmt->execute();
    $detailsResult = $detailsStmt->get_result();
    $rideDetails = $detailsResult->fetch_assoc();
    $detailsStmt->close();
    
    // Get passenger rating
    $ratingQuery = "
        SELECT AVG(rating) as avg_rating
        FROM driver_ratings
        WHERE user_id = ?
    ";
    
    $ratingStmt = $conn->prepare($ratingQuery);
    $ratingStmt->bind_param("i", $rideDetails['passenger_id']);
    $ratingStmt->execute();
    $ratingResult = $ratingStmt->get_result();
    $ratingData = $ratingResult->fetch_assoc();
    
    $passengerRating = $ratingData['avg_rating'] ?: 5.0; // Default to 5 if no rating yet
    $ratingStmt->close();
    
    $conn->commit();
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Ride accepted successfully';
    $response['data'] = [
        'ride' => [
            'id' => $rideDetails['id'],
            'pickup' => $rideDetails['pickup'],
            'dropoff' => $rideDetails['dropoff'],
            'fare' => $rideDetails['fare'],
            'formatted_fare' => 'G$' . number_format($rideDetails['fare']),
            'status' => $rideDetails['status'],
            'created_at' => $rideDetails['created_at'],
            'passenger' => [
                'id' => $rideDetails['passenger_id'],
                'name' => $rideDetails['passenger_name'],
                'phone' => $rideDetails['passenger_phone'],
                'rating' => number_format($passengerRating, 1)
            ]
        ]
    ];
    
} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    error_log("Error accepting ride: " . $e->getMessage());
    $response['message'] = 'An error occurred while accepting ride';
}

echo json_encode($response);
exit;
?>