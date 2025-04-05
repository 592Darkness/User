<?php
/**
 * API Endpoint for Available Rides
 * Returns a list of rides available for drivers to accept from the database
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
    $response['message'] = 'You must be online to view available rides';
    $response['data'] = ['status' => $driver['status']];
    echo json_encode($response);
    exit;
}

// Fetch available rides
try {
    $conn = dbConnect();
    
    // Get the driver's vehicle type from session
    $vehicleType = $driver['vehicle_type'] ?? 'standard';
    
    // Fetch new rides that don't have a driver assigned yet and match the driver's vehicle type
    // In a real system, you'd also filter by proximity to the driver's current location
    $query = "
        SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.vehicle_type, r.created_at,
               u.name as passenger_name, u.id as passenger_id
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'searching'
        AND r.driver_id IS NULL
        AND r.vehicle_type = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $vehicleType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rides = [];
    
    while ($row = $result->fetch_assoc()) {
        // Get passenger rating
        $ratingQuery = "
            SELECT AVG(rating) as avg_rating
            FROM driver_ratings
            WHERE user_id = ?
        ";
        $ratingStmt = $conn->prepare($ratingQuery);
        $ratingStmt->bind_param("i", $row['passenger_id']);
        $ratingStmt->execute();
        $ratingResult = $ratingStmt->get_result();
        $ratingData = $ratingResult->fetch_assoc();
        
        $passengerRating = $ratingData['avg_rating'] ?: 5.0; // Default to 5 if no rating yet
        $ratingStmt->close();
        
        // Calculate approximate distance between pickup and dropoff
        // For a real system, you'd use actual coordinates and the Haversine formula
        // For now, we'll simulate with a formula based on string length difference
        $pickupWords = str_word_count($row['pickup']);
        $dropoffWords = str_word_count($row['dropoff']);
        $baseDistance = abs($pickupWords - $dropoffWords) * 2.5;
        $randomVariation = mt_rand(-10, 20) / 10; // -1.0 to 2.0 km variation
        $distance = max(1.0, $baseDistance + $randomVariation);
        
        $rides[] = [
            'id' => $row['id'],
            'pickup' => $row['pickup'],
            'dropoff' => $row['dropoff'],
            'fare' => $row['fare'],
            'distance' => $distance,
            'vehicle_type' => $row['vehicle_type'],
            'created_at' => $row['created_at'],
            'passenger' => [
                'id' => $row['passenger_id'],
                'name' => $row['passenger_name'],
                'rating' => number_format($passengerRating, 1)
            ]
        ];
    }
    
    $stmt->close();
    
    // If no rides found, check if there are any rides in the system at all
    if (empty($rides)) {
        $checkQuery = "SELECT COUNT(*) as count FROM rides WHERE status = 'searching' AND driver_id IS NULL";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $totalAvailable = $checkResult->fetch_assoc()['count'];
        $checkStmt->close();
        
        if ($totalAvailable > 0) {
            $response['message'] = 'There are rides available, but none match your vehicle type';
        } else {
            $response['message'] = 'No available rides at this time';
        }
    } else {
        $response['message'] = count($rides) . ' available ' . (count($rides) === 1 ? 'ride' : 'rides') . ' found';
    }
    
    $conn->close();
    
    $response['success'] = true;
    $response['data'] = ['rides' => $rides];
    
} catch (Exception $e) {
    error_log("Error fetching available rides: " . $e->getMessage());
    $response['message'] = 'An error occurred while fetching available rides';
}

echo json_encode($response);
exit;
?>