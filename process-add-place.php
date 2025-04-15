<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    setFlashMessage('error', 'Please log in to add a saved place.');
    redirect('index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Security validation failed. Please try again.');
        redirect('account-dashboard.php?tab=places');
        exit;
    }
    
    $name = isset($_POST['name']) ? sanitize($_POST['name']) : '';
    $address = isset($_POST['address']) ? sanitize($_POST['address']) : '';
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($address)) {
        $errors[] = 'Address is required.';
    }
    
    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        redirect('account-dashboard.php?tab=places');
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    $conn = dbConnect();
    $stmt = $conn->prepare("INSERT INTO saved_places (user_id, name, address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $userId, $name, $address);
    
    if ($stmt->execute()) {
        setFlashMessage('success', "\"$name\" has been added to your saved places!");
    } else {
        setFlashMessage('error', "Error saving place: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
    redirect('account-dashboard.php?tab=places');
    
} else {
    setFlashMessage('error', 'Invalid request method.');
    redirect('account-dashboard.php?tab=places');
}