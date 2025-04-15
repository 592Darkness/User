<?php
// Include necessary configuration, functions, and database connection files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Ensure admin is logged in (Redirects if not)
requireAdminLogin();

// Set page title for the header
$pageTitle = "Analytics - Admin Dashboard";

// Get the selected time period from the URL query parameter, default to 'week'
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
// Validate the period to ensure it's one of the allowed values
if (!in_array($period, ['day', 'week', 'month', 'year'])) {
    $period = 'week'; // Default to 'week' if invalid
}

// Determine a user-friendly label for the selected period
$periodLabel = '';
switch ($period) {
    case 'day':
        $periodLabel = 'Today';
        break;
    case 'week':
        $periodLabel = 'This Week';
        break;
    case 'month':
        $periodLabel = 'This Month';
        break;
    case 'year':
        $periodLabel = 'This Year';
        break;
}

// --- Fetch Data using Functions from admin-functions.php ---

// Get overview statistics for the selected period
$totalRevenue = getTotalRevenue($period); // Total revenue for the period
$completedRides = getTotalRides('completed'); // Total completed rides (consider adding period filter if needed)
$totalUsers = getTotalUsers(); // Overall total users
$totalDrivers = getTotalDrivers(); // Overall total drivers

// Get data specifically for the cards, using the selected period
$revenueGrowth = getRevenueGrowth($period); // Revenue growth percentage
$completionRate = getRideCompletionRate($period); // Ride completion rate percentage
$newUsers = getNewUsers($period); // Number of new users in the period
$onlineDriversPercentage = getOnlineDriversPercentage(); // Percentage of drivers currently online

// Get data for charts
$rideAnalytics = getRideAnalytics($period); // Data for the main activity chart
$ridesByVehicleType = getRidesByVehicleType(); // Data for the vehicle type pie chart
$topDrivers = getTopDrivers(10); // Top 10 drivers by revenue/rides
$popularDestinations = getPopularDestinations(10); // Top 10 popular destinations

// --- Format Data for Charts (using Chart.js) ---

// Format data for the main Ride Activity & Revenue chart
$analyticsLabels = [];
$analyticsTotalRides = [];
$analyticsCompletedRides = [];
$analyticsCancelledRides = [];
$analyticsRevenue = [];

foreach ($rideAnalytics as $item) {
    $analyticsLabels[] = $item['label']; // e.g., 'Mon', 'Tue' or 'Jan', 'Feb'
    $analyticsTotalRides[] = $item['total'];
    $analyticsCompletedRides[] = $item['completed'];
    $analyticsCancelledRides[] = $item['cancelled'];
    $analyticsRevenue[] = $item['revenue'];
}

// Format data for the Vehicle Type pie chart
$vehicleLabels = [];
$vehicleData = [];
foreach ($ridesByVehicleType as $type) {
    $vehicleLabels[] = ucfirst($type['vehicle_type']); // Capitalize type name
    $vehicleData[] = $type['count'];
}

// Include the admin header HTML structure
require_once 'includes/admin-header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <h1 class="text-2xl font-bold text-white">Analytics Dashboard</h1>

        <div class="flex items-center bg-gray-800 rounded-lg border border-gray-700 p-1">
            <a href="admin-analytics.php?period=day" class="px-3 py-1.5 rounded-md <?php echo $period === 'day' ? 'bg-primary-500 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'; ?> transition duration-200 text-sm">
                Today
            </a>
            <a href="admin-analytics.php?period=week" class="px-3 py-1.5 rounded-md <?php echo $period === 'week' ? 'bg-primary-500 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'; ?> transition duration-200 text-sm">
                Week
            </a>
            <a href="admin-analytics.php?period=month" class="px-3 py-1.5 rounded-md <?php echo $period === 'month' ? 'bg-primary-500 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'; ?> transition duration-200 text-sm">
                Month
            </a>
            <a href="admin-analytics.php?period=year" class="px-3 py-1.5 rounded-md <?php echo $period === 'year' ? 'bg-primary-500 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-700'; ?> transition duration-200 text-sm">
                Year
            </a>
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
                // Display revenue growth percentage using real data
                if ($revenueGrowth !== null) {
                    $growthClass = $revenueGrowth >= 0 ? 'text-green-400' : 'text-red-400';
                    $growthIcon = $revenueGrowth >= 0 ? '&#xeaaf;' : '&#xeab1;'; // Trend Up / Trend Down Icons
                    echo '<span class="inline-flex items-center ' . $growthClass . '">';
                    echo '<span class="lucide mr-1" aria-hidden="true">' . $growthIcon . '</span>';
                    echo abs(round($revenueGrowth, 1)) . '% ' . ($revenueGrowth >= 0 ? 'increase' : 'decrease');
                    echo '</span> vs previous ' . $period;
                } else {
                    echo 'No previous data for comparison'; // Message if growth can't be calculated
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
                // Display ride completion rate using real data
                echo number_format($completionRate, 1) . '% completion rate';
                ?>
            </div>
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
                    // Display new users count using real data
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

    <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
        <h3 class="text-lg font-medium text-white mb-4">Ride Activity (<?php echo $periodLabel; ?>)</h3>
        <div class="h-80">
            <canvas id="rideActivityChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Rides by Vehicle Type</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                <div class="h-60">
                    <canvas id="vehicleTypeChart"></canvas>
                </div>
                <div class="flex flex-col justify-center">
                    <?php if (!empty($ridesByVehicleType)): ?>
                        <?php
                        // Calculate total rides for percentage calculation
                        $totalVehicleRides = array_sum(array_column($ridesByVehicleType, 'count'));
                        // Define colors matching the chart
                        $vehicleColors = ['rgba(79, 70, 229, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(236, 72, 153, 0.7)'];
                        ?>
                        <?php foreach ($ridesByVehicleType as $index => $type): ?>
                            <?php $percentage = ($totalVehicleRides > 0) ? ($type['count'] / $totalVehicleRides) * 100 : 0; ?>
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-white"><?php echo ucfirst($type['vehicle_type']); ?></span>
                                    <span class="text-xs text-gray-400"><?php echo number_format($type['count']); ?> rides</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $vehicleColors[$index % count($vehicleColors)]; ?>;">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="mt-4 text-center">
                            <div class="text-sm font-medium text-gray-300">Most Popular</div>
                            <div class="text-2xl font-bold text-white mt-1">
                                <?php echo ucfirst($ridesByVehicleType[0]['vehicle_type']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-400 text-center">No vehicle type data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Revenue Trends</h3>
            <div class="h-60">
                <canvas id="revenueTrendChart"></canvas>
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
                            <th class="py-3 px-2 text-left">#</th>
                            <th class="py-3 px-2 text-left">Driver</th>
                            <th class="py-3 px-2 text-center">Total Rides</th>
                            <th class="py-3 px-2 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($topDrivers)): ?>
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-500">No driver data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($topDrivers as $index => $driver): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3 px-2 text-gray-400"><?php echo $index + 1; ?></td>
                                <td class="py-3 px-2">
                                    <div class="font-medium text-white"><?php echo htmlspecialchars($driver['name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($driver['vehicle']); ?></div>
                                </td>
                                <td class="py-3 px-2 text-center"><?php echo number_format($driver['total_rides']); ?></td>
                                <td class="py-3 px-2 text-right text-yellow-400"><?php echo formatCurrency($driver['total_revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Popular Destinations</h3>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-gray-400 text-xs uppercase tracking-wider border-b border-gray-700">
                            <th class="py-3 px-2 text-left">Destination</th>
                            <th class="py-3 px-2 text-right">Ride Count</th>
                            <th class="py-3 px-2 text-right">% of Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($popularDestinations)): ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-500">No destination data available</td>
                        </tr>
                        <?php else: ?>
                            <?php
                            // Calculate total rides for percentage calculation
                            $totalDestinationRides = array_sum(array_column($popularDestinations, 'count'));
                            ?>
                            <?php foreach ($popularDestinations as $destination): ?>
                                <?php $percentage = ($totalDestinationRides > 0) ? ($destination['count'] / $totalDestinationRides) * 100 : 0; ?>
                                <tr class="hover:bg-gray-700/50 transition-colors">
                                    <td class="py-3 px-2 text-white truncate max-w-xs"><?php echo htmlspecialchars($destination['dropoff']); ?></td>
                                    <td class="py-3 px-2 text-right"><?php echo number_format($destination['count']); ?></td>
                                    <td class="py-3 px-2 text-right">
                                        <div class="inline-flex items-center">
                                            <div class="w-16 bg-gray-700 rounded-full h-1.5 mr-2">
                                                <div class="h-1.5 rounded-full bg-primary-500" style="width: <?php echo $percentage; ?>%;"></div>
                                            </div>
                                            <span class="text-xs"><?php echo number_format($percentage, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Ride Activity & Revenue Chart (Combined Bar and Line) ---
    const rideActivityCtx = document.getElementById('rideActivityChart')?.getContext('2d');
    if (rideActivityCtx) {
        const rideActivityChart = new Chart(rideActivityCtx, {
            type: 'bar', // Base type is bar
            data: {
                labels: <?php echo json_encode($analyticsLabels); ?>,
                datasets: [
                    {
                        label: 'Total Rides',
                        data: <?php echo json_encode($analyticsTotalRides); ?>,
                        backgroundColor: 'rgba(107, 114, 128, 0.7)', // Gray
                        borderColor: 'rgba(107, 114, 128, 1)',
                        borderWidth: 1,
                        order: 3 // Render last
                    },
                    {
                        label: 'Completed Rides',
                        data: <?php echo json_encode($analyticsCompletedRides); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)', // Green
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1,
                        order: 2
                    },
                    {
                        label: 'Cancelled Rides',
                        data: <?php echo json_encode($analyticsCancelledRides); ?>,
                        backgroundColor: 'rgba(239, 68, 68, 0.7)', // Red
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1,
                        order: 1
                    },
                    {
                        label: 'Revenue (G$)',
                        data: <?php echo json_encode($analyticsRevenue); ?>,
                        type: 'line', // Override type for this dataset
                        fill: false,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)', // Yellow
                        borderColor: 'rgba(245, 158, 11, 1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgba(245, 158, 11, 1)',
                        tension: 0.4,
                        yAxisID: 'y1', // Assign to the secondary Y-axis
                        order: 0 // Render first (on top)
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    // Primary Y-axis for ride counts
                    y: {
                        beginAtZero: true,
                        stacked: true, // Stack the ride bars
                        title: { display: true, text: 'Number of Rides', color: '#9ca3af' },
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                        ticks: { color: '#9ca3af' }
                    },
                    // Secondary Y-axis for revenue
                    y1: {
                        beginAtZero: true,
                        position: 'right', // Position on the right side
                        title: { display: true, text: 'Revenue (G$)', color: '#9ca3af' },
                        grid: { drawOnChartArea: false }, // Don't draw grid lines for this axis
                        ticks: { color: '#9ca3af' }
                    },
                    // X-axis (shared)
                    x: {
                        stacked: true, // Ensure bars stack correctly
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                        ticks: { color: '#9ca3af' }
                    }
                },
                plugins: {
                    legend: { labels: { color: '#d1d5db' } },
                    tooltip: { mode: 'index', intersect: false } // Show tooltips for all datasets at the same index
                }
            }
        });
    } else {
        console.warn("Ride Activity Chart canvas not found.");
    }

    // --- Vehicle Type Chart (Doughnut) ---
    const vehicleTypeCtx = document.getElementById('vehicleTypeChart')?.getContext('2d');
    if (vehicleTypeCtx && <?php echo json_encode(!empty($vehicleData)); ?>) { // Only render if data exists
        const vehicleTypeChart = new Chart(vehicleTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($vehicleLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($vehicleData); ?>,
                    backgroundColor: [
                        'rgba(79, 70, 229, 0.7)',  // Indigo
                        'rgba(245, 158, 11, 0.7)',  // Yellow
                        'rgba(236, 72, 153, 0.7)',  // Pink
                        // Add more colors if needed
                    ],
                    borderColor: [
                        'rgba(79, 70, 229, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(236, 72, 153, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#d1d5db', padding: 20, font: { size: 12 } }
                    }
                }
            }
        });
    } else {
        console.warn("Vehicle Type Chart canvas not found or no data.");
    }

    // --- Revenue Trend Chart (Line) ---
    const revenueTrendCtx = document.getElementById('revenueTrendChart')?.getContext('2d');
    if (revenueTrendCtx) {
        const revenueTrendChart = new Chart(revenueTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($analyticsLabels); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode($analyticsRevenue); ?>,
                    fill: true, // Fill area under the line
                    backgroundColor: 'rgba(16, 185, 129, 0.1)', // Light green fill
                    borderColor: 'rgba(16, 185, 129, 1)', // Green line
                    tension: 0.4, // Smooth curve
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(16, 185, 129, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                        ticks: {
                            color: '#9ca3af',
                            // Format Y-axis ticks as currency
                            callback: function(value) {
                                return 'G$' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: { color: 'rgba(75, 85, 99, 0.2)' },
                        ticks: { color: '#9ca3af' }
                    }
                },
                plugins: {
                    legend: { display: false }, // Hide legend for single dataset
                    tooltip: {
                        // Format tooltip to show currency
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: G$' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    } else {
        console.warn("Revenue Trend Chart canvas not found.");
    }
});
</script>

<?php
// Include the admin footer HTML structure
require_once 'includes/admin-footer.php';
?>
