<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

$error = '';
$success = '';
$tokenValid = false;
$token = '';

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    // Validate token
    $conn = dbConnect();
    
    // Check if token exists and hasn't expired
    $stmt = $conn->prepare("
        SELECT t.id, t.user_id, t.expires_at, u.name, u.email 
        FROM password_reset_tokens t
        JOIN users u ON t.user_id = u.id
        WHERE t.token = ? AND t.expires_at > NOW() AND t.used = 0
    ");
    
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $tokenData = $result->fetch_assoc();
        $tokenValid = true;
        $userId = $tokenData['user_id'];
        $userName = $tokenData['name'];
        $userEmail = $tokenData['email'];
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
    
    $stmt->close();
    $conn->close();
} else {
    $error = 'No reset token provided. Please use the link from your email.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $conn = dbConnect();
        $conn->begin_transaction();
        
        try {
            // Hash the new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user's password
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Mark token as used
            $tokenStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $tokenStmt->bind_param("s", $token);
            $tokenStmt->execute();
            $tokenStmt->close();
            
            $conn->commit();
            
            // Set success message
            $success = 'Your password has been reset successfully. You can now log in with your new password.';
            
            // Log password reset
            error_log("Password reset successful for user ID: " . $userId);
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'An error occurred while resetting your password. Please try again.';
            error_log("Password reset error: " . $e->getMessage());
        }
        
        $conn->close();
    }
}

include_once 'includes/header.php';
?>

<section class="flex items-center justify-center min-h-[70vh] px-4 py-16">
    <div class="max-w-md w-full">
        <div class="bg-gray-800 border border-gray-700 p-8 rounded-xl shadow-lg">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-white">Reset Your Password</h1>
                <?php if ($tokenValid && empty($success)): ?>
                    <p class="text-gray-400 mt-2">Create a new password for your account.</p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-600/20 border border-red-500/30 text-red-300 p-3 rounded-lg mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-600/20 border border-green-500/30 text-green-300 p-3 rounded-lg mb-4">
                    <?php echo $success; ?>
                </div>
                <div class="text-center mt-6">
                    <a href="index.php" 
                       class="inline-block bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-8 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                        Go to Login
                    </a>
                </div>
            <?php elseif ($tokenValid): ?>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" 
                                   class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Enter new password"
                                   required
                                   minlength="8">
                            <button type="button" id="toggle-password" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400" 
                                    aria-label="Toggle password visibility">
                                <span class="lucide text-lg toggle-password-icon" aria-hidden="true">&#xea30;</span>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-300 mb-1">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Confirm new password"
                                   required
                                   minlength="8">
                            <button type="button" id="toggle-confirm-password" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400" 
                                    aria-label="Toggle password visibility">
                                <span class="lucide text-lg toggle-password-icon" aria-hidden="true">&#xea30;</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="pt-2">
                        <button type="submit" 
                                class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                            Reset Password
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center mt-4">
                    <a href="forgot-password.php" class="text-primary-400 hover:text-primary-300 hover:underline">
                        Request a new password reset link
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    function setupPasswordToggle(buttonId, inputId) {
        const toggleBtn = document.getElementById(buttonId);
        const passwordInput = document.getElementById(inputId);
        
        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = toggleBtn.querySelector('.toggle-password-icon');
                if (icon) {
                    icon.innerHTML = type === 'password' ? '&#xea30;' : '&#xea76;';
                }
            });
        }
    }
    
    // Setup toggle buttons
    setupPasswordToggle('toggle-password', 'password');
    setupPasswordToggle('toggle-confirm-password', 'confirm_password');
});
</script>

<?php
include_once 'includes/footer.php';
?>