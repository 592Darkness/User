<?php
/**
 * API Endpoint for Driver Rating
 * Returns the driver's current average rating from real data
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Set Content-Type header to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

$driverId = $_SESSION['driver_id'];

try {
    $conn = dbConnect();
    
    // Get the driver's average rating from actual reviews
    $ratingQuery = "
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings 
        FROM ride_ratings 
        WHERE driver_id = ?";
    
    $stmt = $conn->prepare($ratingQuery);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ratingData = $result->fetch_assoc();
        
        // If no ratings yet, use the default rating from the drivers table
        if ($ratingData['total_ratings'] == 0 || $ratingData['avg_rating'] === null) {
            $defaultRatingQuery = "SELECT rating FROM drivers WHERE id = ?";
            $defaultStmt = $conn->prepare($defaultRatingQuery);
            $defaultStmt->bind_param("i", $driverId);
            $defaultStmt->execute();
            $defaultResult = $defaultStmt->get_result();
            $defaultRating = $defaultResult->fetch_assoc()['rating'];
            $defaultStmt->close();
            
            $rating = $defaultRating;
        } else {
            $rating = $ratingData['avg_rating'];
        }
        
        $response['success'] = true;
        $response['message'] = 'Driver rating retrieved successfully';
        $response['data'] = [
            'rating' => floatval($rating),
            'total_ratings' => $ratingData['total_ratings']
        ];
    } else {
        $response['message'] = 'Failed to retrieve driver rating';
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error fetching driver rating: " . $e->getMessage());
    $response['message'] = 'An error occurred while fetching driver rating data';
}

echo json_encode($response);
exit;
?>