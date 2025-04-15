<?php
// Public_html/api/api-ride-history.php
// ADDED DEBUG LOGGING

// Enhanced error handling
error_reporting(E_ALL);
ini_set('display_errors', 1); // Keep 0 for production API
ini_set('log_errors', 1);

// Explicitly require config and functions
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Strict authentication check
    $currentUser = getCurrentUser(); // Fetches from $_SESSION['user']

    if (!$currentUser || !isset($currentUser['id'])) { // Check if user data and ID exist
        http_response_code(401); // Unauthorized
        error_log("API Ride History Error: Authentication failed or user ID missing in session. Session: " . json_encode($_SESSION));
        throw new Exception('Authentication required. Please log in.');
    }

    $userId = $currentUser['id'];
    error_log("API Ride History: Processing request for User ID: $userId"); // Log User ID

    // Handle GET request only
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Method not allowed. Use GET.');
    }

    // Get filter parameters
    $filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10; // Sensible limit
    $offset = ($page - 1) * $limit;
    error_log("API Ride History: Filter='$filter', Page=$page, Limit=$limit, Offset=$offset"); // Log params

    // Connect to database
    $conn = dbConnect();

    // --- Build Query ---
    $baseQuery = "SELECT r.id, r.pickup, r.dropoff, r.fare, r.status, r.created_at, r.vehicle_type, r.driver_id FROM rides r";
    $countQuery = "SELECT COUNT(*) as total FROM rides r";
    $whereClause = " WHERE r.user_id = ?";
    $params = [$userId];
    $types = "i"; // Type for user_id

    // Apply status filter
    if ($filter === 'completed') {
        $whereClause .= " AND r.status = 'completed'";
    } elseif ($filter === 'cancelled' || $filter === 'canceled') { // Accept both spellings
        $whereClause .= " AND r.status = 'cancelled'";
    } elseif ($filter === 'active') {
         // Define what 'active' means - e.g., not completed or cancelled
         $whereClause .= " AND r.status NOT IN ('completed', 'cancelled')";
    } elseif ($filter === 'month') {
         $whereClause .= " AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
    // Note: 'all' filter doesn't add status conditions

    // --- Count Total Matching Rides ---
    $finalCountQuery = $countQuery . $whereClause;
    error_log("API Ride History: Count Query: $finalCountQuery"); // Log count query
    error_log("API Ride History: Count Params: " . json_encode($params)); // Log count params
    $countStmt = $conn->prepare($finalCountQuery);
    if (!$countStmt) throw new Exception("Count query prepare failed: " . $conn->error);
    $countStmt->bind_param($types, ...$params); // Bind only user_id (and potentially status)
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    error_log("API Ride History: Total matching rides found: $totalCount"); // Log total count

    // --- Fetch Paginated Rides ---
    $finalQuery = $baseQuery . $whereClause . " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    error_log("API Ride History: Main Query: $finalQuery"); // Log main query
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii"; // Add types for limit and offset
    error_log("API Ride History: Main Params: " . json_encode($params)); // Log main params
    error_log("API Ride History: Main Types: $types"); // Log types

    $stmt = $conn->prepare($finalQuery);
    if (!$stmt) throw new Exception("Main query prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("API Ride History: Query executed. Rows returned by fetch: " . $result->num_rows); // Log fetched rows

    $rides = [];
    while ($ride = $result->fetch_assoc()) {
        // Format data for frontend
        try {
            $date = new DateTime($ride['created_at']);
            $ride['formatted_date'] = $date->format('M j, Y'); // Consistent format
            $ride['formatted_time'] = $date->format('g:i A');
        } catch (Exception $e) {
             $ride['formatted_date'] = 'N/A';
             $ride['formatted_time'] = 'N/A';
        }
        $ride['formatted_fare'] = 'G$' . number_format($ride['fare'] ?? 0, 2); // Format fare safely

        // Fetch driver name (Consider optimizing if causing performance issues)
        $ride['driver_name'] = null;
        if (!empty($ride['driver_id'])) {
            $driverStmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
            if ($driverStmt) {
                $driverStmt->bind_param("i", $ride['driver_id']);
                $driverStmt->execute();
                $driverResult = $driverStmt->get_result();
                $ride['driver_name'] = $driverResult->num_rows > 0 ? $driverResult->fetch_assoc()['name'] : null;
                $driverStmt->close();
            } else {
                 error_log("Failed to prepare driver name query: " . $conn->error);
            }
        }
        $rides[] = $ride;
    }
    $stmt->close();
    $conn->close();

    // Prepare response
    $response['success'] = true;
    $response['message'] = 'Ride history retrieved successfully.';
    $response['data'] = [
        'rides' => $rides,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
            'total_rides' => (int)$totalCount, // Ensure integer
            'rides_per_page' => $limit
        ],
        'filter' => $filter
    ];

} catch (Exception $e) {
    error_log("API Ride History Exception: " . $e->getMessage());
    $response['message'] = $e->getMessage();
    if (http_response_code() === 200) { // Set error code if not already set by auth check
         http_response_code(500);
    }
    if (isset($conn) && $conn->ping()) { $conn->close(); } // Ensure connection closed on error
}

echo json_encode($response);
exit;
?>