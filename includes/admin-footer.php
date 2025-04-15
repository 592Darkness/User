</main>

    <footer class="bg-gray-800 border-t border-gray-700/50 py-4">
        <div class="container mx-auto px-4 text-center text-gray-400 text-sm">
            <p>&copy; <?php echo date('Y'); ?> Salaam Rides. All Rights Reserved. | Admin Panel</p>
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

    <script>
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            mobileMenu.classList.toggle('hidden');
            mobileMenu.classList.toggle('animate-slide-in');
        });
    }
    
    // Notification functions
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
    
    // Close notification button
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
    
    // Setup form submission with loading indicator
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                // Don't show loading for search forms
                if (!this.classList.contains('search-form')) {
                    showLoadingIndicator();
                }
            });
        });
    });
    </script>
    <script src="<?php echo asset('js/admin-fixes.js'); ?>"></script>
    <script src="<?php echo asset('js/admin-modal-fix.js'); ?>"></script>
    <script src="<?php echo asset('js/admin-drivers-debug.js'); ?>"></script>
</body>
</html>
