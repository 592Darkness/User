<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

$error = '';
$success = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists in the database
        $conn = dbConnect();
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Don't reveal if email exists or not for security reasons
            $success = 'If an account with that email exists, we have sent password reset instructions.';
        } else {
            $user = $result->fetch_assoc();
            
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            
            // Set expiry time (24 hours from now)
            $expires = date('Y-m-d H:i:s', time() + 86400);
            
            // Create reset_tokens table if it doesn't exist
            $createTableQuery = "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `token` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `used` TINYINT(1) DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )";
            $conn->query($createTableQuery);
            
            // Check for existing tokens and invalidate them
            $deleteStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ?");
            $deleteStmt->bind_param("i", $user['id']);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Store the new token
            $tokenStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $tokenStmt->bind_param("iss", $user['id'], $token, $expires);
            $tokenStmt->execute();
            $tokenStmt->close();
            
            // Create reset URL
            $resetUrl = SITE_URL . '/reset-password.php?token=' . $token;
            
            // Send email
            $to = $email;
            $subject = 'Reset Your Salaam Rides Password';
            $message = <<<EMAIL
Hello {$user['name']},

You recently requested to reset your password for Salaam Rides. Click the link below to reset it:

{$resetUrl}

This link is only valid for 24 hours. If you did not request a password reset, please ignore this email.

Best regards,
The Salaam Rides Team
EMAIL;
            
            $headers = 'From: no-reply@salaamrides.com' . "\r\n" .
                'Reply-To: support@salaamrides.com' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
            
            $mailSent = mail($to, $subject, $message, $headers);
            
            if ($mailSent) {
                // Log successful email
                error_log("Password reset email sent to: " . $email);
            } else {
                // Log email failure
                error_log("Failed to send password reset email to: " . $email);
            }
            
            // Always show success message (even if email fails) for security
            $success = 'If an account with that email exists, we have sent password reset instructions.';
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
                <p class="text-gray-400 mt-2">Enter your email address and we'll send you instructions to reset your password.</p>
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
            <?php endif; ?>
            
            <form method="post" action="forgot-password.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                    <input type="email" id="email" name="email" 
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="Enter your email address"
                           required
                           <?php if (!empty($success)): ?>disabled<?php endif; ?>>
                </div>
                
                <div class="pt-2">
                    <?php if (empty($success)): ?>
                        <button type="submit" 
                                class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                            Send Reset Instructions
                        </button>
                    <?php else: ?>
                        <a href="index.php" 
                           class="block text-center w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                            Return to Home
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="text-sm text-primary-400 hover:text-primary-300 hover:underline">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
include_once 'includes/footer.php';
?>