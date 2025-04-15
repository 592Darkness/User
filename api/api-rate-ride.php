<?php
/**
 * API Endpoint for Rating Rides
 * Place this file at /api/api-rate-ride.php
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);  // In production, do not display errors
ini_set('log_errors', 1);      // But do log them

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

    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401); // Unauthorized
        throw new Exception('Please log in to rate a ride.');
    }

    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use POST.');
    }

    // Get the current user
    $userId = $_SESSION['user_id'];

    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }

    // Validate required fields
    $rideId = isset($data['ride_id']) ? intval($data['ride_id']) : 0;
    $rating = isset($data['rating']) ? intval($data['rating']) : 0;
    $comment = isset($data['comment']) ? sanitize($data['comment']) : '';

    if ($rideId <= 0) {
        http_response_code(400); // Bad Request
        throw new Exception('Ride ID is required.');
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400); // Bad Request
        throw new Exception('Rating must be between 1 and 5.');
    }

    // Connect to the database
    $conn = dbConnect();
    
    // Verify the ride belongs to the user and is completed
    $stmt = $conn->prepare("
        SELECT id, user_id, driver_id, status, rating 
        FROM rides 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $rideId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404); // Not Found
        throw new Exception('Ride not found.');
    }
    
    $ride = $result->fetch_assoc();
    $stmt->close();
    
    if ($ride['user_id'] != $userId) {
        http_response_code(403); // Forbidden
        throw new Exception('You can only rate your own rides.');
    }
    
    if ($ride['status'] !== 'completed') {
        http_response_code(400); // Bad Request
        throw new Exception('Only completed rides can be rated.');
    }
    
    if (!empty($ride['rating'])) {
        http_response_code(409); // Conflict
        throw new Exception('This ride has already been rated.');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update the ride with the rating
        $updateRideStmt = $conn->prepare("
            UPDATE rides 
            SET rating = ?, rating_comment = ?, rating_date = NOW() 
            WHERE id = ?
        ");
        $updateRideStmt->bind_param("isi", $rating, $comment, $rideId);
        $updateRideStmt->execute();
        $updateRideStmt->close();
        
        // 2. If there's a driver, update their average rating
        if ($ride['driver_id']) {
            // Add rating to driver_ratings table for history
            $ratingHistoryStmt = $conn->prepare("
                INSERT INTO driver_ratings (
                    driver_id, ride_id, user_id, rating, comment, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $ratingHistoryStmt->bind_param("iiiss", $ride['driver_id'], $rideId, $userId, $rating, $comment);
            $ratingHistoryStmt->execute();
            $ratingHistoryStmt->close();
            
            // Update driver's average rating
            $avgRatingStmt = $conn->prepare("
                UPDATE drivers 
                SET 
                    rating = (
                        SELECT AVG(rating) 
                        FROM driver_ratings 
                        WHERE driver_id = ?
                    ),
                    rating_count = (
                        SELECT COUNT(*) 
                        FROM driver_ratings 
                        WHERE driver_id = ?
                    ),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $avgRatingStmt->bind_param("iii", $ride['driver_id'], $ride['driver_id'], $ride['driver_id']);
            $avgRatingStmt->execute();
            $avgRatingStmt->close();
        }
        
        // 3. Award bonus points for leaving a rating
        if (!empty($comment) && strlen($comment) > 10) {
            // Bonus points for detailed feedback
            $bonusPoints = 5;
            
            $pointsStmt = $conn->prepare("
                INSERT INTO reward_points (user_id, points, description, created_at)
                VALUES (?, ?, 'Bonus for rating with feedback', NOW())
                ON DUPLICATE KEY UPDATE
                points = points + ?,
                updated_at = NOW()
            ");
            $pointsStmt->bind_param("iii", $userId, $bonusPoints, $bonusPoints);
            $pointsStmt->execute();
            $pointsStmt->close();
            
            // Get updated points total
            $totalPointsStmt = $conn->prepare("
                SELECT points FROM reward_points WHERE user_id = ?
            ");
            $totalPointsStmt->bind_param("i", $userId);
            $totalPointsStmt->execute();
            $totalPointsResult = $totalPointsStmt->get_result();
            $totalPoints = 0;
            
            if ($totalPointsResult->num_rows > 0) {
                $totalPoints = $totalPointsResult->fetch_assoc()['points'];
            }
            $totalPointsStmt->close();
            
            $response['data']['points_earned'] = $bonusPoints;
            $response['data']['total_points'] = $totalPoints;
        }
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        $response = [
            'success' => true,
            'message' => 'Thank you for your feedback! Your rating has been saved.',
            'data' => [
                'ride_id' => $rideId,
                'rating' => $rating
            ]
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Rating submission error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;