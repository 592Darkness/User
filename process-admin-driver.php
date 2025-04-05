<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Check if admin is logged in
requireAdminLogin();

// Set response type to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Get request method and data
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If the data is null, check if form data was submitted
    if ($data === null) {
        $data = $_POST;
    }
    
    // Check CSRF token
    if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
        $response['message'] = 'Security validation failed. Please refresh the page and try again.';
        echo json_encode($response);
        exit;
    }
    
    // Get action from request
    $action = isset($data['action']) ? $data['action'] : '';
    
    switch ($action) {
        case 'add':
            // Validate required fields
            $requiredFields = ['name', 'email', 'password', 'phone', 'vehicle', 'plate', 'vehicle_type'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $response['message'] = 'The following fields are required: ' . implode(', ', $missingFields);
                break;
            }
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Please enter a valid email address.';
                break;
            }
            
            // Check if email already exists
            if (driverEmailExists($data['email'])) {
                $response['message'] = 'A driver with this email already exists.';
                break;
            }
            
            // Check password length
            if (strlen($data['password']) < 8) {
                $response['message'] = 'Password must be at least 8 characters.';
                break;
            }
            
            // Prepare driver data
            $driverData = [
                'name' => trim($data['name']),
                'email' => trim($data['email']),
                'password' => $data['password'],
                'phone' => trim($data['phone']),
                'vehicle' => trim($data['vehicle']),
                'plate' => trim($data['plate']),
                'vehicle_type' => $data['vehicle_type'],
                'status' => isset($data['status']) ? $data['status'] : 'available'
            ];
            
            // Add driver to database
            $driverId = addDriver($driverData);
            
            if ($driverId) {
                $response['success'] = true;
                $response['message'] = 'Driver added successfully.';
                $response['driver_id'] = $driverId;
            } else {
                $response['message'] = 'Failed to add driver. Please try again.';
            }
            break;
            
        case 'edit':
            // Validate driver ID
            if (!isset($data['driver_id']) || !is_numeric($data['driver_id'])) {
                $response['message'] = 'Invalid driver ID.';
                break;
            }
            
            $driverId = (int)$data['driver_id'];
            
            // Validate required fields
            $requiredFields = ['name', 'email', 'phone', 'vehicle', 'plate', 'vehicle_type'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $response['message'] = 'The following fields are required: ' . implode(', ', $missingFields);
                break;
            }
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Please enter a valid email address.';
                break;
            }
            
            // Check if email already exists (excluding current driver)
            if (driverEmailExists($data['email'], $driverId)) {
                $response['message'] = 'A driver with this email already exists.';
                break;
            }
            
            // Check password if provided
            if (isset($data['password']) && !empty($data['password']) && strlen($data['password']) < 8) {
                $response['message'] = 'Password must be at least 8 characters.';
                break;
            }
            
            // Prepare driver data
            $driverData = [
                'name' => trim($data['name']),
                'email' => trim($data['email']),
                'phone' => trim($data['phone']),
                'vehicle' => trim($data['vehicle']),
                'plate' => trim($data['plate']),
                'vehicle_type' => $data['vehicle_type'],
                'status' => isset($data['status']) ? $data['status'] : 'available'
            ];
            
            // Add password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $driverData['password'] = $data['password'];
            }
            
            // Update driver in database
            $result = updateDriver($driverId, $driverData);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Driver updated successfully.';
            } else {
                $response['message'] = 'Failed to update driver. Please try again.';
            }
            break;
            
        case 'delete':
            // Validate driver ID
            if (!isset($data['driver_id']) || !is_numeric($data['driver_id'])) {
                $response['message'] = 'Invalid driver ID.';
                break;
            }
            
            $driverId = (int)$data['driver_id'];
            
            // Delete driver from database
            $result = deleteDriver($driverId);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Driver deleted successfully.';
            } else {
                $response['message'] = 'Failed to delete driver. The driver may have rides associated with them and cannot be deleted.';
            }
            break;
            
        case 'get_driver':
            // Validate driver ID
            if (!isset($data['driver_id']) || !is_numeric($data['driver_id'])) {
                $response['message'] = 'Invalid driver ID.';
                break;
            }
            
            $driverId = (int)$data['driver_id'];
            
            // Get driver details
            $driver = getDriverDetails($driverId);
            
            if ($driver) {
                $response['success'] = true;
                $response['driver'] = $driver;
            } else {
                $response['message'] = 'Driver not found.';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action.';
            break;
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Send response
echo json_encode($response);
