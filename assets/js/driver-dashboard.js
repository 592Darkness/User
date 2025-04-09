/**
 * Salaam Rides - Driver Dashboard JavaScript
 * Handles dynamic functionality for the driver dashboard with real data from the database
 */

// Global variables
let currentDriverId = null;
let activePollInterval = null;
let currentRideStatus = null;
let currentRideId = null;

// Initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log("Driver dashboard loaded");
    
    // Get driver ID from the page
    currentDriverId = document.body.getAttribute('data-driver-id');
    
    // Initialize based on the active tab
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'overview';
    
    initTabHandlers();
    
    switch (activeTab) {
        case 'overview':
            loadOverviewData();
            loadCurrentRide();
            break;
        case 'rides':
            loadAvailableRides();
            // Start auto-refresh for available rides tab
            startAvailableRidesAutoRefresh();
            break;
        case 'history':
            loadRideHistory();
            break;
        case 'earnings':
            loadEarningsData();
            break;
        case 'profile':
            // Profile form is already loaded in the HTML
            break;
    }
    
    // Set up global event handlers
    setupEventListeners();
});

// Global variables for auto-refresh
let availableRidesRefreshInterval = null;
let refreshCountdown = 10;
let refreshTimerElement = null;

// Tab navigation handlers
function initTabHandlers() {
    const dashboardTabs = document.querySelectorAll('.dashboard-tab');
    
    dashboardTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            // Clear any active polls when switching tabs
            if (activePollInterval) {
                clearInterval(activePollInterval);
                activePollInterval = null;
            }
            
            // Clear available rides auto-refresh
            stopAvailableRidesAutoRefresh();
            
            // If switching to available rides tab, start auto-refresh
            const tabHref = this.getAttribute('href');
            if (tabHref && tabHref.includes('tab=rides')) {
                startAvailableRidesAutoRefresh();
            }
        });
    });
}

// Set up global event listeners
function setupEventListeners() {
    // Refresh buttons
    const refreshRidesBtn = document.getElementById('refresh-rides-btn');
    if (refreshRidesBtn) {
        refreshRidesBtn.addEventListener('click', loadAvailableRides);
    }
    
    const refreshHistoryBtn = document.getElementById('refresh-history-btn');
    if (refreshHistoryBtn) {
        refreshHistoryBtn.addEventListener('click', loadRideHistory);
    }
    
    // Filter changes
    const historyFilter = document.getElementById('history-filter');
    if (historyFilter) {
        historyFilter.addEventListener('change', loadRideHistory);
    }
    
    const earningsPeriod = document.getElementById('earnings-period');
    if (earningsPeriod) {
        earningsPeriod.addEventListener('change', loadEarningsData);
    }
    
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            mobileMenu.classList.toggle('hidden');
        });
    }
    
    // Handle page visibility changes - pause auto-refresh when tab is not visible
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            // Page is not visible (user switched tabs or minimized)
            stopAvailableRidesAutoRefresh();
        } else {
            // Page is visible again
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'overview';
            
            // Only restart refresh if on rides tab
            if (activeTab === 'rides') {
                startAvailableRidesAutoRefresh();
                loadAvailableRides(); // Refresh immediately when coming back
            }
        }
    });
}

/* Overview Tab Functions */

function loadOverviewData() {
    // Fetch overview data from API
    fetchWithLoader('/api/driver-earnings.php?period=day', 'GET')
        .then(response => {
            if (response.success) {
                updateOverviewStats(response.data.summary);
            } else {
                showConfirmation(response.message || "Failed to load overview data", true);
            }
        })
        .catch(error => {
            console.error("Error loading overview data:", error);
            showConfirmation("Failed to connect to server. Please try again.", true);
        });
}

function updateOverviewStats(data) {
    // Today's stats
    document.getElementById('today-rides').textContent = data.total_rides || '0';
    document.getElementById('today-earnings').textContent = data.formatted_earnings || 'G$0';
    document.getElementById('today-hours').textContent = data.total_hours || '0';
    
    // If we have weekly data, update it too
    fetchWithLoader('/api/driver-earnings.php?period=week', 'GET', null, false)
        .then(response => {
            if (response.success) {
                document.getElementById('weekly-rides').textContent = response.data.summary.total_rides || '0';
                document.getElementById('weekly-earnings').textContent = response.data.summary.formatted_earnings || 'G$0';
                document.getElementById('weekly-hours').textContent = response.data.summary.total_hours || '0';
                
                // Fetch driver rating data
                fetchWithLoader('/api/driver-rating.php', 'GET', null, false)
                    .then(ratingData => {
                        if (ratingData.success) {
                            document.getElementById('weekly-rating').textContent = ratingData.data.rating.toFixed(1);
                        }
                    })
                    .catch(error => {
                        console.error("Error loading rating data:", error);
                    });
            }
        })
        .catch(error => {
            console.error("Error loading weekly data:", error);
        });
}

function loadCurrentRide() {
    const rideLoading = document.getElementById('ride-loading');
    const currentRideInfo = document.getElementById('current-ride-info');
    const noRideInfo = document.getElementById('no-ride-info');
    
    // Show loading first
    if (rideLoading) rideLoading.classList.remove('hidden');
    if (currentRideInfo) currentRideInfo.classList.add('hidden');
    if (noRideInfo) noRideInfo.classList.add('hidden');
    
    fetchWithLoader('/api/driver-current-ride.php', 'GET', null, false)
        .then(response => {
            if (response.success) {
                if (rideLoading) rideLoading.classList.add('hidden');
                
                if (response.data.has_ride) {
                    // We have an active ride
                    const ride = response.data.ride;
                    currentRideId = ride.id;
                    currentRideStatus = ride.status;
                    
                    if (currentRideInfo) {
                        currentRideInfo.classList.remove('hidden');
                        currentRideInfo.innerHTML = generateCurrentRideHTML(ride);
                        
                        // Set up the action buttons
                        setupRideActionButtons(ride);
                    }
                    
                    // Start polling for updates
                    startRideStatusPolling();
                } else {
                    // No active ride
                    if (noRideInfo) noRideInfo.classList.remove('hidden');
                }
            } else {
                if (rideLoading) rideLoading.classList.add('hidden');
                if (noRideInfo) noRideInfo.classList.remove('hidden');
                showConfirmation(response.message || "Failed to load current ride data", true);
            }
        })
        .catch(error => {
            console.error("Error loading current ride:", error);
            if (rideLoading) rideLoading.classList.add('hidden');
            if (noRideInfo) noRideInfo.classList.remove('hidden');
            showConfirmation("Failed to connect to server. Please try again.", true);
        });
}

function generateCurrentRideHTML(ride) {
    // Format the ride status better for display
    let displayStatus = ride.status.replace('_', ' ');
    displayStatus = displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1);
    
    return `
        <div class="flex justify-between items-start mb-4">
            <div>
                <h4 class="text-lg font-medium text-white">${displayStatus}</h4>
                <p class="text-gray-400">${ride.pickup} → ${ride.dropoff}</p>
            </div>
            <div class="bg-primary-500/20 text-primary-400 px-3 py-1 rounded-full text-sm font-medium">
                ${ride.formatted_fare}
            </div>
        </div>
        
        <div class="flex items-center mb-4">
            <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center mr-3">
                <span class="lucide text-xl text-gray-300" aria-hidden="true">&#xebe4;</span>
            </div>
            <div>
                <p class="font-medium text-white">${ride.passenger.name}</p>
                <div class="flex items-center text-sm text-gray-400">
                    <span class="lucide text-yellow-400 mr-1" aria-hidden="true">&#xeae5;</span>
                    <span>${ride.passenger.rating}</span>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button type="button" id="ride-call-btn" class="bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg font-medium transition duration-300 text-sm">
                <span class="lucide mr-1" aria-hidden="true">&#xea9d;</span> Call
            </button>
            <button type="button" id="ride-navigate-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg font-medium transition duration-300 text-sm">
                <span class="lucide mr-1" aria-hidden="true">&#xea29;</span> Navigate
            </button>
            <button type="button" id="ride-status-btn" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg font-medium transition duration-300 text-sm">
                <span class="lucide mr-1" aria-hidden="true">&#xe96c;</span> <span id="ride-status-btn-text">Update Status</span>
            </button>
        </div>
    `;
}

function setupRideActionButtons(ride) {
    // Call button
    const callBtn = document.getElementById('ride-call-btn');
    if (callBtn && ride.passenger.phone) {
        callBtn.addEventListener('click', function() {
            window.location.href = `tel:${ride.passenger.phone}`;
        });
    }
    
    // Navigate button
    const navigateBtn = document.getElementById('ride-navigate-btn');
    if (navigateBtn) {
        navigateBtn.addEventListener('click', function() {
            // Get appropriate destination based on ride status
            let destination = '';
            if (['confirmed', 'arriving'].includes(ride.status)) {
                destination = ride.pickup; // Navigate to pickup
            } else {
                destination = ride.dropoff; // Navigate to dropoff
            }
            
            // Open in Google Maps
            const mapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination)}`;
            window.open(mapsUrl, '_blank');
        });
    }
    
    // Status button
    const statusBtn = document.getElementById('ride-status-btn');
    const statusBtnText = document.getElementById('ride-status-btn-text');
    
    if (statusBtn && statusBtnText) {
        // Configure button based on current status
        let nextStatus = '';
        let buttonText = '';
        let buttonClass = '';
        
        switch (ride.status) {
            case 'confirmed':
                nextStatus = 'arriving';
                buttonText = 'Arriving to Pickup';
                buttonClass = 'bg-blue-600 hover:bg-blue-700';
                break;
            case 'arriving':
                nextStatus = 'arrived';
                buttonText = 'Arrived at Pickup';
                buttonClass = 'bg-purple-600 hover:bg-purple-700';
                break;
            case 'arrived':
                nextStatus = 'in_progress';
                buttonText = 'Start Ride';
                buttonClass = 'bg-green-600 hover:bg-green-700';
                break;
            case 'in_progress':
                nextStatus = 'completed';
                buttonText = 'Complete Ride';
                buttonClass = 'bg-green-600 hover:bg-green-700';
                break;
            default:
                buttonText = 'Update Status';
                buttonClass = 'bg-gray-700 hover:bg-gray-600';
        }
        
        // Update button appearance
        statusBtnText.textContent = buttonText;
        
        // Remove existing classes and add new ones
        statusBtn.className = `${buttonClass} text-white py-2 px-4 rounded-lg font-medium transition duration-300 text-sm`;
        
        // Add click event
        statusBtn.addEventListener('click', function() {
            if (nextStatus) {
                updateRideStatus(ride.id, nextStatus);
            } else {
                // Show a status selection dropdown or modal for edge cases
                showConfirmation('This ride status cannot be updated', true);
            }
        });
    }
}

function updateRideStatus(rideId, newStatus) {
    showLoadingIndicator();
    
    fetch('/api/driver-update-ride.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            ride_id: rideId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingIndicator();
        
        if (data.success) {
            // Show success message
            showConfirmation(data.data.message || 'Ride status updated successfully');
            
            // Update current status
            currentRideStatus = newStatus;
            
            // Reload current ride data with updated status
            loadCurrentRide();
            
            // If ride completed or cancelled, reload overview data too
            if (['completed', 'cancelled'].includes(newStatus)) {
                loadOverviewData();
            }
        } else {
            showConfirmation(data.message || 'Failed to update ride status', true);
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error updating ride status:', error);
        showConfirmation('Failed to connect to server. Please try again.', true);
    });
}

function startRideStatusPolling() {
    // Clear any existing poll
    if (activePollInterval) {
        clearInterval(activePollInterval);
    }
    
    // Poll every 15 seconds
    activePollInterval = setInterval(() => {
        fetch('/api/driver-current-ride.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data.has_ride) {
                    const ride = data.data.ride;
                    
                    // Check if status has changed
                    if (ride.status !== currentRideStatus) {
                        loadCurrentRide(); // Reload the entire UI
                    }
                } else {
                    // No longer have an active ride
                    loadCurrentRide();
                }
            }
        })
        .catch(error => {
            console.error('Error polling ride status:', error);
        });
    }, 15000); // 15 seconds
}

// Functions for auto-refreshing available rides
function startAvailableRidesAutoRefresh() {
    // Clear any existing interval first
    stopAvailableRidesAutoRefresh();
    
    // Set countdown timer
    refreshCountdown = 10;
    updateRefreshTimer();
    
    // Create new interval that refreshes every 10 seconds
    availableRidesRefreshInterval = setInterval(() => {
        refreshCountdown--;
        
        // Update the countdown display
        updateRefreshTimer();
        
        // When countdown reaches 0, refresh the data
        if (refreshCountdown <= 0) {
            loadAvailableRides();
            refreshCountdown = 10; // Reset countdown
        }
    }, 1000); // Update every second
}

function stopAvailableRidesAutoRefresh() {
    if (availableRidesRefreshInterval) {
        clearInterval(availableRidesRefreshInterval);
        availableRidesRefreshInterval = null;
    }
}

function updateRefreshTimer() {
    if (refreshTimerElement) {
        refreshTimerElement.innerHTML = `Refreshing in <span class="text-primary-400 font-medium">${refreshCountdown}s</span>`;
    }
}

/* Available Rides Tab Functions */

function loadAvailableRides() {
    const container = document.getElementById('available-rides-container');
    if (!container) return;
    
    // Show loading state
    container.innerHTML = `
        <div class="py-10 flex flex-col items-center justify-center">
            <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
            <p class="text-gray-400">Loading available rides...</p>
        </div>
    `;
    
    // Reset refresh countdown
    refreshCountdown = 10;
    updateRefreshTimer();
    
    fetchWithLoader('/api/driver-available-rides.php', 'GET', null, false) // We use our own loading indicator
        .then(response => {
            if (response.success) {
                const rides = response.data.rides || [];
                
                // Update the header to include auto-refresh indicator
                const headingRow = `
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-white">Available Rides</h2>
                        <div class="flex items-center">
                            <div id="refresh-timer" class="text-gray-400 text-sm mr-4">
                                Refreshing in <span class="text-primary-400 font-medium">${refreshCountdown}s</span>
                            </div>
                            <button id="refresh-rides-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-1.5 px-4 rounded-lg font-medium text-sm transition duration-300 flex items-center">
                                <span class="lucide mr-1" aria-hidden="true">&#xe9d7;</span> Refresh
                            </button>
                        </div>
                    </div>
                `;
                
                // Start with the header
                container.innerHTML = headingRow;
                
                if (rides.length > 0) {
                    // Create a rides container
                    const ridesContainer = document.createElement('div');
                    ridesContainer.className = 'space-y-4';
                    
                    rides.forEach(ride => {
                        const rideElement = document.createElement('div');
                        rideElement.className = 'bg-gray-700/50 rounded-lg p-5 border border-gray-600 hover:border-primary-500 transition duration-300 mb-4';
                        
                        // Format the distance display with a fallback
                        const distanceDisplay = typeof ride.distance === 'number' && ride.distance > 0 
                            ? `${ride.distance.toFixed(1)} km Trip` 
                            : 'Distance unavailable';
                        
                        rideElement.innerHTML = `
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="text-lg font-medium text-white">${distanceDisplay}</h4>
                                    <p class="text-gray-400">${ride.pickup} → ${ride.dropoff}</p>
                                </div>
                                <div class="bg-primary-500/20 text-primary-400 px-3 py-1 rounded-full text-sm font-medium">
                                    G${ride.fare.toLocaleString()}
                                </div>
                            </div>
                            
                            <div class="flex items-center mb-4">
                                <div class="w-10 h-10 rounded-full bg-gray-600 flex items-center justify-center mr-3">
                                    <span class="lucide text-xl text-gray-300" aria-hidden="true">&#xebe4;</span>
                                </div>
                                <div>
                                    <p class="font-medium text-white">${ride.passenger.name}</p>
                                    <div class="flex items-center text-sm text-gray-400">
                                        <span class="lucide text-yellow-400 mr-1" aria-hidden="true">&#xeae5;</span>
                                        <span>${ride.passenger.rating}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="w-full bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg font-medium transition duration-300 text-center accept-ride-btn" data-ride-id="${ride.id}">
                                Accept Ride
                            </button>
                        `;
                        
                        ridesContainer.appendChild(rideElement);
                    });
                    
                    container.appendChild(ridesContainer);
                    
                    // Add event listeners to accept buttons
                    document.querySelectorAll('.accept-ride-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const rideId = parseInt(this.getAttribute('data-ride-id'));
                            acceptRide(rideId);
                        });
                    });
                    
                } else {
                    container.innerHTML += `
                        <div class="bg-gray-700/30 rounded-lg p-6 text-center">
                            <p class="text-gray-400 mb-2">No available rides at the moment.</p>
                            <p class="text-sm text-gray-500">Check back soon or update your status to available.</p>
                        </div>
                    `;
                }
            } else {
                container.innerHTML = `
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-white">Available Rides</h2>
                        <button id="refresh-rides-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-1.5 px-4 rounded-lg font-medium text-sm transition duration-300 flex items-center">
                            <span class="lucide mr-1" aria-hidden="true">&#xe9d7;</span> Refresh
                        </button>
                    </div>
                    <div class="bg-gray-700/30 rounded-lg p-6 text-center">
                        <p class="text-red-400 mb-2">${response.message || 'Failed to load available rides'}</p>
                        <p class="text-sm text-gray-500">Ensure you are set to available status to see rides.</p>
                    </div>
                `;
            }
            
            // Get refresh timer element for future updates
            refreshTimerElement = document.getElementById('refresh-timer');
            
            // Reattach the refresh button event handler
            const refreshRidesBtn = document.getElementById('refresh-rides-btn');
            if (refreshRidesBtn) {
                refreshRidesBtn.addEventListener('click', function() {
                    // Manually refresh and reset the timer
                    loadAvailableRides();
                    refreshCountdown = 10;
                    updateRefreshTimer();
                });
            }
        })
        .catch(error => {
            console.error("Error loading available rides:", error);
            container.innerHTML = `
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-white">Available Rides</h2>
                    <button id="refresh-rides-btn" class="bg-gray-700 hover:bg-gray-600 text-white py-1.5 px-4 rounded-lg font-medium text-sm transition duration-300 flex items-center">
                        <span class="lucide mr-1" aria-hidden="true">&#xe9d7;</span> Refresh
                    </button>
                </div>
                <div class="bg-gray-700/30 rounded-lg p-6 text-center">
                    <p class="text-red-400 mb-2">Failed to connect to server</p>
                    <p class="text-sm text-gray-500">Please check your internet connection and try again.</p>
                </div>
            `;
            
            // Reattach the refresh button event handler
            const refreshRidesBtn = document.getElementById('refresh-rides-btn');
            if (refreshRidesBtn) {
                refreshRidesBtn.addEventListener('click', loadAvailableRides);
            }
        });
}

function acceptRide(rideId) {
    showLoadingIndicator();
    
    fetch('/api/driver-accept-ride.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            ride_id: rideId
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingIndicator();
        
        if (data.success) {
            showConfirmation('Ride accepted successfully! Navigate to pickup location.');
            // Redirect to overview to see current ride
            window.location.href = 'driver-dashboard.php?tab=overview';
        } else {
            showConfirmation(data.message || 'Failed to accept ride', true);
            // Refresh available rides to get updated list
            loadAvailableRides();
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error accepting ride:', error);
        showConfirmation('Failed to connect to server. Please try again.', true);
    });
}

/* Ride History Tab Functions */

function loadRideHistory() {
    const container = document.getElementById('ride-history-container');
    if (!container) return;
    
    // Get the selected filter
    const filter = document.getElementById('history-filter')?.value || 'all';
    
    // Show loading state
    container.innerHTML = `
        <div class="py-10 flex flex-col items-center justify-center">
            <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
            <p class="text-gray-400">Loading ride history...</p>
        </div>
    `;
    
    fetchWithLoader(`/api/driver-ride-history.php?filter=${filter}`, 'GET')
        .then(response => {
            if (response.success) {
                const rides = response.data.rides || [];
                
                if (rides.length > 0) {
                    container.innerHTML = '';
                    
                    rides.forEach(ride => {
                        const rideElement = document.createElement('div');
                        rideElement.className = 'bg-gray-700/50 rounded-lg p-5 border border-gray-600 transition duration-300 mb-4';
                        rideElement.innerHTML = `
                            <div class="flex flex-wrap justify-between items-start mb-4">
                                <div>
                                    <div class="flex items-center mb-1">
                                        <h4 class="text-lg font-medium text-white mr-3">${ride.date}</h4>
                                        <span class="text-sm text-gray-400">${ride.time}</span>
                                    </div>
                                    <p class="text-gray-400">${ride.pickup} → ${ride.dropoff}</p>
                                </div>
                                <div class="mt-2 sm:mt-0 flex items-center">
                                    <span class="mr-3 ${ride.status === 'completed' ? 'text-green-400' : 'text-red-400'}">${ride.status.charAt(0).toUpperCase() + ride.status.slice(1)}</span>
                                    <div class="bg-primary-500/20 text-primary-400 px-3 py-1 rounded-full text-sm font-medium">
                                        ${ride.formatted_fare}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center mr-2">
                                    <span class="lucide text-sm text-gray-300" aria-hidden="true">&#xebe4;</span>
                                </div>
                                <div>
                                    <p class="font-medium text-white text-sm">${ride.passenger.name}</p>
                                    <div class="flex items-center text-xs text-gray-400">
                                        <span class="lucide text-yellow-400 mr-1" aria-hidden="true">&#xeae5;</span>
                                        <span>${ride.passenger.rating}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        container.appendChild(rideElement);
                    });
                    
                    // Add pagination if needed
                    if (response.data.pagination && response.data.pagination.total_pages > 1) {
                        const paginationElement = document.createElement('div');
                        paginationElement.className = 'mt-6 flex justify-center';
                        paginationElement.innerHTML = generatePaginationHTML(response.data.pagination);
                        container.appendChild(paginationElement);
                    }
                    
                } else {
                    container.innerHTML = `
                        <div class="bg-gray-700/30 rounded-lg p-6 text-center">
                            <p class="text-gray-400 mb-2">No ride history found for the selected filter.</p>
                            <p class="text-sm text-gray-500">Try another filter or check back after completing more rides.</p>
                        </div>
                    `;
                }
            } else {
                container.innerHTML = `
                    <div class="bg-gray-700/30 rounded-lg p-6 text-center">
                        <p class="text-red-400 mb-2">${response.message || 'Failed to load ride history'}</p>
                        <p class="text-sm text-gray-500">Please try again later.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error("Error loading ride history:", error);
            container.innerHTML = `
                <div class="bg-gray-700/30 rounded-lg p-6 text-center">
                    <p class="text-red-400 mb-2">Failed to connect to server</p>
                    <p class="text-sm text-gray-500">Please check your internet connection and try again.</p>
                </div>
            `;
        });
}

function generatePaginationHTML(pagination) {
    const currentPage = pagination.current_page;
    const totalPages = pagination.total_pages;
    
    let html = '<div class="inline-flex items-center justify-center gap-2">';
    
    // Previous button
    if (pagination.has_prev_page) {
        html += `<a href="?tab=history&page=${pagination.prev_page}" class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg text-sm transition duration-300">Previous</a>`;
    } else {
        html += `<span class="bg-gray-700/50 text-gray-500 py-2 px-4 rounded-lg text-sm cursor-not-allowed">Previous</span>`;
    }
    
    // Page numbers
    html += `<span class="text-gray-400">Page ${currentPage} of ${totalPages}</span>`;
    
    // Next button
    if (pagination.has_next_page) {
        html += `<a href="?tab=history&page=${pagination.next_page}" class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded-lg text-sm transition duration-300">Next</a>`;
    } else {
        html += `<span class="bg-gray-700/50 text-gray-500 py-2 px-4 rounded-lg text-sm cursor-not-allowed">Next</span>`;
    }
    
    html += '</div>';
    
    return html;
}

/* Earnings Tab Functions */

function loadEarningsData() {
    const period = document.getElementById('earnings-period')?.value || 'week';
    const periodText = document.getElementById('earnings-period-text');
    
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
    
    fetchWithLoader(`/api/driver-earnings.php?period=${period}`, 'GET')
        .then(response => {
            if (response.success) {
                updateEarningsSummary(response.data.summary);
                updateEarningsBreakdown(response.data.breakdown, period); // Pass period as a parameter
                updatePaymentsTable(response.data.payments);
            } else {
                showConfirmation(response.message || "Failed to load earnings data", true);
            }
        })
        .catch(error => {
            console.error("Error loading earnings data:", error);
            showConfirmation("Failed to connect to server. Please try again.", true);
        });
}

function updateEarningsSummary(summary) {
    document.getElementById('total-earnings').textContent = summary.formatted_earnings || 'G$0';
    document.getElementById('total-rides').textContent = summary.total_rides || '0';
    document.getElementById('avg-fare').textContent = summary.formatted_avg_fare || 'G$0';
    document.getElementById('total-hours').textContent = summary.total_hours || '0';
    document.getElementById('avg-hourly').textContent = summary.formatted_hourly_rate || 'G$0';
}

function updateEarningsBreakdown(breakdownData, period) { // Added period parameter
    const chartContainer = document.getElementById('earnings-breakdown-chart');
    if (!chartContainer) return;
    
    // Clear previous content
    chartContainer.innerHTML = '';
    
    if (!breakdownData || breakdownData.length === 0) {
        chartContainer.innerHTML = '<div class="h-full flex items-center justify-center"><p class="text-gray-400">No earnings data available for the selected period.</p></div>';
        return;
    }
    
    // Create a simple bar chart using SVG - the rest of your chart code remains the same
    const width = chartContainer.clientWidth;
    const height = chartContainer.clientHeight;
    const padding = { top: 40, right: 20, bottom: 60, left: 60 };
    
    // Calculate chart area
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    
    // Find the maximum earning value for scaling
    const maxEarning = Math.max(...breakdownData.map(item => item.earnings));
    
    // Calculate bar width based on the number of data points
    const barWidth = Math.max(30, Math.min(80, chartWidth / breakdownData.length - 10));
    
    // Create SVG element
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.style.overflow = 'visible';
    
    // Create a group for the entire chart
    const chart = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    chart.setAttribute('transform', `translate(${padding.left}, ${padding.top})`);
    
    // Add Y-axis line
    const yAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    yAxis.setAttribute('x1', 0);
    yAxis.setAttribute('y1', 0);
    yAxis.setAttribute('x2', 0);
    yAxis.setAttribute('y2', chartHeight);
    yAxis.setAttribute('stroke', '#4B5563');
    yAxis.setAttribute('stroke-width', 1);
    chart.appendChild(yAxis);
    
    // Add X-axis line
    const xAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    xAxis.setAttribute('x1', 0);
    xAxis.setAttribute('y1', chartHeight);
    xAxis.setAttribute('x2', chartWidth);
    xAxis.setAttribute('y2', chartHeight);
    xAxis.setAttribute('stroke', '#4B5563');
    xAxis.setAttribute('stroke-width', 1);
    chart.appendChild(xAxis);
    
    // Generate bars and labels
    breakdownData.forEach((item, index) => {
        const x = (chartWidth / breakdownData.length) * index + (chartWidth / breakdownData.length - barWidth) / 2;
        const barHeight = (item.earnings / maxEarning) * chartHeight;
        const y = chartHeight - barHeight;
        
        // Draw bar
        const bar = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bar.setAttribute('x', x);
        bar.setAttribute('y', y);
        bar.setAttribute('width', barWidth);
        bar.setAttribute('height', barHeight);
        bar.setAttribute('fill', '#10B981');
        bar.setAttribute('rx', 4);
        
        // Add hover effect
        bar.setAttribute('opacity', 0.8);
        bar.addEventListener('mouseover', function() {
            this.setAttribute('opacity', 1);
            this.setAttribute('fill', '#059669');
        });
        bar.addEventListener('mouseout', function() {
            this.setAttribute('opacity', 0.8);
            this.setAttribute('fill', '#10B981');
        });
        
        // Add tooltip on hover
        bar.addEventListener('mouseover', function(e) {
            const tooltip = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            tooltip.setAttribute('id', 'earnings-tooltip');
            
            const tooltipBg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            tooltipBg.setAttribute('x', x - 40);
            tooltipBg.setAttribute('y', y - 50);
            tooltipBg.setAttribute('width', 100);
            tooltipBg.setAttribute('height', 40);
            tooltipBg.setAttribute('fill', '#1F2937');
            tooltipBg.setAttribute('stroke', '#374151');
            tooltipBg.setAttribute('rx', 4);
            
            const tooltipText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            tooltipText.setAttribute('x', x + 10);
            tooltipText.setAttribute('y', y - 30);
            tooltipText.setAttribute('fill', 'white');
            tooltipText.setAttribute('font-size', '12');
            tooltipText.textContent = `G$${item.earnings.toLocaleString()}`;
            
            const tooltipRides = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            tooltipRides.setAttribute('x', x + 10);
            tooltipRides.setAttribute('y', y - 15);
            tooltipRides.setAttribute('fill', '#9CA3AF');
            tooltipRides.setAttribute('font-size', '12');
            tooltipRides.textContent = `${item.rides} rides`;
            
            tooltip.appendChild(tooltipBg);
            tooltip.appendChild(tooltipText);
            tooltip.appendChild(tooltipRides);
            chart.appendChild(tooltip);
        });
        
        bar.addEventListener('mouseout', function() {
            const tooltip = document.getElementById('earnings-tooltip');
            if (tooltip) {
                chart.removeChild(tooltip);
            }
        });
        
        chart.appendChild(bar);
        
        // X-axis label (day)
        const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        label.setAttribute('x', x + barWidth / 2);
        label.setAttribute('y', chartHeight + 20);
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('fill', '#9CA3AF');
        label.setAttribute('font-size', '12');
        label.textContent = item.label;
        chart.appendChild(label);
        
        // Add value on top of bar
        const valueLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        valueLabel.setAttribute('x', x + barWidth / 2);
        valueLabel.setAttribute('y', y - 5);
        valueLabel.setAttribute('text-anchor', 'middle');
        valueLabel.setAttribute('fill', '#D1D5DB');
        valueLabel.setAttribute('font-size', '12');
        valueLabel.textContent = 'G$' + Math.round(item.earnings).toLocaleString();
        chart.appendChild(valueLabel);
    });
    
    // Add Y-axis labels
    if (maxEarning > 0) {
        for (let i = 0; i <= 5; i++) {
            const yValue = maxEarning * (i / 5);
            const yPos = chartHeight - (chartHeight * i / 5);
            
            // Add grid line
            const gridLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            gridLine.setAttribute('x1', 0);
            gridLine.setAttribute('y1', yPos);
            gridLine.setAttribute('x2', chartWidth);
            gridLine.setAttribute('y2', yPos);
            gridLine.setAttribute('stroke', '#374151');
            gridLine.setAttribute('stroke-width', 0.5);
            gridLine.setAttribute('stroke-dasharray', '4,4');
            chart.appendChild(gridLine);
            
            // Add label
            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('x', -10);
            label.setAttribute('y', yPos + 4);
            label.setAttribute('text-anchor', 'end');
            label.setAttribute('fill', '#9CA3AF');
            label.setAttribute('font-size', '12');
            label.textContent = 'G$' + Math.round(yValue).toLocaleString();
            chart.appendChild(label);
        }
    }
    
    // Add title - Now using period parameter that's passed in
    const title = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    title.setAttribute('x', chartWidth / 2);
    title.setAttribute('y', -15);
    title.setAttribute('text-anchor', 'middle');
    title.setAttribute('fill', 'white');
    title.setAttribute('font-size', '14');
    title.setAttribute('font-weight', 'bold');
    title.textContent = 'Earnings by ' + (period === 'day' ? 'Hour' : period === 'week' ? 'Day' : period === 'month' ? 'Day' : 'Month');
    chart.appendChild(title);
    
    svg.appendChild(chart);
    chartContainer.appendChild(svg);
}

function updatePaymentsTable(payments) {
    const tableBody = document.getElementById('payments-table-body');
    if (!tableBody) return;
    
    // Clear existing content
    tableBody.innerHTML = '';
    
    if (!payments || payments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-10 text-gray-400">No payment history available.</td></tr>';
        return;
    }
    
    payments.forEach(payment => {
        const row = document.createElement('tr');
        row.className = 'border-b border-gray-700';
        
        row.innerHTML = `
            <td class="py-3 px-4">${payment.date}</td>
            <td class="py-3 px-4">${payment.description}</td>
            <td class="py-3 px-4">
                <span class="bg-${payment.status === 'completed' ? 'green' : payment.status === 'pending' ? 'yellow' : 'gray'}-500/20 
                             text-${payment.status === 'completed' ? 'green' : payment.status === 'pending' ? 'yellow' : 'gray'}-400 
                             text-xs px-2.5 py-0.5 rounded-full">
                    ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                </span>
            </td>
            <td class="py-3 px-4 text-right">${payment.formatted_amount}</td>
        `;
        
        tableBody.appendChild(row);
    });
}

/* Utility Functions */

function showLoadingIndicator() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('hidden');
    }
}

function hideLoadingIndicator() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
}

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

function formatCurrency(value) {
    return 'G$' + parseInt(value).toLocaleString();
}

async function fetchWithLoader(url, method = 'GET', body = null, showLoader = true) {
    if (showLoader) {
        showLoadingIndicator();
    }
    
    try {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (body) {
            options.body = JSON.stringify(body);
        }
        
        const response = await fetch(url, options);
        const data = await response.json();
        
        if (showLoader) {
            hideLoadingIndicator();
        }
        
        return data;
    } catch (error) {
        if (showLoader) {
            hideLoadingIndicator();
        }
        throw error;
    }
}

/**
 * Driver location tracking module
 * Add this to your driver dashboard JavaScript
 */

let trackingInterval;
const TRACKING_FREQUENCY = 30000; // Update every 30 seconds

function startLocationTracking() {
    // Stop any existing tracking
    stopLocationTracking();
    
    // Start the new tracking interval
    updateDriverLocation(); // First immediate update
    trackingInterval = setInterval(updateDriverLocation, TRACKING_FREQUENCY);
    
    console.log("Location tracking started");
}

function stopLocationTracking() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
        console.log("Location tracking stopped");
    }
}

function updateDriverLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const locationData = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                };
                
                // Send to server
                fetch('/api/driver-location-update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(locationData)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error("Location update failed:", data.message);
                    }
                })
                .catch(error => {
                    console.error("Error updating location:", error);
                });
            },
            function(error) {
                console.error("Geolocation error:", error.message);
                
                // Handle specific errors
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        showConfirmation("Location access denied. Please enable location permissions.", true);
                        break;
                    case error.POSITION_UNAVAILABLE:
                        console.log("Location information unavailable.");
                        break;
                    case error.TIMEOUT:
                        console.log("Location request timed out.");
                        break;
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        console.error("Geolocation is not supported by this browser");
        showConfirmation("Your browser doesn't support location tracking.", true);
    }
}

// Add event listener to status toggle button
document.addEventListener('DOMContentLoaded', function() {
    // Start tracking when driver goes online
    const statusForm = document.querySelector('form[action="api/driver-update-status.php"]');
    const statusInput = statusForm ? statusForm.querySelector('input[name="status"]') : null;
    
    if (statusForm && statusInput) {
        statusForm.addEventListener('submit', function() {
            const newStatus = statusInput.value;
            
            if (newStatus === 'available') {
                // Start tracking when going online
                startLocationTracking();
            } else {
                // Stop tracking when going offline
                stopLocationTracking();
            }
        });
    }
    
    // If driver is already available, start tracking
    const driverStatus = document.body.getAttribute('data-driver-status');
    if (driverStatus === 'available') {
        startLocationTracking();
    }
});