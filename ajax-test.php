<?php
// ajax-test.php - Absolute minimal test
// No includes, no session handling, just pure output

// Set content type to JSON
header('Content-Type: application/json');

// Create simple response
$response = [
    'success' => true,
    'message' => 'Basic AJAX response working',
    'time' => date('Y-m-d H:i:s'),
    'test' => true
];

// Output response
echo json_encode($response);
exit;