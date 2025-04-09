// Global variables for user state
let currentUser = null;
let isLoggedIn = false;

/**
 * Checks the user's login status using localStorage first, then falls back to a server check.
 */
function checkLoginStatus() {
    console.log("Checking login status");

    // Optionally clear localStorage to force server check during development
    // localStorage.removeItem('isLoggedIn');
    // localStorage.removeItem('currentUser');

    const storedLoginStatus = localStorage.getItem('isLoggedIn');
    const storedUser = localStorage.getItem('currentUser');

    console.log("LocalStorage login status:", storedLoginStatus);
    console.log("LocalStorage user exists:", !!storedUser);

    // If localStorage indicates logged in and user data exists
    if (storedLoginStatus === 'true' && storedUser) {
        try {
            isLoggedIn = true;
            currentUser = JSON.parse(storedUser); // Parse stored user data
            console.log("Using localStorage user data:", currentUser);
            updateUIWithUserData(); // Update UI based on stored data
        } catch (error) {
            console.error('Error parsing stored user data:', error);
            // If parsing fails, clear potentially corrupted data and check with the server
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            serverAuthCheck();
        }
    } else {
        // If not found in localStorage, check with the server
        serverAuthCheck();
    }
}

/**
 * Open the ride details modal with information about a specific ride
 * Add this to dashboard.js
 * @param {object} ride - The ride object with all details
 */
function openRideDetails(ride) {
    console.log("Opening ride details for ride:", ride);
    
    // Get the modal element
    const modal = document.getElementById('ride-details-modal');
    if (!modal) {
        console.error("Ride details modal not found in the DOM");
        return;
    }
    
    // Populate the modal with ride data
    document.getElementById('ride-details-title').textContent = 'Ride Details #' + ride.id;
    document.getElementById('ride-details-date').textContent = ride.formatted_date || ride.date || 'Unknown date';
    document.getElementById('ride-details-time').textContent = ride.formatted_time || ride.time || 'Unknown time';
    document.getElementById('ride-details-pickup').textContent = ride.pickup || 'Unknown pickup location';
    document.getElementById('ride-details-dropoff').textContent = ride.dropoff || 'Unknown dropoff location';
    document.getElementById('ride-details-fare').textContent = ride.formatted_fare || ride.fare || 'Unknown fare';
    document.getElementById('ride-details-vehicle-type').textContent = ride.vehicle_type || 'Standard';
    
    // Set the status badge
    const statusBadge = document.getElementById('ride-details-status-badge');
    if (statusBadge) {
        // Set appropriate styling based on status
        const statusText = ride.status.charAt(0).toUpperCase() + ride.status.slice(1);
        
        // Clear existing classes and set new ones
        statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';
        
        // Add appropriate classes based on status
        switch (ride.status.toLowerCase()) {
            case 'completed':
                statusBadge.classList.add('bg-green-600/20', 'text-green-300', 'border', 'border-green-500/30');
                break;
            case 'cancelled':
            case 'canceled':
                statusBadge.classList.add('bg-red-600/20', 'text-red-300', 'border', 'border-red-500/30');
                break;
            case 'in_progress':
                statusBadge.classList.add('bg-blue-600/20', 'text-blue-300', 'border', 'border-blue-500/30');
                break;
            case 'arrived':
                statusBadge.classList.add('bg-purple-600/20', 'text-purple-300', 'border', 'border-purple-500/30');
                break;
            case 'arriving':
                statusBadge.classList.add('bg-indigo-600/20', 'text-indigo-300', 'border', 'border-indigo-500/30');
                break;
            case 'confirmed':
                statusBadge.classList.add('bg-cyan-600/20', 'text-cyan-300', 'border', 'border-cyan-500/30');
                break;
            case 'searching':
                statusBadge.classList.add('bg-yellow-600/20', 'text-yellow-300', 'border', 'border-yellow-500/30');
                break;
            default:
                statusBadge.classList.add('bg-gray-600/20', 'text-gray-300', 'border', 'border-gray-500/30');
                break;
        }
        
        statusBadge.textContent = statusText;
    }
    
    // Set driver information if available
    const driverNameEl = document.getElementById('details-driver-name');
    const driverRatingEl = document.getElementById('details-driver-rating');
    const driverVehicleEl = document.getElementById('details-driver-vehicle');
    const driverPlateEl = document.getElementById('details-driver-plate');
    const driverPhoneEl = document.getElementById('details-driver-phone');
    
    if (driverNameEl) driverNameEl.textContent = ride.driver_name || 'No driver assigned';
    if (driverRatingEl) driverRatingEl.textContent = ride.driver_rating || '---';
    if (driverVehicleEl) driverVehicleEl.textContent = ride.driver_vehicle || '---';
    if (driverPlateEl) driverPlateEl.textContent = ride.driver_plate || '---';
    if (driverPhoneEl) driverPhoneEl.textContent = ride.driver_phone || '---';
    
    // Show the modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Add animation to the modal content
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.classList.remove('animate-slide-down');
        modalContent.classList.add('animate-slide-up');
    }
}

/**
 * Close the ride details modal
 */
function closeRideDetailsModal() {
    const modal = document.getElementById('ride-details-modal');
    if (!modal) return;
    
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.classList.remove('animate-slide-up');
        modalContent.classList.add('animate-slide-down');
        
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            if (modalContent) {
                modalContent.classList.remove('animate-slide-down');
            }
        }, 300);
    } else {
        modal.style.display = 'none';
        document.body.style.overflow = ''; 
    }
}

/**
 * Get detailed information about a specific ride
 * @param {number} rideId - The ID of the ride to fetch details for
 * @returns {Promise} A promise that resolves with the ride details
 */
function fetchRideDetails(rideId) {
    return fetch(`api/api-ride-details.php?id=${rideId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include' // Include cookies for authentication
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error fetching ride details: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to get ride details');
        }
        return data.data.ride;
    });
}

/**
 * Enhanced function to update the ride history UI with clickable items
 */
function updateRideHistoryUI(data) {
    console.log("Updating ride history UI", data);
    // Find the main container for the rides tab content
    const ridesContainer = document.querySelector('#rides-tab-content');
    if (!ridesContainer) {
        console.warn("Rides tab content container not found.");
        return;
    }

    // Clear existing content (filters, list, pagination)
    ridesContainer.innerHTML = '';

    // --- 1. Create Filter Options ---
    const filterOptions = document.createElement('div');
    filterOptions.className = 'mb-4 flex flex-wrap gap-2 pb-2'; // Use flex-wrap for smaller screens
    const currentFilter = data.filter || 'all'; // Default to 'all' if not provided
    const filters = [
        { key: 'all', label: 'All' },
        { key: 'active', label: 'Active' },
        { key: 'month', label: 'Last 30 Days' },
        { key: 'completed', label: 'Completed' },
        { key: 'canceled', label: 'Canceled' }
    ];

    filters.forEach(f => {
        const isActive = f.key === currentFilter;
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.filter = f.key;
        // Dynamic classes based on active state
        button.className = `py-1 px-3 rounded-full border text-sm transition duration-200 ${
            isActive
                ? 'active bg-primary-600 border-primary-500 text-white font-medium cursor-default' // Active style
                : 'bg-gray-700 border-gray-600 text-gray-300 hover:bg-gray-600 hover:border-gray-500 hover:text-white' // Inactive style
        }`;
        button.textContent = f.label;
        button.disabled = isActive; // Disable the currently active filter button

        // Add event listener to load history with the new filter
        button.addEventListener('click', () => {
            loadRideHistory(1, f.key); // Load page 1 with the selected filter
        });
        filterOptions.appendChild(button);
    });

    ridesContainer.appendChild(filterOptions); // Add filter buttons to the container

    // --- 2. Display Rides List or Empty State ---
    if (data.rides && data.rides.length > 0) {
        const ridesList = document.createElement('div');
        ridesList.className = 'space-y-4'; // Spacing between ride items

        data.rides.forEach(ride => {
            // Get the UI element for the ride status
            const rideStatusUI = getRideStatusUI(ride.status);

            const rideItem = document.createElement('div');
            // Add cursor-pointer to indicate clickability
            rideItem.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 relative cursor-pointer hover:bg-gray-600/50 transition-colors duration-200';
            // Add data attributes for reference in click handlers
            rideItem.dataset.rideId = ride.id;
            
            // Add highlight for active rides
            if (ride.is_active) {
                rideItem.classList.add('border-primary-500');
            }

            // Inner HTML for the ride item
            rideItem.innerHTML = `
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap gap-x-3 gap-y-1 items-center mb-2">
                            <span class="text-sm font-medium text-white whitespace-nowrap">${ride.formatted_date || 'N/A'}</span>
                            <span class="text-sm text-gray-400 whitespace-nowrap">${ride.formatted_time || ''}</span>
                            ${rideStatusUI}
                        </div>
                        <div class="mb-1">
                            <p class="text-gray-300 text-sm truncate" title="${ride.pickup || ''}">
                                <span class="lucide text-xs mr-1 text-green-400" aria-hidden="true">&#xea4b;</span> ${ride.pickup || 'Pickup location not available'}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-300 text-sm truncate" title="${ride.dropoff || ''}">
                                <span class="lucide text-xs mr-1 text-red-400" aria-hidden="true">&#xea4a;</span> ${ride.dropoff || 'Dropoff location not available'}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-start md:items-end mt-2 md:mt-0 flex-shrink-0">
                        <div class="text-lg font-medium text-white mb-1">${ride.formatted_fare || ride.fare || 'N/A'}</div>
                        ${ride.driver_name ? `<div class="text-sm text-gray-400">Driver: ${ride.driver_name}</div>` : ''}
                        ${ride.vehicle_type ? `<div class="text-xs text-gray-500">${ride.vehicle_type}</div>` : ''}
                    </div>
                </div>
                
                <button class="view-details-btn absolute right-3 top-3 text-gray-400 hover:text-primary-400 transition duration-200 text-sm px-2 py-1 rounded opacity-50 hover:opacity-100" title="View ride details">
                    <span class="lucide text-sm" aria-hidden="true">&#xea70;</span> Details
                </button>
            `;

            // Add click event listener to view details
            rideItem.addEventListener('click', () => {
                // First try to fetch detailed info from the server
                fetchRideDetails(ride.id)
                    .then(detailedRide => {
                        console.log("Fetched detailed ride info:", detailedRide);
                        openRideDetails(detailedRide);
                    })
                    .catch(error => {
                        console.warn("Could not fetch detailed ride info:", error);
                        // Fallback to using the available information
                        openRideDetails(ride);
                    });
            });

            ridesList.appendChild(rideItem); // Add the ride item to the list
        });

        ridesContainer.appendChild(ridesList); // Add the list to the main container
    } else {
        // Show empty state
        const emptyState = document.createElement('div');
        emptyState.className = 'text-center py-8 text-gray-400';
        let emptyMessage = `<p>No rides found matching the filter '${currentFilter}'.</p>`;
        if (currentFilter === 'all') {
            emptyMessage = `
                <span class="lucide text-4xl mb-2 block mx-auto">&#xea5e;</span>
                <p>You don't have any ride history yet.</p>
                <p class="mt-2">Book your first ride on the <a href="index.php" class="text-primary-400 hover:text-primary-300 underline">home page</a>!</p>
            `;
        }
        emptyState.innerHTML = emptyMessage;
        ridesContainer.appendChild(emptyState);
    }

    // --- 3. Add Pagination Controls if needed ---
    if (data.pagination && data.pagination.total_pages > 1) {
        const pagination = document.createElement('div');
        pagination.className = 'mt-6 flex justify-center items-center gap-2';
        const currentPage = data.pagination.current_page;
        const totalPages = data.pagination.total_pages;

        // Previous Page Button
        if (currentPage > 1) {
            const prevButton = document.createElement('button');
            prevButton.className = 'p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 focus:ring-offset-gray-800';
            prevButton.innerHTML = '<span class="lucide pointer-events-none" aria-hidden="true">&#xeaa2;</span>'; // Chevron Left
            prevButton.setAttribute('aria-label', 'Previous Page');
            prevButton.addEventListener('click', () => {
                loadRideHistory(currentPage - 1, currentFilter);
            });
            pagination.appendChild(prevButton);
        } else {
             // Disabled previous button
             const disabledPrev = document.createElement('span');
             disabledPrev.className = 'p-2 rounded-lg bg-gray-800 text-gray-600 cursor-not-allowed';
             disabledPrev.innerHTML = '<span class="lucide" aria-hidden="true">&#xeaa2;</span>';
             pagination.appendChild(disabledPrev);
        }

        // Page Number Info
        const pageInfo = document.createElement('span');
        pageInfo.className = 'text-gray-400 text-sm pagination-current-page';
        pageInfo.textContent = `${currentPage}`;
        pageInfo.setAttribute('aria-live', 'polite'); // Announce page changes
        
        // Page info with surrounding text
        const pageInfoWrapper = document.createElement('span');
        pageInfoWrapper.className = 'text-gray-400 text-sm';
        pageInfoWrapper.appendChild(document.createTextNode('Page '));
        pageInfoWrapper.appendChild(pageInfo);
        pageInfoWrapper.appendChild(document.createTextNode(` of ${totalPages}`));
        
        pagination.appendChild(pageInfoWrapper);

        // Next Page Button
        if (currentPage < totalPages) {
            const nextButton = document.createElement('button');
            nextButton.className = 'p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 focus:ring-offset-gray-800';
            nextButton.innerHTML = '<span class="lucide pointer-events-none" aria-hidden="true">&#xeaa0;</span>'; // Chevron Right
            nextButton.setAttribute('aria-label', 'Next Page');
            nextButton.addEventListener('click', () => {
                loadRideHistory(currentPage + 1, currentFilter);
            });
            pagination.appendChild(nextButton);
        } else {
             // Disabled next button
             const disabledNext = document.createElement('span');
             disabledNext.className = 'p-2 rounded-lg bg-gray-800 text-gray-600 cursor-not-allowed';
             disabledNext.innerHTML = '<span class="lucide" aria-hidden="true">&#xeaa0;</span>';
             pagination.appendChild(disabledNext);
        }

        ridesContainer.appendChild(pagination); // Add pagination controls to the container
    }

    // Setup modal close button event (only once)
    setupRideDetailsModalEvents();
}

/**
 * Set up event listeners for the ride details modal
 * Called only once
 */
function setupRideDetailsModalEvents() {
    // Check if we've already set this up
    if (window.rideDetailsModalEventsSet) return;
    
    const modal = document.getElementById('ride-details-modal');
    if (!modal) return;
    
    // Get close button and overlay
    const closeBtn = modal.querySelector('.modal-close-btn');
    const overlay = document.getElementById('ride-details-modal-overlay');
    
    // Add click event to close button
    if (closeBtn) {
        closeBtn.addEventListener('click', closeRideDetailsModal);
    }
    
    // Add click event to overlay for closing on background click
    if (overlay) {
        overlay.addEventListener('click', closeRideDetailsModal);
    }
    
    // Add escape key handler
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeRideDetailsModal();
        }
    });
    
    // Mark as set up
    window.rideDetailsModalEventsSet = true;
}

// Add these event listeners to the DOMContentLoaded handler
document.addEventListener('DOMContentLoaded', function() {
    // ... (other code)
    
    // Set up ride details modal events
    setupRideDetailsModalEvents();
});

/**
 * Performs a server-side check to verify authentication status and fetch user data.
 */
function serverAuthCheck() {
    console.log("Performing server auth check");

    // Use absolute path for the API endpoint
    fetch(window.location.origin + '/api/api-auth.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest' // Often used to identify AJAX requests
        },
        credentials: 'include' // Important: Send cookies with the request
    })
    .then(response => {
        console.log("Auth response status:", response.status);
        if (!response.ok) {
            // If response is not OK (e.g., 401, 500), throw an error
            throw new Error('Server returned ' + response.status);
        }
        return response.json(); // Parse the JSON response body
    })
    .then(data => {
        console.log("Auth response data:", data);
        if (data.authenticated) {
            // If the server confirms authentication
            isLoggedIn = true;
            currentUser = data.user; // Store user data
            // Update localStorage with fresh data from the server
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            console.log("Set user data from server:", currentUser);
            updateUIWithUserData(); // Update the UI
        } else {
            // If the server says the user is not authenticated
            console.log("Server says not authenticated");
            isLoggedIn = false;
            currentUser = null;
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            redirectToHome(); // Redirect to the home page
        }
    })
    .catch(error => {
        console.error('Error checking authentication:', error);
        // If the server check fails (e.g., network error)
        // Fallback: If we previously determined the user was logged in via localStorage, trust that.
        if (isLoggedIn && currentUser) {
            console.warn("Using cached user data despite server auth error");
            updateUIWithUserData();
        } else {
            // If no cached data exists either, redirect to home
            isLoggedIn = false;
            currentUser = null;
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            redirectToHome();
        }
    });
}

/**
 * Redirects the user to the index.php page.
 */
function redirectToHome() {
    console.log("User not logged in or session invalid, redirecting to home...");
    // Prevent redirection loops if already on index.php
    if (!window.location.pathname.endsWith('index.php')) {
       window.location.href = "index.php";
    } else {
       console.log("Already on index.php, not redirecting.");
       // Potentially hide dashboard elements if on index.php but shouldn't see them
    }
}

/**
 * Updates various parts of the UI with the current user's data.
 */
function updateUIWithUserData() {
    console.log("Updating UI with user data:", currentUser);
    if (!currentUser) {
        console.warn("Attempted to update UI without user data.");
        return; // Exit if no user data
    }

    // Update elements displaying the user's name
    const userDisplayNames = document.querySelectorAll('.user-display-name');
    userDisplayNames.forEach(el => {
        el.textContent = currentUser.name || currentUser.email || 'User'; // Fallback display name
    });

    fillProfileForm(); // Populate the profile form
    loadUserData(); // Load other user-specific data like rides, places, etc.
}

/**
 * Fills the profile form fields with the current user's details.
 */
function fillProfileForm() {
    console.log("Filling profile form with user data");
    if (currentUser) {
        // Get references to form elements
        const nameInput = document.getElementById('profile-name');
        const emailInput = document.getElementById('profile-email');
        const phoneInput = document.getElementById('profile-phone');
        const languageSelect = document.getElementById('profile-language');

        // Set values if elements exist
        if (nameInput) nameInput.value = currentUser.name || '';
        if (emailInput) emailInput.value = currentUser.email || '';
        if (phoneInput) phoneInput.value = currentUser.phone || '';
        if (languageSelect && currentUser.language) {
            languageSelect.value = currentUser.language;
        }

        // Load user notification preferences if they exist
        if (currentUser.preferences) {
            const notifyEmail = document.getElementById('notify-email');
            const notifySms = document.getElementById('notify-sms');
            const notifyPromotions = document.getElementById('notify-promotions');

            if (notifyEmail) notifyEmail.checked = !!currentUser.preferences.notify_email;
            if (notifySms) notifySms.checked = !!currentUser.preferences.notify_sms;
            if (notifyPromotions) notifyPromotions.checked = !!currentUser.preferences.notify_promotions;
        }
    } else {
        console.warn("Cannot fill profile form, currentUser is null.");
    }
}

/**
 * Initiates loading of various user-specific data sections in parallel.
 */
function loadUserData() {
    console.log("Loading user data from server");
    if (!isLoggedIn) return;
    
    // Show loading indicator if available
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator();
    }
    
    // Load all user data in parallel
    const promises = [
        loadRewardPoints(),
        loadSavedPlaces(),
        loadRideHistory(),
        loadPaymentMethods()
    ];
    
    // Wait for all data to be loaded
    Promise.allSettled(promises)
        .then(results => {
            console.log("All data loading complete:", results);
            
            // Check for any rejected promises and log them
            results.forEach((result, index) => {
                if (result.status === 'rejected') {
                    console.error(`Promise ${index} failed:`, result.reason);
                }
            });
            
            // Hide loading indicator if available
            if (typeof hideLoadingIndicator === 'function') {
                hideLoadingIndicator();
            }
        })
        .catch(error => {
            console.error("Error loading user data:", error);
            
            // Hide loading indicator if available
            if (typeof hideLoadingIndicator === 'function') {
                hideLoadingIndicator();
            }
        });
}

/**
 * Fetches the user's reward points and related rewards/history from the server.
 * @returns {Promise} A promise that resolves with the reward points data or rejects on error.
 */
function loadRewardPoints() {
    console.log("Loading reward points");
    return fetch(window.location.origin + '/api/api-reward-points.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include' // Include cookies
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Reward points data:", data);
        if (data.success) {
            // Update points display
            const pointsElement = document.querySelector('.text-3xl.font-bold.text-white.mb-1');
            if (pointsElement) {
                pointsElement.textContent = data.data.points.toLocaleString();
            }
            
            // Update rewards list if available
            if (data.data.rewards && data.data.rewards.length > 0) {
                updateRewardsList(data.data.rewards);
            }
            
            return data;
        } else {
            throw new Error(data.message || 'Failed to load reward points');
        }
    })
    .catch(error => {
        console.error('Error loading reward points:', error);
        throw error;
    });
}

/**
 * Updates the UI to display the available rewards.
 * @param {Array} rewards - An array of reward objects.
 */
function updateRewardsList(rewards) {
    console.log("Updating rewards list UI", rewards);
    // Find the container for the rewards list
    const rewardsContainer = document.querySelector('#rewards-tab-content .rewards-list-container'); // More specific selector
    if (!rewardsContainer) {
        console.warn("Rewards list container not found.");
        return;
    }

    // Clear any existing rewards from the list
    rewardsContainer.innerHTML = '';

    if (!rewards || rewards.length === 0) {
        rewardsContainer.innerHTML = '<p class="text-gray-400 text-center py-4">No rewards available currently.</p>';
        return;
    }

    // Add each reward to the list
    rewards.forEach(reward => {
        // Check if the user has enough points to redeem this reward
        const userPoints = currentUser?.reward_points ?? 0; // Use nullish coalescing for safety
        const isAvailable = userPoints >= reward.points;

        const rewardElement = document.createElement('div');
        rewardElement.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-3'; // Added gap

        // Inner HTML for the reward item
        rewardElement.innerHTML = `
            <div class="flex-1">
                <h4 class="font-medium text-white">${reward.title || 'Unnamed Reward'}</h4>
                <p class="text-gray-400 text-sm mt-1">${reward.description || 'No description.'}</p>
            </div>
            <div class="mt-3 md:mt-0 flex items-center flex-shrink-0"> <span class="text-yellow-400 font-semibold mr-3">${reward.points?.toLocaleString() || '?'} points</span>
                <button
                    class="redeem-reward-btn bg-primary-500 hover:bg-primary-600 text-white font-medium py-1 px-4 rounded-lg transition duration-300 shadow-md text-sm ${!isAvailable ? 'opacity-50 cursor-not-allowed' : 'hover:scale-105'}"
                    data-reward-id="${reward.id}"
                    ${!isAvailable ? 'disabled title="Not enough points"' : 'title="Redeem this reward"'}
                >
                    Redeem
                </button>
            </div>
        `;

        // Add event listener to the redeem button only if it's available
        if (isAvailable) {
            const redeemButton = rewardElement.querySelector('.redeem-reward-btn');
            if (redeemButton) {
                redeemButton.addEventListener('click', () => {
                    redeemReward(reward.id); // Call the redeem function with the reward ID
                });
            }
        }

        rewardsContainer.appendChild(rewardElement); // Add the new element to the container
    });
}

/**
 * Handles the process of redeeming a specific reward.
 * @param {string|number} rewardId - The ID of the reward to redeem.
 */
function redeemReward(rewardId) {
    console.log("Attempting to redeem reward:", rewardId);

    // Optional: Add a confirmation step
    // if (!confirm("Are you sure you want to redeem this reward?")) {
    //     return;
    // }

    // Show loading indicator
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator();
    }

    // Make the API call to redeem the reward
    fetch(window.location.origin + '/api/api-auth.php?endpoint=redeem-reward', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            reward_id: rewardId // Send the reward ID in the request body
        }),
        credentials: 'include' // Send cookies
    })
    .then(response => response.json()) // Parse the JSON response
    .then(data => {
        console.log("Redeem reward response:", data);
        if (data.success) {
            // If redemption is successful
            loadRewardPoints(); // Reload points and rewards list to reflect changes

            // Show a success message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Reward redeemed successfully!');
            }
        } else {
            // If redemption fails, show an error message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Failed to redeem reward. You may not have enough points.', true); // Mark as error
            }
        }
    })
    .catch(error => {
        console.error('Error redeeming reward:', error);
        // Show a generic error message in case of network or server issues
        if (typeof showConfirmation === 'function') {
            showConfirmation('An error occurred while redeeming the reward. Please try again.', true);
        }
    })
    .finally(() => {
        // Hide loading indicator regardless of outcome
        if (typeof hideLoadingIndicator === 'function') {
            hideLoadingIndicator();
        }
    });
}

/**
 * Updates the UI to display the user's redeemed rewards history.
 * @param {Array} redeemedRewards - An array of redeemed reward objects.
 */
function updateRedemptionHistory(redeemedRewards) {
    console.log("Updating redemption history UI:", redeemedRewards);
    const historyContainer = document.querySelector('#rewards-tab-content .redemption-history-container'); // Specific selector
    if (!historyContainer) {
        console.warn("Redemption history container not found.");
        return;
    }

    historyContainer.innerHTML = ''; // Clear previous history

    if (!redeemedRewards || redeemedRewards.length === 0) {
        historyContainer.innerHTML = '<p class="text-gray-400 text-center py-4">You haven\'t redeemed any rewards yet.</p>';
        return;
    }

    // Create a list to hold history items
    const historyList = document.createElement('ul');
    historyList.className = 'space-y-3'; // Add some spacing

    redeemedRewards.forEach(item => {
        const listItem = document.createElement('li');
        listItem.className = 'bg-gray-700/30 rounded-md p-3 border border-gray-600/50 flex justify-between items-center text-sm';

        // Format date nicely if available
        let formattedDate = 'Date unknown';
        if (item.date) {
            try {
                formattedDate = new Date(item.date).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            } catch (e) { console.warn("Could not parse redemption date:", item.date); }
        }

        listItem.innerHTML = `
            <div>
                <span class="font-medium text-white">${item.title || 'Reward Redeemed'}</span>
                <span class="text-gray-400 ml-2">(${item.points || '?'} points)</span>
            </div>
            <span class="text-gray-400">${formattedDate}</span>
        `;
        historyList.appendChild(listItem);
    });

    historyContainer.appendChild(historyList);
}


/**
 * Fetches the user's saved places from the server.
 * @returns {Promise} A promise that resolves with saved places data or rejects on error.
 */
function loadSavedPlaces() {
    console.log("Loading saved places");
    return fetch(window.location.origin + '/api/api-saved-places.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include' // Send cookies
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Server returned ' + response.status + ' while fetching saved places');
        }
        return response.json();
    })
    .then(data => {
        console.log("Saved places data received:", data);
        if (data.success && data.data) {
            // Update the UI with the fetched places
             if (typeof updateSavedPlacesUI === 'function') {
                updateSavedPlacesUI(data.data.places || []); // Ensure it's an array
             }
            return data; // Return fetched data
        } else {
            console.warn("Saved places API call did not indicate success or returned no data:", data.message);
             if (typeof updateSavedPlacesUI === 'function') {
                updateSavedPlacesUI([]); // Update UI with empty list
             }
            throw new Error(data.message || 'Failed to load saved places.');
        }
    })
    .catch(error => {
        console.error('Error loading saved places:', error);
        // Update UI to show an error state or empty list
        if (typeof updateSavedPlacesUI === 'function') {
             updateSavedPlacesUI([]); // Show empty list on error
        }
        throw error; // Re-throw
    });
}

/**
 * Updates the UI to display the list of saved places and the add place form.
 * @param {Array} places - An array of saved place objects.
 */
function updateSavedPlacesUI(places) {
    console.log("Updating saved places UI", places);
    // Find the main container for the places tab content
    const placesContainer = document.querySelector('#places-tab-content');
    if (!placesContainer) {
        console.warn("Places tab content container not found.");
        return;
    }

    // Find or create the wrapper for the list of saved places
    let placesWrapper = placesContainer.querySelector('.saved-places-list-wrapper');
    if (!placesWrapper) {
        placesWrapper = document.createElement('div');
        placesWrapper.className = 'saved-places-list-wrapper space-y-4 mb-6'; // Added margin-bottom
        // Prepend the list wrapper before the form (if form exists) or just append
        const form = placesContainer.querySelector('.add-place-form-wrapper');
        if (form) {
            placesContainer.insertBefore(placesWrapper, form);
        } else {
            placesContainer.appendChild(placesWrapper);
        }
    }

    // Clear existing places from the list
    placesWrapper.innerHTML = '';

    // Check if there are any places to display
    if (places && places.length > 0) {
        // Add each place to the list
        places.forEach(place => {
            const placeElement = document.createElement('div');
            placeElement.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-3'; // Added gap
            placeElement.dataset.placeId = place.id; // Store ID for potential actions

            // Inner HTML for the place item
            placeElement.innerHTML = `
                <div class="flex-1 min-w-0"> <h4 class="font-medium text-white truncate" title="${place.name || ''}">${place.name || 'Unnamed Place'}</h4>
                    <p class="text-gray-400 text-sm mt-1 truncate" title="${place.address || ''}">${place.address || 'No address'}</p>
                </div>
                <div class="mt-3 md:mt-0 flex items-center space-x-2 flex-shrink-0"> <button class="edit-place-btn p-1.5 rounded-lg bg-gray-600 hover:bg-primary-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 focus:ring-offset-gray-800" title="Edit Place">
                        <span class="lucide text-lg pointer-events-none" aria-hidden="true">&#xea71;</span> </button>
                    <button class="delete-place-btn p-1.5 rounded-lg bg-gray-600 hover:bg-red-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 focus:ring-offset-gray-800" title="Delete Place">
                        <span class="lucide text-lg pointer-events-none" aria-hidden="true">&#xea0f;</span> </button>
                </div>
            `;

            // Add event listeners for edit and delete buttons
            const editButton = placeElement.querySelector('.edit-place-btn');
            const deleteButton = placeElement.querySelector('.delete-place-btn');

            if (editButton) {
                editButton.addEventListener('click', () => {
                    // Call edit function, passing the full place object
                    if (typeof editSavedPlace === 'function') {
                        editSavedPlace(place);
                    } else {
                        console.error("editSavedPlace function is not defined.");
                    }
                });
            }

            if (deleteButton) {
                deleteButton.addEventListener('click', () => {
                    // Call delete function, passing ID and name for confirmation
                     if (typeof deleteSavedPlace === 'function') {
                        deleteSavedPlace(place.id, place.name);
                     } else {
                         console.error("deleteSavedPlace function is not defined.");
                     }
                });
            }

            placesWrapper.appendChild(placeElement); // Add the place item to the list
        });
    } else {
        // Show an empty state message if no places are saved
        placesWrapper.innerHTML = `
            <div class="text-center py-6 text-gray-400">
                <span class="lucide text-4xl mb-2 block mx-auto">&#xea48;</span> <p>You haven't saved any places yet.</p>
                <p class="mt-1 text-sm">Add places like 'Home' or 'Work' below for faster booking.</p>
            </div>
        `;
    }

    // Ensure the 'Add Place' form is present and correctly set up
    ensureAddPlaceForm(placesContainer);
}


/**
 * Ensures the 'Add New Place' form exists in the specified container and sets up its submit handler.
 * @param {HTMLElement} container - The parent element where the form should reside.
 */
function ensureAddPlaceForm(container) {
    // Check if the form wrapper already exists
    let formWrapper = container.querySelector('.add-place-form-wrapper');

    if (!formWrapper) {
        // Create the wrapper div for the form
        formWrapper = document.createElement('div');
        formWrapper.className = 'add-place-form-wrapper mt-6 pt-6 border-t border-gray-700';

        // Get CSRF token if available (important for security)
        const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
        const csrfTokenValue = csrfTokenInput ? csrfTokenInput.value : '';

        // Inner HTML for the form
        formWrapper.innerHTML = `
            <h3 class="text-lg font-medium text-white mb-4">Add a New Place</h3>
            <form id="add-place-form" class="space-y-4">
                ${csrfTokenValue ? `<input type="hidden" name="csrf_token" value="${csrfTokenValue}">` : ''}
                <div>
                    <label for="place-name" class="block text-sm font-medium text-gray-300 mb-1">Place Name (e.g., Home, Work)</label>
                    <input type="text" id="place-name" name="name" required placeholder="Enter a name" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                </div>
                <div>
                    <label for="place-address" class="block text-sm font-medium text-gray-300 mb-1">Address</label>
                    <input type="text" id="place-address" name="address" required placeholder="Enter the full address" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                    </div>
                <div>
                    <button type="submit" class="inline-flex items-center bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                        <span class="lucide mr-1.5 text-base" aria-hidden="true">&#xea9a;</span> Add Place
                    </button>
                </div>
            </form>
        `;

        // Append the form wrapper to the main container
        container.appendChild(formWrapper);

        // Add event listener to the newly created form
        const form = formWrapper.querySelector('#add-place-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Get trimmed values from the form fields
                const nameInput = form.querySelector('#place-name');
                const addressInput = form.querySelector('#place-address');
                const name = nameInput.value.trim();
                const address = addressInput.value.trim();

                // Basic validation
                if (!name || !address) {
                    if (typeof showConfirmation === 'function') {
                        showConfirmation('Please enter both a name and an address for the place.', true); // Show error
                    } else {
                        alert('Please enter both a name and an address for the place.');
                    }
                    // Optionally add visual feedback to the inputs
                    if (!name) nameInput.classList.add('border-red-500'); else nameInput.classList.remove('border-red-500');
                    if (!address) addressInput.classList.add('border-red-500'); else addressInput.classList.remove('border-red-500');
                    return;
                }
                 // Remove validation styles if previously added
                 nameInput.classList.remove('border-red-500');
                 addressInput.classList.remove('border-red-500');

                // Call the function to handle adding the place via API
                if (typeof addSavedPlace === 'function') {
                    addSavedPlace(name, address);
                } else {
                     console.error("addSavedPlace function is not defined.");
                }
            });
        }
    } else {
         // If form wrapper exists, ensure the CSRF token is up-to-date (if applicable)
         const existingCsrfInput = formWrapper.querySelector('input[name="csrf_token"]');
         const currentCsrfToken = document.querySelector('input[name="csrf_token"]')?.value;
         if (existingCsrfInput && currentCsrfToken && existingCsrfInput.value !== currentCsrfToken) {
             existingCsrfInput.value = currentCsrfToken;
             console.log("Updated CSRF token in add place form.");
         }
    }
}


/**
 * Sends a request to the server to add a new saved place.
 * @param {string} name - The name of the place.
 * @param {string} address - The address of the place.
 */
function addSavedPlace(name, address) {
    console.log("Adding saved place:", { name, address });
    // Show loading indicator
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator();
    }

    // Get CSRF token from the form (more reliable than querying the whole document)
    const form = document.getElementById('add-place-form');
    const csrfToken = form ? form.querySelector('input[name="csrf_token"]')?.value || '' : '';

    // Make the API call to add the place
    fetch(window.location.origin + '/api/api-saved-places.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            name: name,
            address: address,
            csrf_token: csrfToken // Include CSRF token if available
        }),
        credentials: 'include' // Send cookies
    })
    .then(response => response.json()) // Parse the JSON response
    .then(data => {
        console.log("Add place response:", data);
        if (data.success) {
            // If successful, clear the form fields
            const nameInput = document.getElementById('place-name');
            const addressInput = document.getElementById('place-address');
            if (nameInput) nameInput.value = '';
            if (addressInput) addressInput.value = '';

            // Reload the list of saved places to show the new one
            loadSavedPlaces();

            // Show a success message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Place added successfully!');
            }
        } else {
            // If adding failed, show an error message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Failed to add place. Please check the details and try again.', true);
            }
        }
    })
    .catch(error => {
        console.error('Error adding saved place:', error);
        // Show a generic error message
        if (typeof showConfirmation === 'function') {
            showConfirmation('An error occurred while adding the place. Please try again later.', true);
        }
    })
    .finally(() => {
        // Hide loading indicator
        if (typeof hideLoadingIndicator === 'function') {
            hideLoadingIndicator();
        }
    });
}

/**
 * Opens a modal dialog to edit an existing saved place.
 * @param {object} place - The place object containing id, name, and address.
 */
function editSavedPlace(place) {
    console.log("Editing place:", place);
    if (!place || !place.id) {
        console.error("Invalid place data provided for editing.");
        return;
    }

    // Find or create the modal element
    let editModal = document.getElementById('edit-place-modal');

    if (!editModal) {
        editModal = document.createElement('div');
        editModal.id = 'edit-place-modal';
        // Use fixed positioning, z-index, and flex for centering
        editModal.className = 'fixed inset-0 z-50 flex items-center justify-center hidden p-4'; // Added padding
        editModal.setAttribute('role', 'dialog');
        editModal.setAttribute('aria-modal', 'true');
        editModal.setAttribute('aria-labelledby', 'edit-place-modal-title');

        // Get current CSRF token
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        // Modal structure: overlay and content
        editModal.innerHTML = `
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="edit-place-modal-overlay" aria-hidden="true"></div>

            <div class="modal-content bg-gray-800 rounded-xl shadow-2xl border border-gray-700 max-w-md w-full mx-auto relative z-10 animate-slide-up p-6 sm:p-8">
                <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-full p-1" aria-label="Close modal">
                    <span class="lucide text-xl" aria-hidden="true">&#xea76;</span> </button>

                <h2 id="edit-place-modal-title" class="text-xl sm:text-2xl font-semibold text-white mb-6">Edit Saved Place</h2>

                <form id="edit-place-form" class="space-y-4">
                    <input type="hidden" id="edit-place-id" name="id">
                    ${csrfToken ? `<input type="hidden" name="csrf_token" value="${csrfToken}">` : ''}
                    <div>
                        <label for="edit-place-name" class="block text-sm font-medium text-gray-300 mb-1">Place Name</label>
                        <input type="text" id="edit-place-name" name="name" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                    </div>
                    <div>
                        <label for="edit-place-address" class="block text-sm font-medium text-gray-300 mb-1">Address</label>
                        <input type="text" id="edit-place-address" name="address" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition">
                    </div>
                    <div class="pt-4">
                        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        `;

        // Append the modal to the body
        document.body.appendChild(editModal);

        // Add event listeners (only once when modal is created)
        const closeBtn = editModal.querySelector('.modal-close-btn');
        const overlay = editModal.querySelector('#edit-place-modal-overlay');
        const form = editModal.querySelector('#edit-place-form');

        // Close modal actions
        const closeModalHandler = () => closeEditModal();
        closeBtn.addEventListener('click', closeModalHandler);
        overlay.addEventListener('click', closeModalHandler); // Close on overlay click

        // Handle form submission
        form.addEventListener('submit', (e) => {
            e.preventDefault(); // Prevent default submission

            // Get values from the edit form
            const idInput = form.querySelector('#edit-place-id');
            const nameInput = form.querySelector('#edit-place-name');
            const addressInput = form.querySelector('#edit-place-address');

            const id = idInput.value;
            const name = nameInput.value.trim();
            const address = addressInput.value.trim();

            // Basic validation
            if (!name || !address) {
                if (typeof showConfirmation === 'function') {
                    showConfirmation('Please fill in all fields.', true);
                } else {
                    alert('Please fill in all fields.');
                }
                 if (!name) nameInput.classList.add('border-red-500'); else nameInput.classList.remove('border-red-500');
                 if (!address) addressInput.classList.add('border-red-500'); else addressInput.classList.remove('border-red-500');
                return;
            }
             nameInput.classList.remove('border-red-500');
             addressInput.classList.remove('border-red-500');

            // Call the function to update the place via API
            if (typeof updateSavedPlace === 'function') {
                updateSavedPlace(id, name, address);
            } else {
                 console.error("updateSavedPlace function is not defined.");
            }
        });

        // Add keyboard accessibility (close on Escape key)
        editModal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    }

    // --- Populate the form with the specific place data ---
    const idInput = editModal.querySelector('#edit-place-id');
    const nameInput = editModal.querySelector('#edit-place-name');
    const addressInput = editModal.querySelector('#edit-place-address');
    const csrfInput = editModal.querySelector('input[name="csrf_token"]');

    if (idInput) idInput.value = place.id;
    if (nameInput) nameInput.value = place.name;
    if (addressInput) addressInput.value = place.address;

    // Update CSRF token in the modal form just in case it changed
     const currentCsrfToken = document.querySelector('input[name="csrf_token"]')?.value;
     if (csrfInput && currentCsrfToken) {
         csrfInput.value = currentCsrfToken;
     }

    // --- Show the modal ---
    editModal.classList.remove('hidden'); // Remove 'hidden' to display
    document.body.style.overflow = 'hidden'; // Prevent background scrolling

    // Focus the first input field for accessibility
    if (nameInput) {
        nameInput.focus();
    }
}


/**
 * Closes the edit saved place modal with an animation.
 */
function closeEditModal() {
    const modal = document.getElementById('edit-place-modal');
    if (!modal || modal.classList.contains('hidden')) {
        return; // Modal not found or already hidden
    }

    const modalContent = modal.querySelector('.modal-content');

    // Add animation class for closing
    if (modalContent) {
        modalContent.classList.remove('animate-slide-up');
        modalContent.classList.add('animate-slide-down');
    }

    // Wait for animation to finish before hiding and resetting
    setTimeout(() => {
        modal.classList.add('hidden'); // Hide the modal
        document.body.style.overflow = ''; // Restore background scrolling

        // Reset animation classes for next time
        if (modalContent) {
            modalContent.classList.remove('animate-slide-down');
            modalContent.classList.add('animate-slide-up'); // Reset to opening animation class
        }
        // Optional: Clear form fields after closing
        // const form = modal.querySelector('#edit-place-form');
        // if (form) form.reset();

    }, 300); // Match animation duration (adjust if CSS animation duration changes)
}

/**
 * Sends a request to the server to update an existing saved place.
 * @param {string|number} id - The ID of the place to update.
 * @param {string} name - The new name for the place.
 * @param {string} address - The new address for the place.
 */
function updateSavedPlace(id, name, address) {
    console.log("Updating saved place:", { id, name, address });
    // Show loading indicator
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator();
    }

    // Get CSRF token from the edit form
    const form = document.getElementById('edit-place-form');
    const csrfToken = form ? form.querySelector('input[name="csrf_token"]')?.value || '' : '';

    // Make the API call using PUT method
    fetch(window.location.origin + '/api/api-saved-places.php', {
        method: 'PUT', // Use PUT for updates
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: id,
            name: name,
            address: address,
            csrf_token: csrfToken // Include CSRF token
        }),
        credentials: 'include' // Send cookies
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        console.log("Update place response:", data);
        if (data.success) {
            // If update is successful
            closeEditModal(); // Close the edit modal
            loadSavedPlaces(); // Reload the list to show changes

            // Show success message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Place updated successfully!');
            }
        } else {
            // If update fails, show error message (possibly inside the modal)
            if (typeof showConfirmation === 'function') {
                // Consider showing error within the modal for better UX
                 showConfirmation(data.message || 'Failed to update place. Please try again.', true);
            } else {
                 alert(data.message || 'Failed to update place.');
            }
        }
    })
    .catch(error => {
        console.error('Error updating saved place:', error);
        // Show generic error message
        if (typeof showConfirmation === 'function') {
            showConfirmation('An error occurred while updating the place. Please try again later.', true);
        }
    })
    .finally(() => {
        // Hide loading indicator
        if (typeof hideLoadingIndicator === 'function') {
            hideLoadingIndicator();
        }
    });
}

/**
 * Sends a request to the server to delete a saved place after confirmation.
 * @param {string|number} id - The ID of the place to delete.
 * @param {string} name - The name of the place (used for confirmation message).
 */
function deleteSavedPlace(id, name) {
    console.log("Attempting to delete place:", { id, name });
    // Confirm with the user before deleting
    if (!confirm(`Are you sure you want to delete the saved place "${name || 'this place'}"? This action cannot be undone.`)) {
        console.log("Deletion cancelled by user.");
        return; // Stop if user cancels
    }

    // Show loading indicator
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator();
    }

    // Get CSRF token (might be needed for DELETE requests depending on backend)
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';


    // Make the API call using DELETE method
    // Pass ID in the URL, include CSRF if needed (can be header or body)
    fetch(window.location.origin + `/api/api-saved-places.php?id=${encodeURIComponent(id)}`, { // Encode ID in URL
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json', // Optional for DELETE if no body
            'X-Requested-With': 'XMLHttpRequest',
            // Add CSRF token header if backend expects it
             'X-CSRF-Token': csrfToken
        },
        // body: JSON.stringify({ csrf_token: csrfToken }), // Or send CSRF in body if required
        credentials: 'include' // Send cookies
    })
    // Check response status directly as DELETE might not return JSON body on success
    .then(response => {
         if (response.ok) {
             // If status is 2xx, assume success. Try parsing JSON, but handle cases where it might be empty.
             return response.json().catch(() => ({ success: true, message: 'Place deleted successfully (no content)' }));
         } else {
             // If status is not OK, try to parse error message from JSON body
             return response.json().then(errData => {
                 throw new Error(errData.message || `Server returned status ${response.status}`);
             }).catch(() => {
                 // If parsing error body fails, throw generic error
                 throw new Error(`Server returned status ${response.status}`);
             });
         }
    })
    .then(data => {
        console.log("Delete place response:", data);
        if (data.success) {
            // If deletion is successful
            loadSavedPlaces(); // Reload the list

            // Show success message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Place deleted successfully!');
            }
        } else {
             // This part might not be reached if errors are thrown above, but included for safety
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Failed to delete place.', true);
            }
        }
    })
    .catch(error => {
        console.error('Error deleting saved place:', error);
        // Show generic error message
        if (typeof showConfirmation === 'function') {
            showConfirmation(`Error deleting place: ${error.message}. Please try again.`, true);
        }
    })
    .finally(() => {
        // Hide loading indicator
        if (typeof hideLoadingIndicator === 'function') {
            hideLoadingIndicator();
        }
    });
}

/**
 * Fetches the user's ride history from the server with pagination and filtering.
 * @param {number} [page=1] - The page number to fetch.
 * @param {string} [filter='all'] - The filter to apply ('all', 'month', 'completed', 'canceled').
 * @returns {Promise} A promise that resolves with ride history data or rejects on error.
 */
function loadRideHistory(page = 1, filter = 'all') {
    console.log("Loading ride history - Page:", page, "Filter:", filter);

    // Show loading indicator specifically for the rides tab if possible, or global one
     const ridesContainer = document.querySelector('#rides-tab-content');
     if (ridesContainer) ridesContainer.classList.add('loading'); // Example class
     else if (typeof showLoadingIndicator === 'function') showLoadingIndicator();


    // Construct the API URL with query parameters
    const apiUrl = new URL(window.location.origin + '/api/api-ride-history.php');
    apiUrl.searchParams.append('page', page);
    apiUrl.searchParams.append('filter', filter);

    return fetch(apiUrl.toString(), { // Use the constructed URL
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include' // Send cookies
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server returned ${response.status} while fetching ride history`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Ride history data received:", data);
        if (data.success && data.data) {
            // Update the UI with the fetched ride history
             if (typeof updateRideHistoryUI === 'function') {
                updateRideHistoryUI(data.data); // Pass the 'data' part containing rides, pagination, filter
             }
            return data; // Return the full response data
        } else {
            console.warn("Ride history API call did not indicate success or returned no data:", data.message);
             if (typeof updateRideHistoryUI === 'function') {
                 // Pass an empty structure to clear the UI
                 updateRideHistoryUI({ rides: [], pagination: null, filter: filter });
             }
            throw new Error(data.message || 'Failed to load ride history.');
        }
    })
    .catch(error => {
        console.error('Error loading ride history:', error);
         if (typeof updateRideHistoryUI === 'function') {
             // Update UI to show an error message within the rides tab
             const ridesContainer = document.querySelector('#rides-tab-content');
             if (ridesContainer) {
                 ridesContainer.innerHTML = `<p class="text-red-400 text-center py-6">Error loading ride history: ${error.message}</p>`;
             }
         }
        throw error; // Re-throw
    })
     .finally(() => {
         // Hide loading indicator for the rides tab or global one
         if (ridesContainer) ridesContainer.classList.remove('loading');
         else if (typeof hideLoadingIndicator === 'function') hideLoadingIndicator();
     });
}

/**
 * Updates the UI to display ride history, filter buttons, and pagination.
 * Includes logic for a 'Cancel Ride' button on applicable rides.
 * @param {object} data - Object containing rides, pagination info, and current filter.
 * Example: { rides: [...], pagination: {...}, filter: 'all' }
 */
function updateRideHistoryUI(data) {
    console.log("Updating ride history UI", data);
    // Find the main container for the rides tab content
    const ridesContainer = document.querySelector('#rides-tab-content');
    if (!ridesContainer) {
        console.warn("Rides tab content container not found.");
        return;
    }

    // Clear existing content (filters, list, pagination)
    ridesContainer.innerHTML = '';

    // --- 1. Create Filter Options ---
    const filterOptions = document.createElement('div');
    filterOptions.className = 'mb-4 flex flex-wrap gap-2 pb-2'; // Use flex-wrap for smaller screens
    const currentFilter = data.filter || 'all'; // Default to 'all' if not provided
    const filters = [
        { key: 'all', label: 'All' },
        { key: 'month', label: 'Last 30 Days' },
        { key: 'completed', label: 'Completed' },
        { key: 'canceled', label: 'Canceled' }
        // Add more filters if needed (e.g., 'scheduled', 'in_progress')
    ];

    filters.forEach(f => {
        const isActive = f.key === currentFilter;
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.filter = f.key;
        // Dynamic classes based on active state
        button.className = `py-1 px-3 rounded-full border text-sm transition duration-200 ${
            isActive
                ? 'bg-primary-600 border-primary-500 text-white font-medium cursor-default' // Active style
                : 'bg-gray-700 border-gray-600 text-gray-300 hover:bg-gray-600 hover:border-gray-500 hover:text-white' // Inactive style
        }`;
        button.textContent = f.label;
        button.disabled = isActive; // Disable the currently active filter button

        // Add event listener to load history with the new filter
        button.addEventListener('click', () => {
            loadRideHistory(1, f.key); // Load page 1 with the selected filter
        });
        filterOptions.appendChild(button);
    });

    ridesContainer.appendChild(filterOptions); // Add filter buttons to the container

    // --- 2. Display Rides List or Empty State ---
    if (data.rides && data.rides.length > 0) {
        const ridesList = document.createElement('div');
        ridesList.className = 'space-y-4'; // Spacing between ride items

        data.rides.forEach(ride => {
            // Get the UI element for the ride status
            const rideStatusUI = getRideStatusUI(ride.status);

            // Define which statuses are considered cancellable
            const cancellableStatuses = ['searching', 'confirmed', 'arriving', 'scheduled'];
            const isCancellable = cancellableStatuses.includes(ride.status?.toLowerCase()); // Check status safely

            const rideItem = document.createElement('div');
            // Add relative positioning for the absolute cancel button
            rideItem.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 relative overflow-hidden'; // Added overflow-hidden

            // Inner HTML for the ride item card
            rideItem.innerHTML = `
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex-1 min-w-0"> <div class="flex flex-wrap gap-x-3 gap-y-1 items-center mb-2">
                            <span class="text-sm font-medium text-white whitespace-nowrap">${ride.date || 'N/A'}</span>
                            <span class="text-sm text-gray-400 whitespace-nowrap">${ride.time || ''}</span>
                            ${rideStatusUI} </div>
                        <div class="mb-1">
                            <p class="text-gray-300 text-sm truncate" title="${ride.pickup || ''}">
                                <span class="lucide text-xs mr-1 text-green-400" aria-hidden="true">&#xea4b;</span> ${ride.pickup || 'Pickup location not available'}
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-300 text-sm truncate" title="${ride.dropoff || ''}">
                                <span class="lucide text-xs mr-1 text-red-400" aria-hidden="true">&#xea4a;</span> ${ride.dropoff || 'Dropoff location not available'}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col items-start md:items-end mt-2 md:mt-0 flex-shrink-0">
                        <div class="text-lg font-medium text-white mb-1">${ride.fare || 'N/A'}</div>
                        ${ride.driver_name ? `<div class="text-sm text-gray-400">Driver: ${ride.driver_name}</div>` : ''}
                        ${ride.rating ? `
                            <div class="flex items-center mt-1" title="Your rating for this ride">
                                <span class="text-sm mr-1 text-gray-300">Rated:</span>
                                <span class="lucide text-yellow-400 text-xs" aria-hidden="true">&#xeae5;</span> <span class="text-sm ml-1">${ride.rating}</span>
                            </div>
                        ` : ''}
                         ${ride.status === 'completed' && !ride.rating ? `
                            <button class="rate-ride-btn mt-2 text-xs text-primary-400 hover:text-primary-300" data-ride-id="${ride.id}">Rate Ride</button>
                         ` : ''}
                    </div>
                </div>

                ${isCancellable ? `
                    <button
                        class="cancel-ride-btn absolute right-3 top-3 bg-red-600 hover:bg-red-700 text-white text-xs py-1 px-2 rounded transition duration-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 focus:ring-offset-gray-700"
                        data-ride-id="${ride.id}"
                        title="Cancel this ride"
                    >
                        Cancel
                    </button>
                ` : ''}
            `;

            ridesList.appendChild(rideItem); // Add the ride item to the list
        });

        ridesContainer.appendChild(ridesList); // Add the list to the main container

        // --- Add Event Listeners for Cancel Buttons (after adding list to DOM) ---
        const cancelButtons = ridesContainer.querySelectorAll('.cancel-ride-btn');
        cancelButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const rideId = e.target.dataset.rideId;
                if (rideId && typeof cancelRideFromDashboard === 'function') {
                    cancelRideFromDashboard(rideId); // Call the cancel function
                } else {
                    console.error("Could not cancel ride: Invalid ride ID or cancel function missing.", rideId);
                }
            });
        });

         // --- Add Event Listeners for Rate Buttons (Optional) ---
         const rateButtons = ridesContainer.querySelectorAll('.rate-ride-btn');
         rateButtons.forEach(btn => {
             btn.addEventListener('click', (e) => {
                 const rideId = e.target.dataset.rideId;
                 console.log("Rate ride button clicked for ride ID:", rideId);
                 // Implement rating functionality (e.g., open a rating modal)
                 // showRatingModal(rideId);
             });
         });


    } else {
        // --- 3. Show Empty State ---
        const emptyState = document.createElement('div');
        emptyState.className = 'text-center py-8 text-gray-400';
        let emptyMessage = `<p>No rides found matching the filter '${currentFilter}'.</p>`;
        if (currentFilter === 'all') {
            emptyMessage = `
                <span class="lucide text-4xl mb-2 block mx-auto">&#xea5e;</span> <p>You don't have any ride history yet.</p>
                <p class="mt-2">Book your first ride on the <a href="index.php" class="text-primary-400 hover:text-primary-300 underline">home page</a>!</p>
            `;
        }
        emptyState.innerHTML = emptyMessage;
        ridesContainer.appendChild(emptyState);
    }

    // --- 4. Add Pagination Controls ---
    if (data.pagination && data.pagination.total_pages > 1) {
        const pagination = document.createElement('div');
        pagination.className = 'mt-6 flex justify-center items-center gap-2';
        const currentPage = data.pagination.current_page;
        const totalPages = data.pagination.total_pages;

        // Previous Page Button
        if (data.pagination.has_prev_page) {
            const prevButton = document.createElement('button');
            prevButton.className = 'p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 focus:ring-offset-gray-800';
            prevButton.innerHTML = '<span class="lucide pointer-events-none" aria-hidden="true">&#xeaa2;</span>'; // Chevron Left
            prevButton.setAttribute('aria-label', 'Previous Page');
            prevButton.addEventListener('click', () => {
                loadRideHistory(data.pagination.prev_page, currentFilter);
            });
            pagination.appendChild(prevButton);
        } else {
             // Optional: Show disabled previous button
             const disabledPrev = document.createElement('span');
             disabledPrev.className = 'p-2 rounded-lg bg-gray-800 text-gray-600 cursor-not-allowed';
             disabledPrev.innerHTML = '<span class="lucide" aria-hidden="true">&#xeaa2;</span>';
             pagination.appendChild(disabledPrev);
        }

        // Page Number Info
        const pageInfo = document.createElement('span');
        pageInfo.className = 'text-gray-400 text-sm';
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        pageInfo.setAttribute('aria-live', 'polite'); // Announce page changes
        pagination.appendChild(pageInfo);

        // Next Page Button
        if (data.pagination.has_next_page) {
            const nextButton = document.createElement('button');
            nextButton.className = 'p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 focus:ring-offset-gray-800';
            nextButton.innerHTML = '<span class="lucide pointer-events-none" aria-hidden="true">&#xeaa0;</span>'; // Chevron Right
            nextButton.setAttribute('aria-label', 'Next Page');
            nextButton.addEventListener('click', () => {
                loadRideHistory(data.pagination.next_page, currentFilter);
            });
            pagination.appendChild(nextButton);
        } else {
             // Optional: Show disabled next button
             const disabledNext = document.createElement('span');
             disabledNext.className = 'p-2 rounded-lg bg-gray-800 text-gray-600 cursor-not-allowed';
             disabledNext.innerHTML = '<span class="lucide" aria-hidden="true">&#xeaa0;</span>';
             pagination.appendChild(disabledNext);
        }

        ridesContainer.appendChild(pagination); // Add pagination controls to the container
    }
}


/**
 * Generates an HTML span element representing the ride status with appropriate styling.
 * @param {string} status - The status string (e.g., 'completed', 'cancelled').
 * @returns {string} - An HTML string for the status badge.
 */
function getRideStatusUI(status) {
    let statusClass = '';
    let statusText = '';
    let icon = ''; // Optional icon

    // Normalize status to lowercase for reliable comparison
    const lowerStatus = status?.toLowerCase() || 'unknown';

    switch (lowerStatus) {
        case 'completed':
            statusClass = 'bg-green-600/20 text-green-300 border border-green-500/30';
            statusText = 'Completed';
            icon = '&#xea6d;'; // Check Circle
            break;
        case 'cancelled': // Allow both spellings
        case 'canceled':
            statusClass = 'bg-red-600/20 text-red-300 border border-red-500/30';
            statusText = 'Canceled';
             icon = '&#xea76;'; // X Circle
            break;
        case 'in_progress':
        case 'on_trip': // Alias
            statusClass = 'bg-blue-600/20 text-blue-300 border border-blue-500/30';
            statusText = 'In Progress';
             icon = '&#xea5e;'; // Car
            break;
        case 'scheduled':
            statusClass = 'bg-purple-600/20 text-purple-300 border border-purple-500/30';
            statusText = 'Scheduled';
             icon = '&#xea66;'; // Calendar
            break;
        case 'searching':
            statusClass = 'bg-yellow-600/20 text-yellow-300 border border-yellow-500/30 animate-pulse'; // Pulse effect
            statusText = 'Finding Driver';
             icon = '&#xeab1;'; // Search
            break;
         case 'confirmed':
             statusClass = 'bg-cyan-600/20 text-cyan-300 border border-cyan-500/30';
             statusText = 'Confirmed';
             icon = '&#xea6c;'; // Check
             break;
         case 'arriving':
             statusClass = 'bg-teal-600/20 text-teal-300 border border-teal-500/30';
             statusText = 'Driver Arriving';
             icon = '&#xea4b;'; // Pin (start)
             break;
        default:
            statusClass = 'bg-gray-600/20 text-gray-300 border border-gray-500/30';
            // Capitalize the first letter of the unknown status
            statusText = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
             icon = '&#xea91;'; // Help Circle
    }

    // Return the HTML string for the badge
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                ${icon ? `<span class="lucide text-xs mr-1" aria-hidden="true">${icon}</span>` : ''}
                ${statusText}
            </span>`;
}

/**
 * Fetches the user's saved payment methods from the server.
 * @returns {Promise} A promise that resolves with payment methods data or rejects on error.
 */
function loadPaymentMethods() {
    console.log("Loading payment methods");
    return fetch(window.location.origin + '/api/api-payment-methods.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include' // Send cookies
    })
    .then(response => {
        // Handle cases where the API endpoint might not be ready (404 or 501)
        if (response.status === 404 || response.status === 501) {
            console.warn(`Payment methods API returned ${response.status}. Endpoint might not be implemented.`);
            // Return a structure indicating no methods, but not necessarily an error state
            return { success: true, data: { payment_methods: [] }, message: 'Payment methods feature not available.' };
        }
        if (!response.ok) {
            throw new Error(`Server returned ${response.status} while fetching payment methods`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Payment methods data received:", data);
        // Update UI. Ensure data.data exists and payment_methods is an array.
        const methods = data?.data?.payment_methods || [];
         if (typeof updatePaymentMethodsUI === 'function') {
            updatePaymentMethodsUI(methods);
         }
        return data; // Return the full data
    })
    .catch(error => {
        console.error('Error loading payment methods:', error);
        // Update UI to show an error state or empty list
        if (typeof updatePaymentMethodsUI === 'function') {
             updatePaymentMethodsUI([]); // Show empty list on error
        }
        throw error; // Re-throw
    });
}

/**
 * Updates the UI to display saved payment methods and the add method form/message.
 * @param {Array} paymentMethods - An array of payment method objects.
 */
function updatePaymentMethodsUI(paymentMethods) {
    console.log("Updating payment methods UI", paymentMethods);
    // Find the main container for the payment tab content
    const paymentContainer = document.querySelector('#payment-tab-content');
    if (!paymentContainer) {
        console.warn("Payment tab content container not found.");
        return;
    }

    // Clear existing content (list and add form/message)
    paymentContainer.innerHTML = '';

    // --- 1. Display Saved Payment Methods List or Empty State ---
    const methodsListWrapper = document.createElement('div');
    methodsListWrapper.className = 'payment-methods-list-wrapper space-y-4 mb-6';

    if (paymentMethods && paymentMethods.length > 0) {
        paymentMethods.forEach(method => {
            const methodItem = document.createElement('div');
            methodItem.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3'; // Responsive layout
            methodItem.dataset.methodId = method.id; // Store ID

            // Determine icon based on type (add more types as needed)
            let cardIcon = '&#xeaa4;'; // Default: Credit Card
            let cardBrandClass = 'text-gray-300'; // Default color
            const lowerType = method.type?.toLowerCase();

            if (lowerType === 'paypal') {
                cardIcon = '&#xec8f;'; // PayPal icon (check if Lucide includes this, might need custom SVG/FontAwesome)
                cardBrandClass = 'text-blue-400';
            } else if (lowerType === 'card') {
                // Potentially detect card brand (Visa, Mastercard etc.) based on 'name' or 'brand' field if available
                const lowerBrand = method.brand?.toLowerCase() || method.name?.toLowerCase() || '';
                if (lowerBrand.includes('visa')) cardIcon = '&#xf1f0;'; // Placeholder: fa-cc-visa if using FontAwesome
                else if (lowerBrand.includes('mastercard')) cardIcon = '&#xf1f1;'; // Placeholder: fa-cc-mastercard
                else if (lowerBrand.includes('amex')) cardIcon = '&#xf1f3;'; // Placeholder: fa-cc-amex
            }

            // Construct inner HTML
            methodItem.innerHTML = `
                <div class="flex items-center flex-1 min-w-0">
                    <span class="lucide text-2xl mr-3 ${cardBrandClass}" aria-hidden="true">${cardIcon}</span>
                    <div class="flex-1">
                        <p class="font-medium text-white truncate" title="${method.name || ''}">${method.name || 'Payment Method'}</p>
                        <p class="text-sm text-gray-400">
                            ${lowerType === 'card' ? `**** **** **** ${method.last4 || '****'}` : (method.email || 'Details not available')}
                            ${method.expiry ? `<span class="ml-2">Exp: ${method.expiry}</span>` : ''}
                        </p>
                    </div>
                </div>
                <div class="mt-3 sm:mt-0 flex items-center space-x-2 flex-shrink-0 self-end sm:self-center"> ${method.default ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-500/20 text-primary-300 border border-primary-500/30" title="Default payment method">Default</span>' : ''}
                    ${!method.default ? `<button class="set-default-payment-btn text-xs text-gray-400 hover:text-primary-300" data-method-id="${method.id}" title="Set as default">Set Default</button>` : ''}
                    <button class="delete-payment-btn p-1.5 rounded-lg bg-gray-600 hover:bg-red-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 focus:ring-offset-gray-800" title="Delete Payment Method">
                        <span class="lucide text-lg pointer-events-none" aria-hidden="true">&#xea0f;</span> </button>
                </div>
            `;

            // Add event listener to delete button
            const deleteButton = methodItem.querySelector('.delete-payment-btn');
            if (deleteButton) {
                deleteButton.addEventListener('click', () => {
                    if (typeof deletePaymentMethod === 'function') {
                        // Pass name or type/last4 for confirmation message
                        const confirmName = method.name || `${method.type} ending in ${method.last4}`;
                        deletePaymentMethod(method.id, confirmName);
                    } else {
                         console.error("deletePaymentMethod function not defined.");
                    }
                });
            }
             // Add event listener to set default button (Optional)
             const setDefaultButton = methodItem.querySelector('.set-default-payment-btn');
             if (setDefaultButton) {
                 setDefaultButton.addEventListener('click', () => {
                     console.log("Set default clicked for:", method.id);
                     // Implement set default functionality
                     // setDefaultPaymentMethod(method.id);
                 });
             }

            methodsListWrapper.appendChild(methodItem);
        });
    } else {
        // Show empty state message
        methodsListWrapper.innerHTML = `
            <div class="text-center py-6 text-gray-400">
                 <span class="lucide text-4xl mb-2 block mx-auto">&#xeaa4;</span> <p>No payment methods added yet.</p>
                <p class="mt-1 text-sm">Add a payment method below for easier checkout.</p>
            </div>
        `;
    }
    paymentContainer.appendChild(methodsListWrapper); // Add the list (or empty state)

    // --- 2. Add 'Add Payment Method' Section ---
    // This section might contain a form, a button linking to a secure payment gateway, or just a message.
    const addPaymentSection = document.createElement('div');
    addPaymentSection.className = 'add-payment-method-section mt-6 pt-6 border-t border-gray-700';

    // Example: Placeholder message if adding via dashboard is not implemented
    addPaymentSection.innerHTML = `
        <h3 class="text-lg font-medium text-white mb-4">Add Payment Method</h3>
        <div class="bg-blue-900/30 rounded-lg p-4 border border-blue-700 text-center">
            <p class="text-blue-200 mb-2">Adding new payment methods securely is coming soon!</p>
            <p class="text-blue-300 text-sm">For now, you might need to add methods during checkout or via a dedicated billing portal (if available).</p>
            </div>
    `;

    // Example: If you had a simple form (Not recommended for real card details directly):
    /*
    addPaymentSection.innerHTML = `
        <h3 class="text-lg font-medium text-white mb-4">Add Payment Method (Demo Only)</h3>
        <form id="add-payment-form" class="space-y-4">
             <p class="text-yellow-400 text-sm">Note: This is a demo form. Do not enter real card details.</p>
             <div><label ...>Card Number</label><input type="text" ...></div>
             <div><label ...>Expiry</label><input type="text" placeholder="MM/YY" ...></div>
             <div><label ...>CVC</label><input type="text" ...></div>
             <button type="submit" ...>Add Card (Demo)</button>
        </form>
    `;
    // Add submit listener to the form here...
    */

    paymentContainer.appendChild(addPaymentSection);
}


/**
 * Sends a request to the server to delete a payment method after confirmation.
 * @param {string|number} id - The ID of the payment method to delete.
 * @param {string} name - The name/description of the method for the confirmation message.
 */
function deletePaymentMethod(id, name) {
    console.log("Attempting to delete payment method:", { id, name });

    // Confirm with the user
    if (!confirm(`Are you sure you want to delete the payment method "${name || 'this method'}"?`)) {
        console.log("Deletion cancelled.");
        return;
    }

    // Show loading indicator
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator();
    }

     // Get CSRF token (important for potentially destructive actions)
     const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Make the API call using DELETE
    fetch(window.location.origin + `/api/api-payment-methods.php?id=${encodeURIComponent(id)}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json', // Optional if no body
            'X-Requested-With': 'XMLHttpRequest',
             'X-CSRF-Token': csrfToken // Send CSRF as header
        },
        // body: JSON.stringify({ csrf_token: csrfToken }), // Or send in body
        credentials: 'include' // Send cookies
    })
     .then(response => { // Check status code first
         if (response.ok) {
             return response.json().catch(() => ({ success: true, message: 'Deleted successfully (no content)' }));
         } else {
             return response.json().then(errData => {
                 throw new Error(errData.message || `Server error ${response.status}`);
             }).catch(() => {
                 throw new Error(`Server error ${response.status}`);
             });
         }
     })
    .then(data => {
        console.log("Delete payment method response:", data);
        if (data.success) {
            // Reload the payment methods list
            loadPaymentMethods();

            // Show success message
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Payment method deleted successfully!');
            }
        } else {
            // Show error message from server response
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Failed to delete payment method.', true);
            }
        }
    })
    .catch(error => {
        console.error('Error deleting payment method:', error);
        // Show generic error message
        if (typeof showConfirmation === 'function') {
            showConfirmation(`Error deleting payment method: ${error.message}. Please try again.`, true);
        }
    })
    .finally(() => {
        // Hide loading indicator
        if (typeof hideLoadingIndicator === 'function') {
            hideLoadingIndicator();
        }
    });
}

/**
 * Sets the initially active dashboard tab based on URL parameters or localStorage.
 */
function initDashboardFromURL() {
    // Get 'tab' parameter from the current URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    // Check localStorage for a previously active tab (e.g., if page reloaded)
    const storedTab = localStorage.getItem('dashboardActiveTab');

    // Determine the target tab: URL param > localStorage > default ('profile')
    const activeTab = tabParam || storedTab || 'profile';
    console.log("Initializing dashboard - Target tab:", activeTab);

    // Find the corresponding tab button element
    const tabBtn = document.getElementById(`${activeTab}-tab-btn`);

    if (tabBtn) {
        // Simulate a click on the tab button to activate it and its content pane
        // Use timeout to ensure other initializations might complete first
        setTimeout(() => {
             tabBtn.click();
             console.log(`Activated tab: ${activeTab}`);
        }, 0);

    } else {
        console.warn(`Tab button not found for target tab: ${activeTab}. Defaulting might occur.`);
        // Optionally, explicitly click the profile tab if the target wasn't found
        const profileTabBtn = document.getElementById('profile-tab-btn');
        if (profileTabBtn) {
             setTimeout(() => profileTabBtn.click(), 0);
        }
    }

    // Clean up localStorage if it was used
    if (storedTab) {
        localStorage.removeItem('dashboardActiveTab');
    }
}


/**
 * Initializes event handlers for all logout links/buttons.
 */
function initLogoutHandlers() {
    console.log("Initializing logout handlers");

    // Select all elements intended for logout actions
    const logoutElements = document.querySelectorAll('a[href="logout.php"], #logout-link, #mobile-logout-link, .logout-button'); // Added class selector

    if (logoutElements.length > 0) {
        console.log(`Found ${logoutElements.length} logout elements.`);
    } else {
        console.warn("No logout elements found on the page.");
        return; // Exit if none found
    }

    logoutElements.forEach(element => {
        // --- Prevent multiple listeners: Remove old before adding new ---
        // 1. Clone the element to effectively remove existing listeners
        const newElement = element.cloneNode(true);
        element.parentNode.replaceChild(newElement, element);

        // 2. Add the event listener to the new clone
        newElement.addEventListener('click', function(e) {
            console.log("Logout element clicked:", this.id || this.tagName);
            e.preventDefault(); // Prevent default link navigation or button action

            // Optional: Confirmation dialog
            // if (!confirm("Are you sure you want to log out?")) {
            //     return;
            // }

            // Show loading indicator
            if (typeof showLoadingIndicator === 'function') {
                showLoadingIndicator("Logging out..."); // Optional message
            }

            // --- Client-side cleanup first ---
            console.log("Performing client-side logout cleanup...");
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            localStorage.removeItem('dashboardActiveTab'); // Clear potentially stored tab
            // Clear any other sensitive session-related localStorage items here

            // Update global state immediately
            isLoggedIn = false;
            currentUser = null;
            console.log("Client-side state cleared.");

            // --- Server-side logout request ---
            console.log("Calling server logout endpoint...");
            fetch('logout.php', { // Assuming logout.php handles session destruction
                method: 'POST', // Use POST for actions that change state (like logout)
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    // Include CSRF token if required by logout.php
                    // 'X-CSRF-Token': document.querySelector('input[name="csrf_token"]')?.value || ''
                },
                credentials: 'include' // Send cookies to invalidate session
            })
            .then(response => {
                console.log("Server logout response status:", response.status);
                // Check if logout was successful on the server (e.g., status 200)
                if (!response.ok) {
                    // Log server-side error but proceed with client-side redirect anyway
                    console.warn(`Server logout endpoint returned status ${response.status}. Proceeding with redirect.`);
                }
                // Attempt to parse JSON, but don't fail if it's not JSON
                return response.text(); // Get text first to avoid JSON parse error on empty/non-JSON response
            })
             .then(text => {
                 console.log("Server logout response body (text):", text);
                 // Optionally parse if you expect JSON:
                 // try { const data = JSON.parse(text); console.log("Parsed logout data:", data); } catch(e) {}
             })
            .catch(error => {
                // Network error or other issue calling the server endpoint
                console.error("Error calling server logout endpoint:", error);
                // Still proceed with redirect as client-side is logged out
            })
            .finally(() => {
                // --- Redirect after attempting server logout ---
                console.log("Logout process complete, redirecting to home page...");

                // Hide loading indicator
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }

                // Show a brief confirmation message (optional)
                if (typeof showConfirmation === 'function') {
                    // Use a short timeout so the message is visible before redirect
                    showConfirmation('You have been logged out.');
                     setTimeout(() => {
                         window.location.href = 'index.php'; // Redirect to home page
                     }, 750); // Delay redirect slightly
                } else {
                     // If no confirmation function, redirect immediately
                     window.location.href = 'index.php';
                }
            });
        });
    });

     // Fail-safe: If the user somehow navigates directly to logout.php, clear storage.
     // This is less reliable than the click handler but acts as a backup.
     if (window.location.pathname.includes('logout.php')) {
         console.warn("Detected direct navigation to logout.php, clearing client storage.");
         localStorage.removeItem('isLoggedIn');
         localStorage.removeItem('currentUser');
         localStorage.removeItem('dashboardActiveTab');
         // Redirect immediately to prevent staying on logout.php
         window.location.href = 'index.php';
     }
}


/**
 * Checks the browser's online status and optionally displays a message.
 */
function checkNetworkStatus() {
    const onlineStatusIndicator = document.getElementById('online-status-indicator'); // Optional UI element

    if (navigator.onLine) {
        console.log("Network status: Online");
        if (onlineStatusIndicator) {
            onlineStatusIndicator.textContent = 'Online';
            onlineStatusIndicator.classList.remove('offline');
            onlineStatusIndicator.classList.add('online');
        }
        // Optional: Hide any persistent offline messages
        // hideOfflineMessage();
    } else {
        console.warn("Network status: Offline");
        if (onlineStatusIndicator) {
            onlineStatusIndicator.textContent = 'Offline';
            onlineStatusIndicator.classList.remove('online');
            onlineStatusIndicator.classList.add('offline');
        }
        // Show a non-intrusive offline message
        if (typeof showConfirmation === 'function') {
            // Use a specific ID or class for the offline message so it can be managed
            showConfirmation("You appear to be offline. Some features may be limited.", true, 'offline-message');
        }
    }
}

/**
 * Sets up primary event listeners for the dashboard interface, like tab switching and form submissions.
 */
function setupEventListeners() {
    console.log("Setting up dashboard event listeners.");

    // --- Dashboard Tab Switching ---
    const dashboardTabs = document.querySelectorAll('.dashboard-tab');
    dashboardTabs.forEach(tabButton => {
        tabButton.addEventListener('click', function() {
            const tabName = this.id.replace('-tab-btn', '');
            console.log(`Switching to tab: ${tabName}`);

            // 1. Hide all content panes
            document.querySelectorAll('.dashboard-content').forEach(contentPane => {
                contentPane.classList.add('hidden');
            });

            // 2. Show the selected content pane
            const selectedContent = document.getElementById(`${tabName}-tab-content`);
            if (selectedContent) {
                selectedContent.classList.remove('hidden');
                // Optional: Trigger data loading if content was previously hidden and needs refresh
                // if (tabName === 'rides' && !selectedContent.hasAttribute('data-loaded')) {
                //     loadRideHistory();
                //     selectedContent.setAttribute('data-loaded', 'true');
                // } // Add similar logic for other tabs if needed
            } else {
                console.warn(`Content pane not found for tab: ${tabName}`);
            }

            // 3. Update button styles (Active/Inactive)
            dashboardTabs.forEach(btn => {
                // Remove active classes, add inactive classes
                btn.classList.remove('active', 'text-primary-400', 'border-primary-400', 'bg-gray-700/50');
                btn.classList.add('text-gray-400', 'hover:text-primary-300', 'border-transparent', 'hover:bg-gray-700/30');
                btn.setAttribute('aria-selected', 'false');
            });

            // Add active classes to the clicked button
            this.classList.add('active', 'text-primary-400', 'border-primary-400', 'bg-gray-700/50');
            this.classList.remove('text-gray-400', 'hover:text-primary-300', 'border-transparent', 'hover:bg-gray-700/30');
            this.setAttribute('aria-selected', 'true');

            // 4. Update URL hash or query parameter (optional, good for deep linking/history)
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                // Use replaceState to avoid adding multiple history entries for tab clicks
                window.history.replaceState({ tab: tabName }, '', url);
            } catch (e) { console.error("Could not update URL state:", e); }


            // 5. Store active tab in localStorage (optional, for persistence on reload)
            localStorage.setItem('dashboardActiveTab', tabName);
        });
    });

    // --- Profile Form Submission ---
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            console.log("Profile form submitted.");

            // Show loading indicator
            if (typeof showLoadingIndicator === 'function') {
                showLoadingIndicator("Saving profile...");
            }

            const formData = new FormData(this); // Get form data

            // Log form data for debugging (remove sensitive data in production logs)
            // for (let [key, value] of formData.entries()) {
            //     console.log(`${key}: ${value}`);
            // }

            // Make the API call to update the profile
            fetch('process-profile-update.php', { // Ensure this endpoint is correct
                method: 'POST',
                body: formData,
                headers: {
                     'X-Requested-With': 'XMLHttpRequest'
                     // No 'Content-Type' needed for FormData, browser sets it
                },
                credentials: 'include' // Send cookies if needed for auth
            })
            .then(response => {
                 // Check if response is OK, then try to parse JSON. Handle non-JSON responses gracefully.
                 if (!response.ok) {
                     // Try to get error message from body, otherwise use status text
                     return response.text().then(text => {
                         try {
                             const errData = JSON.parse(text);
                             throw new Error(errData.message || `Server error ${response.status}`);
                         } catch {
                             throw new Error(text || `Server error ${response.status}`);
                         }
                     });
                 }
                 // If OK, attempt to parse JSON, return success object if parsing fails (e.g., empty response)
                 return response.json().catch(() => ({ success: true, message: 'Profile updated (no content)', user: null }));
             })
            .then(data => {
                console.log("Profile update response:", data);
                if (data.success) {
                    // --- Update client-side user data ---
                    // Option 1: Use data returned from server (if available and complete)
                    if (data.user) {
                         console.log("Updating user data from server response.");
                         currentUser = data.user;
                    } else {
                         // Option 2: Manually update currentUser from form data (less ideal)
                         console.log("Updating user data locally from form.");
                         currentUser = {
                             ...currentUser, // Keep existing properties
                             name: formData.get('name'),
                             email: formData.get('email'), // Be careful if email is read-only
                             phone: formData.get('phone'),
                             language: formData.get('language'),
                             preferences: {
                                 notify_email: formData.get('notify-email') === 'on', // Checkbox value is 'on' when checked
                                 notify_sms: formData.get('notify-sms') === 'on',
                                 notify_promotions: formData.get('notify-promotions') === 'on'
                             }
                         };
                    }

                    // Save updated user data to localStorage
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));

                    // Update UI elements that display user info
                    updateUIWithUserData(); // This will re-fill the form and update display names

                    // Show success message
                    if (typeof showConfirmation === 'function') {
                        showConfirmation(data.message || 'Profile updated successfully!');
                    }
                } else {
                    // Show error message from server
                    if (typeof showConfirmation === 'function') {
                        showConfirmation(data.message || 'Failed to update profile.', true);
                    }
                }
            })
            .catch(error => {
                console.error('Error updating profile:', error);
                // Show generic error message
                if (typeof showConfirmation === 'function') {
                    showConfirmation(`Error updating profile: ${error.message}. Please try again.`, true);
                }
            })
            .finally(() => {
                // Hide loading indicator
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
            });
        });
    } else {
        console.warn("Profile form not found.");
    }

    // Add listeners for other forms (add place, add payment, etc.) if they aren't handled elsewhere
     console.log("Dashboard event listeners set up complete.");
}

/**
 * Updates the display of reward points in the UI.
 * If points are provided, updates directly. Otherwise, triggers a reload.
 * @param {number|string|undefined} points - The number of points to display, or undefined to trigger reload. Can be 'Error'.
 */
function updateRewardPointsDisplay(points) {
    console.log("Updating reward points display with:", points);
    const pointsElements = document.querySelectorAll('.user-reward-points-display'); // Use a class for flexibility

    if (pointsElements.length === 0) {
        console.warn("No elements found with class 'user-reward-points-display'.");
        // If no display element, but points were provided, still update the currentUser object
        if (currentUser && typeof points === 'number') {
             currentUser.reward_points = points;
        }
        return;
    }

    if (points !== undefined && points !== null) {
        let displayValue = '...';
        if (typeof points === 'number') {
            displayValue = points.toLocaleString(); // Format number with commas
             // Update the currentUser object as well
             if (currentUser) {
                 currentUser.reward_points = points;
             }
        } else if (typeof points === 'string') {
            displayValue = points; // Allow displaying strings like "Error" or "Loading..."
        }

        pointsElements.forEach(el => {
            el.textContent = displayValue;
        });
        console.log("Reward points display updated to:", displayValue);
    } else {
        // If no points value provided, trigger a reload from the server
        console.log("No points value provided, triggering reloadRewardPoints.");
        loadRewardPoints().catch(err => console.error("Error reloading points for display update:", err)); // Reload and log potential error
    }
}

/**
 * Function to handle ride cancellation initiated from the dashboard.
 * @param {string|number} rideId - The ID of the ride booking to cancel.
 */
function cancelRideFromDashboard(rideId) {
    console.log("Attempting to cancel ride from dashboard, ID:", rideId);
    if (!rideId) {
        console.error("No ride ID provided for cancellation.");
        if (typeof showConfirmation === 'function') {
            showConfirmation("No ride selected to cancel. Please try again.", true);
        } else {
             alert("No ride selected to cancel.");
        }
        return;
    }

    // Show confirmation dialog first
    if (!confirm("Are you sure you want to cancel this ride? This action cannot be undone.")) {
        console.log("Ride cancellation cancelled by user.");
        return;
    }

    console.log("User confirmed cancellation for ride:", rideId);
    // Show loading indicator
    if (typeof showLoadingIndicator === 'function') {
        showLoadingIndicator("Cancelling ride...");
    }

    // Get CSRF token (important for POST/PUT/DELETE)
     const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Make the API call to the cancellation endpoint
    fetch('/api/api-cancel-ride.php', { // Use absolute path or ensure base URL is correct
        method: 'POST', // Or DELETE, depending on API design
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
             'X-CSRF-Token': csrfToken // Send CSRF as header or in body
        },
        body: JSON.stringify({
            booking_id: rideId, // Ensure the key matches backend expectation
            // csrf_token: csrfToken // Or send in body
        }),
        credentials: 'include' // Send cookies
    })
    .then(response => {
        console.log("Cancel ride response status:", response.status);
        if (!response.ok) {
             // Try to parse error from JSON, fallback to status text
             return response.json()
                 .then(errData => { throw new Error(errData.message || `Server error ${response.status}`); })
                 .catch(() => { throw new Error(`Server responded with ${response.status}: ${response.statusText}`); });
        }
        return response.json(); // Parse successful JSON response
    })
    .then(data => {
        console.log("Cancel ride response data:", data);
        if (data.success) {
            // Reload ride history to reflect the cancellation
            loadRideHistory(); // Reload current page/filter or page 1/all

            // Prepare success message
            let message = data.message || 'Ride cancelled successfully.';
            // Include points info if returned by the API
            if (data.data && data.data.points !== undefined) {
                message += ` Your reward points: ${data.data.points.toLocaleString()}.`;
                // Update points display directly if possible
                updateRewardPointsDisplay(data.data.points);
            } else {
                 // If points not returned, trigger a reload of points separately
                 loadRewardPoints();
            }

            // Show success message
            if (typeof showConfirmation === 'function') {
                showConfirmation(message);
            }

        } else {
            // Show error message from API response
            if (typeof showConfirmation === 'function') {
                showConfirmation(data.message || 'Error cancelling ride. Please try again.', true);
            }
        }
    })
    .catch(error => {
        console.error('Error cancelling ride:', error);
        // Show generic error message
        if (typeof showConfirmation === 'function') {
            showConfirmation(`Error cancelling ride: ${error.message}. Please try again later.`, true);
        }
    })
    .finally(() => {
        // Hide loading indicator
        if (typeof hideLoadingIndicator === 'function') {
            hideLoadingIndicator();
        }
    });
}


// --- Main Initialization ---
document.addEventListener('DOMContentLoaded', () => {
    console.log("Dashboard page DOM fully loaded and parsed.");

    // 1. Check network status immediately and set up listeners
    checkNetworkStatus();
    window.addEventListener('online', checkNetworkStatus);
    window.addEventListener('offline', checkNetworkStatus);

    // 2. Check login status (this will trigger UI updates and data loading if logged in)
    checkLoginStatus();

    // 3. Initialize logout handlers for any logout buttons/links
    // Call it again after a short delay in case elements are added dynamically
    initLogoutHandlers();
    setTimeout(initLogoutHandlers, 1500); // Re-run after potential dynamic content load

    // 4. Set up general dashboard event listeners (tabs, profile form)
    setupEventListeners();

    // 5. Set the initial active tab based on URL or localStorage
    initDashboardFromURL();

    console.log("Dashboard initialization sequence complete.");
});