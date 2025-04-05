<?php
/**
 * API Endpoint for Reward Points
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

    // Check if this is an API request with an endpoint
    $endpoint = isset($_GET['endpoint']) ? sanitize($_GET['endpoint']) : '';
    $method = $_SERVER['REQUEST_METHOD'];

    // Detailed logging
    error_log("Reward Points API Request:");
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

    // Comprehensive table creation function
    function createTablesIfNotExist($conn) {
        try {
            // Reward Points Table
            $conn->query("CREATE TABLE IF NOT EXISTS reward_points (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                points INT DEFAULT 0,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Available Rewards Table
            $conn->query("CREATE TABLE IF NOT EXISTS available_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                points_required INT NOT NULL,
                active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Redeemed Rewards Table
            $conn->query("CREATE TABLE IF NOT EXISTS redeemed_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reward_id INT NOT NULL,
                points_used INT NOT NULL,
                redeemed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (reward_id) REFERENCES available_rewards(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Check if any rewards exist, if not, insert default rewards
            $checkRewardsStmt = $conn->prepare("SELECT COUNT(*) as count FROM available_rewards");
            $checkRewardsStmt->execute();
            $result = $checkRewardsStmt->get_result();
            $rewardsCount = $result->fetch_assoc()['count'];
            $checkRewardsStmt->close();

            if ($rewardsCount == 0) {
                $insertRewardsStmt = $conn->prepare("
                    INSERT INTO available_rewards (title, description, points_required, active) VALUES 
                    (?, ?, ?, TRUE),
                    (?, ?, ?, TRUE),
                    (?, ?, ?, TRUE),
                    (?, ?, ?, TRUE)
                ");
                $insertRewardsStmt->bind_param(
                    "ssisssisssis",
                    $title1, $desc1, $points1,
                    $title2, $desc2, $points2,
                    $title3, $desc3, $points3,
                    $title4, $desc4, $points4
                );

                $title1 = '10% Off Your Next Ride';
                $desc1 = 'Get a discount on your next ride anywhere in Guyana';
                $points1 = 500;

                $title2 = 'Free Airport Transfer';
                $desc2 = 'One free ride to or from the Cheddi Jagan International Airport';
                $points2 = 1500;

                $title3 = 'Premium Vehicle Upgrade';
                $desc3 = 'Upgrade to a premium vehicle for your next ride';
                $points3 = 800;

                $title4 = '25% Off Long Distance Ride';
                $desc4 = 'Discount on rides over 50 kilometers';
                $points4 = 1200;

                $insertRewardsStmt->execute();
                $insertRewardsStmt->close();
            }
        } catch (Exception $e) {
            error_log("Error creating tables: " . $e->getMessage());
            throw $e;
        }
    }

    // Create tables if they don't exist
    createTablesIfNotExist($conn);

    // Handle different endpoints and methods
    switch ($endpoint) {
        case 'reward-points':
            if ($method !== 'GET') {
                http_response_code(405); // Method Not Allowed
                throw new Exception('Method not allowed. Use GET.');
            }

            $userId = $currentUser['id'];
            error_log("Processing reward points for User ID: " . $userId);

            // Get or create reward points record
            $stmt = $conn->prepare("
                INSERT INTO reward_points (user_id, points) 
                VALUES (?, 0) 
                ON DUPLICATE KEY UPDATE points = points
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            // Fetch current points
            $pointsStmt = $conn->prepare("SELECT points FROM reward_points WHERE user_id = ?");
            $pointsStmt->bind_param("i", $userId);
            $pointsStmt->execute();
            $pointsResult = $pointsStmt->get_result();
            
            $points = 0;
            if ($pointsResult->num_rows > 0) {
                $points = $pointsResult->fetch_assoc()['points'];
            }
            $pointsStmt->close();
            error_log("User points: " . $points);

            // Fetch available rewards
            $rewardsStmt = $conn->prepare("
                SELECT id, title, description, points_required 
                FROM available_rewards 
                WHERE active = TRUE 
                ORDER BY points_required ASC
            ");
            $rewardsStmt->execute();
            $rewardsResult = $rewardsStmt->get_result();
            
            $rewards = [];
            while ($reward = $rewardsResult->fetch_assoc()) {
                $rewards[] = $reward;
            }
            $rewardsStmt->close();
            error_log("Available rewards count: " . count($rewards));

            // Fetch redeemed rewards history
            $redeemedStmt = $conn->prepare("
                SELECT rr.id, rr.points_used, rr.redeemed_at, 
                       ar.title, ar.description 
                FROM redeemed_rewards rr
                JOIN available_rewards ar ON rr.reward_id = ar.id
                WHERE rr.user_id = ?
                ORDER BY rr.redeemed_at DESC
            ");
            $redeemedStmt->bind_param("i", $userId);
            $redeemedStmt->execute();
            $redeemedResult = $redeemedStmt->get_result();
            
            $redeemedRewards = [];
            while ($redeemedReward = $redeemedResult->fetch_assoc()) {
                $redeemedRewards[] = $redeemedReward;
            }
            $redeemedStmt->close();
            error_log("Redeemed rewards count: " . count($redeemedRewards));

            $conn->close();

            $response = [
                'success' => true,
                'message' => 'Reward points retrieved successfully.',
                'data' => [
                    'points' => $points,
                    'rewards' => $rewards,
                    'redeemed_rewards' => $redeemedRewards
                ]
            ];
            break;

        default:
            throw new Exception("Unhandled endpoint: $endpoint");
    }

} catch (Exception $e) {
    // Log the error with full details
    error_log("Reward Points API FATAL ERROR: " . $e->getMessage());
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