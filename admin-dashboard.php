<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/admin-functions.php';

// Set page title
$pageTitle = "Admin Dashboard - Salaam Rides";

// Include admin header
require_once 'includes/admin-header.php';

// Get overview statistics
$totalUsers = getTotalUsers();
$totalDrivers = getTotalDrivers();
$totalRides = getTotalRides();
$completedRides = getTotalRides('completed');
$cancelledRides = getTotalRides('cancelled');
$inProgressRides = getTotalRides('in_progress');

$totalRevenue = getTotalRevenue();
$revenueToday = getTotalRevenue('today');
$revenueWeek = getTotalRevenue('week');
$revenueMonth = getTotalRevenue('month');

$topDrivers = getTopDrivers(5);
$popularDestinations = getPopularDestinations(5);
$recentRides = getRecentRides(10);

$dailyRevenue = getDailyRevenue(7);
$ridesByStatus = getRidesByStatus();
$ridesByVehicleType = getRidesByVehicleType();

// Format arrays for Chart.js
$revenueLabels = array_map(function($item) {
    return date('D', strtotime($item['date']));
}, $dailyRevenue);

$revenueData = array_map(function($item) {
    return $item['revenue'];
}, $dailyRevenue);

$rideCountData = array_map(function($item) {
    return $item['rides'];
}, $dailyRevenue);

// Format ride status for chart
$statusLabels = [];
$statusData = [];
foreach ($ridesByStatus as $status) {
    $statusLabels[] = ucfirst($status['status']);
    $statusData[] = $status['count'];
}

// Format vehicle types for chart
$vehicleLabels = [];
$vehicleData = [];
foreach ($ridesByVehicleType as $type) {
    $vehicleLabels[] = ucfirst($type['vehicle_type']);
    $vehicleData[] = $type['count'];
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-white">Dashboard Overview</h1>
        <div>
            <span class="text-sm text-gray-400">Last Updated: <?php echo date('M j, Y g:i A'); ?></span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
            <div class="mt-2 text-xs text-gray-500">
                <a href="#" class="text-blue-400 hover:text-blue-300 transition-colors">View All Users →</a>
            </div>
        </div>
        
        <!-- Drivers Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Total Drivers</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($totalDrivers); ?></p>
                </div>
                <div class="p-2 bg-green-500/20 rounded-full">
                    <span class="lucide text-2xl text-green-400" aria-hidden="true">&#xebe4;</span>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                <a href="admin-drivers.php" class="text-green-400 hover:text-green-300 transition-colors">Manage Drivers →</a>
            </div>
        </div>
        
        <!-- Total Rides Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Total Rides</h3>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($totalRides); ?></p>
                </div>
                <div class="p-2 bg-purple-500/20 rounded-full">
                    <span class="lucide text-2xl text-purple-400" aria-hidden="true">&#xeb15;</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 text-xs">
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-green-500/20 text-green-400">
                    <?php echo number_format($completedRides); ?> Completed
                </span>
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-red-500/20 text-red-400">
                    <?php echo number_format($cancelledRides); ?> Cancelled
                </span>
                <span class="inline-flex items-center px-2 py-1 rounded-full bg-blue-500/20 text-blue-400">
                    <?php echo number_format($inProgressRides); ?> In Progress
                </span>
            </div>
        </div>
        
        <!-- Revenue Card -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md hover:shadow-lg transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-gray-400 text-sm font-medium">Total Revenue</h3>
                    <p class="text-2xl font-bold text-white"><?php echo formatCurrency($totalRevenue); ?></p>
                </div>
                <div class="p-2 bg-yellow-500/20 rounded-full">
                    <span class="lucide text-2xl text-yellow-400" aria-hidden="true">&#xec8f;</span>
                </div>
            </div>
            <div class="flex space-x-2 mt-2 text-xs text-gray-400">
                <span>Today: <span class="text-yellow-400"><?php echo formatCurrency($revenueToday); ?></span></span>
                <span>•</span>
                <span>This Week: <span class="text-yellow-400"><?php echo formatCurrency($revenueWeek); ?></span></span>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Revenue Chart -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Revenue & Rides (Last 7 Days)</h3>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        
        <!-- Ride Status Breakdown -->
        <div class="bg-gray-800 rounded-lg p-5 border border-gray-700 shadow-md">
            <h3 class="text-lg font-medium text-white mb-4">Ride Breakdown</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <h4 class="text-sm font-medium text-gray-400 mb-2">By Status</h4>
                    <canvas id="statusChart" class="h-48"></canvas>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-400 mb-2">By Vehicle Type</h4>
                    <canvas id="vehicleChart" class="h-48"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Driver & Destination Tables -->
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
                            <th class="py-3 text-right">Rides</th>
                            <th class="py-3 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($topDrivers)): ?>
                        <tr>
                            <td colspan="3" class="py-4 text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($topDrivers as $driver): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3 text-white font-medium">
                                    <?php echo htmlspecialchars($driver['name']); ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($driver['vehicle']); ?></div>
                                </td>
                                <td class="py-3 text-right"><?php echo number_format($driver['total_rides']); ?></td>
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
                            <th class="py-3 text-left">Destination</th>
                            <th class="py-3 text-right">Ride Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php if (empty($popularDestinations)): ?>
                        <tr>
                            <td colspan="2" class="py-4 text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($popularDestinations as $destination): ?>
                            <tr class="hover:bg-gray-700/50 transition-colors">
                                <td class="py-3 text-white"><?php echo htmlspecialchars($destination['dropoff']); ?></td>
                                <td class="py-3 text-right"><?php echo number_format($destination['count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Rides -->
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
                        <th class="py-3 text-left">ID</th>
                        <th class="py-3 text-left">User</th>
                        <th class="py-3 text-left">Driver</th>
                        <th class="py-3 text-left">Route</th>
                        <th class="py-3 text-right">Fare</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php if (empty($recentRides)): ?>
                    <tr>
                        <td colspan="7" class="py-4 text-center text-gray-500">No rides available</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($recentRides as $ride): ?>
                        <tr class="hover:bg-gray-700/50 transition-colors">
                            <td class="py-3">#<?php echo $ride['id']; ?></td>
                            <td class="py-3 text-white"><?php echo htmlspecialchars($ride['user_name'] ?? 'Unknown'); ?></td>
                            <td class="py-3"><?php echo htmlspecialchars($ride['driver_name'] ?? 'Unassigned'); ?></td>
                            <td class="py-3 text-xs">
                                <div class="text-gray-300"><?php echo htmlspecialchars(substr($ride['pickup'], 0, 20) . (strlen($ride['pickup']) > 20 ? '...' : '')); ?></div>
                                <div class="text-gray-500">→ <?php echo htmlspecialchars(substr($ride['dropoff'], 0, 20) . (strlen($ride['dropoff']) > 20 ? '...' : '')); ?></div>
                            </td>
                            <td class="py-3 text-right text-yellow-400"><?php echo formatCurrency($ride['fare']); ?></td>
                            <td class="py-3 text-center">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo getRideStatusColor($ride['status']); ?>">
                                    <?php echo ucfirst($ride['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 text-right text-xs text-gray-400"><?php echo date('M j, Y g:i A', strtotime($ride['created_at'])); ?></td>
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
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($revenueLabels); ?>,
            datasets: [
                {
                    label: 'Revenue (G$)',
                    data: <?php echo json_encode($revenueData); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Rides',
                    data: <?php echo json_encode($rideCountData); ?>,
                    type: 'line',
                    fill: false,
                    backgroundColor: 'rgba(79, 70, 229, 0.7)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (G$)',
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
                        text: 'Ride Count',
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
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($statusLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($statusData); ?>,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.7)',  // Completed - Green
                    'rgba(239, 68, 68, 0.7)',   // Cancelled - Red
                    'rgba(59, 130, 246, 0.7)',  // In Progress - Blue
                    'rgba(245, 158, 11, 0.7)',  // Searching - Yellow
                    'rgba(139, 92, 246, 0.7)',  // Others - Purple
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(139, 92, 246, 1)',
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
                        font: {
                            size: 10
                        },
                        boxWidth: 12
                    }
                }
            }
        }
    });
    
    // Vehicle Chart
    const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
    const vehicleChart = new Chart(vehicleCtx, {
        type: 'pie',
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
                        font: {
                            size: 10
                        },
                        boxWidth: 12
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
