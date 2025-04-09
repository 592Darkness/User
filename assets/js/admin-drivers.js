/**
 * Admin Drivers Page Script
 *
 * Handles fetching, displaying, adding, editing, and deleting drivers
 * via AJAX requests to process-admin-driver.php.
 * Includes robust error handling for fetch operations and authentication.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Element References ---
    const driverTableBody = document.getElementById('driverTableBody');
    const addDriverButton = document.getElementById('addDriverButton');
    const driverModalElement = document.getElementById('driverModal'); // The modal container
    const driverModal = driverModalElement ? new bootstrap.Modal(driverModalElement) : null; // Bootstrap modal instance
    const driverForm = document.getElementById('driverForm');
    const driverModalLabel = document.getElementById('driverModalLabel');
    const formErrorMessage = document.getElementById('formErrorMessage'); // Div to show errors in modal
    const loadingIndicator = document.getElementById('loadingIndicator'); // Optional loading indicator element

    // Hidden fields in the form modal
    const driverActionInput = document.getElementById('driverAction');
    const editDriverIdInput = document.getElementById('editDriverId');
    const passwordInput = document.getElementById('password'); // Specific handling for password

    // View Modal Elements (assuming IDs like viewDriverName, viewDriverEmail etc.)
    const viewModalElement = document.getElementById('viewDriverModal');
    const viewModal = viewModalElement ? new bootstrap.Modal(viewModalElement) : null;


    // --- Utility Functions ---

    /**
     * Shows a loading indicator.
     */
    function showLoading() {
        if (loadingIndicator) loadingIndicator.style.display = 'block';
        // Optionally disable buttons during loading
        document.querySelectorAll('button, input[type="submit"]').forEach(el => el.disabled = true);
         console.log("Loading...");
    }

    /**
     * Hides the loading indicator.
     */
    function hideLoading() {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
         // Re-enable buttons
        document.querySelectorAll('button, input[type="submit"]').forEach(el => el.disabled = false);
         console.log("Loading complete.");
    }

    /**
     * Displays an error message, potentially in the modal form.
     * @param {string} message - The error message to display.
     * @param {boolean} isModalError - True if the error should be shown in the modal's error div.
     */
    function displayError(message, isModalError = false) {
        console.error("Error:", message);
        if (isModalError && formErrorMessage) {
            formErrorMessage.textContent = message;
            formErrorMessage.style.display = 'block';
        } else {
            // Use a more general notification system if available, otherwise alert.
            alert(`Error: ${message}`);
        }
    }

    /**
     * Clears the error message display in the modal form.
     */
    function clearModalError() {
        if (formErrorMessage) {
            formErrorMessage.textContent = '';
            formErrorMessage.style.display = 'none';
        }
    }

    /**
     * Escapes HTML special characters to prevent XSS.
     * @param {*} unsafe - The value to escape.
     * @returns {string} - The escaped string.
     */
    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') {
            return '';
        }
        return unsafe
             .toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    // --- Core Fetch Handling ---

    /**
     * Handles fetch responses, checks status codes, content type, and parses JSON.
     * Redirects to login on authentication failure (401/403).
     * @param {Response} response - The Fetch API Response object.
     * @returns {Promise<object>} - A promise that resolves with the parsed JSON data.
     * @throws {Error} - Throws an error for non-OK status, wrong content type, or auth failure.
     */
    async function handleFetchResponse(response) {
        // Check for authentication errors first (401 Unauthorized, 403 Forbidden)
        if (response.status === 401 || response.status === 403) {
            console.error('Authentication error:', response.status);
            let errorMessage = 'Authentication required. Please log in again.';
            try {
                // Try to parse JSON error message from the server response body
                const errorData = await response.json();
                errorMessage = errorData?.message || errorMessage;
            } catch (e) {
                console.warn('Could not parse JSON from auth error response.');
                // Fallback to default message if JSON parsing fails
            }
            alert(errorMessage); // Inform the user
            window.location.href = 'admin-login.php'; // Redirect to login page
            // Throw an error to stop the current promise chain
            throw new Error('Authentication Failed');
        }

        // Check if the response status is OK (e.g., 200, 201)
        if (!response.ok) {
            console.error('Fetch error:', response.status, response.statusText);
            let errorMessage = `HTTP error! Status: ${response.status}`;
            try {
                 // Try to parse JSON error message from the server response body
                const errorData = await response.json();
                errorMessage = errorData?.message || errorMessage;
            } catch (e) {
                 console.warn('Could not parse JSON from error response.');
                 // Fallback to status text if JSON parsing fails
                 errorMessage = `HTTP error ${response.status}: ${response.statusText}`;
            }
             // Throw an error with the extracted or generated message
            throw new Error(errorMessage);
        }

        // Check if the Content-Type header indicates JSON for successful responses
        const contentType = response.headers.get('Content-Type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Response received was not JSON. Content-Type:', contentType);
            // Log the response text for debugging if it's not JSON
            const responseText = await response.text();
            console.log("Non-JSON Response Body:", responseText);
            throw new Error(`Unexpected response format from server: ${contentType || 'Unknown'}`);
        }

        // If all checks pass, parse and return the JSON body
        return response.json();
    }

    /**
     * Centralized fetch function with loading indicators and error handling.
     * @param {string} url - The URL to fetch.
     * @param {object} options - Fetch options (method, body, etc.).
     * @param {string} context - Description of the action (e.g., 'fetching drivers').
     * @param {boolean} isModalAction - Is this action initiated from the modal form?
     * @returns {Promise<object|null>} - Resolves with data on success, null on failure.
     */
    async function performFetch(url, options, context, isModalAction = false) {
        showLoading();
        clearModalError(); // Clear previous modal errors if applicable
        try {
            const response = await fetch(url, options);
            const data = await handleFetchResponse(response); // Handles status, content-type, auth errors

            if (!data.success) {
                 // If the server explicitly indicates failure in the JSON payload
                throw new Error(data.message || `Failed to ${context}.`);
            }
            // Action succeeded
            console.log(`Successfully completed: ${context}`);
            return data; // Return the successful data payload

        } catch (error) {
            // Catch errors from fetch(), handleFetchResponse(), or data.success check
            // Authentication errors are handled inside handleFetchResponse by redirecting
            if (error.message !== 'Authentication Failed') {
                 displayError(error.message || `An unknown error occurred during ${context}.`, isModalAction);
            }
            return null; // Indicate failure
        } finally {
            hideLoading();
        }
    }


    // --- Driver Data Management ---

    /**
     * Fetches all drivers and populates the table.
     */
    async function fetchDriverData() {
        const data = await performFetch(
            'process-admin-driver.php',
            { method: 'POST', body: new URLSearchParams({ action: 'fetch' }) }, // Use URLSearchParams for simple key-value pairs
            'fetching drivers'
        );

        if (data && data.drivers) {
            populateDriverTable(data.drivers);
        } else {
             // Clear table or show error row if fetch failed after initial load
             if(driverTableBody) driverTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load drivers.</td></tr>'; // Adjust colspan
        }
    }

    /**
     * Populates the driver table with data.
     * @param {Array<object>} drivers - Array of driver objects.
     */
    function populateDriverTable(drivers) {
        if (!driverTableBody) {
            console.error("Element with ID 'driverTableBody' not found.");
            return;
        }
        driverTableBody.innerHTML = ''; // Clear existing rows

        if (!drivers || drivers.length === 0) {
            driverTableBody.innerHTML = '<tr><td colspan="6" class="text-center">No drivers found.</td></tr>'; // Adjust colspan
            return;
        }

        drivers.forEach(driver => {
            const row = driverTableBody.insertRow();
            // Ensure all expected properties exist, provide defaults if necessary
            const id = driver.driver_id ?? 'N/A';
            const name = driver.name ?? 'N/A';
            const email = driver.email ?? 'N/A';
            const phone = driver.phone ?? 'N/A';
            const status = driver.status ?? 'unknown';
            const statusClass = status === 'active' ? 'success' : (status === 'inactive' ? 'secondary' : 'warning');

            row.innerHTML = `
                <td>${escapeHtml(id)}</td>
                <td>${escapeHtml(name)}</td>
                <td>${escapeHtml(email)}</td>
                <td>${escapeHtml(phone)}</td>
                <td><span class="badge bg-${statusClass}">${escapeHtml(status)}</span></td>
                <td>
                    <button class="btn btn-sm btn-info view-btn" data-id="${id}" title="View Details">
                        <i class="fas fa-eye"></i> <span class="d-none d-md-inline">View</span>
                    </button>
                    <button class="btn btn-sm btn-warning edit-btn" data-id="${id}" title="Edit Driver">
                         <i class="fas fa-edit"></i> <span class="d-none d-md-inline">Edit</span>
                    </button>
                    <button class="btn btn-sm btn-danger delete-btn" data-id="${id}" title="Delete Driver">
                         <i class="fas fa-trash"></i> <span class="d-none d-md-inline">Delete</span>
                    </button>
                </td>
            `;
        });

        attachActionListeners(); // Re-attach listeners to the new buttons
    }

    /**
     * Attaches event listeners to action buttons in the table.
     */
    function attachActionListeners() {
        driverTableBody.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', handleViewClick);
        });
        driverTableBody.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', handleEditClick);
        });
        driverTableBody.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', handleDeleteClick);
        });
    }

    // --- Event Handlers for Table Actions ---

    /**
     * Handles click on the 'View' button. Fetches details and shows view modal.
     * @param {Event} event - The click event.
     */
    async function handleViewClick(event) {
        const driverId = event.currentTarget.getAttribute('data-id');
        if (!driverId || !viewModal) return;

         const data = await performFetch(
            'process-admin-driver.php',
            { method: 'POST', body: new URLSearchParams({ action: 'view', driver_id: driverId }) },
            `viewing driver ${driverId}`
        );

        if (data && data.driver) {
            populateViewModal(data.driver);
            viewModal.show();
        }
        // Error handling is done within performFetch
    }

     /**
     * Handles click on the 'Edit' button. Fetches details and shows edit modal.
     * @param {Event} event - The click event.
     */
    async function handleEditClick(event) {
        const driverId = event.currentTarget.getAttribute('data-id');
         if (!driverId || !driverModal) return;

         const data = await performFetch(
            'process-admin-driver.php',
            { method: 'POST', body: new URLSearchParams({ action: 'view', driver_id: driverId }) }, // Use 'view' to get full data first
            `loading edit form for driver ${driverId}`
        );

        if (data && data.driver) {
            setupEditModal(data.driver);
            driverModal.show();
        }
         // Error handling is done within performFetch
    }

    /**
     * Handles click on the 'Delete' button. Confirms and performs deletion.
     * @param {Event} event - The click event.
     */
    async function handleDeleteClick(event) {
        const driverId = event.currentTarget.getAttribute('data-id');
        if (!driverId) return;

        if (confirm(`Are you sure you want to delete driver ID ${driverId}? This action cannot be undone.`)) {
             const data = await performFetch(
                'process-admin-driver.php',
                { method: 'POST', body: new URLSearchParams({ action: 'delete', driver_id: driverId }) },
                `deleting driver ${driverId}`
            );

            if (data) { // Check if fetch was successful (performFetch returns data on success)
                alert(data.message || 'Driver deleted successfully.');
                fetchDriverData(); // Refresh the table
            }
             // Error handling is done within performFetch
        }
    }

    // --- Modal Setup and Handling ---

    /**
     * Populates the View modal with driver details.
     * @param {object} driver - The driver data object.
     */
    function populateViewModal(driver) {
        // Helper to set text content safely
        const setText = (id, value) => {
            const el = viewModalElement.querySelector(`#${id}`);
            if (el) el.textContent = value || 'N/A';
        };

        setText('viewDriverName', driver.name);
        setText('viewDriverEmail', driver.email);
        setText('viewDriverPhone', driver.phone);
        setText('viewDriverLicense', driver.license_number);
        setText('viewDriverStatus', driver.status);
        setText('viewVehicleMake', driver.vehicle_make);
        setText('viewVehicleModel', driver.vehicle_model);
        setText('viewVehicleYear', driver.vehicle_year);
        setText('viewVehicleColor', driver.vehicle_color);
        setText('viewVehicleLicensePlate', driver.vehicle_license_plate);
        setText('viewRegistrationDate', driver.registration_date ? new Date(driver.registration_date).toLocaleDateString() : 'N/A');
        // Add other fields as needed
    }


    /**
     * Sets up the Add/Edit modal for adding a new driver.
     */
    function setupAddModal() {
        if (!driverForm || !driverActionInput || !editDriverIdInput || !driverModalLabel) return;
        driverForm.reset(); // Clear all form fields
        driverActionInput.value = 'add';
        editDriverIdInput.value = ''; // No ID for add
        driverModalLabel.textContent = 'Add New Driver';
        clearModalError();

        // Handle password field for 'add' - make it required
        if (passwordInput) {
            passwordInput.placeholder = 'Enter password (required)';
            passwordInput.required = true;
        }
    }

     /**
     * Sets up the Add/Edit modal for editing an existing driver.
     * @param {object} driver - The driver data object.
     */
    function setupEditModal(driver) {
         if (!driverForm || !driverActionInput || !editDriverIdInput || !driverModalLabel) return;
        driverForm.reset(); // Start clean
        driverActionInput.value = 'update';
        editDriverIdInput.value = driver.driver_id; // Set the ID for update
        driverModalLabel.textContent = `Edit Driver (ID: ${driver.driver_id})`;
        clearModalError();

        // Populate form fields from driver data
        // Helper to set input value safely
        const setValue = (id, value) => {
            const el = driverForm.querySelector(`#${id}`);
            if (el) el.value = value ?? ''; // Use empty string for null/undefined
        };

        setValue('name', driver.name);
        setValue('email', driver.email);
        setValue('phone', driver.phone);
        setValue('license_number', driver.license_number);
        setValue('status', driver.status);
        setValue('vehicle_make', driver.vehicle_make);
        setValue('vehicle_model', driver.vehicle_model);
        setValue('vehicle_year', driver.vehicle_year);
        setValue('vehicle_color', driver.vehicle_color);
        setValue('vehicle_license_plate', driver.vehicle_license_plate);

         // Handle password field for 'edit' - make it optional
        if (passwordInput) {
            passwordInput.value = ''; // Clear it
            passwordInput.placeholder = 'Leave blank to keep current password';
            passwordInput.required = false; // Not required for update
        }
    }

    // --- Event Listeners Setup ---

    // Listener for the main "Add New Driver" button
    if (addDriverButton && driverModal) {
        addDriverButton.addEventListener('click', () => {
            setupAddModal();
            driverModal.show();
        });
    } else {
         if (!addDriverButton) console.error("Add Driver button not found.");
         if (!driverModal) console.error("Driver Modal (Bootstrap Instance) not initialized.");
    }

    // Listener for the Add/Edit form submission
    if (driverForm && driverModal) {
        driverForm.addEventListener('submit', async (event) => {
            event.preventDefault(); // Prevent default form submission
            clearModalError(); // Clear previous errors

            const action = driverActionInput.value; // 'add' or 'update'
            const context = action === 'add' ? 'adding new driver' : 'updating driver';

            const formData = new FormData(driverForm); // Collect form data

             // Perform the fetch action (add or update)
             const data = await performFetch(
                'process-admin-driver.php',
                { method: 'POST', body: formData },
                context,
                true // Indicate this is a modal action for error display
            );

            if (data) { // Check if fetch was successful
                alert(data.message || 'Operation successful.');
                driverModal.hide(); // Close the modal on success
                fetchDriverData(); // Refresh the table
            }
            // If fetch failed, performFetch already displayed the error in the modal
        });
    } else {
         if (!driverForm) console.error("Driver Form not found.");
         if (!driverModal) console.error("Driver Modal (Bootstrap Instance) not initialized.");
    }


    // --- Initial Load ---
    fetchDriverData(); // Fetch initial driver list when the page loads

}); // End DOMContentLoaded

document.addEventListener('DOMContentLoaded', function() {
    console.log('Driver Event Handler Fix Loaded');
    
    // Fix for edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Edit button clicked, driver ID:', this.dataset.id);
            const driverId = this.dataset.id;
            
            // Show loading indicator if available
            if (typeof showLoadingIndicator === 'function') {
                showLoadingIndicator();
            }
            
            // Get CSRF token
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            
            // Make the API call
            fetch('process-admin-driver.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'get_driver',
                    driver_id: driverId,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                if (data.success && data.driver) {
                    // Populate the edit form
                    const form = document.getElementById('edit-driver-form');
                    if (form) {
                        form.querySelector('#edit_driver_id').value = data.driver.id;
                        form.querySelector('#edit_name').value = data.driver.name || '';
                        form.querySelector('#edit_email').value = data.driver.email || '';
                        form.querySelector('#edit_phone').value = data.driver.phone || '';
                        form.querySelector('#edit_vehicle').value = data.driver.vehicle || '';
                        form.querySelector('#edit_plate').value = data.driver.plate || '';
                        form.querySelector('#edit_vehicle_type').value = data.driver.vehicle_type || 'standard';
                        form.querySelector('#edit_status').value = data.driver.status || 'offline';
                        form.querySelector('#edit_password').value = ''; // Always clear password
                    }
                    
                    // Show the modal
                    const modal = document.getElementById('edit-driver-modal');
                    if (modal) {
                        modal.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }
                } else {
                    // Show error message
                    alert(data.message || 'Error fetching driver data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                // Show error message
                alert('Error connecting to server. Please try again.');
            });
        });
    });
    
    // Fix for view buttons
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('View button clicked, driver ID:', this.dataset.id);
            const driverId = this.dataset.id;
            
            // Show loading indicator if available
            if (typeof showLoadingIndicator === 'function') {
                showLoadingIndicator();
            }
            
            // Get CSRF token
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            
            // Make the API call
            fetch('process-admin-driver.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'view',
                    driver_id: driverId,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                if (data.success && data.driver) {
                    // Populate the view details
                    const contentDiv = document.getElementById('driver-details-content');
                    if (contentDiv) {
                        contentDiv.innerHTML = `
                            <div class="bg-gray-700/30 rounded-lg p-4 mb-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-xl font-medium text-white">${data.driver.name || 'N/A'}</h3>
                                        <p class="text-sm text-gray-400">ID: #${data.driver.id}</p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                ${(data.driver.status === 'available') ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}">
                                        ${data.driver.status ? data.driver.status.charAt(0).toUpperCase() + data.driver.status.slice(1) : 'Offline'}
                                    </span>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-400 mb-1">Contact</h4>
                                    <div class="bg-gray-700/30 rounded-lg p-3 space-y-1">
                                        <p class="text-white flex items-center"><span class="lucide mr-2 text-gray-400 text-sm">&#xea1c;</span> ${data.driver.email || 'N/A'}</p>
                                        <p class="text-white flex items-center"><span class="lucide mr-2 text-gray-400 text-sm">&#xea9d;</span> ${data.driver.phone || 'N/A'}</p>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-400 mb-1">Vehicle</h4>
                                    <div class="bg-gray-700/30 rounded-lg p-3 space-y-1">
                                        <p class="text-white flex items-center"><span class="lucide mr-2 text-gray-400 text-sm">&#xeb15;</span> ${data.driver.vehicle || 'N/A'} (${data.driver.vehicle_type || 'N/A'})</p>
                                        <p class="text-white flex items-center"><span class="lucide mr-2 text-gray-400 text-sm">&#xea6d;</span> Plate: ${data.driver.plate || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-400 mb-1">Stats</h4>
                                    <div class="bg-gray-700/30 rounded-lg p-3 grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-xs text-gray-500">Total Rides</p>
                                            <p class="text-gray-300">${data.driver.total_rides || 0}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Avg Rating</p>
                                            <p class="text-gray-300">${data.driver.avg_rating ? Number(data.driver.avg_rating).toFixed(1) : 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-400 mb-1">Account</h4>
                                    <div class="bg-gray-700/30 rounded-lg p-3 grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-xs text-gray-500">Created</p>
                                            <p class="text-gray-300">${data.driver.created_at ? new Date(data.driver.created_at).toLocaleDateString() : 'N/A'}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Last Login</p>
                                            <p class="text-gray-300">${data.driver.last_login ? new Date(data.driver.last_login).toLocaleDateString() : 'Never'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    // Show the modal
                    const modal = document.getElementById('view-driver-modal');
                    if (modal) {
                        modal.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }
                } else {
                    // Show error message
                    alert(data.message || 'Error fetching driver data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                // Show error message
                alert('Error connecting to server. Please try again.');
            });
        });
    });
    
    // Fix for delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Delete button clicked, driver ID:', this.dataset.id);
            
            const driverId = this.dataset.id;
            const driverName = this.dataset.name || 'this driver';
            
            if (confirm(`Are you sure you want to delete ${driverName}? This cannot be undone.`)) {
                // Show loading indicator if available
                if (typeof showLoadingIndicator === 'function') {
                    showLoadingIndicator();
                }
                
                // Get CSRF token
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
                
                // Make the API call
                fetch('process-admin-driver.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        driver_id: driverId,
                        csrf_token: csrfToken
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide loading indicator if available
                    if (typeof hideLoadingIndicator === 'function') {
                        hideLoadingIndicator();
                    }
                    
                    if (data.success) {
                        alert(data.message || 'Driver deleted successfully');
                        // Reload the page to show updated list
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error deleting driver');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Hide loading indicator if available
                    if (typeof hideLoadingIndicator === 'function') {
                        hideLoadingIndicator();
                    }
                    
                    // Show error message
                    alert('Error connecting to server. Please try again.');
                });
            }
        });
    });
    
    // Fix form submissions
    const addDriverForm = document.getElementById('add-driver-form');
    if (addDriverForm) {
        addDriverForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Add driver form submitted');
            
            // Show loading indicator if available
            if (typeof showLoadingIndicator === 'function') {
                showLoadingIndicator();
            }
            
            // Build form data as an object
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            data.action = 'add';
            
            // Make the API call
            fetch('process-admin-driver.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                if (data.success) {
                    alert(data.message || 'Driver added successfully');
                    // Close the modal
                    const modal = document.getElementById('add-driver-modal');
                    if (modal) {
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                    // Reload the page to show updated list
                    window.location.reload();
                } else {
                    alert(data.message || 'Error adding driver');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                // Show error message
                alert('Error connecting to server. Please try again.');
            });
        });
    }
    
    const editDriverForm = document.getElementById('edit-driver-form');
    if (editDriverForm) {
        editDriverForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Edit driver form submitted');
            
            // Show loading indicator if available
            if (typeof showLoadingIndicator === 'function') {
                showLoadingIndicator();
            }
            
            // Build form data as an object
            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            data.action = 'update';
            
            // Make the API call
            fetch('process-admin-driver.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                if (data.success) {
                    alert(data.message || 'Driver updated successfully');
                    // Close the modal
                    const modal = document.getElementById('edit-driver-modal');
                    if (modal) {
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                    // Reload the page to show updated list
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating driver');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Hide loading indicator if available
                if (typeof hideLoadingIndicator === 'function') {
                    hideLoadingIndicator();
                }
                
                // Show error message
                alert('Error connecting to server. Please try again.');
            });
        });
    }
});