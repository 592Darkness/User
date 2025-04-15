<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Check if admin is logged in
requireAdminLogin();

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check for required action parameter
    if (!isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing action parameter']);
        exit;
    }
    
    // Validate CSRF token
    if (!isset($data['csrf_token']) || !verifyCSRFToken($data['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    // Process based on action
    switch ($data['action']) {
        case 'get_ride':
            if (!isset($data['ride_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing ride ID']);
                exit;
            }
            
            $rideId = (int)$data['ride_id'];
            
            try {
                $query = "
                    SELECT 
                        r.*, 
                        u.name as user_name, 
                        u.email as user_email,
                        u.phone as user_phone,
                        d.name as driver_name,
                        d.phone as driver_phone
                    FROM rides r
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN drivers d ON r.driver_id = d.id
                    WHERE r.id = ?
                ";
                
                $ride = dbFetchOne($query, [$rideId]);
                
                if ($ride) {
                    echo json_encode([
                        'success' => true, 
                        'ride' => $ride
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ride not found']);
                }
            } catch (Exception $e) {
                error_log("Error getting ride details: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error fetching ride details']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
    exit;
}

// If not an AJAX request, redirect to dashboard
header('Location: admin-dashboard.php');
exit;