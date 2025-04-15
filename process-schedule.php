<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

$response = [
    'success' => false,
    'message' => '',
    'redirect' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = 'index.php';
        setFlashMessage('error', 'Please log in to schedule a ride.');
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $response['message'] = 'Please log in to schedule a ride.';
            $response['redirect'] = 'index.php';
            echo json_encode($response);
            exit;
        }
        
        redirect('index.php');
        exit;
    }
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Security validation failed. Please try again.');
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $response['message'] = 'Security validation failed. Please try again.';
            echo json_encode($response);
            exit;
        }
        
        redirect('index.php');
        exit;
    }
    
    $pickup = isset($_POST['pickup']) ? sanitize($_POST['pickup']) : '';
    $dropoff = isset($_POST['dropoff']) ? sanitize($_POST['dropoff']) : '';
    $vehicleType = isset($_POST['scheduleVehicleType']) ? sanitize($_POST['scheduleVehicleType']) : 'standard';
    $date = isset($_POST['date']) ? sanitize($_POST['date']) : '';
    $time = isset($_POST['time']) ? sanitize($_POST['time']) : '';
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    $errors = [];
    
    if (empty($pickup)) {
        $errors[] = 'Pickup location is required.';
    }
    
    if (empty($dropoff)) {
        $errors[] = 'Dropoff location is required.';
    }
    
    if (!in_array($vehicleType, ['standard', 'suv', 'premium'])) {
        $errors[] = 'Invalid vehicle type.';
    }
    
    if (empty($date)) {
        $errors[] = 'Date is required.';
    }
    
    if (empty($time)) {
        $errors[] = 'Time is required.';
    }
    
    $scheduledDateTime = strtotime($date . ' ' . $time);
    if ($scheduledDateTime === false) {
        $errors[] = 'Invalid date or time.';
    } elseif ($scheduledDateTime < time()) {
        $errors[] = 'Scheduled time cannot be in the past.';
    }
    
    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $response['message'] = implode(' ', $errors);
            echo json_encode($response);
            exit;
        }
        
        redirect('index.php');
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    $scheduledDateTimeFormatted = date('Y-m-d H:i:s', $scheduledDateTime);
    
    $fare = mt_rand(2500, 9000);
    
    $conn = dbConnect();
    $stmt = $conn->prepare("INSERT INTO rides (user_id, pickup, dropoff, vehicle_type, fare, status, notes, scheduled_at, created_at) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?, NOW())");
    $stmt->bind_param("isssdss", $userId, $pickup, $dropoff, $vehicleType, $fare, $notes, $scheduledDateTimeFormatted);
    
    if ($stmt->execute()) {
        $scheduleId = $conn->insert_id;
        
        $_SESSION['scheduled_ride'] = [
            'id' => $scheduleId,
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'vehicle_type' => $vehicleType,
            'fare' => $fare,
            'notes' => $notes,
            'scheduled_at' => $scheduledDateTimeFormatted,
            'formatted_date' => date('F j, Y', $scheduledDateTime),
            'formatted_time' => date('g:i A', $scheduledDateTime),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        $formattedDate = date('F j, Y', $scheduledDateTime);
        $formattedTime = date('g:i A', $scheduledDateTime);
        
        $response['success'] = true;
        $response['message'] = "Ride scheduled for {$formattedDate} at {$formattedTime}!";
        $response['schedule_id'] = $scheduleId;
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        }
        
        setFlashMessage('success', "Ride scheduled for {$formattedDate} at {$formattedTime}!");
        redirect('account-dashboard.php?tab=rides');
    } else {
        $error = "Database error: " . $stmt->error;
        setFlashMessage('error', $error);
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $response['message'] = $error;
            echo json_encode($response);
            exit;
        }
        
        redirect('index.php');
    }
    
    $stmt->close();
    $conn->close();
    
} else {
    $response['message'] = 'Invalid request method.';
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
        exit;
    }
    
    setFlashMessage('error', 'Invalid request method.');
    redirect('index.php');
}