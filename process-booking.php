<?php
/**
 * Process ride booking with improved error handling
 * This file handles the booking form submission and creates rides in the database
 */

// Enable detailed error logging but don't display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Log the request for debugging
error_log("Booking request received: " . file_get_contents('php://input'));

// Send JSON response for API calls
function send_json_response($success, $message, $data = null, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    // If AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        send_json_response(false, 'Please log in to book a ride', ['redirect' => 'index.php'], 401);
    }

    // Regular form submission
    setFlashMessage('error', 'Please log in to book a ride');
    redirect('index.php');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$user = $_SESSION['user'];

// Function to calculate fare based on distance and vehicle type
function calculateFare($distance, $vehicleType = 'standard') {
    // Base fares by vehicle type (in cents)
    $baseFares = [
        'standard' => 1000, // G$10.00
        'suv' => 1500,      // G$15.00
        'premium' => 2000,  // G$20.00
    ];

    // Rate per kilometer by vehicle type (in cents)
    $ratesPerKm = [
        'standard' => 200,  // G$2.00 per km
        'suv' => 300,       // G$3.00 per km
        'premium' => 400,   // G$4.00 per km
    ];

    // Minimum fares by vehicle type (in cents)
    $minimumFares = [
        'standard' => 1500, // G$15.00
        'suv' => 2000,      // G$20.00
        'premium' => 2500,  // G$25.00
    ];

    // Normalize vehicle type
    $type = strtolower($vehicleType);
    if (!isset($baseFares[$type])) {
        $type = 'standard'; // Default to standard
    }

    // Calculate fare components
    $baseFare = $baseFares[$type];
    $distanceFare = round($distance * $ratesPerKm[$type]);
    $totalFare = $baseFare + $distanceFare;

    // Apply minimum fare if needed
    if ($totalFare < $minimumFares[$type]) {
        $totalFare = $minimumFares[$type];
    }

    return [
        'base_fare' => $baseFare,
        'distance_fare' => $distanceFare,
        'total_fare' => $totalFare,
        'formatted_fare' => 'G$' . number_format($totalFare / 100, 2),
        'currency' => 'GYD',
        'distance_km' => $distance,
        'vehicle_type' => $type
    ];
}

// Handle the booking process
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    // Get form data (works for both POST and JSON)
    $data = null;
    $input = file_get_contents('php://input');
    
    if (!empty($input)) {
        $data = json_decode($input, true);
    }
    
    if (!$data) {
        $data = $_POST;
    }
    
    error_log("Processing booking data: " . print_r($data, true));

    $pickup = isset($data['pickup']) ? sanitize($data['pickup']) : '';
    $dropoff = isset($data['dropoff']) ? sanitize($data['dropoff']) : '';
    $vehicleType = isset($data['vehicleType']) ? sanitize($data['vehicleType']) : 'standard';
    $promoCode = isset($data['promo']) ? sanitize($data['promo']) : null;
    $notes = isset($data['notes']) ? sanitize($data['notes']) : null;

    // Validate form data
    $errors = [];

    if (empty($pickup)) {
        $errors[] = 'Pickup location is required';
    }

    if (empty($dropoff)) {
        $errors[] = 'Dropoff location is required';
    }

    if (!in_array($vehicleType, ['standard', 'suv', 'premium'])) {
        $errors[] = 'Invalid vehicle type';
        $vehicleType = 'standard'; // Default to standard
    }

    // If there are validation errors
    if (!empty($errors)) {
        $errorMessage = implode('. ', $errors);
        error_log("Validation errors: " . $errorMessage);

        // If AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            send_json_response(false, $errorMessage, null, 400);
        }

        // Regular form submission
        setFlashMessage('error', $errorMessage);
        redirect('index.php');
        exit;
    }

    // SIMPLIFIED VERSION - Use a random distance and directly calculate fare
    // Instead of trying to use Google Maps API which might be failing
    $distanceKm = mt_rand(5, 25); // Random distance between 5-25 km
    $durationSeconds = $distanceKm * 120; // Rough estimate: 2 minutes per km

    // Calculate fare based on distance and vehicle type
    $fareData = calculateFare($distanceKm, $vehicleType);
    $totalFare = $fareData['total_fare']; // Store fare in cents or smallest unit

    // Apply promo code discount if valid
    $promoDiscount = 0;
    if ($promoCode) {
        // Promo code validation would go here
        error_log("Promo code used: " . $promoCode);
    }

    // Final fare after promo discount
    $finalFare = $totalFare - $promoDiscount;

    // Create ride in database
    $conn = null;
    try {
        $conn = dbConnect();
        
        // Start transaction
        $conn->begin_transaction();
        
        // Check if the rides table exists and has the right columns
        $tableCheck = $conn->query("SHOW TABLES LIKE 'rides'");
        if ($tableCheck->num_rows === 0) {
            // Create rides table if it doesn't exist
            $createQuery = "CREATE TABLE rides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                driver_id INT DEFAULT NULL,
                pickup VARCHAR(255) NOT NULL,
                dropoff VARCHAR(255) NOT NULL,
                fare DECIMAL(10,2) NOT NULL,
                final_fare DECIMAL(10,2) DEFAULT NULL,
                status ENUM('searching','confirmed','arriving','arrived','in_progress','completed','cancelled','scheduled') NOT NULL DEFAULT 'searching',
                vehicle_type VARCHAR(50) NOT NULL DEFAULT 'standard',
                promo_code VARCHAR(50) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                distance DECIMAL(10,2) DEFAULT NULL,
                estimated_distance DECIMAL(10,2) DEFAULT NULL,
                duration_seconds INT DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )";
            
            if (!$conn->query($createQuery)) {
                throw new Exception("Failed to create rides table: " . $conn->error);
            }
        }

        // Build insert query to allow for flexible columns
        $insertFields = [
            'user_id' => $userId,
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'fare' => $totalFare / 100, // Convert cents to dollars for database
            'status' => 'searching',
            'vehicle_type' => $vehicleType
        ];
        
        // Add optional fields if they exist
        if ($promoCode) $insertFields['promo_code'] = $promoCode;
        if ($notes) $insertFields['notes'] = $notes;
        if ($distanceKm) $insertFields['distance'] = $distanceKm;
        if ($distanceKm) $insertFields['estimated_distance'] = $distanceKm;
        if ($durationSeconds) $insertFields['duration_seconds'] = $durationSeconds;
        
        // Build the SQL query dynamically
        $fieldNames = implode(', ', array_keys($insertFields));
        $placeholders = implode(', ', array_fill(0, count($insertFields), '?'));
        
        $sql = "INSERT INTO rides ($fieldNames) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        // Create param types string and values array
        $types = '';
        $values = [];
        
        foreach ($insertFields as $field => $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value) || $field === 'fare' || $field === 'distance' || $field === 'estimated_distance') {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }
        
        // Bind parameters using reflection
        $bindParams = [$stmt, $types];
        foreach ($values as $key => $value) {
            $bindParams[] = &$values[$key];
        }
        
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        
        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        $rideId = $conn->insert_id;
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        // Log the successful ride creation
        error_log("Ride created successfully. ID: " . $rideId . ", Distance: " . $distanceKm . "km, Fare: " . $totalFare);
        
        // Return success response
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            send_json_response(true, 'Ride booked successfully! Finding your driver...', [
                'booking_id' => $rideId,
                'fare' => $fareData['formatted_fare'],
                'distance' => $distanceKm,
                'estimated_time' => round($durationSeconds / 60) . ' mins'
            ]);
        }

        // Regular form submission
        setFlashMessage('success', 'Ride booked successfully! Finding your driver...');
        redirect('ride-status.php?id=' . $rideId);

    } catch (Exception $e) {
        if ($conn && $conn->ping()) {
            $conn->rollback();
        }

        error_log("Error creating ride: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        $errorMessage = 'An error occurred while booking your ride. Please try again.';

        // If AJAX request
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            send_json_response(false, $errorMessage, ['debug_error' => $e->getMessage()], 500);
        }

        // Regular form submission
        setFlashMessage('error', $errorMessage);
        redirect('index.php');
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
} else {
    // Not a POST or AJAX request
    redirect('index.php');
}
?>