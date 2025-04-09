<?php
// session-check.php - For debugging session issues
// Place this file in your root directory and access it directly

// Basic configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session (to see if it works)
session_start();

// Gather session and server information
$info = [
    'session' => [
        'id' => session_id(),
        'status' => session_status(),
        'status_text' => (session_status() === PHP_SESSION_DISABLED ? 'DISABLED' : 
                         (session_status() === PHP_SESSION_NONE ? 'NONE' : 
                         (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'UNKNOWN'))),
        'save_path' => session_save_path(),
        'save_path_writable' => is_writable(session_save_path()),
        'cookie_params' => session_get_cookie_params(),
        'name' => session_name(),
        'module_name' => ini_get('session.save_handler'),
        'lifetime' => ini_get('session.gc_maxlifetime'),
        'admin_logged_in' => isset($_SESSION['admin_id']),
        'admin_id' => $_SESSION['admin_id'] ?? 'Not set',
        'admin_username' => $_SESSION['admin_username'] ?? 'Not set',
        'session_data' => $_SESSION
    ],
    'server' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'request_time' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME'] ?? time()),
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
        'temp_dir' => sys_get_temp_dir(),
        'temp_dir_writable' => is_writable(sys_get_temp_dir()),
        'headers_sent' => headers_sent(),
        'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
        'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ],
    'file_permissions' => [
        'includes_dir' => [
            'exists' => is_dir('includes'),
            'readable' => is_readable('includes'),
            'writable' => is_writable('includes')
        ],
        'config_php' => [
            'exists' => file_exists('includes/config.php'),
            'readable' => is_readable('includes/config.php'),
            'size' => file_exists('includes/config.php') ? filesize('includes/config.php') : 0
        ],
        'admin_functions_php' => [
            'exists' => file_exists('includes/admin-functions.php'),
            'readable' => is_readable('includes/admin-functions.php'),
            'size' => file_exists('includes/admin-functions.php') ? filesize('includes/admin-functions.php') : 0
        ],
        'process_admin_driver_php' => [
            'exists' => file_exists('process-admin-driver.php'),
            'readable' => is_readable('process-admin-driver.php'),
            'size' => file_exists('process-admin-driver.php') ? filesize('process-admin-driver.php') : 0
        ]
    ]
];

// Test if we can write to a session file
if ($info['session']['status_text'] === 'ACTIVE') {
    $_SESSION['test_key'] = 'test_value_' . time();
    $info['session']['test_write'] = isset($_SESSION['test_key']) ? 'Success' : 'Failed';
} else {
    $info['session']['test_write'] = 'Session not active';
}

// Test cookie writing
if (!headers_sent()) {
    setcookie('test_cookie', 'test_value', time() + 3600, '/');
    $info['cookie_test'] = 'Cookie header sent';
} else {
    $info['cookie_test'] = 'Headers already sent, cannot set cookie';
}

// Output as formatted HTML for easy reading
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; color: #333; max-width: 1200px; margin: 0 auto; }
        h1, h2 { color: #0066cc; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .detail-section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Session Diagnostics</h1>
    
    <div class="detail-section">
        <h2>Quick Status</h2>
        <table>
            <tr>
                <th>Session Active</th>
                <td class="<?php echo $info['session']['status_text'] === 'ACTIVE' ? 'success' : 'error'; ?>">
                    <?php echo $info['session']['status_text']; ?>
                </td>
            </tr>
            <tr>
                <th>Session ID</th>
                <td><?php echo $info['session']['id'] ?: 'Not set'; ?></td>
            </tr>
            <tr>
                <th>Admin Logged In</th>
                <td class="<?php echo $info['session']['admin_logged_in'] ? 'success' : 'warning'; ?>">
                    <?php echo $info['session']['admin_logged_in'] ? 'Yes' : 'No'; ?>
                </td>
            </tr>
            <tr>
                <th>Session Path Writable</th>
                <td class="<?php echo $info['session']['save_path_writable'] ? 'success' : 'error'; ?>">
                    <?php echo $info['session']['save_path_writable'] ? 'Yes' : 'NO - THIS IS A PROBLEM'; ?>
                </td>
            </tr>
            <tr>
                <th>HTTPS</th>
                <td><?php echo $info['server']['https'] ? 'Yes' : 'No'; ?></td>
            </tr>
        </table>
    </div>
    
    <div class="detail-section">
        <h2>Session Details</h2>
        <table>
            <?php foreach ($info['session'] as $key => $value): ?>
                <?php if ($key !== 'session_data'): ?>
                <tr>
                    <th><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></th>
                    <td>
                        <?php 
                        if (is_bool($value)) {
                            echo $value ? 'True' : 'False';
                        } elseif (is_array($value)) {
                            echo '<pre>' . htmlspecialchars(print_r($value, true)) . '</pre>';
                        } else {
                            echo htmlspecialchars($value);
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
        
        <h3>Session Data</h3>
        <pre><?php echo htmlspecialchars(print_r($info['session']['session_data'], true)); ?></pre>
    </div>
    
    <div class="detail-section">
        <h2>Server Information</h2>
        <table>
            <?php foreach ($info['server'] as $key => $value): ?>
            <tr>
                <th><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></th>
                <td>
                    <?php 
                    if (is_bool($value)) {
                        echo $value ? 'True' : 'False';
                    } else {
                        echo htmlspecialchars($value);
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="detail-section">
        <h2>File Access & Permissions</h2>
        <table>
            <tr>
                <th>File/Directory</th>
                <th>Exists</th>
                <th>Readable</th>
                <th>Writable</th>
                <th>Size</th>
            </tr>
            <?php foreach ($info['file_permissions'] as $file => $props): ?>
            <tr>
                <td><?php echo htmlspecialchars($file); ?></td>
                <td class="<?php echo $props['exists'] ? 'success' : 'error'; ?>">
                    <?php echo $props['exists'] ? 'Yes' : 'No'; ?>
                </td>
                <td class="<?php echo ($props['exists'] && $props['readable']) ? 'success' : 'error'; ?>">
                    <?php echo ($props['exists'] && $props['readable']) ? 'Yes' : 'No'; ?>
                </td>
                <td>
                    <?php echo isset($props['writable']) ? ($props['writable'] ? 'Yes' : 'No') : 'N/A'; ?>
                </td>
                <td>
                    <?php echo isset($props['size']) ? ($props['size'] . ' bytes') : 'N/A'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <p><strong>Note:</strong> This page contains sensitive system information. Delete it or restrict access when finished.</p>
</body>
</html>