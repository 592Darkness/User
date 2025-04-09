document.addEventListener('DOMContentLoaded', function() {
    console.log("Direct inline JavaScript fix loaded");
    
    // Directly fix view button clicks
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const driverId = this.getAttribute('data-id');
            console.log("View button clicked for driver ID:", driverId);
            
            // Very simple and direct AJAX call with explicit headers
            fetch('process-admin-driver-debug.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'get_driver',
                    driver_id: driverId,
                    csrf_token: document.querySelector('input[name="csrf_token"]')?.value || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log("Driver data received:", data);
                if (data.success) {
                    // Populate view modal
                    const modal = document.getElementById('view-driver-modal');
                    const content = document.getElementById('driver-details-content');
                    
                    if (content) {
                        content.innerHTML = `
                            <div class="p-4 bg-gray-700/30 rounded-lg">
                                <h3 class="text-xl font-medium text-white">${data.driver.name}</h3>
                                <p class="text-gray-400">ID: ${data.driver.id}</p>
                                <p class="mt-2 text-white">Email: ${data.driver.email}</p>
                                <p class="text-white">Phone: ${data.driver.phone}</p>
                                <p class="text-white">Vehicle: ${data.driver.vehicle} (${data.driver.plate})</p>
                                <p class="text-white">Status: ${data.driver.status}</p>
                            </div>
                        `;
                    }
                    
                    if (modal) {
                        modal.style.display = 'flex';
                    }
                } else {
                    alert(data.message || "Error loading driver data");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Connection error. Please try again.");
            });
        });
    });
    
    // Similarly fix edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const driverId = this.getAttribute('data-id');
            console.log("Edit button clicked for driver ID:", driverId);
            
            fetch('process-admin-driver-debug.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'get_driver',
                    driver_id: driverId,
                    csrf_token: document.querySelector('input[name="csrf_token"]')?.value || ''
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log("Driver data received for edit:", data);
                if (data.success) {
                    // Populate edit form
                    const form = document.getElementById('edit-driver-form');
                    if (form) {
                        form.querySelector('#edit_driver_id').value = data.driver.id;
                        form.querySelector('#edit_name').value = data.driver.name;
                        form.querySelector('#edit_email').value = data.driver.email;
                        form.querySelector('#edit_phone').value = data.driver.phone;
                        form.querySelector('#edit_vehicle').value = data.driver.vehicle;
                        form.querySelector('#edit_plate').value = data.driver.plate;
                        form.querySelector('#edit_vehicle_type').value = data.driver.vehicle_type;
                        form.querySelector('#edit_status').value = data.driver.status;
                        
                        // Clear password field
                        form.querySelector('#edit_password').value = '';
                    }
                    
                    // Show modal
                    const modal = document.getElementById('edit-driver-modal');
                    if (modal) {
                        modal.style.display = 'flex';
                    }
                } else {
                    alert(data.message || "Error loading driver data");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Connection error. Please try again.");
            });
        });
    });
});