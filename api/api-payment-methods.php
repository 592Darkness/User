<?php
/**
 * API Endpoint for Payment Methods
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Change to 0 in production

// Always set JSON header for API endpoints
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Explicitly require config and functions
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/db.php';

    // Check if this is an API request with an endpoint
    $endpoint = isset($_GET['endpoint']) ? sanitize($_GET['endpoint']) : '';
    $method = $_SERVER['REQUEST_METHOD'];

    // Detailed logging
    error_log("Payment Methods API Request:");
    error_log("Endpoint: " . $endpoint);
    error_log("Method: " . $method);

    // Check if user is logged in
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        error_log("Authentication failed: No current user");
        http_response_code(401); // Unauthorized
        throw new Exception('Authentication required. Please log in.');
    }

    // Connect to database
    $conn = dbConnect();

    // Create payment methods table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('card', 'paypal', 'bank') NOT NULL,
        name VARCHAR(100) NOT NULL,
        last4 VARCHAR(4),
        email VARCHAR(255),
        is_default BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Handle different endpoints and methods
    switch ($endpoint) {
        case 'payment-methods':
            if ($method !== 'GET') {
                http_response_code(405); // Method Not Allowed
                throw new Exception('Method not allowed. Use GET.');
            }

            $userId = $currentUser['id'];
            error_log("Processing payment methods for User ID: " . $userId);

            // Fetch user's payment methods
            $stmt = $conn->prepare("
                SELECT id, type, name, last4, email, is_default 
                FROM payment_methods 
                WHERE user_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $paymentMethods = [];
            while ($method = $result->fetch_assoc()) {
                $paymentMethods[] = $method;
            }
            $stmt->close();

            error_log("Payment methods count: " . count($paymentMethods));

            $conn->close();

            $response = [
                'success' => true,
                'message' => 'Payment methods retrieved successfully.',
                'data' => [
                    'payment_methods' => $paymentMethods
                ]
            ];
            break;

        default:
            throw new Exception("Unhandled endpoint: $endpoint");
    }

} catch (Exception $e) {
    // Log the error with full details
    error_log("Payment Methods API FATAL ERROR: " . $e->getMessage());
    error_log("Error Trace: " . $e->getTraceAsString());
    
    // Set the error response
    $response['success'] = false;
    $response['message'] = 'Internal Server Error: ' . $e->getMessage();
    
    // Set appropriate HTTP status code
    http_response_code(500);
}

// Send JSON response
echo json_encode($response);
exit;
?>