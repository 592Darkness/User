<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Always return JSON for AJAX requests
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

// Prevent any HTML output that would break JSON
ob_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Get form data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $data = $_POST;
    }
    
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    
    // Validate input
    if (empty($name)) {
        throw new Exception('Name is required');
    }
    
    if (empty($email)) {
        throw new Exception('Email is required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }
    
    if (empty($password)) {
        throw new Exception('Password is required');
    } elseif (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }
    
    if (empty($phone)) {
        throw new Exception('Phone number is required');
    }
    
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('This email is already registered. Please use a different email or login.');
    }
    
    $stmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $phone);
    
    if (!$stmt->execute()) {
        throw new Exception("Error creating account: " . $stmt->error);
    }
    
    $userId = $conn->insert_id;
    $stmt->close();
    
    // Store user in session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user'] = [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Success response
    $response['success'] = true;
    $response['message'] = 'Account created successfully!';
    $response['redirect'] = 'account-dashboard.php';
    
} catch (Exception $e) {
    // Error response
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Signup error: " . $e->getMessage());
}

// Clear any buffered output that might corrupt JSON
ob_clean();

// Send JSON response
echo json_encode($response);
exit;
?>