<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// For direct debugging
error_log("Login process started");

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
$isJson = strpos($contentType, 'application/json') !== false;

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'user' => null,
    'redirect' => ''
];

// Start output buffering for debugging if needed
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from either JSON or form POST
    $data = null;
    
    // Check for JSON input
    if ($isJson) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
    } else {
        // Regular form data
        $data = $_POST;
    }
    
    // For debugging
    error_log("Login data: " . json_encode($data));
    
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (empty($email) || empty($password)) {
        $response['message'] = 'Please provide both email and password.';
        
        if ($isAjax || $isJson) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        setFlashMessage('error', $response['message']);
        redirect('index.php');
        exit;
    }
    
    try {
        // Connect to database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        
        // Directly query for the user
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // CRITICAL - Store user data in session
                $_SESSION['user_id'] = $user['id'];
                
                // Remove password from user data before storing in session
                unset($user['password']);
                $_SESSION['user'] = $user;
                
                // Log for debugging
                error_log("User logged in: " . json_encode($_SESSION));
                
                // Set success response
                $response['success'] = true;
                $response['message'] = 'Login successful!';
                $response['user'] = $_SESSION['user'];
                $response['redirect'] = 'account-dashboard.php';
                
                if ($isAjax || $isJson) {
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
                
                setFlashMessage('success', $response['message']);
                redirect($response['redirect']);
                exit;
            }
        }
        
        // If we get here, login failed
        $response['message'] = 'Invalid email or password. Please try again.';
        
        if ($isAjax || $isJson) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        setFlashMessage('error', $response['message']);
        redirect('index.php');
        exit;
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $response['message'] = 'An error occurred during login. Please try again later.';
        
        if ($isAjax || $isJson) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        setFlashMessage('error', $response['message']);
        redirect('index.php');
        exit;
    }
} else {
    $response['message'] = 'Invalid request method.';
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    setFlashMessage('error', $response['message']);
    redirect('index.php');
    exit;
}
?>