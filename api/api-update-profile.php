<?php
/**
 * API Endpoint for Profile Updates
 */

// Check if request method is POST
if ($method !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Method not allowed. Use POST.';
    echo json_encode($response);
    exit;
}

// Get the current user
$currentUser = getCurrentUser();

// Get request data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// Get form data and sanitize
$name = isset($data['name']) ? sanitize($data['name']) : $currentUser['name'];
$email = isset($data['email']) ? sanitize($data['email']) : $currentUser['email'];
$phone = isset($data['phone']) ? sanitize($data['phone']) : $currentUser['phone'];
$language = isset($data['language']) ? sanitize($data['language']) : 'en';

// Get notification preferences
$notifyEmail = isset($data['notify_email']) ? (bool)$data['notify_email'] : true;
$notifySms = isset($data['notify_sms']) ? (bool)$data['notify_sms'] : true;
$notifyPromotions = isset($data['notify_promotions']) ? (bool)$data['notify_promotions'] : false;

// Basic validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required.';
}

if (empty($email)) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is not valid.';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required.';
}

// If there are errors, return them
if (!empty($errors)) {
    http_response_code(400); // Bad Request
    $response['message'] = implode(' ', $errors);
    echo json_encode($response);
    exit;
}

// In a real application, you'd update the user in a database
// For example:
/*
$result = dbUpdate(
    'users',
    [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'language' => $language
    ],
    'id = ?',
    [$currentUser['id']]
);

if ($result) {
    // Update preferences in a separate table
    dbUpdate(
        'user_preferences',
        [
            'notify_email' => $notifyEmail ? 1 : 0,
            'notify_sms' => $notifySms ? 1 : 0,
            'notify_promotions' => $notifyPromotions ? 1 : 0
        ],
        'user_id = ?',
        [$currentUser['id']]
    );
} else {
    http_response_code(500); // Server Error
    $response['message'] = 'Failed to update profile.';
    echo json_encode($response);
    exit;
}
*/

// For demo, update the user in the session
$_SESSION['user'] = array_merge($currentUser, [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'language' => $language,
    'preferences' => [
        'notify_email' => $notifyEmail,
        'notify_sms' => $notifySms,
        'notify_promotions' => $notifyPromotions
    ]
]);

// Success response
$response['success'] = true;
$response['message'] = 'Profile updated successfully!';
$response['data'] = [
    'user' => $_SESSION['user']
];

echo json_encode($response);
exit;
?>
