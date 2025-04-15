<?php
/**
 * Functions for calculating accurate driver ETAs using Google Maps API
 * Production-ready implementation with error handling and caching
 */

require_once dirname(__DIR__) . '/includes/calculate-distance.php';

/**
 * Calculate ETA between two locations using Google Maps Distance Matrix API
 * 
 * @param string $driverLocation Driver's current location (address or coordinates)
 * @param string $pickupLocation Customer's pickup location (address or coordinates)
 * @param mysqli $conn Database connection for caching results
 * @param bool $forceRefresh Force recalculation even if cached data exists
 * @return array Array containing ETA details and status
 */
function calculateDriverETA($driverLocation, $pickupLocation, $conn = null, $forceRefresh = false) {
    // Validate inputs
    if (empty($driverLocation) || empty($pickupLocation)) {
        return [
            'success' => false,
            'error' => 'Both driver location and pickup location are required'
        ];
    }
    
    try {
        // Check if ETA is cached in the database first if not forced to refresh
        if (!$forceRefresh && $conn) {
            $cachedEta = getCachedETA($driverLocation, $pickupLocation, $conn);
            if ($cachedEta) {
                return $cachedEta;
            }
        }
        
        // Use the comprehensive distance calculation function
        $distanceResult = calculateDistance($driverLocation, $pickupLocation, $forceRefresh);
        
        if ($distanceResult['success']) {
            // Extract ETA information
            $seconds = $distanceResult['duration']['value'];
            $minutes = $distanceResult['duration']['minutes'];
            $formatted = $distanceResult['duration']['text'];
            
            // Log the ETA for analytics
            if ($conn) {
                logDriverETA($driverLocation, $pickupLocation, $minutes, $seconds, $conn);
            }
            
            return [
                'success' => true,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'formatted' => $formatted,
                'distance' => [
                    'km' => $distanceResult['distance']['km'],
                    'text' => $distanceResult['distance']['text']
                ],
                'driver_location' => $distanceResult['origin']['resolved'],
                'pickup_location' => $distanceResult['destination']['resolved']
            ];
        } else {
            // If the distance calculation failed, query historical data
            if ($conn) {
                $historicalEta = getHistoricalETA($driverLocation, $pickupLocation, $conn);
                if ($historicalEta['success']) {
                    return $historicalEta;
                }
            }
            
            // If no historical data, return error
            error_log("ETA calculation error: " . $distanceResult['error']);
            return [
                'success' => false,
                'error' => $distanceResult['error']
            ];
        }
    } catch (Exception $e) {
        error_log("Driver ETA calculation exception: " . $e->getMessage());
        return [
            'success' => false,
            'error' => "Internal error"
        ];
    }
}

/**
 * Get cached ETA from database
 * 
 * @param string $driverLocation Driver location
 * @param string $pickupLocation Pickup location
 * @param mysqli $conn Database connection
 * @return array|null Cached ETA data or null if not found
 */
function getCachedETA($driverLocation, $pickupLocation, $conn) {
    try {
        // Create cache table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS eta_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_location VARCHAR(255) NOT NULL,
            pickup_location VARCHAR(255) NOT NULL,
            estimated_minutes INT NOT NULL,
            estimated_seconds INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_driver_pickup (driver_location(50), pickup_location(50)),
            INDEX idx_created_at (created_at)
        )";
        $conn->query($createTableSql);
        
        // Query for recent cached data (last 5 minutes)
        $stmt = $conn->prepare("
            SELECT estimated_minutes, estimated_seconds
            FROM eta_cache
            WHERE driver_location = ? AND pickup_location = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("ss", $driverLocation, $pickupLocation);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return [
                'success' => true,
                'minutes' => $row['estimated_minutes'],
                'seconds' => $row['estimated_seconds'],
                'formatted' => $row['estimated_minutes'] . ' minutes',
                'source' => 'cache'
            ];
        }
        
        $stmt->close();
        return null;
    } catch (Exception $e) {
        error_log("Error getting cached ETA: " . $e->getMessage());
        return null;
    }
}

/**
 * Get historical ETA based on similar routes from logs
 * 
 * @param string $driverLocation Driver location
 * @param string $pickupLocation Pickup location
 * @param mysqli $conn Database connection
 * @return array Historical ETA data
 */
function getHistoricalETA($driverLocation, $pickupLocation, $conn) {
    try {
        // Create driver_eta_logs table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS driver_eta_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_location VARCHAR(255) NOT NULL,
            pickup_location VARCHAR(255) NOT NULL,
            estimated_minutes INT NOT NULL,
            estimated_seconds INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_created_at (created_at)
        )";
        $conn->query($createTableSql);
        
        // Try to find similar driver/pickup locations first
        $similarQuery = "
            SELECT AVG(estimated_minutes) as avg_minutes, 
                  AVG(estimated_seconds) as avg_seconds,
                  COUNT(*) as count
            FROM driver_eta_logs
            WHERE driver_location LIKE ? AND pickup_location LIKE ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        
        $driverLocationLike = '%' . substr($driverLocation, 0, min(strlen($driverLocation), 10)) . '%';
        $pickupLocationLike = '%' . substr($pickupLocation, 0, min(strlen($pickupLocation), 10)) . '%';
        
        $stmt = $conn->prepare($similarQuery);
        $stmt->bind_param("ss", $driverLocationLike, $pickupLocationLike);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['count'] > 0 && $row['avg_minutes'] > 0) {
            $minutes = round($row['avg_minutes']);
            $seconds = round($row['avg_seconds']);
            
            $stmt->close();
            return [
                'success' => true,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'formatted' => $minutes . ' minutes',
                'source' => 'historical_similar'
            ];
        }
        
        $stmt->close();
        
        // If no similar routes found, get overall average
        $avgQuery = "
            SELECT AVG(estimated_minutes) as avg_minutes, 
                  AVG(estimated_seconds) as avg_seconds,
                  COUNT(*) as count
            FROM driver_eta_logs
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        
        $avgStmt = $conn->prepare($avgQuery);
        $avgStmt->execute();
        $avgResult = $avgStmt->get_result();
        $avgRow = $avgResult->fetch_assoc();
        
        if ($avgRow && $avgRow['count'] > 0 && $avgRow['avg_minutes'] > 0) {
            $minutes = round($avgRow['avg_minutes']);
            $seconds = round($avgRow['avg_seconds']);
            
            $avgStmt->close();
            return [
                'success' => true,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'formatted' => $minutes . ' minutes',
                'source' => 'historical_average'
            ];
        }
        
        $avgStmt->close();
        
        // If no historical data at all, calculate based on ride logs
        $rideLogQuery = "
            SELECT AVG(TIME_TO_SEC(TIMEDIFF(arrived_at, confirmed_at))) as avg_arrival_time,
                  COUNT(*) as count
            FROM ride_logs
            WHERE action = 'status_change'
            AND arrived_at IS NOT NULL AND confirmed_at IS NOT NULL
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $rideLogStmt = $conn->prepare($rideLogQuery);
        $rideLogStmt->execute();
        $rideLogResult = $rideLogStmt->get_result();
        $rideLogRow = $rideLogResult->fetch_assoc();
        
        if ($rideLogRow && $rideLogRow['count'] > 0 && $rideLogRow['avg_arrival_time'] > 0) {
            $seconds = round($rideLogRow['avg_arrival_time']);
            $minutes = round($seconds / 60);
            
            $rideLogStmt->close();
            return [
                'success' => true,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'formatted' => $minutes . ' minutes',
                'source' => 'ride_logs'
            ];
        }
        
        $rideLogStmt->close();
        
        // No historical data available, return default values
        return [
            'success' => true,
            'minutes' => 10,
            'seconds' => 600,
            'formatted' => '10 minutes',
            'source' => 'default'
        ];
    } catch (Exception $e) {
        error_log("Error getting historical ETA: " . $e->getMessage());
        return [
            'success' => true,
            'minutes' => 10,
            'seconds' => 600,
            'formatted' => '10 minutes',
            'source' => 'default_after_error'
        ];
    }
}

/**
 * Log driver ETA calculations for analytics and improvements
 * 
 * @param string $driverLocation Driver location
 * @param string $pickupLocation Pickup location
 * @param int $minutes Estimated minutes
 * @param int $seconds Estimated seconds
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function logDriverETA($driverLocation, $pickupLocation, $minutes, $seconds, $conn) {
    try {
        // Create driver_eta_logs table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS driver_eta_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_location VARCHAR(255) NOT NULL,
            pickup_location VARCHAR(255) NOT NULL,
            estimated_minutes INT NOT NULL,
            estimated_seconds INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_created_at (created_at)
        )";
        $conn->query($createTableSql);
        
        // Insert the log entry
        $stmt = $conn->prepare("INSERT INTO driver_eta_logs 
                             (driver_location, pickup_location, estimated_minutes, estimated_seconds, created_at) 
                             VALUES (?, ?, ?, ?, NOW())");
        
        $stmt->bind_param("ssii", $driverLocation, $pickupLocation, $minutes, $seconds);
        $result = $stmt->execute();
        $stmt->close();
        
        // Also cache the result for quick retrieval
        $cacheTableSql = "CREATE TABLE IF NOT EXISTS eta_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_location VARCHAR(255) NOT NULL,
            pickup_location VARCHAR(255) NOT NULL,
            estimated_minutes INT NOT NULL,
            estimated_seconds INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_driver_pickup (driver_location(50), pickup_location(50)),
            INDEX idx_created_at (created_at)
        )";
        $conn->query($cacheTableSql);
        
        $cacheStmt = $conn->prepare("INSERT INTO eta_cache 
                                  (driver_location, pickup_location, estimated_minutes, estimated_seconds, created_at) 
                                  VALUES (?, ?, ?, ?, NOW())");
        
        $cacheStmt->bind_param("ssii", $driverLocation, $pickupLocation, $minutes, $seconds);
        $cacheStmt->execute();
        $cacheStmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error logging driver ETA: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the nearest available driver for a pickup location
 * 
 * @param string $pickupLocation Customer pickup location
 * @param string $vehicleType Type of vehicle requested
 * @param mysqli $conn Database connection
 * @return array Driver information including location and ETA
 */
function getNearestDriver($pickupLocation, $vehicleType = 'standard', $conn = null) {
    if (!$conn) {
        return [
            'success' => false,
            'error' => 'Database connection required'
        ];
    }
    
    try {
        // Try to geocode the pickup location to get coordinates
        $pickupCoords = null;
        $geocodeResult = geocodeAddress($pickupLocation);
        if ($geocodeResult['success']) {
            $pickupCoords = [
                'lat' => $geocodeResult['lat'],
                'lng' => $geocodeResult['lng']
            ];
        } else {
            error_log("Geocoding failed for pickup: " . $geocodeResult['error']);
        }
        
        $drivers = [];
        
        // Query to find closest drivers
        if ($pickupCoords) {
            // Use the optimized query with coordinates
            $query = "
                SELECT d.id, d.name, d.phone, d.rating, 
                      v.type AS vehicle_type, v.model AS vehicle_model, v.plate AS vehicle_plate,
                      dl.location_lat, dl.location_lng, dl.location_address, dl.updated_at,
                      ( 6371 * acos( 
                          cos( radians(?) ) * 
                          cos( radians( dl.location_lat ) ) * 
                          cos( radians( dl.location_lng ) - radians(?) ) + 
                          sin( radians(?) ) * 
                          sin( radians( dl.location_lat ) ) 
                      ) ) AS distance
                FROM drivers d
                JOIN vehicles v ON d.vehicle_id = v.id
                JOIN driver_locations dl ON d.id = dl.driver_id
                WHERE d.status = 'available'
                AND v.type = ?
                AND dl.updated_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY distance
                LIMIT 5
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ddds", 
                $pickupCoords['lat'],
                $pickupCoords['lng'],
                $pickupCoords['lat'],
                $vehicleType
            );
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $drivers[] = $row;
            }
            
            $stmt->close();
        } else {
            // Fallback query without coordinates-based filtering
            $fallbackQuery = "
                SELECT d.id, d.name, d.phone, d.rating, 
                      v.type AS vehicle_type, v.model AS vehicle_model, v.plate AS vehicle_plate,
                      dl.location_lat, dl.location_lng, dl.location_address, dl.updated_at
                FROM drivers d
                JOIN vehicles v ON d.vehicle_id = v.id
                JOIN driver_locations dl ON d.id = dl.driver_id
                WHERE d.status = 'available'
                AND v.type = ?
                AND dl.updated_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY dl.updated_at DESC
                LIMIT 10
            ";
            
            $stmt = $conn->prepare($fallbackQuery);
            $stmt->bind_param("s", $vehicleType);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $drivers[] = $row;
            }
            
            $stmt->close();
        }
        
        if (empty($drivers)) {
            // If no drivers with exact vehicle type, find any available driver
            $anyDriverQuery = "
                SELECT d.id, d.name, d.phone, d.rating, 
                      v.type AS vehicle_type, v.model AS vehicle_model, v.plate AS vehicle_plate,
                      dl.location_lat, dl.location_lng, dl.location_address, dl.updated_at
                FROM drivers d
                JOIN vehicles v ON d.vehicle_id = v.id
                JOIN driver_locations dl ON d.id = dl.driver_id
                WHERE d.status = 'available'
                AND dl.updated_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ORDER BY dl.updated_at DESC
                LIMIT 5
            ";
            
            $anyDriverStmt = $conn->prepare($anyDriverQuery);
            $anyDriverStmt->execute();
            $anyDriverResult = $anyDriverStmt->get_result();
            
            while ($row = $anyDriverResult->fetch_assoc()) {
                $drivers[] = $row;
            }
            
            $anyDriverStmt->close();
            
            if (empty($drivers)) {
                return [
                    'success' => false,
                    'error' => 'No available drivers found',
                    'vehicle_type' => $vehicleType
                ];
            }
        }
        
        // For the top drivers found, calculate actual ETA using Google Maps API
        // Then select the one with shortest ETA
        $selectedDriver = null;
        $shortestETA = PHP_INT_MAX;
        
        foreach ($drivers as $driver) {
            // Format driver location as coordinates or address
            $driverLocation = '';
            if (!empty($driver['location_address'])) {
                $driverLocation = $driver['location_address'];
            } else if (!empty($driver['location_lat']) && !empty($driver['location_lng'])) {
                $driverLocation = $driver['location_lat'] . ',' . $driver['location_lng'];
            } else {
                continue; // Skip if no valid location
            }
            
            // Calculate actual ETA using Google Maps
            $etaResult = calculateDriverETA($driverLocation, $pickupLocation, $conn);
            
            if ($etaResult['success'] && $etaResult['seconds'] < $shortestETA) {
                $shortestETA = $etaResult['seconds'];
                $selectedDriver = [
                    'id' => $driver['id'],
                    'name' => $driver['name'],
                    'phone' => $driver['phone'],
                    'rating' => $driver['rating'],
                    'vehicle' => $driver['vehicle_model'] ?? 'Vehicle',
                    'vehicle_type' => $driver['vehicle_type'],
                    'plate' => $driver['vehicle_plate'],
                    'location' => $driver['location_address'] ?? "{$driver['location_lat']},{$driver['location_lng']}",
                    'location_updated' => $driver['updated_at'],
                    'eta' => $etaResult['minutes'],
                    'eta_seconds' => $etaResult['seconds'],
                    'eta_text' => $etaResult['formatted'],
                    'distance_km' => $etaResult['distance']['km'] ?? null,
                    'distance_text' => $etaResult['distance']['text'] ?? null
                ];
            }
        }
        
        if ($selectedDriver) {
            return [
                'success' => true,
                'driver' => $selectedDriver
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Could not calculate ETA for any available drivers',
                'vehicle_type' => $vehicleType
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error finding nearest driver: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error finding nearest driver'
        ];
    }
}

/**
 * Get a specific driver's current location and information
 * 
 * @param int $driverId The driver's ID
 * @param mysqli $conn Database connection
 * @return array Driver information
 */
function getDriverLocation($driverId, $conn) {
    if (!$conn) {
        return [
            'success' => false,
            'error' => 'Database connection required'
        ];
    }
    
    try {
        $query = "
            SELECT d.id, d.name, d.phone, d.rating, d.status,
                  v.type AS vehicle_type, v.model AS vehicle_model, v.plate AS vehicle_plate,
                  dl.location_lat, dl.location_lng, dl.location_address, dl.updated_at,
                  dl.heading, dl.speed, dl.accuracy_meters
            FROM drivers d
            JOIN vehicles v ON d.vehicle_id = v.id
            LEFT JOIN driver_locations dl ON d.id = dl.driver_id
            WHERE d.id = ?
            ORDER BY dl.updated_at DESC
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Check if driver exists without location data
            $driverQuery = "SELECT d.id, d.name, d.phone, d.rating, d.status,
                                  v.type AS vehicle_type, v.model AS vehicle_model, v.plate AS vehicle_plate
                           FROM drivers d
                           LEFT JOIN vehicles v ON d.vehicle_id = v.id
                           WHERE d.id = ?";
            $driverStmt = $conn->prepare($driverQuery);
            $driverStmt->bind_param("i", $driverId);
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            
            if ($driverResult->num_rows === 0) {
                return [
                    'success' => false,
                    'error' => 'Driver not found',
                    'driver_id' => $driverId
                ];
            }
            
            $driver = $driverResult->fetch_assoc();
            $driverStmt->close();
            
            return [
                'success' => true,
                'warning' => 'Driver found but no location data available',
                'driver' => [
                    'id' => $driver['id'],
                    'name' => $driver['name'],
                    'phone' => $driver['phone'],
                    'rating' => $driver['rating'],
                    'status' => $driver['status'],
                    'vehicle' => $driver['vehicle_model'] ?? 'Vehicle',
                    'vehicle_type' => $driver['vehicle_type'] ?? 'standard',
                    'plate' => $driver['vehicle_plate'] ?? 'N/A',
                    'location' => null
                ]
            ];
        }
        
        $driver = $result->fetch_assoc();
        $stmt->close();
        
        // Calculate location freshness
        $locationFreshness = null;
        if (!empty($driver['updated_at'])) {
            $updatedTime = new DateTime($driver['updated_at']);
            $now = new DateTime();
            $locationFreshness = $now->getTimestamp() - $updatedTime->getTimestamp(); // seconds
        }
        
        return [
            'success' => true,
            'driver' => [
                'id' => $driver['id'],
                'name' => $driver['name'],
                'phone' => $driver['phone'],
                'rating' => $driver['rating'],
                'status' => $driver['status'],
                'vehicle' => $driver['vehicle_model'] ?? 'Vehicle',
                'vehicle_type' => $driver['vehicle_type'],
                'plate' => $driver['vehicle_plate'],
                'location' => [
                    'lat' => $driver['location_lat'],
                    'lng' => $driver['location_lng'],
                    'address' => $driver['location_address'],
                    'updated_at' => $driver['updated_at'],
                    'freshness_seconds' => $locationFreshness,
                    'heading' => $driver['heading'],
                    'speed' => $driver['speed'],
                    'accuracy' => $driver['accuracy_meters']
                ]
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error getting driver location: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error getting driver information'
        ];
    }
}

/**
 * Update a driver's location in the database
 * 
 * @param int $driverId The driver's ID
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @param string $address Optional address corresponding to coordinates
 * @param array $additionalData Optional additional data (heading, speed, accuracy)
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function updateDriverLocation($driverId, $lat, $lng, $address = null, $additionalData = [], $conn = null) {
    if (!$conn) {
        return false;
    }
    
    try {
        // Prepare values from additional data
        $heading = isset($additionalData['heading']) ? $additionalData['heading'] : null;
        $speed = isset($additionalData['speed']) ? $additionalData['speed'] : null;
        $accuracy = isset($additionalData['accuracy']) ? $additionalData['accuracy'] : null;
        
        // Insert new location record
        $stmt = $conn->prepare("
            INSERT INTO driver_locations 
            (driver_id, location_lat, location_lng, location_address, accuracy_meters, heading, speed, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param("iddsiii", 
            $driverId, 
            $lat, 
            $lng, 
            $address, 
            $accuracy, 
            $heading, 
            $speed
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            // Update driver's status to available if not already assigned
            $updateDriverSql = "
                UPDATE drivers 
                SET status = CASE WHEN status = 'offline' THEN 'available' ELSE status END,
                    last_active = NOW()
                WHERE id = ?
            ";
            $updateStmt = $conn->prepare($updateDriverSql);
            $updateStmt->bind_param("i", $driverId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating driver location: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a driver is available for assignment
 * 
 * @param int $driverId The driver's ID
 * @param mysqli $conn Database connection
 * @return bool True if driver is available
 */
function isDriverAvailable($driverId, $conn) {
    try {
        $stmt = $conn->prepare("SELECT status FROM drivers WHERE id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $driver = $result->fetch_assoc();
        $stmt->close();
        
        return $driver['status'] === 'available';
    } catch (Exception $e) {
        error_log("Error checking driver availability: " . $e->getMessage());
        return false;
    }
}

/**
 * Assign a driver to a ride and update statuses
 * 
 * @param int $rideId The ride ID
 * @param int $driverId The driver's ID
 * @param mysqli $conn Database connection
 * @return array Result of assignment operation
 */
function assignDriverToRide($rideId, $driverId, $conn) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Check if driver is available
        if (!isDriverAvailable($driverId, $conn)) {
            $conn->rollback();
            return [
                'success' => false,
                'error' => 'Driver is not available for assignment'
            ];
        }
        
        // Check if ride is in a state that can have a driver assigned
        $rideStmt = $conn->prepare("SELECT status FROM rides WHERE id = ?");
        $rideStmt->bind_param("i", $rideId);
        $rideStmt->execute();
        $rideResult = $rideStmt->get_result();
        
        if ($rideResult->num_rows === 0) {
            $rideStmt->close();
            $conn->rollback();
            return [
                'success' => false,
                'error' => 'Ride not found'
            ];
        }
        
        $ride = $rideResult->fetch_assoc();
        $rideStmt->close();
        
        if ($ride['status'] !== 'searching' && $ride['status'] !== 'scheduled') {
            $conn->rollback();
            return [
                'success' => false,
                'error' => 'Ride is not in a state that can be assigned a driver'
            ];
        }
        
        // Update the ride with the driver assignment
        $updateRideStmt = $conn->prepare("
            UPDATE rides 
            SET driver_id = ?, 
                status = 'confirmed',
                updated_at = NOW() 
            WHERE id = ?
        ");
        $updateRideStmt->bind_param("ii", $driverId, $rideId);
        $updateRideStmt->execute();
        $updateRideStmt->close();
        
        // Update the driver's status to assigned
        $updateDriverStmt = $conn->prepare("
            UPDATE drivers 
            SET status = 'assigned', 
                current_ride_id = ? 
            WHERE id = ?
        ");
        $updateDriverStmt->bind_param("ii", $rideId, $driverId);
        $updateDriverStmt->execute();
        $updateDriverStmt->close();
        
        // Log the assignment
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs 
            (ride_id, driver_id, action, details, created_at) 
            VALUES (?, ?, 'driver_assigned', ?, NOW())
        ");
        
        $details = json_encode([
            'assigned_at' => date('Y-m-d H:i:s'),
            'assigned_by' => 'system',
            'previous_status' => $ride['status']
        ]);
        
        $logStmt->bind_param("iis", $rideId, $driverId, $details);
        $logStmt->execute();
        $logStmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Driver assigned successfully',
            'ride_id' => $rideId,
            'driver_id' => $driverId
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        if ($conn->ping()) {
            $conn->rollback();
        }
        
        error_log("Error assigning driver: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error assigning driver'
        ];
    }
}

/**
 * Geocode an address to get coordinates
 * 
 * @param string $address Address to geocode
 * @return array Geocoding result with lat/lng or error
 */
function geocodeAddress($address) {
    try {
        $apiKey = defined('GOOGLE_MAPS_SERVER_API_KEY') ? GOOGLE_MAPS_SERVER_API_KEY : GOOGLE_MAPS_API_KEY;
        
        if (empty($apiKey)) {
            throw new Exception("Google Maps API key not configured");
        }
        
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $apiKey;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception("Geocoding request failed: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK' && !empty($data['results'])) {
            $location = $data['results'][0]['geometry']['location'];
            
            return [
                'success' => true,
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'formatted_address' => $data['results'][0]['formatted_address']
            ];
        } else {
            throw new Exception("Geocoding failed: " . ($data['error_message'] ?? $data['status']));
        }
    } catch (Exception $e) {
        error_log("Geocoding error: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'Geocoding failed'
        ];
    }
}
?>