<?php
// Debug-focused version of process-admin-driver.php
// This version will log diagnostics and respond with JSON

// Start by setting appropriate headers for AJAX
header('Content-Type: application/json');

// Create a log function to track execution
function debug_log($message, $data = null) {
    $log_file = __DIR__ . '/driver_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= " - Data: " . json_encode($data);
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'Debug mode active',
    'debug_info' => [
        'time' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'is_ajax' => isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest',
        'uri' => $_SERVER['REQUEST_URI'],
        'input_received' => false
    ]
];

debug_log("Request started", $_SERVER);

// Prevent redirects - we'll handle auth manually for diagnosis
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
    debug_log("Started session", ['session_id' => session_id()]);
}

// Check authentication directly
if (!isset($_SESSION['admin_id'])) {
    debug_log("Authentication failed - admin_id not in session", $_SESSION);
    $response['message'] = 'Authentication failed. Please log in.';
    $response['debug_info']['session_status'] = 'No admin_id found';
    $response['debug_info']['session_data'] = $_SESSION;
    echo json_encode($response);
    exit;
}

debug_log("Authentication successful", ['admin_id' => $_SESSION['admin_id']]);
$response['debug_info']['authenticated'] = true;

// Get input data from either JSON or POST
$input_data = [];

// Try getting JSON input
$raw_input = file_get_contents('php://input');
if (!empty($raw_input)) {
    debug_log("Raw input received", ['length' => strlen($raw_input)]);
    $json_data = json_decode($raw_input, true);
    if ($json_data !== null) {
        $input_data = $json_data;
        $response['debug_info']['input_type'] = 'json';
        $response['debug_info']['input_received'] = true;
    } else {
        debug_log("Failed to parse JSON input", ['raw' => substr($raw_input, 0, 100)]);
    }
}

// If no JSON, try POST
if (empty($input_data) && !empty($_POST)) {
    $input_data = $_POST;
    $response['debug_info']['input_type'] = 'post';
    $response['debug_info']['input_received'] = true;
    debug_log("POST data received", $_POST);
}

// Check for action parameter
if (!isset($input_data['action'])) {
    debug_log("No action parameter", $input_data);
    $response['message'] = 'Missing action parameter';
    $response['debug_info']['input_data'] = $input_data;
    echo json_encode($response);
    exit;
}

// Process simple get_driver request for diagnosis
if ($input_data['action'] === 'get_driver' || $input_data['action'] === 'view') {
    debug_log("Processing get_driver action", $input_data);
    
    if (!isset($input_data['driver_id'])) {
        debug_log("Missing driver_id");
        $response['message'] = 'Missing driver ID';
        echo json_encode($response);
        exit;
    }
    
    // Simple response with fake driver data
    $response['success'] = true;
    $response['message'] = 'Debug driver data returned';
    $response['driver'] = [
        'id' => $input_data['driver_id'],
        'name' => 'Debug Driver',
        'email' => 'debug@example.com',
        'phone' => '555-123-4567',
        'vehicle' => 'Test Vehicle',
        'plate' => 'TEST123',
        'vehicle_type' => 'standard',
        'status' => 'available',
        'total_rides' => 10,
        'avg_rating' => 4.5,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => date('Y-m-d H:i:s')
    ];
    
    debug_log("Sending success response for get_driver");
    echo json_encode($response);
    exit;
}

// For any other action, just return debug info
$response['debug_info']['action_requested'] = $input_data['action'];
$response['debug_info']['input_data'] = $input_data;
debug_log("Sending debug info for action: " . $input_data['action'], $input_data);
echo json_encode($response);
exit;