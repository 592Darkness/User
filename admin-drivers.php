<?php
// 1. Include ALL necessary PHP logic files FIRST
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// 2. Perform authentication check *before* any HTML output
requireAdminLogin(); // This will exit or throw if not logged in

// Generate CSRF token for this page
$csrf_token = generateCSRFToken();

// 3. Set page-specific variables (like $pageTitle)
$pageTitle = "Manage Drivers - Admin Dashboard";

// Handle pagination parameters from URL
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1; // Ensure page is at least 1
$perPage = 10; // Number of drivers per page

// Handle search term from URL
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Process driver deletion if requested
if (isset($_GET['delete']) && isset($_GET['csrf_token'])) {
    $deleteId = (int)$_GET['delete'];
    $csrfToken = $_GET['csrf_token'];
    
    // Verify CSRF token
    if (verifyCSRFToken($csrfToken)) {
        $result = deleteDriver($deleteId);
        
        if ($result) {
            setFlashMessage('success', 'Driver deleted successfully.');
        } else {
            setFlashMessage('error', 'Failed to delete driver. This may be because the driver has associated rides.');
        }
    } else {
        setFlashMessage('error', 'Security validation failed. Please try again.');
    }
    
    // Redirect to avoid re-deletion on refresh
    header('Location: admin-drivers.php');
    exit;
}

// Get drivers data using the corrected function and pagination/search parameters
$driversData = getAllDrivers($page, $perPage, $search);
$drivers = $driversData['drivers'];
$totalDrivers = $driversData['total'];
$pageCount = $driversData['pageCount'];

// 4. Include the HTML header *after* auth check and variable setup
require_once 'includes/admin-header.php'; // This starts the HTML output

// --- Page Content ---
?>

<!-- Hidden input for CSRF token access by JavaScript -->
<input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">Manage Drivers</h1>
        <div class="flex gap-2">
            <form class="search-form flex" action="admin-drivers.php" method="GET">
                <input type="text" name="search" placeholder="Search drivers..." value="<?php echo htmlspecialchars($search); ?>" class="w-full md:w-64 px-3 py-2 bg-gray-700 border border-gray-600 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <button type="submit" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-r-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeac3;</span> </button>
            </form>
            
            <a href="edit-driver.php" class="bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md flex items-center">
                <span class="lucide mr-1" aria-hidden="true">&#xea9a;</span> Add Driver
            </a>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-700/50 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">Driver</th>
                        <th class="py-3 px-4 text-left">Contact</th>
                        <th class="py-3 px-4 text-left">Vehicle</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Rides</th>
                        <th class="py-3 px-4 text-center">Rating</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($drivers)): ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500">
                            <?php if (!empty($search)): ?>
                                No drivers found matching "<?php echo htmlspecialchars($search); ?>".
                                <a href="admin-drivers.php" class="text-primary-400 hover:text-primary-300 underline">Clear search</a>
                            <?php else: ?>
                                No drivers available.
                                <a href="edit-driver.php" class="text-primary-400 hover:text-primary-300 underline">Add your first driver</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                        <tr class="hover:bg-gray-700/50 transition-colors">
                            <td class="py-3 px-4">#<?php echo $driver['id']; ?></td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-white"><?php echo htmlspecialchars($driver['name']); ?></div>
                                <div class="text-xs text-gray-400">Since <?php echo date('M Y', strtotime($driver['created_at'])); ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-300"><?php echo htmlspecialchars($driver['email']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($driver['phone']); ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium"><?php echo htmlspecialchars($driver['vehicle']); ?></div>
                                <div class="text-xs text-yellow-400"><?php echo htmlspecialchars($driver['plate']); ?></div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php echo ($driver['status'] ?? 'offline') === 'available' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'; ?>">
                                    <?php echo ucfirst($driver['status'] ?? 'offline'); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right"><?php echo number_format($driver['total_rides'] ?? 0); ?></td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center">
                                    <span class="lucide text-yellow-400 mr-1 text-sm" aria-hidden="true">&#xeae5;</span> 
                                    <span><?php echo number_format($driver['avg_rating'] ?? 0, 1); ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <a href="edit-driver.php?id=<?php echo $driver['id']; ?>" class="p-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors" title="Edit Driver">
                                        <span class="lucide pointer-events-none" aria-hidden="true">&#xea71;</span>
                                    </a>
                                    <a href="edit-driver.php?id=<?php echo $driver['id']; ?>&mode=view" class="p-1.5 bg-green-500/20 text-green-400 rounded-lg hover:bg-green-500/30 transition-colors" title="View Details">
                                        <span class="lucide pointer-events-none" aria-hidden="true">&#xea11;</span>
                                    </a>
                                    <?php if (($driver['total_rides'] ?? 0) == 0): ?>
                                    <a href="javascript:confirmDelete(<?php echo $driver['id']; ?>, '<?php echo htmlspecialchars(addslashes($driver['name'])); ?>')" class="p-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors" title="Delete Driver">
                                        <span class="lucide pointer-events-none" aria-hidden="true">&#xea0f;</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pageCount > 1): ?>
        <div class="bg-gray-700/30 py-3 px-4 border-t border-gray-700 flex items-center justify-between">
            <div class="text-sm text-gray-400">
                Showing <?php echo (($page - 1) * $perPage) + 1; ?> to <?php echo min($page * $perPage, $totalDrivers); ?> of <?php echo $totalDrivers; ?> drivers
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="admin-drivers.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa2;</span> </a>
                <?php endif; ?>

                <?php if ($page < $pageCount): ?>
                <a href="admin-drivers.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa0;</span> </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Function to confirm and execute driver deletion
function confirmDelete(driverId, driverName) {
    if (confirm("Are you sure you want to delete driver: " + driverName + "?\nThis action cannot be undone and only works if the driver has NO associated rides.")) {
        window.location.href = "admin-drivers.php?delete=" + driverId + "&csrf_token=<?php echo $csrf_token; ?>";
    }
}
</script>

<?php
// Include the admin footer HTML structure
require_once 'includes/admin-footer.php';
?>