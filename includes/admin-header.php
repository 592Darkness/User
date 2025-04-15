<?php
// Only include files absolutely necessary for the header's display logic (like asset function)
// Session should already be started by config.php before this file is included on a page.
require_once 'includes/config.php'; // Needed for SITE_URL, asset()
require_once 'includes/functions.php'; // Needed for asset()

// Login check (requireAdminLogin()) should be done *before* including this header file in page scripts.

// Set default page title if not already set by the calling script
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard - Salaam Rides';
}

// Get current page for active link detection (safe to keep)
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// --- Start HTML Output ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Dashboard - Salaam Rides">
    <title><?php echo htmlspecialchars($pageTitle); // Sanitize output ?></title>

    <link rel="preload" href="<?php echo asset('css/style.css'); ?>" as="style">
    <link rel="preload" href="https://cdn.tailwindcss.com" as="script">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style">

    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%2310b981' d='M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z'/%3E%3C/svg%3E">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin-fixes.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
      // Tailwind config
      if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                extend: {
                    fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                    primary: {
                        DEFAULT: '#10b981', '50': '#ecfdf5', '100': '#d1fae5', '200': '#a7f3d0', '300': '#6ee7b7', '400': '#34d399', '500': '#10b981', '600': '#059669', '700': '#047857', '800': '#065f46', '900': '#064e3b', '950': '#022c22',
                    },
                    },
                     animation: {
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                        'slide-up': 'slideUp 0.3s ease-out forwards',
                        'slide-down': 'slideDown 0.3s ease-in forwards',
                     },
                     keyframes: {
                         fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                         slideUp: { '0%': { transform: 'translateY(10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                         slideDown: { '0%': { transform: 'translateY(0)', opacity: '1' }, '100%': { transform: 'translateY(10px)', opacity: '0' } },
                     },
                }
                }
            }
      } else {
          console.warn("Tailwind object not found for config.");
      }
    </script>
    <style>
        .lucide { font-family: 'LucideIcons'; }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 font-sans antialiased dark min-h-screen flex flex-col">
    <div class="fixed inset-0 z-0 opacity-10 pointer-events-none overflow-hidden">
        <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
    </div>

    <header class="bg-gray-900/95 backdrop-blur-md sticky top-0 left-0 right-0 z-40 border-b border-gray-700/50 shadow-md">
        <div class="container mx-auto px-4 lg:px-6 flex justify-between items-center h-16">
            <a href="admin-dashboard.php" class="text-2xl font-bold text-primary-400 hover:text-primary-300 transition-colors flex items-center" aria-label="Salaam Rides Admin Dashboard">
                Salaam Rides <span class="text-xs font-normal text-gray-400 ml-1">Admin Panel</span>
            </a>

            <div class="hidden md:flex items-center space-x-6">
                <a href="admin-dashboard.php" class="<?php echo ($currentPage == 'admin-dashboard') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 flex items-center gap-1.5">
                    <span class="lucide text-base" aria-hidden="true">&#xeaae;</span> Dashboard
                </a>
                <a href="admin-users.php" class="<?php echo ($currentPage == 'admin-users') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 flex items-center gap-1.5">
                    <span class="lucide text-base" aria-hidden="true">&#xea05;</span> Users
                </a>
                <a href="admin-drivers.php" class="<?php echo ($currentPage == 'admin-drivers') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 flex items-center gap-1.5">
                    <span class="lucide text-base" aria-hidden="true">&#xebe4;</span> Drivers
                </a>
                <a href="admin-rides.php" class="<?php echo ($currentPage == 'admin-rides') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 flex items-center gap-1.5">
                    <span class="lucide text-base" aria-hidden="true">&#xeb15;</span> Rides
                </a>
                <a href="admin-analytics.php" class="<?php echo ($currentPage == 'admin-analytics') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 flex items-center gap-1.5">
                    <span class="lucide text-base" aria-hidden="true">&#xea22;</span> Analytics
                </a>
                <a href="admin-pricing.php" class="<?php echo ($currentPage == 'admin-pricing') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 flex items-center gap-1.5">
                   <span class="lucide text-base" aria-hidden="true">&#xec8f;</span> Pricing
                </a>

                <div class="relative">
                    <button id="admin-dropdown-btn" class="flex items-center space-x-2 bg-gray-800 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md">
                        <span class="lucide" aria-hidden="true">&#xea05;</span>
                        <span><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                        <span class="lucide text-xs" aria-hidden="true">&#xeaa0;</span>
                    </button>
                    <div id="admin-dropdown-menu" class="absolute right-0 top-full mt-2 w-48 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-50 hidden animate-fade-in">
                        <ul class="py-1">
                            <li class="px-4 pt-2 pb-1 border-b border-gray-700">
                                <span class="block text-xs text-gray-400">Signed in as</span>
                                <span class="block text-sm font-medium text-white truncate"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="mt-1">
                                <a href="admin-logout.php" id="logout-link" class="flex items-center gap-2 px-4 py-2 text-red-400 hover:bg-gray-700 hover:text-red-300 transition duration-300 text-sm">
                                    <span class="lucide text-base" aria-hidden="true">&#xea7b;</span>
                                    Log Out
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-gray-300 hover:text-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-1" aria-label="Toggle mobile menu" aria-expanded="false">
                    <span class="lucide text-3xl">&#xe9b2;</span> </button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-gray-800/95 backdrop-blur-md border-b border-gray-700/50 shadow-lg absolute top-full left-0 right-0">
            <div class="container mx-auto px-4 py-3 space-y-1">
                <a href="admin-dashboard.php" class="block <?php echo ($currentPage == 'admin-dashboard') ? 'text-primary-400 bg-gray-700/50' : 'text-gray-300 hover:text-primary-400 hover:bg-gray-700/50'; ?> transition duration-300 py-2 px-3 rounded-md flex items-center gap-2"><span class="lucide text-base" aria-hidden="true">&#xeaae;</span> Dashboard</a>
                <a href="admin-users.php" class="block <?php echo ($currentPage == 'admin-users') ? 'text-primary-400 bg-gray-700/50' : 'text-gray-300 hover:text-primary-400 hover:bg-gray-700/50'; ?> transition duration-300 py-2 px-3 rounded-md flex items-center gap-2"><span class="lucide text-base" aria-hidden="true">&#xea05;</span> Users</a>
                <a href="admin-drivers.php" class="block <?php echo ($currentPage == 'admin-drivers') ? 'text-primary-400 bg-gray-700/50' : 'text-gray-300 hover:text-primary-400 hover:bg-gray-700/50'; ?> transition duration-300 py-2 px-3 rounded-md flex items-center gap-2"><span class="lucide text-base" aria-hidden="true">&#xebe4;</span> Drivers</a>
                <a href="admin-rides.php" class="block <?php echo ($currentPage == 'admin-rides') ? 'text-primary-400 bg-gray-700/50' : 'text-gray-300 hover:text-primary-400 hover:bg-gray-700/50'; ?> transition duration-300 py-2 px-3 rounded-md flex items-center gap-2"><span class="lucide text-base" aria-hidden="true">&#xeb15;</span> Rides</a>
                <a href="admin-analytics.php" class="block <?php echo ($currentPage == 'admin-analytics') ? 'text-primary-400 bg-gray-700/50' : 'text-gray-300 hover:text-primary-400 hover:bg-gray-700/50'; ?> transition duration-300 py-2 px-3 rounded-md flex items-center gap-2"><span class="lucide text-base" aria-hidden="true">&#xea22;</span> Analytics</a>
                <a href="admin-pricing.php" class="block <?php echo ($currentPage == 'admin-pricing') ? 'text-primary-400 bg-gray-700/50' : 'text-gray-300 hover:text-primary-400 hover:bg-gray-700/50'; ?> transition duration-300 py-2 px-3 rounded-md flex items-center gap-2"><span class="lucide text-base" aria-hidden="true">&#xec8f;</span> Pricing</a>

                <div class="border-t border-gray-700 mt-2 pt-2">
                    <span class="block text-sm text-gray-400 py-1 px-3">Signed in as <span class="text-white"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span></span>
                    <a href="admin-logout.php" id="mobile-logout-link" class="block text-red-400 hover:text-red-300 hover:bg-gray-700/50 transition duration-300 py-2 px-3 rounded-md flex items-center gap-2">
                        <span class="lucide text-base" aria-hidden="true">&#xea7b;</span> Log Out
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="h-16"></div>

    <main class="flex-grow container mx-auto px-4 md:px-6 py-8">