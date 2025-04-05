<footer class="bg-gray-800 border-t border-gray-700/50 py-8 mt-auto">
    <div class="container mx-auto px-4 md:flex md:justify-between md:items-center">
        <div class="text-center md:text-left mb-6 md:mb-0">
            <p class="text-gray-400">&copy; <?php echo date('Y'); ?> Salaam Rides. All Rights Reserved.</p>
            <p class="text-sm mt-2 text-gray-500">Serving Georgetown, Linden, Berbice, and all across Guyana.</p>
        </div>
        <div class="flex flex-wrap justify-center md:justify-end space-x-4">
            <a href="#" class="hover:text-primary-400 transition duration-300 text-gray-400">Driver Terms</a>
            <span class="text-gray-600">|</span>
            <a href="#" class="hover:text-primary-400 transition duration-300 text-gray-400">Help Center</a>
            <span class="text-gray-600">|</span>
            <a href="#" class="hover:text-primary-400 transition duration-300 text-gray-400">Contact Us</a>
        </div>
    </div>
    <div class="mt-6 text-center text-xs text-gray-600">
        <p>For support, please call our 24/7 driver hotline: +592-123-4567</p>
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

<!-- Load the real driver dashboard JavaScript with data from the database -->
<script src="<?php echo asset('js/driver-dashboard.js'); ?>"></script>

</body>
</html>