<?php
/**
 * Admin-specific functions for Salaam Rides Admin Dashboard
 */

/**
 * Check if an admin is logged in
 *
 * @return boolean
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Redirect to login page if not logged in as admin
 */
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        setFlashMessage('error', 'You must be logged in to access the admin area.');
        header('Location: admin-login.php');
        exit;
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
        return $result ? $result['total'] : 0;
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
        return $result ? $result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total drivers: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get count of total rides
 *
 * @param string $status Optional ride status
 * @return int
 */
function getTotalRides($status = null) {
    try {
        $query = "SELECT COUNT(*) as total FROM rides";
        $params = [];
        
        if ($status) {
            $query .= " WHERE status = ?";
            $params[] = $status;
        }
        
        $result = dbFetchOne($query, $params);
        return $result ? $result['total'] : 0;
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
        $result = dbFetchAll("SELECT status, COUNT(*) as count FROM rides GROUP BY status");
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting rides by status: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total revenue from completed rides
 *
 * @param string $period Optional time period ('today', 'week', 'month', 'all')
 * @return float
 */
function getTotalRevenue($period = 'all') {
    try {
        $query = "SELECT SUM(fare) as total FROM rides WHERE status = 'completed'";
        
        switch ($period) {
            case 'today':
                $query .= " AND DATE(completed_at) = CURDATE()";
                break;
            case 'week':
                $query .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $query .= " AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
        
        $result = dbFetchOne($query);
        return $result && $result['total'] ? $result['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total revenue: " . $e->getMessage());
        return 0;
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
        
        $result = dbFetchAll($query, [$days]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting daily revenue: " . $e->getMessage());
        return [];
    }
}

/**
 * Get top drivers by completed rides
 *
 * @param int $limit Number of drivers to return
 * @return array
 */
function getTopDrivers($limit = 5) {
    try {
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
            GROUP BY d.id
            ORDER BY total_rides DESC
            LIMIT ?
        ";
        
        $result = dbFetchAll($query, [$limit]);
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
            WHERE status = 'completed'
            GROUP BY dropoff
            ORDER BY count DESC
            LIMIT ?
        ";
        
        $result = dbFetchAll($query, [$limit]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting popular destinations: " . $e->getMessage());
        return [];
    }
}

/**
 * Format currency for display
 *
 * @param float $amount Amount to format
 * @return string
 */
function formatCurrency($amount) {
    return 'G$' . number_format($amount, 0, '.', ',');
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
        
        $result = dbFetchAll($query, [$limit]);
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting recent rides: " . $e->getMessage());
        return [];
    }
}

/**
 * Get ride status color class for display
 *
 * @param string $status Ride status
 * @return string TailwindCSS color class
 */
function getRideStatusColor($status) {
    switch ($status) {
        case 'completed':
            return 'text-green-400';
        case 'cancelled':
        case 'canceled':
            return 'text-red-400';
        case 'in_progress':
            return 'text-blue-400';
        case 'searching':
            return 'text-yellow-400';
        case 'confirmed':
            return 'text-indigo-400';
        case 'arriving':
            return 'text-purple-400';
        case 'arrived':
            return 'text-pink-400';
        default:
            return 'text-gray-400';
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
        $query = "
            SELECT * FROM drivers
            WHERE id = ?
        ";
        
        $result = dbFetchOne($query, [$driverId]);
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Error getting driver details: " . $e->getMessage());
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
        $offset = ($page - 1) * $perPage;
        
        $countQuery = "SELECT COUNT(*) as total FROM drivers";
        $query = "
            SELECT 
                d.*, 
                (SELECT COUNT(*) FROM rides WHERE driver_id = d.id) as total_rides,
                (SELECT AVG(rating) FROM ride_ratings WHERE driver_id = d.id) as avg_rating
            FROM drivers d
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $countQuery .= " WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR vehicle LIKE ? OR plate LIKE ?";
            $query .= " WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? OR vehicle LIKE ? OR plate LIKE ?";
            
            $searchParam = "%$search%";
            $params = [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam];
        }
        
        $query .= " ORDER BY d.name ASC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        
        $total = dbFetchOne($countQuery, empty($search) ? [] : array_slice($params, 0, 5));
        $drivers = dbFetchAll($query, $params);
        
        return [
            'drivers' => $drivers ?: [],
            'total' => $total ? $total['total'] : 0,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => ceil(($total ? $total['total'] : 0) / $perPage)
        ];
    } catch (Exception $e) {
        error_log("Error getting all drivers: " . $e->getMessage());
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
        // Hash the password
        if (isset($driverData['password'])) {
            $driverData['password'] = password_hash($driverData['password'], PASSWORD_DEFAULT);
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
 *
 * @param int $driverId Driver ID
 * @param array $driverData Driver data
 * @return bool Success or failure
 */
function updateDriver($driverId, $driverData) {
    try {
        // Handle password update
        if (isset($driverData['password']) && !empty($driverData['password'])) {
            $driverData['password'] = password_hash($driverData['password'], PASSWORD_DEFAULT);
        } else {
            // Don't update password if empty
            unset($driverData['password']);
        }
        
        // Update the driver
        $result = dbUpdate('drivers', $driverData, 'id = ?', [$driverId]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating driver: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if email exists in drivers table (used for validation)
 *
 * @param string $email Email to check
 * @param int $excludeId Optional driver ID to exclude (for updates)
 * @return bool
 */
function driverEmailExists($email, $excludeId = null) {
    try {
        $query = "SELECT COUNT(*) as count FROM drivers WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = dbFetchOne($query, $params);
        return $result && $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking if driver email exists: " . $e->getMessage());
        return false;
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
        
        switch ($period) {
            case 'day':
                $interval = '24 HOUR';
                $dateFormat = '%H:00';
                break;
            case 'week':
                $interval = '7 DAY';
                $dateFormat = '%a';
                break;
            case 'month':
                $interval = '30 DAY';
                $dateFormat = '%d';
                break;
            case 'year':
                $interval = '12 MONTH';
                $dateFormat = '%b';
                break;
            default:
                $interval = '7 DAY';
                $dateFormat = '%a';
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
            ORDER BY MIN(created_at)
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
 *
 * @param int $driverId Driver ID
 * @return bool Success or failure
 */
function deleteDriver($driverId) {
    try {
        // Check if driver has any rides
        $hasDrives = dbFetchOne("SELECT COUNT(*) as count FROM rides WHERE driver_id = ?", [$driverId]);
        
        if ($hasDrives && $hasDrives['count'] > 0) {
            // Driver has rides, so we can't delete them
            return false;
        }
        
        // Delete driver if they have no rides
        return dbDelete('drivers', 'id = ?', [$driverId]);
    } catch (Exception $e) {
        error_log("Error deleting driver: " . $e->getMessage());
        return false;
    }
}
