<?php
// Set default page title if not already set
if (!isset($pageTitle)) {
    $pageTitle = 'Driver Dashboard - Salaam Rides';
}

// Get current page for active link detection
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Salaam Rides - Driver Dashboard. Manage your rides, earnings and profile.">
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
    <!-- Add this RIGHT BEFORE the earlier bootstrap fix script in driver-header.php -->
<script>
// Create safer versions of key functions with proper error handling
// This must be defined BEFORE the driver-dashboard.js script loads
(function() {
    // Save any original methods that might already exist
    var originalMethods = {
        updateEarningsSummary: window.updateEarningsSummary,
        updateEarningsBreakdown: window.updateEarningsBreakdown,
        updatePaymentsTable: window.updatePaymentsTable,
        loadEarningsData: window.loadEarningsData,
        checkForPaymentConfirmations: window.checkForPaymentConfirmations
    };

    // Helper to safely update DOM elements
    window.safeUpdateElement = function(elementId, value) {
        try {
            var element = document.getElementById(elementId);
            if (element) {
                element.textContent = value;
                return true;
            }
            return false;
        } catch (e) {
            console.warn("Error updating element " + elementId, e);
            return false;
        }
    };

    // Override updateEarningsSummary with a safer version
    window.updateEarningsSummary = function(summary) {
        if (!summary) {
            console.warn("updateEarningsSummary called with invalid data");
            return;
        }
        
        try {
            // Use our safe update method instead of direct assignment
            safeUpdateElement('total-earnings', summary.formatted_earnings || 'G$0');
            safeUpdateElement('total-rides', summary.total_rides || '0');
            safeUpdateElement('avg-fare', summary.formatted_avg_fare || 'G$0');
            safeUpdateElement('total-hours', summary.total_hours || '0');
            safeUpdateElement('avg-hourly', summary.formatted_hourly_rate || 'G$0');
            
            // Call original method if it exists
            if (typeof originalMethods.updateEarningsSummary === 'function') {
                try {
                    originalMethods.updateEarningsSummary(summary);
                } catch (e) {
                    console.warn("Error in original updateEarningsSummary", e);
                }
            }
        } catch (e) {
            console.error("Error in safe updateEarningsSummary implementation", e);
        }
    };
    
    // Override loadEarningsData with a safer version
    window.loadEarningsData = function() {
        try {
            var period = document.getElementById('earnings-period')?.value || 'week';
            var periodText = document.getElementById('earnings-period-text');
            
            if (periodText) {
                switch (period) {
                    case 'week': 
                        periodText.textContent = 'This week';
                        break;
                    case 'month':
                        periodText.textContent = 'This month';
                        break;
                    case 'year':
                        periodText.textContent = 'This year';
                        break;
                    case 'all':
                        periodText.textContent = 'All time';
                        break;
                }
            }
            
            // Set default empty data in case API call fails
            var defaultData = {
                summary: {
                    total_rides: 0,
                    formatted_earnings: 'G$0',
                    formatted_avg_fare: 'G$0',
                    total_hours: 0,
                    formatted_hourly_rate: 'G$0'
                },
                breakdown: [],
                payments: []
            };
            
            // Update UI with defaults to prevent errors
            updateEarningsSummary(defaultData.summary);
            
            // API call here - the original function will continue with this part
            if (typeof originalMethods.loadEarningsData === 'function') {
                try {
                    originalMethods.loadEarningsData();
                } catch (e) {
                    console.warn("Error in original loadEarningsData", e);
                }
            }
        } catch (e) {
            console.error("Error in safe loadEarningsData implementation", e);
        }
    };

    // Disable or create a safe version of checkForPaymentConfirmations
    window.checkForPaymentConfirmations = function() {
        try {
            // Optionally call the original if it exists
            if (typeof originalMethods.checkForPaymentConfirmations === 'function') {
                try {
                    originalMethods.checkForPaymentConfirmations();
                } catch (e) {
                    console.warn("Error in original checkForPaymentConfirmations", e);
                }
            }
        } catch (e) {
            console.error("Error in safe checkForPaymentConfirmations", e);
        }
        
        // Always return a resolved promise to prevent errors
        return Promise.resolve({success: true, rides: []});
    };
})();
</script>
<script>
// Bootstrap Modal Polyfill - must be defined BEFORE driver-dashboard.js loads
window.bootstrap = {
    Modal: function(element) {
        this.element = element;
        this.show = function() {
            if (this.element) {
                this.element.style.display = 'flex';
                this.element.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Create backdrop if it doesn't exist
                let backdrop = document.querySelector('.modal-backdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }
        };
        this.hide = function() {
            if (this.element) {
                this.element.style.display = 'none';
                this.element.classList.remove('show');
                document.body.classList.remove('modal-open');
                
                // Remove backdrop
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        };
    }
};

// Safe DOM element updater - ensures elements exist before updating
window.safeUpdateElement = function(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
};

// Patch key functions to prevent errors
document.addEventListener('DOMContentLoaded', function() {
    // Create missing elements if needed to prevent errors
    const elementsToCheck = [
        'today-rides', 'today-earnings', 'today-hours', 
        'weekly-rides', 'weekly-earnings', 'weekly-hours', 'weekly-rating',
        'total-earnings', 'total-rides', 'avg-fare', 'avg-hourly'
    ];
    
    elementsToCheck.forEach(function(id) {
        if (!document.getElementById(id)) {
            console.log('Creating missing element with ID: ' + id);
            const div = document.createElement('div');
            div.id = id;
            div.style.display = 'none';
            document.body.appendChild(div);
        }
    });
});
</script>
</head>
<body class="bg-gray-900 text-gray-200 font-sans antialiased dark min-h-screen flex flex-col" data-driver-id="<?php echo isset($_SESSION['driver_id']) ? $_SESSION['driver_id'] : ''; ?>">
    <div class="fixed inset-0 z-0 opacity-10 pointer-events-none overflow-hidden">
        <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
    </div>

    <header class="bg-gray-900/95 backdrop-blur-md fixed top-0 left-0 right-0 z-40 border-b border-gray-700/50 shadow-md">
        <div class="container mx-auto px-4 lg:px-6 flex justify-between items-center h-16">
            <a href="driver-dashboard.php" class="text-2xl font-bold text-primary-400 hover:text-primary-300 transition-colors flex items-center" aria-label="Salaam Rides Driver Portal">
                Salaam Rides <span class="text-xs font-normal text-gray-400 ml-1">Driver Portal</span>
            </a>
            
            <div class="hidden md:flex items-center space-x-6">
                <a href="driver-dashboard.php" class="<?php echo ($currentPage == 'driver-dashboard') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300">Dashboard</a>
                <a href="driver-dashboard.php?tab=rides" class="text-gray-300 hover:text-primary-400 transition duration-300">Available Rides</a>
                <a href="driver-dashboard.php?tab=earnings" class="text-gray-300 hover:text-primary-400 transition duration-300">Earnings</a>
                
                <div class="relative group">
                    <button id="driver-dropdown-btn" class="flex items-center space-x-2 bg-gray-800 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md">
                        <span class="lucide" aria-hidden="true">&#xebe4;</span>
                        <span class="driver-display-name"><?php echo htmlspecialchars($_SESSION['driver']['name']); ?></span>
                        <span class="lucide text-xs" aria-hidden="true">&#xeaa0;</span>
                    </button>
                    <div id="driver-dropdown-menu" class="absolute right-0 top-full mt-2 w-48 bg-gray-800 border border-gray-700 rounded-lg shadow-lg z-50 hidden group-hover:block">
                        <ul class="py-2">
                            <li class="border-b border-gray-700">
                                <span class="block px-4 py-2 text-sm text-gray-400">Signed in as</span>
                                <span class="block px-4 py-2 text-sm font-medium text-white driver-display-name"><?php echo htmlspecialchars($_SESSION['driver']['name']); ?></span>
                            </li>
                            <li>
                                <a href="driver-dashboard.php?tab=profile" class="block px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-primary-400 transition duration-300">
                                    <span class="lucide mr-2" aria-hidden="true">&#xea05;</span>
                                    My Profile
                                </a>
                            </li>
                            <li class="border-t border-gray-700 mt-2 pt-2">
                                <a href="driver-logout.php" id="logout-link" class="block px-4 py-2 text-red-400 hover:bg-gray-700 hover:text-red-300 transition duration-300">
                                    <span class="lucide mr-2" aria-hidden="true">&#xea7b;</span>
                                    Log Out
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="md:hidden">
                <button id="mobile-menu-btn" class="text-gray-300 hover:text-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-1" aria-label="Toggle mobile menu" aria-expanded="false">
                    <span class="lucide text-3xl">&#xe9b2;</span>
                </button>
            </div>
        </div>
        
        <div id="mobile-menu" class="hidden md:hidden bg-gray-800/95 backdrop-blur-md border-b border-gray-700/50 shadow-lg">
            <div class="container mx-auto px-4 py-3 space-y-3">
                <a href="driver-dashboard.php" class="block <?php echo ($currentPage == 'driver-dashboard' && !isset($_GET['tab'])) ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 py-2">Dashboard</a>
                <a href="driver-dashboard.php?tab=rides" class="block <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'rides') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 py-2">Available Rides</a>
                <a href="driver-dashboard.php?tab=history" class="block <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'history') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 py-2">Ride History</a>
                <a href="driver-dashboard.php?tab=earnings" class="block <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'earnings') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 py-2">Earnings</a>
                <a href="driver-dashboard.php?tab=profile" class="block <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'profile') ? 'text-primary-400' : 'text-gray-300 hover:text-primary-400'; ?> transition duration-300 py-2">Profile</a>
                
                <div class="border-t border-gray-700 mt-2 pt-2">
                    <span class="block text-sm text-gray-400 py-1">Signed in as <span class="text-white"><?php echo htmlspecialchars($_SESSION['driver']['name']); ?></span></span>
                    <a href="driver-logout.php" id="mobile-logout-link" class="block text-red-400 hover:text-red-300 transition duration-300 py-2">
                        <span class="lucide mr-2" aria-hidden="true">&#xea7b;</span>
                        Log Out
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="h-16 md:h-16"></div>