<?php
/**
 * API Endpoint for Saved Places Management
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);  // Change to 0 in production

// Explicitly determine the request method
$method = $_SERVER['REQUEST_METHOD'];

// Log request for debugging
error_log("Saved Places API request: Method = " . $method);

try {
    // Explicitly require config and functions
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/db.php';

    // Strict authentication check
    $currentUser = getCurrentUser();

    if (!$currentUser) {
        http_response_code(401); // Unauthorized
        throw new Exception('Authentication required. Please log in.');
    }

    $userId = $currentUser['id'];

    // Handle different request methods
    switch ($method) {
        case 'GET':
            // Get all saved places for the user
            try {
                $conn = dbConnect();

                // Ensure the saved_places table exists
                $conn->query("CREATE TABLE IF NOT EXISTS saved_places (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    address VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )");

                $stmt = $conn->prepare("
                    SELECT id, name, address, created_at 
                    FROM saved_places 
                    WHERE user_id = ? 
                    ORDER BY name ASC
                ");
                
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $places = [];
                while ($place = $result->fetch_assoc()) {
                    $places[] = $place;
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Saved places retrieved successfully.',
                    'data' => [
                        'places' => $places
                    ]
                ];
                
                $stmt->close();
                $conn->close();

                header('Content-Type: application/json');
                echo json_encode($response);
                exit;

            } catch (Exception $e) {
                throw new Exception("Error retrieving saved places: " . $e->getMessage());
            }
            break;

        default:
            http_response_code(405); // Method Not Allowed
            throw new Exception('Method not allowed.');
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Saved Places API fatal error: " . $e->getMessage());
    
    // Return a proper error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
    exit;
}
?>