<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Check if admin is logged in
requireAdminLogin();

// Set response type to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Get request method and data
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // If the data is null, check if form data was submitted
    if ($data === null) {
        $data = $_POST;
    }
    
    // Check CSRF token
    if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
        $response['message'] = 'Security validation failed. Please refresh the page and try again.';
        echo json_encode($response);
        exit;
    }
    
    // Get action from request
    $action = isset($data['action']) ? $data['action'] : '';
    
    switch ($action) {
        case 'get_user':
            // Validate user ID
            if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
                $response['message'] = 'Invalid user ID.';
                break;
            }
            
            $userId = (int)$data['user_id'];
            
            // Get user details
            $user = getUserDetails($userId);
            
            if ($user) {
                $response['success'] = true;
                $response['user'] = $user;
            } else {
                $response['message'] = 'User not found.';
            }
            break;
            
        case 'reset_password':
            // Validate user ID
            if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
                $response['message'] = 'Invalid user ID.';
                break;
            }
            
            $userId = (int)$data['user_id'];
            
            // Validate new password
            if (!isset($data['new_password']) || strlen($data['new_password']) < 8) {
                $response['message'] = 'Password must be at least 8 characters.';
                break;
            }
            
            // Validate password confirmation
            if (!isset($data['confirm_password']) || $data['new_password'] !== $data['confirm_password']) {
                $response['message'] = 'Passwords do not match.';
                break;
            }
            
            // Check if user exists
            $user = getUserDetails($userId);
            if (!$user) {
                $response['message'] = 'User not found.';
                break;
            }
            
            // Reset password
            $newPassword = $data['new_password'];
            $notifyUser = isset($data['notify_user']) && $data['notify_user'] ? true : false;
            
            $result = resetUserPassword($userId, $newPassword, $notifyUser);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Password reset successfully.';
                
                if ($notifyUser) {
                    $response['message'] .= ' User has been notified by email.';
                }
            } else {
                $response['message'] = 'Failed to reset password. Please try again.';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action.';
            break;
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

// Send response
echo json_encode($response);