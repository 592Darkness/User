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

/**
 *
 * @param {Array} breakdownData - Array of objects like {label: 'Mon', earnings: 15000, rides: 5}
 * @param {string} period - The time period ('day', 'week', 'month', etc.)
 */
function updateEarningsBreakdown(breakdownData, period) {
    const chartContainer = document.getElementById('earnings-breakdown-chart');
    if (!chartContainer) {
        console.error("Earnings breakdown chart container not found.");
        return; // Exit if container doesn't exist
    }
    chartContainer.innerHTML = ''; // Clear previous content

    // --- Log initial container size for debugging ---
    const initialWidth = chartContainer.clientWidth;
    const initialHeight = chartContainer.clientHeight;
    console.log(`[Chart Debug] Initial container dimensions: width=${initialWidth}, height=${initialHeight}`);

    // --- Check for valid data ---
    if (!breakdownData || !Array.isArray(breakdownData) || breakdownData.length === 0) {
        chartContainer.innerHTML = '<div class="h-full flex items-center justify-center"><p class="text-gray-400">No earnings data available for the selected period.</p></div>';
        console.log("[Chart Debug] No breakdown data provided.");
        return; // Exit if no data
    }

    // --- Check for valid container dimensions ---
    if (!initialWidth || !initialHeight || initialWidth <= 50 || initialHeight <= 50) { // Added minimum size check
        console.error(`[Chart Debug] Chart container has invalid dimensions or is not visible yet: width=${initialWidth}, height=${initialHeight}. Cannot draw chart.`);
        // Display error message in the container
        chartContainer.innerHTML = '<div class="h-full flex items-center justify-center p-4"><p class="text-red-400 text-center">Error: Chart container size is too small or invalid. Please ensure it is visible and has dimensions.</p></div>';
        // Optionally, try again after a delay if it might be a timing issue
        // setTimeout(() => updateEarningsBreakdown(breakdownData, period), 200);
        return; // Exit if dimensions are invalid
    }

    // Define chart padding
    const padding = { top: 40, right: 20, bottom: 60, left: 60 };

    // Calculate actual chart drawing area dimensions
    const chartWidth = initialWidth - padding.left - padding.right;
    const chartHeight = initialHeight - padding.top - padding.bottom;

    // --- Check calculated chart dimensions ---
    if (chartWidth <= 0 || chartHeight <= 0) {
        console.error(`[Chart Debug] Calculated chart dimensions are invalid: chartWidth=${chartWidth}, chartHeight=${chartHeight}`);
        chartContainer.innerHTML = '<div class="h-full flex items-center justify-center"><p class="text-red-400">Error: Cannot draw chart in the calculated area.</p></div>';
        return;
    }
    console.log(`[Chart Debug] Calculated chart dimensions: width=${chartWidth}, height=${chartHeight}`);

    // --- Calculate Max Earning Safely ---
    const earningsValues = breakdownData.map(item => parseFloat(item.earnings) || 0); // Ensure numbers, default invalid to 0
    let maxEarning = Math.max(0, ...earningsValues); // Ensure max is at least 0
    // Use a sensible minimum for the scale if max earning is very low or zero, prevents visually squashed bars
    const scaleMaxEarning = maxEarning <= 1 ? (breakdownData.length > 0 ? 1000 : 1) : maxEarning;
    console.log(`[Chart Debug] Max Earning: ${maxEarning}, Scale Max Earning: ${scaleMaxEarning}`);

    // --- Calculate Bar Width and Spacing ---
    const barSpacing = 10;
    const totalSpacing = barSpacing * (breakdownData.length - 1);
    const availableWidthForBars = chartWidth - totalSpacing;
    const calculatedBarWidth = (breakdownData.length > 0) ? (availableWidthForBars / breakdownData.length) : chartWidth; // Avoid division by zero
    const barWidth = Math.max(20, Math.min(80, calculatedBarWidth)); // Clamp bar width: Min 20px, Max 80px
    console.log(`[Chart Debug] Bar Width: ${barWidth}, Spacing: ${barSpacing}`);


    // --- Create SVG Structure ---
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', initialWidth);
    svg.setAttribute('height', initialHeight);
    svg.setAttribute('aria-labelledby', 'chartTitle'); // Accessibility
    svg.setAttribute('role', 'img'); // Accessibility
    svg.style.overflow = 'visible';

    const chart = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    chart.setAttribute('transform', `translate(${padding.left}, ${padding.top})`);

    // Add Axes lines
    const yAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    yAxis.setAttribute('x1', 0); yAxis.setAttribute('y1', 0); yAxis.setAttribute('x2', 0); yAxis.setAttribute('y2', chartHeight);
    yAxis.setAttribute('stroke', '#4B5563'); yAxis.setAttribute('stroke-width', 1);
    chart.appendChild(yAxis);
    const xAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    xAxis.setAttribute('x1', 0); xAxis.setAttribute('y1', chartHeight); xAxis.setAttribute('x2', chartWidth); xAxis.setAttribute('y2', chartHeight);
    xAxis.setAttribute('stroke', '#4B5563'); xAxis.setAttribute('stroke-width', 1);
    chart.appendChild(xAxis);

    // --- Generate Bars and Labels ---
    breakdownData.forEach((item, index) => {
        // Calculate X position for the bar
        const x = index * (barWidth + barSpacing);
        const finalX = isNaN(x) ? 0 : x; // Fallback for X

        // Calculate bar height safely
        const earningValue = parseFloat(item.earnings) || 0;
        const barHeightRatio = scaleMaxEarning > 0 ? (earningValue / scaleMaxEarning) : 0;
        // Ensure height is non-negative and at least 1px if value > 0 to be visible
        let barHeight = earningValue > 0 ? Math.max(1, barHeightRatio * chartHeight) : 0;
        // Ensure y is calculated correctly based on height
        let y = chartHeight - barHeight;

        // Final check for NaN values before setting attributes
        const finalY = isNaN(y) ? chartHeight : Math.max(0, y); // Ensure Y is not negative
        const finalHeight = isNaN(barHeight) || barHeight < 0 ? 0 : Math.min(barHeight, chartHeight); // Ensure height isn't negative or larger than chart
        const finalBarWidth = isNaN(barWidth) || barWidth <= 0 ? 20 : barWidth; // Ensure width is positive

        // Log calculated values for this bar
        console.log(`[Chart Debug] Item ${index}: Label=${item.label}, Earning=${earningValue}, X=${finalX}, Y=${finalY}, Height=${finalHeight}, Width=${finalBarWidth}`);

        // Skip rendering if critical values are NaN
        if (isNaN(finalY) || isNaN(finalHeight) || isNaN(finalX) || isNaN(finalBarWidth)) {
             console.error(`>>> NaN DETECTED for item ${index}: Y=${finalY}, Height=${finalHeight}, X=${finalX}, Width=${finalBarWidth}. Skipping bar rendering.`);
             return; // Skip this iteration
        }

        // Draw the bar
        const bar = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bar.setAttribute('x', finalX);
        bar.setAttribute('y', finalY);
        bar.setAttribute('width', finalBarWidth);
        bar.setAttribute('height', finalHeight);
        bar.setAttribute('fill', '#10B981'); // primary-500
        bar.setAttribute('rx', 4);
        bar.setAttribute('opacity', 0.8);
        // ... (Add mouseover/mouseout listeners as in previous example, ensuring tooltip positions are also checked for NaN) ...
         bar.addEventListener('mouseover', function(e) { /* ... Tooltip logic ... */ });
         bar.addEventListener('mouseout', function() { /* ... Tooltip logic ... */ });
        chart.appendChild(bar);

        // X-axis label
        const xLabelPos = finalX + finalBarWidth / 2;
        if (!isNaN(xLabelPos)) {
            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('x', xLabelPos);
            label.setAttribute('y', chartHeight + 20); // Position below axis
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('fill', '#9CA3AF'); // gray-400
            label.setAttribute('font-size', '12');
            label.textContent = item.label || `[${index}]`; // Fallback label
            chart.appendChild(label);
        } else {
            console.error(`>>> NaN DETECTED for item ${index} X label position.`);
        }

        // Value label on top (only if height > 10 to avoid overlap)
        const valueLabelY = finalY - 5;
        if (earningValue > 0 && finalHeight > 10 && !isNaN(valueLabelY) && !isNaN(xLabelPos)) {
            const valueLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            valueLabel.setAttribute('x', xLabelPos);
            valueLabel.setAttribute('y', valueLabelY);
            valueLabel.setAttribute('text-anchor', 'middle');
            valueLabel.setAttribute('fill', '#D1D5DB'); // gray-300
            valueLabel.setAttribute('font-size', '11');
            valueLabel.textContent = 'G$' + Math.round(earningValue).toLocaleString();
            chart.appendChild(valueLabel);
        } else if (isNaN(valueLabelY) || isNaN(xLabelPos)) {
            console.error(`>>> NaN DETECTED for item ${index} value label position calculation.`);
        }
    });

    // --- Add Y-axis Labels and Grid Lines ---
    const numYTicks = 5;
    for (let i = 0; i <= numYTicks; i++) {
        const yValue = (scaleMaxEarning / numYTicks) * i;
        const yPosRatio = scaleMaxEarning > 0 ? (yValue / scaleMaxEarning) : 0;
        let yPos = chartHeight - (chartHeight * yPosRatio);

        // Final check for NaN yPos
        if (isNaN(yPos)) {
             console.error(`>>> NaN DETECTED for Y-axis tick ${i} position.`);
             yPos = chartHeight - (chartHeight * i / numYTicks); // Fallback calculation
             if (isNaN(yPos)) continue; // Skip if still NaN
         }

        // Grid line
        const gridLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        gridLine.setAttribute('x1', 0);
        gridLine.setAttribute('y1', yPos);
        gridLine.setAttribute('x2', chartWidth);
        gridLine.setAttribute('y2', yPos);
        gridLine.setAttribute('stroke', '#374151'); // gray-700
        gridLine.setAttribute('stroke-width', 0.5);
        gridLine.setAttribute('stroke-dasharray', '4,4');
        chart.appendChild(gridLine);

        // Label
        const yLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        yLabel.setAttribute('x', -10);
        yLabel.setAttribute('y', yPos + 4);
        yLabel.setAttribute('text-anchor', 'end');
        yLabel.setAttribute('fill', '#9CA3AF'); // gray-400
        yLabel.setAttribute('font-size', '12');
        // Format label nicely
        yLabel.textContent = 'G$' + (yValue >= 1000 ? (yValue / 1000).toFixed(1) + 'k' : Math.round(yValue).toLocaleString());
        chart.appendChild(yLabel);
    }

    // --- Add Chart Title ---
    let chartTitleText = 'Earnings Breakdown'; // Default title
    // Determine title based on period more accurately
    if (period === 'day') chartTitleText = 'Earnings by Hour';
    else if (period === 'week') chartTitleText = 'Earnings by Day (This Week)';
    else if (period === 'month') chartTitleText = 'Earnings by Day (This Month)';
    else if (period === 'year') chartTitleText = 'Earnings by Month (This Year)';
    else if (period === 'all') chartTitleText = 'Earnings by Month (All Time)';

    const title = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    title.setAttribute('id', 'chartTitle');
    title.setAttribute('x', chartWidth / 2);
    title.setAttribute('y', -15);
    title.setAttribute('text-anchor', 'middle');
    title.setAttribute('fill', 'white');
    title.setAttribute('font-size', '14');
    title.setAttribute('font-weight', 'bold');
    title.textContent = chartTitleText;
    chart.appendChild(title);

    // --- Final Append ---
    svg.appendChild(chart);
    chartContainer.appendChild(svg);

    console.log("[Chart Debug] Earnings breakdown chart update completed.");
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

/**
 * assets/js/driver-dashboard.js
 * Additions for driver payment confirmation.
 */

document.addEventListener('DOMContentLoaded', () => {

    // --- Payment Confirmation Check ---
    const paymentConfirmationModalElement = document.getElementById('paymentConfirmationModal');
    let paymentConfirmationModal = null; // Initialize Bootstrap modal instance later
    if (paymentConfirmationModalElement) {
         paymentConfirmationModal = new bootstrap.Modal(paymentConfirmationModalElement);
    }
    let checkPaymentInterval; // To store the interval ID

    function checkForPaymentConfirmations() {
        // Fetch rides where customer has confirmed payment but driver hasn't
        // This requires a new or modified API endpoint. Let's assume you create
        // `api/driver-pending-confirmations.php` or modify `api/driver-current-ride.php`
        // to include this info.

        fetch('api/driver-pending-confirmations.php') // **You need to create this API endpoint**
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
             })
            .then(data => {
                if (data.success && data.rides && data.rides.length > 0) {
                    // Check if modal is already shown for one of these rides
                    const currentModalRideId = document.getElementById('confirm-modal-ride-id').value;
                    const needsToShowModal = data.rides.some(ride => ride.ride_id != currentModalRideId);

                    if (needsToShowModal && paymentConfirmationModal && !paymentConfirmationModalElement.classList.contains('show')) {
                        const rideToConfirm = data.rides[0]; // Show modal for the first one found

                        // Populate modal content
                        document.getElementById('confirm-ride-id').textContent = rideToConfirm.ride_id;
                        document.getElementById('confirm-customer-name').textContent = rideToConfirm.customer_name || 'N/A'; // Add customer name to API response
                        document.getElementById('confirm-ride-amount').textContent = rideToConfirm.fare ? `$${parseFloat(rideToConfirm.fare).toFixed(2)}` : 'N/A'; // Add fare to API response
                        document.getElementById('confirm-modal-ride-id').value = rideToConfirm.ride_id;

                        // Show the modal
                        paymentConfirmationModal.show();
                        // Optional: Stop polling while modal is shown to avoid duplicates?
                        // stopPaymentCheck();
                    }
                } else if (!data.success) {
                     console.warn("Could not check for pending payment confirmations:", data.message);
                     // Maybe stop polling if there's a persistent error
                }
            })
            .catch(error => {
                console.error('Error checking for payment confirmations:', error);
                // Potentially stop polling after several errors
                // stopPaymentCheck();
            });
    }

    function startPaymentCheck() {
        // Check immediately and then every 30 seconds (adjust interval as needed)
        if (!checkPaymentInterval) { // Prevent multiple intervals
             console.log("Starting payment confirmation checks...");
             checkForPaymentConfirmations(); // Initial check
             checkPaymentInterval = setInterval(checkForPaymentConfirmations, 30000); // 30 seconds
        }
    }

    function stopPaymentCheck() {
         console.log("Stopping payment confirmation checks.");
         clearInterval(checkPaymentInterval);
         checkPaymentInterval = null;
    }

    // --- Modal Button Actions ---
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    const disputeBtn = document.getElementById('disputePaymentBtn');

    function handleDriverConfirmation(action) {
        const rideId = document.getElementById('confirm-modal-ride-id').value;
        if (!rideId) {
            alert('Error: Ride ID not found in modal.');
            return;
        }

        // Disable buttons
        confirmBtn.disabled = true;
        disputeBtn.disabled = true;

        fetch('api/confirm-payment.php', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/json',
                 'Accept': 'application/json'
                 // Add authentication headers if needed
             },
             body: JSON.stringify({
                 ride_id: rideId,
                 user_type: 'driver',
                 action: action // 'confirm' or 'dispute'
             })
         })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Payment status updated to: ${data.new_status}`);
                paymentConfirmationModal.hide(); // Hide modal on success
                // Optionally refresh ride list or update UI
                 document.getElementById('confirm-modal-ride-id').value = ''; // Clear ride ID from modal
            } else {
                alert(`Error: ${data.message || 'Could not update payment status.'}`);
            }
        })
        .catch(error => {
            console.error('Error updating payment status:', error);
            alert('An error occurred. Please try again.');
        })
        .finally(() => {
             // Re-enable buttons
             confirmBtn.disabled = false;
             disputeBtn.disabled = false;
             // Maybe restart polling if it was stopped
             // startPaymentCheck();
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => handleDriverConfirmation('confirm'));
    }
    if (disputeBtn) {
        disputeBtn.addEventListener('click', () => handleDriverConfirmation('dispute'));
    }

     // Start polling when the driver dashboard loads
     // Ensure the driver is logged in and active before starting
     if (/* check if driver is logged in and potentially 'online' */ true) {
          startPaymentCheck();
     }

    // Make sure to stop polling if the driver logs out or goes offline
    // Add cleanup logic to stopPaymentCheck() in your logout/status update functions.

}); // End DOMContentLoaded

/**
 * Add this to your driver-dashboard.js file to ensure payment confirmation works
 */

// Add a global reference for the payment modal
let paymentConfirmationModal = null;

// Function to check for pending payment confirmations
function checkForPaymentConfirmations() {
    console.log("Checking for payment confirmations...");
    
    // Make the API call to check for pending confirmations
    fetch('api/driver-pending-confirmations.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Pending confirmations data:", data);
            
            if (data.success && data.rides && data.rides.length > 0) {
                // Get the payment confirmation modal element
                const modalElement = document.getElementById('paymentConfirmationModal');
                
                if (!modalElement) {
                    console.error("Payment confirmation modal not found in the DOM");
                    return;
                }
                
                // Get current modal ride ID if it exists
                const currentModalRideId = document.getElementById('confirm-modal-ride-id').value;
                
                // Check if we need to show a new modal (don't show if already displaying for this ride)
                const needsToShowModal = data.rides.some(ride => ride.ride_id != currentModalRideId);
                
                if (needsToShowModal) {
                    const rideToConfirm = data.rides[0]; // Show modal for the first one found
                    
                    // Populate modal content
                    document.getElementById('confirm-ride-id').textContent = rideToConfirm.ride_id;
                    document.getElementById('confirm-customer-name').textContent = rideToConfirm.customer_name || 'Customer';
                    document.getElementById('confirm-ride-amount').textContent = rideToConfirm.formatted_fare || 'G$0.00';
                    document.getElementById('confirm-modal-ride-id').value = rideToConfirm.ride_id;
                    
                    // Show the modal
                    console.log("Displaying payment confirmation modal for ride:", rideToConfirm.ride_id);
                    modalElement.style.display = 'flex';
                    
                    // If using Bootstrap modal, uncomment this:
                    // if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    //     paymentConfirmationModal = new bootstrap.Modal(modalElement);
                    //     paymentConfirmationModal.show();
                    // } else {
                    //     modalElement.style.display = 'flex';
                    // }
                }
            }
        })
        .catch(error => {
            console.error('Error checking for payment confirmations:', error);
        });
}

// Function to start polling for payment confirmations
function startPaymentConfirmationsCheck() {
    console.log("Starting payment confirmation checks...");
    // Check immediately
    checkForPaymentConfirmations();
    
    // Then check every 30 seconds
    setInterval(checkForPaymentConfirmations, 30000);
    
    // Set up modal buttons if they don't already have handlers
    setupPaymentConfirmationButtons();
}

// Function to set up the payment confirmation buttons
function setupPaymentConfirmationButtons() {
    const confirmBtn = document.getElementById('confirmPaymentBtn');
    const disputeBtn = document.getElementById('disputePaymentBtn');
    const modal = document.getElementById('paymentConfirmationModal');
    
    if (confirmBtn) {
        // Use a cloned element to ensure no duplicate event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        newConfirmBtn.addEventListener('click', function() {
            handlePaymentAction('confirm');
        });
    }
    
    if (disputeBtn) {
        // Use a cloned element to ensure no duplicate event listeners
        const newDisputeBtn = disputeBtn.cloneNode(true);
        disputeBtn.parentNode.replaceChild(newDisputeBtn, disputeBtn);
        
        newDisputeBtn.addEventListener('click', function() {
            handlePaymentAction('dispute');
        });
    }
    
    // Add close button functionality
    const closeButtons = modal?.querySelectorAll('.modal-close-btn');
    if (closeButtons && closeButtons.length > 0) {
        closeButtons.forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
    }
}

// Function to handle payment confirmation/dispute
function handlePaymentAction(action) {
    const rideId = document.getElementById('confirm-modal-ride-id').value;
    if (!rideId) {
        console.error('No ride ID found in modal');
        return;
    }
    
    // Show loading indicator
    showLoadingIndicator();
    
    // Make the API call
    fetch('api/confirm-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ride_id: rideId,
            action: action,
            user_type: 'driver'
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingIndicator();
        
        // Hide the modal
        const modal = document.getElementById('paymentConfirmationModal');
        if (modal) {
            modal.style.display = 'none';
        }
        
        // Show success/error message
        if (data.success) {
            showConfirmation(action === 'confirm' 
                ? 'Payment confirmed successfully!' 
                : 'Payment dispute submitted successfully');
        } else {
            showConfirmation(data.message || 'Failed to process payment action', true);
        }
        
        // Clear the modal ride ID
        document.getElementById('confirm-modal-ride-id').value = '';
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error processing payment action:', error);
        
        // Hide the modal
        const modal = document.getElementById('paymentConfirmationModal');
        if (modal) {
            modal.style.display = 'none';
        }
        
        showConfirmation('Network error. Please try again later.', true);
    });
}

// Start the payment confirmation check when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, initializing payment confirmation system");
    startPaymentConfirmationsCheck();
});

// Force a manual check to debug the issue
function forcePaymentConfirmationCheck() {
    checkForPaymentConfirmations();
}

// Manual testing function to simulate a pending payment
function simulatePendingPayment() {
    console.log("Simulating pending payment...");
    
    // Get the modal and elements
    const modal = document.getElementById('paymentConfirmationModal');
    const rideIdElement = document.getElementById('confirm-ride-id');
    const customerNameElement = document.getElementById('confirm-customer-name');
    const amountElement = document.getElementById('confirm-ride-amount');
    const hiddenRideIdInput = document.getElementById('confirm-modal-ride-id');
    
    // Set values
    if (rideIdElement) rideIdElement.textContent = '1234';
    if (customerNameElement) customerNameElement.textContent = 'Test Customer';
    if (amountElement) amountElement.textContent = 'G$2,500.00';
    if (hiddenRideIdInput) hiddenRideIdInput.value = '1234';
    
    // Show the modal
    if (modal) {
        modal.style.display = 'flex';
        console.log("Modal should now be visible");
    } else {
        console.error("Modal element not found!");
    }
}