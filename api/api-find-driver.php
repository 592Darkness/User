<?php
/**
 * API Endpoint for Finding a Driver
 * Uses database to find available drivers and track ride status
 * Enhanced with realistic ETA calculations using Google Maps API
 */

// Include the driver ETA calculation functions
require_once 'includes/driver-eta.php';

// Google Maps API key from config
$googleMapsApiKey = "AIzaSyA-6uXAa6MkIMwlYYwMIVBq5s3T0aTh0EI";

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'You must be logged in to request a ride.';
    echo json_encode($response);
    exit;
}

// Check if request method is POST
if ($method !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Method not allowed. Use POST.';
    echo json_encode($response);
    exit;
}

// Get form data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// Get booking ID from request
$bookingId = isset($data['booking_id']) ? intval(sanitize($data['booking_id'])) : 0;

// Check if booking ID is provided
if (empty($bookingId)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Booking ID is required.';
    echo json_encode($response);
    exit;
}

$conn = dbConnect();

// Check if booking exists and belongs to current user
$userId = $_SESSION['user_id'];
$rideStmt = $conn->prepare("SELECT * FROM rides WHERE id = ? AND user_id = ?");
$rideStmt->bind_param("ii", $bookingId, $userId);
$rideStmt->execute();
$rideResult = $rideStmt->get_result();

if ($rideResult->num_rows === 0) {
    $rideStmt->close();
    http_response_code(404); // Not Found
    $response['message'] = 'Booking not found or not authorized.';
    echo json_encode($response);
    $conn->close();
    exit;
}

$ride = $rideResult->fetch_assoc();
$rideStmt->close();

// Get the stage parameter from request
$stage = isset($data['stage']) ? intval($data['stage']) : 0;

// Log stage for debugging
error_log("Find driver request - Booking ID: $bookingId, Stage: $stage");

// Process the driver finding based on the stage
switch ($stage) {
    case 0: // Initial search
        // Check if this ride already has a driver assigned
        if (!empty($ride['driver_id'])) {
            // Get the driver details and location
            $driver = getNearestDriverLocation($bookingId, $ride['pickup'], $conn);
            
            if ($driver && isset($driver['id']) && $driver['id'] > 0) {
                // Calculate realistic ETA using Google Maps API
                $etaData = calculateETA($driver['location'], $ride['pickup'], $googleMapsApiKey);
                
                $response['success'] = true;
                $response['status'] = 'confirmed';
                $response['message'] = 'Driver already assigned and is on the way!';
                $response['data'] = [
                    'booking_id' => $bookingId,
                    'next_stage' => 2,
                    'waiting_time' => 15,
                    'driver' => [
                        'id' => $driver['id'],
                        'name' => $driver['name'],
                        'rating' => $driver['rating'],
                        'vehicle' => $driver['vehicle'],
                        'plate' => $driver['plate'],
                        'eta' => $etaData['minutes'],
                        'eta_text' => $etaData['formatted'],
                        'location' => $driver['location'],
                        'photo' => null
                    ]
                ];
                
                $conn->close();
                echo json_encode($response);
                exit;
            }
        }
        
        // Find available drivers near pickup location
        // Calculate distance from each driver to the pickup location
        $availableDriversStmt = $conn->prepare("
            SELECT d.id, d.name, d.rating, d.vehicle, d.plate, dl.location, dl.latitude, dl.longitude 
            FROM drivers d
            LEFT JOIN driver_locations dl ON d.id = dl.driver_id
            WHERE d.status = 'available' 
            ORDER BY RAND() 
            LIMIT 5
        ");
        $availableDriversStmt->execute();
        $driversResult = $availableDriversStmt->get_result();
        
        // If no drivers found, return searching status
        if ($driversResult->num_rows === 0) {
            $response['success'] = true;
            $response['status'] = 'searching';
            $response['message'] = 'No drivers available at the moment. Please try again soon.';
            $response['data'] = [
                'booking_id' => $bookingId,
                'next_stage' => 0, // Stay in searching stage
                'waiting_time' => 10 // seconds to wait before next request
            ];
        } else {
            // Return searching status
            $response['success'] = true;
            $response['status'] = 'searching';
            $response['message'] = 'Searching for drivers in your area...';
            $response['data'] = [
                'booking_id' => $bookingId,
                'next_stage' => 1,
                'waiting_time' => 5 // seconds to wait before next request
            ];
        }
        
        $availableDriversStmt->close();
        break;
        
    case 1: // Driver found
        // No driver assigned yet, assign one
        if (empty($ride['driver_id'])) {
            // Find the best available driver (consider proximity, rating, etc.)
            $findDriverStmt = $conn->prepare("
                SELECT d.id, d.name, d.rating, d.vehicle, d.plate, dl.location, dl.latitude, dl.longitude 
                FROM drivers d
                LEFT JOIN driver_locations dl ON d.id = dl.driver_id
                WHERE d.status = 'available' 
                ORDER BY d.rating DESC, RAND() 
                LIMIT 1
            ");
            $findDriverStmt->execute();
            $driverResult = $findDriverStmt->get_result();
            
            if ($driverResult->num_rows === 0) {
                // No available drivers
                $response['success'] = false;
                $response['status'] = 'searching';
                $response['message'] = 'No drivers available at the moment. Please try again soon.';
                $response['data'] = [
                    'booking_id' => $bookingId,
                    'next_stage' => 0, // Go back to searching
                    'waiting_time' => 10
                ];
                
                $findDriverStmt->close();
                $conn->close();
                echo json_encode($response);
                exit;
            }
            
            $driver = $driverResult->fetch_assoc();
            $findDriverStmt->close();
            
            // If driver has no location yet, generate one
            if (empty($driver['location'])) {
                $driver['location'] = generateSimulatedLocation($ride['pickup']);
                
                // Save this location to the database
                saveDriverLocation($driver['id'], $driver['location'], $conn);
            }
            
            // Assign the driver to the ride
            $assignDriverStmt = $conn->prepare("UPDATE rides SET driver_id = ?, status = 'confirmed', updated_at = NOW() WHERE id = ?");
            $assignDriverStmt->bind_param("ii", $driver['id'], $bookingId);
            $assignDriverStmt->execute();
            $assignDriverStmt->close();
            
            // Update driver status
            $updateDriverStmt = $conn->prepare("UPDATE drivers SET status = 'busy', updated_at = NOW() WHERE id = ?");
            $updateDriverStmt->bind_param("i", $driver['id']);
            $updateDriverStmt->execute();
            $updateDriverStmt->close();
            
            // Log driver assignment
            $logStmt = $conn->prepare("
                INSERT INTO ride_logs (ride_id, user_id, driver_id, action, details, created_at)
                VALUES (?, ?, ?, 'driver_assigned', ?, NOW())
            ");
            $details = json_encode([
                'driver_name' => $driver['name'],
                'driver_rating' => $driver['rating'],
                'vehicle' => $driver['vehicle'],
                'plate' => $driver['plate']
            ]);
            $logStmt->bind_param("iiis", $bookingId, $userId, $driver['id'], $details);
            $logStmt->execute();
            $logStmt->close();
            
            // Calculate realistic ETA using Google Maps API
            $etaData = calculateETA($driver['location'], $ride['pickup'], $googleMapsApiKey);
            
            $response['success'] = true;
            $response['status'] = 'confirmed';
            $response['message'] = 'Driver found and is on the way!';
            $response['data'] = [
                'booking_id' => $bookingId,
                'next_stage' => 2,
                'waiting_time' => 15,
                'driver' => [
                    'id' => $driver['id'],
                    'name' => $driver['name'],
                    'rating' => $driver['rating'],
                    'vehicle' => $driver['vehicle'],
                    'plate' => $driver['plate'],
                    'eta' => $etaData['minutes'],
                    'eta_text' => $etaData['formatted'],
                    'location' => $driver['location'],
                    'photo' => null
                ]
            ];
        } else {
            // Driver already assigned, get their details
            $driver = getNearestDriverLocation($bookingId, $ride['pickup'], $conn);
            
            // Calculate realistic ETA using Google Maps API
            $etaData = calculateETA($driver['location'], $ride['pickup'], $googleMapsApiKey);
            
            $response['success'] = true;
            $response['status'] = 'confirmed';
            $response['message'] = 'Driver has been assigned and is on the way!';
            $response['data'] = [
                'booking_id' => $bookingId,
                'next_stage' => 2,
                'waiting_time' => 15,
                'driver' => [
                    'id' => $driver['id'],
                    'name' => $driver['name'],
                    'rating' => $driver['rating'],
                    'vehicle' => $driver['vehicle'],
                    'plate' => $driver['plate'],
                    'eta' => $etaData['minutes'],
                    'eta_text' => $etaData['formatted'],
                    'location' => $driver['location'],
                    'photo' => null
                ]
            ];
        }
        break;
        
    case 2: // Driver arriving
        // Update ride status to "arriving"
        $updateStmt = $conn->prepare("UPDATE rides SET status = 'arriving', updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $bookingId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Get driver info and current location
        $driver = getNearestDriverLocation($bookingId, $ride['pickup'], $conn);
        
        // Update driver's location to be closer to pickup (simulate movement)
        // Update location to be much closer to pickup
        $nearbyLocation = "approaching " . $ride['pickup'];
        saveDriverLocation($driver['id'], $nearbyLocation, $conn);
        $driver['location'] = $nearbyLocation;
        
        // Calculate new ETA based on being very close
        $etaData = calculateETA($driver['location'], $ride['pickup'], $googleMapsApiKey);
        
        // If the API can't calculate a very short distance, set a reasonable ETA
        if (!$etaData['success'] || $etaData['minutes'] > 3) {
            $etaData['minutes'] = 2;
            $etaData['formatted'] = "2 mins";
        }
        
        // Log driver arriving status
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs (ride_id, user_id, driver_id, action, details, created_at)
            VALUES (?, ?, ?, 'driver_arriving', ?, NOW())
        ");
        $details = json_encode([
            'eta_minutes' => $etaData['minutes'],
            'location' => $driver['location']
        ]);
        $logStmt->bind_param("iiis", $bookingId, $userId, $driver['id'], $details);
        $logStmt->execute();
        $logStmt->close();
        
        $response['success'] = true;
        $response['status'] = 'arriving';
        $response['message'] = 'Your driver is arriving in ' . $etaData['formatted'] . '!';
        $response['data'] = [
            'booking_id' => $bookingId,
            'next_stage' => 3,
            'waiting_time' => 10,
            'driver' => [
                'id' => $driver['id'],
                'name' => $driver['name'],
                'rating' => $driver['rating'],
                'vehicle' => $driver['vehicle'],
                'plate' => $driver['plate'],
                'eta' => $etaData['minutes'],
                'eta_text' => $etaData['formatted'],
                'location' => $driver['location'],
                'photo' => null
            ]
        ];
        break;
        
    case 3: // Driver arrived
        // Update ride status to "arrived"
        $updateStmt = $conn->prepare("UPDATE rides SET status = 'arrived', updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $bookingId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Get driver info
        $driver = getNearestDriverLocation($bookingId, $ride['pickup'], $conn);
        
        // Update driver location to be at pickup point
        $driver['location'] = $ride['pickup'];
        saveDriverLocation($driver['id'], $driver['location'], $conn);
        
        // Log driver arrived
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs (ride_id, user_id, driver_id, action, details, created_at)
            VALUES (?, ?, ?, 'driver_arrived', ?, NOW())
        ");
        $details = json_encode([
            'arrived_at' => date('Y-m-d H:i:s')
        ]);
        $logStmt->bind_param("iiis", $bookingId, $userId, $driver['id'], $details);
        $logStmt->execute();
        $logStmt->close();
        
        $response['success'] = true;
        $response['status'] = 'arrived';
        $response['message'] = 'Your driver has arrived!';
        $response['data'] = [
            'booking_id' => $bookingId,
            'driver' => [
                'id' => $driver['id'],
                'name' => $driver['name'],
                'rating' => $driver['rating'],
                'vehicle' => $driver['vehicle'],
                'plate' => $driver['plate'],
                'eta' => 0, // Has arrived
                'eta_text' => 'Arrived',
                'location' => $driver['location'],
                'photo' => null
            ],
            'ride_instructions' => 'Please meet your driver at the pickup location.'
        ];
        break;
        
    case 4: // Ride in progress
        // Update ride status to "in_progress"
        $updateStmt = $conn->prepare("UPDATE rides SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $bookingId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Get driver info
        $driver = getNearestDriverLocation($bookingId, $ride['pickup'], $conn);
        
        // Update driver location to be moving toward destination
        $driver['location'] = 'en route to ' . $ride['dropoff'];
        saveDriverLocation($driver['id'], $driver['location'], $conn);
        
        // Calculate realistic travel time to destination
        $etaData = calculateETA($ride['pickup'], $ride['dropoff'], $googleMapsApiKey);
        
        // Format a nice estimated arrival time
        $arrivalMinutes = $etaData['success'] ? $etaData['minutes'] : mt_rand(15, 25);
        $estimatedArrivalTime = date('H:i', strtotime("+" . $arrivalMinutes . " minutes"));
        
        // Log ride in progress
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs (ride_id, user_id, driver_id, action, details, created_at)
            VALUES (?, ?, ?, 'ride_started', ?, NOW())
        ");
        $details = json_encode([
            'started_at' => date('Y-m-d H:i:s'),
            'estimated_arrival' => $estimatedArrivalTime,
            'estimated_minutes' => $arrivalMinutes
        ]);
        $logStmt->bind_param("iiis", $bookingId, $userId, $driver['id'], $details);
        $logStmt->execute();
        $logStmt->close();
        
        $response['success'] = true;
        $response['status'] = 'in_progress';
        $response['message'] = 'Your ride is in progress!';
        $response['data'] = [
            'booking_id' => $bookingId,
            'next_stage' => 5,
            'waiting_time' => 20,
            'driver' => [
                'id' => $driver['id'],
                'name' => $driver['name'],
                'rating' => $driver['rating'],
                'vehicle' => $driver['vehicle'],
                'plate' => $driver['plate'],
                'eta_to_destination' => $arrivalMinutes,
                'photo' => null
            ],
            'estimated_arrival_time' => $estimatedArrivalTime
        ];
        break;
        
    case 5: // Ride completed
        // Calculate actual fare based on distance and time
        // For now, use the original fare from the ride
        $actualFare = $ride['fare'];
        
        // Update ride status to "completed" and set completion time
        $updateStmt = $conn->prepare("UPDATE rides SET status = 'completed', completed_at = NOW(), final_fare = ? WHERE id = ?");
        $updateStmt->bind_param("di", $actualFare, $bookingId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Update driver status back to "available"
        $updateDriverStmt = $conn->prepare("UPDATE drivers SET status = 'available' WHERE id = ?");
        $updateDriverStmt->bind_param("i", $ride['driver_id']);
        $updateDriverStmt->execute();
        $updateDriverStmt->close();
        
        // Add reward points for the user (10% of fare in points)
        $pointsToAdd = floor($actualFare * 0.1);
        
        // Check if user has reward points record
        $pointsStmt = $conn->prepare("SELECT points FROM reward_points WHERE user_id = ?");
        $pointsStmt->bind_param("i", $userId);
        $pointsStmt->execute();
        $pointsResult = $pointsStmt->get_result();
        
        if ($pointsResult->num_rows > 0) {
            // Update existing points
            $pointsData = $pointsResult->fetch_assoc();
            $newPoints = $pointsData['points'] + $pointsToAdd;
            
            $updatePointsStmt = $conn->prepare("UPDATE reward_points SET points = ? WHERE user_id = ?");
            $updatePointsStmt->bind_param("ii", $newPoints, $userId);
            $updatePointsStmt->execute();
            $updatePointsStmt->close();
        } else {
            // Create new points record
            $insertPointsStmt = $conn->prepare("INSERT INTO reward_points (user_id, points) VALUES (?, ?)");
            $insertPointsStmt->bind_param("ii", $userId, $pointsToAdd);
            $insertPointsStmt->execute();
            $insertPointsStmt->close();
        }
        
        $pointsStmt->close();
        
        // Get driver name
        $driverStmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
        $driverStmt->bind_param("i", $ride['driver_id']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        $driverName = ($driverResult->num_rows > 0) ? $driverResult->fetch_assoc()['name'] : 'your driver';
        $driverStmt->close();
        
        // Log ride completion
        $logStmt = $conn->prepare("
            INSERT INTO ride_logs (ride_id, user_id, driver_id, action, details, created_at)
            VALUES (?, ?, ?, 'ride_completed', ?, NOW())
        ");
        $details = json_encode([
            'completed_at' => date('Y-m-d H:i:s'),
            'fare' => $actualFare,
            'points_earned' => $pointsToAdd
        ]);
        $logStmt->bind_param("iiis", $bookingId, $userId, $ride['driver_id'], $details);
        $logStmt->execute();
        $logStmt->close();
        
        $formattedFare = "G$" . number_format($actualFare);
        
        $response['success'] = true;
        $response['status'] = 'completed';
        $response['message'] = 'Your ride has been completed!';
        $response['data'] = [
            'booking_id' => $bookingId,
            'fare' => $formattedFare,
            'points_earned' => $pointsToAdd,
            'rating_prompt' => "How was your ride with {$driverName}?"
        ];
        break;
        
    default:
        http_response_code(400); // Bad Request
        $response['message'] = 'Invalid stage.';
        $conn->close();
        echo json_encode($response);
        exit;
}

$conn->close();
echo json_encode($response);
exit;
?>