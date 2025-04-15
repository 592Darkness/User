<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Set page title
$pageTitle = "Manage Users - Admin Dashboard";

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get users with pagination
$usersData = getAllUsers($page, $perPage, $search);
$users = $usersData['users'];
$totalUsers = $usersData['total'];
$pageCount = $usersData['pageCount'];

// Include admin header
require_once 'includes/admin-header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">Manage Users</h1>
        
        <div class="flex gap-2">
            <form class="search-form flex" action="admin-users.php" method="GET">
                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" class="w-full md:w-64 px-3 py-2 bg-gray-700 border border-gray-600 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <button type="submit" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-r-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeac3;</span>
                </button>
            </form>
            
            <!-- Add New User Button -->
            <a href="edit-user.php" class="bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md flex items-center">
                <span class="lucide mr-1" aria-hidden="true">&#xea9a;</span> Add User
            </a>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-700/50 text-gray-400 text-xs uppercase tracking-wider">
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">User</th>
                        <th class="py-3 px-4 text-left">Contact</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Rides</th>
                        <th class="py-3 px-4 text-center">Joined</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">
                            <?php if (!empty($search)): ?>
                                No users found matching "<?php echo htmlspecialchars($search); ?>".
                                <a href="admin-users.php" class="text-primary-400 hover:text-primary-300">Clear search</a>
                            <?php else: ?>
                                No users available.
                                <a href="edit-user.php" class="text-primary-400 hover:text-primary-300">Add your first user</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-700/50 transition-colors">
                            <td class="py-3 px-4">#<?php echo $user['id']; ?></td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-white"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="text-xs text-gray-400">Since <?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-gray-300"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['phone']); ?></div>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $user['status'] === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right"><?php echo number_format($user['total_rides']); ?></td>
                            <td class="py-3 px-4 text-center text-xs text-gray-400">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <!-- Edit User Link (New) -->
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="p-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors" title="Edit User">
                                        <span class="lucide" aria-hidden="true">&#xea71;</span>
                                    </a>
                                    <button onclick="openViewUserModal(<?php echo $user['id']; ?>)" class="p-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors" title="View Details">
                                        <span class="lucide" aria-hidden="true">&#xea6d;</span>
                                    </button>
                                    <button onclick="openResetPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['name'])); ?>')" class="p-1.5 bg-yellow-500/20 text-yellow-400 rounded-lg hover:bg-yellow-500/30 transition-colors" title="Reset Password">
                                        <span class="lucide" aria-hidden="true">&#xea30;</span>
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
                Showing <?php echo (($page - 1) * $perPage) + 1; ?> to <?php echo min($page * $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="admin-users.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa2;</span>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $pageCount): ?>
                <a href="admin-users.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    <span class="lucide" aria-hidden="true">&#xeaa0;</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View User Modal -->
<div id="view-user-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="view-user-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal" onclick="closeModal('view-user-modal')">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6">User Details</h2>
        
        <div id="user-details-content">
            <div class="animate-pulse space-y-4">
                <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                <div class="h-4 bg-gray-700 rounded w-2/3"></div>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeModal('view-user-modal')" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-password-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="reset-password-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-md w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal" onclick="closeModal('reset-password-modal')">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-2">Reset User Password</h2>
        <p class="text-gray-400 mb-6">You are about to reset the password for <span id="reset-user-name" class="font-medium text-white"></span></p>
        
        <form id="reset-password-form" action="process-admin-user.php" method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" id="reset_user_id" name="user_id" value="">
            
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" minlength="8">
                    <button type="button" id="toggle-new-password" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400" aria-label="Toggle password visibility">
                        <span class="lucide text-lg toggle-password-icon" aria-hidden="true">&#xea30;</span>
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-1">Confirm Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" minlength="8">
                    <button type="button" id="toggle-confirm-password" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400" aria-label="Toggle password visibility">
                        <span class="lucide text-lg toggle-password-icon" aria-hidden="true">&#xea30;</span>
                    </button>
                </div>
            </div>
            
            <div class="flex items-center py-2">
                <input type="checkbox" id="notify_user" name="notify_user" class="h-4 w-4 rounded text-primary-500 focus:ring-primary-500">
                <label for="notify_user" class="ml-2 text-sm text-gray-300">Notify user by email</label>
            </div>
            
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="closeModal('reset-password-modal')" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                    Cancel
                </button>
                <button type="submit" class="flex-1 bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md">
                    Reset Password
                </button>
            </div>
        </form>
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

// View user details
function openViewUserModal(userId) {
    openModal('view-user-modal');
    
    // Show loading state
    document.getElementById('user-details-content').innerHTML = `
        <div class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-700 rounded w-3/4"></div>
            <div class="h-4 bg-gray-700 rounded w-1/2"></div>
            <div class="h-4 bg-gray-700 rounded w-2/3"></div>
        </div>
    `;
    
    // Fetch user details
    fetch('process-admin-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_user',
            user_id: userId,
            csrf_token: document.querySelector('input[name="csrf_token"]').value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const user = data.user;
            
            // Format the content
            const content = `
                <div class="bg-gray-700/30 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-medium text-white">${user.name}</h3>
                            <p class="text-sm text-gray-400">ID: #${user.id}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    ${user.status === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}">
                            ${user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'Unknown'}
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Contact Information</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3">
                            <p class="text-white flex items-center">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xea1c;</span> 
                                ${user.email}
                            </p>
                            <p class="text-white flex items-center mt-2">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xea9d;</span>
                                ${user.phone}
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-400 mb-1">Account Information</h4>
                        <div class="bg-gray-700/30 rounded-lg p-3">
                            <p class="text-white flex items-center">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xeb15;</span>
                                Total Rides: ${user.total_rides}
                            </p>
                            <p class="text-white flex items-center mt-2">
                                <span class="lucide mr-2 text-gray-400" aria-hidden="true">&#xeae5;</span>
                                Avg. Rating: ${user.avg_rating || 'N/A'}
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
                                <p class="text-gray-300">${new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Last Login</p>
                                <p class="text-gray-300">${user.last_login ? new Date(user.last_login).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Never'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <a href="edit-user.php?id=${user.id}" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 text-sm inline-flex items-center">
                            <span class="lucide mr-1" aria-hidden="true">&#xea71;</span>
                            Edit User
                        </a>
                    </div>
                </div>
            `;
            
            document.getElementById('user-details-content').innerHTML = content;
        } else {
            document.getElementById('user-details-content').innerHTML = `
                <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                    <p>${data.message || 'Error fetching user data'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('user-details-content').innerHTML = `
            <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                <p>Failed to fetch user data. Please try again.</p>
            </div>
        `;
    });
}

// Reset password modal
function openResetPasswordModal(userId, userName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset-user-name').textContent = userName;
    openModal('reset-password-modal');
    
    // Reset form fields
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_password').value = '';
    document.getElementById('notify_user').checked = true;
}

// Toggle password visibility
function togglePasswordVisibility(inputId, buttonId) {
    const passwordInput = document.getElementById(inputId);
    const toggleButton = document.getElementById(buttonId);
    
    if (passwordInput && toggleButton) {
        const icon = toggleButton.querySelector('.toggle-password-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            if (icon) icon.innerHTML = '&#xea76;';
        } else {
            passwordInput.type = 'password';
            if (icon) icon.innerHTML = '&#xea30;';
        }
    }
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Click outside modal to close
    const modalOverlays = document.querySelectorAll('[id$="-modal-overlay"]');
    
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                const modalId = this.id.replace('-overlay', '');
                closeModal(modalId);
            }
        });
    });
    
    // Add password toggle listeners
    document.getElementById('toggle-new-password').addEventListener('click', function() {
        togglePasswordVisibility('new_password', 'toggle-new-password');
    });
    
    document.getElementById('toggle-confirm-password').addEventListener('click', function() {
        togglePasswordVisibility('confirm_password', 'toggle-confirm-password');
    });
    
    // Reset password form validation
    const resetForm = document.getElementById('reset-password-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 8) {
                e.preventDefault();
                showConfirmation('Password must be at least 8 characters.', true);
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showConfirmation('Passwords do not match.', true);
                return;
            }
            
            // Show loading indicator
            showLoadingIndicator();
        });
    }
});
</script>

<?php
// Include admin footer
require_once 'includes/admin-footer.php';
?>