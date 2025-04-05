<?php
/**
 * API Endpoint for Ride History
 */

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);  // Change to 0 in production

// Explicitly determine the request method
$method = $_SERVER['REQUEST_METHOD'];

// Log request for debugging
error_log("Ride History API request: Method = " . $method . ", Params = " . json_encode($_GET));

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

    // Handle GET request only
    if ($method !== 'GET') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use GET.');
    }

    $userId = $currentUser['id'];

    // Get filter parameters
    $filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;

    // Calculate offset
    $offset = ($page - 1) * $limit;

    // Connect to database
    $conn = dbConnect();

    // Ensure rides table exists
    $conn->query("CREATE TABLE IF NOT EXISTS rides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pickup VARCHAR(255) NOT NULL,
        dropoff VARCHAR(255) NOT NULL,
        fare DECIMAL(10,2) NOT NULL,
        status ENUM('searching', 'confirmed', 'arriving', 'arrived', 'in_progress', 'completed', 'cancelled') NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Count total rides
    $countWhere = "user_id = ?";
    $countParams = [$userId];
    $countTypes = "i";

    // Apply filters
    if ($filter === 'completed') {
        $countWhere .= " AND status = 'completed'";
    } elseif ($filter === 'cancelled') {
        $countWhere .= " AND status = 'cancelled'";
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM rides WHERE $countWhere");
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    // Prepare query
    $where = "user_id = ?";
    $params = [$userId];
    $types = "i";

    if ($filter === 'completed') {
        $where .= " AND status = 'completed'";
    } elseif ($filter === 'cancelled') {
        $where .= " AND status = 'cancelled'";
    }

    $stmt = $conn->prepare("
        SELECT id, pickup, dropoff, fare, status, created_at, vehicle_type, driver_id 
        FROM rides 
        WHERE $where 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");

    $stmt->bind_param($types . "ii", ...[...$params, $limit, $offset]);
    $stmt->execute();
    $result = $stmt->get_result();

    $rides = [];
    while ($ride = $result->fetch_assoc()) {
        // Format dates
        $date = new DateTime($ride['created_at']);
        $ride['formatted_date'] = $date->format('F j, Y');
        $ride['formatted_time'] = $date->format('g:i A');

        // Fetch driver name if exists
        if ($ride['driver_id']) {
            $driverStmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
            $driverStmt->bind_param("i", $ride['driver_id']);
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            $ride['driver_name'] = $driverResult->num_rows > 0 ? $driverResult->fetch_assoc()['name'] : null;
            $driverStmt->close();
        }

        $rides[] = $ride;
    }
    $stmt->close();
    $conn->close();

    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Ride history retrieved successfully.',
        'data' => [
            'rides' => $rides,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_rides' => $totalCount,
                'rides_per_page' => $limit
            ],
            'filter' => $filter
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log the error
    error_log("Ride History API fatal error: " . $e->getMessage());
    
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