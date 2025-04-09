document.addEventListener('DOMContentLoaded', function() {
    console.log("Admin modal fix loaded");
    
    // Fix for Add Driver button
    document.querySelectorAll('a[onclick*="openAddDriverModal"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log("Add driver button clicked");
            openDriverModal();
        });
    });
    
    // Fix for "Add your first driver" text links
    document.querySelectorAll('a').forEach(link => {
        if (link.textContent.includes('Add your first driver')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Add first driver link clicked");
                openDriverModal();
            });
        }
    });
    
    // Function to actually open the modal
    function openDriverModal() {
        // Force display on the modal
        const modal = document.getElementById('add-driver-modal');
        if (modal) {
            console.log("Found add driver modal");
            // Reset form
            const form = document.getElementById('add-driver-form');
            if (form) form.reset();
            
            // Force display CSS
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            document.body.style.overflow = 'hidden';
            
            // Make sure overlay is visible
            const overlay = document.getElementById('add-driver-modal-overlay');
            if (overlay) overlay.style.display = 'block';
            
            // Ensure the modal content is visible
            const content = modal.querySelector('.modal-content');
            if (content) {
                content.style.display = 'block';
                content.style.opacity = '1';
            }
        } else {
            console.error("Add driver modal not found");
        }
    }
    
    // Fix modal close buttons
    document.querySelectorAll('.modal-close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('[id$="-modal"]');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    });
    
    // Fix for modal overlays
    document.querySelectorAll('[id$="-modal-overlay"]').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                const modalId = this.id.replace('-overlay', '');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }
        });
    });
});

// Add a more direct way to open the modal
window.forceOpenAddDriverModal = function() {
    const modal = document.getElementById('add-driver-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        document.body.style.overflow = 'hidden';
    } else {
        alert("Modal not found!");
    }
};