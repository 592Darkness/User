document.addEventListener('DOMContentLoaded', function() {
    console.log("Admin rides page loaded");
    
    // Get CSRF token for AJAX requests
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
    
    // Add event listeners to view buttons
    const viewButtons = document.querySelectorAll('button[onclick*="openViewRideModal"]');
    viewButtons.forEach(button => {
        // Replace the inline onclick with proper event listener
        const rideId = button.getAttribute('onclick').match(/\((\d+)\)/)?.[1];
        if (rideId) {
            // Remove the inline onclick
            button.removeAttribute('onclick');
            
            // Add a click event listener
            button.addEventListener('click', function() {
                openViewRideModal(rideId);
            });
            
            console.log(`Added event listener to view button for ride ID: ${rideId}`);
        }
    });
    
    // Function to open modal
    window.openViewRideModal = function(rideId) {
        console.log(`Opening view ride modal for ID: ${rideId}`);
        openModal('view-ride-modal');
        
        // Show loading state
        document.getElementById('ride-details-content').innerHTML = `
            <div class="animate-pulse space-y-4">
                <div class="h-4 bg-gray-700 rounded w-3/4"></div>
                <div class="h-4 bg-gray-700 rounded w-1/2"></div>
                <div class="h-4 bg-gray-700 rounded w-2/3"></div>
            </div>
        `;
        
        // Fetch ride details
        fetch('process-admin-ride.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_ride',
                ride_id: rideId,
                csrf_token: csrfToken
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
            console.log("Response data:", data);
            if (data.success) {
                const ride = data.ride;
                
                // Format the content
                const content = `
                    <div class="bg-gray-700/30 rounded-lg p-4 mb-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-xl font-medium text-white">Ride #${ride.id}</h3>
                                <p class="text-sm text-gray-400">Created ${new Date(ride.created_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}</p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getRideStatusClass(ride.status)}">
                                ${ride.status.charAt(0).toUpperCase() + ride.status.slice(1)}
                            </span>
                        </div>
                    </div>
                    
                    <div class="space-y-4 mb-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Route Information</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                <p class="text-white flex items-start">
                                    <span class="lucide mr-2 text-gray-400 mt-1" aria-hidden="true">&#xea4b;</span> 
                                    <span>${ride.pickup}</span>
                                </p>
                                <div class="ml-6 my-1 border-l-2 border-gray-600 h-4"></div>
                                <p class="text-white flex items-start">
                                    <span class="lucide mr-2 text-gray-400 mt-1" aria-hidden="true">&#xea4a;</span>
                                    <span>${ride.dropoff}</span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-400 mb-1">User</h4>
                                <div class="bg-gray-700/30 rounded-lg p-3">
                                    <p class="text-white">${ride.user_name || 'Unknown'}</p>
                                    ${ride.user_email ? `<p class="text-xs text-gray-400 mt-1">${ride.user_email}</p>` : ''}
                                    ${ride.user_phone ? `<p class="text-xs text-gray-400">${ride.user_phone}</p>` : ''}
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-400 mb-1">Driver</h4>
                                <div class="bg-gray-700/30 rounded-lg p-3">
                                    ${ride.driver_name ? 
                                        `<p class="text-white">${ride.driver_name}</p>
                                         ${ride.driver_phone ? `<p class="text-xs text-gray-400 mt-1">${ride.driver_phone}</p>` : ''}
                                         ${ride.vehicle_type ? `<p class="text-xs text-gray-400">${ride.vehicle_type.toUpperCase()} vehicle</p>` : ''}`
                                        : 
                                        `<p class="text-gray-500">No driver assigned</p>`
                                    }
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-400 mb-1">Fare</h4>
                                <div class="bg-gray-700/30 rounded-lg p-3">
                                    <p class="text-xl font-medium text-yellow-400">${formatCurrency(ride.fare)}</p>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-400 mb-1">Vehicle Type</h4>
                                <div class="bg-gray-700/30 rounded-lg p-3">
                                    <p class="text-white">${ride.vehicle_type ? ride.vehicle_type.charAt(0).toUpperCase() + ride.vehicle_type.slice(1) : 'Unknown'}</p>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-400 mb-1">Created</h4>
                                <div class="bg-gray-700/30 rounded-lg p-3">
                                    <p class="text-white">${new Date(ride.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
                                    <p class="text-xs text-gray-400 mt-1">${new Date(ride.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}</p>
                                </div>
                            </div>
                        </div>
                        
                        ${ride.completed_at ? `
                        <div>
                            <h4 class="text-sm font-medium text-gray-400 mb-1">Completion</h4>
                            <div class="bg-gray-700/30 rounded-lg p-3">
                                <p class="text-white">Completed at ${new Date(ride.completed_at).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })}</p>
                                ${ride.status === 'completed' ? `<p class="text-xs text-green-400 mt-1">Ride completed successfully</p>` : ''}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                document.getElementById('ride-details-content').innerHTML = content;
            } else {
                document.getElementById('ride-details-content').innerHTML = `
                    <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                        <p>${data.message || 'Error fetching ride data'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('ride-details-content').innerHTML = `
                <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                    <p>Failed to fetch ride data. Please try again.</p>
                </div>
            `;
        });
    };

    // Function to open modals
    window.openModal = function(modalId) {
        console.log(`Opening modal: ${modalId}`);
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.classList.remove('animate-slide-down');
                modalContent.classList.add('animate-slide-up');
            }
        } else {
            console.error(`Modal with ID "${modalId}" not found`);
        }
    };

    // Function to close modals
    window.closeModal = function(modalId) {
        console.log(`Closing modal: ${modalId}`);
        const modal = document.getElementById(modalId);
        if (modal) {
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.classList.remove('animate-slide-up');
                modalContent.classList.add('animate-slide-down');
                
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                    modalContent.classList.remove('animate-slide-down');
                    modalContent.classList.add('animate-slide-up');
                }, 300);
            } else {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        } else {
            console.error(`Modal with ID "${modalId}" not found`);
        }
    };

    // Helper function for ride status color classes
    window.getRideStatusClass = function(status) {
        switch (status) {
            case 'completed':
                return 'bg-green-500/20 text-green-400';
            case 'cancelled':
            case 'canceled':
                return 'bg-red-500/20 text-red-400';
            case 'in_progress':
                return 'bg-blue-500/20 text-blue-400';
            case 'searching':
                return 'bg-yellow-500/20 text-yellow-400';
            case 'confirmed':
                return 'bg-indigo-500/20 text-indigo-400';
            case 'arriving':
                return 'bg-purple-500/20 text-purple-400';
            case 'arrived':
                return 'bg-pink-500/20 text-pink-400';
            default:
                return 'bg-gray-500/20 text-gray-400';
        }
    };

    // Format currency for display
    window.formatCurrency = function(amount) {
        return 'G$' + parseFloat(amount).toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    };

    // Add event listeners to modal close buttons
    const closeButtons = document.querySelectorAll('.modal-close-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('[id]');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Add event listeners to modal overlays
    const modalOverlays = document.querySelectorAll('[id$="-modal-overlay"]');
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                const modalId = this.id.replace('-overlay', '');
                closeModal(modalId);
            }
        });
    });

    // Add keyboard event to close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.fixed.inset-0.z-50[style*="flex"]');
            openModals.forEach(modal => {
                closeModal(modal.id);
            });
        }
    });

    console.log("Admin rides page JS initialized");
});

