<?php
/**
 * Production-ready Google Maps Distance Matrix API integration
 * Place this file in your includes directory as calculate-distance.php
 * FIXED: Correctly handles API key definition and checks.
 */

/**
 * Calculate distance and duration between two addresses with caching
 *
 * @param string $origin Starting address or coordinates
 * @param string $destination Ending address or coordinates
 * @param bool $forceRefresh Force recalculation even if cached data exists
 * @return array Array with distance and duration data, or error information.
 * Success structure: ['success' => true, 'distance' => [...], 'duration' => [...], ...]
 * Error structure: ['success' => false, 'error' => '...', 'error_code' => '...']
 */
function calculateDistance($origin, $destination, $forceRefresh = false) {
    if (empty($origin) || empty($destination)) {
        return [
            'success' => false,
            'error' => 'Missing origin or destination address.',
            'error_code' => 'MISSING_PARAMS'
        ];
    }

    $conn = null; // Initialize connection variable

    try {
        // Create a cache key based on origin and destination
        $cacheKey = md5($origin . '|' . $destination);
        $conn = dbConnect(); // Get DB connection

        // Check for cached results if not forcing refresh
        if (!$forceRefresh) {
            $cachedResult = getCachedDistance($cacheKey, $conn);
            if ($cachedResult) {
                error_log("Using cached distance result for key: $cacheKey");
                // Return cached result if found and not expired
                return $cachedResult;
            }
        }

        // --- FIX: Correctly get and check for API key ---
        $apiKey = null;
        // Prioritize the server-specific key if defined and not empty
        if (defined('Maps_SERVER_API_KEY') && !empty(Maps_SERVER_API_KEY)) {
            $apiKey = Maps_SERVER_API_KEY;
            error_log("Using Maps_SERVER_API_KEY for distance calculation.");
        }
        // Fallback to the general key if server key isn't defined or empty
        elseif (defined('Maps_API_KEY') && !empty(Maps_API_KEY)) {
            $apiKey = Maps_API_KEY;
            error_log("Using fallback Maps_API_KEY for distance calculation.");
        }

        // Throw an exception if NO key is defined or found - This will be caught below
        if (empty($apiKey)) {
            throw new Exception('Google Maps API key is not configured correctly in config.php. Please define Maps_SERVER_API_KEY or Maps_API_KEY.');
        }
        // --- END FIX ---

        // Build parameters using the determined $apiKey
        $params = [
            'origins' => $origin,
            'destinations' => $destination,
            'mode' => 'driving',
            'language' => 'en', // Or configure as needed
            'key' => $apiKey // Use the verified $apiKey variable
        ];

        // Add user's region if defined in config
        if (defined('Maps_REGION') && !empty(Maps_REGION)) {
            $params['region'] = Maps_REGION;
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
            CURLOPT_USERAGENT => 'Salaam Rides Distance Calculator/' . ($_SERVER['HTTP_HOST'] ?? 'UnknownHost'), // Identify your app
        ]);

        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            error_log("Google Maps API cURL Error: " . $curlError);
            // Log failed API call
            if ($conn) logApiCall($conn, 'google_distance_matrix', $params, null, false, "cURL Error: " . $curlError);
            // Return structured error
            return [
                'success' => false,
                'error' => 'Could not connect to distance service. Please try again later.',
                'error_code' => 'CURL_ERROR'
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Process API response
        $data = json_decode($response, true);

        // Validate response status and content
        $apiErrorMessage = null;
        $apiErrorCode = 'UNKNOWN_API_ERROR';
        $logResponseData = $data; // Log the received data for debugging

        if ($httpCode !== 200) {
            $apiErrorMessage = 'API returned HTTP status: ' . $httpCode;
            $apiErrorCode = 'HTTP_ERROR_' . $httpCode;
        } elseif (!isset($data['status']) || $data['status'] !== 'OK') {
            $apiErrorMessage = $data['error_message'] ?? ('API Error: ' . ($data['status'] ?? 'Unknown'));
            $apiErrorCode = $data['status'] ?? 'UNKNOWN_API_ERROR';
        } elseif (!isset($data['rows'][0]['elements'][0]['status']) || $data['rows'][0]['elements'][0]['status'] !== 'OK') {
            $apiErrorMessage = 'No route found between these locations (' . ($data['rows'][0]['elements'][0]['status'] ?? 'UNKNOWN_ELEMENT_ERROR') . ')';
            $apiErrorCode = $data['rows'][0]['elements'][0]['status'] ?? 'ELEMENT_ERROR';
        }

        // If there was an API error
        if ($apiErrorMessage !== null) {
            error_log("Google Maps API Error: [$apiErrorCode] $apiErrorMessage | Response: " . $response);
            // Log failed API call
            if ($conn) logApiCall($conn, 'google_distance_matrix', $params, $logResponseData, false, "[$apiErrorCode] $apiErrorMessage");
            // Return structured error
            return [
                'success' => false,
                'error' => $apiErrorMessage,
                'error_code' => $apiErrorCode
            ];
        }

        // --- Success Case ---
        $element = $data['rows'][0]['elements'][0];
        $originAddress = $data['origin_addresses'][0] ?? $origin; // Use resolved address or fallback
        $destinationAddress = $data['destination_addresses'][0] ?? $destination; // Use resolved address or fallback

        $result = [
            'success' => true,
            'distance' => [
                'value' => $element['distance']['value'] ?? 0, // meters
                'text' => $element['distance']['text'] ?? 'N/A',
                'km' => isset($element['distance']['value']) ? round($element['distance']['value'] / 1000, 2) : 0 // convert to km
            ],
            'duration' => [
                'value' => $element['duration']['value'] ?? 0, // seconds
                'text' => $element['duration']['text'] ?? 'N/A',
                'minutes' => isset($element['duration']['value']) ? round($element['duration']['value'] / 60) : 0 // convert to minutes
            ],
            'origin' => [
                'input' => $origin,
                'resolved' => $originAddress
            ],
            'destination' => [
                'input' => $destination,
                'resolved' => $destinationAddress
            ],
            'timestamp' => time(), // Current timestamp
            'cache_key' => $cacheKey,
            'source' => 'api' // Indicate it came from the API, not cache
        ];

        // Save successful result to cache
        if ($conn) {
            cacheDistanceResult($cacheKey, $result, $conn);
            logApiCall($conn, 'google_distance_matrix', $params, [
                'distance' => $result['distance']['value'],
                'duration' => $result['duration']['value']
            ], true);
        }

        return $result;

    } catch (Exception $e) {
        error_log("Distance calculation internal error: " . $e->getMessage());
        // Return structured error
        return [
            'success' => false,
            'error' => 'An internal error occurred while calculating distance.',
            'error_code' => 'INTERNAL_ERROR',
            'debug_message' => $e->getMessage() // Include specific message for debugging logs if needed
        ];
    } finally {
        // Ensure database connection is closed if it was opened
        if ($conn) {
            $conn->close();
        }
    }
}

/**
 * Get cached distance calculation result if available and valid
 *
 * @param string $cacheKey The MD5 hash of origin and destination
 * @param mysqli $conn Database connection
 * @return array|null Cached result or null if not found or expired
 */
function getCachedDistance($cacheKey, $conn) {
    try {
        // Create cache table if it doesn't exist (consider doing this in a migration script)
        $createTableSql = "CREATE TABLE IF NOT EXISTS distance_cache (
            cache_key CHAR(32) PRIMARY KEY,
            origin TEXT NOT NULL,
            destination TEXT NOT NULL,
            result JSON NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        )";
        // Use query() which might be safer than directly executing potentially complex DDL
        if (!$conn->query($createTableSql)) {
             error_log("Error ensuring distance_cache table exists: " . $conn->error);
             return null; // Cannot use cache if table might not exist
        }


        // Get cached result if not expired
        $stmt = $conn->prepare("SELECT result FROM distance_cache
                                WHERE cache_key = ? AND expires_at > NOW()");
        if (!$stmt) {
             error_log("Error preparing cache select query: " . $conn->error);
             return null;
        }

        $stmt->bind_param("s", $cacheKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cachedData = json_decode($row['result'], true);
            // Add source indication
            if (is_array($cachedData)) {
                $cachedData['source'] = 'cache';
                $cachedData['cache_key'] = $cacheKey; // Ensure key is present
                return $cachedData;
            }
        }

        return null;
    } catch (Exception $e) {
        error_log("Error getting cached distance: " . $e->getMessage());
        return null;
    }
}

/**
 * Cache distance calculation result in the database
 *
 * @param string $cacheKey The MD5 hash of origin and destination
 * @param array $result The result to cache
 * @param mysqli $conn Database connection
 * @param int $cacheHours How many hours to cache the result (default 24)
 * @return bool Success status
 */
function cacheDistanceResult($cacheKey, $result, $conn, $cacheHours = 24) {
    try {
        // Use INSERT ... ON DUPLICATE KEY UPDATE for atomicity
        $stmt = $conn->prepare("INSERT INTO distance_cache
                              (cache_key, origin, destination, result, created_at, expires_at)
                              VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))
                              ON DUPLICATE KEY UPDATE
                              origin = VALUES(origin),
                              destination = VALUES(destination),
                              result = VALUES(result),
                              created_at = VALUES(created_at),
                              expires_at = VALUES(expires_at)");

        if (!$stmt) {
             error_log("Error preparing cache insert/update query: " . $conn->error);
             return false;
        }

        $resultJson = json_encode($result); // Encode the full result
        $origin = $result['origin']['input'];
        $destination = $result['destination']['input'];

        $stmt->bind_param("ssssi", $cacheKey, $origin, $destination, $resultJson, $cacheHours);
        $success = $stmt->execute();
        if (!$success) {
             error_log("Error executing cache insert/update: " . $stmt->error);
        }
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
 * @param string|null $errorMessage Error message if unsuccessful
 * @return int|bool The ID of the inserted log entry or false on failure
 */
function logApiCall($conn, $apiName, $requestData, $responseData, $success, $errorMessage = null) {
    try {
        // Create api_logs table if it doesn't exist (consider migrations)
        $createTableSql = "CREATE TABLE IF NOT EXISTS api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_name VARCHAR(100) NOT NULL,
            request_data JSON,
            response_data JSON,
            success TINYINT(1) NOT NULL DEFAULT 0,
            error_message TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, /* Added default */
            INDEX idx_api_name (api_name),
            INDEX idx_created_at (created_at),
            INDEX idx_success (success)
        )";
         if (!$conn->query($createTableSql)) {
             error_log("Error ensuring api_logs table exists: " . $conn->error);
             // Continue, but logging might fail
         }

        // Insert log entry
        $stmt = $conn->prepare("INSERT INTO api_logs
                              (api_name, request_data, response_data, success, error_message, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");

         if (!$stmt) {
             error_log("Error preparing API log statement: " . $conn->error);
             return false;
         }

        $requestJson = json_encode($requestData);
        // Handle potential null response data gracefully
        $responseJson = ($responseData !== null) ? json_encode($responseData) : null;
        $successInt = $success ? 1 : 0;

        // Ensure error message is not null if not provided
        $errorMessage = $errorMessage ?? '';

        // Use 'b' type for JSON/TEXT if driver supports it, otherwise 's'
        // Assuming 's' is generally safe here. Adjust if needed.
        $stmt->bind_param("sssis", $apiName, $requestJson, $responseJson, $successInt, $errorMessage);
        $result = $stmt->execute();
        $id = $result ? $conn->insert_id : false;
        if (!$result) {
             error_log("Error executing API log statement: " . $stmt->error);
        }
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
 * @param mysqli|null $conn Optional database connection (required for DB rates)
 * @return array Array with fare details
 */
function calculateFare($distance, $vehicleType = 'standard', $conn = null) {
    // Default fare structure if database fetch fails (Units: G$, amounts are in cents/smallest unit)
    $defaultRates = [
        'standard' => ['base_rate' => 1000, 'price_per_km' => 200, 'minimum_fare' => 1500, 'multiplier' => 1.0],
        'suv'      => ['base_rate' => 1500, 'price_per_km' => 300, 'minimum_fare' => 2000, 'multiplier' => 1.5],
        'premium'  => ['base_rate' => 2000, 'price_per_km' => 400, 'minimum_fare' => 2500, 'multiplier' => 2.0]
    ];

    // Normalize vehicle type and provide fallback
    $type = strtolower($vehicleType);
    if (!array_key_exists($type, $defaultRates)) {
        error_log("Invalid vehicle type '$vehicleType' passed to calculateFare. Defaulting to 'standard'.");
        $type = 'standard';
    }

    // Use default rates initially
    $rates = $defaultRates[$type];
    $source = 'default';

    // Attempt to fetch rates from database if connection provided
    if ($conn) {
        try {
            // Ensure the fare_rates table exists (Consider migrations)
            $createTableSql = "CREATE TABLE IF NOT EXISTS fare_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_type VARCHAR(50) NOT NULL UNIQUE,
                base_rate INT NOT NULL COMMENT 'In cents/smallest unit',
                price_per_km INT NOT NULL COMMENT 'In cents/smallest unit',
                minimum_fare INT NOT NULL COMMENT 'In cents/smallest unit',
                multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00, /* Increased precision */
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            )";
             if (!$conn->query($createTableSql)) {
                  error_log("Error ensuring fare_rates table exists: " . $conn->error);
                  // Continue with default rates
             }


            // Get current active rates from database
            $stmt = $conn->prepare("SELECT base_rate, price_per_km, minimum_fare, multiplier
                                   FROM fare_rates
                                   WHERE vehicle_type = ? AND active = 1");
            if (!$stmt) {
                 error_log("Error preparing fare rate query: " . $conn->error);
                 // Continue with default rates
            } else {
                $stmt->bind_param("s", $type);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // Use rates from database
                    $dbRates = $result->fetch_assoc();
                    $rates = [
                        'base_rate'    => (int)$dbRates['base_rate'],
                        'price_per_km' => (int)$dbRates['price_per_km'],
                        'minimum_fare' => (int)$dbRates['minimum_fare'],
                        'multiplier'   => (float)$dbRates['multiplier']
                    ];
                    $source = 'database';
                    error_log("Using fare rates from database for type: $type");
                } else {
                    // If no rates found in DB, log it and maybe insert defaults (optional)
                    error_log("No active fare rates found in database for type: $type. Using defaults.");
                    // Consider inserting defaults here if desired
                    /*
                    $insertStmt = $conn->prepare("INSERT INTO fare_rates (vehicle_type, base_rate, price_per_km, minimum_fare, multiplier, active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE active=1");
                    if ($insertStmt) {
                        $insertStmt->bind_param("siidd", $type, $defaultRates[$type]['base_rate'], $defaultRates[$type]['price_per_km'], $defaultRates[$type]['minimum_fare'], $defaultRates[$type]['multiplier']);
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                    */
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching fare rates from DB: " . $e->getMessage() . ". Using defaults.");
            // Continue with default rates on any DB error
            $rates = $defaultRates[$type]; // Ensure defaults are used
            $source = 'default_after_db_error';
        }
    } else {
         error_log("No database connection provided to calculateFare. Using defaults.");
         $source = 'default_no_db_conn';
    }

    // --- Fare Calculation Logic ---
    $baseFare = $rates['base_rate'];
    $distanceFare = round($distance * $rates['price_per_km']); // Round distance component

    // Apply traffic time multiplier based on time of day (optional)
    $hour = (int)date('H'); // Get current hour (server time)
    $trafficMultiplier = 1.0;
    // Example: Rush hours: 7-9 AM and 4-6 PM (adjust as needed)
    if (($hour >= 7 && $hour <= 9) || ($hour >= 16 && $hour <= 18)) {
        $trafficMultiplier = 1.2; // Example: 20% surcharge during rush hour
    }

    // Calculate total fare before minimum
    $subtotal = $baseFare + $distanceFare;
    $totalFare = round($subtotal * $rates['multiplier'] * $trafficMultiplier); // Apply multipliers and round

    // Apply minimum fare
    $finalFare = max($totalFare, $rates['minimum_fare']); // Ensure fare is at least the minimum

    // Round final fare to nearest 100 (if applicable, common in GYD)
    $roundedFare = ceil($finalFare / 100) * 100; // Use ceil to round up

    return [
        'success'           => true, // Indicate success
        'base_fare'         => $baseFare,
        'distance_fare'     => $distanceFare,
        'subtotal'          => $subtotal,
        'vehicle_multiplier'=> $rates['multiplier'],
        'traffic_multiplier'=> $trafficMultiplier,
        'calculated_total'  => $totalFare, // Fare before minimum applied
        'minimum_fare'      => $rates['minimum_fare'],
        'final_fare'        => $finalFare, // Fare after minimum applied (before rounding)
        'rounded_fare'      => $roundedFare, // Final rounded fare (in cents/smallest unit)
        // Format for display (dividing by 100 assuming cents)
        'formatted_fare'    => 'G$' . number_format($roundedFare / 100, 2),
        'currency'          => 'GYD',
        'distance_km'       => $distance,
        'vehicle_type'      => $type,
        'rates_source'      => $source // Indicate where rates came from
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
        return ['success' => false, 'error' => 'Address is required', 'error_code' => 'MISSING_ADDRESS'];
    }

    $conn = null; // Initialize $conn

    try {
        // Get API key
        $apiKey = null;
        if (defined('Maps_SERVER_API_KEY') && !empty(Maps_SERVER_API_KEY)) {
            $apiKey = Maps_SERVER_API_KEY;
        } elseif (defined('Maps_API_KEY') && !empty(Maps_API_KEY)) {
            $apiKey = Maps_API_KEY;
        }
        if (empty($apiKey)) {
            throw new Exception('Google Geocoding API key not configured.');
        }

        // Build URL
        $params = ['address' => $address, 'key' => $apiKey];
        if (defined('Maps_REGION') && !empty(Maps_REGION)) {
            $params['region'] = Maps_REGION;
        }
        $url = "https://maps.googleapis.com/maps/api/geocode/json?" . http_build_query($params);

        // Make the request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Salaam Rides Geocoder/' . ($_SERVER['HTTP_HOST'] ?? 'UnknownHost'),
        ]);
        $response = curl_exec($ch);

        // Check cURL error
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            throw new Exception('Connection error: ' . $curlError);
        }
        curl_close($ch);

        // Process response
        $data = json_decode($response, true);

        // Log the API call
        try {
            $conn = dbConnect(); // Connect only if needed for logging
            if ($conn) {
                logApiCall(
                    $conn,
                    'google_geocoding',
                    $params, // Log request params (excluding key for security if needed)
                    $data,
                    ($data['status'] === 'OK'),
                    ($data['status'] !== 'OK' ? ($data['error_message'] ?? $data['status']) : null)
                );
            }
        } catch (Exception $logEx) {
             error_log("Geocoding Log Error: " . $logEx->getMessage());
             // Don't let logging failure stop the main function
        }


        // Validate API response status
        if ($data['status'] !== 'OK') {
            throw new Exception('Geocoding API Error: ' . ($data['error_message'] ?? $data['status']));
        }

        // Extract coordinates
        if (isset($data['results'][0]['geometry']['location'])) {
            $location = $data['results'][0]['geometry']['location'];
            $formattedAddress = $data['results'][0]['formatted_address'] ?? $address;

            return [
                'success' => true,
                'lat' => $location['lat'],
                'lng' => $location['lng'],
                'formatted_address' => $formattedAddress,
                'input_address' => $address
            ];
        } else {
            throw new Exception('No location results found for the address.');
        }
    } catch (Exception $e) {
        error_log("Geocoding Exception: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => 'GEOCODING_FAILED'
        ];
    } finally {
         if ($conn) $conn->close(); // Close connection if opened for logging
    }
}


/**
 * Cleanup old cached distance calculations
 * Should be called periodically via cron job
 *
 * @param mysqli $conn Database connection
 * @param int $olderThanDays Delete cache entries older than this many days (default 30)
 * @return int Number of records deleted or -1 on error
 */
function cleanupDistanceCache($conn, $olderThanDays = 30) {
    try {
        // Validate input
        $days = max(1, (int)$olderThanDays); // Ensure positive integer

        $stmt = $conn->prepare("DELETE FROM distance_cache WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
         if (!$stmt) {
             error_log("Error preparing cache cleanup query: " . $conn->error);
             return -1;
         }
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();

        error_log("Cleaned up $deletedCount old distance cache entries (older than $days days).");
        return $deletedCount;
    } catch (Exception $e) {
        error_log("Error cleaning up distance cache: " . $e->getMessage());
        return -1; // Indicate error
    }
}

?>