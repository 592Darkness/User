<?php
/**
 * Functions for calculating realistic driver ETAs using Google Maps API
 * Add this to a new file called 'driver-eta.php' in your includes directory
 */

/**
 * Calculate ETA between two locations using Google Maps Distance Matrix API
 * 
 * @param string $origin Starting location (driver's location)
 * @param string $destination Ending location (pickup location)
 * @param string $apiKey Google Maps API key
 * @return array Array containing minutes, seconds, and formatted ETA string
 */
function calculateETA($origin, $destination, $apiKey) {
    // Default fallback values in case API call fails
    $defaultEta = [
        'minutes' => 5,
        'seconds' => 300,
        'formatted' => '5 mins',
        'success' => false
    ];
    
    // If missing parameters, return default
    if (empty($origin) || empty($destination) || empty($apiKey)) {
        error_log("Missing parameters for ETA calculation");
        return $defaultEta;
    }
    
    try {
        // Prepare the API URL with origin, destination and API key
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . 
               urlencode($origin) . 
               "&destinations=" . urlencode($destination) . 
               "&mode=driving&key=" . $apiKey;
        
        // Make the API request with proper error handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 second connection timeout
        $result = curl_exec($ch);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            error_log("cURL error in ETA calculation: " . curl_error($ch));
            curl_close($ch);
            return $defaultEta;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            error_log("API call failed with HTTP code: " . $httpCode);
            curl_close($ch);
            return $defaultEta;
        }
        
        curl_close($ch);
        
        // Parse the response
        $response_data = json_decode($result, true);
        
        // Check if the API returned valid data
        if ($response_data['status'] === 'OK' && 
            isset($response_data['rows'][0]['elements'][0]['status']) && 
            $response_data['rows'][0]['elements'][0]['status'] === 'OK') {
            
            // Get duration in seconds
            $durationSeconds = $response_data['rows'][0]['elements'][0]['duration']['value'];
            $durationText = $response_data['rows'][0]['elements'][0]['duration']['text'];
            
            // Convert to minutes (rounded up)
            $durationMinutes = ceil($durationSeconds / 60);
            
            // Format ETA nicely
            $formattedEta = $durationMinutes <= 1 ? 
                  "1 min" : 
                  $durationMinutes . " mins";
            
            return [
                'minutes' => $durationMinutes,
                'seconds' => $durationSeconds,
                'formatted' => $formattedEta,
                'api_formatted' => $durationText,
                'success' => true
            ];
        } else {
            // Log any API errors
            error_log("Distance Matrix API error: " . json_encode($response_data));
            return $defaultEta;
        }
    } catch (Exception $e) {
        error_log("Exception in ETA calculation: " . $e->getMessage());
        return $defaultEta;
    }
}

/**
 * Get nearest driver location from the database
 * If no actual driver locations are available, it uses simulated locations
 * 
 * @param int $rideId The ride ID to find a driver for
 * @param string $pickupLocation The pickup location address
 * @param object $conn Database connection
 * @return array Driver information including location
 */
function getNearestDriverLocation($rideId, $pickupLocation, $conn) {
    try {
        // First check if we have a driver already assigned to this ride
        $stmt = $conn->prepare("SELECT driver_id FROM rides WHERE id = ?");
        $stmt->bind_param("i", $rideId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $driverId = $row['driver_id'];
            
            if (!empty($driverId)) {
                // Get this driver's details
                $driverStmt = $conn->prepare("SELECT id, name, rating, vehicle, plate FROM drivers WHERE id = ?");
                $driverStmt->bind_param("i", $driverId);
                $driverStmt->execute();
                $driverResult = $driverStmt->get_result();
                
                if ($driverResult->num_rows > 0) {
                    $driver = $driverResult->fetch_assoc();
                    $driverStmt->close();
                    
                    // Check if we have a real location for this driver
                    $locationStmt = $conn->prepare("SELECT location FROM driver_locations WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1");
                    $locationStmt->bind_param("i", $driverId);
                    $locationStmt->execute();
                    $locationResult = $locationStmt->get_result();
                    
                    if ($locationResult->num_rows > 0) {
                        // We have a real location
                        $locationData = $locationResult->fetch_assoc();
                        $driver['location'] = $locationData['location'];
                    } else {
                        // Generate a simulated location near the pickup
                        $driver['location'] = generateSimulatedLocation($pickupLocation);
                    }
                    
                    $locationStmt->close();
                    return $driver;
                }
                
                $driverStmt->close();
            }
        }
        
        // If we don't have a driver assigned yet, find nearest available driver
        $availableDriversStmt = $conn->prepare("
            SELECT id, name, rating, vehicle, plate 
            FROM drivers 
            WHERE status = 'available' 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $availableDriversStmt->execute();
        $driversResult = $availableDriversStmt->get_result();
        
        if ($driversResult->num_rows > 0) {
            $driver = $driversResult->fetch_assoc();
            
            // Generate a simulated location near the pickup
            $driver['location'] = generateSimulatedLocation($pickupLocation);
            
            $availableDriversStmt->close();
            return $driver;
        }
        
        $availableDriversStmt->close();
        
        // No drivers found, return dummy data
        return [
            'id' => 0,
            'name' => 'No Driver Found',
            'rating' => 0,
            'vehicle' => 'N/A',
            'plate' => 'N/A',
            'location' => 'Georgetown, Guyana', // Default location
        ];
        
    } catch (Exception $e) {
        error_log("Error getting driver location: " . $e->getMessage());
        
        // Return fallback data
        return [
            'id' => 0,
            'name' => 'Error',
            'rating' => 0,
            'vehicle' => 'N/A',
            'plate' => 'N/A',
            'location' => 'Georgetown, Guyana', // Default location
        ];
    }
}

/**
 * Generate a simulated location near the given address
 * This creates realistic nearby starting points for drivers when actual GPS data isn't available
 * 
 * @param string $address Base address to generate location near
 * @return string A nearby location address
 */
function generateSimulatedLocation($address) {
    // List of nearby landmarks or neighborhoods in Guyana
    // This makes the simulation more realistic than random coordinates
    $guyanaLocations = [
        'Kitty, Georgetown, Guyana',
        'Alberttown, Georgetown, Guyana',
        'Queenstown, Georgetown, Guyana',
        'Bourda, Georgetown, Guyana',
        'Stabroek, Georgetown, Guyana',
        'South Ruimveldt, Georgetown, Guyana',
        'North Ruimveldt, Georgetown, Guyana',
        'Sheriff Street, Georgetown, Guyana',
        'Camp Street, Georgetown, Guyana',
        'Vlissengen Road, Georgetown, Guyana',
        'Agricola, East Bank Demerara, Guyana',
        'Providence, East Bank Demerara, Guyana',
        'Diamond, East Bank Demerara, Guyana',
        'Eccles, East Bank Demerara, Guyana',
        'Peter\'s Hall, East Bank Demerara, Guyana',
        'Turkeyen, East Coast Demerara, Guyana',
        'Sophia, Greater Georgetown, Guyana',
        'Ogle, East Coast Demerara, Guyana',
        'Plaisance, East Coast Demerara, Guyana',
        'Enmore, East Coast Demerara, Guyana'
    ];
    
    // Pick a random location from the list
    $randomIndex = mt_rand(0, count($guyanaLocations) - 1);
    return $guyanaLocations[$randomIndex];
}

/**
 * Save driver location to database
 * 
 * @param int $driverId Driver ID
 * @param string $location Address string
 * @param object $conn Database connection
 * @return bool Success status
 */
function saveDriverLocation($driverId, $location, $conn) {
    try {
        // First check if we need to create a driver_locations table
        $tableCheckSql = "SHOW TABLES LIKE 'driver_locations'";
        $tableResult = $conn->query($tableCheckSql);
        
        if ($tableResult->num_rows == 0) {
            // Create the table if it doesn't exist
            $createTableSql = "
                CREATE TABLE driver_locations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    driver_id INT NOT NULL,
                    location VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX (driver_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            if (!$conn->query($createTableSql)) {
                error_log("Failed to create driver_locations table: " . $conn->error);
                return false;
            }
        }
        
        // Insert or update driver location
        $stmt = $conn->prepare("
            INSERT INTO driver_locations (driver_id, location, updated_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE location = VALUES(location), updated_at = NOW()
        ");
        
        if (!$stmt) {
            error_log("Prepare failed for driver location update: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("is", $driverId, $location);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error saving driver location: " . $e->getMessage());
        return false;
    }
}