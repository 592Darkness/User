<?php
session_start();

// --- IMPORTANT: Ensure these files are included ---
require_once '../includes/db.php'; // Database connection ($conn assumed to be mysqli object)
require_once '../includes/functions.php'; // General functions - MUST contain the sendRideAcceptedNotification definition now

require_once __DIR__ . '/../../vendor/autoload.php'; 

// Check if the driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Driver not logged in.']);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get input data (ride_id) from the request body
$input = json_decode(file_get_contents('php://input'), true);
$ride_id = filter_var($input['ride_id'] ?? null, FILTER_VALIDATE_INT); // Validate/Sanitize input
$driver_id = $_SESSION['driver_id']; // Already validated by session check

// Validate ride_id
if ($ride_id === false || $ride_id <= 0) { // Ensure ride_id is a positive integer
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Valid Ride ID is required.']);
    exit;
}

// --- Database Interaction ---
// Define variables for prepared statements
$ride = null;
$driver = null;
$stmtRide = null;
$stmtDriver = null;
$stmtUpdateRide = null;
$stmtUpdateDriver = null;
$resultRide = null;
$resultDriver = null;
$user_id_to_notify = null; // Variable to hold the user ID for notification

try {
    // Begin transaction for atomicity
    $conn->begin_transaction();

    // 1. Fetch Ride Details (including requested vehicle type and user_id) and lock the row
    $sqlRide = "SELECT user_id, requested_vehicle_type, status FROM rides WHERE ride_id = ? FOR UPDATE";
    $stmtRide = $conn->prepare($sqlRide);
    if (!$stmtRide) {
        throw new Exception("Prepare failed (Ride Select): " . $conn->error);
    }
    $stmtRide->bind_param("i", $ride_id);
    $stmtRide->execute();
    $resultRide = $stmtRide->get_result();
    $ride = $resultRide->fetch_assoc();
    $stmtRide->close(); // Close statement promptly

    // Check if ride exists
    if (!$ride) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Ride not found.']);
        exit;
    }

    // Store user_id for potential notification later
    $user_id_to_notify = $ride['user_id'];

    // Check if the ride is still available
    if ($ride['status'] !== 'pending' && $ride['status'] !== 'searching') {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Ride is no longer available.']);
        exit;
    }

    // 2. Fetch Driver Details
    $sqlDriver = "SELECT vehicle_type, status FROM drivers WHERE driver_id = ?";
    $stmtDriver = $conn->prepare($sqlDriver);
    if (!$stmtDriver) {
        throw new Exception("Prepare failed (Driver Select): " . $conn->error);
    }
    $stmtDriver->bind_param("i", $driver_id);
    $stmtDriver->execute();
    $resultDriver = $stmtDriver->get_result();
    $driver = $resultDriver->fetch_assoc();
    $stmtDriver->close(); // Close statement promptly

    // Check if driver exists
    if (!$driver) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Driver not found.']);
        exit;
    }

    // Check if driver is available/active
    if ($driver['status'] !== 'available' && $driver['status'] !== 'active') {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Driver status is not available to accept rides.']);
        exit;
    }

    // 3. Compare Vehicle Types (Case-insensitive and trimmed)
    $requested_vehicle_type = trim(strtolower($ride['requested_vehicle_type'] ?? ''));
    $driver_vehicle_type = trim(strtolower($driver['vehicle_type'] ?? ''));

    if (empty($requested_vehicle_type)) {
         $conn->rollback();
         header('Content-Type: application/json');
         echo json_encode(['status' => 'error', 'message' => 'Ride does not specify a required vehicle type.']);
         exit;
    }

    if ($driver_vehicle_type !== $requested_vehicle_type) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'This ride requires a different vehicle type.']);
        exit;
    }

    // 4. Update Ride Status and Assign Driver
    $new_ride_status = 'accepted';
    $sqlUpdateRide = "UPDATE rides SET driver_id = ?, status = ?, accepted_at = NOW() WHERE ride_id = ? AND (status = 'pending' OR status = 'searching')";
    $stmtUpdateRide = $conn->prepare($sqlUpdateRide);
    if (!$stmtUpdateRide) {
        throw new Exception("Prepare failed (Ride Update): " . $conn->error);
    }
    $stmtUpdateRide->bind_param("isi", $driver_id, $new_ride_status, $ride_id);
    $success = $stmtUpdateRide->execute();

    if (!$success || $conn->affected_rows !== 1) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to accept ride. It might have been accepted by another driver, cancelled, or the status changed unexpectedly.']);
        exit;
    }
    $stmtUpdateRide->close();

    // 5. Update Driver Status
    $new_driver_status = 'on_ride';
    $sqlUpdateDriver = "UPDATE drivers SET status = ? WHERE driver_id = ?";
    $stmtUpdateDriver = $conn->prepare($sqlUpdateDriver);
    if (!$stmtUpdateDriver) {
        throw new Exception("Prepare failed (Driver Update): " . $conn->error);
    }
    $stmtUpdateDriver->bind_param("si", $new_driver_status, $driver_id);
    $stmtUpdateDriver->execute();
    $stmtUpdateDriver->close();

    // --- Transaction Commit ---
    $conn->commit();

    // --- Post-Commit Actions: Send Notification ---
    // Call the function defined in includes/functions.php
    if (function_exists('sendRideAcceptedNotification')) {
        // Pass the database connection ($conn) to the function
        // Ideally, queue this or run asynchronously to avoid delaying the API response.
        $notificationSent = sendRideAcceptedNotification($user_id_to_notify, $ride_id, $driver_id, $conn);
        if (!$notificationSent) {
            // Log that sending failed, but don't necessarily fail the whole request,
            // as the ride acceptance itself was successful.
            error_log("Warning: Ride acceptance succeeded (Ride ID: $ride_id), but notification failed to send for User ID: $user_id_to_notify.");
        }
    } else {
         error_log("FATAL ERROR: Function 'sendRideAcceptedNotification' does not exist (should be in functions.php) - Cannot send notification for Ride ID: $ride_id.");
         // Decide if you want to return an error to the driver app in this case,
         // even though the ride was accepted. For now, we just log it.
    }


    // --- Final Success Response ---
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Ride accepted successfully.']);

} catch (Exception $e) { // Catch mysqli_sql_exception or general Exception
    // Rollback transaction in case of ANY error during the try block
    if ($conn && $conn->thread_id && $conn->ping()) {
        @$conn->rollback();
    }

    // Log the detailed error for debugging
    error_log("Database error or exception in driver-accept-ride.php: " . $e->getMessage());

    // Return generic error response
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred while accepting the ride. Please try again later.']);

} finally {
    // Clean up result sets if they were created
    if (isset($resultRide) && $resultRide instanceof mysqli_result) {
        $resultRide->free();
    }
    if (isset($resultDriver) && $resultDriver instanceof mysqli_result) {
        $resultDriver->free();
    }
    // Statements are closed within the try block immediately after use.
    // Ensure database connection closure is handled appropriately
    // if ($conn) { $conn->close(); }
}
?>