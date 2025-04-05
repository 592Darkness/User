<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Log current session state
error_log("Dashboard session: " . json_encode($_SESSION));

// Check if user is logged in
if (!isLoggedIn()) {
    error_log("User not logged in, redirecting");
    setFlashMessage('error', 'Please log in to access your dashboard.');
    redirect('index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$currentUser = getCurrentUser();

// For debugging
error_log("Dashboard user ID: $userId");
error_log("Current user: " . json_encode($currentUser));

// If we're logged in but no user data found, try to fetch it from database
if (!$currentUser || empty($currentUser)) {
    error_log("User ID exists but no user data, trying to fetch from database");
    
    try {
        $conn = dbConnect();
        $stmt = $conn->prepare("SELECT id, name, email, phone, language, created_at, updated_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $currentUser = $result->fetch_assoc();
            $_SESSION['user'] = $currentUser;
            error_log("User data fetched from database: " . json_encode($currentUser));
        } else {
            error_log("User not found in database!");
            session_destroy();
            setFlashMessage('error', 'Your account could not be found. Please login again.');
            redirect('index.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Include header
include_once 'includes/header.php';
?>

<!-- Add user data for JavaScript -->
<script id="user-data" type="application/json"><?php echo json_encode($currentUser); ?></script>

<main class="flex-grow container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-white">Account Dashboard</h1>
            <p class="text-gray-400">Welcome, <span class="user-display-name font-medium text-white"><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></span></p>
        </div>
        
        <div class="mb-8 border-b border-gray-700">
            <div class="flex overflow-x-auto hide-scrollbar">
                <button type="button" id="profile-tab-btn" class="dashboard-tab <?php echo ($activeTab == 'profile') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'profile') ? 'true' : 'false'; ?>" role="tab">Profile</button>
                <button type="button" id="rides-tab-btn" class="dashboard-tab <?php echo ($activeTab == 'rides') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'rides') ? 'true' : 'false'; ?>" role="tab">Ride History</button>
                <button type="button" id="places-tab-btn" class="dashboard-tab <?php echo ($activeTab == 'places') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'places') ? 'true' : 'false'; ?>" role="tab">Saved Places</button>
                <button type="button" id="payment-tab-btn" class="dashboard-tab <?php echo ($activeTab == 'payment') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'payment') ? 'true' : 'false'; ?>" role="tab">Payment Methods</button>
                <button type="button" id="rewards-tab-btn" class="dashboard-tab <?php echo ($activeTab == 'rewards') ? 'active text-primary-400 border-primary-400' : 'text-gray-400 hover:text-primary-300 border-transparent'; ?> px-4 py-3 text-center font-medium border-b-2 whitespace-nowrap" aria-selected="<?php echo ($activeTab == 'rewards') ? 'true' : 'false'; ?>" role="tab">Rewards</button>
            </div>
        </div>
        
        <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl p-6 md:p-8">
            <!-- Profile Tab -->
            <div id="profile-tab-content" class="dashboard-content <?php echo ($activeTab != 'profile') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="profile-tab-btn">
                <h2 class="text-xl font-semibold text-white mb-6">Personal Information</h2>
                <form id="profile-form" action="process-profile-update.php" method="post" class="space-y-6 max-w-2xl">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="profile-name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                            <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="profile-email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                            <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="profile-phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number</label>
                            <input type="tel" id="profile-phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="profile-language" class="block text-sm font-medium text-gray-300 mb-1">Preferred Language</label>
                            <select id="profile-language" name="language" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="en" <?php echo ($currentUser['language'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="es" <?php echo ($currentUser['language'] ?? 'en') == 'es' ? 'selected' : ''; ?>>Spanish</option>
                                <option value="pt" <?php echo ($currentUser['language'] ?? 'en') == 'pt' ? 'selected' : ''; ?>>Portuguese</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-700 pt-6">
                        <h3 class="text-lg font-medium text-white mb-4">Notification Preferences</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-email" name="notify_email" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded" checked>
                                <span class="ml-2 text-gray-300">Email notifications</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-sms" name="notify_sms" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded" checked>
                                <span class="ml-2 text-gray-300">SMS notifications</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" id="notify-promotions" name="notify_promotions" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded">
                                <span class="ml-2 text-gray-300">Promotional messages and offers</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" class="w-full md:w-auto bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-8 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">Save Changes</button>
                    </div>
                </form>
            </div>
            
            <!-- Other tabs with simplified content -->
            <div id="rides-tab-content" class="dashboard-content <?php echo ($activeTab != 'rides') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="rides-tab-btn">
                <div class="text-center py-8 text-gray-400">
                    <p>Your ride history will appear here once you take your first ride.</p>
                    <p class="mt-2">Book your first ride on the <a href="index.php" class="text-primary-400 hover:text-primary-300">home page</a>.</p>
                </div>
            </div>
            
            <div id="places-tab-content" class="dashboard-content <?php echo ($activeTab != 'places') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="places-tab-btn">
                <div class="text-center py-8 text-gray-400">
                    <p>You haven't saved any places yet.</p>
                </div>
            </div>
            
            <div id="payment-tab-content" class="dashboard-content <?php echo ($activeTab != 'payment') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="payment-tab-btn">
                <div class="text-center py-8 text-gray-400">
                    <p>No payment methods added yet.</p>
                </div>
            </div>
            
            <div id="rewards-tab-content" class="dashboard-content <?php echo ($activeTab != 'rewards') ? 'hidden' : ''; ?>" role="tabpanel" aria-labelledby="rewards-tab-btn">
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="md:w-1/3">
                        <div class="bg-gradient-to-br from-primary-700 to-primary-900 p-6 rounded-xl shadow-lg border border-primary-600">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-white">Your Points</h3>
                                <span class="lucide text-2xl text-yellow-400" aria-hidden="true">&#xeae5;</span>
                            </div>
                            <div class="text-3xl font-bold text-white mb-1">0</div>
                            <p class="text-primary-200 text-sm">Points earned</p>
                        </div>
                    </div>
                    
                    <div class="md:w-2/3">
                        <h3 class="text-lg font-semibold text-white mb-4">Available Rewards</h3>
                        <div class="space-y-4">
                            <div class="bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col md:flex-row justify-between">
                                <div>
                                    <h4 class="font-medium text-white">10% Off Your Next Ride</h4>
                                    <p class="text-gray-400 text-sm mt-1">Get a discount on your next ride anywhere in Guyana</p>
                                </div>
                                <div class="mt-3 md:mt-0 flex items-center">
                                    <span class="text-yellow-400 font-semibold mr-3">500 points</span>
                                    <button class="bg-primary-500 hover:bg-primary-600 text-white font-medium py-1 px-4 rounded-lg transition duration-300 shadow-md text-sm opacity-50 cursor-not-allowed" disabled>Redeem</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Simple inline dashboard script
document.addEventListener('DOMContentLoaded', function() {
    console.log("Dashboard loaded - inline version");
    
    // Setup tab switching
    document.querySelectorAll('.dashboard-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.id.replace('-tab-btn', '');
            
            // Hide all contents
            document.querySelectorAll('.dashboard-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Deactivate all buttons
            document.querySelectorAll('.dashboard-tab').forEach(tab => {
                tab.classList.remove('text-primary-400', 'border-primary-400');
                tab.classList.add('text-gray-400', 'hover:text-primary-300', 'border-transparent');
                tab.setAttribute('aria-selected', 'false');
            });
            
            // Activate selected tab
            document.getElementById(`${tabName}-tab-content`).classList.remove('hidden');
            btn.classList.add('text-primary-400', 'border-primary-400');
            btn.classList.remove('text-gray-400', 'hover:text-primary-300', 'border-transparent');
            btn.setAttribute('aria-selected', 'true');
            
            // Update URL with tab parameter
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url);
            } catch (e) {
                console.error("Error updating URL:", e);
            }
        });
    });
    
    // Handle logout
    document.querySelectorAll('#logout-link, #mobile-logout-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            window.location.href = "logout.php";
        });
    });
});
</script>

<?php
include_once 'includes/footer.php';
?>