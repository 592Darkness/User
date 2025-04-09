<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || empty($_SESSION['driver_id'])) {
    setFlashMessage('error', 'Please log in to access your dashboard.');
    redirect('driver-login.php');
    exit;
}

$driverId = $_SESSION['driver_id'];
$currentDriver = $_SESSION['driver'];

// Get active tab from URL parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Fetch real-time driver data from database to ensure latest information
try {
    $conn = dbConnect();
    $stmt = $conn->prepare("SELECT name, email, phone, status, rating, vehicle, plate, vehicle_type FROM drivers WHERE id = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update the driver data in session with latest from DB
        $driverData = $result->fetch_assoc();
        $_SESSION['driver'] = array_merge($_SESSION['driver'], $driverData);
        $currentDriver = $_SESSION['driver'];
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching updated driver data: " . $e->getMessage());
    // Continue with session data if DB fetch fails
}

// Include driver-specific header file
include_once 'includes/driver-header.php';
?>

<main class="flex-grow container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-white">Driver Dashboard</h1>
            <div class="flex items-center space-x-2">
                <span class="relative inline-flex h-3 w-3">
                    <span class="<?php echo $currentDriver['status'] === 'available' ? 'bg-green-400' : 'bg-red-400'; ?> absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping"></span>
                    <span class="<?php echo $currentDriver['status'] === 'available' ? 'bg-green-500' : 'bg-red-500'; ?> relative inline-flex rounded-full h-3 w-3"></span>
                </span>
                <p class="text-gray-300">Status: <span class="<?php echo $currentDriver['status'] === 'available' ? 'text-green-400' : 'text-red-400'; ?> font-medium"><?php echo ucfirst($currentDriver['status']); ?></span></p>
            </div>
        </div>
        
        <!-- Status Toggle Button -->
        <div class="mb-6 bg-gray-800 border border-gray-700 rounded-lg p-4 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-400">You are currently</p>
                <p class="text-lg font-medium <?php echo $currentDriver['status'] === 'available' ? 'text-green-400' : 'text-red-400'; ?>">
                    <?php echo $currentDriver['status'] === 'available' ? 'Available for rides' : 'Not accepting rides'; ?>
                </p>
            </div>
            <form action="api/driver-update-status.php" method="post" class="inline-block">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="status" value="<?php echo $currentDriver['status'] === 'available' ? 'busy' : 'available'; ?>">
                <button type="submit" class="<?php echo $currentDriver['status'] === 'available' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white py-2 px-6 rounded-lg font-medium transition duration-300">
                    <?php echo $currentDriver['status'] === 'available' ? 'Go Offline' : 'Go Online'; ?>
                </button>
            </form>
        </div>
        
        <!-- Dashboard Tabs -->
        <div class="mb-8 border-b border-gray-700">
            <div class="flex overflow-x-auto hide-scrollbar">
                <a href="?tab=overview" class="dashboard-tab <?php echo ($activeTab == 'overview') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'overview') ? 'true' : 'false'; ?>" role="tab">Overview</a>
                <a href="?tab=rides" class="dashboard-tab <?php echo ($activeTab == 'rides') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'rides') ? 'true' : 'false'; ?>" role="tab">Available Rides</a>
                <a href="?tab=history" class="dashboard-tab <?php echo ($activeTab == 'history') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'history') ? 'true' : 'false'; ?>" role="tab">Ride History</a>
                <a href="?tab=earnings" class="dashboard-tab <?php echo ($activeTab == 'earnings') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'earnings') ? 'true' : 'false'; ?>" role="tab">Earnings</a>
                <a href="?tab=profile" class="dashboard-tab <?php echo ($activeTab == 'profile') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'profile') ? 'true' : 'false'; ?>" role="tab">Profile</a>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl p-6 md:p-8">
            <!-- Overview Tab -->
            <?php if ($activeTab == 'overview'): ?>
                <div id="overview-tab-content" role="tabpanel">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Today's Stats -->
                        <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                            <h3 class="text-lg font-medium text-white mb-4">Today's Stats</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Completed Rides</span>
                                    <span class="text-white font-medium" id="today-rides">-</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Earnings</span>
                                    <span class="text-white font-medium" id="today-earnings">-</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Hours Online</span>
                                    <span class="text-white font-medium" id="today-hours">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Ratings -->
                        <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                            <h3 class="text-lg font-medium text-white mb-4">Your Rating</h3>
                            <div class="flex flex-col items-center">
                                <div class="flex items-center mb-4">
                                    <span class="text-3xl font-bold text-white"><?php echo number_format($currentDriver['rating'], 1); ?></span>
                                    <div class="ml-2 flex">
                                        <?php
                                        $fullStars = floor($currentDriver['rating']);
                                        $halfStar = ($currentDriver['rating'] - $fullStars) >= 0.5;
                                        
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $fullStars) {
                                                echo '<span class="lucide text-yellow-400" aria-hidden="true">&#xeae5;</span>'; // Full star
                                            } elseif ($halfStar && $i == $fullStars + 1) {
                                                echo '<span class="lucide text-yellow-400" aria-hidden="true">&#xeae4;</span>'; // Half star
                                                $halfStar = false;
                                            } else {
                                                echo '<span class="lucide text-gray-500" aria-hidden="true">&#xeae3;</span>'; // Empty star
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <p class="text-gray-400 text-sm">Based on your last 100 rides</p>
                            </div>
                        </div>

                        <!-- Vehicle Info -->
                        <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                            <h3 class="text-lg font-medium text-white mb-4">Vehicle Info</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Vehicle</span>
                                    <span class="text-white font-medium"><?php echo htmlspecialchars($currentDriver['vehicle']); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">License Plate</span>
                                    <span class="text-white font-medium"><?php echo htmlspecialchars($currentDriver['plate']); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Vehicle Type</span>
                                    <span class="text-white font-medium"><?php echo ucfirst(htmlspecialchars($currentDriver['vehicle_type'] ?? 'standard')); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-400">Service Status</span>
                                    <span class="bg-green-500/20 text-green-400 text-xs px-2.5 py-0.5 rounded-full">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current or Next Ride -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-white mb-4">Current/Next Ride</h3>
                        <div id="current-ride-container">
                            <div class="bg-gray-700/50 rounded-lg p-6 border border-gray-600">
                                <div id="ride-loading" class="py-10 flex flex-col items-center justify-center">
                                    <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
                                    <p class="text-gray-400">Checking for assigned rides...</p>
                                </div>
                                
                                <div id="current-ride-info" class="hidden">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                                
                                <div id="no-ride-info" class="hidden py-10 text-center">
                                    <p class="text-gray-400 mb-4">You don't have any current or upcoming rides.</p>
                                    <a href="?tab=rides" class="bg-primary-600 hover:bg-primary-700 text-white py-2 px-6 rounded-lg font-medium transition duration-300">
                                        Find Available Rides
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly Summary -->
                    <div>
                        <h3 class="text-lg font-medium text-white mb-4">Weekly Summary</h3>
                        <div class="bg-gray-700/50 rounded-lg p-6 border border-gray-600">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="text-center">
                                    <p class="text-gray-400 text-sm mb-1">Total Rides</p>
                                    <p class="text-xl font-semibold text-white" id="weekly-rides">-</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-sm mb-1">Total Earnings</p>
                                    <p class="text-xl font-semibold text-white" id="weekly-earnings">-</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-sm mb-1">Hours Online</p>
                                    <p class="text-xl font-semibold text-white" id="weekly-hours">-</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-sm mb-1">Avg. Rating</p>
                                    <p class="text-xl font-semibold text-white" id="weekly-rating">-</p>
                                </div>
                            </div>
                            
                            <div id="earnings-chart" class="h-64">
                                <!-- Chart will be populated by JavaScript -->
                                <div class="h-full flex items-center justify-center">
                                    <p class="text-gray-400">Loading earnings data...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Available Rides Tab -->
            <?php if ($activeTab == 'rides'): ?>
                <div id="rides-tab-content" role="tabpanel">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-white">Available Rides</h2>
                        <button id="refresh-rides-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-1.5 px-4 rounded-lg font-medium text-sm transition duration-300 flex items-center">
                            <span class="lucide mr-1" aria-hidden="true">&#xe9d7;</span> Refresh
                        </button>
                    </div>
                    
                    <div id="available-rides-container" class="space-y-4">
                        <div class="py-10 flex flex-col items-center justify-center">
                            <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
                            <p class="text-gray-400">Loading available rides...</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Ride History Tab -->
            <?php if ($activeTab == 'history'): ?>
                <div id="history-tab-content" role="tabpanel">
                    <div class="flex flex-wrap justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-white">Ride History</h2>
                        
                        <div class="flex space-x-2 mt-2 sm:mt-0">
                            <select id="history-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="all">All Rides</option>
                                <option value="week">Last 7 Days</option>
                                <option value="month">Last 30 Days</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            
                            <button id="refresh-history-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-1.5 px-4 rounded-lg font-medium text-sm transition duration-300 flex items-center">
                                <span class="lucide mr-1" aria-hidden="true">&#xe9d7;</span> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div id="ride-history-container" class="space-y-4">
                        <div class="py-10 flex flex-col items-center justify-center">
                            <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
                            <p class="text-gray-400">Loading ride history...</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Earnings Tab -->
            <?php if ($activeTab == 'earnings'): ?>
                <div id="earnings-tab-content" role="tabpanel">
                    <div class="flex flex-wrap justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-white">Earnings</h2>
                        
                        <div class="flex space-x-2 mt-2 sm:mt-0">
                            <select id="earnings-period" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                                <option value="all">All Time</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                            <h3 class="text-sm text-gray-400 mb-1">Total Earnings</h3>
                            <p class="text-2xl font-bold text-white" id="total-earnings">-</p>
                            <p class="text-xs text-gray-400 mt-1" id="earnings-period-text">This week</p>
                        </div>
                        
                        <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                            <h3 class="text-sm text-gray-400 mb-1">Rides Completed</h3>
                            <p class="text-2xl font-bold text-white" id="total-rides">-</p>
                            <p class="text-xs text-gray-400 mt-1">Average fare: <span id="avg-fare">-</span></p>
                        </div>
                        
                        <div class="bg-gray-700/50 rounded-lg p-5 border border-gray-600">
                            <h3 class="text-sm text-gray-400 mb-1">Hours Online</h3>
                            <p class="text-2xl font-bold text-white" id="total-hours">-</p>
                            <p class="text-xs text-gray-400 mt-1">Average hourly: <span id="avg-hourly">-</span></p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-700/50 rounded-lg p-6 border border-gray-600 mb-8">
                        <h3 class="text-lg font-medium text-white mb-4">Earnings Breakdown</h3>
                        <div id="earnings-breakdown-chart" class="h-80">
                            <!-- Chart will be populated by JavaScript -->
                            <div class="h-full flex items-center justify-center">
                                <p class="text-gray-400">Loading earnings data...</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-700/50 rounded-lg p-6 border border-gray-600">
                        <h3 class="text-lg font-medium text-white mb-4">Recent Payments</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-600">
                                        <th class="text-left text-gray-400 font-medium py-2 px-4">Date</th>
                                        <th class="text-left text-gray-400 font-medium py-2 px-4">Description</th>
                                        <th class="text-left text-gray-400 font-medium py-2 px-4">Status</th>
                                        <th class="text-right text-gray-400 font-medium py-2 px-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="payments-table-body">
                                    <tr class="animate-pulse">
                                        <td colspan="4" class="text-center py-10 text-gray-400">Loading payment data...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Profile Tab -->
            <?php if ($activeTab == 'profile'): ?>
                <div id="profile-tab-content" role="tabpanel">
                    <h2 class="text-xl font-semibold text-white mb-6">Account Information</h2>
                    
                    <form id="driver-profile-form" action="api/driver-update-profile.php" method="post" class="space-y-6 max-w-2xl">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="driver-name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                                <input type="text" id="driver-name" name="name" value="<?php echo htmlspecialchars($currentDriver['name']); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="driver-email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                                <input type="email" id="driver-email" name="email" value="<?php echo htmlspecialchars($currentDriver['email']); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="driver-phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number</label>
                                <input type="tel" id="driver-phone" name="phone" value="<?php echo htmlspecialchars($currentDriver['phone']); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="driver-vehicle" class="block text-sm font-medium text-gray-300 mb-1">Vehicle</label>
                                <input type="text" id="driver-vehicle" name="vehicle" value="<?php echo htmlspecialchars($currentDriver['vehicle']); ?>" readonly class="w-full px-3 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-gray-400 focus:outline-none cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-1">Contact administration to update vehicle information</p>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-700 pt-6">
                            <h3 class="text-lg font-medium text-white mb-4">Change Password</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="current-password" class="block text-sm font-medium text-gray-300 mb-1">Current Password</label>
                                    <input type="password" id="current-password" name="current_password" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="new-password" class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                                    <input type="password" id="new-password" name="new_password" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-700 pt-6">
                            <h3 class="text-lg font-medium text-white mb-4">Notification Preferences</h3>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="checkbox" id="notify-email" name="notify_email" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded" 
                                        <?php echo isset($currentDriver['preferences']['notify_email']) && $currentDriver['preferences']['notify_email'] ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-gray-300">Email notifications</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="notify-sms" name="notify_sms" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded"
                                        <?php echo isset($currentDriver['preferences']['notify_sms']) && $currentDriver['preferences']['notify_sms'] ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-gray-300">SMS notifications</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="notify-app" name="notify_app" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded"
                                        <?php echo isset($currentDriver['preferences']['notify_app']) && $currentDriver['preferences']['notify_app'] ? 'checked' : ''; ?>>
                                    <span class="ml-2 text-gray-300">In-app notifications</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="w-full md:w-auto bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-8 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">Save Changes</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('ServiceWorker registered:', registration.scope);
      })
      .catch(error => {
        console.log('ServiceWorker registration failed:', error);
      });
  });
}
</script>

<?php
include_once 'includes/driver-footer.php';
?>