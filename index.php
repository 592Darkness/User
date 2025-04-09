<?php
// Standard includes for configuration, functions, database
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Include the header - This now contains the Google Maps API script
// AND the standard <script> block defining initMap, initAutocompleteFields, updateFareEstimate, etc.
include_once 'includes/header.php';
?>

    <section class="relative min-h-[600px] flex items-center justify-center text-center px-4 py-10 md:py-16 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/60 to-gray-900 z-0">
            <img
                src="https://images.unsplash.com/photo-1586891962736-7c81e5f9b8a0?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1740&q=80"
                alt="Georgetown Cityscape at Night"
                class="w-full h-full object-cover opacity-20"
                loading="lazy"
                onerror="this.onerror=null; this.src='https://placehold.co/1920x1080/111827/374151?text=Guyana+Cityscape';">
        </div>

        <div class="relative z-10 max-w-4xl mx-auto">
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-white mb-5 leading-tight animate-fade-in">
                Your Reliable Ride in Guyana
            </h1>

            <p class="text-lg md:text-xl text-gray-300 mb-10 animate-fade-in delay-1">
                Book a safe and comfortable taxi anytime, anywhere with Salaam Rides.
            </p>

            <div id="booking-section" class="animate-fade-in delay-2">
                <form id="booking-form" action="process-booking.php" method="post" class="bg-gray-800/80 backdrop-blur-sm p-6 md:p-8 rounded-xl shadow-xl mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="relative">
                            <label for="pickup-address" class="sr-only">Pickup Location</label>
                            <span class="lucide absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-xl z-10" aria-hidden="true">&#xea4b;</span>
                            <input type="text" id="pickup-address" name="pickup" placeholder="Enter Pickup Location" required
                                   class="w-full pl-10 pr-12 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-300"
                                   aria-describedby="pickup-location-desc">
                            <button type="button" id="use-current-location-main" title="Use Current Location"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400 focus:outline-none transition-colors z-20 focus:ring-2 focus:ring-primary-500 rounded-full p-1"
                                    aria-label="Use your current location for pickup">
                                <span class="lucide text-xl">&#xe9cd;</span>
                            </button>
                            <span id="pickup-location-desc" class="sr-only">Enter your pickup location or use the location button to detect automatically</span>
                        </div>
                        <div class="relative">
                            <label for="dropoff-address" class="sr-only">Dropoff Location</label>
                            <span class="lucide absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-xl z-10" aria-hidden="true">&#xea4a;</span>
                            <input type="text" id="dropoff-address" name="dropoff" placeholder="Enter Dropoff Location" required
                                   class="w-full pl-10 pr-3 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-300">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-2 text-left">Vehicle Type</label>
                        <div class="flex flex-wrap gap-3 justify-center">
                            <label class="flex items-center space-x-2 cursor-pointer p-2 bg-gray-700 rounded-lg border border-transparent hover:border-primary-500 transition duration-200" tabindex="0" role="radio" aria-checked="true">
                                <input type="radio" name="vehicleType" value="standard" class="form-radio text-primary-500 focus:ring-primary-500" checked>
                                <span class="text-sm">Standard</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer p-2 bg-gray-700 rounded-lg border border-transparent hover:border-primary-500 transition duration-200" tabindex="0" role="radio" aria-checked="false">
                                <input type="radio" name="vehicleType" value="suv" class="form-radio text-primary-500 focus:ring-primary-500">
                                <span class="text-sm">SUV</span>
                            </label>
                            <label class="flex items-center space-x-2 cursor-pointer p-2 bg-gray-700 rounded-lg border border-transparent hover:border-primary-500 transition duration-200" tabindex="0" role="radio" aria-checked="false">
                                <input type="radio" name="vehicleType" value="premium" class="form-radio text-primary-500 focus:ring-primary-500">
                                <span class="text-sm">Premium</span>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="relative md:col-span-1">
                            <label for="promo-code" class="sr-only">Promo Code</label>
                            <span class="lucide absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-xl" aria-hidden="true">&#xeab2;</span>
                            <input type="text" id="promo-code" name="promo" placeholder="Promo Code"
                                   class="w-full pl-10 pr-3 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-300">
                        </div>
                        <div id="fare-estimate" class="md:col-span-1 flex items-center justify-center text-gray-300 text-lg font-medium">
                            </div>
                        <button type="submit" id="request-ride-btn" class="w-full md:col-span-1 bg-primary-500 hover:bg-primary-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 shadow-md hover:shadow-lg flex items-center justify-center space-x-2 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-700">
                            <span class="lucide text-xl" aria-hidden="true">&#xeb15;</span>
                            <span>Request Ride</span>
                        </button>
                    </div>

                    <div class="text-center mt-4">
                        <a href="#" id="schedule-ride-link" class="text-primary-400 hover:text-primary-300 text-sm transition duration-300 underline focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-sm px-2 py-1">
                            Or Schedule a Ride for Later
                        </a>
                    </div>
                </form>
            </div><div id="ride-status" class="hidden mt-6 bg-gray-800/80 backdrop-blur-sm p-6 rounded-xl shadow-xl text-left animate-slide-up">
                <div class="relative">
                    <h3 class="text-xl font-semibold text-white mb-3 text-center">Finding Your Ride...</h3>

                    <div id="ride-status-loading" class="flex items-center justify-center bg-gray-800/95 rounded-xl py-4 mb-4">
                        <div class="flex flex-col items-center">
                            <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mb-2" role="status"></div>
                            <p class="text-gray-300">Searching for nearby drivers...</p>
                        </div>
                    </div>

                    <p class="text-gray-300 mb-2"><span class="font-medium text-gray-100">Status:</span> <span id="status-message">Searching...</span></p>
                    <p class="text-gray-300 mb-2"><span class="font-medium text-gray-100">Driver:</span> <span id="driver-name">---</span> (<span id="driver-rating">---</span> <span class="lucide text-yellow-400 text-sm" aria-hidden="true">&#xeae5;</span>)</p>
                    <p class="text-gray-300 mb-2"><span class="font-medium text-gray-100">Vehicle:</span> <span id="driver-vehicle">---</span></p>
                    <p class="text-gray-300 mb-4"><span class="font-medium text-gray-100">Est. Arrival:</span> <span id="driver-eta">---</span></p>

                    <div id="driver-card" class="hidden mt-4 p-4 bg-gray-700/60 rounded-lg border border-gray-600">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-12 h-12 rounded-full bg-gray-600 flex items-center justify-center">
                                <span class="lucide text-2xl text-gray-300" aria-hidden="true">&#xebe4;</span>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-200" id="driver-card-name">John D.</p>
                                <div class="flex items-center space-x-1">
                                    <span class="lucide text-yellow-400 text-sm" aria-hidden="true">&#xeae5;</span>
                                    <span class="text-sm text-gray-300" id="driver-card-rating">4.9</span>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <button class="p-2 bg-gray-600 rounded-full text-gray-300 hover:bg-primary-600 hover:text-white transition-colors" aria-label="Call driver" id="call-driver-btn">
                                    <span class="lucide text-lg" aria-hidden="true">&#xea9d;</span>
                                </button>
                                <button class="p-2 bg-gray-600 rounded-full text-gray-300 hover:bg-primary-600 hover:text-white transition-colors" aria-label="Text driver" id="text-driver-btn">
                                    <span class="lucide text-lg" aria-hidden="true">&#xeb28;</span>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm mt-2 border-t border-gray-600 pt-3">
                            <div><p class="text-gray-400">Vehicle:</p><p class="text-white font-medium" id="driver-card-vehicle">Toyota Camry</p></div>
                            <div><p class="text-gray-400">License Plate:</p><p class="text-white font-medium" id="driver-card-plate">ABC 123</p></div>
                            <div><p class="text-gray-400">Phone:</p><p class="text-white font-medium" id="driver-card-phone">+592 123-4567</p></div>
                            <div><p class="text-gray-400">ETA:</p><p class="text-white font-medium" id="driver-card-eta">5 mins</p></div>
                        </div>
                    </div>

                    <div class="mt-4 text-center">
                        <button id="cancel-ride-btn" class="text-red-400 hover:text-red-300 text-sm transition duration-300 underline focus:outline-none focus:ring-2 focus:ring-red-500 rounded-sm px-2 py-1">
                            Cancel Ride
                        </button>
                    </div>

                </div>
            </div></div>
    </section>

    <section class="container mx-auto px-4 -mt-12 md:-mt-24 relative z-20 mb-12 md:mb-20">
         <div id="map-canvas" class="h-64 md:h-96 w-full rounded-xl shadow-xl border-4 border-gray-800 animate-fade-in delay-3">
             <div id="map-fallback" class="hidden h-full w-full flex items-center justify-center bg-gray-700">
                 <p class="text-gray-400">Map loading error. Please check connection or API key.</p>
             </div>
         </div>
    </section>

    <section id="features" class="py-16 md:py-24 bg-gray-800 relative overflow-hidden">
        <div class="absolute inset-0 opacity-5 pointer-events-none">
            <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
        </div>

        <div class="container mx-auto px-4 text-center relative z-10">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Why Choose Salaam Rides?</h2>
            <p class="text-lg text-gray-400 mb-12 max-w-2xl mx-auto">
                Experience the difference with our commitment to safety, reliability, and convenience across Guyana.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-700/50 p-8 rounded-xl shadow-lg hover:shadow-primary-900/50 transform hover:-translate-y-2 transition duration-300 ease-in-out border border-gray-700 hover:border-primary-500 feature-card">
                    <span class="lucide text-4xl text-primary-400 mb-4 inline-block" aria-hidden="true">&#xea9b;</span>
                    <h3 class="text-xl font-semibold text-white mb-2">Safety First</h3>
                    <p class="text-gray-300">Verified drivers, real-time ride tracking, and in-app emergency features ensure your peace of mind.</p>
                </div>
                <div class="bg-gray-700/50 p-8 rounded-xl shadow-lg hover:shadow-primary-900/50 transform hover:-translate-y-2 transition duration-300 ease-in-out border border-gray-700 hover:border-primary-500 feature-card">
                    <span class="lucide text-4xl text-primary-400 mb-4 inline-block" aria-hidden="true">&#xea58;</span>
                    <h3 class="text-xl font-semibold text-white mb-2">Simple Booking</h3>
                    <p class="text-gray-300">Book your ride in seconds through our user-friendly website or upcoming mobile app.</p>
                </div>
                <div class="bg-gray-700/50 p-8 rounded-xl shadow-lg hover:shadow-primary-900/50 transform hover:-translate-y-2 transition duration-300 ease-in-out border border-gray-700 hover:border-primary-500 feature-card">
                    <span class="lucide text-4xl text-primary-400 mb-4 inline-block" aria-hidden="true">&#xe953;</span>
                    <h3 class="text-xl font-semibold text-white mb-2">Schedule Ahead</h3>
                    <p class="text-gray-300">Plan your trips in advance. Schedule rides for airport transfers, appointments, or any occasion.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="rewards" class="py-16 md:py-24 bg-gray-900 relative overflow-hidden">
        <div class="absolute inset-0 opacity-5 pointer-events-none">
            <img src="<?php echo asset('img/islamic-pattern.svg'); ?>" alt="" class="w-full h-full object-cover" aria-hidden="true">
        </div>

        <div class="container mx-auto px-4 text-center relative z-10">
            <span class="lucide text-5xl text-yellow-400 mb-4 inline-block animate-pulse-slow" aria-hidden="true">&#xeae5;</span>
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Salaam Rewards</h2>
            <p class="text-lg text-gray-400 mb-10 max-w-2xl mx-auto">
                We appreciate our loyal customers! Earn points for every ride and redeem them for discounts and exclusive perks.
            </p>
            <div class="bg-gradient-to-br from-primary-700 to-primary-900 p-8 rounded-xl shadow-xl max-w-lg mx-auto border border-primary-600">
                <h3 class="text-2xl font-semibold text-white mb-4">How it Works</h3>
                <ul class="text-left space-y-3 text-primary-100">
                    <li class="flex items-start space-x-3">
                        <span class="lucide text-xl text-yellow-300 mt-1 flex-shrink-0" aria-hidden="true">&#xe96c;</span>
                        <span>Sign up or log in to your Salaam Rides account.</span>
                    </li>
                    <li class="flex items-start space-x-3">
                        <span class="lucide text-xl text-yellow-300 mt-1 flex-shrink-0" aria-hidden="true">&#xe96c;</span>
                        <span>Earn points automatically for every completed ride.</span>
                    </li>
                    <li class="flex items-start space-x-3">
                        <span class="lucide text-xl text-yellow-300 mt-1 flex-shrink-0" aria-hidden="true">&#xe96c;</span>
                        <span>Track your points balance in your profile.</span>
                    </li>
                    <li class="flex items-start space-x-3">
                        <span class="lucide text-xl text-yellow-300 mt-1 flex-shrink-0" aria-hidden="true">&#xe96c;</span>
                        <span>Redeem points for ride discounts or special offers.</span>
                    </li>
                </ul>
                <button id="join-rewards-btn" class="mt-8 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold py-3 px-8 rounded-lg transition duration-300 shadow-md hover:shadow-lg transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-yellow-600 focus:ring-offset-2 focus:ring-offset-primary-800">
                    Join Rewards Now!
                </button>
            </div>
        </div>
    </section>

<?php
// Include modals and the standard footer
// Footer will load the standard script.js (no type="module" needed there anymore for this approach)
include_once 'includes/modals.php';
include_once 'includes/footer.php';
?>