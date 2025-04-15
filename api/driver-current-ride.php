<?php
/**
 * API Endpoint for Driver's Current Ride
 * Returns information about the driver's current active ride from the database
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

// Fetch current ride
try {
    $conn = dbConnect();
    
    // Query for an active ride (status is not completed or cancelled)
    $query = "
        SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.vehicle_type, 
               r.status, r.created_at, r.notes,
               u.name as passenger_name, u.id as passenger_id, u.phone as passenger_phone
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE r.driver_id = ?
        AND r.status NOT IN ('completed', 'cancelled')
        ORDER BY r.created_at DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ride = $result->fetch_assoc();
        
        // Get passenger rating
        $ratingQuery = "
            SELECT AVG(rating) as avg_rating
            FROM driver_ratings
            WHERE user_id = ?
        ";
        $ratingStmt = $conn->prepare($ratingQuery);
        $ratingStmt->bind_param("i", $ride['passenger_id']);
        $ratingStmt->execute();
        $ratingResult = $ratingStmt->get_result();
        $ratingData = $ratingResult->fetch_assoc();
        
        $passengerRating = $ratingData['avg_rating'] ?: 5.0; // Default to 5 if no rating yet
        $ratingStmt->close();
        
        // Format ride data
        $response['success'] = true;
        $response['message'] = 'Current ride retrieved successfully';
        $response['data'] = [
            'has_ride' => true,
            'ride' => [
                'id' => $ride['id'],
                'pickup' => $ride['pickup'],
                'dropoff' => $ride['dropoff'],
                'fare' => $ride['fare'],
                'formatted_fare' => 'G$' . number_format($ride['fare']),
                'vehicle_type' => $ride['vehicle_type'],
                'status' => $ride['status'],
                'created_at' => $ride['created_at'],
                'notes' => $ride['notes'],
                'passenger' => [
                    'id' => $ride['passenger_id'],
                    'name' => $ride['passenger_name'],
                    'phone' => $ride['passenger_phone'],
                    'rating' => number_format($passengerRating, 1)
                ]
            ]
        ];
        
    } else {
        // No active ride
        $response['success'] = true;
        $response['message'] = 'No active ride found';
        $response['data'] = [
            'has_ride' => false
        ];
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error fetching current ride: " . $e->getMessage());
    $response['message'] = 'An error occurred while fetching current ride';
}

echo json_encode($response);
exit;
?>