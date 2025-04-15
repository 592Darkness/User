<?php
/**
 * Enhanced API Endpoint for Driver Finding, Tracking and Ride Status
 * Production-ready implementation with accurate distance/ETA calculations from database
 */

// Include necessary files
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/calculate-distance.php';
require_once dirname(__DIR__) . '/api/driver-eta.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize response structure
$response = [
    'success' => false,
    'message' => 'Unknown error',
    'status' => 'error',
    'data' => null
];

try {
    // Check authentication
    if (!isLoggedIn()) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Authentication required';
        echo json_encode($response);
        exit;
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        $response['message'] = 'Method Not Allowed';
        echo json_encode($response);
        exit;
    }

    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate input
    $bookingId = isset($data['booking_id']) ? intval(sanitize($data['booking_id'])) : null;
    $stage = isset($data['stage']) ? intval(sanitize($data['stage'])) : 0;

    if (!$bookingId) {
        http_response_code(400); // Bad Request
        $response['message'] = 'Booking ID is required';
        echo json_encode($response);
        exit;
    }

    // Connect to database
    $conn = dbConnect();

    // Verify ride exists and belongs to current user
    $userId = $_SESSION['user_id'];
    $rideStmt = $conn->prepare("
        SELECT r.*, 
               d.id AS driver_id, 
               d.name AS driver_name, 
               d.phone AS driver_phone,
               d.rating AS driver_rating, 
               v.type AS vehicle_type, 
               v.model AS vehicle_model,
               v.plate AS vehicle_plate
        FROM rides r
        LEFT JOIN drivers d ON r.driver_id = d.id
        LEFT JOIN vehicles v ON d.vehicle_id = v.id
        WHERE r.id = ? AND r.user_id = ?
    ");
    $rideStmt->bind_param("ii", $bookingId, $userId);
    $rideStmt->execute();
    $rideResult = $rideStmt->get_result();

    if ($rideResult->num_rows === 0) {
        http_response_code(404); // Not Found
        $response['message'] = 'Ride not found';
        $response['status'] = 'error';
        echo json_encode($response);
        exit;
    }

    $ride = $rideResult->fetch_assoc();
    $rideStmt->close();

    // Processing based on stage
    switch($stage) {
        case 0: // Initial searching stage
            // Return immediately with searching status
            $response['success'] = true;
            $response['status'] = 'searching';
            $response['message'] = 'Searching for nearby drivers...';
            $response['data'] = [
                'next_stage' => 1,
                'waiting_time' => 5, // Wait 5 seconds before next poll
                'driver' => null
            ];
            break;

        case 1: // Driver assignment stage
            // Check if a driver is already assigned
            if (!empty($ride['driver_id'])) {
                // Get driver's current location
                $driverLocation = null;
                $driverInfo = getDriverLocation($ride['driver_id'], $conn);
                
                if ($driverInfo['success'] && isset($driverInfo['driver']['location'])) {
                    if (!empty($driverInfo['driver']['location']['address'])) {
                        $driverLocation = $driverInfo['driver']['location']['address'];
                    } else if (!empty($driverInfo['driver']['location']['lat']) && !empty($driverInfo['driver']['location']['lng'])) {
                        $driverLocation = $driverInfo['driver']['location']['lat'] . ',' . $driverInfo['driver']['location']['lng'];
                    }
                }
                
                // Calculate ETA
                $etaData = [];
                
                if ($driverLocation) {
                    $etaResult = calculateDriverETA($driverLocation, $ride['pickup'], $conn);
                    if ($etaResult['success']) {
                        $etaData = [
                            'eta' => $etaResult['minutes'],
                            'eta_seconds' => $etaResult['seconds'],
                            'eta_text' => $etaResult['formatted']
                        ];
                    } else {
                        // If ETA calculation fails, query the database for average ETAs
                        $avgEtaStmt = $conn->prepare("
                            SELECT AVG(estimated_minutes) as avg_minutes, 
                                   AVG(estimated_seconds) as avg_seconds
                            FROM driver_eta_logs
                            WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                            LIMIT 1
                        ");
                        $avgEtaStmt->execute();
                        $avgEtaResult = $avgEtaStmt->get_result();
                        
                        if ($avgEtaResult->num_rows > 0) {
                            $avgEta = $avgEtaResult->fetch_assoc();
                            $minutes = round($avgEta['avg_minutes'] ?? 10);
                            $seconds = round($avgEta['avg_seconds'] ?? 600);
                            
                            $etaData = [
                                'eta' => $minutes,
                                'eta_seconds' => $seconds,
                                'eta_text' => $minutes . ' minutes'
                            ];
                        } else {
                            // If no average data, set default ETA
                            $etaData = [
                                'eta' => 10,
                                'eta_seconds' => 600,
                                'eta_text' => '10 minutes'
                            ];
                        }
                        $avgEtaStmt->close();
                    }
                } else {
                    // If no driver location, set default ETA
                    $etaData = [
                        'eta' => 10,
                        'eta_seconds' => 600,
                        'eta_text' => '10 minutes'
                    ];
                }
                
                // Create response with driver data and calculated ETA
                $response = [
                    'success' => true,
                    'status' => 'confirmed',
                    'message' => 'Driver found and assigned!',
                    'data' => [
                        'next_stage' => 2,
                        'waiting_time' => 15,
                        'driver' => [
                            'id' => $ride['driver_id'],
                            'name' => $ride['driver_name'] ?? 'Your Driver',
                            'phone' => $ride['driver_phone'] ?? 'N/A',
                            'rating' => $ride['driver_rating'] ?? '4.8',
                            'vehicle' => $ride['vehicle_model'] ?? 'Vehicle',
                            'vehicle_type' => $ride['vehicle_type'] ?? 'Standard',
                            'plate' => $ride['vehicle_plate'] ?? 'N/A',
                            'location' => $driverLocation ?? 'En route',
                            'eta' => $etaData['eta'],
                            'eta_seconds' => $etaData['eta_seconds'],
                            'eta_text' => $etaData['eta_text']
                        ]
                    ]
                ];
            } else {
                // No driver assigned yet - try to find a driver
                $nearestDriverResult = getNearestDriver($ride['pickup'], $ride['vehicle_type'], $conn);
                
                if ($nearestDriverResult['success']) {
                    // Found a driver - assign them to the ride
                    $assignResult = assignDriverToRide($bookingId, $nearestDriverResult['driver']['id'], $conn);
                    
                    if ($assignResult['success']) {
                        // Create response with assigned driver
                        $driver = $nearestDriverResult['driver'];
                        $response = [
                            'success' => true,
                            'status' => 'confirmed',
                            'message' => 'Driver found and assigned!',
                            'data' => [
                                'next_stage' => 2,
                                'waiting_time' => 15,
                                'driver' => [
                                    'id' => $driver['id'],
                                    'name' => $driver['name'],
                                    'phone' => $driver['phone'],
                                    'rating' => $driver['rating'],
                                    'vehicle' => $driver['vehicle'],
                                    'vehicle_type' => $driver['vehicle_type'],
                                    'plate' => $driver['plate'],
                                    'location' => $driver['location'],
                                    'eta' => $driver['eta'],
                                    'eta_seconds' => $driver['eta_seconds'],
                                    'eta_text' => $driver['eta_text']
                                ]
                            ]
                        ];
                    } else {
                        // Failed to assign driver - continue searching
                        $response = [
                            'success' => true,
                            'status' => 'searching',
                            'message' => 'Still searching for drivers...',
                            'data' => [
                                'next_stage' => 1,
                                'waiting_time' => 5
                            ]
                        ];
                    }
                } else {
                    // No driver found - continue searching
                    $response = [
                        'success' => true,
                        'status' => 'searching',
                        'message' => 'Still searching for drivers...',
                        'data' => [
                            'next_stage' => 1,
                            'waiting_time' => 5
                        ]
                    ];
                }
            }
            break;

        case 2: // Driver arriving stage
            if (!empty($ride['driver_id'])) {
                // Update ride status to "arriving" if currently "confirmed"
                if ($ride['status'] === 'confirmed') {
                    $updateStmt = $conn->prepare("
                        UPDATE rides 
                        SET status = 'arriving', updated_at = NOW() 
                        WHERE id = ? AND status = 'confirmed'
                    ");
                    $updateStmt->bind_param("i", $bookingId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    // Also log this status change
                    $logStmt = $conn->prepare("
                        INSERT INTO ride_logs 
                        (ride_id, driver_id, action, details, created_at) 
                        VALUES (?, ?, 'status_change', ?, NOW())
                    ");
                    
                    $details = json_encode([
                        'from_status' => 'confirmed',
                        'to_status' => 'arriving',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    
                    $logStmt->bind_param("iis", $bookingId, $ride['driver_id'], $details);
                    $logStmt->execute();
                    $logStmt->close();
                }
                
                // Get updated driver location
                $driverInfo = getDriverLocation($ride['driver_id'], $conn);
                $driverLocation = null;
                
                if ($driverInfo['success'] && isset($driverInfo['driver']['location'])) {
                    if (!empty($driverInfo['driver']['location']['address'])) {
                        $driverLocation = $driverInfo['driver']['location']['address'];
                    } else if (!empty($driverInfo['driver']['location']['lat']) && !empty($driverInfo['driver']['location']['lng'])) {
                        $driverLocation = $driverInfo['driver']['location']['lat'] . ',' . $driverInfo['driver']['location']['lng'];
                    }
                }
                
                // Calculate updated ETA
                $etaData = [];
                
                if ($driverLocation) {
                    $etaResult = calculateDriverETA($driverLocation, $ride['pickup'], $conn);
                    if ($etaResult['success']) {
                        $etaData = [
                            'eta' => $etaResult['minutes'],
                            'eta_seconds' => $etaResult['seconds'],
                            'eta_text' => $etaResult['formatted']
                        ];
                    } else {
                        // Set default ETA if calculation fails
                        $etaData = [
                            'eta' => 5,
                            'eta_seconds' => 300,
                            'eta_text' => '5 minutes'
                        ];
                    }
                } else {
                    // Set default ETA if no location data
                    $etaData = [
                        'eta' => 5,
                        'eta_seconds' => 300,
                        'eta_text' => '5 minutes'
                    ];
                }
                
                // Create response with updated driver location and ETA
                $response = [
                    'success' => true,
                    'status' => 'arriving',
                    'message' => 'Your driver is on the way!',
                    'data' => [
                        'next_stage' => 3,
                        'waiting_time' => 10,
                        'driver' => [
                            'id' => $ride['driver_id'],
                            'name' => $ride['driver_name'] ?? 'Your Driver',
                            'phone' => $ride['driver_phone'] ?? 'N/A',
                            'rating' => $ride['driver_rating'] ?? '4.8',
                            'vehicle' => $ride['vehicle_model'] ?? 'Vehicle',
                            'vehicle_type' => $ride['vehicle_type'] ?? 'Standard',
                            'plate' => $ride['vehicle_plate'] ?? 'N/A',
                            'location' => $driverLocation ?? 'Near pickup location',
                            'eta' => $etaData['eta'],
                            'eta_seconds' => $etaData['eta_seconds'],
                            'eta_text' => $etaData['eta_text']
                        ]
                    ]
                ];
            } else {
                // If somehow we lost the driver assignment, go back to searching
                $response = [
                    'success' => true,
                    'status' => 'searching',
                    'message' => 'Driver not found. Continuing search...',
                    'data' => [
                        'next_stage' => 1,
                        'waiting_time' => 5
                    ]
                ];
            }
            break;

        case 3: // Driver arrived stage
            // Update ride status to "arrived" if currently "arriving"
            if ($ride['status'] === 'arriving') {
                $updateStmt = $conn->prepare("
                    UPDATE rides 
                    SET status = 'arrived', updated_at = NOW() 
                    WHERE id = ? AND status = 'arriving'
                ");
                $updateStmt->bind_param("i", $bookingId);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Log this status change
                $logStmt = $conn->prepare("
                    INSERT INTO ride_logs 
                    (ride_id, driver_id, action, details, created_at) 
                    VALUES (?, ?, 'status_change', ?, NOW())
                ");
                
                $details = json_encode([
                    'from_status' => 'arriving',
                    'to_status' => 'arrived',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $logStmt->bind_param("iis", $bookingId, $ride['driver_id'], $details);
                $logStmt->execute();
                $logStmt->close();
            }
            
            $response = [
                'success' => true,
                'status' => 'arrived',
                'message' => 'Your driver has arrived!',
                'data' => [
                    'next_stage' => 4,
                    'waiting_time' => 15,
                    'driver' => [
                        'id' => $ride['driver_id'],
                        'name' => $ride['driver_name'] ?? 'Your Driver',
                        'phone' => $ride['driver_phone'] ?? 'N/A',
                        'rating' => $ride['driver_rating'] ?? '4.8',
                        'vehicle' => $ride['vehicle_model'] ?? 'Vehicle',
                        'vehicle_type' => $ride['vehicle_type'] ?? 'Standard',
                        'plate' => $ride['vehicle_plate'] ?? 'N/A',
                        'message' => 'Your driver has arrived at the pickup location.'
                    ]
                ]
            ];
            break;

        case 4: // Ride in progress
            // Update ride status to "in_progress" if currently "arrived"
            if ($ride['status'] === 'arrived') {
                $updateStmt = $conn->prepare("
                    UPDATE rides 
                    SET status = 'in_progress', updated_at = NOW() 
                    WHERE id = ? AND status = 'arrived'
                ");
                $updateStmt->bind_param("i", $bookingId);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Log this status change
                $logStmt = $conn->prepare("
                    INSERT INTO ride_logs 
                    (ride_id, driver_id, action, details, created_at) 
                    VALUES (?, ?, 'status_change', ?, NOW())
                ");
                
                $details = json_encode([
                    'from_status' => 'arrived',
                    'to_status' => 'in_progress',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $logStmt->bind_param("iis", $bookingId, $ride['driver_id'], $details);
                $logStmt->execute();
                $logStmt->close();
            }
            
            // Calculate estimated arrival time
            $estimatedArrivalTime = date('g:i A', strtotime('+20 minutes')); // Default fallback
            
            // Get driver location
            $driverInfo = getDriverLocation($ride['driver_id'], $conn);
            if ($driverInfo['success'] && isset($driverInfo['driver']['location'])) {
                $driverLocation = null;
                
                if (!empty($driverInfo['driver']['location']['address'])) {
                    $driverLocation = $driverInfo['driver']['location']['address'];
                } else if (!empty($driverInfo['driver']['location']['lat']) && !empty($driverInfo['driver']['location']['lng'])) {
                    $driverLocation = $driverInfo['driver']['location']['lat'] . ',' . $driverInfo['driver']['location']['lng'];
                }
                
                if ($driverLocation) {
                    // Calculate ETA to dropoff location
                    $etaResult = calculateDriverETA($driverLocation, $ride['dropoff'], $conn);
                    if ($etaResult['success']) {
                        $minutesToAdd = $etaResult['minutes'];
                        $estimatedArrivalTime = date('g:i A', strtotime("+$minutesToAdd minutes"));
                    }
                }
            }
            
            $response = [
                'success' => true,
                'status' => 'in_progress',
                'message' => 'Your ride is in progress',
                'data' => [
                    'next_stage' => 5,
                    'waiting_time' => 20,
                    'estimated_arrival_time' => $estimatedArrivalTime,
                    'ride_details' => [
                        'pickup' => $ride['pickup'],
                        'dropoff' => $ride['dropoff'],
                        'fare' => 'G$' . number_format($ride['fare'], 2)
                    ]
                ]
            ];
            break;

        case 5: // Ride completed
            // Calculate final fare based on actual ride details
            $distanceResult = calculateDistance($ride['pickup'], $ride['dropoff']);
            $actualDistance = $distanceResult['success'] ? $distanceResult['distance']['km'] : $ride['distance'];
            
            $fareResult = calculateFare($actualDistance, $ride['vehicle_type'], $conn);
            $finalFare = $fareResult['rounded_fare'];
            
            // Update ride status to "completed"
            $updateStmt = $conn->prepare("
                UPDATE rides 
                SET status = 'completed', 
                    final_fare = ?, 
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $finalFareDB = $finalFare / 100; // Convert from cents to dollars for DB
            $updateStmt->bind_param("di", $finalFareDB, $bookingId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Update driver status to available
            $updateDriverStmt = $conn->prepare("
                UPDATE drivers 
                SET status = 'available', 
                    current_ride_id = NULL 
                WHERE id = ?
            ");
            $updateDriverStmt->bind_param("i", $ride['driver_id']);
            $updateDriverStmt->execute();
            $updateDriverStmt->close();
            
            // Log this status change
            $logStmt = $conn->prepare("
                INSERT INTO ride_logs 
                (ride_id, driver_id, action, details, created_at) 
                VALUES (?, ?, 'status_change', ?, NOW())
            ");
            
            $details = json_encode([
                'from_status' => 'in_progress',
                'to_status' => 'completed',
                'final_fare' => $finalFare,
                'distance_km' => $actualDistance,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $logStmt->bind_param("iis", $bookingId, $ride['driver_id'], $details);
            $logStmt->execute();
            $logStmt->close();
            
            // Add reward points (if enabled)
            $pointsToAdd = floor($finalFare * 0.01); // 1% of fare as points
            $pointsStmt = $conn->prepare("
                INSERT INTO reward_points (user_id, points) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE points = points + ?
            ");
            $pointsStmt->bind_param("iii", $userId, $pointsToAdd, $pointsToAdd);
            $pointsStmt->execute();
            $pointsStmt->close();
            
            // Get current points balance
            $pointsBalanceStmt = $conn->prepare("
                SELECT points FROM reward_points WHERE user_id = ?
            ");
            $pointsBalanceStmt->bind_param("i", $userId);
            $pointsBalanceStmt->execute();
            $pointsResult = $pointsBalanceStmt->get_result();
            $currentPoints = $pointsResult->fetch_assoc()['points'] ?? 0;
            $pointsBalanceStmt->close();
            
            $response = [
                'success' => true,
                'status' => 'completed',
                'message' => 'Ride completed successfully!',
                'data' => [
                    'fare' => 'G$' . number_format($finalFare / 100, 2), // Convert cents to dollars for display
                    'points_earned' => $pointsToAdd,
                    'total_points' => $currentPoints,
                    'ride_details' => [
                        'id' => $bookingId,
                        'pickup' => $ride['pickup'],
                        'dropoff' => $ride['dropoff'],
                        'distance' => $actualDistance,
                        'vehicle_type' => $ride['vehicle_type'],
                        'driver_name' => $ride['driver_name'],
                        'completed_at' => date('Y-m-d H:i:s')
                    ]
                ]
            ];
            break;

        default:
            http_response_code(400); // Bad Request
            $response = [
                'success' => false,
                'message' => 'Invalid stage',
                'status' => 'error'
            ];
            break;
    }

    // Close database connection
    $conn->close();

    // Send response
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log error and return error response
    error_log("Driver tracking API error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'message' => 'Internal server error',
        'status' => 'error'
    ];
    
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit;
}
?>