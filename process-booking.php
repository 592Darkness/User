<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Prevent any output before headers are sent
ob_start();

// Always set JSON header for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
    header('Content-Type: application/json');
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'redirect' => '',
    'booking_id' => null
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = 'index.php';
        $response['message'] = 'Please log in to book a ride.';
        $response['redirect'] = 'index.php';
        echo json_encode($response);
        exit;
    }
    
    // Get request data (either JSON or POST)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }
    
    // CSRF validation for regular form posts
    if (isset($data['csrf_token']) && !verifyCSRFToken($data['csrf_token'])) {
        throw new Exception('Security validation failed. Please try again.');
    }
    
    $pickup = isset($data['pickup']) ? sanitize($data['pickup']) : '';
    $dropoff = isset($data['dropoff']) ? sanitize($data['dropoff']) : '';
    $vehicleType = isset($data['vehicleType']) ? sanitize($data['vehicleType']) : 'standard';
    $promoCode = isset($data['promo']) ? sanitize($data['promo']) : '';
    
    // Validate inputs
    if (empty($pickup)) {
        throw new Exception('Pickup location is required.');
    }
    
    if (empty($dropoff)) {
        throw new Exception('Dropoff location is required.');
    }
    
    if (!in_array($vehicleType, ['standard', 'suv', 'premium'])) {
        throw new Exception('Invalid vehicle type.');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Connect to database
    $conn = dbConnect();
    
    // Get fare rates from database
    $stmt = $conn->prepare("SELECT vehicle_type, base_rate, rate_per_km, multiplier FROM fare_rates WHERE active = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize default rates
    $baseRates = [
        'standard' => 1000,
        'suv' => 1500,
        'premium' => 2000
    ];
    
    $multipliers = [
        'standard' => 1.0,
        'suv' => 1.5,
        'premium' => 2.0
    ];
    
    $ratePerKm = 200;
    
    // Update with rates from database if available
    if ($result->num_rows > 0) {
        while ($rate = $result->fetch_assoc()) {
            $type = $rate['vehicle_type'];
            if (isset($baseRates[$type])) {
                $baseRates[$type] = $rate['base_rate'];
                $multipliers[$type] = $rate['multiplier'];
                if ($type === 'standard') {
                    $ratePerKm = $rate['rate_per_km'];
                }
            }
        }
    }
    $stmt->close();
    
    // Calculate distance using Google Maps Distance Matrix API
    $apiKey = "AIzaSyA-6uXAa6MkIMwlYYwMIVBq5s3T0aTh0EI";
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . urlencode($pickup) . 
         "&destinations=" . urlencode($dropoff) . 
         "&mode=driving&key=" . $apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    
    $distance = 0;
    $duration = 0;
    
    if (!curl_errno($ch)) {
        $response_data = json_decode($result, true);
        
        if ($response_data['status'] === 'OK' && isset($response_data['rows'][0]['elements'][0]['status']) && 
            $response_data['rows'][0]['elements'][0]['status'] === 'OK') {
            
            // Get distance in meters and convert to kilometers
            $distance = $response_data['rows'][0]['elements'][0]['distance']['value'] / 1000;
            
            // Get duration in seconds
            $duration = $response_data['rows'][0]['elements'][0]['duration']['value'];
        } else {
            // If API fails, fall back to a distance estimation
            $distance = mt_rand(5, 20);
        }
    } else {
        // If curl fails, fall back to a distance estimation
        $distance = mt_rand(5, 20);
    }
    
    curl_close($ch);
    
    // Calculate fare
    $baseFare = $baseRates[$vehicleType];
    $distanceFare = $distance * $ratePerKm;
    $fare = ($baseFare + $distanceFare) * $multipliers[$vehicleType];
    
    // Apply promo code discount if valid
    $promoDiscount = 0;
    if (!empty($promoCode)) {
        $promoStmt = $conn->prepare("SELECT discount FROM promo_codes WHERE code = ? AND active = 1 AND expiry_date > NOW()");
        $promoStmt->bind_param("s", $promoCode);
        $promoStmt->execute();
        $promoResult = $promoStmt->get_result();
        
        if ($promoResult->num_rows > 0) {
            $promoData = $promoResult->fetch_assoc();
            $promoDiscount = $promoData['discount'];
            $fare = $fare * (1 - ($promoDiscount / 100));
        }
        $promoStmt->close();
    }
    
    // Round to nearest 100
    $fare = ceil($fare / 100) * 100;
    
    // Start a transaction for data integrity
    $conn->begin_transaction();
    
    try {
        // Insert the ride into the database
        $stmt = $conn->prepare("
            INSERT INTO rides (
                user_id, pickup, dropoff, vehicle_type, fare, 
                status, created_at, promo_code
            ) VALUES (
                ?, ?, ?, ?, ?, 
                'searching', NOW(), ?
            )
        ");
        
        $stmt->bind_param("isssds", $userId, $pickup, $dropoff, $vehicleType, $fare, $promoCode);
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }
        
        $bookingId = $conn->insert_id;
        $stmt->close();
        
        // Log the ride request
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs (
                ride_id, user_id, action, details, created_at
            ) VALUES (
                ?, ?, 'requested', ?, NOW()
            )
        ");
        
        $details = json_encode([
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'vehicle_type' => $vehicleType,
            'fare' => $fare,
            'distance' => $distance,
            'duration' => $duration,
            'promo_code' => $promoCode,
            'promo_discount' => $promoDiscount
        ]);
        
        $logStmt->bind_param("iis", $bookingId, $userId, $details);
        $logStmt->execute();
        $logStmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        // Store booking in session
        $_SESSION['current_booking'] = [
            'id' => $bookingId,
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'vehicle_type' => $vehicleType,
            'promo_code' => $promoCode,
            'fare' => $fare,
            'status' => 'searching',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        // Set success response
        $response['success'] = true;
        $response['message'] = 'Ride booking successful! Finding a driver for you...';
        $response['booking_id'] = $bookingId;
        
        // Log success
        error_log("Ride booking successful for user {$userId}. Booking ID: {$bookingId}");
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        throw $e;
    } finally {
        $conn->close();
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Booking error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Clear output buffer and send JSON response
ob_end_clean();
echo json_encode($response);
exit;
?>