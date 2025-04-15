<?php
/**
 * Database connection diagnostic tool
 * Place this file in your api directory to test database connectivity
 */

// Enable detailed error reporting for diagnosis
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set Content-Type header
header('Content-Type: text/html');

echo "<h1>Database Connection Diagnostic</h1>";

// Test 1: Check if config.php exists and can be loaded
echo "<h2>Test 1: Loading configuration files</h2>";
try {
    if (file_exists('../includes/config.php')) {
        echo "<p>✅ config.php file exists</p>";
        
        // Include necessary files
        require_once '../includes/config.php';
        echo "<p>✅ config.php loaded successfully</p>";
        
        // Check if DB constants are defined
        echo "<p>Checking database constants:</p>";
        echo "<ul>";
        echo "<li>DB_HOST defined: " . (defined('DB_HOST') ? "✅ Yes" : "❌ No") . "</li>";
        echo "<li>DB_USER defined: " . (defined('DB_USER') ? "✅ Yes" : "❌ No") . "</li>";
        echo "<li>DB_PASS defined: " . (defined('DB_PASS') ? "✅ Yes" : "❌ No") . "</li>";
        echo "<li>DB_NAME defined: " . (defined('DB_NAME') ? "✅ Yes" : "❌ No") . "</li>";
        echo "</ul>";
        
        // Load db.php
        if (file_exists('../includes/db.php')) {
            echo "<p>✅ db.php file exists</p>";
            require_once '../includes/db.php';
            echo "<p>✅ db.php loaded successfully</p>";
        } else {
            echo "<p>❌ db.php file not found</p>";
            die("Critical error: db.php not found");
        }
        
    } else {
        echo "<p>❌ config.php file not found</p>";
        die("Critical error: config.php not found");
    }
} catch (Exception $e) {
    echo "<p>❌ Error loading configuration: " . htmlspecialchars($e->getMessage()) . "</p>";
    die("Critical error loading configuration");
}

// Test 2: Test direct database connection
echo "<h2>Test 2: Direct database connection</h2>";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        echo "<p>❌ Direct connection failed: " . htmlspecialchars($conn->connect_error) . "</p>";
        
        // Test if the host is reachable
        echo "<p>Testing if host is reachable...</p>";
        $socket = @fsockopen(DB_HOST, 3306, $errno, $errstr, 5);
        if (!$socket) {
            echo "<p>❌ Cannot connect to database host: $errstr ($errno)</p>";
        } else {
            echo "<p>✅ Database host is reachable</p>";
            fclose($socket);
        }
        
        die("Database connection failed");
    } else {
        echo "<p>✅ Direct database connection successful</p>";
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p>❌ Exception during direct connection: " . htmlspecialchars($e->getMessage()) . "</p>";
    die("Database connection failed with exception");
}

// Test 3: Test database connection using dbConnect function
echo "<h2>Test 3: Using dbConnect function</h2>";
try {
    $conn = dbConnect();
    echo "<p>✅ dbConnect() successful</p>";
    
    // Test a simple query
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<p>✅ Simple query successful</p>";
        echo "<p>Tables in database:</p>";
        echo "<ul>";
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_row()) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>";
            }
        } else {
            echo "<li>No tables found</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ Simple query failed: " . htmlspecialchars($conn->error) . "</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p>❌ Exception using dbConnect: " . htmlspecialchars($e->getMessage()) . "</p>";
    die("dbConnect() failed with exception");
}

// Test 4: Check for required tables
echo "<h2>Test 4: Check for required tables</h2>";
try {
    $conn = dbConnect();
    
    $requiredTables = [
        'api_logs',
        'distance_cache',
        'fare_rates'
    ];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<p>✅ Table '$table' exists</p>";
            
            // Check table structure
            $columns = $conn->query("SHOW COLUMNS FROM $table");
            if ($columns) {
                $columnCount = $columns->num_rows;
                echo "<p>   Table has $columnCount columns</p>";
            }
        } else {
            echo "<p>❌ Table '$table' does not exist</p>";
            
            // Try to create the table based on its name
            echo "<p>Attempting to create table '$table'...</p>";
            
            $created = false;
            
            if ($table === 'api_logs') {
                $sql = "CREATE TABLE IF NOT EXISTS api_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    api_name VARCHAR(100) NOT NULL,
                    request_data TEXT,
                    response_data TEXT,
                    success TINYINT(1) NOT NULL DEFAULT 0,
                    error_message TEXT,
                    created_at DATETIME NOT NULL,
                    INDEX idx_api_name (api_name(50)),
                    INDEX idx_created_at (created_at)
                )";
                $created = $conn->query($sql);
            } else if ($table === 'distance_cache') {
                $sql = "CREATE TABLE IF NOT EXISTS distance_cache (
                    cache_key CHAR(32) PRIMARY KEY,
                    origin TEXT NOT NULL,
                    destination TEXT NOT NULL,
                    result TEXT NOT NULL,
                    created_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    INDEX idx_expires (expires_at)
                )";
                $created = $conn->query($sql);
            } else if ($table === 'fare_rates') {
                $sql = "CREATE TABLE IF NOT EXISTS fare_rates (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    vehicle_type VARCHAR(50) NOT NULL,
                    base_rate INT NOT NULL,
                    price_per_km INT NOT NULL,
                    minimum_fare INT NOT NULL,
                    multiplier DECIMAL(3,1) NOT NULL DEFAULT 1.0,
                    active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY idx_vehicle_type (vehicle_type)
                )";
                $created = $conn->query($sql);
            }
            
            if ($created) {
                echo "<p>✅ Table '$table' created successfully</p>";
                
                // Insert default data if necessary
                if ($table === 'fare_rates') {
                    $insertSql = "INSERT IGNORE INTO fare_rates 
                                (vehicle_type, base_rate, price_per_km, minimum_fare, multiplier, active, created_at) 
                                VALUES 
                                ('standard', 1000, 200, 1500, 1.0, 1, NOW()),
                                ('suv', 1500, 300, 2000, 1.5, 1, NOW()),
                                ('premium', 2000, 400, 2500, 2.0, 1, NOW())";
                    if ($conn->query($insertSql)) {
                        echo "<p>✅ Default fare rates inserted</p>";
                    } else {
                        echo "<p>❌ Failed to insert default fare rates: " . htmlspecialchars($conn->error) . "</p>";
                    }
                }
                
            } else {
                echo "<p>❌ Failed to create table: " . htmlspecialchars($conn->error) . "</p>";
            }
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p>❌ Exception checking tables: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Test fare estimation function
echo "<h2>Test 5: Test fare calculation functions</h2>";
try {
    require_once '../includes/calculate-distance.php';
    echo "<p>✅ calculate-distance.php loaded successfully</p>";
    
    // Test calculating fare without involving Google Maps API
    $vehicleTypes = ['standard', 'suv', 'premium'];
    foreach ($vehicleTypes as $vehicleType) {
        echo "<h3>Testing fare calculation for $vehicleType vehicle</h3>";
        
        $distance = 10.5; // 10.5 km
        $conn = dbConnect();
        
        $fare = calculateFare($distance, $vehicleType, $conn);
        
        echo "<pre>";
        print_r($fare);
        echo "</pre>";
        
        $conn->close();
    }
    
} catch (Exception $e) {
    echo "<p>❌ Exception testing fare calculation: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 6: JSON data format for API
echo "<h2>Test 6: Test API JSON format</h2>";
try {
    $testData = [
        'success' => true,
        'message' => 'Test successful',
        'data' => [
            'fare' => 'G$2,500',
            'details' => [
                'distance' => [
                    'value' => 10.5,
                    'text' => '10.5 km'
                ],
                'base_fare' => 1000,
                'distance_fare' => 2100,
                'vehicle_multiplier' => 1.0,
                'total' => 3100
            ]
        ]
    ];
    
    $jsonString = json_encode($testData);
    if ($jsonString === false) {
        echo "<p>❌ JSON encoding failed: " . htmlspecialchars(json_last_error_msg()) . "</p>";
    } else {
        echo "<p>✅ JSON encoding successful</p>";
        echo "<pre>" . htmlspecialchars($jsonString) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p>❌ Exception testing JSON: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Recommendations</h2>";
echo "<p>Based on these tests, here are some recommendations:</p>";
echo "<ol>";
echo "<li>Check if the database server is running and accessible from your web server</li>";
echo "<li>Verify the database credentials in config.php</li>";
echo "<li>Make sure your database user has permission to create tables</li>";
echo "<li>Check if JSON extension is enabled in PHP</li>";
echo "<li>Ensure PHP has the mysqli extension installed</li>";
echo "<li>Check PHP error logs for more detailed error messages</li>";
echo "</ol>";

echo "<p>Once you've fixed any issues found by this diagnostic, your fare calculation API should work properly.</p>";
?></document_content>
<parameter name="language">php