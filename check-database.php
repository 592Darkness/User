<?php
/**
 * Database Check Tool
 * This script checks if you have the necessary database tables and data for the payment confirmation system.
 * Save this as check-database.php in your project root and access it to verify your setup.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Only allow this script to run in development mode
$isDevelopment = true; // Set this to false in production

if (!$isDevelopment) {
    die("This script is for development use only.");
}

// Initialize response
$results = [];

// Connect to database
try {
    $conn = dbConnect();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to check table existence
function tableExists($tableName, $conn) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Function to get column names
function getTableColumns($tableName, $conn) {
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM $tableName");
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    return $columns;
}

// Check for required tables and create if missing
$requiredTables = [
    'rides' => [
        'id', 'user_id', 'driver_id', 'pickup', 'dropoff', 'fare', 'final_fare', 
        'status', 'payment_status', 'payment_method', 'created_at', 'completed_at'
    ],
    'payments' => [
        'id', 'user_id', 'ride_id', 'amount', 'payment_method', 'status', 
        'transaction_id', 'created_at'
    ],
    'driver_payments' => [
        'id', 'driver_id', 'ride_id', 'amount', 'status', 'payment_method', 
        'description', 'created_at'
    ],
    'ride_logs' => [
        'id', 'ride_id', 'user_id', 'driver_id', 'action', 'details', 'created_at'
    ]
];

// Check each required table
foreach ($requiredTables as $tableName => $requiredColumns) {
    $tableResult = [
        'table_name' => $tableName,
        'exists' => tableExists($tableName, $conn)
    ];
    
    if ($tableResult['exists']) {
        $tableResult['columns'] = getTableColumns($tableName, $conn);
        $tableResult['missing_columns'] = array_diff($requiredColumns, $tableResult['columns']);
        $tableResult['has_required_columns'] = count($tableResult['missing_columns']) === 0;
    } else {
        $tableResult['columns'] = [];
        $tableResult['missing_columns'] = $requiredColumns;
        $tableResult['has_required_columns'] = false;
    }
    
    $results['tables'][] = $tableResult;
}

// Check for pending rides that need payment confirmation
$pendingPaymentsQuery = "
    SELECT r.id as ride_id, r.user_id, r.driver_id, r.status, r.payment_status, 
           r.fare, r.final_fare, r.completed_at, r.payment_method,
           u.name as user_name, d.name as driver_name
    FROM rides r
    JOIN users u ON r.user_id = u.id
    JOIN drivers d ON r.driver_id = d.id
    WHERE r.status = 'completed' 
    AND r.payment_status IN ('pending', 'customer_confirmed', 'driver_confirmed')
    ORDER BY r.completed_at DESC
    LIMIT 10
";

try {
    $pendingResult = $conn->query($pendingPaymentsQuery);
    $pendingPayments = [];
    
    if ($pendingResult) {
        while ($row = $pendingResult->fetch_assoc()) {
            $pendingPayments[] = $row;
        }
    }
    
    $results['pending_payments'] = [
        'count' => count($pendingPayments),
        'data' => $pendingPayments
    ];
} catch (Exception $e) {
    $results['pending_payments'] = [
        'error' => $e->getMessage()
    ];
}

// Test query to insert a sample payment confirmation record for driver 1
$testData = [
    'success' => false,
    'message' => 'No test run'
];

if (isset($_GET['run_test']) && $_GET['run_test'] === 'yes') {
    try {
        // First find a completed ride
        $findRideQuery = "
            SELECT id, driver_id, user_id 
            FROM rides 
            WHERE status = 'completed' 
            AND (payment_status IS NULL OR payment_status = 'pending')
            ORDER BY completed_at DESC
            LIMIT 1
        ";
        
        $rideResult = $conn->query($findRideQuery);
        if ($rideResult && $rideResult->num_rows > 0) {
            $ride = $rideResult->fetch_assoc();
            
            // Update the ride's payment status to need confirmation
            $updateQuery = "
                UPDATE rides 
                SET payment_status = 'customer_confirmed',
                    payment_method = 'cash'
                WHERE id = ?
            ";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("i", $ride['id']);
            $success = $stmt->execute();
            
            if ($success) {
                $testData = [
                    'success' => true,
                    'message' => "Created test payment confirmation for ride #{$ride['id']}",
                    'ride_id' => $ride['id'],
                    'driver_id' => $ride['driver_id'],
                    'user_id' => $ride['user_id']
                ];
            } else {
                $testData = [
                    'success' => false,
                    'message' => "Failed to update ride: " . $stmt->error
                ];
            }
            
            $stmt->close();
        } else {
            $testData = [
                'success' => false,
                'message' => "No eligible rides found for testing"
            ];
        }
    } catch (Exception $e) {
        $testData = [
            'success' => false,
            'message' => "Error running test: " . $e->getMessage()
        ];
    }
}

$results['test_run'] = $testData;

// Close connection
$conn->close();

// Output results as HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Check Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1, h2, h3 {
            color: #10b981;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f0f0f0;
        }
        .success {
            color: #10b981;
        }
        .error {
            color: #ef4444;
        }
        .warning {
            color: #f59e0b;
        }
        .button {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 10px;
        }
        .button:hover {
            background-color: #059669;
        }
        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>Database Check Tool</h1>
    
    <div class="card">
        <h2>Table Status</h2>
        <table>
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                    <th>Missing Columns</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results['tables'] as $table): ?>
                <tr>
                    <td><?php echo htmlspecialchars($table['table_name']); ?></td>
                    <td class="<?php echo $table['exists'] ? 'success' : 'error'; ?>">
                        <?php echo $table['exists'] ? 'Exists' : 'Missing'; ?>
                        <?php if ($table['exists'] && !$table['has_required_columns']): ?>
                            <span class="warning">(Incomplete)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($table['missing_columns'])): ?>
                            <span class="error"><?php echo implode(', ', $table['missing_columns']); ?></span>
                        <?php else: ?>
                            <span class="success">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <h2>Pending Payment Confirmations</h2>
        <?php if (isset($results['pending_payments']['error'])): ?>
            <p class="error">Error: <?php echo htmlspecialchars($results['pending_payments']['error']); ?></p>
        <?php else: ?>
            <p>Found <?php echo $results['pending_payments']['count']; ?> pending payment(s) that need confirmation.</p>
            
            <?php if ($results['pending_payments']['count'] > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ride ID</th>
                            <th>Customer</th>
                            <th>Driver</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Completed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['pending_payments']['data'] as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['ride_id']); ?></td>
                            <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($payment['driver_name']); ?></td>
                            <td>
                                <?php 
                                $amount = !empty($payment['final_fare']) ? $payment['final_fare'] : $payment['fare'];
                                echo 'G$' . number_format($amount, 2);
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($payment['payment_status']); ?></td>
                            <td><?php echo htmlspecialchars($payment['completed_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending payments found. Use the button below to create a test payment confirmation.</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <a href="?run_test=yes" class="button">Create Test Payment Confirmation</a>
    </div>
    
    <div class="card">
        <h2>Test Run Result</h2>
        <?php if ($results['test_run']['success']): ?>
            <p class="success"><?php echo htmlspecialchars($results['test_run']['message']); ?></p>
        <?php else: ?>
            <p class="<?php echo $results['test_run']['message'] === 'No test run' ? 'warning' : 'error'; ?>">
                <?php echo htmlspecialchars($results['test_run']['message']); ?>
            </p>
        <?php endif; ?>
        
        <?php if ($results['test_run']['success']): ?>
            <p>
                <strong>Ride ID:</strong> <?php echo htmlspecialchars($results['test_run']['ride_id']); ?><br>
                <strong>Driver ID:</strong> <?php echo htmlspecialchars($results['test_run']['driver_id']); ?><br>
                <strong>User ID:</strong> <?php echo htmlspecialchars($results['test_run']['user_id']); ?>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>