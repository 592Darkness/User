<?php
// edit-user.php - Handles adding and editing users from admin panel

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Debug function
function debug_to_log($message, $data = null) {
    $log_file = __DIR__ . '/edit_user_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= " - Data: " . print_r($data, true);
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Start debugging
debug_to_log("Edit user page loaded");

// Ensure admin is logged in
requireAdminLogin();
debug_to_log("Admin login check passed");

// Set page title
$pageTitle = "Edit User - Admin Dashboard";

// Check for user ID
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = null;
debug_to_log("User ID from GET", $userId);

// Process form submission first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_to_log("Form submitted", $_POST);
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        debug_to_log("Action", $action);
        
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            debug_to_log("CSRF validation failed");
            setFlashMessage('error', 'Security validation failed. Please try again.');
            header('Location: admin-users.php');
            exit;
        }
        
        switch ($action) {
            case 'update':
                debug_to_log("Processing update action");
                
                // Get user ID from form
                $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
                debug_to_log("User ID from form", $userId);
                
                // Check required fields
                $requiredFields = ['name', 'email', 'phone'];
                $missingFields = false;
                
                foreach ($requiredFields as $field) {
                    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                        debug_to_log("Missing required field", $field);
                        $missingFields = true;
                        break;
                    }
                }
                
                if ($missingFields) {
                    debug_to_log("Missing required fields");
                    setFlashMessage('error', 'All required fields must be filled.');
                    header("Location: edit-user.php?id=$userId");
                    exit;
                }
                
                // Check email uniqueness
                if (userEmailExists($_POST['email'], $userId)) {
                    debug_to_log("Email already exists", $_POST['email']);
                    setFlashMessage('error', 'Email already in use by another user.');
                    header("Location: edit-user.php?id=$userId");
                    exit;
                }
                
                // Prepare user data
                $userData = [
                    'name' => sanitize($_POST['name']),
                    'email' => sanitize($_POST['email']),
                    'phone' => sanitize($_POST['phone']),
                    'status' => sanitize($_POST['status'])
                ];
                
                debug_to_log("User data prepared", $userData);
                
                // Add password only if provided
                if (isset($_POST['password']) && !empty($_POST['password'])) {
                    debug_to_log("Password provided, adding to data");
                    $userData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                // Update the user
                try {
                    $conn = dbConnect();
                    debug_to_log("Database connected");
                    
                    // Prepare query parts
                    $setParts = [];
                    $params = [];
                    
                    foreach ($userData as $field => $value) {
                        $setParts[] = "$field = ?";
                        $params[] = $value;
                    }
                    
                    // Add user ID
                    $params[] = $userId;
                    
                    $query = "UPDATE users SET " . implode(", ", $setParts) . " WHERE id = ?";
                    debug_to_log("Update query", $query);
                    
                    // Execute update
                    $stmt = $conn->prepare($query);
                    
                    if (!$stmt) {
                        debug_to_log("Prepare failed", $conn->error);
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    
                    // Use dynamic binding
                    $types = str_repeat('s', count($params) - 1) . 'i'; // All string fields except the last one (ID) which is integer
                    $stmt->bind_param($types, ...$params);
                    
                    if (!$stmt->execute()) {
                        debug_to_log("Execute failed", $stmt->error);
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    
                    debug_to_log("Update successful, affected rows: " . $stmt->affected_rows);
                    
                    $stmt->close();
                    $conn->close();
                    
                    setFlashMessage('success', 'User updated successfully.');
                    header('Location: admin-users.php');
                    exit;
                } catch (Exception $e) {
                    debug_to_log("Exception in update", $e->getMessage());
                    setFlashMessage('error', 'Failed to update user. Please try again.');
                    header("Location: edit-user.php?id=$userId");
                    exit;
                }
                break;
                
            case 'add':
                debug_to_log("Processing add action");
                
                // Check required fields
                $requiredFields = ['name', 'email', 'phone', 'password'];
                $missingFields = false;
                
                foreach ($requiredFields as $field) {
                    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                        debug_to_log("Missing required field for add", $field);
                        $missingFields = true;
                        break;
                    }
                }
                
                if ($missingFields) {
                    debug_to_log("Missing required fields for add");
                    setFlashMessage('error', 'All required fields must be filled.');
                    header('Location: edit-user.php');
                    exit;
                }
                
                // Check email uniqueness
                if (userEmailExists($_POST['email'])) {
                    debug_to_log("Email already exists", $_POST['email']);
                    setFlashMessage('error', 'Email already in use by another user.');
                    header('Location: edit-user.php');
                    exit;
                }
                
                // Prepare user data
                $userData = [
                    'name' => sanitize($_POST['name']),
                    'email' => sanitize($_POST['email']),
                    'phone' => sanitize($_POST['phone']),
                    'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'status' => sanitize($_POST['status']),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                debug_to_log("Add user data prepared", $userData);
                
                // Add the user
                try {
                    $conn = dbConnect();
                    debug_to_log("Database connected for add");
                    
                    // Prepare fields and values
                    $fields = array_keys($userData);
                    $placeholders = array_fill(0, count($fields), '?');
                    
                    $query = "INSERT INTO users (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
                    debug_to_log("Insert query", $query);
                    
                    // Execute insert
                    $stmt = $conn->prepare($query);
                    
                    if (!$stmt) {
                        debug_to_log("Prepare failed for insert", $conn->error);
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    
                    // Use dynamic binding
                    $types = str_repeat('s', count($userData)); // All fields are strings
                    $stmt->bind_param($types, ...array_values($userData));
                    
                    if (!$stmt->execute()) {
                        debug_to_log("Execute failed for insert", $stmt->error);
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    
                    $newUserId = $stmt->insert_id;
                    debug_to_log("Add successful, new user ID: " . $newUserId);
                    
                    $stmt->close();
                    $conn->close();
                    
                    setFlashMessage('success', 'User added successfully.');
                    header('Location: admin-users.php');
                    exit;
                } catch (Exception $e) {
                    debug_to_log("Exception in add", $e->getMessage());
                    setFlashMessage('error', 'Failed to add user. Please try again.');
                    header('Location: edit-user.php');
                    exit;
                }
                break;
        }
    } else {
        debug_to_log("POST request without action");
    }
}

// Function to check if email exists (avoiding duplication)
function userEmailExists($email, $excludeId = null) {
    try {
        $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = dbFetchOne($query, $params);
        return $result && $result['count'] > 0;
    } catch (Exception $e) {
        debug_to_log("Error checking if user email exists: " . $e->getMessage());
        return false;
    }
}

// If we're editing, get the user data
if ($userId > 0) {
    debug_to_log("Getting user details for ID", $userId);
    $user = getUserDetails($userId);
    
    if (!$user) {
        debug_to_log("User not found", $userId);
        setFlashMessage('error', 'User not found.');
        header('Location: admin-users.php');
        exit;
    } else {
        debug_to_log("User found", $user);
    }
}

// Include admin header
require_once 'includes/admin-header.php';
debug_to_log("Admin header included");
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white"><?php echo $userId > 0 ? 'Edit User' : 'Add New User'; ?></h1>
        
        <a href="admin-users.php" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center">
            <span class="lucide mr-1" aria-hidden="true">&#xeaa2;</span>
            Back to Users
        </a>
    </div>

    <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
        <form method="post" action="edit-user.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $userId > 0 ? 'update' : 'add'; ?>">
            <?php if ($userId > 0): ?>
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Full Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $user ? htmlspecialchars($user['name']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo $user ? htmlspecialchars($user['email']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required 
                           value="<?php echo $user ? htmlspecialchars($user['phone']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1">
                        Password <?php echo $userId > 0 ? '' : '*'; ?>
                    </label>
                    <input type="password" id="password" name="password" 
                           <?php echo $userId > 0 ? '' : 'required'; ?>
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <p class="text-xs text-gray-500 mt-1">
                        <?php echo $userId > 0 ? 'Leave blank to keep current password.' : 'Minimum 8 characters.'; ?>
                    </p>
                </div>
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                <select id="status" name="status" 
                        class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="active" <?php echo ($user && $user['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($user && $user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo ($user && $user['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                    <?php echo $userId > 0 ? 'Update User' : 'Add User'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
debug_to_log("Rendering form complete");
// Include admin footer
require_once 'includes/admin-footer.php';
debug_to_log("Admin footer included");
?>