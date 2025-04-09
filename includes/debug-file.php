<?php
/**
 * !!!!! SECURITY WARNING !!!!!
 * 
 * This debugging utility can expose sensitive system information.
 * NEVER leave this file accessible in a production environment.
 * 
 * Recommended Usage:
 * 1. Only use during local development or urgent troubleshooting
 * 2. Immediately remove or disable after use
 * 3. NEVER share the full output publicly
 * 
 * Debugging Utility for Salaam Rides
 * Helps diagnose configuration, session, and login issues
 */

// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

class SalaamRidesDebug {
    private $debugLog = [];
    private $configFile = 'config.php';
    private $dbFile = 'db.php';
    private $functionsFile = 'functions.php';

    public function __construct() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Run comprehensive system check
     */
    public function runSystemCheck() {
        $this->checkPHPConfiguration();
        $this->checkSessionConfiguration();
        $this->checkDatabaseConnection();
        $this->checkConfigFiles();
        $this->checkLoginCredentials();
        
        return $this->debugLog;
    }

    /**
     * Check PHP Configuration
     */
    private function checkPHPConfiguration() {
        $this->debugLog['php_config'] = [
            'php_version' => PHP_VERSION,
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'log_errors' => ini_get('log_errors'),
            'error_log' => ini_get('error_log'),
            'session_save_path' => session_save_path(),
            'timezone' => date_default_timezone_get()
        ];
    }

    /**
     * Check Session Configuration
     */
    private function checkSessionConfiguration() {
        $sessionConfig = session_get_cookie_params();
        
        $this->debugLog['session_config'] = [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'lifetime' => $sessionConfig['lifetime'],
            'path' => $sessionConfig['path'],
            'domain' => $sessionConfig['domain'],
            'secure' => $sessionConfig['secure'] ? 'Yes' : 'No',
            'httponly' => $sessionConfig['httponly'] ? 'Yes' : 'No',
            'current_session_data' => $_SESSION
        ];
    }

    /**
     * Check Database Connection
     */
    private function checkDatabaseConnection() {
        try {
            require_once 'config.php';
            require_once 'db.php';
            
            $conn = dbConnect();
            
            $this->debugLog['database_config'] = [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'connection_status' => 'Successful',
                'server_info' => $conn->server_info,
                'client_info' => $conn->client_info,
                'host_info' => $conn->host_info
            ];
            
            $conn->close();
        } catch (Exception $e) {
            $this->debugLog['database_config'] = [
                'connection_status' => 'Failed',
                'error_message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check Configuration Files
     */
    private function checkConfigFiles() {
        $configChecks = [
            'config.php' => $this->configFile,
            'db.php' => $this->dbFile,
            'functions.php' => $this->functionsFile
        ];

        foreach ($configChecks as $name => $file) {
            $this->debugLog['file_checks'][$name] = [
                'exists' => file_exists($file),
                'readable' => is_readable($file),
                'size' => file_exists($file) ? filesize($file) : 0
            ];
        }
    }

    /**
     * Verify Login Credentials
     * Add a method to safely test admin credentials without exposing sensitive info
     */
    private function checkLoginCredentials() {
        try {
            $conn = dbConnect();
            
            // Check total number of admin accounts
            $adminQuery = "SELECT COUNT(*) as admin_count FROM admins";
            $adminStmt = $conn->prepare($adminQuery);
            $adminStmt->execute();
            $adminResult = $adminStmt->get_result()->fetch_assoc();
            
            // Check for weak or default credentials
            $weakCredentialsQuery = "
                SELECT username FROM admins 
                WHERE 
                    username IN ('admin', 'administrator', 'root') OR 
                    LENGTH(password) < 60 OR 
                    password = '' OR 
                    password IS NULL
            ";
            $weakStmt = $conn->prepare($weakCredentialsQuery);
            $weakStmt->execute();
            $weakResults = $weakStmt->get_result();
            
            $this->debugLog['login_test'] = [
                'admin_account_count' => $adminResult['admin_count'],
                'weak_credentials_found' => $weakResults->num_rows > 0,
                'weak_credential_usernames' => [],
                'recommended_actions' => [
                    'Minimize number of admin accounts',
                    'Enforce strong password requirements',
                    'Enable multi-factor authentication',
                    'Regularly audit admin credentials'
                ]
            ];
            
            // Collect weak usernames if any found
            while ($row = $weakResults->fetch_assoc()) {
                $this->debugLog['login_test']['weak_credential_usernames'][] = $row['username'];
            }
            
            $adminStmt->close();
            $weakStmt->close();
            $conn->close();
        } catch (Exception $e) {
            $this->debugLog['login_test'] = [
                'error' => 'Unable to check login credentials',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a secure token for debugging session
     */
    public function generateDebugToken() {
        $token = bin2hex(random_bytes(16));
        $_SESSION['debug_token'] = $token;
        return $token;
    }

    /**
     * Output debug information
     * @param bool $asJson Whether to return as JSON
     */
    /**
     * Perform a stress test on login credentials
     * WARNING: This method should ONLY be used in controlled environments
     */
    public function performCredentialStressTest() {
        require_once 'config.php';
        require_once 'db.php';
        
        $testCredentials = [
            ['username' => 'admin', 'password' => 'admin'],
            ['username' => 'administrator', 'password' => 'password'],
            ['username' => 'root', 'password' => 'root'],
            ['username' => '', 'password' => ''],
        ];
        
        $stressTestResults = [
            'vulnerable_credentials' => []
        ];
        
        try {
            $conn = dbConnect();
            
            foreach ($testCredentials as $cred) {
                $query = "SELECT * FROM admins WHERE username = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $cred['username']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    
                    // Attempt password verification
                    if (password_verify($cred['password'], $admin['password'])) {
                        $stressTestResults['vulnerable_credentials'][] = [
                            'username' => $cred['username'],
                            'status' => 'VULNERABLE'
                        ];
                    }
                }
                
                $stmt->close();
            }
            
            $conn->close();
        } catch (Exception $e) {
            $stressTestResults['error'] = $e->getMessage();
        }
        
        return $stressTestResults;
    }

    public function outputDebug($asJson = false) {
        $debugInfo = $this->runSystemCheck();
        
        // Add stress test results to debug info if admin is logged in
        if (isset($_SESSION['admin_id'])) {
            $debugInfo['credential_stress_test'] = $this->performCredentialStressTest();
        }
        
        if ($asJson) {
            header('Content-Type: application/json');
            echo json_encode($debugInfo, JSON_PRETTY_PRINT);
        } else {
            echo "<pre>";
            print_r($debugInfo);
            echo "</pre>";
        }
    }

    /**
     * Safety check before rendering debug info
     */
    public function renderDebugInfo() {
        // Strict admin-only access
        if (!isset($_SESSION['admin_id'])) {
            die("Access Denied: Administrator login required.");
        }

        // Optional: Additional IP restriction
        $allowedIPs = ['127.0.0.1', $_SERVER['SERVER_ADDR']];
        if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
            die("Access Denied: Debug mode restricted to local/server IPs.");
        }

        $this->outputDebug(false);
    }
}

// Check if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $debug = new SalaamRidesDebug();
    $debug->renderDebugInfo();
}
