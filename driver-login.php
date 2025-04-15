<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// If driver is already logged in, redirect to dashboard
if (isset($_SESSION['driver_id']) && !empty($_SESSION['driver_id'])) {
    redirect('driver-dashboard.php');
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? sanitize($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Attempt to login
        $conn = dbConnect();
        $stmt = $conn->prepare("SELECT id, name, email, password, phone, rating, vehicle, plate, status FROM drivers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $driver = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $driver['password'])) {
                // Store driver data in session
                $_SESSION['driver_id'] = $driver['id'];
                unset($driver['password']); // Remove password before storing in session
                $_SESSION['driver'] = $driver;
                
                // Redirect to dashboard
                redirect('driver-dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Set page title for header
$pageTitle = 'Driver Login - Salaam Rides';
$isDriverPage = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Salaam Rides - Driver Login portal for our trusted driver partners">
    <title><?php echo $pageTitle; ?></title>
    
    <link rel="preload" href="<?php echo asset('css/style.css'); ?>" as="style">
    <link rel="preload" href="https://cdn.tailwindcss.com" as="script">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style">
    
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%2310b981' d='M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z'/%3E%3C/svg%3E">
    
    <script src="https://cdn.tailwindcss.com"></script> 
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <script>
      tailwind.config = {
        darkMode: 'class', 
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'sans-serif'],
            },
            colors: {
              primary: { 
                DEFAULT: '#10b981', 
                '50': '#ecfdf5',
                '100': '#d1fae5',
                '200': '#a7f3d0',
                '300': '#6ee7b7',
                '400': '#34d399',
                '500': '#10b981',
                '600': '#059669',
                '700': '#047857',
                '800': '#065f46',
                '900': '#064e3b',
                '950': '#022c22',
              },
            }
          }
        }
      }
    </script>
</head>
<body class="bg-gray-900 text-gray-200 font-sans antialiased dark min-h-screen flex flex-col">
    <div class="fixed inset-0 z-0 opacity-10 pointer-events-none overflow-hidden">
        <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
    </div>

    <header class="bg-gray-900/95 backdrop-blur-md fixed top-0 left-0 right-0 z-40 border-b border-gray-700/50 shadow-md">
        <div class="container mx-auto px-4 lg:px-6 flex justify-between items-center h-16">
            <a href="driver-login.php" class="text-2xl font-bold text-primary-400 hover:text-primary-300 transition-colors flex items-center" aria-label="Salaam Rides Guyana Home">
                Salaam Rides <span class="text-xs font-normal text-gray-400 ml-1">Driver Portal</span>
            </a>
        </div>
    </header>

    <div class="h-16 md:h-16"></div>

    <main class="flex-grow container mx-auto px-4 py-12 flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="bg-gray-800 shadow-lg rounded-lg border border-gray-700 overflow-hidden">
                <div class="p-6 sm:p-8">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-white">Driver Login</h1>
                        <p class="text-gray-400 mt-2">Welcome back! Please login to continue.</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                    <div class="bg-red-900/50 border border-red-800 text-red-200 px-4 py-3 rounded-md mb-6">
                        <p><?php echo $error; ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                            <input type="email" id="email" name="email" required autocomplete="email" 
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                            <input type="password" id="password" name="password" required autocomplete="current-password" 
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember-me" name="remember-me" type="checkbox" 
                                    class="h-4 w-4 text-primary-500 focus:ring-primary-500 border-gray-600 rounded">
                                <label for="remember-me" class="ml-2 block text-sm text-gray-400">Remember me</label>
                            </div>
                            <div class="text-sm">
                                <a href="#" class="text-primary-400 hover:text-primary-300">Forgot password?</a>
                            </div>
                        </div>
                        <div>
                            <button type="submit" 
                                class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-300">
                                Sign in
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-8 text-center text-sm text-gray-400">
                        Need an account? Please contact Salaam Rides administration to get registered as a driver.
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 border-t border-gray-700/50 py-6 mt-auto">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400">&copy; <?php echo date('Y'); ?> Salaam Rides. All Rights Reserved.</p>
            <p class="text-sm mt-2 text-gray-500">Serving Georgetown, Linden, Berbice, and all across Guyana.</p>
        </div>
    </footer>

    <div id="confirmation-message" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-green-600 text-white text-sm font-medium py-3 px-6 rounded-lg shadow-lg z-50 flex items-center space-x-2 opacity-0 transition-all duration-300">
        <span class="lucide hidden" id="confirmation-icon" aria-hidden="true">&#xe96c;</span>
        <span id="confirmation-text">
            <?php 
            $flashMessage = getFlashMessage();
            if ($flashMessage) {
                echo htmlspecialchars($flashMessage['message']);
                $messageType = $flashMessage['type'] == 'error' ? 'true' : 'false';
                echo '<script>document.addEventListener("DOMContentLoaded", function() { showConfirmation("' . htmlspecialchars($flashMessage['message']) . '", ' . $messageType . '); });</script>';
            }
            ?>
        </span>
        <button id="close-notification" class="ml-2 text-white hover:text-white/80" aria-label="Close notification">
            <span class="lucide text-sm" aria-hidden="true">&#xea76;</span>
        </button>
    </div>

    <script>
    function showConfirmation(message, isError = false) {
        const confirmationMessage = document.getElementById('confirmation-message');
        const confirmationText = document.getElementById('confirmation-text');
        const confirmationIcon = document.getElementById('confirmation-icon');
        
        if (!confirmationMessage || !confirmationText) return;

        confirmationText.textContent = message;
        
        if (confirmationIcon) {
            confirmationIcon.innerHTML = isError ? '&#xea0e;' : '&#xe96c;';
            confirmationIcon.classList.remove('hidden');
        }
        
        confirmationMessage.classList.remove('opacity-0', 'translate-y-6');
        confirmationMessage.classList.add('opacity-100', 'translate-y-0');

        if(isError) {
            confirmationMessage.classList.remove('bg-green-600');
            confirmationMessage.classList.add('bg-red-600');
        } else {
             confirmationMessage.classList.remove('bg-red-600');
             confirmationMessage.classList.add('bg-green-600');
        }

        if (window.confirmationTimeout) {
            clearTimeout(window.confirmationTimeout);
        }
        
        window.confirmationTimeout = setTimeout(() => {
             confirmationMessage.classList.remove('opacity-100', 'translate-y-0');
             confirmationMessage.classList.add('opacity-0', 'translate-y-6');
        }, 5000); 
    }

    // Close notification button
    const closeNotificationBtn = document.getElementById('close-notification');
    if (closeNotificationBtn) {
        closeNotificationBtn.addEventListener('click', () => {
            const confirmationMessage = document.getElementById('confirmation-message');
            if (confirmationMessage) {
                confirmationMessage.classList.remove('opacity-100', 'translate-y-0');
                confirmationMessage.classList.add('opacity-0', 'translate-y-6');
            }
        });
    }
    </script>
</body>
</html>
