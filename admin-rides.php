<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Set page title
$pageTitle = "Manage Rides - Admin Dashboard";
// Generate CSRF token for this page
$csrf_token = generateCSRFToken();
// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Handle filters
$filters = [];
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Get rides function
function getAllRides($page = 1, $perPage = 15, $filters = []) {
    try {
        $offset = ($page - 1) * $perPage;
        
        // Base query parts
        $countQueryBase = "SELECT COUNT(*) as total FROM rides r";
        $queryBase = "
            SELECT 
                r.id, r.pickup, r.dropoff, r.fare, r.status, 
                r.created_at, r.completed_at, r.vehicle_type,
                u.name as user_name,
                d.name as driver_name
            FROM rides r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN drivers d ON r.driver_id = d.id
        ";
        
        // Initialize where clauses and parameters
        $whereConditions = [];
        $countParams = [];
        $queryParams = [];
        
        // Add filters
        if (!empty($filters['status'])) {
            $whereConditions[] = "r.status = ?";
            $countParams[] = $filters['status'];
            $queryParams[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = "r.created_at >= ?";
            $countParams[] = $filters['date_from'] . ' 00:00:00';
            $queryParams[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = "r.created_at <= ?";
            $countParams[] = $filters['date_to'] . ' 23:59:59';
            $queryParams[] = $filters['date_to'] . ' 23:59:59';
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = "%" . $filters['search'] . "%";
            $whereConditions[] = "(r.pickup LIKE ? OR r.dropoff LIKE ? OR u.name LIKE ? OR d.name LIKE ?)";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
            $queryParams[] = $searchTerm;
        }
        
        // Construct final queries
        $countQuery = $countQueryBase;
        $query = $queryBase;
        
        if (!empty($whereConditions)) {
            $whereClause = " WHERE " . implode(" AND ", $whereConditions);
            $countQuery .= $whereClause;
            $query .= $whereClause;
        }
        
        // Execute count query
        $totalResult = dbFetchOne($countQuery, $countParams);
        $total = $totalResult ? (int)$totalResult['total'] : 0;
        
        // Add pagination to main query only
        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $queryParams[] = $perPage;
        $queryParams[] = $offset;
        
        // Execute main query
        $rides = dbFetchAll($query, $queryParams);
        
        return [
            'rides' => $rides ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => ceil($total / $perPage)
        ];
    } catch (Exception $e) {
        error_log("Error getting all rides: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [
            'rides' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'pageCount' => 0
        ];
    }
}

// Get rides with pagination
$ridesData = getAllRides($page, $perPage, $filters);
$rides = $ridesData['rides'];
$totalRides = $ridesData['total'];
$pageCount = $ridesData['pageCount'];

// Include admin header
require_once 'includes/admin-header.php';
?>

<div class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">Manage Rides</h1>
        
        <div class="flex gap-2">
            <form class="search-form flex" action="admin-rides.php" method="GET">
                <!-- Preserve existing filters in hidden fields -->
                <?php if (isset($filters['status']) && !empty($filters['status'])): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filters['status']); ?>">
                <?php endif; ?>
                
                <?php if (isset($filters['date_from']) && !empty($filters['date_from'])): ?>
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                <?php endif; ?>
                
                <?php if (isset($filters['date_to']) && !empty($filters['date_to'])): ?>
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                <?php endif; ?>
                
                <input type="text" name="search" placeholder="Search rides..." value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>" class="w-full md:w-64 px-3 py-2 bg-gray-700 border border-gray-600 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <button type="submit" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-r-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeac3;</span>
                </button>
            </form>
            
            <button type="button" id="filter-toggle-btn" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md">
                <span class="lucide mr-1" aria-hidden="true">&#xeb94;</span>
                Filters
            </button>
        </div>
    </div>
    
    <!-- Filter Panel -->
    <div id="filter-panel" class="bg-gray-800 rounded-lg border border-gray-700 p-4 <?php echo empty($filters) ? 'hidden' : ''; ?>">
        <form action="admin-rides.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <?php if (isset($filters['search']) && !empty($filters['search'])): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>">
            <?php endif; ?>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                <select id="status" name="status" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="">All Statuses</option>
                    <option value="completed" <?php echo (isset($filters['status']) && $filters['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo (isset($filters['status']) && $filters['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="in_progress" <?php echo (isset($filters['status']) && $filters['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="searching" <?php echo (isset($filters['status']) && $filters['status'] === 'searching') ? 'selected' : ''; ?>>Searching</option>
                    <option value="confirmed" <?php echo (isset($filters['status']) && $filters['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="arriving" <?php echo (isset($filters['status']) && $filters['status'] === 'arriving') ? 'selected' : ''; ?>>Arriving</option>
                    <option value="arrived" <?php echo (isset($filters['status']) && $filters['status'] === 'arrived') ? 'selected' : ''; ?>>Arrived</option>
                </select>
            </div>
            
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-300 mb-1">From Date</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo isset($filters['date_from']) ? htmlspecialchars($filters['date_from']) : ''; ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-300 mb-1">To Date</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo isset($filters['date_to']) ? htmlspecialchars($filters['date_to']) : ''; ?>" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    Apply Filters
                </button>
                <a href="admin-rides.php" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Rides Table -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-700/50 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">User</th>
                        <th class="py-3 px-4 text-left">Driver</th>
                        <th class="py-3 px-4 text-left">Route</th>
                        <th class="py-3 px-4 text-right">Fare</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Date</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($rides)): ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500">
                            <?php if (!empty($filters)): ?>
                                No rides found matching your filters.
                                <a href="admin-rides.php" class="text-primary-400 hover:text-primary-300">Clear filters</a>
                            <?php else: ?>
                                No rides available.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rides as $ride): ?>
                        <tr class="hover:bg-gray-700/50 transition-colors">
                            <td class="py-3 px-4">#<?php echo $ride['id']; ?></td>
                            <td class="py-3 px-4 text-white"><?php echo htmlspecialchars($ride['user_name'] ?? 'Unknown'); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($ride['driver_name'] ?? 'Unassigned'); ?></td>
                            <td class="py-3 px-4 text-xs">
                                <div class="text-gray-300"><?php echo htmlspecialchars(substr($ride['pickup'], 0, 20) . (strlen($ride['pickup']) > 20 ? '...' : '')); ?></div>
                                <div class="text-gray-500">→ <?php echo htmlspecialchars(substr($ride['dropoff'], 0, 20) . (strlen($ride['dropoff']) > 20 ? '...' : '')); ?></div>
                            </td>
                            <td class="py-3 px-4 text-right text-yellow-400"><?php echo formatCurrency($ride['fare']); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo getRideStatusColor($ride['status']); ?>">
                                    <?php echo ucfirst($ride['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-xs text-gray-400"><?php echo date('M j, Y g:i A', strtotime($ride['created_at'])); ?></td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button class="view-ride-btn p-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors" title="View Details" data-ride-id="<?php echo $ride['id']; ?>">
                                        <span class="lucide" aria-hidden="true">&#xea6d;</span>
                                    </button>
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
                Showing <?php echo (($page - 1) * $perPage) + 1; ?> to <?php echo min($page * $perPage, $totalRides); ?> of <?php echo $totalRides; ?> rides
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="admin-rides.php?page=<?php echo $page - 1; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa2;</span>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $pageCount): ?>
                <a href="admin-rides.php?page=<?php echo $page + 1; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa0;</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Ride Modal -->
<div id="view-ride-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="view-ride-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10 p-6">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6">Ride Details</h2>
        
        <div id="ride-details-content">
            <div class="animate-pulse space-y-4">
                <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                <div class="h-4 bg-gray-700 rounded w-2/3"></div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button type="button" class="modal-close-btn bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Include CSRF token for AJAX requests -->
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

<script src="assets/js/admin-rides.js"></script>

<script>
// Toggle filter panel
function toggleFilterPanel() {
    const filterPanel = document.getElementById('filter-panel');
    if (filterPanel.classList.contains('hidden')) {
        filterPanel.classList.remove('hidden');
    } else {
        filterPanel.classList.add('hidden');
    }
}

// Modal functions
function openModal(modalId) {
    console.log(`Opening modal: ${modalId}`);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.classList.remove('animate-slide-down');
            modalContent.classList.add('animate-slide-up');
        }
    } else {
        console.error(`Modal not found: ${modalId}`);
    }
}

function closeModal(modalId) {
    console.log(`Closing modal: ${modalId}`);
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.classList.remove('animate-slide-up');
            modalContent.classList.add('animate-slide-down');
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
                modalContent.classList.remove('animate-slide-down');
                modalContent.classList.add('animate-slide-up');
            }, 300);
        } else {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    } else {
        console.error(`Modal not found: ${modalId}`);
    }
}

// View ride details
function openViewRideModal(rideId) {
    console.log(`Opening ride details for ID: ${rideId}`);
    openModal('view-ride-modal');
    
    // Show loading state
    document.getElementById('ride-details-content').innerHTML = `
        <div class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
            <div class="h-4 bg-gray-700 rounded w-1/2"></div>
            <div class="h-4 bg-gray-700 rounded w-2/3"></div>
        </div>
    `;
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
    if (!csrfToken) {
        console.error("CSRF token not found!");
        document.getElementById('ride-details-content').innerHTML = `
            <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                <p>Security token missing. Please refresh the page.</p>
            </div>
        `;
        return;
    }
    
    // Fetch ride details
    fetch('process-admin-ride.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'get_ride',
            ride_id: rideId,
            csrf_token: csrfToken
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const ride = data.ride;
            
            // Format the content
            const content = `
                <div class="bg-gray-700/30 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-medium text-white">Ride #${ride.id}</h3>
                            <p class="text-sm text-gray-400">Created ${new Date(ride.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getRideStatusClass(ride.status)}">
                            ${ride.status.charAt(0).toUpperCase() + ride.status.slice(1)}
                        </span>
                    </div>
                </div>
                
                <div class="space-y-4 mb-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Route Information</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3">
                            <p class="text-white flex items-start">
                                <span class="lucide mr-2 text-gray-400 mt-1" aria-hidden="true">&#xea4b;</span> 
                                <span>${ride.pickup}</span>
                            </p>
                            <div class="ml-6 my-1 border-l-2 border-gray-600 h-4"></div>
                            <p class="text-white flex items-start">
                                <span class="lucide mr-2 text-gray-400 mt-1" aria-hidden="true">&#xea4a;</span>
                                <span>${ride.dropoff}</span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">User</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                <p class="text-white">${ride.user_name || 'Unknown'}</p>
                                ${ride.user_email ? `<p class="text-xs text-gray-400 mt-1">${ride.user_email}</p>` : ''}
                                ${ride.user_phone ? `<p class="text-xs text-gray-400">${ride.user_phone}</p>` : ''}
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Driver</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                ${ride.driver_name ? 
                                    `<p class="text-white">${ride.driver_name}</p>
                                     ${ride.driver_phone ? `<p class="text-xs text-gray-400 mt-1">${ride.driver_phone}</p>` : ''}
                                     ${ride.vehicle_type ? `<p class="text-xs text-gray-400">${ride.vehicle_type.toUpperCase()} vehicle</p>` : ''}`
                                    : 
                                    `<p class="text-gray-500">No driver assigned</p>`
                                }
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Fare</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                <p class="text-xl font-medium text-yellow-400">${formatCurrency(ride.fare)}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Vehicle Type</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                <p class="text-white">${ride.vehicle_type ? ride.vehicle_type.charAt(0).toUpperCase() + ride.vehicle_type.slice(1) : 'Unknown'}</p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Created</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                <p class="text-white">${new Date(ride.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                                <p class="text-xs text-gray-400 mt-1">${new Date(ride.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}</p>
                            </div>
                        </div>
                    </div>
                    
                    ${ride.completed_at ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Completion</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3">
                            <p class="text-white">Completed at ${new Date(ride.completed_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}</p>
                            ${ride.status === 'completed' ? `<p class="text-xs text-green-400 mt-1">Ride completed successfully</p>` : ''}
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('ride-details-content').innerHTML = content;
        } else {
            document.getElementById('ride-details-content').innerHTML = `
                <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                    <p>${data.message || 'Error fetching ride data'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('ride-details-content').innerHTML = `
            <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                <p>Failed to fetch ride data. Please try again.</p>
            </div>
        `;
    });
}

// Helper function for ride status color classes
function getRideStatusClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-500/20 text-green-400';
        case 'cancelled':
        case 'canceled':
            return 'bg-red-500/20 text-red-400';
        case 'in_progress':
            return 'bg-blue-500/20 text-blue-400';
        case 'searching':
            return 'bg-yellow-500/20 text-yellow-400';
        case 'confirmed':
            return 'bg-indigo-500/20 text-indigo-400';
        case 'arriving':
            return 'bg-purple-500/20 text-purple-400';
        case 'arrived':
            return 'bg-pink-500/20 text-pink-400';
        default:
            return 'bg-gray-500/20 text-gray-400';
    }
}

// Format currency for display
function formatCurrency(amount) {
    return 'G$' + parseInt(amount).toLocaleString();
}

// When document is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log("Admin Rides page initialized");
    
    // Attach event listeners
    document.querySelectorAll('button[onclick*="openViewRideModal"]').forEach(button => {
        // Extract the ride ID from the onclick attribute
        const onclickAttr = button.getAttribute('onclick');
        const rideIdMatch = onclickAttr.match(/openViewRideModal\((\d+)\)/);
        
        if (rideIdMatch && rideIdMatch[1]) {
            const rideId = rideIdMatch[1];
            
            // Remove the onclick attribute and add a proper event listener
            button.removeAttribute('onclick');
            
            button.addEventListener('click', function(e) {
                e.preventDefault();
                console.log(`View button clicked for ride ID: ${rideId}`);
                openViewRideModal(rideId);
            });
            
            console.log(`Fixed view button for ride ID: ${rideId}`);
        }
    });
    
    // Wire up modal overlay clicks
    const modalOverlays = document.querySelectorAll('[id$="-modal-overlay"]');
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                const modalId = this.id.replace('-overlay', '');
                closeModal(modalId);
            }
        });
    });
    
    // Close buttons
    document.querySelectorAll('.modal-close-btn').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('[id$="-modal"]');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[id$="-modal"][style*="flex"]').forEach(modal => {
                closeModal(modal.id);
            });
        }
    });
});
</script>

<?php
// Include admin footer
require_once 'includes/admin-footer.php';
?>