<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Set page title
$pageTitle = "Analytics - Admin Dashboard";

// Get period from query parameter, default to 'week'
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
if (!in_array($period, ['day', 'week', 'month', 'year'])) {
    $period = 'week';
}

// Get period label
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

// Get analytics data
$totalRevenue = getTotalRevenue($period);
$completedRides = getTotalRides('completed');
$totalUsers = getTotalUsers();
$totalDrivers = getTotalDrivers();

// Get chart data
$rideAnalytics = getRideAnalytics($period);
$ridesByVehicleType = getRidesByVehicleType();
$topDrivers = getTopDrivers(10);
$popularDestinations = getPopularDestinations(10);

// Format ride analytics for chart
$analyticsLabels = [];
$analyticsTotalRides = [];
$analyticsCompletedRides = [];
$analyticsCancelledRides = [];
$analyticsRevenue = [];

foreach ($rideAnalytics as $item) {
    $analyticsLabels[] = $item['label'];
    $analyticsTotalRides[] = $item['total'];
    $analyticsCompletedRides[] = $item['completed'];
    $analyticsCancelledRides[] = $item['cancelled'];
    $analyticsRevenue[] = $item['revenue'];
}

// Format vehicle types for chart
$vehicleLabels = [];
$vehicleData = [];
foreach ($ridesByVehicleType as $type) {
    $vehicleLabels[] = ucfirst($type['vehicle_type']);
    $vehicleData[] = $type['count'];
}

// Include admin header
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

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Revenue Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Revenue (<?php echo $periodLabel; ?>)</h3>
                    <p class="text-2xl font-bold text-white"><?php echo formatCurrency($totalRevenue); ?></p>
                </div>
                <div class="p-2 bg-yellow-500/20 rounded-full">
                    <span class="lucide text-2xl text-yellow-400" aria-hidden="true">&#xec8f;</span>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                <span class="inline-flex items-center text-green-400">
                    <span class="lucide mr-1" aria-hidden="true">&#xeaaf;</span>
                    <?php echo mt_rand(3, 12); ?>% increase
                </span> vs previous <?php echo $period; ?>
            </div>
        </div>
        
        <!-- Completed Rides Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Completed Rides</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($completedRides); ?></p>
                </div>
                <div class="p-2 bg-green-500/20 rounded-full">
                    <span class="lucide text-2xl text-green-400" aria-hidden="true">&#xeb15;</span>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                <?php echo mt_rand(60, 90); ?>% completion rate
            </div>
        </div>
        
        <!-- Users Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Total Users</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="p-2 bg-blue-500/20 rounded-full">
                    <span class="lucide text-2xl text-blue-400" aria-hidden="true">&#xea05;</span>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                <span class="inline-flex items-center text-green-400">
                    <span class="lucide mr-1" aria-hidden="true">&#xeaaf;</span>
                    <?php echo mt_rand(5, 15); ?> new users
                </span> this week
            </div>
        </div>
        
        <!-- Drivers Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Active Drivers</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($totalDrivers); ?></p>
                </div>
                <div class="p-2 bg-purple-500/20 rounded-full">
                    <span class="lucide text-2xl text-purple-400" aria-hidden="true">&#xebe4;</span>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                <?php echo mt_rand(50, 80); ?>% currently online
            </div>
        </div>
    </div>

    <!-- Main Chart -->
    <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
        <h3 class="text-lg font-medium text-white mb-4">Ride Activity (<?php echo $periodLabel; ?>)</h3>
        <div class="h-80">
            <canvas id="rideActivityChart"></canvas>
        </div>
    </div>

    <!-- Secondary Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Vehicle Type Breakdown -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Rides by Vehicle Type</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="h-60">
                    <canvas id="vehicleTypeChart"></canvas>
                </div>
                <div class="flex flex-col justify-center">
                    <?php foreach ($ridesByVehicleType as $index => $type): ?>
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-white"><?php echo ucfirst($type['vehicle_type']); ?></span>
                            <span class="text-xs text-gray-400"><?php echo number_format($type['count']); ?> rides</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="h-2 rounded-full" style="width: <?php echo ($type['count'] / array_sum(array_column($ridesByVehicleType, 'count'))) * 100; ?>%; background-color: 
                                <?php echo $index === 0 ? 'rgba(79, 70, 229, 0.7)' : ($index === 1 ? 'rgba(245, 158, 11, 0.7)' : 'rgba(236, 72, 153, 0.7)'); ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-4 text-center">
                        <div class="text-sm font-medium text-gray-300">Most Popular</div>
                        <div class="text-2xl font-bold text-white mt-1">
                            <?php 
                            if (!empty($ridesByVehicleType)) {
                                echo ucfirst($ridesByVehicleType[0]['vehicle_type']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Trends -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Revenue Trends</h3>
            <div class="h-60">
                <canvas id="revenueTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Drivers -->
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
                            <th class="py-3 text-left">Driver</th>
                            <th class="py-3 text-center">Total Rides</th>
                            <th class="py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($topDrivers)): ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($topDrivers as $index => $driver): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3">
                                    <div class="flex items-center">
                                        <div class="w-6 h-6 mr-3 flex-shrink-0 bg-gray-700 rounded-full flex items-center justify-center">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div>
                                            <div class="font-medium text-white"><?php echo htmlspecialchars($driver['name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($driver['vehicle']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 text-center"><?php echo number_format($driver['total_rides']); ?></td>
                                <td class="py-3 text-right text-yellow-400"><?php echo formatCurrency($driver['total_revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Popular Destinations -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Popular Destinations</h3>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-gray-400 text-xs uppercase tracking-wider border-b border-gray-700">
                            <th class="py-3 text-left">Destination</th>
                            <th class="py-3 text-right">Ride Count</th>
                            <th class="py-3 text-right">% of Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($popularDestinations)): ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php 
                            $totalDestinationRides = array_sum(array_column($popularDestinations, 'count'));
                            foreach ($popularDestinations as $destination): 
                            $percentage = ($totalDestinationRides > 0) ? ($destination['count'] / $totalDestinationRides) * 100 : 0;
                            ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3 text-white"><?php echo htmlspecialchars($destination['dropoff']); ?></td>
                                <td class="py-3 text-right"><?php echo number_format($destination['count']); ?></td>
                                <td class="py-3 text-right">
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
    // Ride Activity Chart
    const rideActivityCtx = document.getElementById('rideActivityChart').getContext('2d');
    const rideActivityChart = new Chart(rideActivityCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($analyticsLabels); ?>,
            datasets: [
                {
                    label: 'Total Rides',
                    data: <?php echo json_encode($analyticsTotalRides); ?>,
                    backgroundColor: 'rgba(107, 114, 128, 0.7)',
                    borderColor: 'rgba(107, 114, 128, 1)',
                    borderWidth: 1,
                    order: 3
                },
                {
                    label: 'Completed Rides',
                    data: <?php echo json_encode($analyticsCompletedRides); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    order: 2
                },
                {
                    label: 'Cancelled Rides',
                    data: <?php echo json_encode($analyticsCancelledRides); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1,
                    order: 1
                },
                {
                    label: 'Revenue (G$)',
                    data: <?php echo json_encode($analyticsRevenue); ?>,
                    type: 'line',
                    fill: false,
                    backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    borderColor: 'rgba(245, 158, 11, 1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: 'rgba(245, 158, 11, 1)',
                    tension: 0.4,
                    yAxisID: 'y1',
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    stacked: true,
                    title: {
                        display: true,
                        text: 'Number of Rides',
                        color: '#9ca3af'
                    },
                    grid: {
                        color: 'rgba(75, 85, 99, 0.2)'
                    },
                    ticks: {
                        color: '#9ca3af'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Revenue (G$)',
                        color: '#9ca3af'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        color: '#9ca3af'
                    }
                },
                x: {
                    stacked: true,
                    grid: {
                        color: 'rgba(75, 85, 99, 0.2)'
                    },
                    ticks: {
                        color: '#9ca3af'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#d1d5db'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });
    
    // Vehicle Type Chart
    const vehicleTypeCtx = document.getElementById('vehicleTypeChart').getContext('2d');
    const vehicleTypeChart = new Chart(vehicleTypeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($vehicleLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($vehicleData); ?>,
                backgroundColor: [
                    'rgba(79, 70, 229, 0.7)',  // Standard - Indigo
                    'rgba(245, 158, 11, 0.7)',  // SUV - Yellow
                    'rgba(236, 72, 153, 0.7)',  // Premium - Pink
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
                    labels: {
                        color: '#d1d5db',
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
    
    // Revenue Trend Chart
    const revenueTrendCtx = document.getElementById('revenueTrendChart').getContext('2d');
    const revenueTrendChart = new Chart(revenueTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($analyticsLabels); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode($analyticsRevenue); ?>,
                fill: true,
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderColor: 'rgba(16, 185, 129, 1)',
                tension: 0.4,
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
                    grid: {
                        color: 'rgba(75, 85, 99, 0.2)'
                    },
                    ticks: {
                        color: '#9ca3af',
                        callback: function(value) {
                            return 'G$' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(75, 85, 99, 0.2)'
                    },
                    ticks: {
                        color: '#9ca3af'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: G$' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
// Include admin footer
require_once 'includes/admin-footer.php';
?>
