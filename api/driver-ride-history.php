<?php
/**
 * API Endpoint for Driver Ride History
 * Returns a list of rides completed by the driver from the database
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

// Get filter and pagination parameters
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

// Validate filter
$validFilters = ['all', 'week', 'month', 'completed', 'cancelled'];
if (!in_array($filter, $validFilters)) {
    $filter = 'all';
}

// Validate pagination
if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 50) $limit = 10;

$offset = ($page - 1) * $limit;

// Fetch ride history
try {
    $conn = dbConnect();
    
    // Build the base query
    $query = "
        SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.vehicle_type, 
               r.status, r.created_at, r.completed_at,
               u.name as passenger_name, u.id as passenger_id
        FROM rides r
        JOIN users u ON r.user_id = u.id
        WHERE r.driver_id = ?
    ";
    
    // Apply filters
    $params = [$driverId];
    $types = "i";
    
    if ($filter === 'week') {
        $query .= " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter === 'month') {
        $query .= " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($filter === 'completed') {
        $query .= " AND r.status = 'completed'";
    } elseif ($filter === 'cancelled') {
        $query .= " AND r.status = 'cancelled'";
    }
    
    // Add count query for pagination
    $countQuery = str_replace("SELECT r.id, r.user_id, r.pickup, r.dropoff, r.fare, r.vehicle_type, 
               r.status, r.created_at, r.completed_at,
               u.name as passenger_name, u.id as passenger_id", "SELECT COUNT(*) as total", $query);
    
    // Execute count query
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRows = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Complete the main query
    $query .= " ORDER BY r.created_at DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";
    
    // Execute main query
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rides = [];
    
    while ($row = $result->fetch_assoc()) {
        // Get passenger rating
        $ratingQuery = "
            SELECT AVG(rating) as avg_rating
            FROM driver_ratings
            WHERE user_id = ?
        ";
        $ratingStmt = $conn->prepare($ratingQuery);
        $ratingStmt->bind_param("i", $row['passenger_id']);
        $ratingStmt->execute();
        $ratingResult = $ratingStmt->get_result();
        $ratingData = $ratingResult->fetch_assoc();
        
        $passengerRating = $ratingData['avg_rating'] ?: 5.0; // Default to 5 if no rating yet
        $ratingStmt->close();
        
        // Format dates
        $createdDate = new DateTime($row['created_at']);
        $formattedDate = $createdDate->format('M j, Y');
        $formattedTime = $createdDate->format('H:i');
        
        $rides[] = [
            'id' => $row['id'],
            'pickup' => $row['pickup'],
            'dropoff' => $row['dropoff'],
            'fare' => $row['fare'],
            'formatted_fare' => 'G$' . number_format($row['fare']),
            'vehicle_type' => $row['vehicle_type'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'completed_at' => $row['completed_at'],
            'date' => $formattedDate,
            'time' => $formattedTime,
            'passenger' => [
                'id' => $row['passenger_id'],
                'name' => $row['passenger_name'],
                'rating' => number_format($passengerRating, 1)
            ]
        ];
    }
    
    $stmt->close();
    
    // Calculate pagination data
    $totalPages = ceil($totalRows / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    $response['success'] = true;
    $response['message'] = 'Ride history retrieved successfully';
    $response['data'] = [
        'rides' => $rides,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_rides' => $totalRows,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage,
            'next_page' => $hasNextPage ? $page + 1 : null,
            'prev_page' => $hasPrevPage ? $page - 1 : null
        ],
        'filter' => $filter
    ];
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error fetching ride history: " . $e->getMessage());
    $response['message'] = 'An error occurred while fetching ride history';
}

echo json_encode($response);
exit;
?>