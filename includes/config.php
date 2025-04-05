<?php
/**
 * Configuration file for Salaam Rides
 * Enhanced with better error handling and character set settings
 */

// Set error handling options
// In production, set display_errors to 0
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set default character set for PHP
ini_set('default_charset', 'UTF-8');

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');

// Check if session is already active before trying to configure
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    $currentCookieParams = session_get_cookie_params();
    session_set_cookie_params(
        $currentCookieParams["lifetime"],
        $currentCookieParams["path"],
        $currentCookieParams["domain"],
        $currentCookieParams["secure"],
        true // httponly flag
    );
    
    // Now start the session
    session_start();
}

// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'u169889364_Salaamrides');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'Welcome72022@@');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'u169889364_Salaamrides');
}

// Database charset settings
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// Site configuration
define('SITE_NAME', 'salaamrides');
define('SITE_URL', 'https://autositetest.com');

// API Keys
define('GOOGLE_MAPS_API_KEY', 'AIzaSyA-6uXAa6MkIMwlYYwMIVBq5s3T0aTh0EI');

// Timezone setting
date_default_timezone_set('America/New_York');

// Path definitions
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDE_PATH', ROOT_PATH . '/includes');
define('ASSET_PATH', ROOT_PATH . '/assets');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');

// Create log directory if it doesn't exist
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Helper function for asset URLs
function asset($path) {
    return SITE_URL . '/assets/' . $path;
}

// Helper function for constructing full URLs
function url($path = '') {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

// Set up default headers for security
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Set default content type to UTF-8
header('Content-Type: text/html; charset=utf-8');
?>