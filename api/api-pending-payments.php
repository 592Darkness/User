<?php
/**
 * API Endpoint for Fetching Pending Payments
 * Returns completed rides that require payment confirmation
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Set to 0 in production
ini_set('log_errors', 1);

// Always set JSON header for API endpoints
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'payments' => []
];

try {
    // Explicitly require config and functions
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/db.php';

    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401); // Unauthorized
        throw new Exception('You must be logged in to view pending payments.');
    }

    $userId = $_SESSION['user_id'];

    // Connect to database
    $conn = dbConnect();

    // Fetch completed rides that haven't been paid yet
    $stmt = $conn->prepare("
        SELECT 
            r.id, 
            r.pickup, 
            r.dropoff, 
            r.fare, 
            r.final_fare, 
            r.status, 
            r.vehicle_type,
            r.created_at,
            r.completed_at
        FROM 
            rides r
        WHERE 
            r.user_id = ? 
            AND r.status = 'completed' 
            AND (r.payment_status IS NULL OR r.payment_status != 'paid')
        ORDER BY 
            r.completed_at DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    
    while ($ride = $result->fetch_assoc()) {
        // Get the correct fare amount (final_fare if available, otherwise use fare)
        $fareAmount = $ride['final_fare'] !== null ? $ride['final_fare'] : $ride['fare'];
        
        // Format dates
        try {
            $createdDate = new DateTime($ride['created_at']);
            $formattedDate = $createdDate->format('F j, Y');
            $formattedTime = $createdDate->format('g:i A');
        } catch (Exception $e) {
            $formattedDate = 'Unknown Date';
            $formattedTime = 'Unknown Time';
        }
        
        // Add to payments array with properly formatted data
        $payments[] = [
            'id' => $ride['id'],
            'pickup' => $ride['pickup'],
            'dropoff' => $ride['dropoff'],
            'formatted_date' => $formattedDate,
            'formatted_time' => $formattedTime,
            'formatted_fare' => 'G$' . number_format($fareAmount, 2),
            'final_fare_numeric' => $fareAmount, // Make sure this is numeric value, not formatted
            'vehicle_type' => $ride['vehicle_type'],
            'status' => $ride['status']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    // Set success response
    $response['success'] = true;
    $response['message'] = 'Pending payments retrieved successfully.';
    $response['payments'] = $payments;
    
} catch (Exception $e) {
    // Log the error
    error_log("API pending payments error: " . $e->getMessage());
    
    // Set the error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Set appropriate HTTP status code if not already set
    if (http_response_code() === 200) {
        http_response_code(500);
    }
}

// Send JSON response
echo json_encode($response);
exit;
?>