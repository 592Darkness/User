<?php
/**
 * Database Connection Tester
 * 
 * This file checks your database connection and verifies table structure.
 * Place this file in your project root and access it through your browser.
 * DELETE THIS FILE AFTER USING IT FOR SECURITY REASONS.
 */

// Include your config and db files
require_once 'includes/config.php';
require_once 'includes/db.php';

// Set up error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Header for better readability
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salaam Rides - Database Checker</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1, h2, h3 { color: #10b981; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #dc2626; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Salaam Rides - Database Connection Checker</h1>
    
    <div class="section">
        <h2>1. Database Connection Test</h2>
        <?php
        try {
            $conn = dbConnect();
            echo "<p class='success'>✅ Successfully connected to database: " . DB_NAME . "</p>";
            
            // Get database info
            $result = $conn->query("SELECT VERSION() as version");
            $version = $result->fetch_assoc()['version'];
            
            echo "<p>Database Version: " . htmlspecialchars($version) . "</p>";
            echo "<p>Database Charset: " . htmlspecialchars($conn->character_set_name()) . "</p>";
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            die("Database connection test failed. Fix this issue before continuing.");
        }
        ?>
    </div>
    
    <div class="section">
        <h2>2. Table Structure Check</h2>
        <?php
        $requiredTables = [
            'users', 'drivers', 'rides', 'ride_ratings', 
            'driver_ratings', 'reward_points', 'saved_places',
            'user_preferences', 'driver_preferences', 'driver_payments',
            'driver_locations'
        ];
        
        echo "<h3>Checking required tables:</h3>";
        echo "<ul>";
        
        $missingTables = [];
        $existingTables = [];
        
        foreach ($requiredTables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            
            if ($result->num_rows > 0) {
                echo "<li class='success'>✅ Table exists: $table</li>";
                $existingTables[] = $table;
            } else {
                echo "<li class='error'>❌ Missing table: $table</li>";
                $missingTables[] = $table;
            }
        }
        
        echo "</ul>";
        
        if (!empty($missingTables)) {
            echo "<p class='warning'>Some required tables are missing. Please create them before continuing.</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. Sample Data Check</h2>
        
        <?php
        if (!empty($existingTables)) {
            echo "<h3>Checking for real data in tables:</h3>";
            echo "<table>";
            echo "<tr><th>Table</th><th>Row Count</th><th>Status</th></tr>";
            
            foreach ($existingTables as $table) {
                $result = $conn->query("SELECT COUNT(*) as count FROM $table");
                $count = $result->fetch_assoc()['count'];
                
                $status = $count > 0 ? 
                    "<span class='success'>Contains data</span>" : 
                    "<span class='warning'>Empty</span>";
                
                echo "<tr><td>$table</td><td>$count</td><td>$status</td></tr>";
            }
            
            echo "</table>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. Mock Data Analysis</h2>
        <p>The following files contain simulated/mock data that should be replaced with real database data:</p>
        <ul>
            <li><strong>api/driver-eta.php</strong> - Contains <code>generateSimulatedLocation()</code> function which returns random locations</li>
            <li><strong>api/driver-available-rides.php</strong> - Uses word count to simulate ride distances instead of real coordinates</li>
            <li><strong>api/driver-earnings.php</strong> - Creates synthetic payment records when none exist</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>5. Recommended Actions</h2>
        <ol>
            <?php if (!empty($missingTables)): ?>
                <li class="error">Create the missing database tables listed above</li>
            <?php endif; ?>
            <li>Update <code>api/driver-eta.php</code> to use real driver location data</li>
            <li>Update <code>api/driver-available-rides.php</code> to calculate actual distances using coordinates</li>
            <li>Modify <code>api/driver-earnings.php</code> to only display real payment records</li>
            <li>Ensure all API endpoints properly handle empty result sets without adding mock data</li>
        </ol>
    </div>
    
    <footer>
        <p><strong>Important:</strong> Delete this file after use for security reasons.</p>
    </footer>
    
    <?php
    // Close the database connection
    if (isset($conn)) {
        $conn->close();
    }
    ?>
</body>
</html>