<?php
// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Log the request
error_log("Admin login process started: " . json_encode($_POST));

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token - commenting out temporarily for testing
    /*
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        error_log("CSRF token validation failed");
        setFlashMessage('error', 'Security validation failed. Please try again.');
        header('Location: admin-login.php');
        exit;
    }
    */
    
    // Get and sanitize input
    $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    error_log("Login attempt for username: $username");
    
    // Validate input
    if (empty($username) || empty($password)) {
        error_log("Empty username or password");
        setFlashMessage('error', 'Please enter both username and password.');
        header('Location: admin-login.php');
        exit;
    }
    
    try {
        // Connect to database
        $conn = dbConnect();
        error_log("Database connected successfully");
        
        // Check if admin table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'admins'");
        
        // If admin table doesn't exist, create it
        if ($tableCheck->num_rows === 0) {
            error_log("Creating admin table");
            // Create admin table
            $createTableQuery = "CREATE TABLE IF NOT EXISTS `admins` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `username` varchar(50) NOT NULL,
                `password` varchar(255) NOT NULL,
                `name` varchar(100) NOT NULL,
                `email` varchar(255) DEFAULT NULL,
                `last_login` datetime DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $conn->query($createTableQuery);
            
            // Create default admin user (username: admin, password: Admin123!)
            $defaultAdminName = "Administrator";
            $defaultAdminUsername = "admin";
            $defaultAdminPassword = password_hash("Admin123!", PASSWORD_DEFAULT);
            
            $insertAdminQuery = "INSERT INTO `admins` (`username`, `password`, `name`, `email`) 
                                VALUES (?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertAdminQuery);
            $stmt->bind_param("ssss", $defaultAdminUsername, $defaultAdminPassword, $defaultAdminName, $defaultAdminUsername);
            $stmt->execute();
            $stmt->close();
            
            error_log("Created default admin user with username: admin");
        }
        
        // Query for the admin
        $query = "SELECT * FROM admins WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        error_log("Query executed, found rows: " . $result->num_rows);
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $admin['password'])) {
                error_log("Password verified successfully for user: $username");
                
                // Password is correct, set up session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                
                error_log("Session set: " . json_encode($_SESSION));
                
                // Update last login time
                $updateLoginQuery = "UPDATE admins SET last_login = NOW() WHERE id = ?";
                $updateStmt = $conn->prepare($updateLoginQuery);
                $updateStmt->bind_param("i", $admin['id']);
                $updateStmt->execute();
                $updateStmt->close();
                
                // Log successful login
                error_log("Admin login successful: " . $admin['username']);
                
                // Redirect to dashboard - using full URL
                $redirectUrl = SITE_URL . '/admin-dashboard.php';
                error_log("Redirecting to: $redirectUrl");
                header("Location: $redirectUrl");
                exit;
            } else {
                // Password is incorrect
                error_log("Password verification failed for user: $username");
                setFlashMessage('error', 'Invalid username or password.');
                header('Location: admin-login.php');
                exit;
            }
        } else {
            // No admin found with that username
            error_log("No admin found with username: $username");
            setFlashMessage('error', 'Invalid username or password.');
            header('Location: admin-login.php');
            exit;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Admin login error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again later.');
        header('Location: admin-login.php');
        exit;
    }
} else {
    // If not a POST request, redirect to login page
    error_log("Not a POST request, redirecting to login page");
    header('Location: admin-login.php');
    exit;
}