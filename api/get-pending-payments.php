<?php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'payments' => [], 'message' => ''];

// Check login
if (!isLoggedIn()) {
    http_response_code(401);
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $conn = dbConnect();

    // Query for completed rides where payment is not yet marked as completed
    // Assumes payment_status is NULL or 'pending' initially for completed rides
    // Adjust the WHERE clause based on your exact `payment_status` values for unpaid rides.
    $stmt = $conn->prepare("
        SELECT id, pickup, dropoff, final_fare, completed_at, status, payment_status
        FROM rides
        WHERE user_id = ?
          AND status = 'completed'
          AND (payment_status IS NULL OR payment_status NOT IN ('completed', 'paid')) /* Adjust condition as needed */
        ORDER BY completed_at DESC
    ");

    if (!$stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $pendingPayments = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure final_fare is treated as a numeric value (e.g., in cents or smallest unit)
        $row['final_fare_numeric'] = isset($row['final_fare']) ? floatval($row['final_fare']) : 0;
        // Format fare for display (assuming final_fare is in base currency units like dollars)
        $row['formatted_fare'] = 'G$' . number_format($row['final_fare_numeric'], 2);
         // Format date
         try {
             $completedDate = new DateTime($row['completed_at']);
             $row['formatted_date'] = $completedDate->format('M j, Y');
             $row['formatted_time'] = $completedDate->format('g:i A');
         } catch (Exception $e) {
             $row['formatted_date'] = 'N/A';
             $row['formatted_time'] = 'N/A';
         }

        $pendingPayments[] = $row;
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['payments'] = $pendingPayments;
    if (empty($pendingPayments)) {
        $response['message'] = 'No pending payments found.';
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error fetching pending payments: ' . $e->getMessage();
    error_log("Error fetching pending payments for user $userId: " . $e->getMessage());
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}

echo json_encode($response);
exit;
?>