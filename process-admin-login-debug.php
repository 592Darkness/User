<?php
// Enhanced version of process-admin-login.php with better debugging
// Replace your existing process-admin-login.php with this file

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
$logsDir = dirname(__FILE__) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Set up logging
$logFile = $logsDir . '/admin-login-debug.log';
$debugLog = function($message) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
};

$debugLog("=== NEW LOGIN ATTEMPT ===");
$debugLog("IP: " . $_SERVER['REMOTE_ADDR']);
$debugLog("User Agent: " . $_SERVER['HTTP_USER_AGENT']);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    $debugLog("Starting new session");
    session_start();
    $debugLog("Session started. Session ID: " . session_id());
} else {
    $debugLog("Session already active. Session ID: " . session_id());
}

// Log initial session state
$debugLog("Initial Session Data: " . json_encode($_SESSION ?? 'No Session'));
$cookieParams = session_get_cookie_params();
$debugLog("Cookie Params: " . json_encode($cookieParams));

// Include required files
$debugLog("Loading required files");
try {
    require_once 'includes/config.php';
    require_once 'includes/functions.php';
    require_once 'includes/db.php';
    $debugLog("Required files loaded successfully");
} catch (Exception $e) {
    $debugLog("ERROR loading required files: " . $e->getMessage());
    die("Error loading required files: " . $e->getMessage());
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    $debugLog("Login attempt for username: [$username]");

    if (empty($username) || empty($password)) {
        $debugLog("Login failed: Empty username or password");
        setFlashMessage('error', 'Please enter both username and password.');
        header('Location: admin-login.php');
        exit;
    }

    try {
        $debugLog("Connecting to database");
        $conn = dbConnect();
        $debugLog("Database connected successfully");

        // Check if admin table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'admins'");
        if ($tableCheck->num_rows === 0) {
            $debugLog("Admin table not found, creating default table and admin user");
            
            // Create admins table
            $sql = "CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                role VARCHAR(20) DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL
            )";
            
            if ($conn->query($sql)) {
                $debugLog("Successfully created 'admins' table");
                
                // Insert default admin account
                $defaultUsername = 'admin';
                $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $defaultName = 'Administrator';
                
                $stmt = $conn->prepare("INSERT INTO admins (username, password, name) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $defaultUsername, $defaultPassword, $defaultName);
                
                if ($stmt->execute()) {
                    $debugLog("Created default admin account (admin/admin123)");
                } else {
                    $debugLog("Error creating default admin account: " . $stmt->error);
                }
                
                $stmt->close();
            } else {
                $debugLog("Error creating 'admins' table: " . $conn->error);
            }
        } else {
            $debugLog("Admin table exists");
        }

        // Query for the admin user
        $query = "SELECT * FROM admins WHERE username = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $debugLog("Prepare statement failed: " . $conn->error);
            throw new Exception("Database query preparation failed");
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $debugLog("Admin query executed. Rows found: " . $result->num_rows);

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            $debugLog("Admin found. ID: " . $admin['id'] . ", Name: " . $admin['name']);
            
            if (password_verify($password, $admin['password'])) {
                $debugLog("Password verified for user: [$username]");

                // Clear any existing session data
                $_SESSION = array();
                $debugLog("Session data cleared");

                // Regenerate session ID
                session_regenerate_id(true);
                $newSessionId = session_id();
                $debugLog("Session ID regenerated to: [$newSessionId]");

                // Set session variables
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = (string)$admin['username'];
                $_SESSION['admin_name'] = (string)$admin['name'];
                $debugLog("Admin session variables set. New data: " . json_encode($_SESSION));

                // Update last login time
                $updateLoginQuery = "UPDATE admins SET last_login = NOW() WHERE id = ?";
                $updateStmt = $conn->prepare($updateLoginQuery);
                $updateStmt->bind_param("i", $admin['id']);
                $updateStmt->execute();
                $updateStmt->close();
                $debugLog("Last login time updated");

                // Write session data and close
                session_write_close();
                $debugLog("Session data written and closed");

                // Redirect to dashboard
                $debugLog("Redirecting to admin-dashboard.php");
                if (!headers_sent($file, $line)) {
                    header("Location: admin-dashboard.php");
                    $debugLog("Header redirect sent");
                    exit();
                } else {
                    $debugLog("Headers already sent by $file:$line! Using JS redirect");
                    echo '<script>window.location.href = "admin-dashboard.php";</script>';
                    exit();
                }
            } else {
                $debugLog("Password verification FAILED for user: [$username]");
                setFlashMessage('error', 'Invalid username or password.');
                header('Location: admin-login.php');
                exit;
            }
        } else {
            $debugLog("No admin found with username: [$username]");
            setFlashMessage('error', 'Invalid username or password.');
            header('Location: admin-login.php');
            exit;
        }
        
        $stmt->close();
        $conn->close();
        $debugLog("Database connection closed");
        
    } catch (Exception $e) {
        $debugLog("CRITICAL LOGIN ERROR: " . $e->getMessage());
        $debugLog("Stack Trace: " . $e->getTraceAsString());
        setFlashMessage('error', 'An error occurred: ' . $e->getMessage());
        
        if (isset($conn) && $conn->ping()) {
            $conn->close();
            $debugLog("Database connection closed after error");
        }
        
        header('Location: admin-login.php');
        exit;
    }
} else {
    $debugLog("Not a POST request, redirecting to login");
    header('Location: admin-login.php');
    exit;
}
?>
