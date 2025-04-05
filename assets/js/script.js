let map; 
let pickupAutocomplete, dropoffAutocomplete, schedulePickupAutocomplete, scheduleDropoffAutocomplete;
let geocoder;
let isGoogleMapsLoaded = false;
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

function initMap() {
    console.log("Initializing Google Maps...");
    
    if (typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
        console.error("Google Maps API not loaded correctly. Check API key and script tag.");
        showMapFallback();
        showConfirmation("Error loading map features. Please reload the page or try again later.", true);
        return; 
    }

    console.log("Google Maps API loaded successfully.");
    isGoogleMapsLoaded = true;
    geocoder = new google.maps.Geocoder();

    const guyanaCenter = { lat: 6.8013, lng: -58.1551 }; // Georgetown, Guyana

    const mapStyles = [ 
        { elementType: "geometry", stylers: [{ color: "#242f3e" }] }, 
        { elementType: "labels.text.stroke", stylers: [{ color: "#242f3e" }] }, 
        { elementType: "labels.text.fill", stylers: [{ color: "#746855" }] }, 
        { featureType: "administrative.locality", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] }, 
        { featureType: "poi", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] }, 
        { featureType: "poi.park", elementType: "geometry", stylers: [{ color: "#263c3f" }] }, 
        { featureType: "poi.park", elementType: "labels.text.fill", stylers: [{ color: "#6b9a76" }] }, 
        { featureType: "road", elementType: "geometry", stylers: [{ color: "#38414e" }] }, 
        { featureType: "road", elementType: "geometry.stroke", stylers: [{ color: "#212a37" }] }, 
        { featureType: "road", elementType: "labels.text.fill", stylers: [{ color: "#9ca5b3" }] }, 
        { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#746855" }] }, 
        { featureType: "road.highway", elementType: "geometry.stroke", stylers: [{ color: "#1f2835" }] }, 
        { featureType: "road.highway", elementType: "labels.text.fill", stylers: [{ color: "#f3d19c" }] }, 
        { featureType: "transit", elementType: "geometry", stylers: [{ color: "#2f3948" }] }, 
        { featureType: "transit.station", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] }, 
        { featureType: "water", elementType: "geometry", stylers: [{ color: "#17263c" }] }, 
        { featureType: "water", elementType: "labels.text.fill", stylers: [{ color: "#515c6d" }] }, 
        { featureType: "water", elementType: "labels.text.stroke", stylers: [{ color: "#17263c" }] }, 
    ];

    const mapElement = document.getElementById('map-canvas');
    if (mapElement) {
         try {
            map = new google.maps.Map(mapElement, {
                center: guyanaCenter,
                zoom: 12, 
                mapTypeId: 'roadmap',
                styles: mapStyles, 
                disableDefaultUI: true, 
                zoomControl: true,
                streetViewControl: false,
                mapTypeControl: false,
                fullscreenControl: false,
                gestureHandling: 'cooperative',
                mapTypeControlOptions: {
                    position: google.maps.ControlPosition.TOP_RIGHT
                },
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_BOTTOM
                }
            });
            console.log("Map initialized.");
            
            const centerMarker = new google.maps.Marker({
                position: guyanaCenter,
                map: map,
                title: "Georgetown, Guyana",
                animation: google.maps.Animation.DROP,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 8,
                    fillColor: "#10b981",
                    fillOpacity: 1,
                    strokeColor: "#ffffff",
                    strokeWeight: 2,
                }
            });
            
         } catch (error) {
             console.error("Error initializing Google Map:", error);
             showMapFallback();
             showConfirmation("Could not display the map.", true);
         }
    } else {
        console.error("Map canvas element not found.");
    }

    initAutocompleteFields();
}

function initAutocompleteFields() {
    const autocompleteOptions = {
        componentRestrictions: { country: "gy" }, 
        fields: ["address_components", "geometry", "icon", "name", "formatted_address"], 
        strictBounds: false, 
    };

    const pickupInput = document.getElementById('pickup-address');
    const dropoffInput = document.getElementById('dropoff-address');
    const schedulePickupInput = document.getElementById('schedule-pickup-address');
    const scheduleDropoffInput = document.getElementById('schedule-dropoff-address');

    const initAutocomplete = (inputElement, options) => {
        if (!inputElement || !isGoogleMapsLoaded) {
            console.warn(`Cannot initialize autocomplete for ${inputElement?.id || 'unknown input'}`);
            return null;
        }
        
        try {
            const autocomplete = new google.maps.places.Autocomplete(inputElement, options);
            
            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();
                if (place && place.formatted_address) {
                    console.log(`${inputElement.id} Place Selected:`, place.formatted_address);
                    
                    if (inputElement.id === 'pickup-address' || inputElement.id === 'dropoff-address') {
                        updateFareEstimate();
                    } else if (inputElement.id === 'schedule-pickup-address' || inputElement.id === 'schedule-dropoff-address') {
                        updateScheduleFareEstimate();
                    }
                    
                    if (inputElement.id === 'pickup-address' || inputElement.id === 'schedule-pickup-address') {
                        if (place.geometry && place.geometry.location && map) {
                            map.setCenter(place.geometry.location);
                            map.setZoom(15);
                            
                            new google.maps.Marker({
                                position: place.geometry.location,
                                map: map,
                                title: "Pickup Location",
                                animation: google.maps.Animation.DROP,
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    scale: 8,
                                    fillColor: "#10b981",
                                    fillOpacity: 1,
                                    strokeColor: "#ffffff",
                                    strokeWeight: 2,
                                }
                            });
                        }
                    }
                    
                } else {
                    console.log("Autocomplete selection cleared or invalid place.");
                    if (inputElement.id === 'pickup-address' || inputElement.id === 'dropoff-address') {
                        document.getElementById('fare-estimate').textContent = ''; 
                    } else if (inputElement.id === 'schedule-pickup-address' || inputElement.id === 'schedule-dropoff-address') {
                        document.getElementById('schedule-fare-estimate').textContent = '';
                    }
                }
            });
            
            return autocomplete; 
        } catch (error) {
            console.error(`Error initializing autocomplete for ${inputElement.id}:`, error);
            return null;
        }
    };

    try {
        pickupAutocomplete = initAutocomplete(pickupInput, autocompleteOptions);
        dropoffAutocomplete = initAutocomplete(dropoffInput, autocompleteOptions);
        schedulePickupAutocomplete = initAutocomplete(schedulePickupInput, autocompleteOptions);
        scheduleDropoffAutocomplete = initAutocomplete(scheduleDropoffInput, autocompleteOptions);
    } catch (error) {
        console.error("Error initializing autocomplete:", error);
        showConfirmation("Error setting up address search. Please try again later.", true);
    }
}

function getCurrentLocation(inputId, buttonElement) {
    if (!navigator.geolocation) {
        showConfirmation("Geolocation is not supported by your browser.", true);
        return;
    }

    if (!geocoder) {
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

            geocoder.geocode({ location: latLng }, (results, status) => {
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
                        
                        if(map) {
                            map.setCenter(latLng);
                            map.setZoom(15);
                            
                            new google.maps.Marker({
                                position: latLng,
                                map: map,
                                title: "Your Location",
                                animation: google.maps.Animation.DROP,
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    scale: 8,
                                    fillColor: "#10b981",
                                    fillOpacity: 1,
                                    strokeColor: "#ffffff",
                                    strokeWeight: 2,
                                }
                            });
                        }
                        
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

function updateFareEstimate() {
    const pickup = document.getElementById('pickup-address').value;
    const dropoff = document.getElementById('dropoff-address').value;
    const fareEstimateDiv = document.getElementById('fare-estimate');
    const selectedVehicleType = document.querySelector('input[name="vehicleType"]:checked');

    if (pickup && dropoff && selectedVehicleType && fareEstimateDiv) {
        fareEstimateDiv.textContent = 'Calculating...';
        fareEstimateDiv.classList.add('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
        
        // Call the fare estimation API directly
        fetch('api/api-fare-estimate.php', {
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
            showConfirmation('Error connecting to fare estimation service', true);
            fareEstimateDiv.classList.remove('animate-pulse-slow');
        });
    } else if (fareEstimateDiv) {
        fareEstimateDiv.textContent = ''; 
        fareEstimateDiv.classList.remove('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
    }
}


function updateScheduleFareEstimate() {
    const pickup = document.getElementById('schedule-pickup-address').value;
    const dropoff = document.getElementById('schedule-dropoff-address').value;
    const fareEstimateDiv = document.getElementById('schedule-fare-estimate');
    const selectedVehicleType = document.querySelector('input[name="scheduleVehicleType"]:checked');

    if (pickup && dropoff && selectedVehicleType && fareEstimateDiv) {
        fareEstimateDiv.textContent = 'Calculating...';
        fareEstimateDiv.classList.add('bg-gray-700/50', 'p-2', 'rounded-lg', 'animate-pulse-slow');
        
        // Call the fare estimation API directly
        fetch('api/api-fare-estimate.php', {
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
            showConfirmation('Error connecting to fare estimation service', true);
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

function showMapFallback() {
    const mapElement = document.getElementById('map-canvas');
    const mapFallback = document.getElementById('map-fallback');
    
    if (mapElement && mapFallback) {
        mapFallback.classList.remove('hidden');
        mapElement.style.backgroundColor = '#1f2937';
        mapElement.style.border = '1px solid #4b5563';
    }
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
    
    fetch('process-booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData),
        credentials: 'same-origin' // Include cookies for session
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
            currentRideId = data.booking_id;
            
            const bookingSection = document.getElementById('booking-section');
            const rideStatusSection = document.getElementById('ride-status');
            const mapCanvas = document.getElementById('map-canvas');
            
            if (bookingSection && rideStatusSection) {
                bookingSection.classList.add('hidden');
                if (mapCanvas) mapCanvas.classList.add('hidden');
                rideStatusSection.classList.remove('hidden');
                
                document.getElementById('status-message').textContent = 'Searching for nearby drivers...';
                document.getElementById('driver-name').textContent = '---';
                document.getElementById('driver-rating').textContent = '---';
                document.getElementById('driver-vehicle').textContent = '---';
                document.getElementById('driver-eta').textContent = '---';
                
                const loadingElement = document.getElementById('ride-status-loading');
                if (loadingElement) loadingElement.classList.remove('hidden');
                
                const driverCard = document.getElementById('driver-card');
                if (driverCard) driverCard.classList.add('hidden');
                
                // Start the polling for driver status
                pollDriverStatus(currentRideId, 0);
            }
            
            showConfirmation(data.message);
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
        console.error('Error requesting ride:', error);
        showConfirmation('Error connecting to booking service. Please try again.', true);
    });
}

function pollDriverStatus(bookingId, stage) {
    if (!bookingId) return;
    
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
            throw new Error(`Server responded with ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            updateRideStatus(data);
            
            // If there's a next stage, poll again after the specified time
            if (data.data && data.data.next_stage !== undefined && data.data.waiting_time) {
                setTimeout(() => {
                    pollDriverStatus(bookingId, data.data.next_stage);
                }, data.data.waiting_time * 1000);
            }
        } else {
            console.error('Error polling driver status:', data.message);
            document.getElementById('status-message').textContent = 'Error updating ride status';
            
            // Try again after a delay for errors
            setTimeout(() => {
                pollDriverStatus(bookingId, stage);
            }, 10000); // Retry after 10 seconds
        }
    })
    .catch(error => {
        console.error('Error polling driver status:', error);
        
        // Try again after a delay, but only if we're in the early stages
        if (stage < 3) {
            setTimeout(() => {
                pollDriverStatus(bookingId, stage);
            }, 10000); // Retry after 10 seconds
        }
    });
}

function updateRideStatus(data) {
    const statusMessage = document.getElementById('status-message');
    const driverName = document.getElementById('driver-name');
    const driverRating = document.getElementById('driver-rating');
    const driverVehicle = document.getElementById('driver-vehicle');
    const driverEta = document.getElementById('driver-eta');
    const loadingElement = document.getElementById('ride-status-loading');
    const driverCard = document.getElementById('driver-card');
    
    statusMessage.textContent = data.message;
    
    if (data.status === 'searching') {
        // Still searching, keep the loading indicator visible
        if (loadingElement) loadingElement.classList.remove('hidden');
        if (driverCard) driverCard.classList.add('hidden');
    } else if (data.status === 'confirmed' && data.data && data.data.driver) {
        // Driver found
        if (loadingElement) loadingElement.classList.add('hidden');
        
        driverName.textContent = data.data.driver.name;
        driverRating.textContent = data.data.driver.rating;
        driverVehicle.textContent = `${data.data.driver.vehicle} (${data.data.driver.plate})`;
        driverEta.textContent = data.data.driver.eta;
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
            document.getElementById('driver-card-name').textContent = data.data.driver.name;
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating;
        }
        
        showConfirmation(`Driver found! ${data.data.driver.name} is on the way.`, false);
    } else if (data.status === 'arriving' && data.data && data.data.driver) {
        // Driver is arriving
        if (loadingElement) loadingElement.classList.add('hidden');
        
        driverName.textContent = data.data.driver.name;
        driverRating.textContent = data.data.driver.rating;
        driverVehicle.textContent = `${data.data.driver.vehicle} (${data.data.driver.plate})`;
        driverEta.textContent = data.data.driver.eta;
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
            document.getElementById('driver-card-name').textContent = data.data.driver.name;
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating;
        }
        
        showConfirmation('Your driver is arriving soon!', false);
    } else if (data.status === 'arrived') {
        // Driver has arrived
        if (loadingElement) loadingElement.classList.add('hidden');
        
        driverName.textContent = data.data.driver.name;
        driverRating.textContent = data.data.driver.rating;
        driverVehicle.textContent = `${data.data.driver.vehicle} (${data.data.driver.plate})`;
        driverEta.textContent = '0'; // Has arrived
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
            document.getElementById('driver-card-name').textContent = data.data.driver.name;
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating;
        }
        
        showConfirmation('Your driver has arrived!', false);
    } else if (data.status === 'in_progress') {
        // Ride is in progress
        if (loadingElement) loadingElement.classList.add('hidden');
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
        }
        
        showConfirmation('Your ride is in progress. Estimated arrival: ' + data.data.estimated_arrival_time, false);
    } else if (data.status === 'completed') {
        // Ride completed
        if (loadingElement) loadingElement.classList.add('hidden');
        
        // Show completion information
        showConfirmation(`Ride completed! Fare: ${data.data.fare}`, false);
        
        // Show rating dialog or return to booking
        setTimeout(() => {
            resetBookingForm();
        }, 5000);
    }
}

function cancelRide() {
    if (!currentRideId) {
        console.error("No active ride to cancel");
        return;
    }
    
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
            resetBookingForm();
            showConfirmation('Ride cancelled.');
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

// FIXED: Improved logout handler function that fixes the "no logout links found" warning
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

// NEW SIGNUP HANDLER CODE
// Find all signup forms on the page
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

// Signup Handler Helper Functions
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

// Add these modifications to your assets/js/script.js file
// This updates the UI to handle the new ETA format from the API

function updateRideStatus(data) {
    const statusMessage = document.getElementById('status-message');
    const driverName = document.getElementById('driver-name');
    const driverRating = document.getElementById('driver-rating');
    const driverVehicle = document.getElementById('driver-vehicle');
    const driverEta = document.getElementById('driver-eta');
    const loadingElement = document.getElementById('ride-status-loading');
    const driverCard = document.getElementById('driver-card');
    
    statusMessage.textContent = data.message;
    
    if (data.status === 'searching') {
        // Still searching, keep the loading indicator visible
        if (loadingElement) loadingElement.classList.remove('hidden');
        if (driverCard) driverCard.classList.add('hidden');
    } else if (data.status === 'confirmed' && data.data && data.data.driver) {
        // Driver found
        if (loadingElement) loadingElement.classList.add('hidden');
        
        driverName.textContent = data.data.driver.name;
        driverRating.textContent = data.data.driver.rating;
        driverVehicle.textContent = `${data.data.driver.vehicle} (${data.data.driver.plate})`;
        
        // Use the formatted ETA text if available, otherwise fall back to minutes
        if (data.data.driver.eta_text) {
            driverEta.textContent = data.data.driver.eta_text;
        } else {
            driverEta.textContent = data.data.driver.eta;
        }
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
            document.getElementById('driver-card-name').textContent = data.data.driver.name;
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating;
        }
        
        // Add location info if available
        let locationInfo = '';
        if (data.data.driver.location) {
            locationInfo = ` from ${data.data.driver.location}`;
        }
        showConfirmation(`Driver found! ${data.data.driver.name} is on the way${locationInfo}.`, false);
    } else if (data.status === 'arriving' && data.data && data.data.driver) {
        // Driver is arriving
        if (loadingElement) loadingElement.classList.add('hidden');
        
        driverName.textContent = data.data.driver.name;
        driverRating.textContent = data.data.driver.rating;
        driverVehicle.textContent = `${data.data.driver.vehicle} (${data.data.driver.plate})`;
        
        // Use the formatted ETA text if available, otherwise fall back to minutes
        if (data.data.driver.eta_text) {
            driverEta.textContent = data.data.driver.eta_text;
        } else {
            driverEta.textContent = data.data.driver.eta;
        }
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
            document.getElementById('driver-card-name').textContent = data.data.driver.name;
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating;
        }
        
        showConfirmation('Your driver is arriving soon!', false);
    } else if (data.status === 'arrived') {
        // Driver has arrived
        if (loadingElement) loadingElement.classList.add('hidden');
        
        driverName.textContent = data.data.driver.name;
        driverRating.textContent = data.data.driver.rating;
        driverVehicle.textContent = `${data.data.driver.vehicle} (${data.data.driver.plate})`;
        driverEta.textContent = 'Has arrived!'; // Use text instead of number
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
            document.getElementById('driver-card-name').textContent = data.data.driver.name;
            document.getElementById('driver-card-rating').textContent = data.data.driver.rating;
        }
        
        showConfirmation('Your driver has arrived!', false);
    } else if (data.status === 'in_progress') {
        // Ride is in progress
        if (loadingElement) loadingElement.classList.add('hidden');
        
        if (driverCard) {
            driverCard.classList.remove('hidden');
            driverCard.classList.add('show');
        }
        
        showConfirmation('Your ride is in progress. Estimated arrival: ' + data.data.estimated_arrival_time, false);
    } else if (data.status === 'completed') {
        // Ride completed
        if (loadingElement) loadingElement.classList.add('hidden');
        
        // Show completion information
        showConfirmation(`Ride completed! Fare: ${data.data.fare}`, false);
        
        // Show rating dialog or return to booking
        setTimeout(() => {
            resetBookingForm();
        }, 5000);
    }
}

function updateRewardPointsDisplay(points) {
    // Find the points display element
    const pointsElement = document.querySelector('.text-3xl.font-bold.text-white.mb-1');
    
    if (pointsElement && points !== undefined) {
        pointsElement.textContent = points.toLocaleString();
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

// Call this when the page loads
document.addEventListener('DOMContentLoaded', initCancelRideHandler);

window.initMap = initMap;
window.openModal = openModal;
window.closeModal = closeModal;
window.switchTab = switchTab;
window.toggleMobileMenu = toggleMobileMenu;