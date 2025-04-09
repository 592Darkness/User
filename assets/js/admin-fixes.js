document.addEventListener('DOMContentLoaded', function() {
    console.log("Admin fixes loaded");
    
    // Fix for Add Driver button
    const addDriverBtn = document.querySelector('a[onclick="openAddDriverModal()"]');
    if (addDriverBtn) {
        console.log("Add driver button found");
        // Remove the onclick attribute and add direct event listener
        addDriverBtn.removeAttribute('onclick');
        addDriverBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log("Opening add driver modal");
            const modal = document.getElementById('add-driver-modal');
            if (modal) {
                document.getElementById('add-driver-form').reset();
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        });
    }
    
    // Fix for Administrator dropdown
    const adminDropdownBtn = document.getElementById('admin-dropdown-btn');
    const adminDropdownMenu = document.getElementById('admin-dropdown-menu');
    
    if (adminDropdownBtn && adminDropdownMenu) {
        console.log("Admin dropdown found");
        adminDropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log("Toggling admin dropdown");
            
            // Toggle dropdown visibility
            if (adminDropdownMenu.classList.contains('hidden')) {
                adminDropdownMenu.classList.remove('hidden');
                // Close when clicking outside
                setTimeout(() => {
                    document.addEventListener('click', closeAdminDropdown);
                }, 10);
            } else {
                adminDropdownMenu.classList.add('hidden');
                document.removeEventListener('click', closeAdminDropdown);
            }
        });
        
        // Also fix group hover functionality that might not be working
        adminDropdownBtn.parentElement.classList.remove('group');
    }
    
    // Function to close admin dropdown when clicking outside
    function closeAdminDropdown(e) {
        if (!adminDropdownBtn.contains(e.target) && !adminDropdownMenu.contains(e.target)) {
            adminDropdownMenu.classList.add('hidden');
            document.removeEventListener('click', closeAdminDropdown);
        }
    }
    
    // Fix all other button click handlers
    document.querySelectorAll('[onclick]').forEach(el => {
        const onclickValue = el.getAttribute('onclick');
        if (onclickValue && (onclickValue.includes('Modal') || onclickValue.includes('driver'))) {
            console.log("Fixing onclick for:", onclickValue);
            const funcName = onclickValue.split('(')[0];
            const params = onclickValue.match(/\((.*)\)/);
            
            el.removeAttribute('onclick');
            el.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Executing:", onclickValue);
                
                // Handle common functions
                if (funcName === 'openAddDriverModal') {
                    openAddDriverModal();
                } else if (funcName === 'openEditDriverModal' && params) {
                    openEditDriverModal(params[1]);
                } else if (funcName === 'openViewDriverModal' && params) {
                    openViewDriverModal(params[1]);
                } else if (funcName === 'confirmDeleteDriver' && params) {
                    const paramValues = params[1].split(',');
                    confirmDeleteDriver(paramValues[0], paramValues[1].replace(/'/g, ''));
                } else if (funcName === 'closeModal' && params) {
                    closeModal(params[1].replace(/'/g, ''));
                }
            });
        }
    });
    
    // Define modal functions globally if they don't exist
    if (typeof openAddDriverModal !== 'function') {
        window.openAddDriverModal = function() {
            const modal = document.getElementById('add-driver-modal');
            if (modal) {
                document.getElementById('add-driver-form').reset();
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        };
    }
    
    if (typeof closeModal !== 'function') {
        window.closeModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        };
    }
});