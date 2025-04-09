<?php ?>

<div id="account-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="account-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-md w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6 text-center">Welcome to Salaam Rides</h2>
        <div class="flex border-b border-gray-600 mb-6">
            <button type="button" id="login-tab-btn" class="flex-1 py-2 text-center font-medium text-primary-400 border-b-2 border-primary-400 transition-colors" aria-selected="true" role="tab">Login</button>
            <button type="button" id="signup-tab-btn" class="flex-1 py-2 text-center font-medium text-gray-400 hover:text-primary-300 transition-colors" aria-selected="false" role="tab">Sign Up</button>
        </div>
        <form id="login-form" action="process-login.php" method="post" class="space-y-4" role="tabpanel" aria-labelledby="login-tab-btn">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div>
                <label for="login-email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                <input type="email" id="login-email" name="email" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            <div>
                <label for="login-password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="login-password" name="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <button type="button" id="toggle-password" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400" aria-label="Toggle password visibility">
                        <span class="lucide text-lg toggle-password-icon" aria-hidden="true">&#xea30;</span>
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" class="form-checkbox text-primary-500 focus:ring-primary-500 h-4 w-4 rounded">
                    <span class="ml-2 text-sm text-gray-400">Remember me</span>
                </label>
                <a href="forgot-password.php" class="text-sm text-primary-400 hover:text-primary-300 hover:underline">Forgot password?</a>
            </div>
            <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">Login</button>
        </form>
        <form id="signup-form" action="process-signup.php" method="post" class="space-y-4 hidden" role="tabpanel" aria-labelledby="signup-tab-btn">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div>
                <label for="signup-name" class="block text-sm font-medium text-gray-300 mb-1">Full Name</label>
                <input type="text" id="signup-name" name="name" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            <div>
                <label for="signup-email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                <input type="email" id="signup-email" name="email" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            <div>
                <label for="signup-password" class="block text-sm font-medium text-gray-300 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="signup-password" name="password" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <button type="button" id="toggle-signup-password" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-400" aria-label="Toggle password visibility">
                        <span class="lucide text-lg toggle-password-icon" aria-hidden="true">&#xea30;</span>
                    </button>
                </div>
                <div class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</div>
            </div>
            <div>
                <label for="signup-phone" class="block text-sm font-medium text-gray-300 mb-1">Phone Number</label>
                <input type="tel" id="signup-phone" name="phone" required placeholder="+592" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">Create Account</button>
            <p class="text-xs text-gray-400 text-center mt-2">By signing up, you agree to our <a href="#" class="hover:underline hover:text-primary-300">Terms of Service</a> and <a href="#" class="hover:underline hover:text-primary-300">Privacy Policy</a>.</p>
        </form>
    </div>
</div>

<div id="schedule-modal" class="fixed inset-0 z-50 items-center justify-center hidden">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" id="schedule-modal-overlay"></div>
    <div class="modal-content bg-gray-800 shadow-2xl border border-gray-700 max-w-lg w-full mx-auto relative z-10">
        <button type="button" class="modal-close-btn text-gray-500 hover:text-primary-400 absolute right-4 top-4 focus:outline-none focus:text-primary-500" aria-label="Close modal">
            <span class="lucide" aria-hidden="true">&#xea76;</span>
        </button>
        <h2 class="text-2xl font-semibold text-white mb-6 text-center">Schedule Your Ride</h2>
        <form id="schedule-form" action="process-schedule.php" method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="relative">
                <label for="schedule-pickup-address" class="block text-sm font-medium text-gray-300 mb-1">Pickup Location</label>
                <span class="lucide absolute left-3 top-[38px] transform -translate-y-1/2 text-gray-400 text-lg z-10" aria-hidden="true">&#xea4b;</span>
                <input type="text" id="schedule-pickup-address" name="pickup" required placeholder="Enter Pickup Location" class="w-full pl-10 pr-12 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <button type="button" id="use-current-location-schedule" title="Use Current Location" class="absolute right-3 top-[38px] transform -translate-y-1/2 text-gray-400 hover:text-primary-400 focus:outline-none transition-colors z-20 focus:ring-2 focus:ring-primary-500 rounded-full p-1" aria-label="Use your current location for pickup">
                    <span class="lucide text-lg" aria-hidden="true">&#xe9cd;</span>
                </button>
            </div>
            <div class="relative">
                <label for="schedule-dropoff-address" class="block text-sm font-medium text-gray-300 mb-1">Dropoff Location</label>
                <span class="lucide absolute left-3 top-[38px] transform -translate-y-1/2 text-gray-400 text-lg z-10" aria-hidden="true">&#xea4a;</span>
                <input type="text" id="schedule-dropoff-address" name="dropoff" required placeholder="Enter Dropoff Location" class="w-full pl-10 pr-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-300 mb-2">Vehicle Type</label>
                <div class="flex flex-wrap gap-3">
                    <label class="flex items-center space-x-2 cursor-pointer p-2 bg-gray-700 rounded-lg border border-transparent hover:border-primary-500 transition duration-200 flex-1 justify-center" tabindex="0" role="radio" aria-checked="true">
                        <input type="radio" name="scheduleVehicleType" value="standard" class="form-radio text-primary-500 focus:ring-primary-500" checked>
                        <span class="text-sm">Standard</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer p-2 bg-gray-700 rounded-lg border border-transparent hover:border-primary-500 transition duration-200 flex-1 justify-center" tabindex="0" role="radio" aria-checked="false">
                        <input type="radio" name="scheduleVehicleType" value="suv" class="form-radio text-primary-500 focus:ring-primary-500">
                        <span class="text-sm">SUV</span>
                    </label>
                    <label class="flex items-center space-x-2 cursor-pointer p-2 bg-gray-700 rounded-lg border border-transparent hover:border-primary-500 transition duration-200 flex-1 justify-center" tabindex="0" role="radio" aria-checked="false">
                        <input type="radio" name="scheduleVehicleType" value="premium" class="form-radio text-primary-500 focus:ring-primary-500">
                        <span class="text-sm">Premium</span>
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="schedule-date" class="block text-sm font-medium text-gray-300 mb-1">Date</label>
                    <input type="date" id="schedule-date" name="date" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent appearance-none" style="color-scheme: dark;">
                </div>
                <div>
                    <label for="schedule-time" class="block text-sm font-medium text-gray-300 mb-1">Time</label>
                    <input type="time" id="schedule-time" name="time" required class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent appearance-none" style="color-scheme: dark;">
                </div>
            </div>
            <div>
                <label for="schedule-notes" class="block text-sm font-medium text-gray-300 mb-1">Notes (Optional)</label>
                <textarea id="schedule-notes" name="notes" rows="2" placeholder="Any special instructions? e.g., Flight number" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
            </div>
            <div id="schedule-fare-estimate" class="text-center text-gray-300 text-lg font-medium p-2 bg-gray-700/50 rounded-lg">
            </div>
            <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">Schedule Ride</button>
        </form>
    </div>
</div>