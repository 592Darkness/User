<?php
session_start();
require_once '../includes/db.php'; // Adjust path if needed
require_once '../includes/functions.php'; // Adjust path if needed

header('Content-Type: application/json');

// Check if user is logged in (adjust session variable if needed)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}
$userId = $_SESSION['user_id'];

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Get token from request body
$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? null;

if (empty($token) || !is_string($token) || strlen($token) > 255) { // Basic validation
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing token.']);
    exit;
}

// --- Store Token in Database ---
// Use the single column approach (adjust if using the separate table)
$sql = "UPDATE users SET fcm_token = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
     error_log("Store FCM Token Error: Prepare failed: " . $conn->error);
     echo json_encode(['status' => 'error', 'message' => 'Database error preparing statement.']);
     exit;
}

$stmt->bind_param("si", $token, $userId);

if ($stmt->execute()) {
    // Check if any row was actually updated (or if token was the same)
    if ($stmt->affected_rows > 0) {
         error_log("FCM Token Stored: Updated token for user ID: $userId");
         echo json_encode(['status' => 'success', 'message' => 'Token stored successfully.']);
    } else {
         // This might happen if the token submitted is the same as the one already stored
         error_log("FCM Token Stored: Token for user ID $userId is unchanged or user not found.");
         echo json_encode(['status' => 'success', 'message' => 'Token is current or user not found.']);
    }
} else {
    error_log("Store FCM Token Error: Execute failed for user ID $userId: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to store token.']);
}

$stmt->close();
// $conn->close(); // Close connection if not handled globally
?>