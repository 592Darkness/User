<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    setFlashMessage('error', 'Please log in to access the dashboard.');
    redirect('driver-login.php');
    exit;
}

$driverId = $_SESSION['driver_id'];
$driver = $_SESSION['driver'] ?? null;

if (!$driver) {
    try {
        $conn = dbConnect();
        $stmt = $conn->prepare("SELECT id, name, email, phone, rating, status FROM drivers WHERE id = ?");
        $stmt->bind_param("i", $driverId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Session has an ID but no matching driver in database - critical error
            error_log("CRITICAL: Driver Dashboard - Driver ID {$driverId} in session not found in database.");
            session_unset();
            session_destroy();
            redirect('driver-login.php?error=invalid_session');
            exit;
        }
        
        $driver = $result->fetch_assoc();
        $_SESSION['driver'] = $driver; // Update the session with fetched data
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Database error fetching driver details: " . $e->getMessage());
        setFlashMessage('error', 'Error loading your profile. Please try logging in again.');
        redirect('driver-login.php');
        exit;
    }
}

// Generate CSRF token if needed
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCSRFToken();
}
$csrfToken = $_SESSION['csrf_token'];

// Get the active tab
$activeTab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'overview';
$allowedTabs = ['overview', 'rides', 'history', 'earnings', 'profile'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'overview';
}

// Set page title based on active tab
$tabTitles = [
    'overview' => 'Dashboard',
    'rides' => 'Available Rides',
    'history' => 'Ride History',
    'earnings' => 'Earnings',
    'profile' => 'Profile'
];
$pageTitle = $tabTitles[$activeTab] . ' - Driver Portal - Salaam Rides';

// Fetch driver performance metrics from the database
$performanceMetrics = [
    'acceptance_rate' => 0,
    'completion_rate' => 0,
    'on_time_rate' => 0
];

$memberSince = '';
$vehicleType = '';
$vehicleModel = '';
$vehiclePlate = '';
$supportPhone = '';

try {
    $conn = dbConnect();
    
    // Get real performance metrics from database
    $metricsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM ride_requests WHERE driver_id = ? AND status = 'accepted') / 
            (SELECT GREATEST(COUNT(*), 1) FROM ride_requests WHERE driver_id = ?) * 100 as acceptance_rate,
            
            (SELECT COUNT(*) FROM rides WHERE driver_id = ? AND status = 'completed') / 
            (SELECT GREATEST(COUNT(*), 1) FROM rides WHERE driver_id = ? AND status IN ('completed', 'cancelled')) * 100 as completion_rate,
            
            (SELECT COUNT(*) FROM rides WHERE driver_id = ? AND status = 'completed' AND arrived_on_time = 1) / 
            (SELECT GREATEST(COUNT(*), 1) FROM rides WHERE driver_id = ? AND status = 'completed') * 100 as on_time_rate
    ";
    
    $stmt = $conn->prepare($metricsQuery);
    if ($stmt) {
        $stmt->bind_param("iiiiii", $driverId, $driverId, $driverId, $driverId, $driverId, $driverId);
        $stmt->execute();
        $metricsResult = $stmt->get_result();
        
        if ($metricsResult->num_rows > 0) {
            $metrics = $metricsResult->fetch_assoc();
            
            // Only update metrics if we have actual data (not NULL)
            if (!is_null($metrics['acceptance_rate'])) {
                $performanceMetrics['acceptance_rate'] = round($metrics['acceptance_rate']);
            }
            
            if (!is_null($metrics['completion_rate'])) {
                $performanceMetrics['completion_rate'] = round($metrics['completion_rate']);
            }
            
            if (!is_null($metrics['on_time_rate'])) {
                $performanceMetrics['on_time_rate'] = round($metrics['on_time_rate']);
            }
        }
        
        $stmt->close();
    }
    
    // Get driver's member since date
    $memberSinceQuery = "SELECT DATE_FORMAT(created_at, '%b %Y') as join_date FROM drivers WHERE id = ?";
    $memberStmt = $conn->prepare($memberSinceQuery);
    if ($memberStmt) {
        $memberStmt->bind_param("i", $driverId);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        
        if ($memberResult->num_rows > 0) {
            $memberData = $memberResult->fetch_assoc();
            $memberSince = $memberData['join_date'];
        }
        
        $memberStmt->close();
    }
    
    // Get driver's vehicle details
    $vehicleQuery = "
        SELECT v.type, v.model, v.plate 
        FROM vehicles v 
        JOIN drivers d ON d.vehicle_id = v.id
        WHERE d.id = ?
    ";
    
    $vehicleStmt = $conn->prepare($vehicleQuery);
    if ($vehicleStmt) {
        $vehicleStmt->bind_param("i", $driverId);
        $vehicleStmt->execute();
        $vehicleResult = $vehicleStmt->get_result();
        
        if ($vehicleResult->num_rows > 0) {
            $vehicleData = $vehicleResult->fetch_assoc();
            $vehicleType = $vehicleData['type'];
            $vehicleModel = $vehicleData['model'];
            $vehiclePlate = $vehicleData['plate'];
        }
        
        $vehicleStmt->close();
    }
    
    // Get company support phone number from settings
    $settingsQuery = "SELECT value FROM site_settings WHERE setting_key = 'support_phone'";
    $settingsStmt = $conn->prepare($settingsQuery);
    
    if ($settingsStmt) {
        $settingsStmt->execute();
        $settingsResult = $settingsStmt->get_result();
        
        if ($settingsResult->num_rows > 0) {
            $setting = $settingsResult->fetch_assoc();
            $supportPhone = $setting['value'];
        }
        
        $settingsStmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching driver metrics: " . $e->getMessage());
    // We'll continue with empty metrics rather than hardcoded fallbacks
}

// Include header
include __DIR__ . '/includes/driver-header.php';
?>

<main class="flex-grow container mx-auto px-4 py-8">
    <!-- Dashboard Header Section -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">Driver Dashboard</h1>
            <p class="text-gray-400">Welcome back, <?php echo htmlspecialchars($driver['name']); ?>!</p>
        </div>
        
        <!-- Driver Status Toggle -->
        <div class="mt-4 md:mt-0">
            <form action="api/driver-update-status.php" method="post" class="flex items-center">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="flex items-center bg-gray-700 p-1 rounded-lg">
                    <?php if ($driver['status'] === 'available'): ?>
                        <!-- If currently online, show the offline button as active -->
                        <input type="hidden" name="status" value="busy">
                        <button type="button" disabled class="relative flex items-center px-4 py-2 rounded-md bg-green-600 text-white">
                            <span class="mr-2">●</span>
                            <span>Online</span>
                        </button>
                        <button type="submit" class="relative flex items-center px-4 py-2 rounded-md bg-transparent text-gray-300 hover:text-white hover:bg-gray-600">
                            <span class="mr-2">●</span>
                            <span>Go Offline</span>
                        </button>
                    <?php else: ?>
                        <!-- If currently offline, show the online button as active -->
                        <input type="hidden" name="status" value="available">
                        <button type="submit" class="relative flex items-center px-4 py-2 rounded-md bg-transparent text-gray-300 hover:text-white hover:bg-gray-600">
                            <span class="mr-2">●</span>
                            <span>Go Online</span>
                        </button>
                        <button type="button" disabled class="relative flex items-center px-4 py-2 rounded-md bg-red-600 text-white">
                            <span class="mr-2">●</span>
                            <span>Offline</span>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="mb-8 border-b border-gray-700">
        <nav class="flex space-x-1">
            <a href="?tab=overview" class="dashboard-tab px-4 py-3 text-sm font-medium <?php echo $activeTab === 'overview' ? 'text-primary-400 border-b-2 border-primary-400' : 'text-gray-400 hover:text-gray-200'; ?>">
                <span class="lucide mr-2" aria-hidden="true">&#xe987;</span>
                Overview
            </a>
            <a href="?tab=rides" class="dashboard-tab px-4 py-3 text-sm font-medium <?php echo $activeTab === 'rides' ? 'text-primary-400 border-b-2 border-primary-400' : 'text-gray-400 hover:text-gray-200'; ?>">
                <span class="lucide mr-2" aria-hidden="true">&#xe99d;</span>
                Available Rides
            </a>
            <a href="?tab=history" class="dashboard-tab px-4 py-3 text-sm font-medium <?php echo $activeTab === 'history' ? 'text-primary-400 border-b-2 border-primary-400' : 'text-gray-400 hover:text-gray-200'; ?>">
                <span class="lucide mr-2" aria-hidden="true">&#xea64;</span>
                History
            </a>
            <a href="?tab=earnings" class="dashboard-tab px-4 py-3 text-sm font-medium <?php echo $activeTab === 'earnings' ? 'text-primary-400 border-b-2 border-primary-400' : 'text-gray-400 hover:text-gray-200'; ?>">
                <span class="lucide mr-2" aria-hidden="true">&#xea6b;</span>
                Earnings
            </a>
            <a href="?tab=profile" class="dashboard-tab px-4 py-3 text-sm font-medium <?php echo $activeTab === 'profile' ? 'text-primary-400 border-b-2 border-primary-400' : 'text-gray-400 hover:text-gray-200'; ?>">
                <span class="lucide mr-2" aria-hidden="true">&#xea05;</span>
                Profile
            </a>
        </nav>
    </div>
    
    <!-- Tab Content -->
    <div class="tab-content">
        <?php if ($activeTab === 'overview'): ?>
            <!-- Overview Tab -->
            <div id="overview-tab-content">
                <!-- Current Ride Section -->
                <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6 mb-8">
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center">
                        <span class="lucide mr-2" aria-hidden="true">&#xe99d;</span>
                        Current Ride
                    </h2>
                    
                    <!-- Loading State -->
                    <div id="ride-loading" class="py-8 flex flex-col items-center justify-center">
                        <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
                        <p class="text-gray-400">Loading current ride information...</p>
                    </div>
                    
                    <!-- With Active Ride -->
                    <div id="current-ride-info" class="hidden">
                        <!-- Content will be populated by JavaScript -->
                    </div>
                    
                    <!-- No Active Ride -->
                    <div id="no-ride-info" class="hidden py-8 text-center">
                        <p class="text-gray-400 mb-4">You don't have an active ride at the moment.</p>
                        <a href="?tab=rides" class="inline-block bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg font-medium transition duration-300">
                            Find Available Rides
                        </a>
                    </div>
                </div>
                
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                    <!-- Today's Stats -->
                    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Today's Stats</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-gray-400 text-sm mb-1">Rides</p>
                                <p class="text-2xl font-bold text-white" id="today-rides">0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-gray-400 text-sm mb-1">Earnings</p>
                                <p class="text-2xl font-bold text-primary-400" id="today-earnings">G$0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-gray-400 text-sm mb-1">Hours</p>
                                <p class="text-2xl font-bold text-white" id="today-hours">0</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Weekly Stats -->
                    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Weekly Stats</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-gray-400 text-sm mb-1">Rides</p>
                                <p class="text-2xl font-bold text-white" id="weekly-rides">0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-gray-400 text-sm mb-1">Earnings</p>
                                <p class="text-2xl font-bold text-primary-400" id="weekly-earnings">G$0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-gray-400 text-sm mb-1">Rating</p>
                                <p class="text-2xl font-bold text-yellow-400" id="weekly-rating"><?php echo number_format($driver['rating'] ?? 0, 1); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Driver Performance -->
                    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Performance</h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm text-gray-400">Acceptance Rate</span>
                                    <span class="text-sm text-primary-400"><?php echo $performanceMetrics['acceptance_rate']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="bg-primary-500 h-2 rounded-full" style="width: <?php echo $performanceMetrics['acceptance_rate']; ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm text-gray-400">Completion Rate</span>
                                    <span class="text-sm text-primary-400"><?php echo $performanceMetrics['completion_rate']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="bg-primary-500 h-2 rounded-full" style="width: <?php echo $performanceMetrics['completion_rate']; ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm text-gray-400">On-Time Arrival</span>
                                    <span class="text-sm text-primary-400"><?php echo $performanceMetrics['on_time_rate']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="bg-primary-500 h-2 rounded-full" style="width: <?php echo $performanceMetrics['on_time_rate']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($activeTab === 'rides'): ?>
            <!-- Available Rides Tab -->
            <div id="available-rides-container" class="space-y-4">
                <div class="py-10 flex flex-col items-center justify-center">
                    <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
                    <p class="text-gray-400">Loading available rides...</p>
                </div>
            </div>
        <?php elseif ($activeTab === 'history'): ?>
            <!-- Ride History Tab -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Your Ride History</h2>
                    <p class="text-sm text-gray-400 mt-1">View details of your past rides</p>
                </div>
                <div class="flex items-center space-x-3">
                    <select id="history-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="all">All Time</option>
                        <option value="week" selected>This Week</option>
                        <option value="month">This Month</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button id="refresh-history-btn" class="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded-lg">
                        <span class="lucide text-lg" aria-hidden="true">&#xe9d7;</span>
                    </button>
                </div>
            </div>
            
            <div id="ride-history-container" class="space-y-4">
                <div class="py-10 flex flex-col items-center justify-center">
                    <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
                    <p class="text-gray-400">Loading ride history...</p>
                </div>
            </div>
        <?php elseif ($activeTab === 'earnings'): ?>
            <!-- Earnings Tab -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-white">Earnings</h2>
                    <p class="text-sm text-gray-400 mt-1">Your earnings for <span id="earnings-period-text">this week</span></p>
                </div>
                <div class="flex items-center space-x-3">
                    <select id="earnings-period" class="bg-gray-700 border border-gray-600 text-white rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="week" selected>This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6 flex flex-col items-center justify-center">
                    <p class="text-gray-400 text-sm mb-1">Total Earnings</p>
                    <p class="text-3xl font-bold text-white mb-1" id="total-earnings">G$0</p>
                </div>
                <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6 flex flex-col items-center justify-center">
                    <p class="text-gray-400 text-sm mb-1">Total Rides</p>
                    <p class="text-3xl font-bold text-white mb-1" id="total-rides">0</p>
                </div>
                <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6 flex flex-col items-center justify-center">
                    <p class="text-gray-400 text-sm mb-1">Average Fare</p>
                    <p class="text-3xl font-bold text-white mb-1" id="avg-fare">G$0</p>
                </div>
                <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6 flex flex-col items-center justify-center">
                    <p class="text-gray-400 text-sm mb-1">Hourly Average</p>
                    <p class="text-3xl font-bold text-white mb-1" id="avg-hourly">G$0</p>
                </div>
            </div>
            
            <!-- Earnings Chart -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6 mb-8">
                <h3 class="text-lg font-semibold text-white mb-4">Earnings Breakdown</h3>
                <div id="earnings-breakdown-chart" class="w-full h-80 bg-gray-800">
                    <!-- Chart will be rendered here by JS -->
                    <div class="h-full flex items-center justify-center">
                        <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full"></div>
                    </div>
                </div>
            </div>
            
            <!-- Payment History -->
            <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Payment History</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left border-b border-gray-700">
                                <th class="py-3 px-4 text-gray-400 font-medium">Date</th>
                                <th class="py-3 px-4 text-gray-400 font-medium">Description</th>
                                <th class="py-3 px-4 text-gray-400 font-medium">Status</th>
                                <th class="py-3 px-4 text-gray-400 font-medium text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="payments-table-body">
                            <tr>
                                <td colspan="4" class="text-center py-10 text-gray-400">Loading payment history...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($activeTab === 'profile'): ?>
            <!-- Profile Tab -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6">
                        <h2 class="text-xl font-semibold text-white mb-6">Edit Profile</h2>
                        
                        <form id="profile-form" action="api/driver-update-profile.php" method="post" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($driver['name']); ?>" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($driver['email']); ?>" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($driver['phone']); ?>" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            
                            <div class="pt-4 border-t border-gray-700">
                                <h3 class="text-lg font-medium text-white mb-3">Change Password</h3>
                                <p class="text-sm text-gray-400 mb-4">Leave blank if you don't want to change your password</p>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-300 mb-1">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label for="new_password" class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                                        <input type="password" id="new_password" name="new_password" minlength="8" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                        <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-700">
                                <h3 class="text-lg font-medium text-white mb-3">Notification Preferences</h3>
                                
                                <div class="space-y-3">
                                    <?php
                                    // Get notification preferences from database
                                    $notifyEmail = 1;
                                    $notifySms = 1;
                                    $notifyApp = 1;
                                    
                                    try {
                                        $conn = dbConnect();
                                        $prefStmt = $conn->prepare("SELECT * FROM driver_preferences WHERE driver_id = ?");
                                        $prefStmt->bind_param("i", $driverId);
                                        $prefStmt->execute();
                                        $prefResult = $prefStmt->get_result();
                                        
                                        if ($prefResult->num_rows > 0) {
                                            $preferences = $prefResult->fetch_assoc();
                                            $notifyEmail = $preferences['notify_email'] ?? 1;
                                            $notifySms = $preferences['notify_sms'] ?? 1;
                                            $notifyApp = $preferences['notify_app'] ?? 1;
                                        }
                                        
                                        $prefStmt->close();
                                        $conn->close();
                                    } catch (Exception $e) {
                                        error_log("Error fetching notification preferences: " . $e->getMessage());
                                    }
                                    ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="notify_email" value="1" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-500 rounded" <?php echo $notifyEmail ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-gray-300">Email Notifications</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" name="notify_sms" value="1" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-500 rounded" <?php echo $notifySms ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-gray-300">SMS Notifications</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" name="notify_app" value="1" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-500 rounded" <?php echo $notifyApp ? 'checked' : ''; ?>>
                                        <span class="ml-2 text-gray-300">App Notifications</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white py-2 px-6 rounded-lg font-medium transition duration-300">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div>
                    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md p-6">
                        <div class="text-center mb-6">
                            <div class="w-24 h-24 rounded-full bg-primary-500/20 text-primary-400 flex items-center justify-center text-4xl mx-auto mb-4">
                                <span class="lucide" aria-hidden="true">&#xebe4;</span>
                            </div>
                            <h2 class="text-xl font-semibold text-white"><?php echo htmlspecialchars($driver['name']); ?></h2>
                            <p class="text-gray-400 mt-1">Driver Account</p>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-400">Rating</span>
                                <span class="text-white font-medium"><?php echo number_format($driver['rating'] ?? 0, 1); ?> ⭐</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-400">Status</span>
                                <span class="text-white font-medium capitalize"><?php echo htmlspecialchars($driver['status']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-400">Account Type</span>
                                <span class="text-white font-medium">Verified Driver</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-700">
                                <span class="text-gray-400">Vehicle Type</span>
                                <span class="text-white font-medium"><?php echo htmlspecialchars($vehicleType ?: 'Not assigned'); ?></span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-400">Member Since</span>
                                <span class="text-white font-medium"><?php echo htmlspecialchars($memberSince ?: 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Payment Confirmation Modal -->
<div id="paymentConfirmationModal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-md w-full mx-auto relative z-10 rounded-lg p-6">
        <h3 class="text-xl font-semibold text-white mb-4">Confirm Payment Received</h3>
        
        <div class="mb-6">
            <p class="text-gray-300 mb-4">Please confirm if you received the correct payment for the following ride:</p>
            <div class="bg-gray-700/50 rounded-lg p-4 space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-400">Ride ID:</span>
                    <span class="text-white font-medium" id="confirm-ride-id">1234</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Customer:</span>
                    <span class="text-white font-medium" id="confirm-customer-name">John Smith</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Amount Due:</span>
                    <span class="text-green-400 font-medium" id="confirm-ride-amount">G$2,500.00</span>
                </div>
            </div>
        </div>
        
        <input type="hidden" id="confirm-modal-ride-id" value="">
        
        <div class="flex justify-between gap-4">
            <button id="disputePaymentBtn" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg font-medium transition duration-300">
                <span class="lucide mr-1" aria-hidden="true">&#xea0e;</span>
                No, Dispute Payment
            </button>
            <button id="confirmPaymentBtn" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg font-medium transition duration-300">
                <span class="lucide mr-1" aria-hidden="true">&#xe96c;</span>
                Yes, Confirm Received
            </button>
        </div>
    </div>
</div>

<div id="loading-overlay" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="flex flex-col items-center">
        <div class="spinner-border animate-spin inline-block w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
        <p class="text-xl text-white">Loading...</p>
    </div>
</div>

<?php include __DIR__ . '/includes/driver-footer.php'; ?>