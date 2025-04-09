<?php
// config_test.php - Test inclusion of config.php and session path writability

error_reporting(E_ALL);
ini_set('display_errors', 1); // Attempt to display errors directly

echo "<!DOCTYPE html><html><head><title>Config Test</title><style>body{font-family: sans-serif; padding: 1em;} .ok{color: green;} .error{color: red;} .warn{color: orange;}</style></head><body>";
echo "<h1>Config & Session Path Test</h1>";

// --- 1. Check Session Save Path BEFORE including config.php ---
echo "<h2>1. Session Save Path Check</h2>";
$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
    echo "<p>Session save path determined as: " . htmlspecialchars($session_path) . " (System Temp)</p>";
} else {
    echo "<p>Session save path configured as: " . htmlspecialchars($session_path) . "</p>";
}

if (is_writable($session_path)) {
    echo "<p class='ok'>✅ Session path IS WRITABLE by the web server process.</p>";
    $path_writable = true;
} else {
    echo "<p class='error'>❌ Session path IS NOT WRITABLE by the web server process. This is likely the cause of the 500 errors!</p>";
    $path_writable = false;
    // You can add instructions here, e.g., "Please check server permissions for this directory."
}
echo "<hr>";

// --- 2. Attempt to Include config.php ---
echo "<h2>2. Including includes/config.php</h2>";
$config_path = __DIR__ . '/includes/config.php'; // Assumes this file is in public_html
echo "<p>Attempting to include: " . htmlspecialchars($config_path) . "</p>";

if (!file_exists($config_path)) {
    echo "<p class='error'>❌ File not found: includes/config.php</p>";
} elseif (!is_readable($config_path)) {
    echo "<p class='error'>❌ File not readable: includes/config.php (Check permissions)</p>";
} else {
    try {
        // Use include instead of require_once for this test to see potential warnings
        include($config_path);
        echo "<p class='ok'>✅ includes/config.php included successfully.</p>";

        // --- 3. Check Session Status AFTER including config.php ---
        echo "<h2>3. Session Status After Include</h2>";
        if (session_status() === PHP_SESSION_ACTIVE) {
            echo "<p class='ok'>✅ Session is ACTIVE.</p>";
            echo "<p>Session ID: " . session_id() . "</p>";
            echo "<p>Session Data:</p><pre>" . htmlspecialchars(print_r($_SESSION, true)) . "</pre>";
            if (isset($_SESSION['driver_id'])) {
                 echo "<p class='ok'>✅ Driver ID found in session: " . htmlspecialchars($_SESSION['driver_id']) . "</p>";
            } else {
                 echo "<p class='warn'>⚠️ Session active, but 'driver_id' NOT found in session.</p>";
            }
        } else {
            echo "<p class='error'>❌ Session is NOT ACTIVE after including config.php.</p>";
             if ($path_writable) {
                 echo "<p class='warn'>This might indicate an error within the session_start() logic inside config.php, or headers already being sent before session_start() was called.</p>";
             } else {
                  echo "<p class='error'>This is expected because the session path is not writable.</p>";
             }
        }

    } catch (Throwable $t) { // Catch ParseError, Error, Exception
        echo "<p class='error'>❌ FATAL ERROR during inclusion of includes/config.php:</p>";
        echo "<pre>" . htmlspecialchars($t->getMessage()) . "\nFile: " . htmlspecialchars($t->getFile()) . "\nLine: " . $t->getLine() . "</pre>";
    }
}

echo "</body></html>";
?>