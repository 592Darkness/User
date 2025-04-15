// Public_html/assets/js/dashboard.js

// Global variables for user state
let currentUser = null;
let isLoggedIn = false;

// --- Utility Functions (Assuming these are defined elsewhere or add them here) ---

/**
 * Shows a global loading indicator.
 * @param {string} [message='Loading...'] - Optional message to display.
 */
function showLoadingIndicator(message = 'Loading...') {
    const loadingOverlay = document.getElementById('loading-overlay');
    const loadingText = loadingOverlay ? loadingOverlay.querySelector('p') : null;
    if (loadingOverlay) {
        if (loadingText) loadingText.textContent = message;
        loadingOverlay.classList.remove('hidden');
    }
    console.log("Loading indicator shown:", message);
}

/**
 * Hides the global loading indicator.
 */
function hideLoadingIndicator() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
     console.log("Loading indicator hidden");
}

/**
 * Displays a temporary confirmation or error message toast.
 * @param {string} message - The message to display.
 * @param {boolean} [isError=false] - True if it's an error message, false for success/info.
 * @param {string|null} [messageId=null] - Optional unique ID to manage specific messages (e.g., 'offline-message').
 */
function showConfirmation(message, isError = false, messageId = null) {
    const confirmationMessage = document.getElementById('confirmation-message');
    const confirmationText = document.getElementById('confirmation-text');
    const confirmationIcon = document.getElementById('confirmation-icon');

    if (!confirmationMessage || !confirmationText) {
        console.warn("Confirmation message element not found. Message:", message);
        // Fallback to alert if UI element is missing
        // alert((isError ? "Error: " : "Info: ") + message);
        return;
    }

    // If managing a specific message type and it's already shown, maybe don't show again
    if (messageId && confirmationMessage.dataset.messageId === messageId && !confirmationMessage.classList.contains('opacity-0')) {
        return;
    }
    if (messageId) {
        confirmationMessage.dataset.messageId = messageId;
    } else {
        delete confirmationMessage.dataset.messageId;
    }


    confirmationText.textContent = message;

    if (confirmationIcon) {
        confirmationIcon.innerHTML = isError ? '&#xea0e;' : '&#xe96c;'; // Example Lucide icons (XCircle, CheckCircle)
        confirmationIcon.classList.remove('hidden');
    }

    confirmationMessage.classList.remove('opacity-0', 'translate-y-6');
    confirmationMessage.classList.add('opacity-100', 'translate-y-0');

    // Remove potentially conflicting background classes before adding new one
    confirmationMessage.classList.remove('bg-green-600', 'bg-red-600');

    if(isError) {
        confirmationMessage.classList.add('bg-red-600');
    } else {
        confirmationMessage.classList.add('bg-green-600');
    }

    // Clear previous timeout if one exists
    if (window.confirmationTimeout) {
        clearTimeout(window.confirmationTimeout);
    }

    // Auto-hide after 5 seconds
    window.confirmationTimeout = setTimeout(() => {
         confirmationMessage.classList.remove('opacity-100', 'translate-y-0');
         confirmationMessage.classList.add('opacity-0', 'translate-y-6');
         // Clear message ID after hiding
          if (messageId) {
              delete confirmationMessage.dataset.messageId;
          }
    }, 5000);
    console.log(`Confirmation shown: "${message}" (Error: ${isError})`);
}

// --- Authentication and User Data ---

/**
 * Checks the user's login status using localStorage first, then falls back to a server check.
 */
function checkLoginStatus() {
    console.log("Checking login status...");

    const storedLoginStatus = localStorage.getItem('isLoggedIn');
    const storedUser = localStorage.getItem('currentUser');

    console.log("LocalStorage login status:", storedLoginStatus);
    console.log("LocalStorage user exists:", !!storedUser);

    if (storedLoginStatus === 'true' && storedUser) {
        try {
            isLoggedIn = true;
            currentUser = JSON.parse(storedUser);
            console.log("Using localStorage user data:", currentUser);
            updateUIWithUserData(); // Update UI based on stored data
        } catch (error) {
            console.error('Error parsing stored user data:', error);
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            serverAuthCheck(); // Fallback to server check
        }
    } else {
        serverAuthCheck(); // Check with the server
    }
}

/**
 * Performs a server-side check to verify authentication status and fetch user data.
 */
function serverAuthCheck() {
    console.log("Performing server auth check...");
    const apiUrl = window.location.origin + '/api/api-auth.php?endpoint=check-auth'; // Added endpoint
    console.log("Fetching from:", apiUrl);

    fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include' // Send cookies
    })
    .then(response => {
        console.log("Auth check response status:", response.status);
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Auth check response data:", data);
        if (data.authenticated && data.user) { // Check for user data as well
            isLoggedIn = true;
            currentUser = data.user;
            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            console.log("Set user data from server:", currentUser);
            updateUIWithUserData();
        } else {
            console.log("Server says not authenticated or missing user data.");
            isLoggedIn = false;
            currentUser = null;
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            redirectToHome(); // Redirect if not authenticated
        }
    })
    .catch(error => {
        console.error('Error checking authentication:', error);
        // If server check fails, rely on localStorage (if available) or redirect
        if (!isLoggedIn || !currentUser) { // Only redirect if we have no valid state
            redirectToHome();
        } else {
            console.warn("Using cached user data due to server auth check error.");
            updateUIWithUserData(); // Update UI with potentially stale cached data
        }
    });
}

/**
 * Redirects the user to the index.php page.
 */
function redirectToHome() {
    console.log("User not logged in or session invalid, redirecting to home...");
    // Prevent redirection loops if already on index.php
    if (!window.location.pathname.endsWith('/') && !window.location.pathname.endsWith('index.php')) {
       window.location.href = "index.php"; // Adjust if your home page has a different name
    } else {
       console.log("Already on index/home page, not redirecting.");
       // Hide dashboard-specific elements if necessary
       document.querySelectorAll('.dashboard-content, .dashboard-tab').forEach(el => el.style.display = 'none');
    }
}

/**
 * Updates various parts of the UI with the current user's data.
 */
function updateUIWithUserData() {
    console.log("Updating UI with user data:", currentUser);
    if (!currentUser) {
        console.warn("Attempted to update UI without user data.");
        return;
    }

    // Update display names
    const userDisplayNames = document.querySelectorAll('.user-display-name');
    userDisplayNames.forEach(el => {
        el.textContent = currentUser.name || currentUser.email || 'User';
    });

    // Update reward points display (uses its own internal logic)
    updateRewardPointsDisplay(currentUser.reward_points);

    // Fill the profile form (if on the profile tab)
    fillProfileForm();

    // Load other dynamic user data sections
    loadUserData();
}

/**
 * Fills the profile form fields with the current user's details.
 */
function fillProfileForm() {
    console.log("Attempting to fill profile form...");
    if (currentUser && document.getElementById('profile-form')) { // Check if form exists
        console.log("Filling profile form with data:", currentUser);
        // Get references to form elements
        const nameInput = document.getElementById('profile-name');
        const emailInput = document.getElementById('profile-email');
        const phoneInput = document.getElementById('profile-phone');
        const languageSelect = document.getElementById('profile-language');
        const notifyEmail = document.getElementById('notify-email');
        const notifySms = document.getElementById('notify-sms');
        const notifyPromotions = document.getElementById('notify-promotions');

        // Set values if elements exist
        if (nameInput) nameInput.value = currentUser.name || '';
        if (emailInput) emailInput.value = currentUser.email || '';
        if (phoneInput) phoneInput.value = currentUser.phone || '';
        if (languageSelect && currentUser.language) {
            languageSelect.value = currentUser.language;
        }

        // Set notification preferences
        if (notifyEmail) notifyEmail.checked = !!currentUser.preferences?.notify_email;
        if (notifySms) notifySms.checked = !!currentUser.preferences?.notify_sms;
        if (notifyPromotions) notifyPromotions.checked = !!currentUser.preferences?.notify_promotions;

        console.log("Profile form filled.");
    } else {
        console.warn("Cannot fill profile form - currentUser is null or form doesn't exist.");
    }
}

// --- Data Loading Functions ---

/**
 * Initiates loading of various user-specific data sections in parallel.
 */
function loadUserData() {
    console.log("Loading user-specific dashboard data...");
    if (!isLoggedIn) {
        console.warn("User not logged in, aborting data load.");
        return;
    }

    // Show a general loading indicator for the dashboard content area
    const dashboardContentArea = document.querySelector('.bg-gray-800.rounded-xl');
    if (dashboardContentArea && !dashboardContentArea.querySelector('.loading-placeholder')) {
        const placeholder = document.createElement('div');
        placeholder.className = 'loading-placeholder text-center py-10 text-gray-400';
        placeholder.innerHTML = 'Loading dashboard data...';
        // Insert placeholder at the beginning or manage visibility of tab content
    }
    // Or use the global indicator: showLoadingIndicator("Loading dashboard data...");


    // Load all data sections concurrently
    const promises = [
        loadRewardPoints(),
        loadSavedPlaces(),
        loadRideHistory(), // Load default view (e.g., page 1, filter 'all')
        loadPaymentMethods(),
        loadPendingPayments() // Load pending payments
    ];

    // Wait for all promises to settle (finish, regardless of success/failure)
    Promise.allSettled(promises)
        .then(results => {
            console.log("All dashboard data loading settled:", results);

            // Log any errors encountered during loading
            results.forEach((result, index) => {
                if (result.status === 'rejected') {
                    const section = ['Rewards', 'Places', 'History', 'Payments', 'Pending Payments'][index];
                    console.error(`Error loading ${section}:`, result.reason);
                    // Optionally display an error message in the relevant section
                }
            });
        })
        .finally(() => {
            // Hide the general loading indicator
            // if (dashboardContentArea) { ... remove placeholder ... }
            // Or use the global indicator: hideLoadingIndicator();
            console.log("Finished loading all dashboard data sections.");
        });
}

/**
 * Fetches and updates the user's reward points and related lists.
 * @returns {Promise}
 */
function loadRewardPoints() {
    console.log("Loading reward points...");
    const rewardsContainer = document.querySelector('#rewards-tab-content');
    const pointsDisplayElements = document.querySelectorAll('.user-reward-points-display'); // Use class selector

    // Show loading state in the rewards tab
    if (rewardsContainer) {
         // You might want a more specific loading indicator within the rewards tab
         // rewardsContainer.innerHTML = '<p class="text-gray-400 text-center py-4">Loading rewards...</p>';
    }
     pointsDisplayElements.forEach(el => el.textContent = '...'); // Indicate loading points

    return fetch('/api/api-reward-points.php?endpoint=reward-points', { // Ensure correct endpoint
        method: 'GET',
        headers: { /* ... headers ... */ },
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        console.log("Reward points response:", data);
        if (data.success && data.data) {
            // Update global currentUser object
            if(currentUser) {
                currentUser.reward_points = data.data.points ?? 0;
            }
            // Update UI elements
            updateRewardPointsDisplay(data.data.points);
            if (typeof updateRewardsList === 'function') {
                updateRewardsList(data.data.rewards || []);
            }
             if (typeof updateRedemptionHistory === 'function') {
                 updateRedemptionHistory(data.data.redeemed_rewards || []);
             }
        } else {
            console.error("Failed to load reward points:", data.message);
            updateRewardPointsDisplay('Error'); // Update display to show error
             if (rewardsContainer) {
                 rewardsContainer.innerHTML = `<p class="text-red-400 text-center py-4">Error loading rewards: ${data.message || 'Unknown error'}</p>`;
             }
            // Throw error to be caught by Promise.allSettled
            throw new Error(data.message || 'Failed to load reward points');
        }
        return data; // Return data for promise chain if needed
    })
    .catch(error => {
        console.error('Fetch error loading reward points:', error);
        updateRewardPointsDisplay('Error');
         if (rewardsContainer) {
             rewardsContainer.innerHTML = `<p class="text-red-400 text-center py-4">Could not connect to rewards service.</p>`;
         }
        throw error; // Re-throw for Promise.allSettled
    });
}

/**
 * Fetches and updates the user's saved places list.
 * @returns {Promise}
 */
function loadSavedPlaces() {
    console.log("Loading saved places...");
    const placesContainer = document.querySelector('#places-tab-content');
    if (placesContainer) {
         // Show loading state within the places tab
         // placesContainer.innerHTML = '<p class="text-gray-400 text-center py-4">Loading saved places...</p>';
    }

    return fetch('/api/api-saved-places.php?endpoint=saved-places', { // Ensure correct endpoint
        method: 'GET',
        headers: { /* ... headers ... */ },
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        console.log("Saved places response:", data);
        if (data.success && data.data) {
             if (typeof updateSavedPlacesUI === 'function') {
                updateSavedPlacesUI(data.data.places || []);
             }
        } else {
            console.error("Failed to load saved places:", data.message);
             if (typeof updateSavedPlacesUI === 'function') {
                 updateSavedPlacesUI([]); // Show empty state on error
             }
             if (placesContainer) {
                 // Optionally show error message above the empty state/form
                 const errorDiv = document.createElement('p');
                 errorDiv.className = 'text-red-400 text-center pb-4';
                 errorDiv.textContent = `Error loading saved places: ${data.message || 'Unknown error'}`;
                 placesContainer.prepend(errorDiv);
             }
            throw new Error(data.message || 'Failed to load saved places');
        }
        return data;
    })
    .catch(error => {
        console.error('Fetch error loading saved places:', error);
         if (typeof updateSavedPlacesUI === 'function') {
             updateSavedPlacesUI([]); // Show empty state on error
         }
         if (placesContainer) {
             placesContainer.innerHTML = `<p class="text-red-400 text-center py-4">Could not connect to saved places service.</p>`;
         }
        throw error; // Re-throw
    });
}

/**
 * Fetches and updates the user's ride history.
 * @param {number} [page=1]
 * @param {string} [filter='all']
 * @returns {Promise}
 */
function loadRideHistory(page = 1, filter = 'all') {
    console.log(`Loading ride history - Page: ${page}, Filter: ${filter}`);
    const ridesContainer = document.querySelector('#rides-tab-content');
    if (ridesContainer) {
         // Show loading state
         ridesContainer.innerHTML = '<p class="text-gray-400 text-center py-8">Loading ride history...</p>';
    }

    const apiUrl = new URL('/api/api-ride-history.php', window.location.origin);
    apiUrl.searchParams.append('page', page);
    apiUrl.searchParams.append('filter', filter);
    apiUrl.searchParams.append('endpoint', 'ride-history'); // Ensure endpoint is specified if needed by router

    return fetch(apiUrl.toString(), {
        method: 'GET',
        headers: { /* ... headers ... */ },
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        console.log("Ride history response:", data);
        if (data.success && data.data) {
             if (typeof updateRideHistoryUI === 'function') {
                updateRideHistoryUI(data.data); // Pass the inner 'data' object
             }
        } else {
            console.error("Failed to load ride history:", data.message);
             if (typeof updateRideHistoryUI === 'function') {
                 // Pass structure to show error message within the UI function
                 updateRideHistoryUI({ rides: [], pagination: null, filter: filter, error: data.message || 'Failed to load history.' });
             }
            throw new Error(data.message || 'Failed to load ride history');
        }
        return data;
    })
    .catch(error => {
        console.error('Fetch error loading ride history:', error);
         if (typeof updateRideHistoryUI === 'function') {
              updateRideHistoryUI({ rides: [], pagination: null, filter: filter, error: 'Could not connect to ride history service.' });
         }
        throw error; // Re-throw
    });
}

/**
 * Updates the UI to display ride history, filter buttons, and pagination.
 * Fixed to properly attach click handlers to ride detail items.
 * @param {object} data - Object containing rides, pagination info, and current filter.
 */
function updateRideHistoryUI(data) {
    console.log(">>> updateRideHistoryUI START. Received:", data);

    const ridesContainer = document.querySelector('#rides-tab-content');
    if (!ridesContainer) {
        console.error("[UI Update Error] Rides tab content container '#rides-tab-content' not found!");
        return;
    }
    console.log("[UI Update Debug] Found rides container:", ridesContainer);

    // Clear existing content
    ridesContainer.innerHTML = '';

    // Add filter options (existing code...)
    try {
        const filterOptions = document.createElement('div');
        filterOptions.className = 'mb-4 flex flex-wrap gap-2 pb-2';
        const currentFilter = data?.filter || 'all';
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
            button.className = `py-1 px-3 rounded-full border text-sm transition duration-200 ${
                isActive
                    ? 'active bg-primary-600 border-primary-500 text-white font-medium cursor-default'
                    : 'bg-gray-700 border-gray-600 text-gray-300 hover:bg-gray-600 hover:border-gray-500 hover:text-white'
            }`;
            button.textContent = f.label;
            button.disabled = isActive;

            button.addEventListener('click', () => {
                if (typeof loadRideHistory === 'function') {
                    loadRideHistory(1, f.key);
                } else {
                    console.error("loadRideHistory function is not defined.");
                }
            });
            filterOptions.appendChild(button);
        });

        ridesContainer.appendChild(filterOptions);
        console.log("[UI Update Debug] Filter buttons added.");

        if (data?.error) {
            const errorDiv = document.createElement('p');
            errorDiv.className = 'text-red-400 text-center py-4';
            errorDiv.textContent = data.error;
            ridesContainer.appendChild(errorDiv);
        }
    } catch (filterError) {
        console.error("[UI Update Error] Failed to create filter buttons:", filterError);
        ridesContainer.innerHTML += '<p class="text-red-500">Error displaying filters.</p>';
    }

    // Display Rides List
    const rides = data?.rides;
    if (rides && Array.isArray(rides) && rides.length > 0) {
        console.log(`[UI Update Debug] Processing ${rides.length} rides.`);
        const ridesList = document.createElement('div');
        ridesList.className = 'space-y-4';

        rides.forEach((ride, index) => {
            console.log(`[UI Update Debug] Rendering ride index ${index}, ID: ${ride?.id}`);
            try {
                const rideStatusUI = getRideStatusUI(ride?.status);
                const rideItem = document.createElement('div');
                rideItem.className = 'ride-history-item bg-gray-700/50 rounded-lg p-4 border border-gray-600 relative cursor-pointer hover:bg-gray-600/50 transition-colors duration-200';
                rideItem.dataset.rideId = ride?.id ?? `unknown-${index}`;

                // Create ride item content
                const pickupText = ride?.pickup || 'N/A';
                const dropoffText = ride?.dropoff || 'N/A';
                const formattedDate = ride?.formatted_date || 'N/A';
                const formattedTime = ride?.formatted_time || '';
                const formattedFare = ride?.formatted_fare || 'N/A';
                const driverName = ride?.driver_name;
                const vehicleType = ride?.vehicle_type;
                const rideId = ride?.id;

                // Define cancellable statuses
                const cancellableStatuses = ['searching', 'confirmed', 'arriving', 'scheduled'];
                const isCancellable = ride?.status && cancellableStatuses.includes(ride.status.toLowerCase());

                // Create ride item HTML
                rideItem.innerHTML = `
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap gap-x-3 gap-y-1 items-center mb-2">
                                <span class="text-sm font-medium text-white whitespace-nowrap">${formattedDate}</span>
                                <span class="text-sm text-gray-400 whitespace-nowrap">${formattedTime}</span>
                                ${rideStatusUI || ''}
                            </div>
                            <div class="mb-1">
                                <p class="text-gray-300 text-sm truncate" title="${pickupText}">
                                    <span class="lucide text-xs mr-1 text-green-400" aria-hidden="true">&#xea4b;</span> ${pickupText}
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-300 text-sm truncate" title="${dropoffText}">
                                    <span class="lucide text-xs mr-1 text-red-400" aria-hidden="true">&#xea4a;</span> ${dropoffText}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col items-start md:items-end mt-2 md:mt-0 flex-shrink-0">
                            <div class="text-lg font-medium text-white mb-1">${formattedFare}</div>
                            ${driverName ? `<div class="text-sm text-gray-400">Driver: ${driverName}</div>` : ''}
                            ${vehicleType ? `<div class="text-xs text-gray-500">${vehicleType}</div>` : ''}
                        </div>
                    </div>

                    <div class="absolute right-3 top-3 flex flex-col space-y-1">
                        <button class="view-details-btn text-gray-400 hover:text-primary-400 transition duration-200 text-xs px-1 py-0.5 rounded opacity-70 hover:opacity-100 flex items-center gap-1" title="View ride details">
                            <span class="lucide text-xs pointer-events-none" aria-hidden="true">&#xea70;</span> Details
                        </button>
                        ${isCancellable ? `
                        <button
                            class="cancel-ride-btn bg-red-600 hover:bg-red-700 text-white text-xs py-0.5 px-2 rounded transition duration-300 opacity-70 hover:opacity-100 flex items-center gap-1"
                            data-ride-id="${rideId}"
                            title="Cancel this ride"
                        >
                            <span class="lucide text-xs pointer-events-none" aria-hidden="true">&#xea76;</span> Cancel
                        </button>
                        ` : ''}
                    </div>
                `;

                // CRITICAL FIX: Improved click handler attachment
                // Add click handler to entire ride item, excluding buttons
                rideItem.addEventListener('click', function(e) {
                    // Only trigger if click is not on a button
                    if (!e.target.closest('button')) {
                        console.log("Ride item clicked, opening details for ID:", this.dataset.rideId);
                        
                        // Get ride ID from the dataset
                        const rideId = this.dataset.rideId;
                        
                        if (rideId) {
                            // Fetch details from API if possible, otherwise use basic ride data
                            if (typeof fetchRideDetails === 'function') {
                                fetchRideDetails(rideId)
                                    .then(rideDetails => {
                                        console.log("Fetched ride details:", rideDetails);
                                        openRideDetails(rideDetails);
                                    })
                                    .catch(error => {
                                        console.warn("Could not fetch detailed ride info:", error);
                                        // Use the basic ride data as fallback
                                        openRideDetails(ride);
                                    });
                            } else {
                                // Fallback if fetchRideDetails function is not available
                                console.log("fetchRideDetails not available, using basic ride data");
                                openRideDetails(ride);
                            }
                        } else {
                            console.error("No ride ID found for this item");
                        }
                    }
                });

                // Add separate click handler for view details button
                const viewDetailsBtn = rideItem.querySelector('.view-details-btn');
                if (viewDetailsBtn) {
                    viewDetailsBtn.addEventListener('click', function(e) {
                        e.stopPropagation(); // Prevent triggering the parent's click
                        console.log("View details button clicked for ride:", rideId);
                        
                        if (rideId && typeof fetchRideDetails === 'function') {
                            fetchRideDetails(rideId)
                                .then(rideDetails => openRideDetails(rideDetails))
                                .catch(error => {
                                    console.warn("Error fetching ride details:", error);
                                    openRideDetails(ride);
                                });
                        } else {
                            // Fallback to using the basic ride data
                            openRideDetails(ride);
                        }
                    });
                }

                // Add click handler for cancel button if present
                const cancelButton = rideItem.querySelector('.cancel-ride-btn');
                if (cancelButton) {
                    cancelButton.addEventListener('click', function(e) {
                        e.stopPropagation(); // Prevent triggering the parent's click
                        const rideIdToCancel = this.dataset.rideId;
                        if (rideIdToCancel && typeof cancelRideFromDashboard === 'function') {
                            cancelRideFromDashboard(rideIdToCancel);
                        } else {
                            console.error("Cannot cancel ride: Invalid ride ID or cancel function missing");
                        }
                    });
                }

                ridesList.appendChild(rideItem);
            } catch (renderError) {
                console.error(`[UI Update Error] Failed to render ride index ${index}:`, renderError, ride);
                const errorItem = document.createElement('div');
                errorItem.className = 'p-4 border border-red-500/50 bg-red-900/30 rounded text-red-300 text-sm';
                errorItem.textContent = `Error displaying ride ID ${ride?.id || 'N/A'}.`;
                ridesList.appendChild(errorItem);
            }
        });
        
        ridesContainer.appendChild(ridesList);
        console.log("[UI Update Debug] Rides list appended.");
    } else if (!data?.error) {
        // Show empty state
        console.log("[UI Update Debug] No rides to display, showing empty state.");
        const emptyState = document.createElement('div');
        emptyState.className = 'text-center py-8 text-gray-400';
        const currentFilter = data?.filter || 'all';
        let emptyMessage = `<p>No rides found matching the filter '${currentFilter}'.</p>`;
        if (currentFilter === 'all') {
            emptyMessage = `<span class="lucide text-4xl mb-2 block mx-auto">&#xea5e;</span><p>You don't have any ride history yet.</p><p class="mt-2">Book your first ride!</p>`;
        }
        emptyState.innerHTML = emptyMessage;
        ridesContainer.appendChild(emptyState);
    }

    // --- Add Pagination Controls ---
    try {
        const paginationData = data?.pagination; // Safely access pagination
        if (paginationData && paginationData.total_pages > 1) {
            console.log("[UI Update Debug] Adding pagination controls.");
            const pagination = document.createElement('div');
            pagination.className = 'mt-6 flex justify-center items-center gap-2';
            const currentPage = paginationData.current_page;
            const totalPages = paginationData.total_pages;
            const currentFilter = data?.filter || 'all'; // Get filter for pagination links

            // Previous Button
            if (currentPage > 1) {
                const prevButton = document.createElement('button');
                prevButton.className = 'p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500';
                prevButton.innerHTML = '<span class="lucide pointer-events-none" aria-hidden="true">&#xeaa2;</span>'; // Chevron Left
                prevButton.setAttribute('aria-label', 'Previous Page');
                prevButton.addEventListener('click', () => loadRideHistory(currentPage - 1, currentFilter)); // Pass filter
                pagination.appendChild(prevButton);
            } else {
                 const disabledPrev = document.createElement('span');
                 disabledPrev.className = 'p-2 rounded-lg bg-gray-800 text-gray-600 cursor-not-allowed';
                 disabledPrev.innerHTML = '<span class="lucide" aria-hidden="true">&#xeaa2;</span>';
                 pagination.appendChild(disabledPrev);
            }

            // Page Info with proper class for reference
            const pageInfoWrapper = document.createElement('span');
            pageInfoWrapper.className = 'pagination-info text-gray-400 text-sm px-3';
            // Add a specific class to the current page span for easier reference
            pageInfoWrapper.innerHTML = `Page <span class="pagination-current-page font-medium text-white">${currentPage}</span> of ${totalPages}`;
            pagination.appendChild(pageInfoWrapper);

            // Next Button
            if (currentPage < totalPages) {
                const nextButton = document.createElement('button');
                nextButton.className = 'p-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500';
                nextButton.innerHTML = '<span class="lucide pointer-events-none" aria-hidden="true">&#xeaa0;</span>'; // Chevron Right
                nextButton.setAttribute('aria-label', 'Next Page');
                nextButton.addEventListener('click', () => loadRideHistory(currentPage + 1, currentFilter)); // Pass filter
                pagination.appendChild(nextButton);
            } else {
                const disabledNext = document.createElement('span');
                disabledNext.className = 'p-2 rounded-lg bg-gray-800 text-gray-600 cursor-not-allowed';
                disabledNext.innerHTML = '<span class="lucide" aria-hidden="true">&#xeaa0;</span>';
                pagination.appendChild(disabledNext);
            }
            ridesContainer.appendChild(pagination);
        } else {
            console.log("[UI Update Debug] No pagination needed.");
        }
    } catch(paginationError) {
         console.error("[UI Update Error] Failed to create pagination:", paginationError);
         ridesContainer.innerHTML += '<p class="text-red-500 text-center">Error displaying pagination.</p>';
    }

    console.log(">>> updateRideHistoryUI END");
}

/**
 * Generates an HTML span element for the ride status badge.
 * @param {string} status - The status string (e.g., 'completed', 'cancelled').
 * @returns {string} - An HTML string for the status badge.
 */
function getRideStatusUI(status) {
    let statusClass = 'bg-gray-600/20 text-gray-300 border border-gray-500/30'; // Default
    let statusText = 'Unknown';
    let icon = '&#xea91;'; // Help Circle icon

    // Normalize status to lowercase for reliable comparison
    const lowerStatus = status?.toLowerCase() || 'unknown';

    switch (lowerStatus) {
        case 'completed':
            statusClass = 'bg-green-600/20 text-green-300 border border-green-500/30';
            statusText = 'Completed';
            icon = '&#xea6d;'; // Check Circle icon
            break;
        case 'cancelled': // Allow both spellings
        case 'canceled':
            statusClass = 'bg-red-600/20 text-red-300 border border-red-500/30';
            statusText = 'Canceled';
            icon = '&#xea76;'; // X Circle icon
            break;
        case 'in_progress':
        case 'on_trip': // Alias if used
            statusClass = 'bg-blue-600/20 text-blue-300 border border-blue-500/30';
            statusText = 'In Progress';
            icon = '&#xea5e;'; // Car icon
            break;
        case 'scheduled':
            statusClass = 'bg-purple-600/20 text-purple-300 border border-purple-500/30';
            statusText = 'Scheduled';
            icon = '&#xea66;'; // Calendar icon
            break;
        case 'searching':
            statusClass = 'bg-yellow-600/20 text-yellow-300 border border-yellow-500/30 animate-pulse'; // Pulse effect
            statusText = 'Finding Driver';
            icon = '&#xeab1;'; // Search icon
            break;
        case 'confirmed':
            statusClass = 'bg-cyan-600/20 text-cyan-300 border border-cyan-500/30';
            statusText = 'Confirmed';
            icon = '&#xea6c;'; // Check icon
            break;
        case 'arriving':
            statusClass = 'bg-teal-600/20 text-teal-300 border border-teal-500/30';
            statusText = 'Driver Arriving';
            icon = '&#xea4b;'; // Pin (start) icon
            break;
        case 'arrived': // Added 'arrived' status styling
            statusClass = 'bg-pink-600/20 text-pink-300 border border-pink-500/30';
            statusText = 'Driver Arrived';
            icon = '&#xe9cd;'; // Target icon
            break;
        default:
            // Capitalize the first letter if status is provided but unknown
            statusText = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
    }

    // Return the HTML string for the badge
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                ${icon ? `<span class="lucide text-xs mr-1" aria-hidden="true">${icon}</span>` : ''}
                ${statusText}
            </span>`;
}


/**
 * Fetches and updates the user's saved payment methods.
 * @returns {Promise}
 */
function loadPaymentMethods() {
    console.log("Loading payment methods...");
    const paymentContainer = document.querySelector('#payment-tab-content');
     if (paymentContainer) {
         // paymentContainer.innerHTML = '<p class="text-gray-400 text-center py-4">Loading payment methods...</p>';
     }

    return fetch('/api/api-payment-methods.php?endpoint=payment-methods', { // Ensure correct endpoint
        method: 'GET',
        headers: { /* ... headers ... */ },
        credentials: 'include'
    })
    .then(response => {
         // Handle non-JSON responses or specific errors like 404/501 gracefully
         if (response.status === 404 || response.status === 501) {
             console.warn(`Payment methods API endpoint not found or not implemented (${response.status}).`);
             return { success: true, data: { payment_methods: [] }, message: 'Payment methods feature not available.' };
         }
         if (!response.ok) { throw new Error(`Server error ${response.status}`); }
         return response.json();
     })
    .then(data => {
        console.log("Payment methods response:", data);
        const methods = data?.data?.payment_methods || [];
         if (typeof updatePaymentMethodsUI === 'function') {
            updatePaymentMethodsUI(methods);
         }
        // Handle potential message from 404/501 case
        if (data.message === 'Payment methods feature not available.') {
             // Optionally display this message more prominently in the UI if needed
        } else if (!data.success) {
             console.error("Failed to load payment methods:", data.message);
              if (paymentContainer) {
                  paymentContainer.innerHTML = `<p class="text-red-400 text-center py-4">Error: ${data.message || 'Failed to load.'}</p>`;
              }
             throw new Error(data.message || 'Failed to load payment methods');
        }
        return data;
    })
    .catch(error => {
        console.error('Fetch error loading payment methods:', error);
         if (typeof updatePaymentMethodsUI === 'function') {
             updatePaymentMethodsUI([]); // Show empty/error state
         }
          if (paymentContainer) {
              paymentContainer.innerHTML = `<p class="text-red-400 text-center py-4">Could not connect to payment methods service.</p>`;
          }
        throw error; // Re-throw
    });
}

/**
 * Fetches and displays pending payments for the logged-in user.
 * @returns {Promise}
 */
function loadPendingPayments() {
    console.log("Loading pending payments...");
    const listContainer = document.getElementById('pending-payments-list');
    if (!listContainer) {
        console.warn("Pending payments list container not found.");
        return Promise.resolve(); // Resolve immediately if container missing
    }

    listContainer.innerHTML = '<p class="text-gray-400 text-center py-4">Loading pending payments...</p>';

    // Use the new API endpoint (note the updated URL)
    return fetch('/api/api-pending-payments.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) throw new Error(`Server error ${response.status}`);
        return response.json();
    })
    .then(data => {
        console.log("Pending payments response:", data);
        if (data.success) {
            displayPendingPayments(data.payments || []);
        } else {
            listContainer.innerHTML = `<p class="text-red-400 text-center py-4">${data.message || 'Could not load pending payments.'}</p>`;
            throw new Error(data.message || 'Failed to load pending payments');
        }
        return data;
    })
    .catch(error => {
        console.error('Fetch error loading pending payments:', error);
        listContainer.innerHTML = '<p class="text-red-400 text-center py-4">Error connecting to server for pending payments.</p>';
        throw error; // Re-throw
    });
}

/**
 * Updates the UI to display saved payment methods and the add method section.
 * @param {Array} paymentMethods - An array of payment method objects.
 */
function updatePaymentMethodsUI(paymentMethods) {
    console.log("Updating payment methods UI", paymentMethods);
    const paymentContainer = document.querySelector('#payment-tab-content');
    if (!paymentContainer) {
        console.warn("Payment tab content container not found.");
        return;
    }
    paymentContainer.innerHTML = ''; // Clear

    const methodsListWrapper = document.createElement('div');
    methodsListWrapper.className = 'payment-methods-list-wrapper space-y-4 mb-6';

    if (paymentMethods && paymentMethods.length > 0) {
        paymentMethods.forEach(method => {
            const methodItem = document.createElement('div');
            methodItem.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3';
            methodItem.dataset.methodId = method.id;

            let cardIcon = '&#xeaa4;'; // Default: Credit Card
            let cardBrandClass = 'text-gray-300';
            const lowerType = method.type?.toLowerCase();

            if (lowerType === 'paypal') { cardIcon = '&#xec8f;'; cardBrandClass = 'text-blue-400'; }
            // Add more icons based on type or brand if available

            methodItem.innerHTML = `
                <div class="flex items-center flex-1 min-w-0">
                    <span class="lucide text-2xl mr-3 ${cardBrandClass}" aria-hidden="true">${cardIcon}</span>
                    <div class="flex-1">
                        <p class="font-medium text-white truncate" title="${method.name || ''}">${method.name || 'Payment Method'}</p>
                        <p class="text-sm text-gray-400">
                            ${lowerType === 'card' ? `**** **** **** ${method.last4 || '****'}` : (method.email || 'N/A')}
                        </p>
                    </div>
                </div>
                <div class="mt-3 sm:mt-0 flex items-center space-x-2 flex-shrink-0 self-end sm:self-center">
                    ${method.is_default ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-500/20 text-primary-300 border border-primary-500/30">Default</span>' : ''}
                    ${!method.is_default ? `<button class="set-default-payment-btn text-xs text-gray-400 hover:text-primary-300" data-method-id="${method.id}" title="Set as default">Set Default</button>` : ''}
                    <button class="delete-payment-btn p-1.5 rounded-lg bg-gray-600 hover:bg-red-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-red-500" title="Delete Payment Method" data-method-id="${method.id}" data-method-name="${method.name || 'this method'}">
                        <span class="lucide text-lg pointer-events-none" aria-hidden="true">&#xea0f;</span> </button>
                </div>
            `;
            methodsListWrapper.appendChild(methodItem);
        });
        attachPaymentActionListeners(); // Attach listeners after rendering
    } else {
        methodsListWrapper.innerHTML = `
            <div class="text-center py-6 text-gray-400">
                 <span class="lucide text-4xl mb-2 block mx-auto" aria-hidden="true">&#xeaa4;</span> <p>No payment methods added yet.</p>
                <p class="mt-1 text-sm">Add a payment method for easier checkout.</p>
            </div>`;
    }
    paymentContainer.appendChild(methodsListWrapper);

    // Ensure the "Add Payment Method" section (placeholder) is present
    ensureAddPaymentMethodSection(paymentContainer);
}

/**
 * Ensures the 'Add Payment Method' placeholder section exists.
 * @param {HTMLElement} container - The parent element (#payment-tab-content).
 */
function ensureAddPaymentMethodSection(container) {
    let addSection = container.querySelector('.add-payment-method-section');
    if (!addSection) {
        addSection = document.createElement('div');
        addSection.className = 'add-payment-method-section mt-6 pt-6 border-t border-gray-700';
        addSection.innerHTML = `
            <h3 class="text-lg font-medium text-white mb-4">Add Payment Method</h3>
            <div class="bg-blue-900/30 rounded-lg p-4 border border-blue-700 text-center">
                <p class="text-blue-200 mb-2">Adding new payment methods securely via the dashboard is coming soon!</p>
                <p class="text-blue-300 text-sm">Currently, methods might be added during the checkout process.</p>
            </div>
        `;
        container.appendChild(addSection);
    }
}

/**
 * Attaches listeners to dynamically created payment action buttons.
 */
function attachPaymentActionListeners() {
    document.querySelectorAll('#payment-tab-content .delete-payment-btn').forEach(button => {
         const newButton = button.cloneNode(true); button.parentNode.replaceChild(newButton, button);
         newButton.addEventListener('click', (e) => {
             const methodId = e.currentTarget.dataset.methodId;
             const methodName = e.currentTarget.dataset.methodName;
              if (typeof deletePaymentMethod === 'function') deletePaymentMethod(methodId, methodName);
         });
    });
    document.querySelectorAll('#payment-tab-content .set-default-payment-btn').forEach(button => {
         const newButton = button.cloneNode(true); button.parentNode.replaceChild(newButton, button);
         newButton.addEventListener('click', (e) => {
             const methodId = e.currentTarget.dataset.methodId;
             console.log("Set default payment method ID:", methodId);
             // Implement API call to set default
             // setDefaultPaymentMethod(methodId);
             showConfirmation("Set default functionality requires backend endpoint.");
         });
    });
}

// --- Delete Action Function (needs API endpoint) ---
function deletePaymentMethod(id, name) { /* ... Confirm, then API call using DELETE ... */ console.log("API call to delete payment method:", {id, name}); showConfirmation("Delete payment method functionality requires backend endpoint."); loadPaymentMethods(); /* Refresh list */ }
// function setDefaultPaymentMethod(id) { /* ... API call using PUT/POST ... */ console.log("API call to set default method:", id); showConfirmation("Set default functionality requires backend endpoint."); loadPaymentMethods(); /* Refresh list */ }

/**
 * Updates the display of reward points in the UI.
 * @param {number|string|undefined} points - The number of points to display, or undefined/null. Can be 'Error'.
 */
function updateRewardPointsDisplay(points) {
    console.log("Updating reward points display with:", points);
    const pointsElements = document.querySelectorAll('.user-reward-points-display'); // Class selector

    if (pointsElements.length === 0) {
        console.warn("No reward points display elements found.");
        // Still update currentUser if points value is valid
         if (currentUser && typeof points === 'number') {
             currentUser.reward_points = points;
             localStorage.setItem('currentUser', JSON.stringify(currentUser)); // Update local storage
         }
        return;
    }

    let displayValue = '---'; // Default placeholder
    if (points !== undefined && points !== null) {
        if (typeof points === 'number') {
            displayValue = points.toLocaleString();
             // Update currentUser object and localStorage
             if (currentUser) {
                 currentUser.reward_points = points;
                  localStorage.setItem('currentUser', JSON.stringify(currentUser));
             }
        } else if (typeof points === 'string') {
            displayValue = points; // Allow displaying strings like "Error"
        }
    } else {
         // If points are explicitly null/undefined, maybe fetch them again? Or show placeholder.
         // For now, keep the placeholder.
         console.log("Points value is null or undefined, showing placeholder.");
    }


    pointsElements.forEach(el => {
        el.textContent = displayValue;
    });
    console.log("Reward points display updated to:", displayValue);
}

/**
 * Updates the UI to display the available rewards.
 * @param {Array} rewards - An array of reward objects.
 */
function updateRewardsList(rewards) {
    console.log("Updating rewards list UI", rewards);
    const rewardsContainer = document.querySelector('#rewards-tab-content .rewards-list-container');
    if (!rewardsContainer) {
        console.warn("Rewards list container not found.");
        return;
    }
    rewardsContainer.innerHTML = ''; // Clear

    if (!rewards || rewards.length === 0) {
        rewardsContainer.innerHTML = '<p class="text-gray-400 text-center py-4">No rewards available currently.</p>';
        return;
    }

    rewards.forEach(reward => {
        const userPoints = currentUser?.reward_points ?? 0;
        const pointsRequired = parseInt(reward.points_required) || 0; // Ensure number
        const canRedeem = userPoints >= pointsRequired;

        const rewardElement = document.createElement('div');
        rewardElement.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-3';

        rewardElement.innerHTML = `
            <div class="flex-1">
                <h4 class="font-medium text-white">${reward.title || 'Reward'}</h4>
                <p class="text-gray-400 text-sm mt-1">${reward.description || 'No description.'}</p>
            </div>
            <div class="mt-3 md:mt-0 flex items-center flex-shrink-0">
                <span class="text-yellow-400 font-semibold mr-3">${pointsRequired.toLocaleString()} points</span>
                <button
                    class="redeem-reward-btn bg-primary-500 hover:bg-primary-600 text-white font-medium py-1 px-4 rounded-lg transition duration-300 shadow-md text-sm ${!canRedeem ? 'opacity-50 cursor-not-allowed' : 'hover:scale-105'}"
                    data-reward-id="${reward.id}"
                    ${!canRedeem ? 'disabled title="Not enough points"' : 'title="Redeem this reward"'}
                >
                    Redeem
                </button>
            </div>
        `;

        if (canRedeem) {
            const redeemButton = rewardElement.querySelector('.redeem-reward-btn');
            redeemButton?.addEventListener('click', () => redeemReward(reward.id));
        }

        rewardsContainer.appendChild(rewardElement);
    });
}

/**
 * Updates the display of reward points in the UI.
 * @param {number|string|undefined} points - The number of points to display, or undefined/null. Can be 'Error'.
 */
function updateRewardPointsDisplay(points) {
    console.log("Updating reward points display with:", points);
    const pointsElements = document.querySelectorAll('.user-reward-points-display'); // Use class selector

    if (pointsElements.length === 0) {
        // Points display elements might be within the rewards tab, which might not be built yet
        // We'll update the currentUser object, and the UI will pick it up when the tab is rendered
         if (currentUser && typeof points === 'number') {
             currentUser.reward_points = points;
             localStorage.setItem('currentUser', JSON.stringify(currentUser));
         }
        console.warn("No elements found with class 'user-reward-points-display' at this time.");
        return;
    }

    let displayValue = '---'; // Default placeholder
    if (points !== undefined && points !== null) {
        if (typeof points === 'number') {
            displayValue = points.toLocaleString();
             // Update currentUser object and localStorage
             if (currentUser) {
                 currentUser.reward_points = points;
                  localStorage.setItem('currentUser', JSON.stringify(currentUser));
             }
        } else if (typeof points === 'string') {
            displayValue = points; // Allow strings like "Error"
        }
    } else {
         console.log("Points value is null or undefined, showing placeholder.");
    }

    pointsElements.forEach(el => {
        el.textContent = displayValue;
    });
    console.log("Reward points display updated to:", displayValue);
}


/**
 * Updates the UI to display the available rewards.
 * @param {Array} rewards - An array of reward objects.
 */
function updateRewardsList(rewards) {
    console.log("Updating rewards list UI", rewards);
    const rewardsContainer = document.querySelector('#rewards-tab-content .rewards-list-container');
    if (!rewardsContainer) {
        console.warn("Rewards list container not found.");
        return;
    }
    rewardsContainer.innerHTML = ''; // Clear

    if (!rewards || rewards.length === 0) {
        rewardsContainer.innerHTML = '<p class="text-gray-400 text-center py-4">No rewards available currently.</p>';
        return;
    }

    rewards.forEach(reward => {
        const userPoints = currentUser?.reward_points ?? 0;
        const pointsRequired = parseInt(reward.points_required) || 0; // Use correct field name
        const canRedeem = userPoints >= pointsRequired;

        const rewardElement = document.createElement('div');
        rewardElement.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-3';

        rewardElement.innerHTML = `
            <div class="flex-1">
                <h4 class="font-medium text-white">${reward.title || 'Reward'}</h4>
                <p class="text-gray-400 text-sm mt-1">${reward.description || 'No description.'}</p>
            </div>
            <div class="mt-3 md:mt-0 flex items-center flex-shrink-0">
                <span class="text-yellow-400 font-semibold mr-3">${pointsRequired.toLocaleString()} points</span>
                <button
                    class="redeem-reward-btn bg-primary-500 hover:bg-primary-600 text-white font-medium py-1 px-4 rounded-lg transition duration-300 shadow-md text-sm ${!canRedeem ? 'opacity-50 cursor-not-allowed' : 'hover:scale-105'}"
                    data-reward-id="${reward.id}"
                    ${!canRedeem ? 'disabled title="Not enough points"' : 'title="Redeem this reward"'}
                >
                    Redeem
                </button>
            </div>
        `;

        if (canRedeem) {
            const redeemButton = rewardElement.querySelector('.redeem-reward-btn');
            // Ensure listener isn't duplicated if re-rendering
            const newButton = redeemButton.cloneNode(true);
            redeemButton.parentNode.replaceChild(newButton, redeemButton);
            newButton.addEventListener('click', () => redeemReward(reward.id));
        }

        rewardsContainer.appendChild(rewardElement);
    });
}

/**
 * Updates the UI to display the user's redeemed rewards history.
 * @param {Array} redeemedRewards - An array of redeemed reward objects.
 */
function updateRedemptionHistory(redeemedRewards) {
    console.log("Updating redemption history UI:", redeemedRewards);
    const historyContainer = document.querySelector('#rewards-tab-content .redemption-history-container');
    if (!historyContainer) {
        console.warn("Redemption history container not found.");
        return;
    }
    historyContainer.innerHTML = ''; // Clear

    if (!redeemedRewards || redeemedRewards.length === 0) {
        historyContainer.innerHTML = '<p class="text-gray-400 text-center py-4">You haven\'t redeemed any rewards yet.</p>';
        return;
    }

    const historyList = document.createElement('ul');
    historyList.className = 'space-y-3';

    redeemedRewards.forEach(item => {
        const listItem = document.createElement('li');
        listItem.className = 'bg-gray-700/30 rounded-md p-3 border border-gray-600/50 flex justify-between items-center text-sm';

        let formattedDate = 'Date unknown';
        if (item.redeemed_at) { // Check field name from your API response
            try {
                formattedDate = new Date(item.redeemed_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            } catch (e) { console.warn("Could not parse redemption date:", item.redeemed_at); }
        }

        listItem.innerHTML = `
            <div>
                <span class="font-medium text-white">${item.title || 'Reward'}</span>
                <span class="text-gray-400 ml-2">(${parseInt(item.points_used) || '?'} points)</span>
            </div>
            <span class="text-gray-400">${formattedDate}</span>
        `;
        historyList.appendChild(listItem);
    });

    historyContainer.appendChild(historyList);
}

// --- Redeem Action Function (needs API endpoint) ---
function redeemReward(rewardId) { /* ... Confirm, API call using POST ... */ console.log("API call to redeem reward:", rewardId); showConfirmation("Redeem reward functionality requires backend endpoint."); loadRewardPoints(); /* Refresh points & lists */ }

/**
 * Updates the UI to display the user's redeemed rewards history.
 * @param {Array} redeemedRewards - An array of redeemed reward objects.
 */
function updateRedemptionHistory(redeemedRewards) {
    console.log("Updating redemption history UI:", redeemedRewards);
    const historyContainer = document.querySelector('#rewards-tab-content .redemption-history-container');
    if (!historyContainer) {
        console.warn("Redemption history container not found.");
        return;
    }
    historyContainer.innerHTML = ''; // Clear

    if (!redeemedRewards || redeemedRewards.length === 0) {
        historyContainer.innerHTML = '<p class="text-gray-400 text-center py-4">You haven\'t redeemed any rewards yet.</p>';
        return;
    }

    const historyList = document.createElement('ul');
    historyList.className = 'space-y-3';

    redeemedRewards.forEach(item => {
        const listItem = document.createElement('li');
        listItem.className = 'bg-gray-700/30 rounded-md p-3 border border-gray-600/50 flex justify-between items-center text-sm';

        let formattedDate = 'Date unknown';
        if (item.redeemed_at) { // Use the correct field name from API
            try {
                formattedDate = new Date(item.redeemed_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            } catch (e) { console.warn("Could not parse redemption date:", item.redeemed_at); }
        }

        listItem.innerHTML = `
            <div>
                <span class="font-medium text-white">${item.title || 'Reward'}</span>
                <span class="text-gray-400 ml-2">(${parseInt(item.points_used) || '?'} points)</span>
            </div>
            <span class="text-gray-400">${formattedDate}</span>
        `;
        historyList.appendChild(listItem);
    });

    historyContainer.appendChild(historyList);
}

/**
 * Updates the UI for saved places.
 * @param {Array} places
 */
function updateSavedPlacesUI(places) {
    console.log("Updating saved places UI", places);
    const placesContainer = document.querySelector('#places-tab-content');
    if (!placesContainer) {
        console.warn("Places tab content container not found.");
        return;
    }

    let placesWrapper = placesContainer.querySelector('.saved-places-list-wrapper');
    if (!placesWrapper) {
        placesWrapper = document.createElement('div');
        placesWrapper.className = 'saved-places-list-wrapper space-y-4 mb-6';
        const form = placesContainer.querySelector('.add-place-form-wrapper');
        if (form) {
            placesContainer.insertBefore(placesWrapper, form);
        } else {
            placesContainer.appendChild(placesWrapper);
        }
    }
    placesWrapper.innerHTML = ''; // Clear

    if (places && places.length > 0) {
        places.forEach(place => {
            const placeElement = document.createElement('div');
            placeElement.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-3';
            placeElement.dataset.placeId = place.id;

            placeElement.innerHTML = `
                <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-white truncate" title="${place.name || ''}">${place.name || 'Unnamed Place'}</h4>
                    <p class="text-gray-400 text-sm mt-1 truncate" title="${place.address || ''}">${place.address || 'No address'}</p>
                </div>
                <div class="mt-3 md:mt-0 flex items-center space-x-2 flex-shrink-0 self-end md:self-center">
                    <button class="edit-place-btn p-1.5 rounded-lg bg-gray-600 hover:bg-primary-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500" title="Edit Place" data-place='${JSON.stringify(place)}'>
                        <span class="lucide text-lg pointer-events-none">&#xea71;</span>
                    </button>
                    <button class="delete-place-btn p-1.5 rounded-lg bg-gray-600 hover:bg-red-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-red-500" title="Delete Place" data-place-id="${place.id}" data-place-name="${place.name || 'this place'}">
                        <span class="lucide text-lg pointer-events-none">&#xea0f;</span>
                    </button>
                </div>
            `;
            placesWrapper.appendChild(placeElement);
        });
        attachPlaceActionListeners(); // Attach listeners after rendering
    } else {
        placesWrapper.innerHTML = `<div class="text-center py-6 text-gray-400">... (empty state message) ...</div>`;
    }
    ensureAddPlaceForm(placesContainer); // Make sure add form is present
}

/**
 * Updates the UI for payment methods.
 * @param {Array} paymentMethods
 */
function updatePaymentMethodsUI(paymentMethods) {
    console.log("Updating payment methods UI", paymentMethods);
    const paymentContainer = document.querySelector('#payment-tab-content');
    if (!paymentContainer) {
        console.warn("Payment tab content container not found.");
        return;
    }
    paymentContainer.innerHTML = ''; // Clear

    const methodsListWrapper = document.createElement('div');
    methodsListWrapper.className = 'payment-methods-list-wrapper space-y-4 mb-6';

    if (paymentMethods && paymentMethods.length > 0) {
        paymentMethods.forEach(method => {
            const methodItem = document.createElement('div');
            methodItem.className = 'bg-gray-700/50 rounded-lg p-4 border border-gray-600 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3';
            methodItem.dataset.methodId = method.id;

            let cardIcon = '&#xeaa4;'; // Default: Credit Card
            let cardBrandClass = 'text-gray-300';
            const lowerType = method.type?.toLowerCase();

            if (lowerType === 'paypal') cardIcon = '&#xec8f;'; // Example
            // Add more icon logic if needed

            methodItem.innerHTML = `
                <div class="flex items-center flex-1 min-w-0">
                    <span class="lucide text-2xl mr-3 ${cardBrandClass}" aria-hidden="true">${cardIcon}</span>
                    <div class="flex-1">
                        <p class="font-medium text-white truncate" title="${method.name || ''}">${method.name || 'Payment Method'}</p>
                        <p class="text-sm text-gray-400">
                            ${lowerType === 'card' ? `**** **** **** ${method.last4 || '****'}` : (method.email || 'N/A')}
                        </p>
                    </div>
                </div>
                <div class="mt-3 sm:mt-0 flex items-center space-x-2 flex-shrink-0 self-end sm:self-center">
                    ${method.is_default ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-500/20 text-primary-300 border border-primary-500/30">Default</span>' : ''}
                    ${!method.is_default ? `<button class="set-default-payment-btn text-xs text-gray-400 hover:text-primary-300" data-method-id="${method.id}" title="Set as default">Set Default</button>` : ''}
                    <button class="delete-payment-btn p-1.5 rounded-lg bg-gray-600 hover:bg-red-600 text-gray-200 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-red-500" title="Delete Payment Method" data-method-id="${method.id}" data-method-name="${method.name || 'this method'}">
                        <span class="lucide text-lg pointer-events-none">&#xea0f;</span>
                    </button>
                </div>
            `;
            methodsListWrapper.appendChild(methodItem);
        });
        attachPaymentActionListeners(); // Attach listeners after rendering
    } else {
        methodsListWrapper.innerHTML = `<div class="text-center py-6 text-gray-400">... (empty state message) ...</div>`;
    }
    paymentContainer.appendChild(methodsListWrapper);
    ensureAddPaymentMethodSection(paymentContainer); // Add placeholder for adding methods
}

/**
 * Renders the list of pending payments in the UI.
 * @param {Array} payments - Array of pending payment objects from the API.
 */
function displayPendingPayments(payments) {
    const listContainer = document.getElementById('pending-payments-list');
    if (!listContainer) return;
    listContainer.innerHTML = ''; // Clear

    if (!payments || payments.length === 0) {
        listContainer.innerHTML = '<p class="text-gray-400 text-center py-4">You have no pending payments.</p>';
        return;
    }

    payments.forEach(ride => {
        const paymentElement = document.createElement('div');
        paymentElement.className = 'bg-gray-700/50 rounded-lg p-4 border border-yellow-600/50 flex flex-col md:flex-row justify-between items-start gap-3';
        // Store numeric fare amount (make sure API provides this as final_fare_numeric)
        const numericAmount = parseFloat(ride.final_fare_numeric);

        paymentElement.innerHTML = `
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-400 mb-1">Ride completed on ${ride.formatted_date || 'N/A'} at ${ride.formatted_time || 'N/A'}</p>
                <p class="text-gray-300 text-sm truncate" title="${ride.pickup || 'N/A'}">
                    <span class="lucide text-xs mr-1 text-green-400">&#xea4b;</span> ${ride.pickup || 'N/A'}
                </p>
                <p class="text-gray-300 text-sm truncate" title="${ride.dropoff || 'N/A'}">
                    <span class="lucide text-xs mr-1 text-red-400">&#xea4a;</span> ${ride.dropoff || 'N/A'}
                </p>
            </div>
            <div class="mt-3 md:mt-0 flex flex-col md:items-end md:space-y-2 items-start space-y-2 md:space-y-0 flex-shrink-0">
                <div class="text-lg font-medium text-yellow-300 mb-1 md:mb-0">${ride.formatted_fare || 'N/A'}</div>
                <button
                    class="mark-cash-paid-btn bg-green-600 hover:bg-green-700 text-white font-medium py-1.5 px-4 rounded-lg transition duration-300 shadow text-sm"
                    data-ride-id="${ride.id}"
                    data-amount="${isNaN(numericAmount) ? 0 : numericAmount}" title="Confirm you paid cash for this ride"
                    ${isNaN(numericAmount) || numericAmount <= 0 ? 'disabled' : ''} >
                    Paid Cash? Confirm Here
                </button>
                </div>
        `;
        listContainer.appendChild(paymentElement);
    });
    attachCashPaidListeners(); // Re-attach listeners
}


// --- Action Functions ---

/**
 * Handles redeeming a reward via API call.
 * @param {string|number} rewardId
 */
function redeemReward(rewardId) {
    console.log("Attempting to redeem reward:", rewardId);
    showLoadingIndicator();
    fetch('/api/api-reward-points.php?endpoint=redeem-reward', {
        method: 'POST',
        headers: { /* ... headers ... */ },
        body: JSON.stringify({ reward_id: rewardId }),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showConfirmation(data.message || 'Reward redeemed successfully!');
            loadRewardPoints(); // Refresh points and lists
        } else {
            showConfirmation(data.message || 'Failed to redeem reward.', true);
        }
    })
    .catch(error => {
        console.error('Error redeeming reward:', error);
        showConfirmation('An error occurred while redeeming the reward.', true);
    })
    .finally(hideLoadingIndicator);
}

/**
 * Adds a saved place via API call.
 * @param {string} name
 * @param {string} address
 */
function addSavedPlace(name, address) {
    console.log("Adding saved place:", { name, address });
    showLoadingIndicator();
    const form = document.getElementById('add-place-form');
    const csrfToken = form ? form.querySelector('input[name="csrf_token"]')?.value || '' : '';

    fetch('/api/api-saved-places.php?endpoint=save-place', { // Assuming endpoint for adding
        method: 'POST',
        headers: { /* ... headers ... */ },
        body: JSON.stringify({ name, address, csrf_token: csrfToken }),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showConfirmation('Place added successfully!');
            if (form) form.reset(); // Clear form
            loadSavedPlaces(); // Refresh list
        } else {
            showConfirmation(data.message || 'Failed to add place.', true);
        }
    })
    .catch(error => {
        console.error('Error adding saved place:', error);
        showConfirmation('An error occurred while adding the place.', true);
    })
    .finally(hideLoadingIndicator);
}

/**
 * Updates a saved place via API call.
 * @param {string|number} id
 * @param {string} name
 * @param {string} address
 */
function updateSavedPlace(id, name, address) {
    console.log("Updating saved place:", { id, name, address });
    showLoadingIndicator();
    const form = document.getElementById('edit-place-form'); // Assuming edit modal has this form ID
    const csrfToken = form ? form.querySelector('input[name="csrf_token"]')?.value || '' : '';


    fetch('/api/api-saved-places.php?endpoint=update-place', { // Assuming endpoint for updating
        method: 'PUT', // Use PUT for updates
        headers: { /* ... headers ... */ },
        body: JSON.stringify({ id, name, address, csrf_token: csrfToken }),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showConfirmation('Place updated successfully!');
            closeEditModal(); // Close the edit modal
            loadSavedPlaces(); // Refresh list
        } else {
            showConfirmation(data.message || 'Failed to update place.', true);
        }
    })
    .catch(error => {
        console.error('Error updating saved place:', error);
        showConfirmation('An error occurred while updating the place.', true);
    })
    .finally(hideLoadingIndicator);
}

/**
 * Deletes a saved place via API call after confirmation.
 * @param {string|number} id
 * @param {string} name
 */
function deleteSavedPlace(id, name) {
    if (!confirm(`Are you sure you want to delete the saved place "${name || 'this place'}"?`)) {
        return;
    }
    console.log("Deleting saved place:", { id, name });
    showLoadingIndicator();
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || ''; // Get general CSRF if needed

    fetch(`/api/api-saved-places.php?endpoint=delete-place&id=${encodeURIComponent(id)}`, { // Pass ID in URL for DELETE
        method: 'DELETE',
        headers: {
             'X-Requested-With': 'XMLHttpRequest',
             'X-CSRF-Token': csrfToken // Send CSRF as header example
             /* No Content-Type needed for DELETE with no body */
        },
        // body: JSON.stringify({ csrf_token: csrfToken }), // Alternative: send CSRF in body
        credentials: 'include'
    })
     .then(response => {
         if (response.ok) { // Check status 200-299
             return response.json().catch(() => ({ success: true, message: 'Deleted successfully.' })); // Handle empty response
         } else {
             return response.json().then(err => { throw new Error(err.message || `Server error ${response.status}`) }); // Throw detailed error
         }
     })
    .then(data => {
        if (data.success) {
            showConfirmation('Place deleted successfully!');
            loadSavedPlaces(); // Refresh list
        } else {
            showConfirmation(data.message || 'Failed to delete place.', true);
        }
    })
    .catch(error => {
        console.error('Error deleting saved place:', error);
        showConfirmation(`Error deleting place: ${error.message}.`, true);
    })
    .finally(hideLoadingIndicator);
}


/**
 * Handles the click event for the "Mark as Paid (Cash)" button.
 * @param {Event} event - The click event object.
 */
function handleCashPaidClick(event) {
    const button = event.currentTarget;
    const rideId = button.dataset.rideId;
    // Get the numeric amount (ensure it's stored correctly in data-amount)
    const amount = parseFloat(button.dataset.amount);

    if (!rideId || isNaN(amount) || amount <= 0) { // Also check if amount is positive
        console.error("Missing ride ID or valid positive amount on button:", rideId, amount);
        if(typeof showConfirmation === 'function') showConfirmation('Cannot process payment: Invalid ride ID or amount.', true);
        return;
    }

    if (!confirm(`Please confirm that you have paid G$${amount.toFixed(2)} cash for ride #${rideId}.`)) {
        return;
    }

    button.disabled = true;
    button.textContent = 'Processing...';
    if(typeof showLoadingIndicator === 'function') showLoadingIndicator();
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    fetch('/api/process-payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken // Example CSRF header
        },
        body: JSON.stringify({
            ride_id: parseInt(rideId), // Ensure ID is integer
            amount: amount, // Send the numeric amount
            payment_method: 'cash',
            csrf_token: csrfToken // Example CSRF in body
        }),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if(typeof showConfirmation === 'function') showConfirmation(data.message || 'Payment confirmed!');
            loadPendingPayments(); // Refresh the list
            loadRideHistory(); // Also refresh history as status changed
        } else {
            if(typeof showConfirmation === 'function') showConfirmation(data.message || 'Failed to confirm payment.', true);
            button.disabled = false;
            button.textContent = 'Paid Cash? Confirm Here';
        }
    })
    .catch(error => {
        console.error('Error confirming cash payment:', error);
        if(typeof showConfirmation === 'function') showConfirmation('An network error occurred. Please try again.', true);
        button.disabled = false;
        button.textContent = 'Paid Cash? Confirm Here';
    })
    .finally(() => {
         if(typeof hideLoadingIndicator === 'function') hideLoadingIndicator();
    });
}


/**
 * Deletes a saved payment method via API call after confirmation.
 * @param {string|number} id
 * @param {string} name
 */
function deletePaymentMethod(id, name) {
    if (!confirm(`Are you sure you want to delete the payment method "${name || 'this method'}"?`)) {
        return;
    }
    console.log("Deleting payment method:", { id, name });
    showLoadingIndicator();
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    fetch(`/api/api-payment-methods.php?endpoint=delete-method&id=${encodeURIComponent(id)}`, { // Assuming endpoint
        method: 'DELETE',
        headers: { /* ... headers, CSRF ... */ },
        credentials: 'include'
    })
     .then(response => { /* ... handle response ... */ })
    .then(data => {
        if (data.success) {
            showConfirmation('Payment method deleted!');
            loadPaymentMethods(); // Refresh list
        } else {
            showConfirmation(data.message || 'Failed to delete payment method.', true);
        }
    })
    .catch(error => { /* ... error handling ... */ })
    .finally(hideLoadingIndicator);
}

/**
 * Cancels a ride via API call after confirmation.
 * @param {string|number} rideId
 */
function cancelRideFromDashboard(rideId) {
    if (!confirm("Are you sure you want to cancel this ride?")) {
        return;
    }
    console.log("Cancelling ride from dashboard:", rideId);
    showLoadingIndicator("Cancelling ride...");
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    fetch('/api/api-cancel-ride.php', { // Assuming this is the correct endpoint
        method: 'POST', // Or DELETE
        headers: { /* ... headers, CSRF ... */ },
        body: JSON.stringify({ booking_id: rideId, csrf_token: csrfToken }), // Ensure key is 'booking_id' if API expects it
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showConfirmation('Ride cancelled successfully.');
            loadRideHistory(); // Refresh history
            loadPendingPayments(); // Also refresh pending payments (if cancelled ride was pending)
        } else {
            showConfirmation(data.message || 'Failed to cancel ride.', true);
        }
    })
    .catch(error => { /* ... error handling ... */ })
    .finally(hideLoadingIndicator);
}


// --- Event Listener Setup ---

/**
 * Attaches listeners to dynamically created place action buttons.
 */
/**
 * Attaches listeners to dynamically created place action buttons.
 */
function attachPlaceActionListeners() {
    // *** ADD LOGGING ***
    console.log("[Attach Listeners] Running attachPlaceActionListeners...");

    const editButtons = document.querySelectorAll('#places-tab-content .edit-place-btn');
    // *** ADD LOGGING ***
    console.log(`[Attach Listeners] Found ${editButtons.length} edit buttons.`);

    editButtons.forEach(button => {
         const newButton = button.cloneNode(true); button.parentNode.replaceChild(newButton, button);
         newButton.addEventListener('click', (e) => {
             // *** ADD LOGGING ***
             console.log("[Attach Listeners] Edit button listener triggered.");
             try {
                const placeData = JSON.parse(e.currentTarget.dataset.place);
                // *** ADD LOGGING ***
                console.log("[Attach Listeners] Parsed place data for edit:", placeData);
                if (typeof editSavedPlace === 'function') {
                    editSavedPlace(placeData);
                } else { console.error("editSavedPlace function undefined"); }
             } catch(err) { console.error("Could not parse place data for edit:", err); }
         });
         // *** ADD LOGGING ***
         console.log(`[Attach Listeners] Added EDIT listener for place ID: ${newButton.dataset.placeId || JSON.parse(newButton.dataset.place || '{}').id}`);
    });

    const deleteButtons = document.querySelectorAll('#places-tab-content .delete-place-btn');
    // *** ADD LOGGING ***
    console.log(`[Attach Listeners] Found ${deleteButtons.length} delete buttons.`);

    deleteButtons.forEach(button => {
         const newButton = button.cloneNode(true); button.parentNode.replaceChild(newButton, button);
         newButton.addEventListener('click', (e) => {
             // *** ADD LOGGING ***
             console.log("[Attach Listeners] Delete button listener triggered.");
             const placeId = e.currentTarget.dataset.placeId;
             const placeName = e.currentTarget.dataset.placeName;
             if (typeof deleteSavedPlace === 'function') {
                deleteSavedPlace(placeId, placeName);
             } else { console.error("deleteSavedPlace function undefined"); }
         });
         // *** ADD LOGGING ***
         console.log(`[Attach Listeners] Added DELETE listener for place ID: ${newButton.dataset.placeId}`);
    });
}

/**
 * Attaches listeners to dynamically created payment action buttons.
 */
function attachPaymentActionListeners() {
    document.querySelectorAll('#payment-tab-content .delete-payment-btn').forEach(button => {
         button.replaceWith(button.cloneNode(true));
         button = document.querySelector(`[data-method-id='${button.dataset.methodId}'] .delete-payment-btn`); // Re-select

         button?.addEventListener('click', (e) => {
             const methodId = e.currentTarget.dataset.methodId;
             const methodName = e.currentTarget.dataset.methodName;
              if (typeof deletePaymentMethod === 'function') {
                 deletePaymentMethod(methodId, methodName);
              } else { console.error("deletePaymentMethod function undefined"); }
         });
    });
    document.querySelectorAll('#payment-tab-content .set-default-payment-btn').forEach(button => {
         button.replaceWith(button.cloneNode(true));
         button = document.querySelector(`[data-method-id='${button.dataset.methodId}'] .set-default-payment-btn`); // Re-select

         button?.addEventListener('click', (e) => {
             const methodId = e.currentTarget.dataset.methodId;
             console.log("Set default payment method ID:", methodId);
             // Implement API call to set default
             // setDefaultPaymentMethod(methodId);
         });
    });
}

/**
 * Attaches click listeners to dynamically created "Paid Cash? Confirm Here" buttons.
 * Enhanced with better logging and error handling.
 */
function attachCashPaidListeners() {
    console.log("Attaching cash paid listeners to payment buttons");
    const buttons = document.querySelectorAll('#pending-payments-list .mark-cash-paid-btn');
    console.log(`Found ${buttons.length} payment buttons`);
    
    buttons.forEach((button, index) => {
        // Efficiently replace listeners: clone and replace
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        // Log button data before attaching
        const rideId = newButton.dataset.rideId;
        const amount = parseFloat(newButton.dataset.amount);
        console.log(`Button ${index+1}: Ride ID=${rideId}, Amount=${amount}, Disabled=${newButton.disabled}`);
        
        // Add listener to the new button
        newButton.addEventListener('click', handleCashPaidClick);
        console.log(`Attached listener to button for ride #${rideId}`);
    });
    
    if (buttons.length === 0) {
        console.log("No payment buttons found to attach listeners to");
    }
}

/**
 * Ensures the 'Add Place' form exists and has its submit listener.
 * @param {HTMLElement} container - The parent element (#places-tab-content).
 */
function ensureAddPlaceForm(container) {
    let formWrapper = container.querySelector('.add-place-form-wrapper');
    if (!formWrapper) {
        formWrapper = document.createElement('div');
        formWrapper.className = 'add-place-form-wrapper mt-6 pt-6 border-t border-gray-700';
        const csrfTokenValue = document.querySelector('input[name="csrf_token"]')?.value || '';

        formWrapper.innerHTML = `
            <h3 class="text-lg font-medium text-white mb-4">Add a New Place</h3>
            <form id="add-place-form" class="space-y-4">
                ${csrfTokenValue ? `<input type="hidden" name="csrf_token" value="${csrfTokenValue}">` : ''}
                <div>
                    <label for="place-name" class="block text-sm font-medium text-gray-300 mb-1">Place Name</label>
                    <input type="text" id="place-name" name="name" required placeholder="e.g., Home, Work" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label for="place-address" class="block text-sm font-medium text-gray-300 mb-1">Address</label>
                    <input type="text" id="place-address" name="address" required placeholder="Enter the full address" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <button type="submit" class="inline-flex items-center bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                        <span class="lucide mr-1.5 text-base">&#xea9a;</span> Add Place
                    </button>
                </div>
            </form>
        `;
        container.appendChild(formWrapper);

        // Attach submit listener to the new form
        const form = formWrapper.querySelector('#add-place-form');
        form?.addEventListener('submit', handleAddPlaceSubmit);
    }
     // Ensure CSRF token is up-to-date even if form existed
     const existingCsrfInput = formWrapper?.querySelector('input[name="csrf_token"]');
     const currentCsrfToken = document.querySelector('input[name="csrf_token"]')?.value;
     if (existingCsrfInput && currentCsrfToken && existingCsrfInput.value !== currentCsrfToken) {
         existingCsrfInput.value = currentCsrfToken;
     }
}

/**
 * Handles the submission of the add place form.
 * @param {Event} e
 */
function handleAddPlaceSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const nameInput = form.querySelector('#place-name');
    const addressInput = form.querySelector('#place-address');
    const name = nameInput?.value.trim();
    const address = addressInput?.value.trim();

    // Basic validation
    let isValid = true;
    if (!name) { nameInput?.classList.add('border-red-500'); isValid = false; } else { nameInput?.classList.remove('border-red-500'); }
    if (!address) { addressInput?.classList.add('border-red-500'); isValid = false; } else { addressInput?.classList.remove('border-red-500'); }

    if (!isValid) {
        showConfirmation('Please enter both a name and an address.', true);
        return;
    }

    if (typeof addSavedPlace === 'function') {
        addSavedPlace(name, address);
    } else {
        console.error("addSavedPlace function is not defined.");
    }
}

/**
 * Ensures the 'Add Payment Method' section exists.
 * @param {HTMLElement} container - The parent element (#payment-tab-content).
 */
function ensureAddPaymentMethodSection(container) {
    let addSection = container.querySelector('.add-payment-method-section');
    if (!addSection) {
        addSection = document.createElement('div');
        addSection.className = 'add-payment-method-section mt-6 pt-6 border-t border-gray-700';
        addSection.innerHTML = `
            <h3 class="text-lg font-medium text-white mb-4">Add Payment Method</h3>
            <div class="bg-blue-900/30 rounded-lg p-4 border border-blue-700 text-center">
                <p class="text-blue-200 mb-2">Adding new payment methods via the dashboard is currently unavailable.</p>
                <p class="text-blue-300 text-sm">You can usually add methods during the checkout process for a ride.</p>
            </div>
        `;
        container.appendChild(addSection);
    }
}

// --- General Setup ---

/**
 * Sets up primary event listeners for the dashboard interface (tabs, profile form, logout).
 */
function setupEventListeners() {
    console.log("Setting up dashboard event listeners.");

    // Tab Switching
    document.querySelectorAll('.dashboard-tab').forEach(tabButton => {
        tabButton.addEventListener('click', function() {
            const tabName = this.id.replace('-tab-btn', '');
            console.log(`Switching to tab: ${tabName}`);

            document.querySelectorAll('.dashboard-content').forEach(pane => pane.classList.add('hidden'));
            document.getElementById(`${tabName}-tab-content`)?.classList.remove('hidden');

            document.querySelectorAll('.dashboard-tab').forEach(btn => {
                btn.classList.remove('active', 'text-primary-400', 'border-primary-400', 'bg-gray-700/50');
                btn.classList.add('text-gray-400', 'hover:text-primary-300', 'border-transparent', 'hover:bg-gray-700/30');
                btn.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active', 'text-primary-400', 'border-primary-400', 'bg-gray-700/50');
            this.classList.remove('text-gray-400', 'hover:text-primary-300', 'border-transparent', 'hover:bg-gray-700/30');
            this.setAttribute('aria-selected', 'true');

            try {
                const url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({ tab: tabName }, '', url);
            } catch (e) { console.error("URL state update failed:", e); }

            localStorage.setItem('dashboardActiveTab', tabName);

            // Trigger data loading for the activated tab if needed
             if (tabName === 'rides') loadRideHistory();
             else if (tabName === 'places') loadSavedPlaces();
             else if (tabName === 'payment') loadPaymentMethods();
             else if (tabName === 'rewards') loadRewardPoints();
             else if (tabName === 'profile') loadPendingPayments(); // Load pending payments when profile is active

        });
    });

    // Profile Form Submission
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            showLoadingIndicator("Saving profile...");
            const formData = new FormData(this);

            fetch('process-profile-update.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'include'
            })
            .then(response => response.json().catch(() => ({ success: false, message: 'Invalid response from server.'}))) // Handle non-JSON response
            .then(data => {
                if (data.success) {
                    // Update client-side user data
                    if (data.user) { // Check if server returned updated user data
                         currentUser = data.user;
                    } else { // Manually update based on form if server didn't return user object
                         currentUser = { ...currentUser,
                             name: formData.get('name'), email: formData.get('email'), phone: formData.get('phone'), language: formData.get('language'),
                             preferences: {
                                 notify_email: formData.has('notify-email'), notify_sms: formData.has('notify-sms'), notify_promotions: formData.has('notify-promotions')
                             }
                         };
                    }
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                    updateUIWithUserData(); // Refresh UI
                    showConfirmation('Profile updated successfully!');
                } else {
                    showConfirmation(data.message || 'Failed to update profile.', true);
                }
            })
            .catch(error => {
                console.error('Error updating profile:', error);
                showConfirmation('An error occurred while updating profile.', true);
            })
            .finally(hideLoadingIndicator);
        });
    }

    // Logout Handlers (ensure this runs after elements are in DOM)
    initLogoutHandlers();
     // Network Status
     checkNetworkStatus();
     window.addEventListener('online', checkNetworkStatus);
     window.addEventListener('offline', checkNetworkStatus);

    console.log("Dashboard event listeners set up complete.");
}

/**
 * Initializes event handlers for all logout links/buttons.
 */
function initLogoutHandlers() {
    console.log("Initializing logout handlers");
    const logoutElements = document.querySelectorAll('a[href="logout.php"], #logout-link, #mobile-logout-link, .logout-button');

    logoutElements.forEach(element => {
        // Prevent multiple listeners
        const newElement = element.cloneNode(true);
        element.parentNode.replaceChild(newElement, element);

        newElement.addEventListener('click', function(e) {
            e.preventDefault();
            if (!confirm("Are you sure you want to log out?")) return;
            showLoadingIndicator("Logging out...");

            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('currentUser');
            localStorage.removeItem('dashboardActiveTab');
            isLoggedIn = false; currentUser = null;

            fetch('logout.php', { method: 'POST', credentials: 'include' })
                .catch(err => console.warn("Server logout call failed:", err)) // Log error but continue
                .finally(() => {
                    showConfirmation('You have been logged out.');
                    setTimeout(() => { window.location.href = 'index.php'; }, 750);
                });
        });
    });
    if (logoutElements.length > 0) console.log(`Attached ${logoutElements.length} logout handlers.`);
    else console.warn("No logout elements found.");
}


/**
 * Sets the initially active dashboard tab based on URL parameters or localStorage.
 */
function initDashboardFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    const storedTab = localStorage.getItem('dashboardActiveTab');
    const activeTab = tabParam || storedTab || 'profile'; // Default to profile
    console.log("Initializing dashboard - Target tab:", activeTab);

    const tabBtn = document.getElementById(`${activeTab}-tab-btn`);
    if (tabBtn) {
        // Use timeout to ensure it runs after initial DOM setup and other scripts
        setTimeout(() => {
            // Check if button is still in DOM before clicking
            if (document.body.contains(tabBtn)) {
                 tabBtn.click();
                 console.log(`Activated tab via init: ${activeTab}`);
                 // Manually trigger data load for the initial tab if needed
                 if (activeTab === 'profile') loadPendingPayments();
                 // Add else if for other tabs if their initial load isn't handled elsewhere
            } else {
                 console.warn(`Initial tab button #${activeTab}-tab-btn not found in DOM during timeout.`);
                 document.getElementById('profile-tab-btn')?.click(); // Fallback to profile
                 loadPendingPayments();
            }
        }, 50); // Small delay
    } else {
        console.warn(`Initial tab button not found: #${activeTab}-tab-btn. Defaulting to profile.`);
        // Explicitly click profile tab as fallback
        setTimeout(() => {
             document.getElementById('profile-tab-btn')?.click();
             loadPendingPayments();
        }, 50);
    }
    // Clean up localStorage if it was used for initial load
    if (storedTab && storedTab === activeTab) {
        localStorage.removeItem('dashboardActiveTab');
    }
}

/**
 * Checks the browser's online status and optionally displays a message.
 */
function checkNetworkStatus() {
    const isOnline = navigator.onLine;
    console.log(`Network status: ${isOnline ? 'Online' : 'Offline'}`);
    // Manage offline UI elements if needed (like the offline bar in script.js)
}

// --- Main Initialization ---
document.addEventListener('DOMContentLoaded', () => {
    console.log("Dashboard DOM loaded.");
    checkLoginStatus(); // Checks auth, potentially loads user data & updates UI
    setupEventListeners(); // Sets up tab clicks, profile form submit, logout
    initDashboardFromURL(); // Sets the correct active tab on load
    console.log("Dashboard initialization sequence complete.");
});

/**
 * Opens the ride details modal with information about a specific ride.
 * Fixed to ensure modal displays correctly.
 * @param {object} ride - The ride object with details.
 */
function openRideDetails(ride) {
    console.log("Opening ride details for ride:", ride);
    const modal = document.getElementById('ride-details-modal');
    if (!modal) { 
        console.error("Ride details modal element not found."); 
        return; 
    }
    
    // Process and extract ride data from API response structure if needed
    if (ride.data && ride.data.ride) {
        console.log("Unwrapping ride from API response structure");
        ride = ride.data.ride;
    }

    // Safely set text content with fallbacks
    function safeSetTextContent(elementId, value, fallback = 'N/A') {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value || fallback;
        } else {
            console.warn(`Element with ID ${elementId} not found in modal`);
        }
    }

    // Populate modal (with safe fallbacks)
    safeSetTextContent('ride-details-title', `Ride Details #${ride.id}`, 'Ride Details');
    safeSetTextContent('ride-details-date', ride.formatted_date);
    safeSetTextContent('ride-details-time', ride.formatted_time, '');
    safeSetTextContent('ride-details-pickup', ride.pickup);
    safeSetTextContent('ride-details-dropoff', ride.dropoff);
    safeSetTextContent('ride-details-fare', ride.formatted_fare);
    safeSetTextContent('ride-details-vehicle-type', ride.vehicle_type);

    // Populate driver details safely
    safeSetTextContent('details-driver-name', ride.driver_name);
    safeSetTextContent('details-driver-rating', ride.driver_rating);
    safeSetTextContent('details-driver-vehicle', ride.vehicle_model || ride.vehicle_type);
    safeSetTextContent('details-driver-plate', ride.vehicle_plate);
    safeSetTextContent('details-driver-phone', ride.driver_phone);

    // Handle status badge
    const statusBadge = document.getElementById('ride-details-status-badge');
    if (statusBadge) {
        if (typeof getRideStatusUI === 'function') {
            statusBadge.innerHTML = getRideStatusUI(ride.status); // Use helper function
        } else {
            // Fallback if helper function is missing
            statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-600/20 text-gray-300 border border-gray-500/30">${ride.status || 'Unknown'}</span>`;
        }
    }

    // Make sure all close buttons are properly attached
    const closeButtons = modal.querySelectorAll('.modal-close-btn');
    closeButtons.forEach(button => {
        // Remove existing listeners to prevent duplicates
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        newButton.addEventListener('click', () => closeRideDetailsModal());
    });
    
    // Ensure the overlay click closes the modal
    const overlay = document.getElementById('ride-details-modal-overlay');
    if (overlay) {
        const newOverlay = overlay.cloneNode(true);
        overlay.parentNode.replaceChild(newOverlay, overlay);
        newOverlay.addEventListener('click', (e) => {
            if (e.target === newOverlay) closeRideDetailsModal();
        });
    }

    // Display the modal - CRITICAL FIX: Force display:flex style
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Make sure the modal content has the animation class
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.classList.remove('animate-slide-down');
        modalContent.classList.add('animate-slide-up');
    }
    
    console.log("Modal should now be visible");
}

/**
 * Closes the ride details modal with proper animation.
 */
function closeRideDetailsModal() {
    const modal = document.getElementById('ride-details-modal');
    if (!modal) return;
    
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.classList.remove('animate-slide-up');
        modalContent.classList.add('animate-slide-down');
        
        // Wait for animation before hiding
        setTimeout(() => {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    } else {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

/**
 * Fetches detailed information about a specific ride.
 * @param {number} rideId
 * @returns {Promise<object>} A promise resolving with the detailed ride object.
 */
function fetchRideDetails(rideId) {
    console.log(`Fetching details for ride ID: ${rideId}`);
    const apiUrl = `/api/api-ride-details.php?id=${rideId}`;

    return fetch(apiUrl, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) {
            console.warn(`Ride details endpoint returned ${response.status}. Using fallback method.`);
            return getFallbackRideDetails(rideId);
        }
        return response.json();
    })
    .then(data => {
        console.log("API Response:", data);
        
        if (data.success && data.data && data.data.ride) {
            return data.data.ride; // Return just the ride data object
        }
        
        // If API returns success=false or invalid structure, use fallback
        console.warn("Invalid API response structure. Using fallback method.");
        return getFallbackRideDetails(rideId);
    })
    .catch(error => {
        console.error("Error fetching ride details:", error);
        // Use fallback method if API fetch fails
        return getFallbackRideDetails(rideId);
    });
}


/**
 * Creates a ride details object from UI elements when API fails
 * @param {number} rideId 
 * @returns {Promise<object>} Promise with ride details
 */
function getFallbackRideDetails(rideId) {
    console.log(`Using fallback method for ride ID: ${rideId}`);
    // Find the ride element in the DOM
    const rideElement = document.querySelector(`.ride-history-item[data-ride-id="${rideId}"]`);
    
    if (!rideElement) {
        return Promise.reject(new Error("Ride not found in current view"));
    }
    
    // Extract data from the DOM element
    const pickup = rideElement.querySelector('p[title]')?.title || 'N/A';
    const dropoff = rideElement.querySelectorAll('p[title]')[1]?.title || 'N/A'; 
    
    // Extract status from the badge
    let status = 'unknown';
    const statusBadge = rideElement.querySelector('.inline-flex.items-center.rounded-full');
    if (statusBadge) {
        // Extract text content and normalize
        status = statusBadge.textContent.trim().toLowerCase();
    }
    
    // Extract date and time
    const dateElement = rideElement.querySelector('.text-sm.font-medium.text-white');
    const timeElement = rideElement.querySelector('.text-sm.text-gray-400');
    const formattedDate = dateElement?.textContent || 'N/A';
    const formattedTime = timeElement?.textContent || '';
    
    // Extract fare
    const fareElement = rideElement.querySelector('.text-lg.font-medium.text-white');
    const formattedFare = fareElement?.textContent || 'N/A';
    
    // Extract driver name if available
    const driverElement = rideElement.querySelector('.text-sm.text-gray-400');
    let driverName = null;
    if (driverElement && driverElement.textContent.includes('Driver:')) {
        driverName = driverElement.textContent.replace('Driver:', '').trim();
    }
    
    // Extract vehicle type if available
    const vehicleElement = rideElement.querySelector('.text-xs.text-gray-500');
    const vehicleType = vehicleElement?.textContent || null;
    
    // Create fallback ride object
    const ride = {
        id: rideId,
        pickup: pickup,
        dropoff: dropoff,
        status: status,
        formatted_date: formattedDate,
        formatted_time: formattedTime,
        formatted_fare: formattedFare,
        driver_name: driverName,
        vehicle_type: vehicleType,
        // Add a flag to indicate this was created by fallback
        _fallback: true
    };
    
    console.log("Created fallback ride details:", ride);
    return Promise.resolve(ride);
}

/**
 * Opens a modal dialog to edit an existing saved place.
 * Pre-fills the form with the place's current data.
 * @param {object} place - The place object containing id, name, and address.
 */
function editSavedPlace(place) {
    console.log("[Edit Place] Opening modal for:", place);
    if (!place || !place.id) {
        console.error("[Edit Place] Invalid place data provided.");
        showConfirmation("Cannot edit place: Invalid data.", true);
        return;
    }

    // Find or create the modal element (ensures it exists)
    let editModal = document.getElementById('edit-place-modal');
    if (!editModal) {
        editModal = document.createElement('div');
        editModal.id = 'edit-place-modal';
        editModal.className = 'fixed inset-0 z-50 flex items-center justify-center hidden p-4'; // Start hidden
        editModal.setAttribute('role', 'dialog');
        editModal.setAttribute('aria-modal', 'true');
        editModal.setAttribute('aria-labelledby', 'edit-place-modal-title');

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        editModal.innerHTML = `
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="edit-place-modal-overlay" aria-hidden="true"></div>
            <div class="modal-content bg-gray-800 rounded-xl shadow-2xl border border-gray-700 max-w-md w-full mx-auto relative z-10 p-6 sm:p-8">
                <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-full p-1" aria-label="Close modal">
                    <span class="lucide text-xl" aria-hidden="true">&#xea76;</span> </button>
                <h2 id="edit-place-modal-title" class="text-xl sm:text-2xl font-semibold text-white mb-6">Edit Saved Place</h2>
                <form id="edit-place-form" class="space-y-4">
                    <input type="hidden" id="edit-place-id" name="id">
                    ${csrfToken ? `<input type="hidden" name="csrf_token" value="${csrfToken}">` : ''}
                    <div>
                        <label for="edit-place-name" class="block text-sm font-medium text-gray-300 mb-1">Place Name</label>
                        <input type="text" id="edit-place-name" name="name" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label for="edit-place-address" class="block text-sm font-medium text-gray-300 mb-1">Address</label>
                        <input type="text" id="edit-place-address" name="address" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div class="pt-4 flex gap-3">
                         <button type="button" class="modal-close-btn-secondary flex-1 bg-gray-600 hover:bg-gray-500 text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                             Cancel
                         </button>
                        <button type="submit" class="flex-1 bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(editModal);

        // --- Attach Event Listeners (ONLY ONCE during creation) ---
        const closeModalHandler = () => closeEditModal();
        editModal.querySelector('.modal-close-btn')?.addEventListener('click', closeModalHandler);
        editModal.querySelector('.modal-close-btn-secondary')?.addEventListener('click', closeModalHandler); // Also close on Cancel
        editModal.querySelector('#edit-place-modal-overlay')?.addEventListener('click', (e) => {
             if(e.target === e.currentTarget) closeModalHandler(); // Close on overlay click
        });
        editModal.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModalHandler(); }); // Close on Escape key

        const form = editModal.querySelector('#edit-place-form');
        form?.addEventListener('submit', handleUpdatePlaceSubmit); // Use separate handler
        // --- End Event Listeners ---
    }

    // --- Populate Form ---
    const idInput = editModal.querySelector('#edit-place-id');
    const nameInput = editModal.querySelector('#edit-place-name');
    const addressInput = editModal.querySelector('#edit-place-address');
    const csrfInput = editModal.querySelector('input[name="csrf_token"]');

    if (idInput) idInput.value = place.id;
    if (nameInput) nameInput.value = place.name || '';
    if (addressInput) addressInput.value = place.address || '';
     // Ensure CSRF token is current if the modal already existed
     const currentCsrfToken = document.querySelector('input[name="csrf_token"]')?.value;
     if (csrfInput && currentCsrfToken) csrfInput.value = currentCsrfToken;


    // --- Show Modal ---
    openModal('edit-place-modal'); // Use the general openModal function

    // Focus the first input field
    if (nameInput) {
        setTimeout(() => nameInput.focus(), 50); // Slight delay for focus
    }
}

/**
 * Handles the submission of the edit place form.
 * @param {Event} e
 */
function handleUpdatePlaceSubmit(e) {
     e.preventDefault();
     const form = e.target;
     const idInput = form.querySelector('#edit-place-id');
     const nameInput = form.querySelector('#edit-place-name');
     const addressInput = form.querySelector('#edit-place-address');

     const id = idInput?.value;
     const name = nameInput?.value.trim();
     const address = addressInput?.value.trim();

     // Basic validation
     let isValid = true;
     if (!id) { console.error("Missing place ID in edit form."); isValid = false; }
     if (!name) { nameInput?.classList.add('border-red-500'); isValid = false; } else { nameInput?.classList.remove('border-red-500'); }
     if (!address) { addressInput?.classList.add('border-red-500'); isValid = false; } else { addressInput?.classList.remove('border-red-500'); }

     if (!isValid) {
         showConfirmation('Please ensure all fields are filled correctly.', true);
         return;
     }

     // Call the function to update the place via API
     if (typeof updateSavedPlace === 'function') {
         updateSavedPlace(id, name, address);
     } else {
          console.error("updateSavedPlace function is not defined.");
     }
}


/**
 * Closes the edit saved place modal.
 */
function closeEditModal() {
    closeModal('edit-place-modal'); // Use the general closeModal function
}

/**
 * Sends a request to the server to update an existing saved place.
 * @param {string|number} id - The ID of the place to update.
 * @param {string} name - The new name for the place.
 * @param {string} address - The new address for the place.
 */
function updateSavedPlace(id, name, address) {
    console.log("Updating saved place:", { id, name, address });
    showLoadingIndicator("Saving changes...");
    const form = document.getElementById('edit-place-form'); // Get CSRF from the correct form
    const csrfToken = form ? form.querySelector('input[name="csrf_token"]')?.value || '' : '';

    // Make the API call using PUT method
    fetch('/api/api-saved-places.php', { // Endpoint might not need specific 'update-place' if method is PUT
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken // Send CSRF as header (adjust if needed)
        },
        body: JSON.stringify({
            id: id,
            name: name,
            address: address,
            // csrf_token: csrfToken // Or send CSRF in body
        }),
        credentials: 'include' // Send cookies
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        console.log("Update place response:", data);
        if (data.success) {
            closeEditModal(); // Close the edit modal
            showConfirmation(data.message || 'Place updated successfully!');
            loadSavedPlaces(); // Reload the list to show changes
        } else {
            // Show error message (possibly inside the modal for better UX)
            showConfirmation(data.message || 'Failed to update place.', true);
        }
    })
    .catch(error => {
        console.error('Error updating saved place:', error);
        showConfirmation(`Error updating place: ${error.message}.`, true);
    })
    .finally(hideLoadingIndicator);
}

/**
 * Sets up event listeners for the ride details modal close buttons/overlay.
 * Ensures modal can be closed properly.
 */
function setupRideDetailsModalEvents() {
    console.log("Setting up ride details modal events");
    if (window.rideDetailsModalEventsSet) return;
    
    const modal = document.getElementById('ride-details-modal');
    if (!modal) {
        console.warn("Ride details modal not found in DOM");
        return;
    }
    
    // Set up close button click handler
    const closeButton = modal.querySelector('.modal-close-btn');
    if (closeButton) {
        closeButton.addEventListener('click', closeRideDetailsModal);
        console.log("Added click handler to modal close button");
    } else {
        console.warn("Modal close button not found");
    }
    
    // Set up overlay click handler
    const overlay = document.getElementById('ride-details-modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeRideDetailsModal();
        });
        console.log("Added click handler to modal overlay");
    } else {
        console.warn("Modal overlay not found");
    }
    
    // Add Escape key handler
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeRideDetailsModal();
        }
    });
    
    window.rideDetailsModalEventsSet = true;
    console.log("Ride details modal events setup complete");
}

// Make sure to call this function when the DOM is loaded
document.addEventListener('DOMContentLoaded', setupRideDetailsModalEvents);

// --- Helper Functions ---

/**
 * Generates an HTML span element for the ride status badge.
 * @param {string} status - The status string.
 * @returns {string} - HTML string for the badge.
 */
function getRideStatusUI(status) {
    let statusClass = 'bg-gray-600/20 text-gray-300 border border-gray-500/30'; // Default
    let statusText = 'Unknown';
    let icon = '&#xea91;'; // Help Circle

    const lowerStatus = status?.toLowerCase() || 'unknown';

    switch (lowerStatus) {
        case 'completed':
            statusClass = 'bg-green-600/20 text-green-300 border border-green-500/30';
            statusText = 'Completed'; icon = '&#xea6d;'; break;
        case 'cancelled': case 'canceled':
            statusClass = 'bg-red-600/20 text-red-300 border border-red-500/30';
            statusText = 'Canceled'; icon = '&#xea76;'; break;
        case 'in_progress': case 'on_trip':
            statusClass = 'bg-blue-600/20 text-blue-300 border border-blue-500/30';
            statusText = 'In Progress'; icon = '&#xea5e;'; break;
        case 'scheduled':
            statusClass = 'bg-purple-600/20 text-purple-300 border border-purple-500/30';
            statusText = 'Scheduled'; icon = '&#xea66;'; break;
        case 'searching':
            statusClass = 'bg-yellow-600/20 text-yellow-300 border border-yellow-500/30 animate-pulse';
            statusText = 'Finding Driver'; icon = '&#xeab1;'; break;
        case 'confirmed':
            statusClass = 'bg-cyan-600/20 text-cyan-300 border border-cyan-500/30';
            statusText = 'Confirmed'; icon = '&#xea6c;'; break;
        case 'arriving':
            statusClass = 'bg-teal-600/20 text-teal-300 border border-teal-500/30';
            statusText = 'Driver Arriving'; icon = '&#xea4b;'; break;
        default:
            statusText = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
    }

    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                <span class="lucide text-xs mr-1" aria-hidden="true">${icon}</span>
                ${statusText}
            </span>`;
}

// Ensure general modal functions are available globally if needed by other parts
window.openModal = window.openModal || function(modalId) { /* Basic implementation */
    const modal = document.getElementById(modalId);
    if(modal) modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
};
window.closeModal = window.closeModal || function(modalId) { /* Basic implementation */
     const modal = document.getElementById(modalId);
     if(modal) modal.style.display = 'none';
     document.body.style.overflow = '';
};

function checkModalStructure() {
    const modal = document.getElementById('ride-details-modal');
    if (!modal) {
        console.error("CRITICAL ERROR: Ride details modal element doesn't exist in the DOM!");
        return false;
    }
    
    const overlay = document.getElementById('ride-details-modal-overlay');
    if (!overlay) {
        console.error("CRITICAL ERROR: Modal overlay element doesn't exist!");
        return false;
    }
    
    const modalContent = modal.querySelector('.modal-content');
    if (!modalContent) {
        console.error("CRITICAL ERROR: Modal content element doesn't exist!");
        return false;
    }
    
    console.log("Modal structure check passed ✓");
    return true;
}

// Direct override of openRideDetails - simplified for debugging
function openRideDetailsFixed(ride) {
    console.log("DIRECT FIX: Opening ride details for ride:", ride);
    
    // Check modal structure
    if (!checkModalStructure()) {
        alert("Modal structure issue detected. Check console for details.");
        return;
    }
    
    // Get modal elements
    const modal = document.getElementById('ride-details-modal');
    const overlay = document.getElementById('ride-details-modal-overlay');
    const modalContent = modal.querySelector('.modal-content');
    
    // Fill in the data
    try {
        document.getElementById('ride-details-title').textContent = `Ride Details #${ride.id}`;
        document.getElementById('ride-details-date').textContent = ride.formatted_date || 'N/A';
        document.getElementById('ride-details-time').textContent = ride.formatted_time || '';
        document.getElementById('ride-details-pickup').textContent = ride.pickup || 'N/A';
        document.getElementById('ride-details-dropoff').textContent = ride.dropoff || 'N/A';
        document.getElementById('ride-details-fare').textContent = ride.formatted_fare || 'N/A';
        document.getElementById('ride-details-vehicle-type').textContent = ride.vehicle_type || 'N/A';
        
        // Status badge
        const statusBadge = document.getElementById('ride-details-status-badge');
        if (statusBadge) {
            if (typeof getRideStatusUI === 'function') {
                statusBadge.innerHTML = getRideStatusUI(ride.status);
            } else {
                statusBadge.innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-600/20 text-gray-300 border border-gray-500/30">${ride.status || 'Unknown'}</span>`;
            }
        }
        
        // Driver info (might be null)
        document.getElementById('details-driver-name').textContent = ride.driver_name || 'N/A';
        document.getElementById('details-driver-rating').textContent = ride.driver_rating || 'N/A';
        document.getElementById('details-driver-vehicle').textContent = ride.vehicle_model || ride.vehicle_type || 'N/A';
        document.getElementById('details-driver-plate').textContent = ride.vehicle_plate || 'N/A';
        document.getElementById('details-driver-phone').textContent = ride.driver_phone || 'N/A';
        
        console.log("Modal content populated successfully ✓");
    } catch (error) {
        console.error("Error populating modal content:", error);
    }
    
    // CRITICAL FIXES
    // 1. Force display style with !important
    modal.style.cssText = "display: flex !important; z-index: 9999 !important;";
    
    // 2. Make sure overlay is visible
    overlay.style.cssText = "opacity: 1 !important; visibility: visible !important;";
    
    // 3. Add animation class to content
    modalContent.classList.remove('animate-slide-down');
    modalContent.classList.add('animate-slide-up');
    
    // 4. Lock body scroll
    document.body.style.overflow = 'hidden';
    
    console.log("Modal display styles applied ✓");
    console.log("Modal should now be visible!");
    
    // Ensure close button works
    const closeButtons = modal.querySelectorAll('.modal-close-btn');
    closeButtons.forEach(button => {
        button.onclick = function() {
            closeRideDetailsFixed();
        };
    });
    
    // Ensure overlay closes modal
    overlay.onclick = function(e) {
        if (e.target === overlay) {
            closeRideDetailsFixed();
        }
    };
}

// Direct override of closeRideDetailsModal - simplified for debugging
function closeRideDetailsFixed() {
    console.log("DIRECT FIX: Closing ride details modal");
    
    const modal = document.getElementById('ride-details-modal');
    const modalContent = modal.querySelector('.modal-content');
    
    if (modalContent) {
        modalContent.classList.remove('animate-slide-up');
        modalContent.classList.add('animate-slide-down');
    }
    
    // Hide modal with a slight delay for animation
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        console.log("Modal hidden ✓");
    }, 300);
}

// Register a new event handler that uses our direct fix
function setupDirectRideDetailsHandlers() {
    console.log("Setting up direct fix handlers for ride details");
    
    // For all ride items
    document.querySelectorAll('.ride-history-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.closest('button')) {
                const rideId = this.dataset.rideId;
                console.log("Direct fix: Ride item clicked, ID:", rideId);
                
                // Fetch details directly from API
                fetch(`/api/api-ride-details.php?id=${rideId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include'
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Direct fix API response:", data);
                    if (data.success && data.data && data.data.ride) {
                        // Use our fixed function
                        openRideDetailsFixed(data.data.ride);
                    } else {
                        console.error("API returned invalid data structure:", data);
                    }
                })
                .catch(error => {
                    console.error("Error fetching ride details:", error);
                });
            }
        });
    });
    
    // For all view details buttons
    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const rideId = this.closest('.ride-history-item').dataset.rideId;
            console.log("Direct fix: View details button clicked, ID:", rideId);
            
            // Fetch details directly from API
            fetch(`/api/api-ride-details.php?id=${rideId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                console.log("Direct fix API response:", data);
                if (data.success && data.data && data.data.ride) {
                    // Use our fixed function
                    openRideDetailsFixed(data.data.ride);
                } else {
                    console.error("API returned invalid data structure:", data);
                }
            })
            .catch(error => {
                console.error("Error fetching ride details:", error);
            });
        });
    });
    
    console.log("Direct fix handlers setup complete ✓");
}

// Apply our direct fix when DOM is loaded and any time rides tab content changes
document.addEventListener('DOMContentLoaded', function() {
    // Add a mutation observer to detect when ride history items are added to the DOM
    const ridesTabContent = document.getElementById('rides-tab-content');
    if (ridesTabContent) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && 
                    document.querySelector('.ride-history-item')) {
                    console.log("Rides tab content changed, setting up direct fix handlers");
                    setupDirectRideDetailsHandlers();
                }
            });
        });
        
        observer.observe(ridesTabContent, { childList: true, subtree: true });
    }
    
    // Also check if we need to setup handlers immediately
    if (document.querySelector('.ride-history-item')) {
        setupDirectRideDetailsHandlers();
    }
    
    // Override the originals with our fixed versions
    window.originalOpenRideDetails = window.openRideDetails || function(){};
    window.openRideDetails = function(ride) {
        console.log("Intercepted openRideDetails call, using fixed version");
        openRideDetailsFixed(ride);
    };
    
    window.originalCloseRideDetailsModal = window.closeRideDetailsModal || function(){};
    window.closeRideDetailsModal = function() {
        console.log("Intercepted closeRideDetailsModal call, using fixed version");
        closeRideDetailsFixed();
    };
    
    console.log("Direct fix for ride details modal applied ✓");
});