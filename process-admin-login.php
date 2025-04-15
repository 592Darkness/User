<?php
// Improved process-admin-login.php with elements from the direct login solution

// Start session first
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Safe redirection function
function safeRedirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

// Write detailed logs for easier debugging
function writeLog($message) {
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $logFile = $logsDir . '/admin-login.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Start login process
writeLog("=== Login attempt started ===");

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    writeLog("Login attempt for username: $username");

    if (empty($username) || empty($password)) {
        writeLog("Login failed: Empty username or password");
        setFlashMessage('error', 'Please enter both username and password.');
        safeRedirect('admin-login.php');
    }

    try {
        // Connect to database
        $conn = dbConnect();
        writeLog("Database connected successfully");

        // Check for admin user
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        
        if (!$stmt) {
            writeLog("Prepare statement failed: " . $conn->error);
            setFlashMessage('error', 'Database error. Please try again later.');
            safeRedirect('admin-login.php');
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        writeLog("Query executed. Found " . $result->num_rows . " matching users");

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            writeLog("User found: ID=" . $admin['id'] . ", Name=" . $admin['name']);
            
            if (password_verify($password, $admin['password'])) {
                writeLog("Password verified successfully");
                
                // Clear session data
                $_SESSION = array();
                writeLog("Session data cleared");
                
                // Set admin session variables
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                
                writeLog("Session variables set: " . json_encode($_SESSION));
                
                // Update last login time
                $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $updateStmt->bind_param("i", $admin['id']);
                $updateStmt->execute();
                $updateStmt->close();
                writeLog("Last login time updated");
                
                // Force session data to be written
                session_write_close();
                writeLog("Session written and closed");
                
                // Restart session to ensure data is loaded fresh
                session_start();
                writeLog("Session restarted: " . session_id());
                
                // Redirect to dashboard
                writeLog("Redirecting to admin dashboard");
                safeRedirect('admin-dashboard.php');
            } else {
                writeLog("Password verification failed");
                setFlashMessage('error', 'Invalid username or password.');
                safeRedirect('admin-login.php');
            }
        } else {
            writeLog("No matching user found");
            setFlashMessage('error', 'Invalid username or password.');
            safeRedirect('admin-login.php');
        }
        
        $stmt->close();
        $conn->close();
        writeLog("Database connection closed");
        
    } catch (Exception $e) {
        writeLog("ERROR: " . $e->getMessage());
        writeLog("Trace: " . $e->getTraceAsString());
        
        setFlashMessage('error', 'An error occurred during login. Please try again.');
        
        if (isset($conn) && $conn->ping()) {
            $conn->close();
            writeLog("Database connection closed after error");
        }
        
        safeRedirect('admin-login.php');
    }
} else {
    writeLog("Non-POST request received");
    safeRedirect('admin-login.php');
}
?>