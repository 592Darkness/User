<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Check if already logged in
if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id'])) {
    header('Location: admin-dashboard.php');
    exit;
}

// Get flash message if any
$flashMessage = getFlashMessage();

// Page title
$pageTitle = "Admin Login - Salaam Rides";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Dashboard - Salaam Rides">
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
            },
          }
        }
      }
    </script>
</head>
<body class="bg-gray-900 text-gray-200 font-sans antialiased dark min-h-screen flex flex-col">
    <div class="fixed inset-0 z-0 opacity-10 pointer-events-none overflow-hidden">
        <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
    </div>

    <div class="flex-grow flex items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
        <div class="max-w-md w-full space-y-8 bg-gray-800 p-8 rounded-lg shadow-lg border border-gray-700">
            <div>
                <h1 class="text-center text-3xl font-bold text-primary-400">
                    Salaam Rides
                </h1>
                <h2 class="mt-2 text-center text-xl text-gray-300">
                    Admin Dashboard
                </h2>
                <p class="mt-2 text-center text-sm text-gray-400">
                    Please log in to access the admin panel
                </p>
            </div>
            
            <?php if ($flashMessage): ?>
            <div class="bg-<?php echo $flashMessage['type'] == 'error' ? 'red' : 'green'; ?>-500/20 text-<?php echo $flashMessage['type'] == 'error' ? 'red' : 'green'; ?>-400 p-3 rounded-lg text-sm">
                <?php echo $flashMessage['message']; ?>
            </div>
            <?php endif; ?>
            
            <form class="mt-8 space-y-6" action="process-admin-login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div class="mb-4">
                        <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                        <input id="username" name="username" type="text" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                        <input id="password" name="password" type="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                        Sign in
                    </button>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="text-sm text-primary-400 hover:text-primary-300 transition-colors">
                        ← Return to main website
                    </a>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-gray-800 border-t border-gray-700/50 py-4">
        <div class="container mx-auto px-4 text-center text-gray-400 text-sm">
            <p>&copy; <?php echo date('Y'); ?> Salaam Rides. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                if (!username || !password) {
                    e.preventDefault();
                    alert('Please enter both username and password');
                }
            });
        });
    </script>
</body>
</html>
