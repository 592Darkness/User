<?php
/**
 * Authentication API Endpoints
 * For checking login status and managing user authentication
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);  // Change to 0 in production

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

    // Check which endpoint is being requested
    $endpoint = isset($_GET['endpoint']) ? sanitize($_GET['endpoint']) : '';
    $method = $_SERVER['REQUEST_METHOD'];

    // Endpoint for checking authentication status
    if ($endpoint === 'check-auth') {
        if ($method !== 'GET') {
            http_response_code(405); // Method Not Allowed
            throw new Exception('Method not allowed. Use GET.');
        }
        
        // Check if user is logged in
        if (isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            $currentUser = getCurrentUser();
            
            // Debug session data
            error_log("User is logged in. User ID: " . $userId);
            error_log("Current user data: " . json_encode($currentUser));
            
            // Connect to database
            $conn = dbConnect();
            
            // Get reward points
            $pointsStmt = $conn->prepare("
                CREATE TABLE IF NOT EXISTS reward_points (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL UNIQUE,
                    points INT DEFAULT 0,
                    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                INSERT INTO reward_points (user_id, points) 
                VALUES (?, 0) 
                ON DUPLICATE KEY UPDATE points = points;

                SELECT points FROM reward_points WHERE user_id = ?
            ");
            $pointsStmt->bind_param("ii", $userId, $userId);
            $pointsStmt->execute();
            
            // Multiple results handling
            $pointsResult = 0;
            do {
                $result = $pointsStmt->get_result();
                if ($result) {
                    $row = $result->fetch_assoc();
                    if ($row && isset($row['points'])) {
                        $pointsResult = $row['points'];
                    }
                }
            } while ($pointsStmt->nextResult());
            $pointsStmt->close();
            
            // Make sure all the expected properties exist
            if (!isset($currentUser['id'])) $currentUser['id'] = $userId;
            if (!isset($currentUser['name'])) $currentUser['name'] = "";
            if (!isset($currentUser['email'])) $currentUser['email'] = "";
            if (!isset($currentUser['phone'])) $currentUser['phone'] = "";
            
            $currentUser['reward_points'] = $pointsResult;
            
            $conn->close();
            
            $response = [
                'success' => true,
                'authenticated' => true,
                'message' => 'User is authenticated',
                'user' => $currentUser
            ];
        } else {
            error_log("User is not authenticated");
            $response = [
                'success' => true,
                'authenticated' => false,
                'message' => 'User is not authenticated'
            ];
        }
        
        echo json_encode($response);
        exit;
    }

    // If we get here, the specific endpoint wasn't handled
    throw new Exception("Unhandled endpoint: $endpoint");
    
} catch (Exception $e) {
    // Log the error
    error_log("API Auth fatal error: " . $e->getMessage());
    
    // Return a proper error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
    exit;
}
?>