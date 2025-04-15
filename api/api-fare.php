<?php
/**
 * Standalone Fare Estimation API
 * Production-ready implementation with accurate distance calculation
 * FIXED: Handles errors from calculateDistance gracefully to prevent 500 errors.
 */

// Enable detailed error logging but don't display to users in production
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 for production
ini_set('log_errors', 1);
// Optional: Specify a log file path
ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');

// Important: Set proper headers
header('Content-Type: application/json');

// --- Dependency Check (Optional but recommended) ---
$required_files = [
    dirname(__DIR__) . '/includes/config.php',
    dirname(__DIR__) . '/includes/functions.php',
    dirname(__DIR__) . '/includes/db.php',
    dirname(__DIR__) . '/includes/calculate-distance.php',
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        error_log("API Fare Error: Missing required file - " . $file);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server configuration error. Please contact support.',
            'error_code' => 'MISSING_DEPENDENCY'
        ]);
        exit;
    }
    require_once $file;
}
// --- End Dependency Check ---


// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

$conn = null; // Initialize $conn to null

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use POST.');
    }

    // Get input data (JSON first, fallback to POST)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
     if (!$data) {
         throw new Exception('Could not parse input data. Ensure JSON is valid or form data is sent.');
     }


    // Validate required fields
    $pickup = isset($data['pickup']) ? trim($data['pickup']) : '';
    $dropoff = isset($data['dropoff']) ? trim($data['dropoff']) : '';
    $vehicleType = isset($data['vehicleType']) ? trim(strtolower($data['vehicleType'])) : 'standard'; // Normalize to lowercase

    if (empty($pickup)) {
        http_response_code(400); // Bad Request
        throw new Exception('Pickup location is required.');
    }

    if (empty($dropoff)) {
        http_response_code(400); // Bad Request
        throw new Exception('Dropoff location is required.');
    }

    // Validate vehicle type
    $validVehicleTypes = ['standard', 'suv', 'premium'];
    if (!in_array($vehicleType, $validVehicleTypes)) {
        http_response_code(400); // Bad Request
        throw new Exception('Invalid vehicle type. Choose from: ' . implode(', ', $validVehicleTypes));
    }

    // Connect to database
    $conn = dbConnect(); // This function should throw an exception on failure

    // --- Calculate Distance ---
    error_log("Calculating distance from '$pickup' to '$dropoff'");
    $distanceResult = calculateDistance($pickup, $dropoff); // Call the distance function

    // *** FIX: Handle distance calculation failure explicitly ***
    if (!isset($distanceResult['success']) || !$distanceResult['success']) {
        $errorMessage = $distanceResult['error'] ?? 'Failed to calculate distance. Please check addresses or try again later.';
        $errorCode = $distanceResult['error_code'] ?? 'DIST_ERR';
        error_log("API Fare Error (Distance Calculation): $errorMessage (Code: $errorCode)");

        // Determine appropriate HTTP status code based on error
        $statusCode = 503; // Service Unavailable (e.g., Google API issue)
        if (in_array($errorCode, ['ZERO_RESULTS', 'NOT_FOUND', 'INVALID_REQUEST', 'MISSING_PARAMS'])) {
            $statusCode = 400; // Bad Request (likely invalid addresses)
        }

        http_response_code($statusCode);
        $response['success'] = false;
        $response['message'] = $errorMessage;
        $response['error_code'] = $errorCode; // Optionally include error code

        // Close DB connection and send response
        if ($conn) $conn->close();
        echo json_encode($response);
        exit;
    }
    // --- END FIX ---

    // Extract distance and duration from successful result
    $distanceKm = $distanceResult['distance']['km'] ?? 0;
    $durationSeconds = $distanceResult['duration']['value'] ?? 0;
    $durationText = $distanceResult['duration']['text'] ?? 'N/A';

    // --- Calculate Fare ---
    $fareResult = calculateFare($distanceKm, $vehicleType, $conn);
    // Optional: Check $fareResult['success'] if calculateFare is modified to return it
    // if (!$fareResult['success']) { /* handle fare calculation error */ }

    // --- Log Fare Estimate (Optional, wrapped in try-catch) ---
    try {
        // Ensure api_fare_logs table exists (consider moving table creation to a migration script)
        $logTableQuery = "CREATE TABLE IF NOT EXISTS api_fare_logs (
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
        $conn->query($logTableQuery); // Execute table creation/check

        // Prepare and execute log insertion
        $logStmt = $conn->prepare("INSERT INTO api_fare_logs
                                 (pickup, dropoff, vehicle_type, distance, duration, fare, ip_address, request_time)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

        if ($logStmt) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            // Use the calculated rounded fare (in cents/smallest unit) for logging consistency
            $fareAmountInCents = $fareResult['rounded_fare'] ?? 0;

            $logStmt->bind_param("sssdiis",
                $pickup,
                $dropoff,
                $vehicleType,
                $distanceKm,
                $durationSeconds,
                $fareAmountInCents, // Log the fare amount (e.g., in cents)
                $ipAddress
            );
            $logStmt->execute();
            $logStmt->close();
        } else {
             error_log("API Fare Warning: Failed to prepare log statement: " . $conn->error);
        }

    } catch (Exception $logEx) {
        error_log("API Fare Warning: Error logging fare estimate: " . $logEx->getMessage());
        // Do not interrupt the response to the user if logging fails
    }

    // --- Prepare Success Response ---
    $response = [
        'success' => true,
        'message' => 'Fare estimated successfully.',
        'data' => [
            // Use formatted fare from calculateFare result
            'fare' => $fareResult['formatted_fare'] ?? 'N/A',
            'details' => [
                'distance' => $distanceResult['distance'], // Pass whole distance object
                'duration' => $distanceResult['duration'], // Pass whole duration object
                'pickup' => $distanceResult['origin']['resolved'] ?? $pickup, // Prefer resolved address
                'dropoff' => $distanceResult['destination']['resolved'] ?? $dropoff, // Prefer resolved address
                'base_fare' => $fareResult['base_fare'] ?? 0,
                'distance_fare' => $fareResult['distance_fare'] ?? 0,
                'vehicle_multiplier' => $fareResult['vehicle_multiplier'] ?? 1.0,
                'traffic_multiplier' => $fareResult['traffic_multiplier'] ?? 1.0,
                'subtotal' => $fareResult['subtotal'] ?? 0,
                // Use rounded_fare (in cents/smallest unit) for total
                'total' => $fareResult['rounded_fare'] ?? 0,
                'vehicle_type' => $vehicleType,
                'currency' => 'GYD' // Assuming Guyana Dollar
            ]
        ]
    ];

    $conn->close();
    $conn = null; // Ensure connection is marked as closed

} catch (Exception $e) {
    // --- General Error Handling ---
    error_log("API Fare General Exception: " . $e->getMessage());
    // Ensure status code hasn't been set already by specific errors
    if (http_response_code() === 200) {
        http_response_code(500); // Default to 500 for unexpected errors
    }
    $response['success'] = false;
    // Provide a generic error message for unexpected issues
    $response['message'] = $response['message'] ?: 'An internal server error occurred. Please try again later.';
     $response['error_code'] = $response['error_code'] ?? 'INTERNAL_SERVER_ERROR'; // Add error code if not set

    // Clean up DB connection if it was opened and the exception occurred after connection
    if (isset($conn) && $conn && $conn->ping()) {
        $conn->close();
    }
}

// Send the final JSON response
echo json_encode($response);
exit; // Ensure script terminates cleanly
?>