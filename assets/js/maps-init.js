/**
 * Google Maps initialization bridge
 * This file exposes the initMap function globally for the Google Maps API
 */

// Set up the global initMap function that the Google Maps API will call
window.initMap = function() {
    // Maps state variables
    let map; 
    let pickupMarker = null;
    let dropoffMarker = null;
    let directionsService = null;
    let directionsRenderer = null;
    let pickupAutocomplete, dropoffAutocomplete, schedulePickupAutocomplete, scheduleDropoffAutocomplete;
    let geocoder;
    let isGoogleMapsLoaded = false;
    
    // Main maps initialization
    function initializeMap() {
        if (typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.places === 'undefined') {
            showMapFallback();
            return; 
        }

        isGoogleMapsLoaded = true;
        geocoder = new google.maps.Geocoder();
        
        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
            suppressMarkers: true,
            polylineOptions: {
                strokeColor: '#10b981',
                strokeWeight: 5,
                strokeOpacity: 0.7
            }
        });

        const guyanaCenter = { lat: 6.8013, lng: -58.1551 };

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
            { featureType: "water", elementType: "labels.text.stroke", stylers: [{ color: "#17263c" }] }
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
                
                directionsRenderer.setMap(map);
                
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
                 showMapFallback();
             }
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

        const initAutocomplete = (inputElement, options, isPickup) => {
            if (!inputElement || !isGoogleMapsLoaded) {
                return null;
            }
            
            try {
                const autocomplete = new google.maps.places.Autocomplete(inputElement, options);
                
                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    if (place && place.formatted_address) {
                        if (inputElement.id === 'pickup-address' || inputElement.id === 'dropoff-address') {
                            if (typeof updateFareEstimate === 'function') {
                                updateFareEstimate();
                            }
                            
                            if (place.geometry && place.geometry.location) {
                                if (isPickup) {
                                    updatePickupOnMap(place.geometry.location, place.formatted_address);
                                } else {
                                    updateDropoffOnMap(place.geometry.location, place.formatted_address);
                                }
                                
                                checkAndDisplayRoute();
                            }
                        } else if (inputElement.id === 'schedule-pickup-address' || inputElement.id === 'schedule-dropoff-address') {
                            if (typeof updateScheduleFareEstimate === 'function') {
                                updateScheduleFareEstimate();
                            }
                        }
                    } else {
                        if (inputElement.id === 'pickup-address' || inputElement.id === 'dropoff-address') {
                            const fareEstimate = document.getElementById('fare-estimate');
                            if (fareEstimate) {
                                fareEstimate.textContent = '';
                            }
                            
                            if (isPickup && pickupMarker) {
                                pickupMarker.setMap(null);
                                pickupMarker = null;
                            } else if (!isPickup && dropoffMarker) {
                                dropoffMarker.setMap(null);
                                dropoffMarker = null;
                            }
                            
                            if (directionsRenderer) {
                                directionsRenderer.setDirections({routes: []});
                            }
                        } else if (inputElement.id === 'schedule-pickup-address' || inputElement.id === 'schedule-dropoff-address') {
                            const scheduleEstimate = document.getElementById('schedule-fare-estimate');
                            if (scheduleEstimate) {
                                scheduleEstimate.textContent = '';
                            }
                        }
                    }
                });
                
                return autocomplete; 
            } catch (error) {
                return null;
            }
        };

        try {
            pickupAutocomplete = initAutocomplete(pickupInput, autocompleteOptions, true);
            dropoffAutocomplete = initAutocomplete(dropoffInput, autocompleteOptions, false);
            schedulePickupAutocomplete = initAutocomplete(schedulePickupInput, autocompleteOptions, true);
            scheduleDropoffAutocomplete = initAutocomplete(scheduleDropoffInput, autocompleteOptions, false);
        } catch (error) {
            // Map autocomplete failed silently 
        }
    }

    function updatePickupOnMap(location, addressText) {
        if (!map) return;
        
        if (pickupMarker) {
            pickupMarker.setMap(null);
        }
        
        pickupMarker = new google.maps.Marker({
            position: location,
            map: map,
            title: "Pickup: " + addressText,
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
        
        const infoWindow = new google.maps.InfoWindow({
            content: "<div><strong>Pickup Location</strong><br>" + addressText + "</div>"
        });
        
        pickupMarker.addListener('click', () => {
            infoWindow.open(map, pickupMarker);
        });
        
        map.setCenter(location);
        map.setZoom(15);
    }

    function updateDropoffOnMap(location, addressText) {
        if (!map) return;
        
        if (dropoffMarker) {
            dropoffMarker.setMap(null);
        }
        
        dropoffMarker = new google.maps.Marker({
            position: location,
            map: map,
            title: "Dropoff: " + addressText,
            animation: google.maps.Animation.DROP,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: "#dc2626",
                fillOpacity: 1,
                strokeColor: "#ffffff",
                strokeWeight: 2,
            }
        });
        
        const infoWindow = new google.maps.InfoWindow({
            content: "<div><strong>Dropoff Location</strong><br>" + addressText + "</div>"
        });
        
        dropoffMarker.addListener('click', () => {
            infoWindow.open(map, dropoffMarker);
        });
        
        if (pickupMarker && dropoffMarker) {
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(pickupMarker.getPosition());
            bounds.extend(dropoffMarker.getPosition());
            map.fitBounds(bounds);
            
            const padding = { top: 50, right: 50, bottom: 50, left: 50 };
            map.fitBounds(bounds, padding);
        } else {
            map.setCenter(location);
            map.setZoom(15);
        }
    }

    function checkAndDisplayRoute() {
        if (!map || !directionsService || !directionsRenderer) return;
        
        if (pickupMarker && dropoffMarker) {
            const pickupLocation = pickupMarker.getPosition();
            const dropoffLocation = dropoffMarker.getPosition();
            
            directionsService.route({
                origin: pickupLocation,
                destination: dropoffLocation,
                travelMode: google.maps.TravelMode.DRIVING
            }, (response, status) => {
                if (status === "OK") {
                    directionsRenderer.setDirections(response);
                    
                    const route = response.routes[0];
                    if (route && route.legs.length > 0) {
                        const leg = route.legs[0];
                        
                        const distanceDurationInfo = document.createElement('div');
                        distanceDurationInfo.id = 'distance-duration-info';
                        distanceDurationInfo.className = 'bg-gray-800/90 text-white p-2 rounded shadow-lg text-sm';
                        distanceDurationInfo.style.position = 'absolute';
                        distanceDurationInfo.style.bottom = '20px';
                        distanceDurationInfo.style.left = '10px';
                        distanceDurationInfo.style.zIndex = '1';
                        distanceDurationInfo.innerHTML = `
                            <div class="font-medium">${leg.distance.text}</div>
                            <div class="text-gray-300">${leg.duration.text}</div>
                        `;
                        
                        const existingInfo = document.getElementById('distance-duration-info');
                        if (existingInfo) {
                            existingInfo.remove();
                        }
                        
                        const mapContainer = document.getElementById('map-canvas');
                        if (mapContainer) {
                            mapContainer.appendChild(distanceDurationInfo);
                        }
                    }
                }
            });
        }
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
    
    // Export map-related functions to global scope for use in script.js
    window.updatePickupOnMap = updatePickupOnMap;
    window.updateDropoffOnMap = updateDropoffOnMap;
    window.checkAndDisplayRoute = checkAndDisplayRoute;
    window.showMapFallback = showMapFallback;
    
    // Initialize the map
    initializeMap();
};