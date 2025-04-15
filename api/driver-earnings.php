<?php
/**
 * API Endpoint for Driver Earnings
 * Production-ready implementation that only uses database data
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Set Content-Type header to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    http_response_code(401); // Unauthorized
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

$driverId = $_SESSION['driver_id'];

// Get period parameter
$period = isset($_GET['period']) ? sanitize($_GET['period']) : 'week';

// Validate period
$validPeriods = ['day', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'week';
}

// Fetch earnings data
try {
    $conn = dbConnect();
    
    // Determine the date range based on period
    $dateClause = "";
    switch ($period) {
        case 'day':
            $dateClause = "WHERE r.completed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            $groupBy = "HOUR(r.completed_at)";
            $labelFormat = "%h %p"; // Hour with AM/PM
            break;
        case 'week':
            $dateClause = "WHERE r.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $groupBy = "DATE(r.completed_at)";
            $labelFormat = "%a"; // Day of week (Mon, Tue, etc.)
            break;
        case 'month':
            $dateClause = "WHERE r.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $groupBy = "DATE(r.completed_at)";
            $labelFormat = "%b %d"; // Month day (Jan 01, etc.)
            break;
        case 'year':
            $dateClause = "WHERE r.completed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            $groupBy = "MONTH(r.completed_at)";
            $labelFormat = "%b"; // Month name (Jan, Feb, etc.)
            break;
        case 'all':
        default:
            $dateClause = ""; // No date restriction
            $groupBy = "MONTH(r.completed_at), YEAR(r.completed_at)";
            $labelFormat = "%b %Y"; // Month Year (Jan 2023, etc.)
            break;
    }
    
    // Add driver_id filter to dateClause
    if (empty($dateClause)) {
        $dateClause = "WHERE r.driver_id = ? AND r.status = 'completed'";
    } else {
        $dateClause .= " AND r.driver_id = ? AND r.status = 'completed'";
    }
    
    // Build the summary query
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_rides,
            SUM(r.final_fare) as total_earnings,
            AVG(r.final_fare) as avg_fare,
            SUM(TIMESTAMPDIFF(MINUTE, r.created_at, r.completed_at)) as total_minutes,
            AVG(TIMESTAMPDIFF(MINUTE, r.created_at, r.completed_at)) as avg_ride_minutes
        FROM rides r
        $dateClause
    ";
    
    $stmt = $conn->prepare($summaryQuery);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $summaryResult = $stmt->get_result();
    $summary = $summaryResult->fetch_assoc();
    $stmt->close();
    
    // Default values in case there are no completed rides
    $totalRides = $summary['total_rides'] ?: 0;
    $totalEarnings = $summary['total_earnings'] ?: 0;
    $avgFare = $summary['avg_fare'] ?: 0;
    $totalMinutes = $summary['total_minutes'] ?: 0;
    
    // Convert minutes to hours for display
    $totalHours = $totalMinutes / 60;
    $hourlyRate = $totalHours > 0 ? $totalEarnings / $totalHours : 0;
    
    // Get earnings breakdown by period
    $breakdownQuery = "
        SELECT 
            DATE_FORMAT(r.completed_at, '$labelFormat') as period_label,
            $groupBy as period_group,
            COUNT(*) as rides,
            SUM(r.final_fare) as earnings
        FROM rides r
        $dateClause
        GROUP BY period_group, period_label
        ORDER BY period_group ASC
    ";
    
    $breakdownStmt = $conn->prepare($breakdownQuery);
    $breakdownStmt->bind_param("i", $driverId);
    $breakdownStmt->execute();
    $breakdownResult = $breakdownStmt->get_result();
    
    $earnings_breakdown = [];
    while ($row = $breakdownResult->fetch_assoc()) {
        $earnings_breakdown[] = [
            'label' => $row['period_label'],
            'rides' => intval($row['rides']),
            'earnings' => floatval($row['earnings'])
        ];
    }
    $breakdownStmt->close();
    
    // Get recent payments from driver_payments table
    $paymentsQuery = "
        SELECT 
            p.id,
            p.amount,
            p.status,
            p.payment_method,
            p.created_at,
            p.description
        FROM driver_payments p
        WHERE p.driver_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ";
    
    $paymentsStmt = $conn->prepare($paymentsQuery);
    $paymentsStmt->bind_param("i", $driverId);
    $paymentsStmt->execute();
    $paymentsResult = $paymentsStmt->get_result();
    
    $payments = [];
    while ($row = $paymentsResult->fetch_assoc()) {
        $paymentDate = new DateTime($row['created_at']);
        
        $payments[] = [
            'id' => $row['id'],
            'amount' => floatval($row['amount']),
            'formatted_amount' => 'G$' . number_format($row['amount']),
            'status' => $row['status'],
            'payment_method' => $row['payment_method'],
            'created_at' => $row['created_at'],
            'date' => $paymentDate->format('M j, Y'),
            'description' => $row['description'] ?: 'Ride earnings payout'
        ];
    }
    $paymentsStmt->close();
    
    $conn->close();
    
    $response['success'] = true;
    $response['message'] = 'Earnings data retrieved successfully';
    $response['data'] = [
        'summary' => [
            'total_rides' => $totalRides,
            'total_earnings' => $totalEarnings,
            'formatted_earnings' => 'G$' . number_format($totalEarnings),
            'avg_fare' => $avgFare,
            'formatted_avg_fare' => 'G$' . number_format($avgFare, 2),
            'total_hours' => round($totalHours, 1),
            'hourly_rate' => $hourlyRate,
            'formatted_hourly_rate' => 'G$' . number_format($hourlyRate, 2),
            'period' => $period
        ],
        'breakdown' => $earnings_breakdown,
        'payments' => $payments
    ];
    
} catch (Exception $e) {
    error_log("Error fetching earnings data: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['message'] = 'An error occurred while fetching earnings data';
}

echo json_encode($response);
exit;
?>