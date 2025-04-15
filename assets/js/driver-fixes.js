
document.addEventListener('DOMContentLoaded', function() {
    // Fix for Bootstrap modal references
    if (typeof bootstrap === 'undefined') {
        // Create a simple modal implementation if Bootstrap isn't available
        window.bootstrap = {
            Modal: function(element) {
                this.element = element;
                this.show = function() {
                    if (this.element) {
                        this.element.style.display = 'flex';
                        this.element.classList.add('show');
                    }
                };
                this.hide = function() {
                    if (this.element) {
                        this.element.style.display = 'none';
                        this.element.classList.remove('show');
                    }
                };
            }
        };
        
        // Initialize any modals that might be used
        const paymentConfirmationModal = document.getElementById('paymentConfirmationModal');
        if (paymentConfirmationModal) {
            // Add show/hide functionality
            const modalCloseButtons = paymentConfirmationModal.querySelectorAll('button[data-dismiss="modal"]');
            modalCloseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    paymentConfirmationModal.style.display = 'none';
                });
            });
        }
    }
    
    // Fix for updating stats elements that might not exist yet
    const safeUpdateElement = function(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value;
        }
    };
    
    // Override or patch the updateOverviewStats function
    // This assumes the original function exists in driver-dashboard.js
    const originalUpdateOverviewStats = window.updateOverviewStats;
    window.updateOverviewStats = function(data) {
        try {
            // Today's stats
            safeUpdateElement('today-rides', data.total_rides || '0');
            safeUpdateElement('today-earnings', data.formatted_earnings || 'G$0');
            safeUpdateElement('today-hours', data.total_hours || '0');
            
            // Call original function if it exists
            if (typeof originalUpdateOverviewStats === 'function') {
                originalUpdateOverviewStats(data);
            }
        } catch (error) {
            console.log('Error in safe updateOverviewStats: ', error);
        }
    };
    
    // Add other safe element updaters as needed
    const safeUpdatePaymentsTable = function(payments) {
        const tableBody = document.getElementById('payments-table-body');
        if (!tableBody) return;
        
    };
});