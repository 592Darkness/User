<?php
/**
 * API Router for Salaam Rides
 * Production-ready implementation with robust security and error handling
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
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
        case 'fare-estimate':
            try {
                // Get data from request 
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                if (!$data) {
                    $data = $_POST;
                }
                
                // Validate required fields
                if (empty($data['pickup']) || empty($data['dropoff']) || empty($data['vehicleType'])) {
                    http_response_code(400); // Bad Request
                    $response = [
                        'success' => false,
                        'message' => 'Missing required fields'
                    ];
                    break;
                }
                
                $pickup = sanitize($data['pickup']);
                $dropoff = sanitize($data['dropoff']);
                $vehicleType = sanitize($data['vehicleType']);
                
                // Validate vehicle type
                $validVehicleTypes = ['standard', 'suv', 'premium'];
                if (!in_array($vehicleType, $validVehicleTypes)) {
                    http_response_code(400); // Bad Request
                    $response = [
                        'success' => false,
                        'message' => 'Invalid vehicle type'
                    ];
                    break;
                }
                
                // Connect to database
                $conn = dbConnect();
                
                // Calculate distance between pickup and dropoff locations
                $apiKey = defined('GOOGLE_MAPS_SERVER_API_KEY') ? GOOGLE_MAPS_SERVER_API_KEY : GOOGLE_MAPS_API_KEY;
                $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . urlencode($pickup) . 
                       "&destinations=" . urlencode($dropoff) . 
                       "&mode=driving&key=" . $apiKey;
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                $result = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    throw new Exception("Distance calculation failed: " . curl_error($ch));
                }
                
                $distance = 0;
                $response_data = json_decode($result, true);
                
                if ($response_data['status'] === 'OK' && 
                    isset($response_data['rows'][0]['elements'][0]['status']) && 
                    $response_data['rows'][0]['elements'][0]['status'] === 'OK') {
                    // Distance in kilometers
                    $distance = $response_data['rows'][0]['elements'][0]['distance']['value'] / 1000;
                } else {
                    throw new Exception("Could not calculate distance between locations: " . ($response_data['error_message'] ?? $response_data['status']));
                }
                curl_close($ch);
                
                // Get pricing for selected vehicle type
                $pricingStmt = $conn->prepare("
                    SELECT base_rate, price_per_km, multiplier, min_fare 
                    FROM fare_rates 
                    WHERE vehicle_type = ? AND active = 1
                ");
                $pricingStmt->bind_param("s", $vehicleType);
                $pricingStmt->execute();
                $pricingResult = $pricingStmt->get_result();
                
                if ($pricingResult->num_rows == 0) {
                    throw new Exception("Pricing information not available for selected vehicle type");
                }
                
                $pricing = $pricingResult->fetch_assoc();
                $pricingStmt->close();
                
                // Calculate fare with traffic multiplier
                $baseRate = (float)$pricing['base_rate'];
                $pricePerKm = (float)$pricing['price_per_km'];
                $multiplier = (float)$pricing['multiplier'];
                $minFare = (float)$pricing['min_fare'];
                
                $distanceFare = $distance * $pricePerKm;
                $subtotal = $baseRate + $distanceFare;
                
                // Traffic time multiplier
                $hour = (int)date('H');
                $trafficMultiplier = (($hour >= 7 && $hour <= 9) || ($hour >= 16 && $hour <= 18)) ? 1.2 : 1.0;
                
                $totalFare = $subtotal * $multiplier * $trafficMultiplier;
                $finalFare = max($totalFare, $minFare);
                
                // Round to nearest 100
                $finalFare = ceil($finalFare / 100) * 100;
                
                // Store fare estimate if user is logged in
                if (isLoggedIn()) {
                    $userId = $_SESSION['user_id'];
                    $estimateStmt = $conn->prepare("
                        INSERT INTO fare_estimates 
                        (user_id, pickup, dropoff, vehicle_type, distance, fare, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $estimateStmt->bind_param("isssdd", $userId, $pickup, $dropoff, $vehicleType, $distance, $finalFare);
                    $estimateStmt->execute();
                    $estimateStmt->close();
                }
                
                $conn->close();
                
                // Success response
                $response = [
                    'success' => true,
                    'message' => 'Fare estimated successfully',
                    'data' => [
                        'fare' => 'G$' . number_format($finalFare),
                        'details' => [
                            'distance' => number_format($distance, 1),
                            'base_fare' => number_format($baseRate, 0),
                            'distance_fare' => number_format($distanceFare, 0),
                            'vehicle_multiplier' => $multiplier,
                            'traffic_multiplier' => $trafficMultiplier,
                            'total' => $finalFare
                        ]
                    ]
                ];
            } catch (Exception $e) {
                error_log("Fare estimation error: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                $response = [
                    'success' => false,
                    'message' => 'Error calculating fare'
                ];
            }
            break;

        case 'reward-points':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401); // Unauthorized
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            // Handle reward points
            $userId = $_SESSION['user_id'];
            
            try {
                $conn = dbConnect();
                
                // Get or create reward points record
                $pointsStmt = $conn->prepare("
                    SELECT points FROM reward_points WHERE user_id = ?
                ");
                $pointsStmt->bind_param("i", $userId);
                $pointsStmt->execute();
                $pointsResult = $pointsStmt->get_result();
                
                $rewardPoints = 0;
                if ($pointsResult->num_rows > 0) {
                    $pointsData = $pointsResult->fetch_assoc();
                    $rewardPoints = $pointsData['points'];
                } else {
                    // Create reward points record if it doesn't exist
                    $createPointsStmt = $conn->prepare("
                        INSERT INTO reward_points (user_id, points, created_at) 
                        VALUES (?, 0, NOW())
                    ");
                    $createPointsStmt->bind_param("i", $userId);
                    $createPointsStmt->execute();
                    $createPointsStmt->close();
                }
                $pointsStmt->close();
                
                // Get all available rewards
                $rewardsStmt = $conn->prepare("
                    SELECT id, title, description, points_required, created_at
                    FROM available_rewards
                    WHERE is_active = 1
                    ORDER BY points_required ASC
                ");
                $rewardsStmt->execute();
                $rewardsResult = $rewardsStmt->get_result();
                
                $rewards = [];
                while ($reward = $rewardsResult->fetch_assoc()) {
                    $rewards[] = [
                        'id' => $reward['id'],
                        'title' => $reward['title'],
                        'description' => $reward['description'],
                        'points' => $reward['points_required']
                    ];
                }
                $rewardsStmt->close();
                
                // Get user's redeemed rewards
                $redeemedStmt = $conn->prepare("
                    SELECT r.id, r.points_used, r.redeemed_at, ar.title, ar.description
                    FROM redeemed_rewards r
                    JOIN available_rewards ar ON r.reward_id = ar.id
                    WHERE r.user_id = ?
                    ORDER BY r.redeemed_at DESC
                ");
                $redeemedStmt->bind_param("i", $userId);
                $redeemedStmt->execute();
                $redeemedResult = $redeemedStmt->get_result();
                
                $redeemedRewards = [];
                while ($redeemed = $redeemedResult->fetch_assoc()) {
                    $redeemedRewards[] = [
                        'id' => $redeemed['id'],
                        'title' => $redeemed['title'],
                        'description' => $redeemed['description'],
                        'points_used' => $redeemed['points_used'],
                        'redeemed_at' => $redeemed['redeemed_at']
                    ];
                }
                $redeemedStmt->close();
                
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
            } catch (Exception $e) {
                error_log("Reward points error: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                $response = [
                    'success' => false,
                    'message' => 'Error retrieving reward points'
                ];
            }
            break;
            
        case 'saved-places':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401); // Unauthorized
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            $userId = $_SESSION['user_id'];
            
            try {
                $conn = dbConnect();
                
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
            } catch (Exception $e) {
                error_log("Saved places error: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                $response = [
                    'success' => false,
                    'message' => 'Error retrieving saved places'
                ];
            }
            break;
            
        case 'ride-history':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401); // Unauthorized
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            // Get filter parameters
            $filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
            $page = isset($_GET['page']) ? intval(sanitize($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? intval(sanitize($_GET['limit'])) : 10;
            
            // Validate parameters
            if ($page <= 0) {
                $page = 1;
            }
            
            if ($limit <= 0 || $limit > 50) {
                $limit = 10; // Default limit
            }
            
            try {
                // Calculate offset for pagination
                $offset = ($page - 1) * $limit;
                
                $userId = $_SESSION['user_id'];
                $conn = dbConnect();
                
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
                    
                    // Get driver name if available
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
                    
                    // Get rating if completed
                    $rating = 0;
                    if ($ride['status'] === 'completed') {
                        $ratingStmt = $conn->prepare("SELECT rating FROM ride_ratings WHERE ride_id = ?");
                        $ratingStmt->bind_param("i", $ride['id']);
                        $ratingStmt->execute();
                        $ratingResult = $ratingStmt->get_result();
                        
                        if ($ratingResult->num_rows > 0) {
                            $rating = $ratingResult->fetch_assoc()['rating'];
                        }
                        
                        $ratingStmt->close();
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
            } catch (Exception $e) {
                error_log("Ride history error: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                $response = [
                    'success' => false,
                    'message' => 'Error retrieving ride history'
                ];
            }
            break;
            
        case 'payment-methods':
            // Check authentication
            if (!isLoggedIn()) {
                http_response_code(401); // Unauthorized
                $response = [
                    'success' => false,
                    'message' => 'Authentication required.',
                    'authenticated' => false
                ];
                break;
            }
            
            try {
                $userId = $_SESSION['user_id'];
                $conn = dbConnect();
                
                // Get payment methods
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
                    $paymentMethods[] = [
                        'id' => $method['id'],
                        'type' => $method['type'],
                        'name' => $method['name'],
                        'last4' => $method['last4'],
                        'email' => $method['email'],
                        'is_default' => (bool)$method['is_default']
                    ];
                }
                $stmt->close();
                
                $conn->close();
                
                $response = [
                    'success' => true,
                    'message' => 'Payment methods retrieved successfully',
                    'data' => [
                        'payment_methods' => $paymentMethods
                    ]
                ];
            } catch (Exception $e) {
                error_log("Payment methods error: " . $e->getMessage());
                http_response_code(500); // Internal Server Error
                $response = [
                    'success' => false,
                    'message' => 'Error retrieving payment methods'
                ];
            }
            break;
            
        default:
            http_response_code(404); // Not Found
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
    http_response_code(500); // Internal Server Error
    $response = [
        'success' => false,
        'message' => 'Server error occurred'
    ];
}

echo json_encode($response);
exit;
?>