<?php
// standalone-admin-login.php
// This is a complete all-in-one admin login solution

// ===== CONFIGURATION =====
define('DB_HOST', 'localhost');
define('DB_USER', 'u169889364_Salaamrides');
define('DB_PASS', 'Welcome72022@@');
define('DB_NAME', 'u169889364_Salaamrides');

// ===== SESSION SETUP =====
// First, clear any existing output buffer
if (ob_get_level()) ob_end_clean();

// Start output buffering
ob_start();

// Set cookie parameters BEFORE starting the session
session_set_cookie_params([
    'lifetime' => 86400, // 1 day
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Start a fresh session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ===== HELPER FUNCTIONS =====
// Database connection
function connectDB() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        return false;
    }
}

// Safe redirection with multiple fallbacks
function redirectTo($url) {
    // First try header redirect
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    
    // If headers were already sent, try JavaScript
    echo "<script>window.location.href='$url';</script>";
    
    // Fallback to meta refresh
    echo "<meta http-equiv='refresh' content='0;url=$url'>";
    
    // Last resort - plain link
    echo "<p>Click <a href='$url'>here</a> if you are not redirected automatically.</p>";
    exit;
}

// Simple logger
function writeLog($message) {
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $logFile = $logsDir . '/admin-login.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// Set flash message
function setMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Get and clear flash message
function getMessage() {
    $message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
    unset($_SESSION['flash_message']);
    return $message;
}

// Display page message
function showMessage($message, $type) {
    $color = ($type == 'error') ? 'red' : (($type == 'success') ? 'green' : 'blue');
    return "<div class='bg-{$color}-500/20 text-{$color}-400 p-3 rounded-lg text-sm mb-4'>{$message}</div>";
}

// Asset URL helper
function asset($path) {
    return 'assets/' . ltrim($path, '/');
}

// ===== LOGIN PROCESSING =====
$error = '';
$success = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    writeLog("Login attempt for username: $username");
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        writeLog("Login failed: Empty fields");
    } else {
        // Connect to database
        $conn = connectDB();
        
        if (!$conn) {
            $error = 'Database connection failed. Please try again later.';
            writeLog("Login failed: Database connection error");
        } else {
            // Check for admin user
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
            
            if (!$stmt) {
                $error = 'Database error. Please try again later.';
                writeLog("Login failed: Prepare statement failed: " . $conn->error);
            } else {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    
                    if (password_verify($password, $admin['password'])) {
                        // SUCCESS - USER AUTHENTICATED
                        writeLog("Login successful for user ID: " . $admin['id']);
                        
                        // Clear existing session
                        $_SESSION = array();
                        
                        // Set admin session data
                        $_SESSION['admin_id'] = (int)$admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_login_time'] = time();
                        
                        // Update last login time
                        $updateStmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                        $updateStmt->bind_param("i", $admin['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Log session data
                        writeLog("Set session data: " . json_encode($_SESSION));
                        
                        // Force write session data
                        session_write_close();
                        
                        // Start session again to ensure data is loaded
                        session_start();
                        
                        // Set success message
                        $success = 'Login successful! Redirecting...';
                        
                        // Redirect to dashboard with delay
                        echo "<meta http-equiv='refresh' content='1;url=admin-dashboard.php'>";
                    } else {
                        $error = 'Invalid username or password.';
                        writeLog("Login failed: Invalid password for user: $username");
                    }
                } else {
                    $error = 'Invalid username or password.';
                    writeLog("Login failed: Username not found: $username");
                }
                
                $stmt->close();
            }
            
            $conn->close();
        }
    }
}

// Check if already logged in
$isLoggedIn = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
if ($isLoggedIn) {
    // Redirect to dashboard
    writeLog("User already logged in, redirecting to dashboard");
    redirectTo('admin-dashboard.php');
}

// Retrieve flash message
$flashMessage = getMessage();
if ($flashMessage) {
    if ($flashMessage['type'] == 'error') {
        $error = $flashMessage['message'];
    } else {
        $success = $flashMessage['message'];
    }
}

// ===== PAGE OUTPUT =====
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Salaam Rides</title>
    
    <!-- Load external CSS and JS -->
    <script src="https://cdn.tailwindcss.com"></script> 
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #111827;
            color: #f3f4f6;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #374151;
            border: 1px solid #4b5563;
            border-radius: 0.5rem;
            color: white;
        }
        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }
        .btn-primary {
            width: 100%;
            background-color: #10b981;
            color: white;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #059669;
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-900">
    <div class="fixed inset-0 z-0 opacity-10 pointer-events-none overflow-hidden">
        <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover">
    </div>

    <div class="flex-grow flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full bg-gray-800 p-8 rounded-lg shadow-lg border border-gray-700">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-green-400">Salaam Rides</h1>
                <h2 class="mt-2 text-xl text-gray-300">Admin Dashboard</h2>
                <p class="mt-2 text-sm text-gray-400">Please log in to access the admin panel</p>
            </div>
            
            <!-- Messages area -->
            <div id="message-area">
                <?php if ($error): ?>
                    <?php echo showMessage($error, 'error'); ?>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <?php echo showMessage($success, 'success'); ?>
                <?php endif; ?>
            </div>
            
            <!-- Login form -->
            <form method="post" action="" id="login-form" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                    <input type="text" id="username" name="username" required class="form-input">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                    <input type="password" id="password" name="password" required class="form-input">
                </div>
                
                <div>
                    <button type="submit" class="btn-primary">Sign in</button>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="text-sm text-green-400 hover:text-green-300 transition-colors">
                        ← Return to main website
                    </a>
                </div>
            </form>
            
            <!-- Session debug info (remove in production) -->
            <div class="mt-8 pt-4 border-t border-gray-700 text-xs text-gray-500">
                <p>Session ID: <?php echo session_id(); ?></p>
                <p>Session active: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No'; ?></p>
                <?php if ($isLoggedIn): ?>
                    <p>You appear to be logged in as: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-gray-800 border-t border-gray-700/50 py-4">
        <div class="container mx-auto px-4 text-center text-gray-400 text-sm">
            <p>&copy; <?php echo date('Y'); ?> Salaam Rides. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('login-form');
        
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                
                // Show error message
                const messageArea = document.getElementById('message-area');
                messageArea.innerHTML = '<div class="bg-red-500/20 text-red-400 p-3 rounded-lg text-sm mb-4">Please enter both username and password</div>';
            }
        });
    });
    </script>
</body>
</html>
<?php
// End output buffering and send the page
ob_end_flush();
?>