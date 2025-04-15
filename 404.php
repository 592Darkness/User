<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

http_response_code(404);

include_once 'includes/header.php';
?>

<section class="flex items-center justify-center min-h-[70vh] px-4 py-16">
    <div class="max-w-md text-center">
        <div class="mb-8">
            <span class="lucide text-primary-400 text-8xl mb-4 inline-block" aria-hidden="true">&#xea0e;</span>
            <h1 class="text-4xl font-bold text-white mb-4">Page Not Found</h1>
            <p class="text-lg text-gray-400">The page you're looking for doesn't exist or has been moved.</p>
        </div>
        
        <div class="space-y-4">
            <a href="index.php" class="inline-block bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-6 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                <span class="lucide mr-2" aria-hidden="true">&#xeb15;</span>
                Back to Home
            </a>
            
            <p class="text-gray-500 text-sm mt-8">
                If you think this is an error, please <a href="#" class="text-primary-400 hover:text-primary-300">contact our support team</a>.
            </p>
        </div>
    </div>
</section>

<?php
include_once 'includes/footer.php';
?>