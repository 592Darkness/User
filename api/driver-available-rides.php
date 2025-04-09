<?php
/**
 * API Endpoint for Available Rides
 * Production-ready implementation that returns available rides from database
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
    http_response_code(401); // Unauthorized
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

$driverId = $_SESSION['driver_id'];
$driver = $_SESSION['driver'];

// Check if driver is available
if ($driver['status'] !== 'available') {
    http_response_code(403); // Forbidden
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
    $query = "
        SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.vehicle_type, r.created_at,
               r.distance, r.estimated_distance,
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
        
        // Use actual distance if available, otherwise estimated distance
        $distance = 0;
        if (isset($row['distance']) && $row['distance'] > 0) {
            $distance = $row['distance'];
        } elseif (isset($row['estimated_distance']) && $row['estimated_distance'] > 0) {
            $distance = $row['estimated_distance'];
        } else {
            // Calculate distance using distance calculation function if available
            if (function_exists('calculateDistance')) {
                $distanceResult = calculateDistance($row['pickup'], $row['dropoff']);
                if ($distanceResult['success']) {
                    $distance = $distanceResult['distance']['km'];
                    
                    // Update the ride with the calculated distance
                    $updateDistanceStmt = $conn->prepare("
                        UPDATE rides 
                        SET distance = ? 
                        WHERE id = ?
                    ");
                    $updateDistanceStmt->bind_param("di", $distance, $row['id']);
                    $updateDistanceStmt->execute();
                    $updateDistanceStmt->close();
                }
            }
        }
        
        $rides[] = [
            'id' => $row['id'],
            'pickup' => $row['pickup'],
            'dropoff' => $row['dropoff'],
            'fare' => $row['fare'],
            'formatted_fare' => 'G$' . number_format($row['fare']),
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
    
    // Check if there are any rides in the system with a different vehicle type
    if (empty($rides)) {
        $otherVehicleQuery = "
            SELECT COUNT(*) as count, vehicle_type
            FROM rides 
            WHERE status = 'searching' 
            AND driver_id IS NULL
            GROUP BY vehicle_type
        ";
        $otherVehicleStmt = $conn->prepare($otherVehicleQuery);
        $otherVehicleStmt->execute();
        $otherVehicleResult = $otherVehicleStmt->get_result();
        
        $otherVehicles = [];
        while ($row = $otherVehicleResult->fetch_assoc()) {
            $otherVehicles[] = $row['vehicle_type'];
        }
        $otherVehicleStmt->close();
        
        if (!empty($otherVehicles)) {
            $response['message'] = 'There are rides available for ' . implode(', ', $otherVehicles) . ' vehicles';
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
    http_response_code(500); // Internal Server Error
    $response['message'] = 'An error occurred while fetching available rides';
}

echo json_encode($response);
exit;
?>