<?php
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getCurrentPage() {
    $path = $_SERVER['PHP_SELF'];
    $filename = basename($path);
    return str_replace('.php', '', $filename);
}

function isActive($page) {
    $currentPage = getCurrentPage();
    return ($currentPage == $page) ? 'active' : '';
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function formatDateTime($datetime, $format = 'M j, Y · g:i A') {
    $date = new DateTime($datetime);
    return $date->format($format);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function login($email, $password) {
    $conn = dbConnect();
    
    // Add error logging for debugging
    error_log("Login attempt for email: " . $email);
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Store important user info in session
            $_SESSION['user_id'] = $user['id'];
            
            // Remove password before storing
            unset($user['password']);
            $_SESSION['user'] = $user;
            
            error_log("User logged in successfully. User ID: " . $user['id']);
            error_log("Session data after login: " . json_encode($_SESSION));
            
            $prefStmt = $conn->prepare("SELECT notify_email, notify_sms, notify_promotions FROM user_preferences WHERE user_id = ?");
            $prefStmt->bind_param("i", $user['id']);
            $prefStmt->execute();
            $prefResult = $prefStmt->get_result();
            
            if ($prefResult->num_rows > 0) {
                $prefs = $prefResult->fetch_assoc();
                $_SESSION['user']['preferences'] = [
                    'notify_email' => (bool)$prefs['notify_email'],
                    'notify_sms' => (bool)$prefs['notify_sms'],
                    'notify_promotions' => (bool)$prefs['notify_promotions']
                ];
            } else {
                // Create default preferences if none exist
                $defaultPrefsStmt = $conn->prepare("INSERT INTO user_preferences (user_id, notify_email, notify_sms, notify_promotions, created_at) VALUES (?, 1, 1, 0, NOW())");
                $defaultPrefsStmt->bind_param("i", $user['id']);
                $defaultPrefsStmt->execute();
                $defaultPrefsStmt->close();
                
                $_SESSION['user']['preferences'] = [
                    'notify_email' => true,
                    'notify_sms' => true,
                    'notify_promotions' => false
                ];
            }
            
            $prefStmt->close();
            
            // Ensure reward points exist for the user
            $pointsStmt = $conn->prepare("SELECT id FROM reward_points WHERE user_id = ?");
            $pointsStmt->bind_param("i", $user['id']);
            $pointsStmt->execute();
            $pointsResult = $pointsStmt->get_result();
            
            if ($pointsResult->num_rows === 0) {
                $createPointsStmt = $conn->prepare("INSERT INTO reward_points (user_id, points) VALUES (?, 0)");
                $createPointsStmt->bind_param("i", $user['id']);
                $createPointsStmt->execute();
                $createPointsStmt->close();
            }
            
            $pointsStmt->close();
            
            // Check for saved session
            session_regenerate_id(true);
            
            // Close DB connection
            $stmt->close();
            $conn->close();
            
            return true;
        }
    }
    
    error_log("Login failed for email: " . $email);
    
    $stmt->close();
    $conn->close();
    return false;
}

function signup($name, $email, $password, $phone) {
    $conn = dbConnect();
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return false;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $phone);
        $success = $stmt->execute();
        
        if ($success) {
            $userId = $conn->insert_id;
            
            $prefStmt = $conn->prepare("INSERT INTO user_preferences (user_id, notify_email, notify_sms, notify_promotions) VALUES (?, 1, 1, 0)");
            $prefStmt->bind_param("i", $userId);
            $prefStmt->execute();
            $prefStmt->close();
            
            $pointsStmt = $conn->prepare("INSERT INTO reward_points (user_id, points) VALUES (?, 0)");
            $pointsStmt->bind_param("i", $userId);
            $pointsStmt->execute();
            $pointsStmt->close();
            
            $_SESSION['user_id'] = $userId;
            $_SESSION['user'] = [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'created_at' => date('Y-m-d H:i:s'),
                'preferences' => [
                    'notify_email' => true,
                    'notify_sms' => true,
                    'notify_promotions' => false
                ]
            ];
            
            // Log the signup success
            error_log("User signup successful. User ID: " . $userId);
            error_log("Session data after signup: " . json_encode($_SESSION));
            
            $conn->commit();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $stmt->close();
            $conn->close();
            return true;
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Signup error: " . $e->getMessage());
    }
    
    if (isset($stmt)) $stmt->close();
    $conn->close();
    return false;
}

function logout() {
    // Log the logout event
    if (isset($_SESSION['user_id'])) {
        error_log("User logged out. User ID: " . $_SESSION['user_id']);
    }
    
    // Clear all session variables
    $_SESSION = [];
    
    // Clear the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Also clear any remember_me cookies if they exist
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

function getCurrentUser() {
    if (isset($_SESSION['user'])) {
        error_log("Getting current user: " . json_encode($_SESSION['user']));
        return $_SESSION['user'];
    }
    return null;
}

function redirect($location) {
    $fullUrl = SITE_URL . "/" . $location;
    error_log("Redirecting to: " . $fullUrl);
    header("Location: " . $fullUrl);
    exit;
}
?>