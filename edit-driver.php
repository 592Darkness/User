<?php
// Enhanced edit-driver.php with debugging

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Debug function
function debug_to_log($message, $data = null) {
    $log_file = __DIR__ . '/edit_driver_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if ($data !== null) {
        $log_message .= " - Data: " . print_r($data, true);
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Start debugging
debug_to_log("Edit driver page loaded");

// Ensure admin is logged in
requireAdminLogin();
debug_to_log("Admin login check passed");

// Set page title
$pageTitle = "Edit Driver - Admin Dashboard";

// Check for driver ID
$driverId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$driver = null;
debug_to_log("Driver ID from GET", $driverId);

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
            header('Location: admin-drivers.php');
            exit;
        }
        
        switch ($action) {
            case 'update':
                debug_to_log("Processing update action");
                
                // Get driver ID from form
                $driverId = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
                debug_to_log("Driver ID from form", $driverId);
                
                // Check required fields
                $requiredFields = ['name', 'email', 'phone', 'vehicle', 'plate', 'vehicle_type'];
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
                    header("Location: edit-driver.php?id=$driverId");
                    exit;
                }
                
                // Check email uniqueness
                if (driverEmailExists($_POST['email'], $driverId)) {
                    debug_to_log("Email already exists", $_POST['email']);
                    setFlashMessage('error', 'Email already in use by another driver.');
                    header("Location: edit-driver.php?id=$driverId");
                    exit;
                }
                
                // Prepare driver data
                $driverData = [
                    'name' => sanitize($_POST['name']),
                    'email' => sanitize($_POST['email']),
                    'phone' => sanitize($_POST['phone']),
                    'vehicle' => sanitize($_POST['vehicle']),
                    'plate' => sanitize($_POST['plate']),
                    'vehicle_type' => sanitize($_POST['vehicle_type']),
                    'status' => sanitize($_POST['status'])
                ];
                
                debug_to_log("Driver data prepared", $driverData);
                
                // Add password only if provided
                if (isset($_POST['password']) && !empty($_POST['password'])) {
                    debug_to_log("Password provided, adding to data");
                    $driverData['password'] = $_POST['password'];
                } else {
                    debug_to_log("No password provided, skipping");
                }
                
                // Try database update directly if updateDriver() fails
                $result = updateDriver($driverId, $driverData);
                debug_to_log("Update driver result", $result);
                
                if (!$result) {
                    debug_to_log("Update failed with updateDriver, trying direct update");
                    
                    // Direct database update as fallback
                    try {
                        $conn = dbConnect();
                        debug_to_log("Database connected");
                        
                        // Prepare query parts
                        $setParts = [];
                        $params = [];
                        $types = "";
                        
                        foreach ($driverData as $field => $value) {
                            if ($field === 'password' && !empty($value)) {
                                $hashedPassword = password_hash($value, PASSWORD_DEFAULT);
                                $setParts[] = "$field = ?";
                                $params[] = $hashedPassword;
                                $types .= "s";
                            } else {
                                $setParts[] = "$field = ?";
                                $params[] = $value;
                                $types .= "s";
                            }
                        }
                        
                        // Add driver ID
                        $params[] = $driverId;
                        $types .= "i";
                        
                        $query = "UPDATE drivers SET " . implode(", ", $setParts) . " WHERE id = ?";
                        debug_to_log("Direct update query", $query);
                        
                        // Execute update
                        $stmt = $conn->prepare($query);
                        
                        if (!$stmt) {
                            debug_to_log("Prepare failed", $conn->error);
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        // Use dynamic binding
                        $bindParams = array($types);
                        foreach ($params as $key => $value) {
                            $bindParams[] = &$params[$key];
                        }
                        
                        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
                        
                        if (!$stmt->execute()) {
                            debug_to_log("Execute failed", $stmt->error);
                            throw new Exception("Execute failed: " . $stmt->error);
                        }
                        
                        debug_to_log("Direct update successful, affected rows: " . $stmt->affected_rows);
                        
                        $stmt->close();
                        $conn->close();
                        
                        $result = true;
                    } catch (Exception $e) {
                        debug_to_log("Exception in direct update", $e->getMessage());
                        $result = false;
                    }
                }
                
                if ($result) {
                    debug_to_log("Update successful, redirecting to driver list");
                    setFlashMessage('success', 'Driver updated successfully.');
                    header('Location: admin-drivers.php');
                    exit;
                } else {
                    debug_to_log("Update failed, returning to edit form");
                    setFlashMessage('error', 'Failed to update driver. Please try again.');
                    header("Location: edit-driver.php?id=$driverId");
                    exit;
                }
                break;
                
            case 'add':
                debug_to_log("Processing add action");
                
                // Process add driver functionality (similar to update)
                $requiredFields = ['name', 'email', 'phone', 'password', 'vehicle', 'plate', 'vehicle_type'];
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
                    header('Location: edit-driver.php');
                    exit;
                }
                
                // Check email uniqueness
                if (driverEmailExists($_POST['email'])) {
                    debug_to_log("Email already exists", $_POST['email']);
                    setFlashMessage('error', 'Email already in use by another driver.');
                    header('Location: edit-driver.php');
                    exit;
                }
                
                // Prepare driver data
                $driverData = [
                    'name' => sanitize($_POST['name']),
                    'email' => sanitize($_POST['email']),
                    'phone' => sanitize($_POST['phone']),
                    'password' => $_POST['password'],
                    'vehicle' => sanitize($_POST['vehicle']),
                    'plate' => sanitize($_POST['plate']),
                    'vehicle_type' => sanitize($_POST['vehicle_type']),
                    'status' => sanitize($_POST['status'])
                ];
                
                debug_to_log("Add driver data prepared", $driverData);
                
                // Add the driver
                $newDriverId = addDriver($driverData);
                debug_to_log("Add driver result", $newDriverId);
                
                if ($newDriverId) {
                    debug_to_log("Add successful, redirecting to driver list");
                    setFlashMessage('success', 'Driver added successfully.');
                    header('Location: admin-drivers.php');
                    exit;
                } else {
                    debug_to_log("Add failed, returning to add form");
                    setFlashMessage('error', 'Failed to add driver. Please try again.');
                    header('Location: edit-driver.php');
                    exit;
                }
                break;
        }
    } else {
        debug_to_log("POST request without action");
    }
}

// If we're editing, get the driver data
if ($driverId > 0) {
    debug_to_log("Getting driver details for ID", $driverId);
    $driver = getDriverDetails($driverId);
    
    if (!$driver) {
        debug_to_log("Driver not found", $driverId);
        setFlashMessage('error', 'Driver not found.');
        header('Location: admin-drivers.php');
        exit;
    } else {
        debug_to_log("Driver found", $driver);
    }
}

// Include admin header
require_once 'includes/admin-header.php';
debug_to_log("Admin header included");
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white"><?php echo $driverId > 0 ? 'Edit Driver' : 'Add New Driver'; ?></h1>
        
        <a href="admin-drivers.php" class="bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center">
            <span class="lucide mr-1" aria-hidden="true">&#xeaa2;</span>
            Back to Drivers
        </a>
    </div>

    <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
        <form method="post" action="edit-driver.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $driverId > 0 ? 'update' : 'add'; ?>">
            <?php if ($driverId > 0): ?>
                <input type="hidden" name="driver_id" value="<?php echo $driverId; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-300 mb-1">Full Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $driver ? htmlspecialchars($driver['name']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo $driver ? htmlspecialchars($driver['email']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required 
                           value="<?php echo $driver ? htmlspecialchars($driver['phone']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-300 mb-1">
                        Password <?php echo $driverId > 0 ? '' : '*'; ?>
                    </label>
                    <input type="password" id="password" name="password" 
                           <?php echo $driverId > 0 ? '' : 'required'; ?>
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <p class="text-xs text-gray-500 mt-1">
                        <?php echo $driverId > 0 ? 'Leave blank to keep current password.' : 'Minimum 8 characters.'; ?>
                    </p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="vehicle" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Model *</label>
                    <input type="text" id="vehicle" name="vehicle" required 
                           value="<?php echo $driver ? htmlspecialchars($driver['vehicle']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label for="plate" class="block text-sm font-medium text-gray-300 mb-1">License Plate *</label>
                    <input type="text" id="plate" name="plate" required 
                           value="<?php echo $driver ? htmlspecialchars($driver['plate']) : ''; ?>"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label for="vehicle_type" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Type *</label>
                    <select id="vehicle_type" name="vehicle_type" required
                            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="standard" <?php echo ($driver && $driver['vehicle_type'] === 'standard') ? 'selected' : ''; ?>>Standard</option>
                        <option value="suv" <?php echo ($driver && $driver['vehicle_type'] === 'suv') ? 'selected' : ''; ?>>SUV</option>
                        <option value="premium" <?php echo ($driver && $driver['vehicle_type'] === 'premium') ? 'selected' : ''; ?>>Premium</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-1">Status</label>
                <select id="status" name="status" 
                        class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <option value="available" <?php echo ($driver && $driver['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="offline" <?php echo ($driver && $driver['status'] === 'offline') ? 'selected' : ''; ?>>Offline</option>
                    <option value="busy" <?php echo ($driver && $driver['status'] === 'busy') ? 'selected' : ''; ?>>Busy</option>
                </select>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                    <?php echo $driverId > 0 ? 'Update Driver' : 'Add Driver'; ?>
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