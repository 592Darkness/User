<?php
// Public_html/account-dashboard.php

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Log current session state for debugging
error_log("Dashboard session: " . json_encode($_SESSION));

// --- Authentication Check ---
// Check if user is logged in. If not, redirect to index.php.
if (!isLoggedIn()) {
    error_log("User not logged in, redirecting from account-dashboard.php");
    setFlashMessage('error', 'Please log in to access your dashboard.');
    redirect('index.php'); // Redirect to login/home page
    exit;
}

// --- User Data Retrieval ---
$userId = $_SESSION['user_id'];
$currentUser = getCurrentUser(); // Get user data from session

// If session user data is somehow empty despite being logged in, try fetching from DB
if (!$currentUser || empty($currentUser['id'])) { // Check if ID is missing specifically
    error_log("User ID $userId exists in session but no user data. Fetching from DB...");
    try {
        $conn = dbConnect();
        $stmt = $conn->prepare("SELECT id, name, email, phone, language, created_at, updated_at FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $currentUser = $result->fetch_assoc();
                $_SESSION['user'] = $currentUser; // Refresh session data
                error_log("User data refreshed from database: " . json_encode($currentUser));
            } else {
                // User ID exists in session but not in DB - critical error, likely requires logout
                error_log("CRITICAL: User ID $userId in session but not found in database! Forcing logout.");
                logout(); // Log the user out
                setFlashMessage('error', 'Your account session was invalid. Please log in again.');
                redirect('index.php');
                exit;
            }
            $stmt->close();
        } else {
             error_log("DB prepare statement failed while fetching user data: " . $conn->error);
        }
        $conn->close();
    } catch (Exception $e) {
        error_log("Error fetching user data from DB: " . $e->getMessage());
        // If DB fetch fails, we might have to force logout if currentUser is unusable
        if (!$currentUser || empty($currentUser['id'])) {
             logout();
             setFlashMessage('error', 'Could not verify your account. Please log in again.');
             redirect('index.php');
             exit;
        }
         // Otherwise, continue with potentially stale session data but log the warning
         error_log("Warning: Could not refresh user data from DB. Using potentially stale session data.");
    }
}

// --- Page Setup ---
$pageTitle = "Account Dashboard - Salaam Rides"; // Set the page title for the header
// Determine the active tab based on URL parameter or default to 'profile'
$activeTab = isset($_GET['tab']) ? sanitize($_GET['tab']) : 'profile';
// Validate activeTab to prevent unexpected values
$validTabs = ['profile', 'rides', 'places', 'payment', 'rewards'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'profile'; // Default to profile if invalid tab provided
}

// --- Include Header ---
// The header file handles the basic HTML structure, CSS, and potentially global JS variables
include_once 'includes/header.php';
?>

<script id="user-data" type="application/json">
    <?php echo json_encode($currentUser ?? null); // Output user data or null if something went wrong ?>
</script>

<main class="flex-grow container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <h1 class="text-3xl font-bold text-white">Account Dashboard</h1>
            <p class="text-gray-400 text-sm sm:text-base">
                Welcome back,
                <span class="user-display-name font-medium text-white">
                    <?php echo htmlspecialchars($currentUser['name'] ?? 'User'); // Display name or fallback ?>
                </span>
            </p>
        </div>

        <div class="mb-8 border-b border-gray-700">
            <div class="flex overflow-x-auto hide-scrollbar" role="tablist" aria-label="Account Dashboard Tabs">
                <?php foreach ($validTabs as $tabKey): ?>
                    <?php
                        // Map tab keys to display names and icons
                        $tabInfo = [
                            'profile' => ['name' => 'Profile', 'icon' => '&#xea05;'], // User icon
                            'rides' => ['name' => 'Ride History', 'icon' => '&#xeb15;'], // Car icon
                            'places' => ['name' => 'Saved Places', 'icon' => '&#xea48;'], // MapPin icon
                            'payment' => ['name' => 'Payment', 'icon' => '&#xeaa4;'], // CreditCard icon
                            'rewards' => ['name' => 'Rewards', 'icon' => '&#xeae5;'] // Star icon
                        ];
                        $tabName = $tabInfo[$tabKey]['name'];
                        $tabIcon = $tabInfo[$tabKey]['icon'];
                        $isActive = ($activeTab == $tabKey);
                        $activeClasses = 'active text-primary-400 border-primary-400 bg-gray-800';
                        $inactiveClasses = 'text-gray-400 hover:text-primary-300 border-transparent hover:border-gray-500 hover:bg-gray-700/30';
                    ?>
                    <button
                        type="button"
                        id="<?php echo $tabKey; ?>-tab-btn"
                        class="dashboard-tab px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap transition-colors duration-200 ease-in-out flex items-center gap-2 <?php echo $isActive ? $activeClasses : $inactiveClasses; ?>"
                        aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                        role="tab"
                        aria-controls="<?php echo $tabKey; ?>-tab-content"
                    >
                        <span class="lucide text-base" aria-hidden="true"><?php echo $tabIcon; ?></span>
                        <?php echo $tabName; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl p-6 md:p-8 min-h-[400px]">

            <div id="profile-tab-content" class="dashboard-content <?php echo ($activeTab != 'profile') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="profile-tab-btn">
                <h2 class="text-xl font-semibold text-white mb-6">Personal Information</h2>
                <form id="profile-form" action="process-profile-update.php" method="post" class="space-y-6 max-w-2xl">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); // Ensure token generation ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="profile-name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                            <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label for="profile-email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                            <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" readonly class="w-full px-3 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-gray-400 focus:outline-none cursor-not-allowed" title="Email cannot be changed">
                            <p class="text-xs text-gray-500 mt-1">Contact support to change your email.</p>
                        </div>
                        <div>
                            <label for="profile-phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number</label>
                            <input type="tel" id="profile-phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                        </div>
                        <div>
                            <label for="profile-language" class="block text-sm font-medium text-gray-300 mb-1">Preferred Language</label>
                            <select id="profile-language" name="language" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                                <option value="en" <?php echo (isset($currentUser['language']) && $currentUser['language'] == 'en') ? 'selected' : ''; ?>>English</option>
                                </select>
                        </div>
                    </div>

                    <div class="border-t border-gray-700 pt-6">
                        <h3 class="text-lg font-medium text-white mb-4">Notification Preferences</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-email" name="notify_email" value="1" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded border-gray-600 bg-gray-700" <?php echo !empty($currentUser['preferences']['notify_email']) ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-300 text-sm">Email notifications (ride updates, receipts)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-sms" name="notify_sms" value="1" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded border-gray-600 bg-gray-700" <?php echo !empty($currentUser['preferences']['notify_sms']) ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-300 text-sm">SMS notifications (driver arrival, important alerts)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-promotions" name="notify_promotions" value="1" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded border-gray-600 bg-gray-700" <?php echo !empty($currentUser['preferences']['notify_promotions']) ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-300 text-sm">Promotional messages and offers</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full md:w-auto bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-8 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-2 focus:ring-offset-gray-800">Save Changes</button>
                    </div>
                </form>

                <div id="pending-payments-section" class="mt-10 pt-6 border-t border-gray-700">
                    <h2 class="text-xl font-semibold text-white mb-4">Pending Payments</h2>
                    <div id="pending-payments-list" class="space-y-4">
                        <p class="text-gray-400 text-center py-4">Checking for pending payments...</p>
                    </div>
                </div>
            </div><div id="rides-tab-content" class="dashboard-content <?php echo ($activeTab != 'rides') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="rides-tab-btn">
                <p class="text-gray-400 text-center py-8">Loading ride history...</p>
            </div><div id="places-tab-content" class="dashboard-content <?php echo ($activeTab != 'places') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="places-tab-btn">
                <p class="text-gray-400 text-center py-8">Loading saved places...</p>
            </div><div id="payment-tab-content" class="dashboard-content <?php echo ($activeTab != 'payment') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="payment-tab-btn">
                <p class="text-gray-400 text-center py-8">Loading payment methods...</p>
            </div><div id="rewards-tab-content" class="dashboard-content <?php echo ($activeTab != 'rewards') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="rewards-tab-btn">
                <div class="flex flex-col lg:flex-row gap-6">
                    <div class="lg:w-1/3">
                        <div class="bg-gradient-to-br from-primary-700 to-primary-900 p-6 rounded-xl shadow-lg border border-primary-600 h-full flex flex-col justify-center">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-white">Your Points</h3>
                                <span class="lucide text-2xl text-yellow-400" aria-hidden="true">&#xeae5;</span>
                            </div>
                            <div class="user-reward-points-display text-3xl font-bold text-white mb-1">...</div>
                            <p class="text-primary-200 text-sm">Points available to redeem</p>
                            <p class="text-xs text-primary-300/70 mt-3">Earn more points with every completed ride!</p>
                        </div>
                    </div>

                    <div class="lg:w-2/3 space-y-6">
                        <div>
                             <h3 class="text-lg font-semibold text-white mb-4">Available Rewards</h3>
                             <div class="rewards-list-container space-y-4">
                                 <p class="text-gray-400 text-center py-4">Loading available rewards...</p>
                             </div>
                        </div>
                        <div class="pt-6 border-t border-gray-700">
                             <h3 class="text-lg font-semibold text-white mb-4">Redemption History</h3>
                             <div class="redemption-history-container max-h-60 overflow-y-auto pr-2">
                                 <p class="text-gray-400 text-center py-4">Loading redemption history...</p>
                             </div>
                        </div>
                    </div>
                </div>
            </div></div></div>
</main>

<div id="ride-details-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden p-4" role="dialog" aria-modal="true" aria-labelledby="ride-details-title">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="ride-details-modal-overlay" aria-hidden="true"></div>

    <div class="modal-content bg-gray-800 rounded-xl shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10 animate-slide-up">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-full p-1" aria-label="Close modal">
            <span class="lucide text-xl" aria-hidden="true">&#xea76;</span> </button>

        <div class="p-6 sm:p-8">
            <h2 id="ride-details-title" class="text-xl sm:text-2xl font-semibold text-white mb-6">Ride Details</h2>

            <div class="space-y-4">
                 <div class="bg-gray-700/30 rounded-lg p-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <div>
                             <p class="text-sm text-gray-400">
                                 <span id="ride-details-date">Date</span> at <span id="ride-details-time">Time</span>
                             </p>
                             <p class="text-lg font-medium text-white mt-1" id="ride-details-fare">Fare</p>
                        </div>
                         <div id="ride-details-status-badge" class="mt-2 sm:mt-0">
                             <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-600/20 text-gray-300 border border-gray-500/30">
                                 Loading...
                             </span>
                         </div>
                    </div>
                 </div>

                 <div>
                     <h4 class="text-sm font-medium text-gray-400 mb-1">Route</h4>
                     <div class="bg-gray-700/30 rounded-lg p-3 space-y-2">
                         <p class="text-white flex items-start">
                             <span class="lucide mr-2 text-green-400 mt-1 text-sm" aria-hidden="true">&#xea4b;</span>
                             <span id="ride-details-pickup">Pickup Address</span>
                         </p>
                         <p class="text-white flex items-start">
                             <span class="lucide mr-2 text-red-400 mt-1 text-sm" aria-hidden="true">&#xea4a;</span>
                             <span id="ride-details-dropoff">Dropoff Address</span>
                         </p>
                     </div>
                 </div>

                 <div>
                      <h4 class="text-sm font-medium text-gray-400 mb-1">Driver</h4>
                      <div class="bg-gray-700/30 rounded-lg p-3">
                           <p class="text-white font-medium" id="details-driver-name">Driver Name</p>
                           <div class="text-xs text-gray-400 mt-1 space-x-3">
                                <span>Rating: <span id="details-driver-rating">N/A</span></span>
                                <span>Vehicle: <span id="details-driver-vehicle">N/A</span> (<span id="details-driver-plate">N/A</span>)</span>
                                <span>Phone: <span id="details-driver-phone">N/A</span></span>
                           </div>
                      </div>
                 </div>
                 <p class="text-xs text-gray-500">Vehicle Type: <span id="ride-details-vehicle-type">N/A</span></p>

            </div>
            <div class="mt-6 pt-4 border-t border-gray-700 flex justify-end">
                <button type="button" class="modal-close-btn bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    Close
                </button>
                </div>
        </div>
    </div>
</div> <?php
// The footer includes dashboard.js which contains all the logic
include_once 'includes/footer.php';
?>