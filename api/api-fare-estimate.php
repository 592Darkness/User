<?php
/**
 * API Endpoint for Accurate Fare Estimation
 * Uses Google Maps Distance Matrix API for precise distance calculation
 */

// Include necessary files
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/calculate-distance.php';

// Set response header
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];

    // Check if request method is POST
    if ($method !== 'POST') {
        http_response_code(405); // Method Not Allowed
        $response['message'] = 'Method not allowed. Use POST.';
        echo json_encode($response);
        exit;
    }

    // Get request data from JSON or form data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }

    // Extract and sanitize parameters
    $pickup = isset($data['pickup']) ? sanitize($data['pickup']) : '';
    $dropoff = isset($data['dropoff']) ? sanitize($data['dropoff']) : '';
    $vehicleType = isset($data['vehicleType']) ? sanitize($data['vehicleType']) : 'standard';

    // Basic validation
    if (empty($pickup)) {
        throw new Exception('Pickup location is required.');
    }

    if (empty($dropoff)) {
        throw new Exception('Dropoff location is required.');
    }

    if (!in_array($vehicleType, ['standard', 'suv', 'premium'])) {
        throw new Exception('Invalid vehicle type. Choose from standard, suv, or premium.');
    }

    // Log the request for analytics
    error_log("Fare estimate request: From '{$pickup}' to '{$dropoff}' via {$vehicleType}");

    // Connect to database
    $conn = dbConnect();

    // Calculate the distance between pickup and dropoff
    $distanceResult = calculateDistance($pickup, $dropoff);

    if (!$distanceResult['success']) {
        throw new Exception($distanceResult['error']);
    }

    // Extract distance in kilometers
    $distanceKm = $distanceResult['distance']['km'];
    $durationSeconds = $distanceResult['duration']['value'];
    $durationText = $distanceResult['duration']['text'];

    // Calculate fare based on distance and vehicle type
    $fareResult = calculateFare($distanceKm, $vehicleType, $conn);

    // Store the fare estimation in the database for analytics if user is logged in
    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];
        
        // Ensure table exists
        $createTableSql = "CREATE TABLE IF NOT EXISTS fare_estimates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            pickup VARCHAR(255) NOT NULL,
            dropoff VARCHAR(255) NOT NULL,
            vehicle_type VARCHAR(50) NOT NULL,
            distance DECIMAL(10,2) NOT NULL,
            duration INT NOT NULL,
            fare INT NOT NULL,
            created_at DATETIME NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        )";
        $conn->query($createTableSql);
        
        // Record the fare estimate
        $stmt = $conn->prepare("INSERT INTO fare_estimates 
                               (user_id, pickup, dropoff, vehicle_type, distance, duration, fare, created_at, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $fareAmount = $fareResult['rounded_fare'];
        
        $stmt->bind_param("isssdisss", 
            $userId, 
            $pickup, 
            $dropoff, 
            $vehicleType, 
            $distanceKm, 
            $durationSeconds, 
            $fareAmount, 
            $ipAddress,
            $userAgent
        );
        $stmt->execute();
        $stmt->close();
    }

    // Prepare the response
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
                'base_fare' => $fareResult['base_fare'],
                'distance_fare' => $fareResult['distance_fare'],
                'traffic_multiplier' => $fareResult['traffic_multiplier'],
                'vehicle_multiplier' => $fareResult['vehicle_multiplier'],
                'subtotal' => $fareResult['subtotal'],
                'total' => $fareResult['rounded_fare'],
                'vehicle_type' => $vehicleType,
                'currency' => 'GYD',
                'pickup' => $distanceResult['origin']['resolved'],
                'dropoff' => $distanceResult['destination']['resolved']
            ]
        ]
    ];

    $conn->close();

} catch (Exception $e) {
    // Log the error
    error_log("Fare estimation error: " . $e->getMessage());
    
    // Set error response
    http_response_code(400);
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
exit;
?>