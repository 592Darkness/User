<?php
// Fix session handling to ensure consistent session IDs across requests
if (session_status() === PHP_SESSION_NONE) {
    // Set up proper session parameters
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    
    // Be more permissive with session lifetime
    ini_set('session.gc_maxlifetime', 7200); // 2 hours
    
    // Set cookie parameters BEFORE starting the session
    session_set_cookie_params([
        'lifetime' => 0,                    // Until browser is closed
        'path' => '/',                      // Available on entire domain
        'domain' => '',                     // Current domain only
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,                 // Not accessible via JavaScript
        'samesite' => 'Lax'                 // Helps with CSRF protection
    ]);
    
    // Start the session with the right settings
    session_start();
    
    // Regenerate session ID if it's older than 30 minutes to prevent fixation
    if (isset($_SESSION['last_regeneration']) && 
        time() - $_SESSION['last_regeneration'] > 1800) {
        
        // Save old session data
        $old_session_data = $_SESSION;
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        // Restore session data
        $_SESSION = $old_session_data;
        
        // Update regeneration time
        $_SESSION['last_regeneration'] = time();
    }
    
    // First time session creation
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    }
} else if (session_status() === PHP_SESSION_ACTIVE) {
    // Session already active, just ensure we have a regeneration timestamp
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    }
}

// Database configuration (remains the same)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'u169889364_Salaamrides');
if (!defined('DB_PASS')) define('DB_PASS', 'Welcome72022@@');
if (!defined('DB_NAME')) define('DB_NAME', 'u169889364_Salaamrides');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// Site configuration (remains the same)
define('SITE_NAME', 'salaamrides');
define('SITE_URL', 'https://autositetest.com');

// API Keys (remains the same)
define('Maps_API_KEY', 'AIzaSyA-6uXAa6MkIMwlYYwMIVBq5s3T0aTh0EI');
define('Maps_SERVER_API_KEY', 'AIzaSyDSgFXlMiN-32DnmTbCqfLY7FhwwDebXgk');

// Timezone setting (remains the same)
date_default_timezone_set('America/New_York');

// Path definitions (remains the same)
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('ASSET_PATH', ROOT_PATH . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Create directories if they don't exist (remains the same)
if (!is_dir(LOG_PATH)) @mkdir(LOG_PATH, 0755, true);
if (!is_dir(UPLOAD_PATH)) @mkdir(UPLOAD_PATH, 0755, true);

// Helper functions (remains the same)
function asset($path) { return SITE_URL . '/assets/' . ltrim($path, '/'); }
function url($path = '') { return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/'); }

// Security headers (remains the same, ensure called only once)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}
?>