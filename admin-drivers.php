<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Set page title
$pageTitle = "Manage Drivers - Admin Dashboard";

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get drivers with pagination
$driversData = getAllDrivers($page, $perPage, $search);
$drivers = $driversData['drivers'];
$totalDrivers = $driversData['total'];
$pageCount = $driversData['pageCount'];

// Include admin header
require_once 'includes/admin-header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">Manage Drivers</h1>
        
        <div class="flex gap-2">
            <form class="search-form flex" action="admin-drivers.php" method="GET">
                <input type="text" name="search" placeholder="Search drivers..." value="<?php echo htmlspecialchars($search); ?>" class="w-full md:w-64 px-3 py-2 bg-gray-700 border border-gray-600 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <button type="submit" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-r-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeac3;</span>
                </button>
            </form>
            
            <a href="#" onclick="openAddDriverModal()" class="bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md">
                <span class="lucide mr-1" aria-hidden="true">&#xea9a;</span>
                Add Driver
            </a>
        </div>
    </div>

    <!-- Drivers Table -->
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
                                <a href="admin-drivers.php" class="text-primary-400 hover:text-primary-300">Clear search</a>
                            <?php else: ?>
                                No drivers available. 
                                <a href="#" onclick="openAddDriverModal()" class="text-primary-400 hover:text-primary-300">Add your first driver</a>
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
                                            <?php echo $driver['status'] === 'available' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'; ?>">
                                    <?php echo ucfirst($driver['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right"><?php echo number_format($driver['total_rides']); ?></td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center">
                                    <span class="lucide text-yellow-400 mr-1" aria-hidden="true">&#xeae5;</span>
                                    <span><?php echo number_format($driver['avg_rating'] ?? 0, 1); ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <button onclick="openEditDriverModal(<?php echo $driver['id']; ?>)" class="p-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors" title="Edit Driver">
                                        <span class="lucide" aria-hidden="true">&#xea71;</span>
                                    </button>
                                    <button onclick="openViewDriverModal(<?php echo $driver['id']; ?>)" class="p-1.5 bg-green-500/20 text-green-400 rounded-lg hover:bg-green-500/30 transition-colors" title="View Details">
                                        <span class="lucide" aria-hidden="true">&#xea6d;</span>
                                    </button>
                                    <?php if ($driver['total_rides'] == 0): ?>
                                    <button onclick="confirmDeleteDriver(<?php echo $driver['id']; ?>, '<?php echo htmlspecialchars(addslashes($driver['name'])); ?>')" class="p-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors" title="Delete Driver">
                                        <span class="lucide" aria-hidden="true">&#xea0f;</span>
                                    </button>
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
                    <span class="lucide" aria-hidden="true">&#xeaa2;</span>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $pageCount): ?>
                <a href="admin-drivers.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa0;</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Driver Modal -->
<div id="add-driver-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="add-driver-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal" onclick="closeModal('add-driver-modal')">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6">Add New Driver</h2>
        
        <form id="add-driver-form" action="process-admin-driver.php" method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Full Name *</label>
                    <input type="text" id="name" name="name" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address *</label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password *</label>
                <input type="password" id="password" name="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="vehicle" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Model *</label>
                    <input type="text" id="vehicle" name="vehicle" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label for="plate" class="block text-sm font-medium text-gray-300 mb-1">License Plate *</label>
                    <input type="text" id="plate" name="plate" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>
            
            <div>
                <label for="vehicle_type" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Type *</label>
                <select id="vehicle_type" name="vehicle_type" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="standard">Standard</option>
                    <option value="suv">SUV</option>
                    <option value="premium">Premium</option>
                </select>
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-1">Initial Status</label>
                <select id="status" name="status" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="available">Available</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
            
            <div class="pt-4">
                <a href="#" class="bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md" onclick="forceOpenAddDriverModal(); return false;">
                   <span class="lucide mr-1" aria-hidden="true">&#xea9a;</span>
                   Add Driver
                </a>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Driver Modal -->
<div id="edit-driver-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="edit-driver-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal" onclick="closeModal('edit-driver-modal')">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6">Edit Driver</h2>
        
        <form id="edit-driver-form" action="process-admin-driver.php" method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_driver_id" name="driver_id" value="">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-300 mb-1">Full Name *</label>
                    <input type="text" id="edit_name" name="name" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label for="edit_phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number *</label>
                    <input type="tel" id="edit_phone" name="phone" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>
            
            <div>
                <label for="edit_email" class="block text-sm font-medium text-gray-300 mb-1">Email Address *</label>
                <input type="email" id="edit_email" name="email" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            
            <div>
                <label for="edit_password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <input type="password" id="edit_password" name="password" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="edit_vehicle" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Model *</label>
                    <input type="text" id="edit_vehicle" name="vehicle" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div>
                    <label for="edit_plate" class="block text-sm font-medium text-gray-300 mb-1">License Plate *</label>
                    <input type="text" id="edit_plate" name="plate" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
            </div>
            
            <div>
                <label for="edit_vehicle_type" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Type *</label>
                <select id="edit_vehicle_type" name="vehicle_type" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="standard">Standard</option>
                    <option value="suv">SUV</option>
                    <option value="premium">Premium</option>
                </select>
            </div>
            
            <div>
                <label for="edit_status" class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                <select id="edit_status" name="status" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <option value="available">Available</option>
                    <option value="offline">Offline</option>
                    <option value="busy">Busy</option>
                </select>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                    Update Driver
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Driver Modal -->
<div id="view-driver-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="view-driver-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal" onclick="closeModal('view-driver-modal')">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6">Driver Details</h2>
        
        <div id="driver-details-content">
            <div class="animate-pulse space-y-4">
                <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                <div class="h-4 bg-gray-700 rounded w-2/3"></div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeModal('view-driver-modal')" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.classList.remove('animate-slide-down');
            modalContent.classList.add('animate-slide-up');
        }
    }
}

function closeModal(modalId) {
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
    }
}

// Click outside modal to close
document.addEventListener('DOMContentLoaded', function() {
    const modalOverlays = document.querySelectorAll('[id$="-modal-overlay"]');
    
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                const modalId = this.id.replace('-overlay', '');
                closeModal(modalId);
            }
        });
    });
    
    // Add form validation
    const addForm = document.getElementById('add-driver-form');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password && password.length < 8) {
                e.preventDefault();
                showConfirmation('Password must be at least 8 characters.', true);
            }
        });
    }
    
    const editForm = document.getElementById('edit-driver-form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const password = document.getElementById('edit_password').value;
            if (password && password.length < 8) {
                e.preventDefault();
                showConfirmation('Password must be at least 8 characters.', true);
            }
        });
    }
});

// Open Add Driver Modal
function openAddDriverModal() {
    // Reset form
    document.getElementById('add-driver-form').reset();
    openModal('add-driver-modal');
}

// Fetch driver data for edit
function openEditDriverModal(driverId) {
    showLoadingIndicator();
    
    fetch('process-admin-driver.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_driver',
            driver_id: driverId,
            csrf_token: document.querySelector('input[name="csrf_token"]').value
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingIndicator();
        
        if (data.success) {
            // Populate form fields
            document.getElementById('edit_driver_id').value = data.driver.id;
            document.getElementById('edit_name').value = data.driver.name;
            document.getElementById('edit_email').value = data.driver.email;
            document.getElementById('edit_phone').value = data.driver.phone;
            document.getElementById('edit_vehicle').value = data.driver.vehicle;
            document.getElementById('edit_plate').value = data.driver.plate;
            document.getElementById('edit_vehicle_type').value = data.driver.vehicle_type;
            document.getElementById('edit_status').value = data.driver.status;
            
            // Clear password field
            document.getElementById('edit_password').value = '';
            
            // Open modal
            openModal('edit-driver-modal');
        } else {
            showConfirmation(data.message || 'Error fetching driver data', true);
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error:', error);
        showConfirmation('Failed to fetch driver data. Please try again.', true);
    });
}

// View driver details
function openViewDriverModal(driverId) {
    openModal('view-driver-modal');
    
    // Show loading state
    document.getElementById('driver-details-content').innerHTML = `
        <div class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
            <div class="h-4 bg-gray-700 rounded w-1/2"></div>
            <div class="h-4 bg-gray-700 rounded w-2/3"></div>
        </div>
    `;
    
    // Fetch driver details
    fetch('process-admin-driver.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_driver',
            driver_id: driverId,
            csrf_token: document.querySelector('input[name="csrf_token"]').value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const driver = data.driver;
            
            // Format the content
            const content = `
                <div class="bg-gray-700/30 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-medium text-white">${driver.name}</h3>
                            <p class="text-sm text-gray-400">ID: #${driver.id}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    ${driver.status === 'available' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}">
                            ${driver.status.charAt(0).toUpperCase() + driver.status.slice(1)}
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Contact Information</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3">
                            <p class="text-white flex items-center">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xea1c;</span> 
                                ${driver.email}
                            </p>
                            <p class="text-white flex items-center mt-2">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xea9d;</span>
                                ${driver.phone}
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Vehicle Information</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3">
                            <p class="text-white flex items-center">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xeb15;</span>
                                ${driver.vehicle} (${driver.vehicle_type})
                            </p>
                            <p class="text-white flex items-center mt-2">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xea6d;</span>
                                ${driver.plate}
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Account Details</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Created</p>
                                <p class="text-gray-300">${new Date(driver.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Last Login</p>
                                <p class="text-gray-300">${driver.last_login ? new Date(driver.last_login).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Never'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <button onclick="openEditDriverModal(${driver.id})" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 text-sm">
                            <span class="lucide mr-1" aria-hidden="true">&#xea71;</span>
                            Edit Driver
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('driver-details-content').innerHTML = content;
        } else {
            document.getElementById('driver-details-content').innerHTML = `
                <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                    <p>${data.message || 'Error fetching driver data'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('driver-details-content').innerHTML = `
            <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                <p>Failed to fetch driver data. Please try again.</p>
            </div>
        `;
    });
}

// Confirm delete driver
function confirmDeleteDriver(driverId, driverName) {
    if (confirm(`Are you sure you want to delete driver: ${driverName}?\nThis action cannot be undone.`)) {
        showLoadingIndicator();
        
        fetch('process-admin-driver.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                driver_id: driverId,
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingIndicator();
            
            if (data.success) {
                showConfirmation(data.message || 'Driver deleted successfully');
                
                // Reload the page after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showConfirmation(data.message || 'Error deleting driver', true);
            }
        })
        .catch(error => {
            hideLoadingIndicator();
            console.error('Error:', error);
            showConfirmation('Failed to delete driver. Please try again.', true);
        });
    }
}
</script>

<?php
// Include admin footer
require_once 'includes/admin-footer.php';
?>
