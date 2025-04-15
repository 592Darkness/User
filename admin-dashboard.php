<?php
// Include necessary files
require_once 'includes/config.php'; // Keep requires after log if using error_log at top
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Ensure admin is logged in
requireAdminLogin();

// Set page title
$pageTitle = "Admin Dashboard - Salaam Rides";

// --- FIX: Define default period and label for dashboard stats ---
$period = 'week'; // Default period for dashboard stats
$periodLabel = 'This Week'; // Corresponding label

// Include admin header (which might use $pageTitle)
require_once 'includes/admin-header.php';

// --- Fetch Data using Functions from admin-functions.php ---

// Get overview statistics (some might use the $period defined above)
$totalUsers = getTotalUsers();
$totalDrivers = getTotalDrivers();
$totalRides = getTotalRides(); // Overall total rides
$completedRides = getTotalRides('completed'); // Overall completed rides (consider adding period?)

// Fetch stats potentially using the $period
$totalRevenue = getTotalRevenue($period); // Use the defined period
$revenueGrowth = getRevenueGrowth($period); // Use the defined period
$completionRate = getRideCompletionRate($period); // Use the defined period
$newUsers = getNewUsers($period); // Use the defined period
$onlineDriversPercentage = getOnlineDriversPercentage(); // Doesn't depend on period

// Data for tables/lists (usually not period-dependent for overview)
$topDrivers = getTopDrivers(5);
$popularDestinations = getPopularDestinations(5);
$recentRides = getRecentRides(10);

// Data for charts (using a fixed period like last 7 days for consistency)
$dailyRevenue = getDailyRevenue(7); // Fetch last 7 days specifically for the chart
$ridesByStatus = getRidesByStatus(); // Overall status breakdown
$ridesByVehicleType = getRidesByVehicleType(); // Overall vehicle breakdown

// --- Format Data for Charts ---

// Format daily revenue for Chart.js
$revenueLabels = array_map(function($item) {
    return date('D', strtotime($item['date'])); // Display day abbreviation (Mon, Tue, etc.)
}, $dailyRevenue);

$revenueData = array_map(function($item) {
    return (float)($item['revenue'] ?? 0); // Ensure it's a float
}, $dailyRevenue);

$rideCountData = array_map(function($item) {
    return (int)($item['rides'] ?? 0); // Ensure it's an integer
}, $dailyRevenue);

// Format ride status for chart
$statusLabels = [];
$statusData = [];
foreach ($ridesByStatus as $status) {
    $statusLabels[] = ucfirst(str_replace('_', ' ', $status['status'])); // Format status nicely
    $statusData[] = (int)$status['count'];
}

// Format vehicle types for chart
$vehicleLabels = [];
$vehicleData = [];
$vehicleColors = [ // Define colors for consistency
    'rgba(79, 70, 229, 0.7)',  // Indigo (Standard)
    'rgba(245, 158, 11, 0.7)', // Yellow (SUV)
    'rgba(236, 72, 153, 0.7)', // Pink (Premium)
    'rgba(16, 185, 129, 0.7)', // Green (Other)
    'rgba(59, 130, 246, 0.7)', // Blue (Other)
];
$vehicleBorderColors = [
    'rgba(79, 70, 229, 1)',
    'rgba(245, 158, 11, 1)',
    'rgba(236, 72, 153, 1)',
    'rgba(16, 185, 129, 1)',
    'rgba(59, 130, 246, 1)',
];
$colorIndex = 0;
foreach ($ridesByVehicleType as $type) {
    $vehicleLabels[] = ucfirst($type['vehicle_type']);
    $vehicleData[] = (int)$type['count'];
}
// Ensure chart colors match the data length
$chartVehicleColors = array_slice($vehicleColors, 0, count($vehicleLabels));
$chartVehicleBorderColors = array_slice($vehicleBorderColors, 0, count($vehicleLabels));


?>

<style>
.chart-container {
    position: relative;
    height: 300px !important; /* Force height */
    max-height: 300px !important;
    min-height: 300px !important;
    width: 100%;
    overflow: hidden !important; /* Prevent overflow issues */
}

.chart-container-sm {
    height: 200px !important; /* Force height */
    max-height: 200px !important;
    width: 100%;
    position: relative;
    overflow: hidden !important;
}

/* Fix for chart dimensions within containers */
canvas.chart-canvas {
    max-height: 100% !important;
    max-width: 100% !important; /* Ensure canvas doesn't exceed container */
}
</style>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-white">Dashboard Overview</h1>
        <div>
            <span class="text-sm text-gray-400">Last Updated: <?php echo date('M j, Y g:i A'); ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Revenue (<?php echo $periodLabel; ?>)</h3>
                    <p class="text-2xl font-bold text-white"><?php echo formatCurrency($totalRevenue); ?></p>
                </div>
                <div class="p-2 bg-yellow-500/20 rounded-full">
                    <span class="lucide text-2xl text-yellow-400" aria-hidden="true">&#xec8f;</span> </div>
            </div>
            <div class="text-xs text-gray-500">
                <?php
                // Display revenue growth percentage using real data and the defined $period
                if ($revenueGrowth !== null) {
                    $growthClass = $revenueGrowth >= 0 ? 'text-green-400' : 'text-red-400';
                    $growthIcon = $revenueGrowth >= 0 ? '&#xeaaf;' : '&#xeab1;'; // Trend Up / Trend Down Icons
                    echo '<span class="inline-flex items-center ' . $growthClass . '">';
                    echo '<span class="lucide mr-1" aria-hidden="true">' . $growthIcon . '</span>';
                    echo abs(round($revenueGrowth, 1)) . '% ' . ($revenueGrowth >= 0 ? 'increase' : 'decrease');
                    echo '</span> vs previous ' . $period;
                } else {
                    echo 'No previous data for comparison';
                }
                ?>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Completed Rides</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($completedRides); ?></p>
                </div>
                <div class="p-2 bg-green-500/20 rounded-full">
                    <span class="lucide text-2xl text-green-400" aria-hidden="true">&#xeb15;</span> </div>
            </div>
            <div class="text-xs text-gray-500">
                 <?php
                // Display ride completion rate using real data and the defined $period
                echo number_format($completionRate, 1) . '% completion rate';
                ?>
                 (<?php echo $periodLabel; ?>) </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Total Users</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="p-2 bg-blue-500/20 rounded-full">
                    <span class="lucide text-2xl text-blue-400" aria-hidden="true">&#xea05;</span> </div>
            </div>
            <div class="text-xs text-gray-500">
                <span class="inline-flex items-center text-green-400">
                    <span class="lucide mr-1" aria-hidden="true">&#xeaaf;</span> <?php
                    // Display new users count using real data and the defined $period
                    echo number_format($newUsers);
                    ?> new users
                </span> this <?php echo $period; // Display the selected period ?>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Active Drivers</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($totalDrivers); ?></p>
                </div>
                <div class="p-2 bg-purple-500/20 rounded-full">
                    <span class="lucide text-2xl text-purple-400" aria-hidden="true">&#xebe4;</span> </div>
            </div>
            <div class="text-xs text-gray-500">
                 <?php
                // Display online drivers percentage using real data
                echo number_format($onlineDriversPercentage, 1);
                ?>% currently online
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Revenue & Rides (Last 7 Days)</h3>
            <div class="chart-container"> <canvas id="revenueChart" class="chart-canvas"></canvas>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Ride Breakdown</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                <div>
                    <h4 class="text-sm font-medium text-gray-400 mb-2 text-center">By Status</h4>
                    <div class="chart-container-sm"> <canvas id="statusChart" class="chart-canvas"></canvas>
                    </div>
                </div>
                <div>
                     <h4 class="text-sm font-medium text-gray-400 mb-2 text-center">By Vehicle Type</h4>
                    <div class="chart-container-sm"> <canvas id="vehicleChart" class="chart-canvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-white">Top Drivers</h3>
                <a href="admin-drivers.php" class="text-xs text-primary-400 hover:text-primary-300 transition-colors">
                    View All Drivers →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-gray-400 text-xs uppercase tracking-wider border-b border-gray-700">
                            <th class="py-3 px-2 text-left">Driver</th>
                            <th class="py-3 px-2 text-right">Rides</th>
                            <th class="py-3 px-2 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($topDrivers)): ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-500">No top driver data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($topDrivers as $driver): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3 px-2 text-white font-medium">
                                    <?php echo htmlspecialchars($driver['name']); ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($driver['vehicle']); ?></div>
                                </td>
                                <td class="py-3 px-2 text-right"><?php echo number_format($driver['total_rides']); ?></td>
                                <td class="py-3 px-2 text-right text-yellow-400"><?php echo formatCurrency($driver['total_revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-white">Popular Destinations</h3>
                <a href="admin-analytics.php" class="text-xs text-primary-400 hover:text-primary-300 transition-colors">
                    View Detailed Analytics →
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-gray-400 text-xs uppercase tracking-wider border-b border-gray-700">
                            <th class="py-3 px-2 text-left">Destination</th>
                            <th class="py-3 px-2 text-right">Ride Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($popularDestinations)): ?>
                        <tr>
                            <td colspan="2" class="py-4 text-center text-gray-500">No popular destination data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($popularDestinations as $destination): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3 px-2 text-white truncate max-w-xs"><?php echo htmlspecialchars($destination['dropoff']); ?></td>
                                <td class="py-3 px-2 text-right"><?php echo number_format($destination['count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-white">Recent Rides</h3>
            <a href="admin-rides.php" class="text-xs text-primary-400 hover:text-primary-300 transition-colors">
                View All Rides →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-gray-400 text-xs uppercase tracking-wider border-b border-gray-700">
                        <th class="py-3 px-2 text-left">ID</th>
                        <th class="py-3 px-2 text-left">User</th>
                        <th class="py-3 px-2 text-left">Driver</th>
                        <th class="py-3 px-2 text-left">Route</th>
                        <th class="py-3 px-2 text-right">Fare</th>
                        <th class="py-3 px-2 text-center">Status</th>
                        <th class="py-3 px-2 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($recentRides)): ?>
                    <tr>
                        <td colspan="7" class="py-4 text-center text-gray-500">No recent rides available</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentRides as $ride): ?>
                        <tr class="hover:bg-gray-700/50 transition-colors">
                            <td class="py-3 px-2">#<?php echo $ride['id']; ?></td>
                            <td class="py-3 px-2 text-white"><?php echo htmlspecialchars($ride['user_name'] ?? 'Unknown'); ?></td>
                            <td class="py-3 px-2"><?php echo htmlspecialchars($ride['driver_name'] ?? 'Unassigned'); ?></td>
                            <td class="py-3 px-2 text-xs">
                                <div class="text-gray-300 truncate" title="<?php echo htmlspecialchars($ride['pickup']); ?>"><?php echo htmlspecialchars(substr($ride['pickup'], 0, 25) . (strlen($ride['pickup']) > 25 ? '...' : '')); ?></div>
                                <div class="text-gray-500">→ <?php echo htmlspecialchars(substr($ride['dropoff'], 0, 25) . (strlen($ride['dropoff']) > 25 ? '...' : '')); ?></div>
                            </td>
                            <td class="py-3 px-2 text-right text-yellow-400"><?php echo formatCurrency($ride['fare']); ?></td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo getRideStatusColor($ride['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $ride['status'])); ?>
                                </span>
                            </td>
                            <td class="py-3 px-2 text-right text-xs text-gray-400"><?php echo date('M j, Y g:i A', strtotime($ride['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Revenue Chart ---
    const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($revenueLabels); ?>,
                datasets: [
                    {
                        label: 'Revenue (G$)',
                        data: <?php echo json_encode($revenueData); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)', // Green
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1,
                        yAxisID: 'y' // Assign to primary Y-axis
                    },
                    {
                        label: 'Rides',
                        data: <?php echo json_encode($rideCountData); ?>,
                        type: 'line', // Override type to line
                        fill: false,
                        borderColor: 'rgba(79, 70, 229, 1)', // Indigo
                        backgroundColor: 'rgba(79, 70, 229, 0.7)',
                        tension: 0.4,
                        yAxisID: 'y1' // Assign to secondary Y-axis
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    y: { // Primary Y-axis (Revenue)
                        beginAtZero: true,
                        title: { display: true, text: 'Revenue (G$)', color: '#9ca3af' },
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                        ticks: { color: '#9ca3af' }
                    },
                    y1: { // Secondary Y-axis (Ride Count)
                        beginAtZero: true,
                        position: 'right',
                        title: { display: true, text: 'Ride Count', color: '#9ca3af' },
                        grid: { drawOnChartArea: false }, // Don't draw grid lines for secondary axis
                        ticks: { color: '#9ca3af', precision: 0 } // Ensure whole numbers for ride count
                    },
                    x: { // Shared X-axis
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                        ticks: { color: '#9ca3af' }
                    }
                },
                plugins: {
                    legend: { labels: { color: '#d1d5db' } },
                    tooltip: { mode: 'index', intersect: false }
                }
            }
        });
    } else {
        console.warn("Revenue Chart canvas not found.");
    }

    // --- Status Chart ---
    const statusCtx = document.getElementById('statusChart')?.getContext('2d');
     if (statusCtx && <?php echo json_encode(!empty($statusData)); ?>) { // Check if data exists
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusData); ?>,
                    backgroundColor: [ // Define colors for statuses
                        'rgba(16, 185, 129, 0.7)',  // Completed - Green
                        'rgba(239, 68, 68, 0.7)',   // Cancelled - Red
                        'rgba(59, 130, 246, 0.7)',  // In Progress - Blue
                        'rgba(245, 158, 11, 0.7)',  // Searching - Yellow
                        'rgba(139, 92, 246, 0.7)',  // Confirmed/Arriving/Arrived - Purple/Indigo/Pink
                        'rgba(107, 114, 128, 0.7)'  // Other - Gray
                    ],
                    borderColor: [
                        'rgba(16, 185, 129, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(107, 114, 128, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#d1d5db', font: { size: 10 }, boxWidth: 12 }
                    }
                }
            }
        });
    } else {
        console.warn("Status Chart canvas not found or no data.");
         if (statusCtx) statusCtx.canvas.parentElement.innerHTML = '<p class="text-center text-gray-500 text-sm h-full flex items-center justify-center">No status data</p>';
    }

    // --- Vehicle Chart ---
    const vehicleCtx = document.getElementById('vehicleChart')?.getContext('2d');
     if (vehicleCtx && <?php echo json_encode(!empty($vehicleData)); ?>) { // Check if data exists
        new Chart(vehicleCtx, {
            type: 'pie', // Use Pie chart for vehicle breakdown
            data: {
                labels: <?php echo json_encode($vehicleLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($vehicleData); ?>,
                    backgroundColor: <?php echo json_encode($chartVehicleColors); ?>, // Use dynamic colors
                    borderColor: <?php echo json_encode($chartVehicleBorderColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#d1d5db', font: { size: 10 }, boxWidth: 12 }
                    }
                }
            }
        });
    } else {
        console.warn("Vehicle Chart canvas not found or no data.");
         if (vehicleCtx) vehicleCtx.canvas.parentElement.innerHTML = '<p class="text-center text-gray-500 text-sm h-full flex items-center justify-center">No vehicle data</p>';
    }
});
</script>

<?php
// Include the admin footer HTML structure
require_once 'includes/admin-footer.php';
?>
