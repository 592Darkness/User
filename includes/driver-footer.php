<footer class="bg-gray-800 border-t border-gray-700/50 py-8 mt-auto">
    <div class="container mx-auto px-4 md:flex md:justify-between md:items-center">
        <div class="text-center md:text-left mb-6 md:mb-0">
            <p class="text-gray-400">&copy; <?php echo date('Y'); ?> Salaam Rides. All Rights Reserved.</p>
            <p class="text-sm mt-2 text-gray-500">Serving Georgetown, Linden, Berbice, and all across Guyana.</p>
        </div>
        <div class="flex flex-wrap justify-center md:justify-end space-x-4">
            <a href="driver-terms.php" class="hover:text-primary-400 transition duration-300 text-gray-400">Driver Terms</a>
            <span class="text-gray-600">|</span>
            <a href="help-center.php" class="hover:text-primary-400 transition duration-300 text-gray-400">Help Center</a>
            <span class="text-gray-600">|</span>
            <a href="contact.php" class="hover:text-primary-400 transition duration-300 text-gray-400">Contact Us</a>
        </div>
    </div>
    <div class="mt-6 text-center text-xs text-gray-600">
        <?php
        // Fetch support phone from database with proper error handling
        $supportPhone = '';
        try {
            $conn = dbConnect();
            $query = "SELECT value FROM site_settings WHERE setting_key = 'support_phone' LIMIT 1";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $supportPhone = $row['value'];
            }
            
            // If no support phone is found in the database, try to get it from a config constant
            if (empty($supportPhone) && defined('SUPPORT_PHONE')) {
                $supportPhone = SUPPORT_PHONE;
            }
            
            // Make sure to close the database connection
            $conn->close();
        } catch (Exception $e) {
            error_log("Error fetching support phone: " . $e->getMessage());
            // If there's an error and we have a config constant, use that
            if (defined('SUPPORT_PHONE')) {
                $supportPhone = SUPPORT_PHONE;
            }
        }
        
        // Only display the support phone info if we actually have a support phone
        if (!empty($supportPhone)):
        ?>
        <p>For support, please call our 24/7 driver hotline: <?php echo htmlspecialchars($supportPhone); ?></p>
        <?php endif; ?>
    </div>
</footer>

<div id="confirmation-message" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-green-600 text-white text-sm font-medium py-3 px-6 rounded-lg shadow-lg z-50 flex items-center space-x-2 opacity-0 transition-all duration-300">
    <span class="lucide hidden" id="confirmation-icon" aria-hidden="true">&#xe96c;</span>
    <span id="confirmation-text">
        <?php 
        $flashMessage = getFlashMessage();
        if ($flashMessage) {
            echo htmlspecialchars($flashMessage['message']);
            $messageType = $flashMessage['type'] == 'error' ? 'true' : 'false';
            echo '<script>document.addEventListener("DOMContentLoaded", function() { showConfirmation("' . htmlspecialchars($flashMessage['message']) . '", ' . $messageType . '); });</script>';
        }
        ?>
    </span>
    <button id="close-notification" class="ml-2 text-white hover:text-white/80" aria-label="Close notification">
        <span class="lucide text-sm" aria-hidden="true">&#xea76;</span>
    </button>
</div>

<div id="loading-overlay" class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="flex flex-col items-center">
        <div class="spinner-border animate-spin inline-block w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full mb-4"></div>
        <p class="text-xl text-white">Loading...</p>
    </div>
</div>

<!-- Driver Dashboard JS Patches -->
<script>
// Patch key functions that might cause errors in driver-dashboard.js
window.updateOverviewStats = window.updateOverviewStats || function(data) {
    console.log('Patched updateOverviewStats called with data:', data);
    // Today's stats - using safeUpdateElement to prevent errors
    safeUpdateElement('today-rides', data.total_rides || '0');
    safeUpdateElement('today-earnings', data.formatted_earnings || 'G$0');
    safeUpdateElement('today-hours', data.total_hours || '0');
};

window.updateEarningsBreakdown = window.updateEarningsBreakdown || function(breakdownData, period) {
    console.log('Patched updateEarningsBreakdown called');
    const chartContainer = document.getElementById('earnings-breakdown-chart');
    if (!chartContainer) {
        console.log('Chart container not found');
        return;
    }
    
    // Safe implementation
    chartContainer.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-400">No chart data available</p></div>';
};

window.updatePaymentsTable = window.updatePaymentsTable || function(payments) {
    console.log('Patched updatePaymentsTable called');
    const tableBody = document.getElementById('payments-table-body');
    if (!tableBody) return;
    
    if (!payments || payments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-gray-400">No payment history available.</td></tr>';
        return;
    }
    
    tableBody.innerHTML = '';
    payments.forEach(payment => {
        const row = document.createElement('tr');
        row.className = 'border-b border-gray-700';
        row.innerHTML = `
            <td class="py-3 px-4">${payment.date || 'N/A'}</td>
            <td class="py-3 px-4">${payment.description || 'Payment'}</td>
            <td class="py-3 px-4">
                <span class="bg-${payment.status === 'completed' ? 'green' : payment.status === 'pending' ? 'yellow' : 'gray'}-500/20 
                             text-${payment.status === 'completed' ? 'green' : payment.status === 'pending' ? 'yellow' : 'gray'}-400 
                             text-xs px-2.5 py-0.5 rounded-full">
                    ${payment.status || 'Pending'}
                </span>
            </td>
            <td class="py-3 px-4 text-right">${payment.formatted_amount || 'G$0'}</td>
        `;
        tableBody.appendChild(row);
    });
};

window.updateEarningsSummary = window.updateEarningsSummary || function(summary) {
    console.log('Patched updateEarningsSummary called');
    safeUpdateElement('total-earnings', summary.formatted_earnings || 'G$0');
    safeUpdateElement('total-rides', summary.total_rides || '0');
    safeUpdateElement('avg-fare', summary.formatted_avg_fare || 'G$0');
    safeUpdateElement('total-hours', summary.total_hours || '0');
    safeUpdateElement('avg-hourly', summary.formatted_hourly_rate || 'G$0');
};

// Helper function to show confirmation messages
window.showConfirmation = window.showConfirmation || function(message, isError = false) {
    console.log(`Confirmation: ${message} (${isError ? 'error' : 'success'})`);
    const confirmationMessage = document.getElementById('confirmation-message');
    const confirmationText = document.getElementById('confirmation-text');
    
    if (!confirmationMessage || !confirmationText) return;

    confirmationText.textContent = message;
    
    // Make sure the confirmation icon is visible
    const confirmationIcon = document.getElementById('confirmation-icon');
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
};

// Helper function to safely update DOM elements
window.safeUpdateElement = function(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
        return true;
    }
    return false;
};

// Init handlers for modals
document.addEventListener('DOMContentLoaded', function() {
    const paymentConfirmationModal = document.getElementById('paymentConfirmationModal');
    if (paymentConfirmationModal) {
        const confirmButton = document.getElementById('confirmPaymentBtn');
        const disputeButton = document.getElementById('disputePaymentBtn');
        
        if (confirmButton) {
            confirmButton.addEventListener('click', function() {
                const rideId = document.getElementById('confirm-modal-ride-id').value;
                if (!rideId) {
                    console.error('No ride ID found in modal');
                    return;
                }
                
                // Show loading spinner
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) loadingOverlay.classList.remove('hidden');
                
                // Make actual API call to confirm payment
                fetch('api/confirm-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ride_id: rideId,
                        action: 'confirm',
                        user_type: 'driver'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (loadingOverlay) loadingOverlay.classList.add('hidden');
                    
                    // Hide the modal
                    if (paymentConfirmationModal) {
                        paymentConfirmationModal.style.display = 'none';
                    }
                    
                    if (data.success) {
                        showConfirmation('Payment confirmed successfully!');
                    } else {
                        showConfirmation(data.message || 'Failed to confirm payment', true);
                    }
                })
                .catch(error => {
                    console.error('Error confirming payment:', error);
                    if (loadingOverlay) loadingOverlay.classList.add('hidden');
                    if (paymentConfirmationModal) {
                        paymentConfirmationModal.style.display = 'none';
                    }
                    showConfirmation('Network error while confirming payment', true);
                });
            });
        }
        
        if (disputeButton) {
            disputeButton.addEventListener('click', function() {
                const rideId = document.getElementById('confirm-modal-ride-id').value;
                if (!rideId) {
                    console.error('No ride ID found in modal');
                    return;
                }
                
                // Show loading spinner
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) loadingOverlay.classList.remove('hidden');
                
                // Make actual API call to dispute payment
                fetch('api/confirm-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ride_id: rideId,
                        action: 'dispute',
                        user_type: 'driver'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (loadingOverlay) loadingOverlay.classList.add('hidden');
                    if (paymentConfirmationModal) {
                        paymentConfirmationModal.style.display = 'none';
                    }
                    
                    if (data.success) {
                        showConfirmation('Payment dispute submitted successfully');
                    } else {
                        showConfirmation(data.message || 'Failed to submit payment dispute', true);
                    }
                })
                .catch(error => {
                    console.error('Error disputing payment:', error);
                    if (loadingOverlay) loadingOverlay.classList.add('hidden');
                    if (paymentConfirmationModal) {
                        paymentConfirmationModal.style.display = 'none';
                    }
                    showConfirmation('Network error while disputing payment', true);
                });
            });
        }
    }
    
    // Close notification button
    const closeNotificationBtn = document.getElementById('close-notification');
    if (closeNotificationBtn) {
        closeNotificationBtn.addEventListener('click', function() {
            const confirmationMessage = document.getElementById('confirmation-message');
            if (confirmationMessage) {
                confirmationMessage.classList.remove('opacity-100', 'translate-y-0');
                confirmationMessage.classList.add('opacity-0', 'translate-y-6');
            }
        });
    }
});
</script>
<script>
// Improved fix that maintains original functionality while preventing errors
(function() {
    console.log("Applying improved fixes to dashboard functions");
    
    // Create a safe fetch wrapper to ensure API calls work correctly
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        console.log(`Fetch call to: ${url}`);
        
        // Log the request for debugging
        return originalFetch(url, options)
            .then(response => {
                console.log(`Fetch response from ${url}: Status ${response.status}`);
                return response;
            })
            .catch(error => {
                console.error(`Fetch error for ${url}:`, error);
                throw error;
            });
    };

    // Safely wrap updateEarningsSummary to maintain functionality
    const originalUpdateEarningsSummary = window.updateEarningsSummary;
    window.updateEarningsSummary = function(summary) {
        console.log("Earnings summary data:", summary);
        
        if (!summary) {
            console.warn("updateEarningsSummary called with invalid data");
            return;
        }
        
        try {
            // Call the original first, in a try-catch block
            if (typeof originalUpdateEarningsSummary === 'function') {
                try {
                    originalUpdateEarningsSummary(summary);
                    return; // If it worked, we're done
                } catch (e) {
                    console.warn("Error in original updateEarningsSummary, using fallback", e);
                }
            }
            
            // Fallback code only runs if the original failed
            const elements = [
                {id: 'total-earnings', value: summary.formatted_earnings || 'G$0'},
                {id: 'total-rides', value: summary.total_rides || '0'},
                {id: 'avg-fare', value: summary.formatted_avg_fare || 'G$0'},
                {id: 'total-hours', value: summary.total_hours || '0'},
                {id: 'avg-hourly', value: summary.formatted_hourly_rate || 'G$0'}
            ];
            
            elements.forEach(item => {
                const element = document.getElementById(item.id);
                if (element) element.textContent = item.value;
            });
        } catch (e) {
            console.error("Error in updateEarningsSummary fallback", e);
        }
    };
    
    // Create missing elements if needed, but don't hide them
    function ensureElementsExist() {
        const elements = [
            'total-earnings', 'total-rides', 'avg-fare', 'avg-hourly', 'total-hours',
            'today-rides', 'today-earnings', 'today-hours',
            'weekly-rides', 'weekly-earnings', 'weekly-hours', 'weekly-rating',
            'earnings-period-text', 'earnings-breakdown-chart'
        ];
        
        elements.forEach(id => {
            if (!document.getElementById(id)) {
                console.log('Creating missing element with ID:', id);
                const div = document.createElement('div');
                div.id = id;
                
                // For chart element, make it visible with a minimum height
                if (id === 'earnings-breakdown-chart') {
                    div.style.minHeight = '300px';
                    div.className = 'w-full bg-gray-800';
                    div.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-400">Loading chart data...</p></div>';
                }
                
                // Add to the appropriate container if possible
                const earningSummarySection = document.querySelector('.tab-content');
                if (earningSummarySection) {
                    earningSummarySection.appendChild(div);
                } else {
                    // Fallback to body if no suitable container found
                    document.body.appendChild(div);
                }
            }
        });
    }
    
    // Fix the earnings data loading to ensure API calls work
    const originalLoadEarningsData = window.loadEarningsData;
    window.loadEarningsData = function() {
        console.log("Enhanced loadEarningsData called");
        
        // Make sure all required elements exist
        ensureElementsExist();
        
        try {
            // Set period text if it exists
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
            
            // Call the original function for proper API calls
            if (typeof originalLoadEarningsData === 'function') {
                try {
                    originalLoadEarningsData();
                    return; // If it worked, we're done
                } catch (e) {
                    console.warn("Error in original loadEarningsData, using fallback", e);
                }
            }
            
            // Fallback only if the original failed
            // Make the API call directly
            fetch(`/api/driver-earnings.php?period=${period}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("Successfully fetched earnings data:", data);
                    updateEarningsSummary(data.data.summary);
                    
                    if (typeof window.updateEarningsBreakdown === 'function') {
                        try {
                            window.updateEarningsBreakdown(data.data.breakdown, period);
                        } catch (e) {
                            console.warn("Error in updateEarningsBreakdown", e);
                        }
                    }
                    
                    if (typeof window.updatePaymentsTable === 'function') {
                        try {
                            window.updatePaymentsTable(data.data.payments);
                        } catch (e) {
                            console.warn("Error in updatePaymentsTable", e);
                        }
                    }
                } else {
                    console.warn("API returned error:", data.message);
                }
            })
            .catch(error => {
                console.error("Earnings API call error:", error);
            });
            
        } catch (e) {
            console.error("Error in loadEarningsData fallback", e);
        }
    };
    
    // Fix the loadOverviewData function which loads data for the main dashboard
    const originalLoadOverviewData = window.loadOverviewData;
    window.loadOverviewData = function() {
        console.log("Enhanced loadOverviewData called");
        
        try {
            // Call original for proper API calls
            if (typeof originalLoadOverviewData === 'function') {
                try {
                    originalLoadOverviewData();
                    return; // If it worked, we're done
                } catch (e) {
                    console.warn("Error in original loadOverviewData, using fallback", e);
                }
            }
            
            // Fallback direct API call
            fetch('/api/driver-earnings.php?period=day', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("Successfully fetched overview data:", data);
                    
                    // Update stats if updateOverviewStats exists
                    if (typeof window.updateOverviewStats === 'function') {
                        try {
                            window.updateOverviewStats(data.data.summary);
                        } catch (e) {
                            console.warn("Error in updateOverviewStats", e);
                            
                            // Direct update as fallback
                            const todayRides = document.getElementById('today-rides');
                            const todayEarnings = document.getElementById('today-earnings');
                            const todayHours = document.getElementById('today-hours');
                            
                            if (todayRides) todayRides.textContent = data.data.summary.total_rides || '0';
                            if (todayEarnings) todayEarnings.textContent = data.data.summary.formatted_earnings || 'G$0';
                            if (todayHours) todayHours.textContent = data.data.summary.total_hours || '0';
                        }
                    }
                    
                    // Also fetch weekly data
                    fetch('/api/driver-earnings.php?period=week', {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(weeklyData => {
                        if (weeklyData.success) {
                            console.log("Successfully fetched weekly data:", weeklyData);
                            
                            const weeklyRides = document.getElementById('weekly-rides');
                            const weeklyEarnings = document.getElementById('weekly-earnings');
                            const weeklyHours = document.getElementById('weekly-hours');
                            
                            if (weeklyRides) weeklyRides.textContent = weeklyData.data.summary.total_rides || '0';
                            if (weeklyEarnings) weeklyEarnings.textContent = weeklyData.data.summary.formatted_earnings || 'G$0';
                            if (weeklyHours) weeklyHours.textContent = weeklyData.data.summary.total_hours || '0';
                            
                            // Fetch rating data
                            fetch('/api/driver-rating.php', {
                                method: 'GET'
                            })
                            .then(response => response.json())
                            .then(ratingData => {
                                if (ratingData.success) {
                                    const weeklyRating = document.getElementById('weekly-rating');
                                    if (weeklyRating) {
                                        weeklyRating.textContent = ratingData.data.rating.toFixed(1);
                                    }
                                }
                            })
                            .catch(error => {
                                console.error("Error fetching rating data:", error);
                            });
                        }
                    })
                    .catch(error => {
                        console.error("Error fetching weekly data:", error);
                    });
                    
                } else {
                    console.warn("API returned error:", data.message);
                }
            })
            .catch(error => {
                console.error("Overview API call error:", error);
            });
            
        } catch (e) {
            console.error("Error in loadOverviewData fallback", e);
        }
    };
    
    // Only apply fixes when the page is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureElementsExist);
    } else {
        ensureElementsExist();
    }
    
    console.log("All dashboard data loading fixes applied");
})();

// Add listener for pending payment confirmations
document.addEventListener('DOMContentLoaded', function() {
    // Start checking for payment confirmations periodically
    function checkForPendingPayments() {
        fetch('api/driver-pending-confirmations.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.rides && data.rides.length > 0) {
                    const paymentConfirmationModal = document.getElementById('paymentConfirmationModal');
                    if (!paymentConfirmationModal) return;
                    
                    // Get current modal ride ID
                    const currentModalRideId = document.getElementById('confirm-modal-ride-id').value;
                    
                    // Only show modal if it's a new ride (not already in the modal)
                    if (!currentModalRideId || currentModalRideId != data.rides[0].ride_id) {
                        const rideToConfirm = data.rides[0];
                        
                        // Update modal content
                        document.getElementById('confirm-ride-id').textContent = rideToConfirm.ride_id;
                        document.getElementById('confirm-customer-name').textContent = rideToConfirm.customer_name || 'Customer';
                        document.getElementById('confirm-ride-amount').textContent = rideToConfirm.formatted_fare || 'G$0';
                        document.getElementById('confirm-modal-ride-id').value = rideToConfirm.ride_id;
                        
                        // Show the modal
                        paymentConfirmationModal.style.display = 'flex';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for payment confirmations:', error);
            });
    }
    
    // Check immediately and then every 30 seconds
    checkForPendingPayments();
    setInterval(checkForPendingPayments, 30000);
});
</script>

<!-- Load the real driver dashboard JavaScript with data from the database -->
<script src="<?php echo asset('js/driver-dashboard.js'); ?>"></script>

</body>
</html>