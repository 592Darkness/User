<?php
/**
 * API Router for Salaam Rides
 * 
 * This file handles requests directly for all endpoints
 * Specifically designed to handle URLs in the format /api/index.php/endpoint
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set the response header to JSON
header('Content-Type: application/json');

// CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request',
    'data' => null
];

// Extract endpoint from URL - DIRECT AND SIMPLE METHOD
$endpoint = '';

// Check PATH_INFO first (this is the pattern when accessing /api/index.php/endpoint)
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
    
    // Handle query parameters by removing them from the endpoint
    if (strpos($endpoint, '?') !== false) {
        $endpoint = substr($endpoint, 0, strpos($endpoint, '?'));
    }
}

// Log for debugging
error_log("API Request via index.php: " . $_SERVER['REQUEST_URI'] . " - Extracted Endpoint: " . $endpoint);

// Check request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle all API endpoints directly here to avoid routing complexity
try {
    // Map the endpoint to specific handlers
    switch ($endpoint) {
        case 'reward-points':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401);
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            // Handle reward points
            $userId = $_SESSION['user_id'];
            
            $conn = dbConnect();
            
            // Create reward_points table if it doesn't exist
            $tableCheck = $conn->query("SHOW TABLES LIKE 'reward_points'");
            if ($tableCheck->num_rows === 0) {
                $conn->query("
                    CREATE TABLE IF NOT EXISTS `reward_points` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `points` int(11) NOT NULL DEFAULT 0,
                        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }
            
            // Get or create reward points record
            $pointsStmt = $conn->prepare("SELECT points FROM reward_points WHERE user_id = ?");
            $pointsStmt->bind_param("i", $userId);
            $pointsStmt->execute();
            $pointsResult = $pointsStmt->get_result();
            
            $rewardPoints = 0;
            if ($pointsResult->num_rows > 0) {
                $pointsData = $pointsResult->fetch_assoc();
                $rewardPoints = $pointsData['points'];
            } else {
                // Create reward points record if it doesn't exist
                $createPointsStmt = $conn->prepare("INSERT INTO reward_points (user_id, points, created_at) VALUES (?, 0, NOW())");
                $createPointsStmt->bind_param("i", $userId);
                $createPointsStmt->execute();
                $createPointsStmt->close();
            }
            $pointsStmt->close();
            
            // Default rewards list
            $rewards = [
                [
                    'id' => 1,
                    'title' => '10% Off Your Next Ride',
                    'description' => 'Get a discount on your next ride anywhere in Guyana',
                    'points' => 500
                ],
                [
                    'id' => 2,
                    'title' => 'Free Airport Transfer',
                    'description' => 'One free ride to or from the Cheddi Jagan International Airport',
                    'points' => 1500
                ],
                [
                    'id' => 3,
                    'title' => 'Premium Upgrade',
                    'description' => 'Upgrade any standard ride to premium at no extra cost',
                    'points' => 800
                ],
                [
                    'id' => 4,
                    'title' => '25% Off Long Distance',
                    'description' => 'Get a discount on rides over 50km',
                    'points' => 1200
                ]
            ];
            
            // No redeemed rewards for now
            $redeemedRewards = [];
            
            $conn->close();
            
            $response = [
                'success' => true,
                'message' => 'Reward points retrieved successfully',
                'data' => [
                    'points' => $rewardPoints,
                    'rewards' => $rewards,
                    'redeemed_rewards' => $redeemedRewards
                ]
            ];
            break;
            
        case 'saved-places':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401);
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            $userId = $_SESSION['user_id'];
            $conn = dbConnect();
            
            // Create saved_places table if it doesn't exist
            $tableCheck = $conn->query("SHOW TABLES LIKE 'saved_places'");
            if ($tableCheck->num_rows === 0) {
                $conn->query("
                    CREATE TABLE IF NOT EXISTS `saved_places` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `name` varchar(100) NOT NULL,
                        `address` varchar(255) NOT NULL,
                        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            }
            
            // Get saved places
            $stmt = $conn->prepare("
                SELECT id, name, address, created_at 
                FROM saved_places 
                WHERE user_id = ? 
                ORDER BY name ASC
            ");
            
            if (!$stmt) {
                throw new Exception("Query preparation failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $places = [];
            while ($place = $result->fetch_assoc()) {
                $places[] = [
                    'id' => $place['id'],
                    'name' => $place['name'],
                    'address' => $place['address'],
                    'created_at' => $place['created_at']
                ];
            }
            
            $stmt->close();
            $conn->close();
            
            $response = [
                'success' => true,
                'message' => 'Saved places retrieved successfully.',
                'data' => [
                    'places' => $places
                ]
            ];
            break;
            
        case 'ride-history':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401);
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            // Get filter parameters
            $filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            
            // Validate parameters
            if ($page <= 0) {
                $page = 1;
            }
            
            if ($limit <= 0 || $limit > 50) {
                $limit = 10; // Default limit
            }
            
            // Calculate offset for pagination
            $offset = ($page - 1) * $limit;
            
            $userId = $_SESSION['user_id'];
            $conn = dbConnect();
            
            // Create rides table if it doesn't exist
            $tableCheck = $conn->query("SHOW TABLES LIKE 'rides'");
            if ($tableCheck->num_rows === 0) {
                $conn->query("
                    CREATE TABLE IF NOT EXISTS `rides` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `user_id` int(11) NOT NULL,
                        `driver_id` int(11) DEFAULT NULL,
                        `pickup` varchar(255) NOT NULL,
                        `dropoff` varchar(255) NOT NULL,
                        `fare` decimal(10,2) NOT NULL,
                        `final_fare` decimal(10,2) DEFAULT NULL,
                        `status` enum('searching','confirmed','arriving','arrived','in_progress','completed','cancelled','scheduled') NOT NULL,
                        `rating` decimal(3,1) DEFAULT NULL,
                        `vehicle_type` varchar(50) NOT NULL DEFAULT 'standard',
                        `promo_code` varchar(50) DEFAULT NULL,
                        `notes` text,
                        `scheduled_at` datetime DEFAULT NULL,
                        `completed_at` datetime DEFAULT NULL,
                        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`),
                        KEY `driver_id` (`driver_id`),
                        KEY `status` (`status`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Since we just created the table, it's empty
                $totalCount = 0;
                $rides = [];
            } else {
                // Build the base query
                $whereClause = "user_id = ?";
                $params = [$userId];
                $types = "i";
                
                // Apply filters
                if ($filter === 'month') {
                    $whereClause .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                } elseif ($filter === 'completed') {
                    $whereClause .= " AND status = 'completed'";
                } elseif ($filter === 'canceled' || $filter === 'cancelled') {
                    $whereClause .= " AND status = 'cancelled'";
                }
                
                // Get total count for pagination
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM rides WHERE $whereClause");
                $countStmt->bind_param($types, ...$params);
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $totalCount = $countResult->fetch_assoc()['count'];
                $countStmt->close();
                
                // Get rides with pagination
                $query = "SELECT id, pickup, dropoff, fare, status, created_at, completed_at, vehicle_type, driver_id 
                        FROM rides 
                        WHERE $whereClause 
                        ORDER BY created_at DESC 
                        LIMIT ?, ?";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types . "ii", ...[...$params, $offset, $limit]);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $rides = [];
                
                while ($ride = $result->fetch_assoc()) {
                    try {
                        $date = new DateTime($ride['created_at']);
                        $formattedDate = $date->format('F j, Y');
                        $formattedTime = $date->format('g:i A');
                    } catch (Exception $e) {
                        $formattedDate = 'Unknown Date';
                        $formattedTime = 'Unknown Time';
                    }
                    
                    // Default values
                    $rating = 0;
                    $driverName = null;
                    
                    if ($ride['driver_id'] !== null) {
                        $driverStmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
                        $driverStmt->bind_param("i", $ride['driver_id']);
                        $driverStmt->execute();
                        $driverResult = $driverStmt->get_result();
                        
                        if ($driverResult->num_rows > 0) {
                            $driverName = $driverResult->fetch_assoc()['name'];
                        }
                        
                        $driverStmt->close();
                    }
                    
                    $rides[] = [
                        'id' => $ride['id'],
                        'pickup' => $ride['pickup'],
                        'dropoff' => $ride['dropoff'],
                        'date' => $formattedDate,
                        'time' => $formattedTime,
                        'fare' => 'G$' . number_format($ride['fare']),
                        'status' => $ride['status'],
                        'vehicle_type' => $ride['vehicle_type'],
                        'rating' => $rating,
                        'driver_name' => $driverName,
                        'completed_at' => $ride['completed_at']
                    ];
                }
                
                $stmt->close();
            }
            
            // Calculate pagination details
            $totalPages = ceil($totalCount / $limit);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;
            $nextPage = $hasNextPage ? $page + 1 : null;
            $prevPage = $hasPrevPage ? $page - 1 : null;
            
            $response = [
                'success' => true,
                'message' => 'Ride history retrieved successfully.',
                'data' => [
                    'rides' => $rides,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'total_rides' => $totalCount,
                        'has_next_page' => $hasNextPage,
                        'has_prev_page' => $hasPrevPage,
                        'next_page' => $nextPage,
                        'prev_page' => $prevPage
                    ],
                    'filter' => $filter
                ]
            ];
            
            $conn->close();
            break;
            
        case 'payment-methods':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401);
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            // This endpoint is still in development, so just return an empty array
            $response = [
                'success' => true,
                'message' => 'Payment methods retrieved successfully',
                'data' => [
                    'payment_methods' => []
                ]
            ];
            break;
            
        default:
            http_response_code(404);
            $response = [
                'success' => false,
                'message' => 'API endpoint not found: ' . $endpoint
            ];
            break;
    }
} catch (Exception $e) {
    // Log the error
    error_log("API fatal error: " . $e->getMessage());
    
    // Return a proper error response
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
exit;
?>