<?php
/**
 * Production-ready Google Maps Distance Matrix API integration
 * Place this file in your includes directory as calculate-distance.php
 */

/**
 * Calculate distance and duration between two addresses with caching
 * 
 * @param string $origin Starting address or coordinates
 * @param string $destination Ending address or coordinates
 * @param bool $forceRefresh Force recalculation even if cached data exists
 * @return array Array with distance and duration data
 */
function calculateDistance($origin, $destination, $forceRefresh = false) {
    if (empty($origin) || empty($destination)) {
        return [
            'success' => false,
            'error' => 'Missing origin or destination',
            'error_code' => 'MISSING_PARAMS'
        ];
    }
    
    try {
        // Create a cache key based on origin and destination
        $cacheKey = md5($origin . '|' . $destination);
        $conn = dbConnect();

        // Check for cached results if not forcing refresh
        if (!$forceRefresh) {
            $cachedResult = getCachedDistance($cacheKey, $conn);
            if ($cachedResult) {
                // Return cached result if found and not expired
                return $cachedResult;
            }
        }

        // Get API key from config
        $apiKey = defined('GOOGLE_MAPS_SERVER_API_KEY') ? GOOGLE_MAPS_SERVER_API_KEY : GOOGLE_MAPS_API_KEY;
        if (empty($apiKey)) {
            throw new Exception('Google Maps API key not configured');
        }

        // Build parameters
        $params = [
            'origins' => $origin,
            'destinations' => $destination,
            'mode' => 'driving',
            'language' => 'en',
            'key' => $apiKey
        ];
        
        // Add user's region if defined
        if (defined('GOOGLE_MAPS_REGION') && GOOGLE_MAPS_REGION) {
            $params['region'] = GOOGLE_MAPS_REGION;
        }
        
        // Build URL with parameters
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query($params);
        
        // Log the API request for auditing
        error_log("Distance Matrix API Request: Origins={$origin}, Destinations={$destination}");

        // Make the request with proper timeouts and error handling
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10, // 10 second timeout
            CURLOPT_CONNECTTIMEOUT => 5, // 5 second connection timeout
            CURLOPT_SSL_VERIFYPEER => true, // Enable SSL verification for security
            CURLOPT_USERAGENT => 'Salaam Rides Distance Calculator',
        ]);
        
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            error_log("Google Maps API cURL Error: " . $curlError);
            
            // Log failed API call
            logApiCall($conn, 'google_distance_matrix', [
                'origins' => $origin,
                'destinations' => $destination
            ], null, false, $curlError);
            
            return [
                'success' => false,
                'error' => 'Connection error: ' . $curlError,
                'error_code' => 'CURL_ERROR'
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Process API response
        $data = json_decode($response, true);
        
        // Validate response
        if ($httpCode !== 200) {
            error_log("Google Maps API HTTP Error: " . $httpCode . ", Response: " . $response);
            
            // Log failed API call
            logApiCall($conn, 'google_distance_matrix', [
                'origins' => $origin,
                'destinations' => $destination
            ], $data, false, "HTTP Error: {$httpCode}");
            
            return [
                'success' => false,
                'error' => 'API returned HTTP status: ' . $httpCode,
                'error_code' => 'HTTP_ERROR'
            ];
        }
        
        if (!isset($data['status']) || $data['status'] !== 'OK') {
            $errorMessage = isset($data['error_message']) ? $data['error_message'] : 'Unknown API error';
            $errorStatus = isset($data['status']) ? $data['status'] : 'UNKNOWN_ERROR';
            
            error_log("Google Maps API Error: " . $errorStatus . " - " . $errorMessage);
            
            // Log failed API call
            logApiCall($conn, 'google_distance_matrix', [
                'origins' => $origin,
                'destinations' => $destination
            ], $data, false, $errorMessage);
            
            return [
                'success' => false,
                'error' => 'API error: ' . $errorMessage,
                'error_code' => $errorStatus
            ];
        }
        
        // Extract distance and duration data
        if (isset($data['rows'][0]['elements'][0]['status']) && 
            $data['rows'][0]['elements'][0]['status'] === 'OK') {
            
            $element = $data['rows'][0]['elements'][0];
            $originAddress = $data['origin_addresses'][0];
            $destinationAddress = $data['destination_addresses'][0];
            
            $result = [
                'success' => true,
                'distance' => [
                    'value' => $element['distance']['value'], // meters
                    'text' => $element['distance']['text'],
                    'km' => round($element['distance']['value'] / 1000, 2) // convert to km
                ],
                'duration' => [
                    'value' => $element['duration']['value'], // seconds
                    'text' => $element['duration']['text'],
                    'minutes' => round($element['duration']['value'] / 60) // convert to minutes
                ],
                'origin' => [
                    'input' => $origin,
                    'resolved' => $originAddress
                ],
                'destination' => [
                    'input' => $destination,
                    'resolved' => $destinationAddress
                ],
                'timestamp' => time(),
                'cache_key' => $cacheKey
            ];
            
            // Save successful result to cache
            cacheDistanceResult($cacheKey, $result, $conn);
            
            // Log successful API call
            logApiCall($conn, 'google_distance_matrix', [
                'origins' => $origin,
                'destinations' => $destination
            ], [
                'distance' => $element['distance']['value'],
                'duration' => $element['duration']['value']
            ], true);
            
            return $result;
        } else {
            $elementStatus = isset($data['rows'][0]['elements'][0]['status']) ? 
                            $data['rows'][0]['elements'][0]['status'] : 'UNKNOWN_ELEMENT_ERROR';
            
            error_log("Google Maps API Element Error: " . $elementStatus);
            
            // Log failed API call
            logApiCall($conn, 'google_distance_matrix', [
                'origins' => $origin,
                'destinations' => $destination
            ], $data, false, "Element Error: {$elementStatus}");
            
            return [
                'success' => false,
                'error' => 'No route found between these locations',
                'error_code' => $elementStatus
            ];
        }
    } catch (Exception $e) {
        error_log("Distance calculation error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Internal error: ' . $e->getMessage(),
            'error_code' => 'INTERNAL_ERROR'
        ];
    }
}

/**
 * Get cached distance calculation result if available
 * 
 * @param string $cacheKey The MD5 hash of origin and destination
 * @param mysqli $conn Database connection
 * @return array|null Cached result or null if not found or expired
 */
function getCachedDistance($cacheKey, $conn) {
    try {
        // Create cache table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS distance_cache (
            cache_key CHAR(32) PRIMARY KEY,
            origin TEXT NOT NULL,
            destination TEXT NOT NULL,
            result JSON NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        )";
        $conn->query($createTableSql);
        
        // Get cached result if not expired
        $stmt = $conn->prepare("SELECT result FROM distance_cache 
                                WHERE cache_key = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $cacheKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return json_decode($row['result'], true);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting cached distance: " . $e->getMessage());
        return null;
    }
}

/**
 * Cache distance calculation result
 * 
 * @param string $cacheKey The MD5 hash of origin and destination
 * @param array $result The result to cache
 * @param mysqli $conn Database connection
 * @param int $cacheHours How many hours to cache the result (default 24)
 * @return bool Success status
 */
function cacheDistanceResult($cacheKey, $result, $conn, $cacheHours = 24) {
    try {
        // Delete any existing cache entry
        $deleteStmt = $conn->prepare("DELETE FROM distance_cache WHERE cache_key = ?");
        $deleteStmt->bind_param("s", $cacheKey);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Insert new cache entry
        $stmt = $conn->prepare("INSERT INTO distance_cache 
                              (cache_key, origin, destination, result, created_at, expires_at) 
                              VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))");
        
        $resultJson = json_encode($result);
        $origin = $result['origin']['input'];
        $destination = $result['destination']['input'];
        
        $stmt->bind_param("ssssi", $cacheKey, $origin, $destination, $resultJson, $cacheHours);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    } catch (Exception $e) {
        error_log("Error caching distance result: " . $e->getMessage());
        return false;
    }
}

/**
 * Log API call for analytics and debugging
 * 
 * @param mysqli $conn Database connection
 * @param string $apiName Name of the API called
 * @param array $requestData Request data
 * @param array|null $responseData Response data
 * @param bool $success Whether the call was successful
 * @param string $errorMessage Error message if unsuccessful
 * @return int|bool The ID of the inserted log entry or false on failure
 */
function logApiCall($conn, $apiName, $requestData, $responseData, $success, $errorMessage = null) {
    try {
        // Create api_logs table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_name VARCHAR(100) NOT NULL,
            request_data JSON,
            response_data JSON,
            success TINYINT(1) NOT NULL DEFAULT 0,
            error_message TEXT,
            created_at DATETIME NOT NULL,
            INDEX idx_api_name (api_name),
            INDEX idx_created_at (created_at),
            INDEX idx_success (success)
        )";
        $conn->query($createTableSql);
        
        // Insert log entry
        $stmt = $conn->prepare("INSERT INTO api_logs 
                              (api_name, request_data, response_data, success, error_message, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())");
        
        $requestJson = json_encode($requestData);
        $responseJson = $responseData ? json_encode($responseData) : null;
        $successInt = $success ? 1 : 0;
        
        $stmt->bind_param("sssss", $apiName, $requestJson, $responseJson, $successInt, $errorMessage);
        $result = $stmt->execute();
        $id = $result ? $conn->insert_id : false;
        $stmt->close();
        
        return $id;
    } catch (Exception $e) {
        error_log("Error logging API call: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate fare based on distance and vehicle type
 * Production-ready implementation with dynamic rate fetching from database
 * 
 * @param float $distance Distance in kilometers
 * @param string $vehicleType Type of vehicle (standard, suv, premium)
 * @param mysqli|null $conn Optional database connection
 * @return array Array with fare details
 */
function calculateFare($distance, $vehicleType = 'standard', $conn = null) {
    // Default fare structure if database fetch fails
    $defaultRates = [
        'standard' => [
            'base_rate' => 1000, // G$10.00
            'price_per_km' => 200, // G$2.00 per km
            'minimum_fare' => 1500, // G$15.00
            'multiplier' => 1.0
        ],
        'suv' => [
            'base_rate' => 1500, // G$15.00
            'price_per_km' => 300, // G$3.00 per km
            'minimum_fare' => 2000, // G$20.00
            'multiplier' => 1.5
        ],
        'premium' => [
            'base_rate' => 2000, // G$20.00
            'price_per_km' => 400, // G$4.00 per km
            'minimum_fare' => 2500, // G$25.00
            'multiplier' => 2.0
        ]
    ];
    
    // Normalize vehicle type and provide fallback
    $type = strtolower($vehicleType);
    if (!in_array($type, ['standard', 'suv', 'premium'])) {
        $type = 'standard'; // Default to standard
    }
    
    // Use default rates initially
    $rates = $defaultRates[$type];
    
    // Attempt to fetch rates from database if connection provided
    if ($conn) {
        try {
            // First, ensure the fare_rates table exists
            $createTableSql = "CREATE TABLE IF NOT EXISTS fare_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_type VARCHAR(50) NOT NULL,
                base_rate INT NOT NULL,
                price_per_km INT NOT NULL,
                minimum_fare INT NOT NULL,
                multiplier DECIMAL(3,1) NOT NULL DEFAULT 1.0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY idx_vehicle_type (vehicle_type)
            )";
            $conn->query($createTableSql);
            
            // Try to get current rates from database
            $stmt = $conn->prepare("SELECT base_rate, price_per_km, minimum_fare, multiplier 
                                   FROM fare_rates 
                                   WHERE vehicle_type = ? AND active = 1");
            $stmt->bind_param("s", $type);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Use rates from database
                $dbRates = $result->fetch_assoc();
                $rates = [
                    'base_rate' => (int)$dbRates['base_rate'],
                    'price_per_km' => (int)$dbRates['price_per_km'],
                    'minimum_fare' => (int)$dbRates['minimum_fare'],
                    'multiplier' => (float)$dbRates['multiplier']
                ];
            } else {
                // If no rates found in database, insert the default ones for future use
                $insertStmt = $conn->prepare("INSERT INTO fare_rates 
                                            (vehicle_type, base_rate, price_per_km, minimum_fare, multiplier, active, created_at) 
                                            VALUES (?, ?, ?, ?, ?, 1, NOW())
                                            ON DUPLICATE KEY UPDATE
                                            base_rate = VALUES(base_rate),
                                            price_per_km = VALUES(price_per_km),
                                            minimum_fare = VALUES(minimum_fare),
                                            multiplier = VALUES(multiplier),
                                            active = 1,
                                            updated_at = NOW()");
                
                $insertStmt->bind_param("siidi", 
                    $type,
                    $defaultRates[$type]['base_rate'],
                    $defaultRates[$type]['price_per_km'],
                    $defaultRates[$type]['minimum_fare'],
                    $defaultRates[$type]['multiplier']
                );
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error fetching fare rates: " . $e->getMessage());
            // Continue with default rates on error
        }
    }
    
    // Calculate fare components
    $baseFare = $rates['base_rate'];
    $distanceFare = round($distance * $rates['price_per_km']);
    
    // Apply traffic time multiplier based on time of day
    $hour = (int)date('H');
    $trafficMultiplier = 1.0;
    
    // Rush hours: 7-9 AM and 4-6 PM
    if (($hour >= 7 && $hour <= 9) || ($hour >= 16 && $hour <= 18)) {
        $trafficMultiplier = 1.2;
    }
    
    // Calculate total fare
    $subtotal = $baseFare + $distanceFare;
    $totalFare = round($subtotal * $rates['multiplier'] * $trafficMultiplier);
    
    // Apply minimum fare if needed
    if ($totalFare < $rates['minimum_fare']) {
        $totalFare = $rates['minimum_fare'];
    }
    
    // Round to nearest 100 (common in Guyana pricing)
    $roundedFare = ceil($totalFare / 100) * 100;
    
    return [
        'base_fare' => $baseFare,
        'distance_fare' => $distanceFare,
        'subtotal' => $subtotal,
        'vehicle_multiplier' => $rates['multiplier'],
        'traffic_multiplier' => $trafficMultiplier,
        'total_fare' => $totalFare,
        'rounded_fare' => $roundedFare,
        'formatted_fare' => 'G$' . number_format($roundedFare),
        'currency' => 'GYD',
        'distance_km' => $distance,
        'vehicle_type' => $type,
        'minimum_fare' => $rates['minimum_fare']
    ];
}

/**
 * Get geolocation coordinates from address using Google Geocoding API
 * 
 * @param string $address Address to geocode
 * @return array Array containing lat and lng coordinates or error
 */
function geocodeAddress($address) {
    if (empty($address)) {
        return [
            'success' => false,
            'error' => 'Address is required',
            'error_code' => 'MISSING_ADDRESS'
        ];
    }
    
    try {
        // Get API key from config
        $apiKey = defined('GOOGLE_MAPS_SERVER_API_KEY') ? GOOGLE_MAPS_SERVER_API_KEY : GOOGLE_MAPS_API_KEY;
        if (empty($apiKey)) {
            throw new Exception('Google Maps API key not configured');
        }
        
        // Build URL with parameters
        $url = "https://maps.googleapis.com/maps/api/geocode/json?";
        $params = [
            'address' => $address,
            'key' => $apiKey
        ];
        
        // Add region if defined
        if (defined('GOOGLE_MAPS_REGION') && GOOGLE_MAPS_REGION) {
            $params['region'] = GOOGLE_MAPS_REGION;
        }
        
        $url .= http_build_query($params);
        
        // Make the request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Salaam Rides Geocoder',
        ]);
        
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            error_log("Google Geocoding API cURL Error: " . $curlError);
            
            return [
                'success' => false,
                'error' => 'Connection error: ' . $curlError,
                'error_code' => 'CURL_ERROR'
            ];
        }
        
        curl_close($ch);
        
        // Process API response
        $data = json_decode($response, true);
        
        // Validate response
        if ($data['status'] !== 'OK') {
            $errorMessage = isset($data['error_message']) ? $data['error_message'] : 'Unknown geocoding error';
            error_log("Google Geocoding API Error: " . $data['status'] . " - " . $errorMessage);
            
            return [
                'success' => false,
                'error' => 'Geocoding error: ' . $errorMessage,
                'error_code' => $data['status']
            ];
        }
        
        // Get coordinates from first result
        if (isset($data['results'][0]['geometry']['location'])) {
            $location = $data['results'][0]['geometry']['location'];
            $formattedAddress = $data['results'][0]['formatted_address'];
            
            return [
                'success' => true,
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'formatted_address' => $formattedAddress,
                'input_address' => $address
            ];
        } else {
            return [
                'success' => false,
                'error' => 'No location found for this address',
                'error_code' => 'NO_RESULTS'
            ];
        }
    } catch (Exception $e) {
        error_log("Geocoding error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Internal error: ' . $e->getMessage(),
            'error_code' => 'INTERNAL_ERROR'
        ];
    }
}

/**
 * Cleanup old cached distance calculations
 * Should be called periodically via cron job
 * 
 * @param mysqli $conn Database connection
 * @param int $olderThanDays Delete cache entries older than this many days
 * @return int Number of records deleted
 */
function cleanupDistanceCache($conn, $olderThanDays = 30) {
    try {
        $stmt = $conn->prepare("DELETE FROM distance_cache WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $olderThanDays);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        return $deletedCount;
    } catch (Exception $e) {
        error_log("Error cleaning up distance cache: " . $e->getMessage());
        return 0;
    }
}
?>