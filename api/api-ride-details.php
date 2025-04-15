<?php
/**
 * API Endpoint for Fetching Ride Details
 * Fixed with robust error handling and better diagnostics
 */

// Enable detailed error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1); // Make sure errors are logged

// Include necessary files
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Set response header
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Log the request for debugging
    error_log("Ride details API request: " . json_encode($_GET));
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Authentication required. Please log in.';
        echo json_encode($response);
        exit;
    }

    // Get ride ID from the URL
    $rideId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($rideId <= 0) {
        http_response_code(400); // Bad Request
        $response['message'] = 'Invalid ride ID.';
        echo json_encode($response);
        exit;
    }

    // Get user ID
    $userId = $_SESSION['user_id'];
    error_log("Fetching ride ID $rideId for user ID $userId");
    
    // Connect to database
    $conn = dbConnect();
    
    // Simple query to get ride details - no complex joins
    $stmt = $conn->prepare("SELECT * FROM rides WHERE id = ? AND user_id = ?");
    
    if (!$stmt) {
        throw new Exception("Database error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $rideId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution error: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404); // Not Found
        $response['message'] = 'Ride not found or you do not have permission to view it.';
        echo json_encode($response);
        exit;
    }
    
    $ride = $result->fetch_assoc();
    $stmt->close();
    
    // Try to get driver details if driver_id exists
    if (!empty($ride['driver_id'])) {
        try {
            $driverStmt = $conn->prepare("SELECT id, name, phone, rating FROM drivers WHERE id = ?");
            if ($driverStmt) {
                $driverStmt->bind_param("i", $ride['driver_id']);
                $driverStmt->execute();
                $driverResult = $driverStmt->get_result();
                
                if ($driverResult->num_rows > 0) {
                    $driver = $driverResult->fetch_assoc();
                    $ride['driver_name'] = $driver['name'];
                    $ride['driver_phone'] = $driver['phone'];
                    $ride['driver_rating'] = $driver['rating'];
                }
                $driverStmt->close();
            }
        } catch (Exception $driverEx) {
            // Just log driver fetch errors but continue
            error_log("Error fetching driver details: " . $driverEx->getMessage());
        }
    }
    
    // Format date and time if available
    if (isset($ride['created_at'])) {
        try {
            $date = new DateTime($ride['created_at']);
            $ride['formatted_date'] = $date->format('M j, Y');
            $ride['formatted_time'] = $date->format('g:i A');
        } catch (Exception $e) {
            error_log("Error formatting date: " . $e->getMessage());
            $ride['formatted_date'] = 'N/A';
            $ride['formatted_time'] = 'N/A';
        }
    }
    
    // Format fare
    if (isset($ride['fare'])) {
        $ride['formatted_fare'] = 'G$' . number_format($ride['fare'], 2);
    }
    
    // Format ride status (ensure it's lowercase for consistency)
    if (isset($ride['status'])) {
        $ride['status'] = strtolower($ride['status']);
    }
    
    // Set response
    $response['success'] = true;
    $response['message'] = 'Ride details retrieved successfully.';
    $response['data']['ride'] = $ride;
    
    $conn->close();
    
} catch (Exception $e) {
    // Log detailed error
    error_log("API Ride Details error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Set error response
    http_response_code(500); // Internal Server Error
    $response['message'] = 'An error occurred while fetching ride details.';
    $response['error'] = true;
    $response['debug'] = $e->getMessage(); // Include the error message for debugging
}

// Send JSON response with error handling for JSON encoding issues
try {
    echo json_encode($response);
} catch (Exception $jsonError) {
    error_log("JSON encoding error: " . $jsonError->getMessage());
    // Send a simplified response that's guaranteed to encode properly
    echo json_encode([
        'success' => false,
        'message' => 'Error generating response',
        'error' => true
    ]);
}
exit;