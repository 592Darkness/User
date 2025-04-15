/**
 * Fix for Dashboard Chart Heights
 * 
 * Save this file as "assets/js/dashboard-chart-fix.js" and include it at the bottom of admin-dashboard.php
 * Or add this code directly at the bottom of admin-dashboard.php just before the closing </script> tag
 */

document.addEventListener('DOMContentLoaded', function() {
    // Fix for chart containers
    const fixChartContainers = () => {
        // 1. Fix Revenue & Rides chart
        const revenueChartContainer = document.querySelector('#revenueChart').parentElement;
        if (revenueChartContainer) {
            revenueChartContainer.style.height = '300px';
            revenueChartContainer.style.maxHeight = '300px';
            revenueChartContainer.style.minHeight = '300px';
        }
        
        // 2. Fix status chart
        const statusChartContainer = document.querySelector('#statusChart').parentElement;
        if (statusChartContainer) {
            statusChartContainer.style.height = '200px';
            statusChartContainer.style.maxHeight = '200px';
        }
        
        // 3. Fix vehicle chart
        const vehicleChartContainer = document.querySelector('#vehicleChart').parentElement;
        if (vehicleChartContainer) {
            vehicleChartContainer.style.height = '200px';
            vehicleChartContainer.style.maxHeight = '200px';
        }
    };
    
    // Call immediately
    fixChartContainers();
    
    // Also call after a slight delay (helps with render timing issues)
    setTimeout(fixChartContainers, 100);
    
    // Fix chart initialization if they exist
    if (typeof Chart !== 'undefined') {
        // Override default Chart.js options
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.responsive = true;
        
        // If charts are already initialized, we can resize them
        if (window.revenueChart) {
            window.revenueChart.resize();
        }
        if (window.statusChart) {
            window.statusChart.resize();
        }
        if (window.vehicleChart) {
            window.vehicleChart.resize();
        }
    }
});
