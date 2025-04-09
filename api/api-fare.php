<?php
/**
 * Standalone Fare Estimation API
 * Production-ready implementation with accurate distance calculation
 */

// Important: Set proper headers
header('Content-Type: application/json');

// Include necessary files
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/calculate-distance.php';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use POST.');
    }

    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST; // Fallback to $_POST if JSON parsing fails
    }

    // Validate required fields
    $pickup = isset($data['pickup']) ? trim($data['pickup']) : '';
    $dropoff = isset($data['dropoff']) ? trim($data['dropoff']) : '';
    $vehicleType = isset($data['vehicleType']) ? trim($data['vehicleType']) : 'standard';

    if (empty($pickup)) {
        http_response_code(400); // Bad Request
        throw new Exception('Pickup location is required.');
    }

    if (empty($dropoff)) {
        http_response_code(400); // Bad Request
        throw new Exception('Dropoff location is required.');
    }

    // Validate vehicle type
    if (!in_array($vehicleType, ['standard', 'suv', 'premium'])) {
        http_response_code(400); // Bad Request
        throw new Exception('Invalid vehicle type. Choose from standard, suv, or premium.');
    }

    // Connect to database
    $conn = dbConnect();

    // Calculate distance using our production-ready function
    $distanceResult = calculateDistance($pickup, $dropoff);

    // If distance calculation failed, throw an exception
    if (!$distanceResult['success']) {
        throw new Exception($distanceResult['error']);
    }

    // Extract distance information
    $distanceKm = $distanceResult['distance']['km'];
    $durationSeconds = $distanceResult['duration']['value'];
    $durationText = $distanceResult['duration']['text'];

    // Calculate fare based on distance and vehicle type
    $fareResult = calculateFare($distanceKm, $vehicleType, $conn);

    // Log the fare estimation
    $logQuery = "CREATE TABLE IF NOT EXISTS api_fare_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pickup VARCHAR(255) NOT NULL,
        dropoff VARCHAR(255) NOT NULL,
        vehicle_type VARCHAR(50) NOT NULL,
        distance DECIMAL(10,2) NOT NULL,
        duration INT NOT NULL,
        fare INT NOT NULL,
        ip_address VARCHAR(45),
        request_time DATETIME NOT NULL,
        source VARCHAR(50) DEFAULT 'api',
        INDEX idx_request_time (request_time)
    )";
    $conn->query($logQuery);

    $logStmt = $conn->prepare("INSERT INTO api_fare_logs 
                             (pickup, dropoff, vehicle_type, distance, duration, fare, ip_address, request_time) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $fareAmount = $fareResult['rounded_fare'];
    
    $logStmt->bind_param("sssdiis", 
        $pickup, 
        $dropoff, 
        $vehicleType, 
        $distanceKm, 
        $durationSeconds, 
        $fareAmount, 
        $ipAddress
    );
    $logStmt->execute();
    $logStmt->close();

    // Success response
    $response = [
        'success' => true,
        'message' => 'Fare estimated successfully.',
        'data' => [
            'fare' => $fareResult['formatted_fare'],
            'details' => [
                'distance' => [
                    'value' => $distanceKm,
                    'text' => $distanceResult['distance']['text']
                ],
                'duration' => [
                    'value' => $durationSeconds,
                    'text' => $durationText
                ],
                'pickup' => $distanceResult['origin']['resolved'],
                'dropoff' => $distanceResult['destination']['resolved'],
                'base_fare' => $fareResult['base_fare'],
                'distance_fare' => $fareResult['distance_fare'],
                'vehicle_multiplier' => $fareResult['vehicle_multiplier'],
                'traffic_multiplier' => $fareResult['traffic_multiplier'],
                'subtotal' => $fareResult['subtotal'],
                'total' => $fareResult['rounded_fare'],
                'vehicle_type' => $vehicleType,
                'currency' => 'GYD'
            ]
        ]
    ];

    $conn->close();

} catch (Exception $e) {
    // Log error
    error_log("API Fare error: " . $e->getMessage());
    
    // Set error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send the response
echo json_encode($response);
exit;
?>