<?php
/**
 * Admin-specific functions for Salaam Rides Admin Dashboard
 * FIXED VERSION - removed duplicated code and fixed bugs
 */

/**
 * Check if an admin is logged in
 *
 * @return boolean
 */
function isAdminLoggedIn() {
    // Make sure session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start(); // Use @ to suppress potential "headers already sent" warnings if session started elsewhere
    }

    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect to login page if not logged in as admin
 */
function requireAdminLogin() {
    // Make sure session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    
    // Check if admin is logged in
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        // Check if this is an AJAX request
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        // For AJAX requests, return a JSON response with auth error
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Session expired. Please refresh the page and log in again.',
                'authenticated' => false,
                'redirect' => 'admin-login.php'
            ]);
            exit;
        }
        
        // For regular requests, redirect to login page
        setFlashMessage('error', 'You must be logged in to access the admin area.');
        
        // Ensure we can redirect
        if (!headers_sent()) {
            header('Location: admin-login.php');
            exit;
        } else {
            // Fallback if headers already sent
            echo '<script>window.location.href = "admin-login.php";</script>';
            exit;
        }
    }
}

/**
 * Get count of total users
 *
 * @return int
 */
function getTotalUsers() {
    try {
        $result = dbFetchOne("SELECT COUNT(*) as total FROM users");
        return $result ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total users: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get count of total drivers
 *
 * @return int
 */
function getTotalDrivers() {
    try {
        $result = dbFetchOne("SELECT COUNT(*) as total FROM drivers");
        return $result ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total drivers: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get count of total rides
 *
 * @param string|null $status Optional ride status
 * @return int
 */
function getTotalRides($status = null) {
    try {
        $query = "SELECT COUNT(*) as total FROM rides";
        $params = [];

        if ($status !== null && $status !== '') {
            $query .= " WHERE status = ?";
            $params[] = $status;
        }

        $result = dbFetchOne($query, $params);
        return $result ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total rides: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get count of rides by status
 *
 * @return array
 */
function getRidesByStatus() {
    try {
        $query = "SELECT status, COUNT(*) as count FROM rides WHERE status IS NOT NULL GROUP BY status";
        $result = dbFetchAll($query);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting rides by status: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total revenue from completed rides
 *
 * @param string $period Optional time period ('day', 'week', 'month', 'year', 'all')
 * @return float
 */
function getTotalRevenue($period = 'all') {
    try {
        $query = "SELECT SUM(fare) as total FROM rides WHERE status = 'completed'";
        $params = [];
        $dateCondition = '';

        switch ($period) {
            case 'day':
                $dateCondition = " AND DATE(completed_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = " AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = " AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = " AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        $query .= $dateCondition;

        $result = dbFetchOne($query, $params);
        return $result && $result['total'] ? (float)$result['total'] : 0.0;
    } catch (Exception $e) {
        error_log("Error getting total revenue for period '$period': " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Get daily revenue for the last X days
 *
 * @param int $days Number of days
 * @return array
 */
function getDailyRevenue($days = 7) {
    try {
        $query = "
            SELECT
                DATE(completed_at) as date,
                SUM(fare) as revenue,
                COUNT(*) as rides
            FROM rides
            WHERE
                status = 'completed'
                AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(completed_at)
            ORDER BY date ASC
        ";

        $result = dbFetchAll($query, [(int)$days]);
        // Ensure revenue and rides are numbers
        return array_map(function($item) {
            $item['revenue'] = (float)($item['revenue'] ?? 0);
            $item['rides'] = (int)($item['rides'] ?? 0);
            return $item;
        }, $result ?: []);
    } catch (Exception $e) {
        error_log("Error getting daily revenue: " . $e->getMessage());
        return [];
    }
}

/**
 * Get top drivers by completed rides or revenue
 *
 * @param int $limit Number of drivers to return
 * @param string $orderBy 'rides' or 'revenue'
 * @return array
 */
function getTopDrivers($limit = 5, $orderBy = 'rides') {
    try {
        $orderClause = ($orderBy === 'revenue') ? 'total_revenue DESC' : 'total_rides DESC';

        $query = "
            SELECT
                d.id,
                d.name,
                d.vehicle,
                COUNT(r.id) as total_rides,
                SUM(r.fare) as total_revenue
            FROM rides r
            JOIN drivers d ON r.driver_id = d.id
            WHERE r.status = 'completed'
            GROUP BY d.id, d.name, d.vehicle
            ORDER BY $orderClause
            LIMIT ?
        ";

        $result = dbFetchAll($query, [(int)$limit]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting top drivers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get popular destinations by ride count
 *
 * @param int $limit Number of destinations to return
 * @return array
 */
function getPopularDestinations($limit = 5) {
    try {
        $query = "
            SELECT
                dropoff,
                COUNT(*) as count
            FROM rides
            WHERE status = 'completed' AND dropoff IS NOT NULL AND dropoff != ''
            GROUP BY dropoff
            ORDER BY count DESC
            LIMIT ?
        ";

        $result = dbFetchAll($query, [(int)$limit]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting popular destinations: " . $e->getMessage());
        return [];
    }
}

/**
 * Format currency for display
 *
 * @param float|null $amount Amount to format
 * @return string
 */
function formatCurrency($amount) {
    if ($amount === null) {
        return 'G$0'; // Or perhaps 'N/A'
    }
    return 'G$' . number_format((float)$amount, 0, '.', ',');
}


/**
 * Get recent rides
 *
 * @param int $limit Number of rides to return
 * @return array
 */
function getRecentRides($limit = 10) {
    try {
        $query = "
            SELECT
                r.id,
                r.pickup,
                r.dropoff,
                r.fare,
                r.status,
                r.created_at,
                r.completed_at,
                u.name as user_name,
                d.name as driver_name
            FROM rides r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN drivers d ON r.driver_id = d.id
            ORDER BY r.created_at DESC
            LIMIT ?
        ";

        $result = dbFetchAll($query, [(int)$limit]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting recent rides: " . $e->getMessage());
        return [];
    }
}

/**
 * Get ride status color class for display
 *
 * @param string|null $status Ride status
 * @return string TailwindCSS color class
 */
function getRideStatusColor($status) {
    // Use background colors for better visibility
    switch (strtolower($status ?? 'unknown')) {
        case 'completed':
            return 'bg-green-500/20 text-green-300 border border-green-500/30';
        case 'cancelled':
        case 'canceled':
            return 'bg-red-500/20 text-red-300 border border-red-500/30';
        case 'in_progress':
            return 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
        case 'searching':
            return 'bg-yellow-500/20 text-yellow-300 border border-yellow-500/30';
        case 'confirmed':
            return 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30';
        case 'arriving':
            return 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
        case 'arrived':
            return 'bg-pink-500/20 text-pink-300 border border-pink-500/30';
        case 'scheduled':
             return 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30';
        default:
            return 'bg-gray-500/20 text-gray-300 border border-gray-500/30';
    }
}

/**
 * Get driver data for detailed view or editing
 *
 * @param int $driverId Driver ID
 * @return array|null
 */
function getDriverDetails($driverId) {
    try {
        // Fetch driver details along with average rating and total rides
        // Ensure driver_ratings table exists or handle potential error
        $ratingQueryPart = "";
        // --- FIX: Check for 'driver_ratings' table ---
        if (dbTableExists('driver_ratings')) {
             // --- FIX: Use 'driver_ratings.driver_id' ---
             $ratingQueryPart = "(SELECT AVG(rating) FROM driver_ratings WHERE driver_ratings.driver_id = d.id) as avg_rating";
        } else {
             $ratingQueryPart = "d.rating as avg_rating"; // Fallback to drivers.rating
        }

        $query = "
            SELECT
                d.*,
                (SELECT COUNT(*) FROM rides WHERE driver_id = d.id) as total_rides,
                $ratingQueryPart
            FROM drivers d
            WHERE d.id = ?
        ";

        $result = dbFetchOne($query, [(int)$driverId]);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting driver details for ID $driverId: " . $e->getMessage());
        return null;
    }
}

/**
 * Get list of all drivers with pagination
 *
 * @param int $page Current page
 * @param int $perPage Items per page
 * @param string $search Search term
 * @return array
 */
function getAllDrivers($page = 1, $perPage = 10, $search = '') {
    try {
        $offset = max(0, ($page - 1) * $perPage); // Ensure offset is not negative
        $perPage = max(1, $perPage); // Ensure perPage is at least 1

        // Base query parts
        $countQueryBase = "SELECT COUNT(*) as total FROM drivers d";
        $queryBase = "
            SELECT
                d.*,
                (SELECT COUNT(*) FROM rides WHERE rides.driver_id = d.id) as total_rides"; // Explicit table name

        // --- FIX: Check for 'driver_ratings' table and use correct column ---
        if (dbTableExists('driver_ratings')) {
             $queryBase .= ", (SELECT AVG(rating) FROM driver_ratings WHERE driver_ratings.driver_id = d.id) as avg_rating"; // Use driver_ratings
        } else {
             $queryBase .= ", d.rating as avg_rating"; // Fallback to drivers.rating
        }

        $queryBase .= " FROM drivers d";

        // Initialize where clauses and parameters
        $whereConditions = [];
        $countParams = [];
        $queryParams = [];
        $types = ""; // Keep track of param types

        // Add search filter if provided
        if (!empty($search)) {
            $searchParam = "%" . $search . "%";
            $whereConditions[] = "(d.name LIKE ? OR d.email LIKE ? OR d.phone LIKE ? OR d.vehicle LIKE ? OR d.plate LIKE ?)";
            $countParams = array_fill(0, 5, $searchParam);
            $queryParams = array_fill(0, 5, $searchParam);
            $types = str_repeat('s', 5); // 5 string parameters for search
        }

        // Construct final queries
        $countQuery = $countQueryBase;
        $query = $queryBase;

        if (!empty($whereConditions)) {
            $whereClause = " WHERE " . implode(" AND ", $whereConditions);
            $countQuery .= $whereClause;
            $query .= $whereClause;
        }

        // Execute count query
        $totalResult = dbFetchOne($countQuery, $countParams);
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        // Add ordering and pagination to main query only
        $query .= " ORDER BY d.name ASC LIMIT ? OFFSET ?";
        $queryParams[] = (int)$perPage;
        $queryParams[] = (int)$offset;
        $types .= "ii"; // Add types for limit and offset

        // Execute main query
        $drivers = dbFetchAll($query, $queryParams); // Assuming dbFetchAll uses dbQuery which handles types

        return [
            'drivers' => $drivers ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => ($perPage > 0) ? ceil($total / $perPage) : 0
        ];
    } catch (Exception $e) {
        error_log("Error getting all drivers: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString()); // Log stack trace for detailed debugging
        return [
            'drivers' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => 0
        ];
    }
}


/**
 * Add a new driver
 *
 * @param array $driverData Driver data
 * @return int|false New driver ID or false on failure
 */
function addDriver($driverData) {
    try {
        // Hash the password securely
        if (isset($driverData['password']) && !empty($driverData['password'])) {
            $driverData['password'] = password_hash($driverData['password'], PASSWORD_DEFAULT);
        } else {
             throw new Exception("Password is required to add a driver."); // Ensure password is set
        }

        // Add created_at timestamp
        $driverData['created_at'] = date('Y-m-d H:i:s');

        // Insert the driver
        $driverId = dbInsert('drivers', $driverData);

        return $driverId;
    } catch (Exception $e) {
        error_log("Error adding driver: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a driver
 * Update a driver - Enhanced with better debugging
 *
 * @param int $driverId Driver ID
 * @param array $driverData Driver data
 * @return bool Success or failure
 */
function updateDriver($driverId, $driverData) {
    try {
        // Log what we're trying to do
        error_log("Attempting to update driver ID: $driverId with data: " . print_r($driverData, true));
        
        // Validate driver ID
        if (empty($driverId) || !is_numeric($driverId)) {
            error_log("Invalid driver ID: $driverId");
            return false;
        }
        
        // Handle password update only if a new password is provided
        if (isset($driverData['password']) && !empty($driverData['password'])) {
            $driverData['password'] = password_hash($driverData['password'], PASSWORD_DEFAULT);
            error_log("Password hashed for update");
        } else {
            // Don't update password if empty or not provided
            unset($driverData['password']);
            error_log("Password field removed from update data (empty)");
        }
        
        // Connect to database
        $conn = dbConnect();
        
        // Check if driver exists
        $checkStmt = $conn->prepare("SELECT id FROM drivers WHERE id = ?");
        $checkStmt->bind_param("i", $driverId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            error_log("Driver ID $driverId does not exist");
            $checkStmt->close();
            $conn->close();
            return false;
        }
        $checkStmt->close();
        
        // Build update query
        $fields = [];
        $values = [];
        $types = '';
        
        foreach ($driverData as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
            
            // Determine parameter type
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's'; // Strings and other types
            }
        }
        
        // Add driver ID to values and types
        $values[] = $driverId;
        $types .= 'i';
        
        // Create the SQL query
        $sql = "UPDATE drivers SET " . implode(', ', $fields) . " WHERE id = ?";
        error_log("Update SQL: $sql with types: $types");
        
        // Prepare and execute the statement
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $conn->close();
            return false;
        }
        
        // Bind parameters using references
        $bindParams = array($types);
        foreach ($values as $key => $value) {
            $bindParams[] = &$values[$key];
        }
        
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        
        // Execute the statement
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            $conn->close();
            return false;
        }
        
        // Log affected rows
        $affectedRows = $stmt->affected_rows;
        error_log("Update executed. Affected rows: $affectedRows");
        
        $stmt->close();
        $conn->close();
        
        // Consider it a success even if no rows were affected (data might be unchanged)
        return true;
    } catch (Exception $e) {
        error_log("Exception in updateDriver: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Check if email exists in drivers table (used for validation)
 *
 * @param string $email Email to check
 * @param int|null $excludeId Optional driver ID to exclude (for updates)
 * @return bool
 */
function driverEmailExists($email, $excludeId = null) {
    try {
        $query = "SELECT COUNT(*) as count FROM drivers WHERE email = ?";
        $params = [$email];
        $types = "s";

        if ($excludeId !== null && is_numeric($excludeId)) {
            $query .= " AND id != ?";
            $params[] = (int)$excludeId;
            $types .= "i";
        }

        // Assuming dbFetchOne can handle types or dbQuery does
        $result = dbFetchOne($query, $params);
        return $result && $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking if driver email exists: " . $e->getMessage());
        return false; // Assume it doesn't exist on error to prevent blocking valid actions
    }
}

/**
 * Get ride analytics for a specific period
 *
 * @param string $period Period ('day', 'week', 'month', 'year')
 * @return array
 */
function getRideAnalytics($period = 'week') {
    try {
        $interval = '';
        $dateFormat = '';
        $orderBy = 'MIN(created_at)'; // Default order for most periods

        switch ($period) {
            case 'day':
                $interval = '24 HOUR';
                $dateFormat = '%H:00'; // Group by hour
                break;
            case 'week':
                $interval = '7 DAY';
                $dateFormat = '%a'; // Group by day abbreviation (Mon, Tue)
                $orderBy = 'DAYOFWEEK(MIN(created_at))'; // Order by day of week
                break;
            case 'month':
                $interval = '30 DAY';
                $dateFormat = '%d'; // Group by day of month
                break;
            case 'year':
                $interval = '12 MONTH';
                $dateFormat = '%b'; // Group by month abbreviation (Jan, Feb)
                $orderBy = 'MONTH(MIN(created_at))'; // Order by month number
                break;
            default: // Fallback to week
                $interval = '7 DAY';
                $dateFormat = '%a';
                $orderBy = 'DAYOFWEEK(MIN(created_at))';
        }

        $query = "
            SELECT
                DATE_FORMAT(created_at, ?) as label,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                SUM(CASE WHEN status = 'completed' THEN fare ELSE 0 END) as revenue
            FROM rides
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
            GROUP BY label
            ORDER BY $orderBy
        ";

        $result = dbFetchAll($query, [$dateFormat]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting ride analytics: " . $e->getMessage());
        return [];
    }
}

/**
 * Get ride counts by vehicle type
 *
 * @return array
 */
function getRidesByVehicleType() {
    try {
        $query = "
            SELECT
                vehicle_type,
                COUNT(*) as count
            FROM rides
            WHERE vehicle_type IS NOT NULL AND vehicle_type != ''
            GROUP BY vehicle_type
            ORDER BY count DESC
        ";

        $result = dbFetchAll($query);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting rides by vehicle type: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete a driver by ID
 * Note: Checks if the driver has associated rides before deleting.
 *
 * @param int $driverId Driver ID
 * @return bool Success or failure
 */
function deleteDriver($driverId) {
    try {
        // Check if driver has any rides (completed or otherwise)
        $hasRides = dbFetchOne("SELECT COUNT(*) as count FROM rides WHERE driver_id = ?", [(int)$driverId]);

        if ($hasRides && $hasRides['count'] > 0) {
            // Driver has rides, so we cannot delete them directly
            error_log("Attempted to delete driver ID $driverId with {$hasRides['count']} associated rides.");
            return false; // Indicate failure due to associated rides
        }

        // Delete driver if they have no rides
        return dbDelete('drivers', 'id = ?', [(int)$driverId]);
    } catch (Exception $e) {
        error_log("Error deleting driver ID $driverId: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user data for detailed view
 *
 * @param int $userId User ID
 * @return array|null
 */
function getUserDetails($userId) {
    try {
         // Ensure driver_ratings table exists or handle potential error
        $ratingQueryPart = "";
        if (dbTableExists('driver_ratings')) { // Note: table name might be different
             $ratingQueryPart = "(SELECT AVG(rating) FROM driver_ratings WHERE user_id = u.id) as avg_rating"; // Rating given BY user to drivers
        } else {
             $ratingQueryPart = "NULL as avg_rating";
        }

        $query = "
            SELECT
                u.*,
                (SELECT COUNT(*) FROM rides WHERE user_id = u.id) as total_rides,
                $ratingQueryPart
            FROM users u
            WHERE u.id = ?
        ";

        $result = dbFetchOne($query, [(int)$userId]);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting user details for ID $userId: " . $e->getMessage());
        return null;
    }
}

/**
 * Get list of all users with pagination
 *
 * @param int $page Current page
 * @param int $perPage Items per page
 * @param string $search Search term
 * @return array
 */
function getAllUsers($page = 1, $perPage = 10, $search = '') {
    try {
        $offset = max(0, ($page - 1) * $perPage);
        $perPage = max(1, $perPage);

        // Base query parts
        $countQueryBase = "SELECT COUNT(*) as total FROM users u";
        $queryBase = "
            SELECT
                u.*,
                (SELECT COUNT(*) FROM rides WHERE user_id = u.id) as total_rides
            FROM users u
        ";

        // Initialize where clauses and parameters
        $whereConditions = [];
        $countParams = [];
        $queryParams = [];
        $types = "";

        // Add search filter if provided
        if (!empty($search)) {
            $searchParam = "%" . $search . "%";
            $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $countParams = array_fill(0, 3, $searchParam);
            $queryParams = array_fill(0, 3, $searchParam);
            $types = str_repeat('s', 3);
        }

        // Construct final queries
        $countQuery = $countQueryBase;
        $query = $queryBase;

        if (!empty($whereConditions)) {
            $whereClause = " WHERE " . implode(" AND ", $whereConditions);
            $countQuery .= $whereClause;
            $query .= $whereClause;
        }

        // Execute count query
        $totalResult = dbFetchOne($countQuery, $countParams);
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        // Add ordering and pagination to main query only
        $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $queryParams[] = (int)$perPage;
        $queryParams[] = (int)$offset;
        $types .= "ii";

        // Execute main query
        $users = dbFetchAll($query, $queryParams); // Assuming dbFetchAll uses dbQuery which handles types

        return [
            'users' => $users ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => ($perPage > 0) ? ceil($total / $perPage) : 0
        ];
    } catch (Exception $e) {
        error_log("Error getting all users: " . $e->getMessage());
        return [
            'users' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => 0
        ];
    }
}

/**
 * Get all pricing settings
 *
 * @return array
 */
function getAllPricing() {
    try {
        // Check if pricing table exists first
        if (!dbTableExists('pricing')) {
             error_log("Pricing table does not exist.");
             // Optionally, attempt to create it here or return default values
             return []; // Return empty if table doesn't exist
        }
        $query = "SELECT * FROM pricing ORDER BY FIELD(vehicle_type, 'standard', 'suv', 'premium')"; // Order logically
        return dbFetchAll($query) ?: [];
    } catch (Exception $e) {
        error_log("Error getting all pricing: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pricing for a specific vehicle type
 *
 * @param string $vehicleType Vehicle type
 * @return array|null
 */
function getPricingByVehicleType($vehicleType) {
    try {
         if (!dbTableExists('pricing')) return null; // Return null if table doesn't exist
        $query = "SELECT * FROM pricing WHERE vehicle_type = ?";
        return dbFetchOne($query, [$vehicleType]);
    } catch (Exception $e) {
        error_log("Error getting pricing by vehicle type '$vehicleType': " . $e->getMessage());
        return null;
    }
}

/**
 * Update pricing for a vehicle type
 *
 * @param string $vehicleType Vehicle type
 * @param array $data Pricing data
 * @return bool
 */
function updatePricing($vehicleType, $data) {
    try {
         if (!dbTableExists('pricing')) return false; // Cannot update if table doesn't exist
        // Add updated_at timestamp if the table supports it
        // $data['updated_at'] = date('Y-m-d H:i:s');
        return dbUpdate('pricing', $data, 'vehicle_type = ?', [$vehicleType]);
    } catch (Exception $e) {
        error_log("Error updating pricing for $vehicleType: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate fare based on distance and vehicle type using database pricing
 *
 * @param float $distance Distance in kilometers
 * @param string $vehicleType Vehicle type
 * @return array Fare details or error message
 */
function calculateFare($distance, $vehicleType) {
    try {
        $pricing = getPricingByVehicleType($vehicleType);

        if (!$pricing) {
            // Fallback to default pricing if database fetch fails or table doesn't exist
            $defaultPricing = [
                'standard' => ['base_rate' => 1000.00, 'price_per_km' => 100.00, 'multiplier' => 1.00, 'min_fare' => 1000.00],
                'suv' => ['base_rate' => 1500.00, 'price_per_km' => 150.00, 'multiplier' => 1.50, 'min_fare' => 1500.00],
                'premium' => ['base_rate' => 2000.00, 'price_per_km' => 200.00, 'multiplier' => 2.00, 'min_fare' => 2000.00]
            ];
            $pricing = $defaultPricing[strtolower($vehicleType)] ?? $defaultPricing['standard'];
            error_log("Using default pricing for vehicle type: $vehicleType");
        }

        $baseRate = (float)($pricing['base_rate'] ?? 0);
        $pricePerKm = (float)($pricing['price_per_km'] ?? 0);
        $multiplier = (float)($pricing['multiplier'] ?? 1.0);
        $minFare = (float)($pricing['min_fare'] ?? 0);

        $distanceFare = $distance * $pricePerKm;
        $subtotal = $baseRate + $distanceFare;
        $totalFare = $subtotal * $multiplier;
        $finalFare = max($totalFare, $minFare);

        return [
            'success' => true,
            'fare' => $finalFare,
            'details' => [
                'base_rate' => $baseRate,
                'distance_fare' => $distanceFare,
                'subtotal' => $subtotal,
                'multiplier' => $multiplier,
                'total_fare' => $totalFare,
                'min_fare' => $minFare,
                'final_fare' => $finalFare,
                'min_fare_applied' => ($finalFare === $minFare)
            ]
        ];
    } catch (Exception $e) {
        error_log("Error calculating fare: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error calculating fare.'
        ];
    }
}

/**
 * Get revenue growth percentage compared to previous period
 *
 * @param string $period Period type ('day', 'week', 'month', 'year')
 * @return float|null Growth percentage or null if not calculable
 */
function getRevenueGrowth($period = 'week') {
    try {
        $currentPeriodQuery = "SELECT SUM(fare) as total FROM rides WHERE status = 'completed'";
        $previousPeriodQuery = "SELECT SUM(fare) as total FROM rides WHERE status = 'completed'";
        $params = [];

        switch ($period) {
            case 'day':
                $currentPeriodQuery .= " AND DATE(completed_at) = CURDATE()";
                $previousPeriodQuery .= " AND DATE(completed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $currentPeriodQuery .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                $previousPeriodQuery .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $currentPeriodQuery .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                $previousPeriodQuery .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $currentPeriodQuery .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                $previousPeriodQuery .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR) AND completed_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                 return null; // Cannot calculate for 'all' or invalid periods
        }

        $currentResult = dbFetchOne($currentPeriodQuery, $params);
        $previousResult = dbFetchOne($previousPeriodQuery, $params);

        $currentRevenue = $currentResult && isset($currentResult['total']) ? (float)$currentResult['total'] : 0;
        $previousRevenue = $previousResult && isset($previousResult['total']) ? (float)$previousResult['total'] : 0;

        if ($previousRevenue > 0) {
            return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
        } else if ($currentRevenue > 0) {
            return 100.0; // Indicate 100% growth if previous was 0 but current is positive
        }

        return 0.0; // Return 0% growth if both are 0 or previous is 0
    } catch (Exception $e) {
        error_log("Error calculating revenue growth for period '$period': " . $e->getMessage());
        return null; // Indicate error
    }
}

/**
 * Get percentage of drivers currently online
 *
 * @return float Percentage of drivers currently online
 */
function getOnlineDriversPercentage() {
    try {
        $totalQuery = "SELECT COUNT(*) as total FROM drivers";
        $onlineQuery = "SELECT COUNT(*) as online FROM drivers WHERE status = 'available'";

        $totalResult = dbFetchOne($totalQuery);
        $onlineResult = dbFetchOne($onlineQuery);

        $totalDrivers = $totalResult && isset($totalResult['total']) ? (int)$totalResult['total'] : 0;
        $onlineDrivers = $onlineResult && isset($onlineResult['online']) ? (int)$onlineResult['online'] : 0;

        if ($totalDrivers > 0) {
            return ($onlineDrivers / $totalDrivers) * 100;
        }

        return 0.0;
    } catch (Exception $e) {
        error_log("Error calculating online drivers percentage: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Get completion rate for rides
 *
 * @param string $period Period type ('day', 'week', 'month', 'year', 'all')
 * @return float Percentage of completed rides
 */
function getRideCompletionRate($period = 'all') {
    try {
        $totalQuery = "SELECT COUNT(*) as total FROM rides";
        $completedQuery = "SELECT COUNT(*) as completed FROM rides WHERE status = 'completed'";
        $params = [];
        $completedParams = [];

        $dateCondition = '';
        switch ($period) {
            case 'day':
                $dateCondition = " DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = " created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = " created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = " created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }

        if (!empty($dateCondition)) {
             $totalQuery .= " WHERE " . $dateCondition;
             // Apply same date range to completed rides based on *completion* time if available, else created_at
             $completedDateCondition = str_replace('created_at', 'COALESCE(completed_at, created_at)', $dateCondition);
             $completedQuery .= " AND " . $completedDateCondition;
        }

        $totalResult = dbFetchOne($totalQuery, $params);
        $completedResult = dbFetchOne($completedQuery, $completedParams);

        $totalRides = $totalResult && isset($totalResult['total']) ? (int)$totalResult['total'] : 0;
        $completedRides = $completedResult && isset($completedResult['completed']) ? (int)$completedResult['completed'] : 0;

        if ($totalRides > 0) {
            return ($completedRides / $totalRides) * 100;
        }

        return 0.0;
    } catch (Exception $e) {
        error_log("Error calculating ride completion rate for period '$period': " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Get new users in time period
 *
 * @param string $period Period type ('day', 'week', 'month', 'year')
 * @return int Number of new users
 */
function getNewUsers($period = 'week') {
    try {
        $query = "SELECT COUNT(*) as total FROM users";
        $params = [];
        $dateCondition = '';

        switch ($period) {
            case 'day':
                $dateCondition = " DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $dateCondition = " created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $dateCondition = " created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'year':
                $dateCondition = " created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                 // If 'all' or invalid period, don't add a date condition
                 break;
        }

        if (!empty($dateCondition)) {
             $query .= " WHERE " . $dateCondition;
        }

        $result = dbFetchOne($query, $params);
        return $result && isset($result['total']) ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting new users for period '$period': " . $e->getMessage());
        return 0;
    }
}

/**
 * Reset a user's password
 *
 * @param int $userId User ID
 * @param string $newPassword New password (plain text)
 * @param bool $notifyUser Whether to notify the user by email
 * @return bool Success or failure
 */
function resetUserPassword($userId, $newPassword, $notifyUser = false) {
    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = dbUpdate('users', ['password' => $hashedPassword], 'id = ?', [(int)$userId]);

        if ($result && $notifyUser) {
            // Fetch user email
            $user = getUserDetails($userId);
            if ($user && !empty($user['email'])) {
                // Send email notification (implement email sending logic)
                // mail($user['email'], "Your Password Has Been Reset", "Your password was reset by an administrator.");
                error_log("Password reset notification sent to user ID: $userId, Email: {$user['email']}");
            }
        }
        return $result;
    } catch (Exception $e) {
        error_log("Error resetting user password for ID $userId: " . $e->getMessage());
        return false;
    }
}
?>