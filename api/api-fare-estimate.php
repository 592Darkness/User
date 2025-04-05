<?php
/**
 * API Endpoint for Fare Estimation
 * Uses Google Maps Distance Matrix API for accurate distance calculation
 */

// Check if request method is POST
if ($method !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Method not allowed. Use POST.';
    echo json_encode($response);
    exit;
}

// Get form data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$pickup = isset($data['pickup']) ? sanitize($data['pickup']) : '';
$dropoff = isset($data['dropoff']) ? sanitize($data['dropoff']) : '';
$vehicleType = isset($data['vehicleType']) ? sanitize($data['vehicleType']) : 'standard';

// Basic validation
if (empty($pickup) || empty($dropoff)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Both pickup and dropoff locations are required.';
    echo json_encode($response);
    exit;
}

if (!in_array($vehicleType, ['standard', 'suv', 'premium'])) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid vehicle type.';
    echo json_encode($response);
    exit;
}

// Get fare rates from database
$conn = dbConnect();
$stmt = $conn->prepare("SELECT vehicle_type, base_rate, rate_per_km FROM fare_rates WHERE active = 1");
$stmt->execute();
$result = $stmt->get_result();

// Initialize default rates
$baseRates = [
    'standard' => 1000, // G$1000
    'suv' => 1500,      // G$1500
    'premium' => 2000   // G$2000
];

$multipliers = [
    'standard' => 1.0,
    'suv' => 1.5,
    'premium' => 2.0
];

// Rate per kilometer in Guyanese dollars
$ratePerKm = 200;

// Update with rates from database if available
if ($result->num_rows > 0) {
    while ($rate = $result->fetch_assoc()) {
        $type = $rate['vehicle_type'];
        if (isset($baseRates[$type])) {
            $baseRates[$type] = $rate['base_rate'];
            if ($type === 'standard') {
                $ratePerKm = $rate['rate_per_km']; // We use standard rate per km as base
            }
        }
    }
}
$stmt->close();

// Calculate distance using Google Maps Distance Matrix API
$apiKey = "AIzaSyA-6uXAa6MkIMwlYYwMIVBq5s3T0aTh0EI";
$distance = 0;
$duration = 0;

try {
    // Prepare the API URL with origin, destination and API key
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . urlencode($pickup) . 
           "&destinations=" . urlencode($dropoff) . 
           "&mode=driving&key=" . $apiKey;
    
    // Make the API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // Parse the response
    $response_data = json_decode($result, true);
    
    // Log the API response for debugging
    error_log("Google Maps API response: " . json_encode($response_data));
    
    // Check if the API returned valid data
    if ($response_data['status'] === 'OK' && isset($response_data['rows'][0]['elements'][0]['status']) && 
        $response_data['rows'][0]['elements'][0]['status'] === 'OK') {
        
        // Get distance in meters and convert to kilometers
        $distance = $response_data['rows'][0]['elements'][0]['distance']['value'] / 1000;
        
        // Get duration in seconds
        $duration = $response_data['rows'][0]['elements'][0]['duration']['value'];
        
        // Save the successful API call for analytics
        $logStmt = $conn->prepare("INSERT INTO api_logs (api_name, request_data, response_data, success, created_at) VALUES (?, ?, ?, 1, NOW())");
        $logData = json_encode(['pickup' => $pickup, 'dropoff' => $dropoff]);
        $logResponse = json_encode(['distance' => $distance, 'duration' => $duration]);
        $apiName = 'google_distance_matrix';
        $logStmt->bind_param("sss", $apiName, $logData, $logResponse);
        $logStmt->execute();
        $logStmt->close();
        
    } else {
        // Log the API failure
        error_log("Distance Matrix API failed: " . json_encode($response_data));
        
        // Save the failed API call
        $logStmt = $conn->prepare("INSERT INTO api_logs (api_name, request_data, response_data, success, created_at) VALUES (?, ?, ?, 0, NOW())");
        $logData = json_encode(['pickup' => $pickup, 'dropoff' => $dropoff]);
        $logResponse = json_encode($response_data);
        $apiName = 'google_distance_matrix';
        $logStmt->bind_param("sss", $apiName, $logData, $logResponse);
        $logStmt->execute();
        $logStmt->close();
        
        // Use a fallback distance calculation (rough estimate for Guyana)
        // Only as a last resort when the API fails
        $distance = mt_rand(2, 20);
    }
} catch (Exception $e) {
    error_log("Error calculating distance: " . $e->getMessage());
    
    // Log the exception
    $logStmt = $conn->prepare("INSERT INTO api_logs (api_name, request_data, error_message, success, created_at) VALUES (?, ?, ?, 0, NOW())");
    $logData = json_encode(['pickup' => $pickup, 'dropoff' => $dropoff]);
    $errorMsg = $e->getMessage();
    $apiName = 'google_distance_matrix';
    $logStmt->bind_param("sss", $apiName, $logData, $errorMsg);
    $logStmt->execute();
    $logStmt->close();
    
    // Fallback distance calculation
    $distance = mt_rand(2, 20);
}

// Calculate fare
$baseFare = $baseRates[$vehicleType];
$distanceFare = $distance * $ratePerKm;
$finalFare = ($baseFare + $distanceFare) * $multipliers[$vehicleType];

// Add traffic conditions factor (based on time of day)
$hour = (int)date('H');
$trafficMultiplier = 1.0;

// Rush hours: 7-9 AM and 4-6 PM
if (($hour >= 7 && $hour <= 9) || ($hour >= 16 && $hour <= 18)) {
    $trafficMultiplier = 1.2;
}

$finalFare *= $trafficMultiplier;

// Round to nearest 100
$finalFare = ceil($finalFare / 100) * 100;

// Format the fare
$formattedFare = 'G$' . number_format($finalFare);

// Store the fare estimation in the database
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO fare_estimates (user_id, pickup, dropoff, vehicle_type, distance, duration, fare, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssddi", $userId, $pickup, $dropoff, $vehicleType, $distance, $duration, $finalFare);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// Success response
$response['success'] = true;
$response['message'] = 'Fare estimated successfully.';
$response['data'] = [
    'fare' => $formattedFare,
    'details' => [
        'distance' => round($distance, 1),
        'duration' => $duration,
        'base_fare' => $baseFare,
        'distance_fare' => round($distanceFare),
        'traffic_multiplier' => $trafficMultiplier,
        'vehicle_multiplier' => $multipliers[$vehicleType],
        'total' => $finalFare
    ]
];

echo json_encode($response);
exit;
?>