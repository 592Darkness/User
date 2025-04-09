<?php
/**
 * API Endpoint: /api/driver-location-update.php
 * Receives location updates from driver mobile apps
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Set Content-Type header to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

$driverId = $_SESSION['driver_id'];

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get location data from request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
$longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;
$address = isset($data['address']) ? sanitize($data['address']) : null;

// Validate location data
if (!$latitude || !$longitude) {
    $response['message'] = 'Invalid location data';
    echo json_encode($response);
    exit;
}

// If address is not provided, geocode it from coordinates
if (empty($address)) {
    $address = geocodeCoordinates($latitude, $longitude);
}

try {
    $conn = dbConnect();
    
    // Store driver location
    $stmt = $conn->prepare("
        INSERT INTO driver_locations (driver_id, location, latitude, longitude, updated_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            location = VALUES(location),
            latitude = VALUES(latitude), 
            longitude = VALUES(longitude),
            updated_at = NOW()
    ");
    
    $stmt->bind_param("isdd", $driverId, $address, $latitude, $longitude);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Mark driver as available if location is being updated
        $updateDriverQuery = "
            UPDATE drivers
            SET last_active = NOW()
            WHERE id = ?
        ";
        
        $updateDriverStmt = $conn->prepare($updateDriverQuery);
        $updateDriverStmt->bind_param("i", $driverId);
        $updateDriverStmt->execute();
        $updateDriverStmt->close();
        
        $response['success'] = true;
        $response['message'] = 'Location updated successfully';
    } else {
        $response['message'] = 'Failed to update location';
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error updating driver location: " . $e->getMessage());
    $response['message'] = 'An error occurred while updating location';
}

echo json_encode($response);
exit;

// Helper function to get address from coordinates using Google Maps API
function geocodeCoordinates($lat, $lng) {
    try {
        $apiKey = GOOGLE_MAPS_API_KEY;
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$apiKey}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK' && !empty($data['results'])) {
            return $data['results'][0]['formatted_address'];
        }
    } catch (Exception $e) {
        error_log("Geocoding error: " . $e->getMessage());
    }
    
    return "Unknown Location";
}
?>