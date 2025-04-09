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

    // Create reward_points table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS reward_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        points INT DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create available_rewards table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS available_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        points_required INT NOT NULL,
        active BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Create redeemed_rewards table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS redeemed_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        reward_id INT NOT NULL,
        points_used INT NOT NULL,
        redeemed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if any rewards exist, if not, insert default rewards
    $checkRewardsStmt = $conn->prepare("SELECT COUNT(*) as count FROM available_rewards");
    $checkRewardsStmt->execute();
    $result = $checkRewardsStmt->get_result();
    $rewardsCount = $result->fetch_assoc()['count'];
    $checkRewardsStmt->close();

    if ($rewardsCount == 0) {
        // Insert default rewards one by one to avoid parameter binding issues
        $insertReward1 = $conn->prepare("INSERT INTO available_rewards (title, description, points_required, active) VALUES (?, ?, ?, TRUE)");
        $title1 = '10% Off Your Next Ride';
        $desc1 = 'Get a discount on your next ride anywhere in Guyana';
        $points1 = 500;
        $insertReward1->bind_param("ssi", $title1, $desc1, $points1);
        $insertReward1->execute();
        $insertReward1->close();
        
        $insertReward2 = $conn->prepare("INSERT INTO available_rewards (title, description, points_required, active) VALUES (?, ?, ?, TRUE)");
        $title2 = 'Free Airport Transfer';
        $desc2 = 'One free ride to or from the Cheddi Jagan International Airport';
        $points2 = 1500;
        $insertReward2->bind_param("ssi", $title2, $desc2, $points2);
        $insertReward2->execute();
        $insertReward2->close();
        
        $insertReward3 = $conn->prepare("INSERT INTO available_rewards (title, description, points_required, active) VALUES (?, ?, ?, TRUE)");
        $title3 = 'Premium Vehicle Upgrade';
        $desc3 = 'Upgrade to a premium vehicle for your next ride';
        $points3 = 800;
        $insertReward3->bind_param("ssi", $title3, $desc3, $points3);
        $insertReward3->execute();
        $insertReward3->close();
        
        $insertReward4 = $conn->prepare("INSERT INTO available_rewards (title, description, points_required, active) VALUES (?, ?, ?, TRUE)");
        $title4 = '25% Off Long Distance Ride';
        $desc4 = 'Discount on rides over 50 kilometers';
        $points4 = 1200;
        $insertReward4->bind_param("ssi", $title4, $desc4, $points4);
        $insertReward4->execute();
        $insertReward4->close();
    }

    // Handle requests
    if ($endpoint === 'reward-points' || $endpoint === '') {
        if ($method !== 'GET') {
            http_response_code(405); // Method Not Allowed
            throw new Exception('Method not allowed. Use GET.');
        }

        $userId = $currentUser['id'];
        error_log("Processing reward points for User ID: " . $userId);

        // Get or create reward points record
        $pointsExists = $conn->prepare("SELECT EXISTS(SELECT 1 FROM reward_points WHERE user_id = ?) as record_exists");
        $pointsExists->bind_param("i", $userId);
        $pointsExists->execute();
        $exists = $pointsExists->get_result()->fetch_assoc()['record_exists'];
        $pointsExists->close();
        
        if (!$exists) {
            $createPoints = $conn->prepare("INSERT INTO reward_points (user_id, points) VALUES (?, 0)");
            $createPoints->bind_param("i", $userId);
            $createPoints->execute();
            $createPoints->close();
        }

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

        // Return successful response
        $response = [
            'success' => true,
            'message' => 'Reward points retrieved successfully.',
            'data' => [
                'points' => $points,
                'rewards' => $rewards,
                'redeemed_rewards' => $redeemedRewards
            ]
        ];
    } else if ($endpoint === 'redeem-reward') {
        if ($method !== 'POST') {
            http_response_code(405); // Method Not Allowed
            throw new Exception('Method not allowed. Use POST.');
        }

        // Get data from request
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['reward_id'])) {
            throw new Exception('Missing reward ID.');
        }

        $rewardId = intval($data['reward_id']);
        $userId = $currentUser['id'];

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Get user's current points
            $pointsStmt = $conn->prepare("SELECT points FROM reward_points WHERE user_id = ?");
            $pointsStmt->bind_param("i", $userId);
            $pointsStmt->execute();
            $pointsResult = $pointsStmt->get_result();
            
            if ($pointsResult->num_rows === 0) {
                throw new Exception('Reward points record not found.');
            }
            
            $currentPoints = $pointsResult->fetch_assoc()['points'];
            $pointsStmt->close();
            
            // Get reward details
            $rewardStmt = $conn->prepare("SELECT title, points_required FROM available_rewards WHERE id = ? AND active = TRUE");
            $rewardStmt->bind_param("i", $rewardId);
            $rewardStmt->execute();
            $rewardResult = $rewardStmt->get_result();
            
            if ($rewardResult->num_rows === 0) {
                throw new Exception('Reward not found or inactive.');
            }
            
            $reward = $rewardResult->fetch_assoc();
            $rewardStmt->close();
            
            // Check if user has enough points
            if ($currentPoints < $reward['points_required']) {
                throw new Exception('Not enough points to redeem this reward.');
            }
            
            // Deduct points
            $newPoints = $currentPoints - $reward['points_required'];
            $updatePointsStmt = $conn->prepare("UPDATE reward_points SET points = ? WHERE user_id = ?");
            $updatePointsStmt->bind_param("ii", $newPoints, $userId);
            $updatePointsStmt->execute();
            $updatePointsStmt->close();
            
            // Record redemption
            $redemptionStmt = $conn->prepare("INSERT INTO redeemed_rewards (user_id, reward_id, points_used) VALUES (?, ?, ?)");
            $pointsUsed = $reward['points_required'];
            $redemptionStmt->bind_param("iii", $userId, $rewardId, $pointsUsed);
            $redemptionStmt->execute();
            $redemptionStmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $response = [
                'success' => true,
                'message' => 'You have successfully redeemed: ' . $reward['title'],
                'data' => [
                    'points_remaining' => $newPoints,
                    'redeemed_reward' => $reward['title'],
                    'points_used' => $pointsUsed
                ]
            ];
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } else {
        throw new Exception("Unhandled endpoint: $endpoint");
    }

    $conn->close();

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