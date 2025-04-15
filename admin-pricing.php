<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Set page title
$pageTitle = "Pricing Management - Admin Dashboard";

// Process pricing update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            setFlashMessage('error', 'Security validation failed. Please try again.');
        } else {
            $vehicleType = isset($_POST['vehicle_type']) ? $_POST['vehicle_type'] : '';
            $baseRate = isset($_POST['base_rate']) ? (float)$_POST['base_rate'] : 0;
            $pricePerKm = isset($_POST['price_per_km']) ? (float)$_POST['price_per_km'] : 0;
            $multiplier = isset($_POST['multiplier']) ? (float)$_POST['multiplier'] : 0;
            $minFare = isset($_POST['min_fare']) ? (float)$_POST['min_fare'] : 0;
            
            // Validate data
            if (empty($vehicleType) || $baseRate <= 0 || $pricePerKm <= 0 || $multiplier <= 0 || $minFare <= 0) {
                setFlashMessage('error', 'All fields are required and must be greater than zero.');
            } else {
                // Update pricing
                $data = [
                    'base_rate' => $baseRate,
                    'price_per_km' => $pricePerKm,
                    'multiplier' => $multiplier,
                    'min_fare' => $minFare,
                    'updated_by' => $_SESSION['admin_id']
                ];
                
                $result = updatePricing($vehicleType, $data);
                
                if ($result) {
                    setFlashMessage('success', 'Pricing for ' . ucfirst($vehicleType) . ' updated successfully.');
                } else {
                    setFlashMessage('error', 'Failed to update pricing. Please try again.');
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error updating pricing: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating pricing.');
    }
    
    // Redirect to prevent form resubmission
    header('Location: admin-pricing.php');
    exit;
}

// Get current pricing
$pricing = getAllPricing();

// Include admin header
require_once 'includes/admin-header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">Pricing Management</h1>
    </div>

    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md overflow-hidden">
        <div class="p-5">
            <p class="text-gray-400 mb-4">Manage the pricing for different vehicle types. These settings will be used to calculate ride fares.</p>
            
            <?php if (empty($pricing)): ?>
            <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                <p>No pricing information found. Please initialize the pricing table using the database migration script.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <?php foreach ($pricing as $price): ?>
                <div class="bg-gray-700/50 rounded-lg border border-gray-600 overflow-hidden">
                    <div class="bg-gray-700 p-4 border-b border-gray-600">
                        <h3 class="text-lg font-medium text-white"><?php echo ucfirst($price['vehicle_type']); ?> Vehicle Pricing</h3>
                    </div>
                    
                    <form action="admin-pricing.php" method="post" class="p-4 space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_pricing">
                        <input type="hidden" name="vehicle_type" value="<?php echo $price['vehicle_type']; ?>">
                        
                        <div>
                            <label for="base_rate_<?php echo $price['vehicle_type']; ?>" class="block text-sm font-medium text-gray-300 mb-1">Base Rate (G$)</label>
                            <input type="number" id="base_rate_<?php echo $price['vehicle_type']; ?>" name="base_rate" value="<?php echo $price['base_rate']; ?>" required min="0" step="0.01" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Initial fare when a ride starts</p>
                        </div>
                        
                        <div>
                            <label for="price_per_km_<?php echo $price['vehicle_type']; ?>" class="block text-sm font-medium text-gray-300 mb-1">Price per KM (G$)</label>
                            <input type="number" id="price_per_km_<?php echo $price['vehicle_type']; ?>" name="price_per_km" value="<?php echo $price['price_per_km']; ?>" required min="0" step="0.01" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Amount charged per kilometer</p>
                        </div>
                        
                        <div>
                            <label for="multiplier_<?php echo $price['vehicle_type']; ?>" class="block text-sm font-medium text-gray-300 mb-1">Fare Multiplier</label>
                            <input type="number" id="multiplier_<?php echo $price['vehicle_type']; ?>" name="multiplier" value="<?php echo $price['multiplier']; ?>" required min="0.1" step="0.01" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Multiplier applied to the total fare</p>
                        </div>
                        
                        <div>
                            <label for="min_fare_<?php echo $price['vehicle_type']; ?>" class="block text-sm font-medium text-gray-300 mb-1">Minimum Fare (G$)</label>
                            <input type="number" id="min_fare_<?php echo $price['vehicle_type']; ?>" name="min_fare" value="<?php echo $price['min_fare']; ?>" required min="0" step="0.01" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Minimum fare for any ride</p>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                                Update Pricing
                            </button>
                        </div>
                        
                        <div class="text-xs text-gray-500 text-right pt-2">
                            Last updated: <?php echo date('M j, Y g:i A', strtotime($price['last_updated'])); ?>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pricing Calculator -->
    <div class="bg-gray-800 rounded-lg border border-gray-700 shadow-md overflow-hidden">
        <div class="p-5">
            <h2 class="text-xl font-bold text-white mb-4">Fare Calculator</h2>
            <p class="text-gray-400 mb-4">Test your pricing settings by calculating a fare for a given distance.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <form id="fare-calculator-form" class="space-y-4">
                        <div>
                            <label for="calculator-vehicle-type" class="block text-sm font-medium text-gray-300 mb-1">Vehicle Type</label>
                            <select id="calculator-vehicle-type" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <?php foreach ($pricing as $price): ?>
                                <option value="<?php echo $price['vehicle_type']; ?>"><?php echo ucfirst($price['vehicle_type']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="calculator-distance" class="block text-sm font-medium text-gray-300 mb-1">Distance (KM)</label>
                            <input type="number" id="calculator-distance" min="0.1" step="0.1" value="5" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        </div>
                        
                        <button type="submit" class="bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 shadow-md transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary-700 focus:ring-offset-1 focus:ring-offset-gray-800">
                            Calculate Fare
                        </button>
                    </form>
                </div>
                
                <div>
                    <div class="bg-gray-700/50 rounded-lg border border-gray-600 p-4 h-full">
                        <h3 class="text-lg font-medium text-white mb-4">Calculated Fare</h3>
                        
                        <div id="fare-result" class="space-y-4">
                            <div class="text-center text-gray-400">
                                Please use the form to calculate a fare
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get pricing data from PHP
    const pricingData = <?php echo json_encode($pricing); ?>;
    
    // Fare calculator
    const calculatorForm = document.getElementById('fare-calculator-form');
    const calculatorVehicleType = document.getElementById('calculator-vehicle-type');
    const calculatorDistance = document.getElementById('calculator-distance');
    const fareResult = document.getElementById('fare-result');
    
    calculatorForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const vehicleType = calculatorVehicleType.value;
        const distance = parseFloat(calculatorDistance.value);
        
        if (isNaN(distance) || distance <= 0) {
            fareResult.innerHTML = `
                <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                    <p>Please enter a valid distance.</p>
                </div>
            `;
            return;
        }
        
        // Find pricing for selected vehicle type
        const pricing = pricingData.find(p => p.vehicle_type === vehicleType);
        
        if (!pricing) {
            fareResult.innerHTML = `
                <div class="bg-red-500/20 text-red-400 p-4 rounded-lg">
                    <p>Pricing not found for the selected vehicle type.</p>
                </div>
            `;
            return;
        }
        
        // Calculate fare
        const baseRate = parseFloat(pricing.base_rate);
        const pricePerKm = parseFloat(pricing.price_per_km);
        const multiplier = parseFloat(pricing.multiplier);
        const minFare = parseFloat(pricing.min_fare);
        
        const distanceFare = distance * pricePerKm;
        const subtotal = baseRate + distanceFare;
        const totalFare = subtotal * multiplier;
        const finalFare = Math.max(totalFare, minFare);
        
        // Display result
        fareResult.innerHTML = `
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-400">Base Rate:</span>
                    <span class="text-white">G$${baseRate.toFixed(2)}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-400">Distance Fare (${distance} KM × G$${pricePerKm.toFixed(2)}):</span>
                    <span class="text-white">G$${distanceFare.toFixed(2)}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-400">Subtotal:</span>
                    <span class="text-white">G$${subtotal.toFixed(2)}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-gray-400">Multiplier (×${multiplier.toFixed(2)}):</span>
                    <span class="text-white">G$${(subtotal * multiplier).toFixed(2)}</span>
                </div>
                
                <div class="pt-2 border-t border-gray-600">
                    <div class="flex justify-between font-medium">
                        <span class="text-white">Total Fare:</span>
                        <span class="text-primary-400 text-xl">G$${finalFare.toFixed(2)}</span>
                    </div>
                    
                    ${finalFare === minFare ? `
                    <div class="text-xs text-yellow-400 mt-1">
                        <span class="lucide mr-1" aria-hidden="true">&#xea3e;</span>
                        Minimum fare of G$${minFare.toFixed(2)} applied
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    });
});
</script>

<?php
// Include admin footer
require_once 'includes/admin-footer.php';
?>