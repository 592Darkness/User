let currentRideId = null;
let offlineMode = false;

let isLoggedIn = false;
let currentUser = null;

const FARE_BASE_RATES = {
    standard: 1000,
    suv: 1500,
    premium: 2000
};

const FARE_MULTIPLIERS = {
    standard: 1.0,
    suv: 1.5,
    premium: 2.0
};

function updateFareEstimate() {
    const pickup = document.getElementById('pickup-address').value;
    const dropoff = document.getElementById('dropoff-address').value;
    const fareEstimateDiv = document.getElementById('fare-estimate');
    const selectedVehicleType = document.querySelector('input[name="vehicleType"]:checked');

    if (pickup && dropoff && selectedVehicleType && fareEstimateDiv) {
        fareEstimateDiv.textContent = 'Calculating...';
        fareEstimateDiv.classList.add('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
        
        // Call the NEW fare estimation API
        fetch('api/api-fare.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                pickup: pickup,
                dropoff: dropoff,
                vehicleType: selectedVehicleType.value
            }),
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                fareEstimateDiv.textContent = data.data.fare;
                
                // Show detailed tooltip on hover
                const detailsHtml = `
                    <div>
                        <p><strong>Distance:</strong> ${data.data.details.distance} km</p>
                        <p><strong>Base fare:</strong> G$${data.data.details.base_fare}</p>
                        <p><strong>Distance fare:</strong> G$${data.data.details.distance_fare}</p>
                        <p><strong>Total:</strong> ${data.data.fare}</p>
                    </div>
                `;
                fareEstimateDiv.setAttribute('title', `Distance: ${data.data.details.distance} km`);
                fareEstimateDiv.classList.remove('animate-pulse-slow');
            } else {
                fareEstimateDiv.textContent = 'Error calculating fare';
                showConfirmation(data.message || 'Error calculating fare estimate', true);
                fareEstimateDiv.classList.remove('animate-pulse-slow');
            }
        })
        .catch(error => {
            console.error('Error calculating fare:', error);
            fareEstimateDiv.textContent = 'Error calculating fare';
            showConfirmation('Error connecting to fare estimation service: ' + error.message, true);
            fareEstimateDiv.classList.remove('animate-pulse-slow');
        });
    } else if (fareEstimateDiv) {
        fareEstimateDiv.textContent = ''; 
        fareEstimateDiv.classList.remove('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
    }
}

// Replace the updateScheduleFareEstimate function
function updateScheduleFareEstimate() {
    const pickup = document.getElementById('schedule-pickup-address').value;
    const dropoff = document.getElementById('schedule-dropoff-address').value;
    const fareEstimateDiv = document.getElementById('schedule-fare-estimate');
    const selectedVehicleType = document.querySelector('input[name="scheduleVehicleType"]:checked');

    if (pickup && dropoff && selectedVehicleType && fareEstimateDiv) {
        fareEstimateDiv.textContent = 'Calculating...';
        fareEstimateDiv.classList.add('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
        
        // Call the NEW fare estimation API
        fetch('api/api-fare.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                pickup: pickup,
                dropoff: dropoff,
                vehicleType: selectedVehicleType.value
            }),
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                fareEstimateDiv.textContent = data.data.fare;
                fareEstimateDiv.setAttribute('title', `Distance: ${data.data.details.distance} km`);
                fareEstimateDiv.classList.remove('animate-pulse-slow');
            } else {
                fareEstimateDiv.textContent = 'Error calculating fare';
                showConfirmation(data.message || 'Error calculating fare estimate', true);
                fareEstimateDiv.classList.remove('animate-pulse-slow');
            }
        })
        .catch(error => {
            console.error('Error calculating fare:', error);
            fareEstimateDiv.textContent = 'Error calculating fare';
            showConfirmation('Error connecting to fare estimation service. Please try again.', true);
            fareEstimateDiv.classList.remove('animate-pulse-slow');
        });
    } else if (fareEstimateDiv) {
        fareEstimateDiv.textContent = ''; 
        fareEstimateDiv.classList.remove('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
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

function toggleOfflineAlert(isOffline) {
    const offlineAlert = document.getElementById('offline-alert');
    if (offlineAlert) {
        if (isOffline) {
            offlineAlert.classList.remove('translate-y-full');
            offlineAlert.classList.add('translate-y-0');
        } else {
            offlineAlert.classList.remove('translate-y-0');
            offlineAlert.classList.add('translate-y-full');
        }
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.classList.remove('animate-slide-down');
            modalContent.classList.add('animate-slide-up');
        }
        
        setTimeout(() => {
            const firstInput = modal.querySelector('input, button:not(.modal-close-btn)');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
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
}

function switchTab(tabName) {
    const loginBtn = document.getElementById('login-tab-btn');
    const signupBtn = document.getElementById('signup-tab-btn');
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    
    if (!loginBtn || !signupBtn || !loginForm || !signupForm) return;

    if (tabName === 'login') {
        loginForm.classList.remove('hidden');
        signupForm.classList.add('hidden');
        loginBtn.classList.add('text-primary-400', 'border-primary-400');
        loginBtn.classList.remove('text-gray-400', 'hover:text-primary-300', 'border-transparent');
        loginBtn.setAttribute('aria-selected', 'true');
        signupBtn.classList.add('text-gray-400', 'hover:text-primary-300', 'border-transparent');
        signupBtn.classList.remove('text-primary-400', 'border-primary-400');
        signupBtn.setAttribute('aria-selected', 'false');

    } else if (tabName === 'signup') {
        loginForm.classList.add('hidden');
        signupForm.classList.remove('hidden');
        signupBtn.classList.add('text-primary-400', 'border-primary-400');
        signupBtn.classList.remove('text-gray-400', 'hover:text-primary-300', 'border-transparent');
        signupBtn.setAttribute('aria-selected', 'true');
        loginBtn.classList.add('text-gray-400', 'hover:text-primary-300', 'border-transparent');
        loginBtn.classList.remove('text-primary-400', 'border-primary-400');
        loginBtn.setAttribute('aria-selected', 'false');
    }
}

function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobile-menu');
    const menuButton = document.getElementById('mobile-menu-btn');
    
    if (mobileMenu && menuButton) {
        const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
        
        if (isExpanded) {
            mobileMenu.classList.add('hidden');
            menuButton.setAttribute('aria-expanded', 'false');
        } else {
            mobileMenu.classList.remove('hidden');
            menuButton.setAttribute('aria-expanded', 'true');
        }
    }
}

function togglePasswordVisibility(inputId, buttonId) {
    const passwordInput = document.getElementById(inputId);
    const toggleButton = document.getElementById(buttonId);
    
    if (passwordInput && toggleButton) {
        const icon = toggleButton.querySelector('.toggle-password-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            if (icon) icon.innerHTML = '&#xea76;';
        } else {
            passwordInput.type = 'password';
            if (icon) icon.innerHTML = '&#xea30;';
        }
    }
}

function validateBookingForm() {
    const pickup = document.getElementById('pickup-address').value.trim();
    const dropoff = document.getElementById('dropoff-address').value.trim();
    const vehicleType = document.querySelector('input[name="vehicleType"]:checked')?.value;
    
    if (!pickup) {
        showConfirmation('Please enter a pickup location.', true);
        return false;
    }
    
    if (!dropoff) {
        showConfirmation('Please enter a dropoff location.', true);
        return false;
    }
    
    if (!vehicleType) {
        showConfirmation('Please select a vehicle type.', true);
        return false;
    }
    
    return {
        pickup,
        dropoff,
        vehicleType,
        promo: document.getElementById('promo-code')?.value.trim() || ''
    };
}

function requestRide(formData) {
    showLoadingIndicator();
    
    // Clear any existing polling intervals
    stopStatusPolling();

    fetch('process-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData),
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoadingIndicator();
        
        if (data.success && data.booking_id) {
            // Set the current ride ID
            currentRideId = data.booking_id;
            
            // Update UI to show ride status tracking
            const bookingSection = document.getElementById('booking-section');
            const rideStatusSection = document.getElementById('ride-status');
            const mapCanvas = document.getElementById('map-canvas');
            
            if (bookingSection && rideStatusSection) {
                bookingSection.classList.add('hidden');
                if (mapCanvas) mapCanvas.classList.add('hidden');
                rideStatusSection.classList.remove('hidden');
                
                // Initial UI update for searching status
                updateRideStatusUI({
                    success: true,
                    status: 'searching',
                    message: 'Searching for nearby drivers...',
                    data: { driver: null }
                });
            }
            
            showConfirmation(data.message || 'Ride booked successfully!');
            
            // Start polling for driver status, starting at stage 0
            pollDriverStatus(currentRideId, 0);
        } else {
            showConfirmation(data.message || 'Error booking ride', true);
            
            // If login is required, open login modal
            if (data.redirect && data.redirect.includes('index.php')) {
                openModal('account-modal');
            }
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error requesting ride:', error);
        showConfirmation('Error connecting to booking service: ' + error.message, true);
    });
}

// Export functions for potential use in other modules
function pollDriverStatus(bookingId, stage) {
    if (!bookingId) {
        stopStatusPolling(); // Stop if bookingId becomes invalid
        return;
    }

    console.log(`Polling for booking ${bookingId}, stage ${stage}`);

    fetch('api/api-find-driver.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            booking_id: bookingId,
            stage: stage
        }),
        credentials: 'same-origin' // Include cookies for session
    })
    .then(response => {
        if (!response.ok) {
            console.error(`Server error: ${response.status}`);
            stopStatusPolling();
            showConfirmation('Error checking ride status. Please refresh.', true);
            throw new Error(`Server error: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Poll response:", data);
        if (data.success) {
            // Update the UI based on the received status and data
            updateRideStatusUI(data);

            // Determine if polling should continue
            const terminalStatuses = ['completed', 'cancelled', 'error'];
            if (data.status && terminalStatuses.includes(data.status)) {
                console.log(`Terminal status reached (${data.status}), stopping polling.`);
                stopStatusPolling();
                
                if(data.status === 'completed') {
                    setTimeout(() => resetBookingForm(), 5000); // Reset form after completion
                } else if (data.status === 'cancelled') {
                    resetBookingForm(); // Reset immediately on cancellation
                }
            } else if (data.data && data.data.next_stage !== undefined && data.data.waiting_time) {
                // Schedule the next poll if not terminal state
                const nextStage = data.data.next_stage;
                const waitTime = (data.data.waiting_time || 5) * 1000; // Default wait time 5s
                console.log(`Scheduling next poll for stage ${nextStage} in ${waitTime}ms`);

                // Clear previous interval before setting a new one
                stopStatusPolling();
                statusPollInterval = setTimeout(() => {
                    pollDriverStatus(bookingId, nextStage);
                }, waitTime);
            } else {
                // If no next stage info but not terminal, poll again with current stage after delay
                console.log("No next stage info, polling current stage again after delay.");
                stopStatusPolling();
                statusPollInterval = setTimeout(() => {
                    pollDriverStatus(bookingId, stage);
                }, 10000); // Retry after 10 seconds
            }
        } else {
            // Handle API errors (e.g., ride not found, server issue)
            console.error('API Error polling driver status:', data.message);
            stopStatusPolling();
            showConfirmation('Error updating ride status: ' + data.message, true);
            resetBookingForm(); // Reset form on error
        }
    })
    .catch(error => {
        // Handle network errors or fetch exceptions
        console.error('Network Error polling driver status:', error);
        showConfirmation('Network error checking ride status. Please check connection.', true);
        stopStatusPolling(); // Stop polling on network error
        resetBookingForm(); // Reset form on error
    });
}

function stopStatusPolling() {
    if (statusPollInterval) {
        clearTimeout(statusPollInterval); // Use clearTimeout since we used setTimeout
        statusPollInterval = null;
        console.log("Status polling stopped.");
    }
}

function updateRideStatusUI(data) {
    const statusMessage = document.getElementById('status-message');
    const driverName = document.getElementById('driver-name');
    const driverRating = document.getElementById('driver-rating');
    const driverVehicle = document.getElementById('driver-vehicle');
    const driverEta = document.getElementById('driver-eta');
    const loadingElement = document.getElementById('ride-status-loading');
    const driverCard = document.getElementById('driver-card');
    
    // Safeguard against missing elements
    if (!statusMessage || !driverName || !driverRating || !driverVehicle || !driverEta) {
        console.warn("Ride status UI elements are missing");
        return;
    }

    statusMessage.textContent = data.message || 'Updating status...';

    if (data.status === 'searching') {
        // Still searching, keep the loading indicator visible
        if (loadingElement) loadingElement.classList.remove('hidden');
        if (driverCard) driverCard.classList.add('hidden');
        
        driverName.textContent = '---';
        driverRating.textContent = '---';
        driverVehicle.textContent = '---';
        driverEta.textContent = '---';
    } else if (data.status === 'confirmed' && data.data && data.data.driver) {
        const driver = data.data.driver;
        
        // Driver found
        if (loadingElement) loadingElement.classList.add('hidden');
        if (driverCard) driverCard.classList.remove('hidden');
        
        // Update driver details
        driverName.textContent = driver.name || 'N/A';
        driverRating.textContent = driver.rating || 'N/A';
        driverVehicle.textContent = `${driver.vehicle || 'N/A'} (${driver.plate || 'N/A'})`;
        
        // Flexible ETA handling
        const etaText = driver.eta_text || 
                        (driver.eta !== undefined ? `${driver.eta} min` : 'N/A');
        driverEta.textContent = etaText;
        
        // Update driver card
        if (driverCard) {
            document.getElementById('driver-card-name').textContent = driver.name || 'N/A';
            document.getElementById('driver-card-rating').textContent = driver.rating || 'N/A';
        }
        
        // Show confirmation message
        let locationInfo = driver.location ? ` from ${driver.location}` : '';
        showConfirmation(`Driver found! ${driver.name} is on the way${locationInfo}.`);
    } else if (data.status === 'arriving' && data.data && data.data.driver) {
        const driver = data.data.driver;
        
        // Driver is arriving
        if (loadingElement) loadingElement.classList.add('hidden');
        if (driverCard) driverCard.classList.remove('hidden');
        
        // Update driver details
        driverName.textContent = driver.name || 'N/A';
        driverRating.textContent = driver.rating || 'N/A';
        driverVehicle.textContent = `${driver.vehicle || 'N/A'} (${driver.plate || 'N/A'})`;
        
        // Flexible ETA handling
        const etaText = driver.eta_text || 
                        (driver.eta !== undefined ? `${driver.eta} min` : 'N/A');
        driverEta.textContent = etaText;
        
        // Update driver card
        if (driverCard) {
            document.getElementById('driver-card-name').textContent = driver.name || 'N/A';
            document.getElementById('driver-card-rating').textContent = driver.rating || 'N/A';
        }
        
        showConfirmation('Your driver is arriving soon!');
    } else if (data.status === 'arrived') {
        // Driver has arrived
        if (loadingElement) loadingElement.classList.add('hidden');
        if (driverCard) driverCard.classList.remove('hidden');
        
        // Update details when driver arrives
        driverName.textContent = data.data.driver.name || 'N/A';
        driverRating.textContent = data.data.driver.rating || 'N/A';
        driverVehicle.textContent = `${data.data.driver.vehicle || 'N/A'} (${data.data.driver.plate || 'N/A'})`;
        driverEta.textContent = 'Arrived';
        
        // Update driver card
        if (driverCard) {
            document.getElementById('driver-card-name').textContent = data.data.driver.name || 'N/A';
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating || 'N/A';
        }
        
        showConfirmation('Your driver has arrived!');
    } else if (data.status === 'in_progress') {
        // Ride is in progress
        if (loadingElement) loadingElement.classList.add('hidden');
        if (driverCard) driverCard.classList.remove('hidden');
        
        const estimatedArrival = data.data.estimated_arrival_time;
        showConfirmation(`Your ride is in progress. Estimated arrival: ${estimatedArrival}`);
    } else if (data.status === 'completed') {
        // Ride completed
        if (loadingElement) loadingElement.classList.add('hidden');
        if (driverCard) driverCard.classList.add('hidden');
        
        // Show completion information
        showConfirmation(`Ride completed! Fare: ${data.data.fare}`);
        
        // Reset booking form after a short delay
        setTimeout(resetBookingForm, 5000);
    } else if (data.status === 'cancelled') {
        // Ride cancelled
        if (loadingElement) loadingElement.classList.add('hidden');
        if (driverCard) driverCard.classList.add('hidden');
        
        showConfirmation('Ride has been cancelled.', true);
        resetBookingForm();
    }
}

function cancelRide() {
    if (!currentRideId) {
        console.error("No active ride to cancel");
        showConfirmation("No active ride to cancel.", true);
        return;
    }
    
    // Show confirmation dialog first
    if (!confirm("Are you sure you want to cancel this ride? This action cannot be undone.")) {
        return;
    }
    
    // Stop any ongoing polling
    stopStatusPolling();
    
    showLoadingIndicator();
    
    fetch('api/api-cancel-ride.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            booking_id: currentRideId
        }),
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoadingIndicator();
        
        if (data.success) {
            // Reset the booking form and UI
            resetBookingForm();
            
            // Show success message with points info if available
            let message = 'Ride cancelled successfully.';
            if (data.data && data.data.points !== undefined) {
                message += ` Your current reward points remain at ${data.data.points}.`;
            }
            
            showConfirmation(message);
            
            // Update rewards points display if possible
            updateRewardPointsDisplay(data.data?.points);
        } else {
            showConfirmation(data.message || 'Error cancelling ride.', true);
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error cancelling ride:', error);
        
        // If we can't connect to the API, just reset the UI
        resetBookingForm();
        showConfirmation('Ride cancelled, but there was an error updating our servers.', true);
    });
}

function resetBookingForm() {
    currentRideId = null;
    
    const bookingSection = document.getElementById('booking-section');
    const rideStatusSection = document.getElementById('ride-status');
    const mapCanvas = document.getElementById('map-canvas');
    
    if (bookingSection && rideStatusSection) {
        rideStatusSection.classList.add('hidden');
        bookingSection.classList.remove('hidden');
        if (mapCanvas) mapCanvas.classList.remove('hidden');
        
        const bookingForm = document.getElementById('booking-form');
        if (bookingForm) bookingForm.reset();
        
        const fareEstimate = document.getElementById('fare-estimate');
        if (fareEstimate) {
            fareEstimate.textContent = '';
            fareEstimate.classList.remove('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
        }
    }
}

function validateScheduleForm() {
    const pickup = document.getElementById('schedule-pickup-address').value.trim();
    const dropoff = document.getElementById('schedule-dropoff-address').value.trim();
    const date = document.getElementById('schedule-date').value;
    const time = document.getElementById('schedule-time').value;
    const vehicleType = document.querySelector('input[name="scheduleVehicleType"]:checked')?.value;
    const notes = document.getElementById('schedule-notes').value.trim();
    
    if (!pickup) {
        showConfirmation('Please enter a pickup location.', true);
        return false;
    }
    
    if (!dropoff) {
        showConfirmation('Please enter a dropoff location.', true);
        return false;
    }
    
    if (!date) {
        showConfirmation('Please select a date.', true);
        return false;
    }
    
    if (!time) {
        showConfirmation('Please select a time.', true);
        return false;
    }
    
    const selectedDateTime = new Date(date + 'T' + time);
    const now = new Date();
    
    if (selectedDateTime <= now) {
        showConfirmation('Please select a future date and time.', true);
        return false;
    }
    
    if (!vehicleType) {
        showConfirmation('Please select a vehicle type.', true);
        return false;
    }
    
    return {
        pickup,
        dropoff,
        date,
        time,
        vehicleType,
        notes
    };
}

function scheduleRide(formData) {
    showLoadingIndicator();
    
    fetch('process-schedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData),
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoadingIndicator();
        closeModal('schedule-modal');
        
        if (data.success) {
            showConfirmation(data.message);
            
            // Reset the schedule form
            document.getElementById('schedule-form').reset();
            const scheduleFormEstimate = document.getElementById('schedule-fare-estimate');
            if (scheduleFormEstimate) {
                scheduleFormEstimate.textContent = '';
                scheduleFormEstimate.classList.remove('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
            }
            
            // Initialize the form again with default values
            initScheduleForm();
        } else {
            showConfirmation(data.message, true);
            
            // If user needs to login, redirect or open login modal
            if (data.redirect && data.redirect.includes('index.php')) {
                openModal('account-modal');
            }
        }
    })
    .catch(error => {
        hideLoadingIndicator();
        console.error('Error scheduling ride:', error);
        showConfirmation('Error connecting to scheduling service. Please try again.', true);
    });
}

function validateLoginForm() {
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;
    
    if (!email) {
        showConfirmation('Please enter your email.', true);
        return false;
    }
    
    if (!isValidEmail(email)) {
        showConfirmation('Please enter a valid email address.', true);
        return false;
    }
    
    if (!password) {
        showConfirmation('Please enter your password.', true);
        return false;
    }
    
    return {
        email,
        password
    };
}

function validateSignupForm() {
    const name = document.getElementById('signup-name').value.trim();
    const email = document.getElementById('signup-email').value.trim();
    const password = document.getElementById('signup-password').value;
    const phone = document.getElementById('signup-phone').value.trim();
    
    if (!name) {
        showConfirmation('Please enter your full name.', true);
        return false;
    }
    
    if (!email) {
        showConfirmation('Please enter your email.', true);
        return false;
    }
    
    if (!isValidEmail(email)) {
        showConfirmation('Please enter a valid email address.', true);
        return false;
    }
    
    if (!password) {
        showConfirmation('Please enter a password.', true);
        return false;
    }
    
    if (password.length < 8) {
        showConfirmation('Password must be at least 8 characters.', true);
        return false;
    }
    
    if (!phone) {
        showConfirmation('Please enter your phone number.', true);
        return false;
    }
    
    return {
        name,
        email,
        password,
        phone
    };
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function checkNetworkStatus() {
    if (navigator.onLine) {
        if (offlineMode) {
            offlineMode = false;
            toggleOfflineAlert(false);
            showConfirmation("You're back online!", false);
        }
    } else {
        offlineMode = true;
        toggleOfflineAlert(true);
        showConfirmation("You're offline. Some features may be unavailable.", true);
    }
}

function initScheduleForm() {
    const scheduleDate = document.getElementById('schedule-date');
    if (scheduleDate) {
        const today = new Date();
        const formattedDate = today.toISOString().split('T')[0];
        scheduleDate.min = formattedDate;
        scheduleDate.value = formattedDate;
    }
    
    const scheduleTime = document.getElementById('schedule-time');
    if (scheduleTime) {
        const now = new Date();
        now.setMinutes(now.getMinutes() + 30);
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        scheduleTime.value = `${hours}:${minutes}`;
    }
}

function updateUIForLoginState() {
    const loginButtons = document.querySelectorAll('#login-signup-btn, #login-signup-btn-mobile');
    const accountDropdowns = document.querySelectorAll('.account-dropdown');
    const userDisplayNames = document.querySelectorAll('.user-display-name');
    
    if (isLoggedIn && currentUser) {
        loginButtons.forEach(btn => btn.classList.add('hidden'));
        
        accountDropdowns.forEach(dropdown => {
            dropdown.classList.remove('hidden');
            
            const displayNameElement = dropdown.querySelector('.user-display-name');
            if (displayNameElement) {
                displayNameElement.textContent = currentUser.name || currentUser.email;
            }
        });
        
        userDisplayNames.forEach(el => {
            el.textContent = currentUser.name || currentUser.email;
        });
    } else {
        loginButtons.forEach(btn => btn.classList.remove('hidden'));
        
        accountDropdowns.forEach(dropdown => dropdown.classList.add('hidden'));
    }
}

function handleLogin(userData) {
    isLoggedIn = true;
    currentUser = userData;
    
    localStorage.setItem('isLoggedIn', 'true');
    localStorage.setItem('currentUser', JSON.stringify(userData));
    
    updateUIForLoginState();
}

function handleLogout() {
    isLoggedIn = false;
    currentUser = null;
    
    localStorage.removeItem('isLoggedIn');
    localStorage.removeItem('currentUser');
    
    updateUIForLoginState();
    
    showConfirmation('Logged out successfully.');
}

function checkLoginStatus() {
    const storedLoginStatus = localStorage.getItem('isLoggedIn');
    const storedUser = localStorage.getItem('currentUser');
    
    if (storedLoginStatus === 'true' && storedUser) {
        try {
            isLoggedIn = true;
            currentUser = JSON.parse(storedUser);
            updateUIForLoginState();
        } catch (error) {
            console.error('Error parsing stored user data:', error);
            handleLogout();
        }
    }
}

// Function to handle geolocation with the Google Maps functions exposed by maps-init.js
function getCurrentLocation(inputId, buttonElement) {
    if (!navigator.geolocation) {
        showConfirmation("Geolocation is not supported by your browser.", true);
        return;
    }

    if (!window.geocoder) {
        showConfirmation("Map services are still loading, please try again shortly.", true);
        return;
    }

    const inputElement = document.getElementById(inputId);
    if (!inputElement) {
        console.error(`Input element with ID ${inputId} not found.`);
        return;
    }

    const originalPlaceholder = inputElement.placeholder;
    inputElement.placeholder = "Fetching location...";
    if (buttonElement) {
        buttonElement.disabled = true;
        buttonElement.classList.add('opacity-50');
    }
    
    showLoadingIndicator();

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const latLng = {
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            };

            window.geocoder.geocode({ location: latLng }, (results, status) => {
                inputElement.placeholder = originalPlaceholder;
                if (buttonElement) {
                    buttonElement.disabled = false;
                    buttonElement.classList.remove('opacity-50');
                }
                
                hideLoadingIndicator();

                if (status === "OK") {
                    if (results[0]) {
                        inputElement.value = results[0].formatted_address;
                        console.log("Geocoded Address:", results[0].formatted_address);
                        showConfirmation("Location set!");
                        
                        // Use the global functions from maps-init.js
                        if (inputId === 'pickup-address') {
                            window.updatePickupOnMap(latLng, results[0].formatted_address);
                        } else if (inputId === 'dropoff-address') {
                            window.updateDropoffOnMap(latLng, results[0].formatted_address);
                        }
                        
                        window.checkAndDisplayRoute();
                        
                        if (inputId === 'pickup-address' || inputId === 'dropoff-address') {
                            updateFareEstimate();
                        } else if (inputId === 'schedule-pickup-address' || inputId === 'schedule-dropoff-address') {
                            updateScheduleFareEstimate();
                        }
                    } else {
                        showConfirmation("No address found for your location.", true);
                    }
                } else {
                    console.error("Geocoder failed due to: " + status);
                    showConfirmation("Could not determine address from location.", true);
                }
            });
        },
        (error) => {
            inputElement.placeholder = originalPlaceholder;
            if (buttonElement) {
                buttonElement.disabled = false;
                buttonElement.classList.remove('opacity-50');
            }
            
            hideLoadingIndicator();
            
            let errorMsg = "Error getting location: ";
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg += "Permission denied.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg += "Location information unavailable.";
                    break;
                case error.TIMEOUT:
                    errorMsg += "Request timed out.";
                    break;
                default:
                    errorMsg += "An unknown error occurred.";
                    break;
            }
            console.error(errorMsg, error);
            showConfirmation(errorMsg, true);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

function initLogoutHandlers() {
    console.log("Initializing logout handlers");
    
    // Find all logout links
    const logoutLinks = document.querySelectorAll('a[href="logout.php"], #logout-link, #mobile-logout-link');
    
    if (logoutLinks.length > 0) {
        console.log(`Found ${logoutLinks.length} logout links`);
        
        // Add event listeners to the found logout links
        logoutLinks.forEach(link => {
            // Remove any existing event listeners
            const newLink = link.cloneNode(true);
            link.parentNode.replaceChild(newLink, link);
            
            // Add our new event listener
            newLink.addEventListener('click', function(e) {
                console.log("Logout link clicked");
                e.preventDefault();
                
                // Show loading if available
                if (typeof showLoadingIndicator === 'function') {
                    showLoadingIndicator();
                }
                
                // First, clear local storage immediately
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('currentUser');
                
                // Update global variables
                window.isLoggedIn = false;
                window.currentUser = null;
                
                console.log("Client-side logout complete, calling server...");
                
                // Then make the server request
                fetch('logout.php', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin' // Include cookies
                })
                .then(response => {
                    console.log("Server responded: ", response.status);
                    return response.json().catch(() => {
                        // If not JSON, just return a success object
                        return { success: true };
                    });
                })
                .then(data => {
                    console.log("Logout successful, redirecting...");
                    
                    // Hide loading if available
                    if (typeof hideLoadingIndicator === 'function') {
                        hideLoadingIndicator();
                    }
                    
                    // Show confirmation if available
                    if (typeof showConfirmation === 'function') {
                        showConfirmation('Logged out successfully.');
                    }
                    
                    // Redirect with a small delay
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 500);
                })
                .catch(error => {
                    console.error("Logout error:", error);
                    
                    // Even if server request fails, still redirect
                    // Hide loading if available
                    if (typeof hideLoadingIndicator === 'function') {
                        hideLoadingIndicator();
                    }
                    
                    // Show error if available
                    if (typeof showConfirmation === 'function') {
                        showConfirmation('Logged out on this device, but there was a server error.', true);
                    }
                    
                    // Redirect with a small delay
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 500);
                });
            });
        });
    } else {
        // Don't show a warning if we're not logged in - this is expected behavior
        if (!window.isLoggedIn) {
            console.log("User not logged in, no logout links expected");
        } else {
            console.warn("User appears to be logged in but no logout links found");
        }
    }
}

function toggleAccountDropdown() {
    const accountDropdownMenu = document.getElementById('user-dropdown-menu');
    if (accountDropdownMenu) {
        // Toggle the dropdown visibility
        accountDropdownMenu.classList.toggle('hidden');
        
        // If we're opening the dropdown, add an event listener to close it when clicking outside
        if (!accountDropdownMenu.classList.contains('hidden')) {
            setTimeout(() => {
                document.addEventListener('click', closeAccountDropdownOnClickOutside);
            }, 10);
        }
    }
}

// Function to close the dropdown when clicking outside
function closeAccountDropdownOnClickOutside(e) {
    const accountDropdownBtn = document.getElementById('user-dropdown-btn');
    const accountDropdownMenu = document.getElementById('user-dropdown-menu');
    
    if (accountDropdownBtn && accountDropdownMenu && 
        !accountDropdownBtn.contains(e.target) && 
        !accountDropdownMenu.contains(e.target)) {
        
        accountDropdownMenu.classList.add('hidden');
        document.removeEventListener('click', closeAccountDropdownOnClickOutside);
    }
}

function toggleMobileAccountMenu() {
    const mobileAccountMenu = document.getElementById('mobile-account-menu');
    if (mobileAccountMenu) {
        mobileAccountMenu.classList.toggle('hidden');
    }
}

function attachSignupHandler(form) {
    // Remove any existing handlers
    const clonedForm = form.cloneNode(true);
    form.parentNode.replaceChild(clonedForm, form);
    form = clonedForm;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log("Signup form submitted");
        
        // Show loading indicator
        showLoadingIndicator();
        
        // Find form fields
        const nameInput = form.querySelector('input[name="name"]') || form.querySelector('input[id*="name"]');
        const emailInput = form.querySelector('input[type="email"]') || form.querySelector('input[id*="email"]');
        const passwordInput = form.querySelector('input[type="password"]');
        const phoneInput = form.querySelector('input[name="phone"]') || form.querySelector('input[id*="phone"]');
        
        if (!nameInput || !emailInput || !passwordInput || !phoneInput) {
            console.error("Could not find all required form fields");
            console.log("Fields found:", {
                name: !!nameInput,
                email: !!emailInput,
                password: !!passwordInput,
                phone: !!phoneInput
            });
            showConfirmation("Form submission error: Missing required fields", true);
            hideLoadingIndicator();
            return;
        }
        
        const formData = {
            name: nameInput.value,
            email: emailInput.value,
            password: passwordInput.value,
            phone: phoneInput.value
        };
        
        console.log("Sending signup data", formData.name, formData.email, formData.phone);
        
        // Send AJAX request to our working endpoint
        fetch('process-signup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData),
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            console.log("Raw response:", text);
            
            // Try to parse as JSON
            try {
                const data = JSON.parse(text);
                console.log("Parsed JSON response:", data);
                
                if (data.success) {
                    // Success! Store login data
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('currentUser', JSON.stringify({
                        name: formData.name,
                        email: formData.email,
                        phone: formData.phone
                    }));
                    
                    // Show success message
                    showConfirmation(data.message || "Account created successfully!");
                    
                    // Redirect after a delay
                    setTimeout(() => {
                        window.location.href = data.redirect || 'account-dashboard.php';
                    }, 1500);
                } else {
                    // Error from server
                    showConfirmation(data.message || "Error creating account", true);
                }
            } catch (e) {
                console.error("Error parsing JSON response:", e);
                showConfirmation("Error communicating with server. Please try again.", true);
            }
            
            hideLoadingIndicator();
        })
        .catch(error => {
            console.error("Fetch error:", error);
            showConfirmation("Connection error. Please try again later.", true);
            hideLoadingIndicator();
        });
    });
}

function handleSignupFromButton(button) {
    // Get closest container that might have input fields
    const container = button.closest('div') || document.body;
    
    // Find nearby input fields
    const nameInput = container.querySelector('input[id*="name"]') || container.querySelector('input[placeholder*="name"]');
    const emailInput = container.querySelector('input[type="email"]') || container.querySelector('input[id*="email"]');
    const passwordInput = container.querySelector('input[type="password"]');
    const phoneInput = container.querySelector('input[id*="phone"]') || container.querySelector('input[placeholder*="phone"]');
    
    if (!nameInput || !emailInput || !passwordInput || !phoneInput) {
        console.error("Could not find all required input fields near button");
        showConfirmation("Please fill in all required fields", true);
        return;
    }
    
    const formData = {
        name: nameInput.value,
        email: emailInput.value,
        password: passwordInput.value,
        phone: phoneInput.value
    };
    
    // Same AJAX process as form handler
    showLoadingIndicator();
    
    fetch('process-signup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData),
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('currentUser', JSON.stringify({
                    name: formData.name,
                    email: formData.email,
                    phone: formData.phone
                }));
                
                showConfirmation(data.message || "Account created successfully!");
                
                setTimeout(() => {
                    window.location.href = data.redirect || 'account-dashboard.php';
                }, 1500);
            } else {
                showConfirmation(data.message || "Error creating account", true);
            }
        } catch (e) {
            console.error("Error parsing JSON response:", e);
            showConfirmation("Error communicating with server. Please try again.", true);
        }
        
        hideLoadingIndicator();
    })
    .catch(error => {
        console.error("Fetch error:", error);
        showConfirmation("Connection error. Please try again later.", true);
        hideLoadingIndicator();
    });
}

function updateRewardPointsDisplay(points) {
    // Find the points display element
    const pointsElement = document.querySelector('.text-3xl.font-bold.text-white.mb-1');
    
    if (pointsElement && points !== undefined) {
        pointsElement.textContent = points.toLocaleString();
    }
}

// Helper function to initialize cancel ride handler
function initCancelRideHandler() {
    const cancelRideBtn = document.getElementById('cancel-ride-btn');
    if (cancelRideBtn) {
        // Remove any existing event listeners
        const newBtn = cancelRideBtn.cloneNode(true);
        cancelRideBtn.parentNode.replaceChild(newBtn, cancelRideBtn);
        
        // Add new event listener
        newBtn.addEventListener('click', cancelRide);
    }
}

// Global variable to store auto-refresh intervals
let statusPollInterval = null;
let historyRefreshInterval = null;

/**
 * Start automatic refresh for ride status
 * @param {number} bookingId - The ID of the current ride
 * @param {number} stage - The current stage of the ride
 * @param {number} interval - Refresh interval in milliseconds (default 5000ms)
 */
function startStatusAutoRefresh(bookingId, stage, interval = 5000) {
    // Clear any existing interval
    if (statusPollInterval) {
        clearInterval(statusPollInterval);
    }
    
    // Set new interval
    statusPollInterval = setInterval(() => {
        console.log("Auto-refreshing ride status...");
        pollDriverStatus(bookingId, stage);
    }, interval);
    
    // Store the interval ID in sessionStorage to persist across page refreshes
    sessionStorage.setItem('statusRefreshBookingId', bookingId);
    sessionStorage.setItem('statusRefreshStage', stage);
}

/**
 * Stop the automatic refresh for ride status
 */
function stopStatusAutoRefresh() {
    if (statusPollInterval) {
        clearInterval(statusPollInterval);
        statusPollInterval = null;
    }
    
    // Clear storage
    sessionStorage.removeItem('statusRefreshBookingId');
    sessionStorage.removeItem('statusRefreshStage');
}

/**
 * Start automatic refresh for ride history
 * @param {number} interval - Refresh interval in milliseconds (default 5000ms)
 */
function startHistoryAutoRefresh(interval = 5000) {
    // Clear any existing interval
    if (historyRefreshInterval) {
        clearInterval(historyRefreshInterval);
    }
    
    // Set new interval
    historyRefreshInterval = setInterval(() => {
        console.log("Auto-refreshing ride history...");
        
        // Preserve current filter and page
        const currentFilter = document.querySelector('[data-filter].active')?.dataset.filter || 'all';
        const currentPage = document.querySelector('.pagination-current-page')?.textContent || 1;
        
        // Reload ride history with current filter and page
        loadRideHistory(currentPage, currentFilter);
    }, interval);
}

/**
 * Stop the automatic refresh for ride history
 */
function stopHistoryAutoRefresh() {
    if (historyRefreshInterval) {
        clearInterval(historyRefreshInterval);
        historyRefreshInterval = null;
    }
}

// Document ready event listener
document.addEventListener('DOMContentLoaded', function() {
    // First check login status
    checkLoginStatus();
    
    // Then initialize logout handlers with a small delay to ensure the DOM has updated
    setTimeout(() => {
        initLogoutHandlers();
    }, 100);

    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        console.log("Found signup form, attaching handler");
        attachSignupHandler(signupForm);
    } else {
        // Try to find any form that looks like a signup form
        const possibleForms = document.querySelectorAll('form');
        possibleForms.forEach(form => {
            const hasNameField = form.querySelector('input[name="name"]') || form.querySelector('input[id*="name"]');
            const hasEmailField = form.querySelector('input[type="email"]') || form.querySelector('input[id*="email"]');
            const hasPasswordField = form.querySelector('input[type="password"]');
            
            if (hasNameField && hasEmailField && hasPasswordField) {
                console.log("Found potential signup form, attaching handler");
                attachSignupHandler(form);
            }
        });
    }
    
    // Also try to find any "Create Account" buttons that might trigger signup
    const signupButtons = document.querySelectorAll('button');
    signupButtons.forEach(button => {
        if (button.textContent.toLowerCase().includes('create account') || 
            button.textContent.toLowerCase().includes('sign up')) {
            console.log("Found signup button, attaching handler");
            button.addEventListener('click', function(e) {
                // If this button is inside a form, don't add another handler
                if (!button.closest('form')) {
                    e.preventDefault();
                    handleSignupFromButton(button);
                }
            });
        }
    });
    
    // Set up the rest of the page
    const currentYearElement = document.getElementById('current-year');
    if (currentYearElement) {
        currentYearElement.textContent = new Date().getFullYear();
    }

    const bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault(); 
            const formData = validateBookingForm();
            if (formData) {
                requestRide(formData);
            }
        });
    }
    
    const scheduleForm = document.getElementById('schedule-form');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = validateScheduleForm();
            if (formData) {
                scheduleRide(formData);
            }
        });
    }
    
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = validateLoginForm();
            if (formData) {
                showLoadingIndicator();
                
                fetch('process-login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(formData),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Login failed');
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoadingIndicator();
                    
                    if (data.success) {
                        closeModal('account-modal');
                        
                        handleLogin({
                            id: data.user.id,
                            email: data.user.email,
                            name: data.user.name,
                            phone: data.user.phone
                        });
                        
                        showConfirmation(data.message || 'Logged in successfully!');
                        loginForm.reset();
                        
                        // Redirect if needed
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    } else {
                        showConfirmation(data.message || 'Login failed. Please check your credentials.', true);
                    }
                })
                .catch(error => {
                    hideLoadingIndicator();
                    console.error('Login error:', error);
                    showConfirmation('Error connecting to the login service. Please try again later.', true);
                });
            }
        });
    }
    
    const loginSignupBtn = document.getElementById('login-signup-btn');
    const loginSignupBtnMobile = document.getElementById('login-signup-btn-mobile');
    const joinRewardsBtn = document.getElementById('join-rewards-btn');
    const scheduleRideLink = document.getElementById('schedule-ride-link');
    const scheduleRideNav = document.getElementById('schedule-ride-nav');
    const scheduleRideNavMobile = document.getElementById('schedule-ride-nav-mobile');
    
    if (loginSignupBtn) {
        loginSignupBtn.addEventListener('click', () => {
            openModal('account-modal');
        });
    }
    
    if (loginSignupBtnMobile) {
        loginSignupBtnMobile.addEventListener('click', () => {
            toggleMobileMenu();
            openModal('account-modal');
        });
    }
    
    if (joinRewardsBtn) {
        joinRewardsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (isLoggedIn) {
                localStorage.setItem('dashboardActiveTab', 'rewards');
                window.location.href = 'account-dashboard.php';
            } else {
                openModal('account-modal');
            }
        });
    }
    
    if (scheduleRideLink) {
        scheduleRideLink.addEventListener('click', (e) => {
            e.preventDefault();
            openModal('schedule-modal');
        });
    }
    
    if (scheduleRideNav) {
        scheduleRideNav.addEventListener('click', (e) => {
            e.preventDefault();
            openModal('schedule-modal');
        });
    }
    
    if (scheduleRideNavMobile) {
        scheduleRideNavMobile.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMobileMenu();
            openModal('schedule-modal');
        });
    }
    
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
    }
    
    const accountModalOverlay = document.getElementById('account-modal-overlay');
    const scheduleModalOverlay = document.getElementById('schedule-modal-overlay');
    
    if (accountModalOverlay) {
        accountModalOverlay.addEventListener('click', (e) => {
            if (e.target === accountModalOverlay) {
                closeModal('account-modal');
            }
        });
    }
    
    if (scheduleModalOverlay) {
        scheduleModalOverlay.addEventListener('click', (e) => {
            if (e.target === scheduleModalOverlay) {
                closeModal('schedule-modal');
            }
        });
    }
    
    const modalCloseBtns = document.querySelectorAll('.modal-close-btn');
    modalCloseBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const modal = btn.closest('[id]').id;
            closeModal(modal);
        });
    });
    
    const cancelRideBtn = document.getElementById('cancel-ride-btn');
    if (cancelRideBtn) {
        cancelRideBtn.addEventListener('click', cancelRide);
    }
    
    const currentLocationBtnMain = document.getElementById('use-current-location-main');
    const currentLocationBtnSchedule = document.getElementById('use-current-location-schedule');
    
    if (currentLocationBtnMain) {
        currentLocationBtnMain.addEventListener('click', function() {
            getCurrentLocation('pickup-address', this);
        });
    }
    
    if (currentLocationBtnSchedule) {
        currentLocationBtnSchedule.addEventListener('click', function() {
            getCurrentLocation('schedule-pickup-address', this);
        });
    }
    
    const vehicleTypeRadios = document.querySelectorAll('input[name="vehicleType"]');
    if (vehicleTypeRadios.length > 0) {
        vehicleTypeRadios.forEach(radio => {
            radio.addEventListener('change', updateFareEstimate);
        });
    }
    
    const scheduleVehicleTypeRadios = document.querySelectorAll('input[name="scheduleVehicleType"]');
    if (scheduleVehicleTypeRadios.length > 0) {
        scheduleVehicleTypeRadios.forEach(radio => {
            radio.addEventListener('change', updateScheduleFareEstimate);
        });
    }
    
    const togglePasswordBtn = document.getElementById('toggle-password');
    const toggleSignupPasswordBtn = document.getElementById('toggle-signup-password');
    
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', () => 
            togglePasswordVisibility('login-password', 'toggle-password')
        );
    }
    
    if (toggleSignupPasswordBtn) {
        toggleSignupPasswordBtn.addEventListener('click', () => 
            togglePasswordVisibility('signup-password', 'toggle-signup-password')
        );
    }
    
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
    
    const loginTabBtn = document.getElementById('login-tab-btn');
    const signupTabBtn = document.getElementById('signup-tab-btn');
    
    if (loginTabBtn) {
        loginTabBtn.addEventListener('click', () => switchTab('login'));
    }
    
    if (signupTabBtn) {
        signupTabBtn.addEventListener('click', () => switchTab('signup'));
    }
    
    const observeElements = () => {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in');
                    }
                });
            }, { threshold: 0.1 });
            
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach(card => {
                card.classList.remove('animate-fade-in');
                observer.observe(card);
            });
        } else {
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach(card => {
                card.classList.add('animate-fade-in');
            });
        }
    };
    
    window.addEventListener('online', checkNetworkStatus);
    window.addEventListener('offline', checkNetworkStatus);
    
    setTimeout(() => {
        observeElements();
        checkNetworkStatus();
    }, 500);
    
    // Account dropdown toggle
    const accountDropdownBtn = document.getElementById('user-dropdown-btn');
    if (accountDropdownBtn) {
        accountDropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleAccountDropdown();
        });
    }
    
    const mobileAccountDropdownBtn = document.getElementById('mobile-account-dropdown-btn');
    if (mobileAccountDropdownBtn) {
        mobileAccountDropdownBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMobileAccountMenu();
        });
    }
    
    const rideHistoryLinks = document.querySelectorAll('#ride-history-link, #mobile-ride-history-link');
    rideHistoryLinks.forEach(link => {
        link.addEventListener('click', () => {
            localStorage.setItem('dashboardActiveTab', 'rides');
        });
    });
    
    const savedPlacesLinks = document.querySelectorAll('#saved-places-link, #mobile-saved-places-link');
    savedPlacesLinks.forEach(link => {
        link.addEventListener('click', () => {
            localStorage.setItem('dashboardActiveTab', 'places');
        });
    });
    
    const paymentMethodsLinks = document.querySelectorAll('#payment-methods-link, #mobile-payment-methods-link');
    paymentMethodsLinks.forEach(link => {
        link.addEventListener('click', () => {
            localStorage.setItem('dashboardActiveTab', 'payment');
        });
    });
    
    // Rest of initialization
    initScheduleForm();
    
    // Check for stored ride status to resume auto-refresh if needed
    const storedRideId = sessionStorage.getItem('statusRefreshBookingId');
    const storedStage = sessionStorage.getItem('statusRefreshStage');
    
    if (storedRideId && storedStage) {
        currentRideId = storedRideId;
        // Resume polling with stored values
        pollDriverStatus(storedRideId, storedStage);
    }
    
    // Start auto-refresh for ride history if on the account dashboard page
    const ridesTabContent = document.getElementById('rides-tab-content');
    if (ridesTabContent && !ridesTabContent.classList.contains('hidden')) {
        startHistoryAutoRefresh();
    }
    
    // Listen for tab changes to start/stop history refresh
    const dashboardTabs = document.querySelectorAll('.dashboard-tab');
    dashboardTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.id;
            
            // If switching to rides tab, start auto-refresh
            if (tabId === 'rides-tab-btn') {
                startHistoryAutoRefresh();
            } else {
                // If switching away from rides tab, stop auto-refresh
                stopHistoryAutoRefresh();
            }
        });
    });
    
    // Request ride button - start auto-refresh when a new ride is requested
    const requestRideBtn = document.getElementById('request-ride-btn');
    if (requestRideBtn) {
        const originalSubmitHandler = requestRideBtn.onclick;
        
        requestRideBtn.onclick = function(e) {
            // Call original handler if it exists
            if (originalSubmitHandler) {
                originalSubmitHandler.call(this, e);
            }
            
            // Start auto-refresh for the new ride (with stage 0)
            if (currentRideId) {
                startStatusAutoRefresh(currentRideId, 0);
            }
        };
    }
});

// Also add this to handle errors related to session state
window.addEventListener('error', function(event) {
    // If we get errors related to session/login status, clear localStorage as a precaution
    if (event.message && 
        (event.message.includes('user') || 
         event.message.includes('login') || 
         event.message.includes('session'))) {
        
        console.warn('Error detected that might be related to login state, clearing localStorage');
        localStorage.removeItem('isLoggedIn');
        localStorage.removeItem('currentUser');
    }
});

window.openModal = openModal;
window.closeModal = closeModal;
window.switchTab = switchTab;
window.toggleMobileMenu = toggleMobileMenu;