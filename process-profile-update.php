<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    setFlashMessage('error', 'Please log in to update your profile.');
    redirect('index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Security validation failed. Please try again.');
        redirect('account-dashboard.php');
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $currentUser = getCurrentUser();
    
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : $currentUser['name'];
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : $currentUser['email'];
    $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : $currentUser['phone'];
    $language = isset($_POST['language']) ? sanitize($_POST['language']) : 'en';
    
    $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
    $notifySms = isset($_POST['notify_sms']) ? 1 : 0;
    $notifyPromotions = isset($_POST['notify_promotions']) ? 1 : 0;
    
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
    
    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        redirect('account-dashboard.php');
        exit;
    }
    
    $conn = dbConnect();
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, language = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $language, $userId);
        $stmt->execute();
        $stmt->close();
        
        $prefStmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $prefStmt->bind_param("i", $userId);
        $prefStmt->execute();
        $result = $prefStmt->get_result();
        $prefStmt->close();
        
        if ($result->num_rows > 0) {
            $prefUpdateStmt = $conn->prepare("UPDATE user_preferences SET notify_email = ?, notify_sms = ?, notify_promotions = ?, updated_at = NOW() WHERE user_id = ?");
            $prefUpdateStmt->bind_param("iiii", $notifyEmail, $notifySms, $notifyPromotions, $userId);
            $prefUpdateStmt->execute();
            $prefUpdateStmt->close();
        } else {
            $prefInsertStmt = $conn->prepare("INSERT INTO user_preferences (user_id, notify_email, notify_sms, notify_promotions, created_at) VALUES (?, ?, ?, ?, NOW())");
            $prefInsertStmt->bind_param("iiii", $userId, $notifyEmail, $notifySms, $notifyPromotions);
            $prefInsertStmt->execute();
            $prefInsertStmt->close();
        }
        
        $conn->commit();
        
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
        
        setFlashMessage('success', 'Profile updated successfully!');
        
    } catch (Exception $e) {
        $conn->rollback();
        setFlashMessage('error', 'Error updating profile: ' . $e->getMessage());
    }
    
    $conn->close();
    redirect('account-dashboard.php');
    
} else {
    setFlashMessage('error', 'Invalid request method.');
    redirect('account-dashboard.php');
}