<?php
require_once 'config.php';
require_once 'functions.php';

$currentUser = getCurrentUser();
$isLoggedIn = isLoggedIn();
$currentPage = getCurrentPage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Salaam Rides - <?php echo ($currentPage == 'index') ? 'Book safe and reliable taxis in Guyana. Available 24/7 with multiple vehicle options.' : 'Account Dashboard. Manage your profile, rides, saved places, and payment methods.'; ?>">
    <title><?php echo ($currentPage == 'index') ? 'Salaam Rides - Reliable Taxi Service in Guyana' : 'Account Dashboard - Salaam Rides'; ?></title>
    
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
            animation: {
              'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
              'fade-in': 'fadeIn 0.8s ease-out forwards',
              'slide-up': 'slideUp 0.5s ease-out forwards',
              'slide-in': 'slideIn 0.4s ease-out forwards',
            },
            keyframes: {
              fadeIn: {
                '0%': { opacity: '0' },
                '100%': { opacity: '1' },
              },
              slideUp: {
                '0%': { transform: 'translateY(20px)', opacity: '0' },
                '100%': { transform: 'translateY(0)', opacity: '1' },
              },
              slideIn: {
                '0%': { transform: 'translateX(-20px)', opacity: '0' },
                '100%': { transform: 'translateX(0)', opacity: '1' },
              },
            },
          }
        }
      }
    </script>

    <?php if ($currentPage == 'index'): ?>
    <script src="<?php echo asset('js/maps-init.js'); ?>"></script>
    <script defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA-6uXAa6MkIMwlYYwMIVBq5s3T0aTh0EI&libraries=places&callback=initMap">
    </script>
    <?php endif; ?>

    <style>
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    
    /* Account Dropdown Styles */
    #user-dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 0.5rem;
        width: 220px;
        background-color: #1f2937; /* gray-800 */
        border-radius: 0.5rem;
        border: 1px solid #374151; /* gray-700 */
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        z-index: 50;
        overflow: hidden;
        transform-origin: top right;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    #user-dropdown-menu:not(.hidden) {
        animation: dropdownFadeIn 0.2s ease forwards;
    }

    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    #user-dropdown-menu ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    #user-dropdown-menu li {
        border-bottom: 1px solid transparent;
    }

    #user-dropdown-menu li:last-child {
        border-top: 1px solid #374151; /* gray-700 */
        margin-top: 0.5rem;
        padding-top: 0.5rem;
    }

    #user-dropdown-menu a {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        color: #d1d5db; /* gray-300 */
        transition: all 0.2s ease;
        font-size: 0.875rem;
    }

    #user-dropdown-menu a:hover {
        background-color: #374151; /* gray-700 */
        color: #10b981; /* primary-400 */
    }

    /* For mobile dropdown */
    #mobile-account-menu:not(.hidden) {
        max-height: 300px;
    }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 font-sans antialiased dark<?php echo ($currentPage == 'account-dashboard') ? ' min-h-screen flex flex-col' : ''; ?>">
    <div class="fixed inset-0 z-0 opacity-10 pointer-events-none overflow-hidden">
        <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
    </div>

    <header class="bg-gray-900/95 backdrop-blur-md fixed top-0 left-0 right-0 z-40 border-b border-gray-700/50 shadow-md">
        <div class="container mx-auto px-4 lg:px-6 flex items-center h-16">
            <!-- Logo (Left Section) -->
            <div class="flex-shrink-0 mr-4">
                <a href="index.php" class="text-2xl font-bold text-primary-400 hover:text-primary-300 transition-colors flex items-center" aria-label="Salaam Rides Guyana Home">
                    Salaam Rides <span class="text-xs font-normal text-gray-400 ml-1">Guyana</span>
                </a>
            </div>
            
            <!-- Account Section (Middle) -->
            <div class="flex-grow flex justify-center items-center">
                <?php if (!$isLoggedIn): ?>
                <button id="login-signup-btn" class="bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-5 rounded-lg transition duration-300 shadow-md hover:shadow-lg transform hover:scale-105">
                    Login / Sign Up
                </button>
                <?php else: ?>
                <div class="flex items-center space-x-4">
                    <a href="account-dashboard.php" class="text-gray-300 hover:text-primary-400 transition duration-300">My Account</a>
                    
                    <div class="relative">
                        <button id="user-dropdown-btn" class="flex items-center space-x-2 bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md hover:shadow-lg">
                            <span class="lucide" aria-hidden="true">&#xebe4;</span>
                            <span class="user-display-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                            <span class="lucide text-xs" aria-hidden="true">&#xeaa0;</span>
                        </button>
                        <div id="user-dropdown-menu" class="hidden">
                            <ul class="py-2">
                                <li class="border-b border-gray-700">
                                    <span class="block px-4 py-2 text-sm text-gray-400">Signed in as</span>
                                    <span class="block px-4 py-2 text-sm font-medium text-white user-display-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                                </li>
                                <li>
                                    <a href="account-dashboard.php" class="block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-primary-400 transition duration-300">
                                        <span class="lucide mr-2" aria-hidden="true">&#xea05;</span>
                                        My Account
                                    </a>
                                </li>
                                <li>
                                    <a href="index.php" class="block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-primary-400 transition duration-300">
                                        <span class="lucide mr-2" aria-hidden="true">&#xeb15;</span>
                                        Book a Ride
                                    </a>
                                </li>
                                <li class="border-t border-gray-700 mt-2 pt-2">
                                    <a href="logout.php" id="logout-link" class="block px-4 py-2 text-red-400 hover:bg-gray-700 hover:text-red-300 transition duration-300">
                                        <span class="lucide mr-2" aria-hidden="true">&#xea7b;</span>
                                        Log Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Navigation (Right Section) -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="<?php echo ($currentPage == 'index') ? '#features' : 'index.php#features'; ?>" class="text-gray-300 hover:text-primary-400 transition duration-300">Features</a>
                <a href="<?php echo ($currentPage == 'index') ? '#rewards' : 'index.php#rewards'; ?>" class="text-gray-300 hover:text-primary-400 transition duration-300">Rewards</a>
                <a href="#" id="schedule-ride-nav" class="text-gray-300 hover:text-primary-400 transition duration-300">Schedule Ride</a>
            </div>
            
            <!-- Mobile Menu Button -->
            <div class="md:hidden ml-auto">
                <button id="mobile-menu-btn" class="text-gray-300 hover:text-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-1" aria-label="Toggle mobile menu" aria-expanded="false">
                    <span class="lucide text-3xl">&#xe9b2;</span>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu (Hidden by Default) -->
        <div id="mobile-menu" class="hidden md:hidden bg-gray-800/95 backdrop-blur-md border-b border-gray-700/50 shadow-lg">
            <div class="container mx-auto px-4 py-3 space-y-3">
                <!-- Login/Account Section (Mobile) -->
                <div class="py-2 border-b border-gray-700 text-center">
                    <?php if (!$isLoggedIn): ?>
                    <button id="login-signup-btn-mobile" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-5 rounded-lg transition duration-300 shadow-md hover:shadow-lg transform hover:scale-105 mt-2">
                        Login / Sign Up
                    </button>
                    <?php else: ?>
                    <div class="flex flex-col items-center">
                        <span class="text-sm text-gray-400 mb-2">Signed in as <span class="text-white user-display-name"><?php echo htmlspecialchars($currentUser['name']); ?></span></span>
                        <div class="flex space-x-2">
                            <a href="account-dashboard.php" class="bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg text-sm transition duration-300">
                                My Account
                            </a>
                            <a href="logout.php" id="mobile-logout-link" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg text-sm transition duration-300">
                                Log Out
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Navigation Links (Mobile) -->
                <a href="<?php echo ($currentPage == 'index') ? '#features' : 'index.php#features'; ?>" class="block text-gray-300 hover:text-primary-400 transition duration-300 py-2">Features</a>
                <a href="<?php echo ($currentPage == 'index') ? '#rewards' : 'index.php#rewards'; ?>" class="block text-gray-300 hover:text-primary-400 transition duration-300 py-2">Rewards</a>
                <a href="#" id="schedule-ride-nav-mobile" class="block text-gray-300 hover:text-primary-400 transition duration-300 py-2">Schedule Ride</a>
                
                <?php if ($isLoggedIn): ?>
                <a href="index.php" class="block text-gray-300 hover:text-primary-400 transition duration-300 py-2">
                    <span class="lucide mr-2" aria-hidden="true">&#xeb15;</span>
                    Book a Ride
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="h-16 md:h-16"></div>