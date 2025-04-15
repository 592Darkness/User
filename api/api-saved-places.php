<?php
/**
 * API Endpoint for Saved Places Management
 * FIXED: Added POST method handling for adding places.
 * Handles GET (fetch all) and POST (add new).
 */

// Enhanced error handling & reporting (Turn off display_errors in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // SET TO 0 IN PRODUCTION
ini_set('log_errors', 1);
// ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log'); // Optional: Define log file

// Always set JSON header
header('Content-Type: application/json');

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null,
    'error' => false
];

$conn = null; // Initialize connection variable

try {
    // Explicitly require config and functions
    require_once dirname(__DIR__) . '/includes/config.php';
    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/db.php';

    // --- Authentication Check ---
    // Ensure user is logged in via session
    if (!isLoggedIn()) {
        http_response_code(401); // Unauthorized
        throw new Exception('Authentication required. Please log in.');
    }
    $userId = $_SESSION['user_id']; // Get logged-in user's ID

    // --- Request Handling ---
    $method = $_SERVER['REQUEST_METHOD'];
    // Check endpoint if needed, though for simple CRUD, method is often enough
    // $endpoint = isset($_GET['endpoint']) ? sanitize($_GET['endpoint']) : '';

    error_log("Saved Places API: Method=$method, UserID=$userId");

    switch ($method) {
        case 'GET':
            // --- Fetch All Saved Places for the User ---
            error_log("Handling GET request for saved places.");
            try {
                $conn = dbConnect();
                // Ensure table exists (optional check, better in migrations)
                // $conn->query("CREATE TABLE IF NOT EXISTS saved_places (...)");

                $stmt = $conn->prepare("SELECT id, name, address, created_at FROM saved_places WHERE user_id = ? ORDER BY name ASC");
                if (!$stmt) throw new Exception("DB Prepare Error (GET): " . $conn->error);

                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $places = [];
                while ($place = $result->fetch_assoc()) {
                    $places[] = $place;
                }
                $stmt->close();
                $conn->close();
                $conn = null; // Mark as closed

                $response['success'] = true;
                $response['message'] = 'Saved places retrieved successfully.';
                $response['data']['places'] = $places; // Nest places under 'data' key

            } catch (Exception $e) {
                error_log("Error retrieving saved places: " . $e->getMessage());
                throw new Exception("Error retrieving saved places."); // Re-throw for main catch block
            }
            break; // End GET case

        case 'POST':
            // --- Add a New Saved Place ---
            error_log("Handling POST request to add saved place.");
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                http_response_code(400); // Bad Request
                throw new Exception('Invalid input data. Ensure JSON is correct.');
            }

            // Extract and sanitize data
            $name = isset($data['name']) ? sanitize($data['name']) : '';
            $address = isset($data['address']) ? sanitize($data['address']) : '';
            // Optional: CSRF check (if sending token in JSON body)
            // $csrfToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';
            // if (!verifyCSRFToken($csrfToken)) { http_response_code(403); throw new Exception('Invalid security token.'); }

            // Validate input
            if (empty($name) || empty($address)) {
                http_response_code(400); // Bad Request
                throw new Exception('Place name and address are required.');
            }

            // Insert into database
            try {
                $conn = dbConnect();
                // Optional: Ensure table exists
                // $conn->query("CREATE TABLE IF NOT EXISTS saved_places (...)");

                $stmt = $conn->prepare("INSERT INTO saved_places (user_id, name, address, created_at) VALUES (?, ?, ?, NOW())");
                if (!$stmt) throw new Exception("DB Prepare Error (POST): " . $conn->error);

                $stmt->bind_param("iss", $userId, $name, $address); // 'i' for user_id, 's' for name, 's' for address

                if ($stmt->execute()) {
                    $newPlaceId = $conn->insert_id;
                    $response['success'] = true;
                    $response['message'] = 'Place added successfully!';
                    $response['data']['new_place_id'] = $newPlaceId; // Include new ID in response
                    http_response_code(201); // HTTP 201 Created
                    error_log("Saved place added successfully. ID: $newPlaceId for User: $userId");
                } else {
                    // Check for specific DB errors, e.g., duplicate entry if name+user_id should be unique
                    throw new Exception('Database error saving place: ' . $stmt->error);
                }
                $stmt->close();
                $conn->close();
                $conn = null; // Mark as closed
            } catch (Exception $e) {
                 error_log("Error adding saved place to DB: " . $e->getMessage());
                 throw new Exception("Could not save the place due to a database error."); // Re-throw
            }
            break; // End POST case

        // --- START IMPLEMENTED PUT CASE ---
        case 'PUT':
            error_log("Handling PUT request to update saved place.");
            // Get JSON data from request body
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                http_response_code(400);
                throw new Exception('Invalid input data. Ensure JSON is correct.');
            }

            // Extract and sanitize data
            $placeIdToUpdate = isset($data['id']) ? (int)$data['id'] : 0;
            $name = isset($data['name']) ? sanitize($data['name']) : '';
            $address = isset($data['address']) ? sanitize($data['address']) : '';
            // Optional: CSRF check
            // $csrfToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';
            // if (!verifyCSRFToken($csrfToken)) { http_response_code(403); throw new Exception('Invalid security token.'); }

            // Validate input
            if ($placeIdToUpdate <= 0) {
                http_response_code(400);
                throw new Exception('Valid Place ID is required for update.');
            }
            if (empty($name) || empty($address)) {
                http_response_code(400);
                throw new Exception('Place name and address cannot be empty.');
            }

            // Update in database
            try {
                $conn = dbConnect();
                // Prepare UPDATE statement, ensuring user owns the place
                $stmt = $conn->prepare("UPDATE saved_places SET name = ?, address = ? WHERE id = ? AND user_id = ?");
                if (!$stmt) throw new Exception("DB Prepare Error (PUT): " . $conn->error);

                $stmt->bind_param("ssii", $name, $address, $placeIdToUpdate, $userId); // Bind name, address, id, user_id

                if ($stmt->execute()) {
                    // Check if a row was actually updated
                    if ($stmt->affected_rows > 0) {
                        $response['success'] = true;
                        $response['message'] = 'Place updated successfully.';
                        error_log("Updated place ID: $placeIdToUpdate for User: $userId");
                    } else {
                        // No rows updated - could be same data, or ID not found/not owned by user
                        // Check if the place actually exists for this user with potentially unchanged data
                        $checkStmt = $conn->prepare("SELECT id FROM saved_places WHERE id = ? AND user_id = ?");
                        if($checkStmt) {
                             $checkStmt->bind_param("ii", $placeIdToUpdate, $userId);
                             $checkStmt->execute();
                             $checkResult = $checkStmt->get_result();
                             if ($checkResult->num_rows > 0) {
                                 // Data was the same, but operation technically succeeded
                                 $response['success'] = true;
                                 $response['message'] = 'Place details unchanged.';
                                 error_log("Place details unchanged for ID: $placeIdToUpdate, User: $userId");
                             } else {
                                 // Place not found for this user
                                 http_response_code(404); // Not Found
                                 throw new Exception('Place not found or you do not have permission to update it.');
                             }
                             $checkStmt->close();
                        } else {
                             // If check fails, assume not found
                             http_response_code(404);
                             throw new Exception('Place not found or you do not have permission to update it.');
                        }
                    }
                } else {
                    // Database execution error
                    throw new Exception('Database error during update: ' . $stmt->error);
                }
                $stmt->close();
                $conn->close();
                $conn = null;
            } catch (Exception $e) {
                 error_log("Error updating saved place in DB: " . $e->getMessage());
                 throw new Exception("Could not update the place due to a server error."); // Re-throw
            }
            break; // End PUT case
        // --- END IMPLEMENTED PUT CASE ---

        case 'DELETE':
            error_log("Handling DELETE request to remove saved place.");

            // Get place ID from query string parameter (as used in dashboard.js)
            $placeIdToDelete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

            // Validate ID
            if ($placeIdToDelete <= 0) {
                http_response_code(400); // Bad Request
                throw new Exception('Missing or invalid place ID for deletion.');
            }

            // Optional: CSRF Check (If you implement CSRF validation for DELETE)
            // $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
            // if (!verifyCSRFToken($csrfToken)) { http_response_code(403); throw new Exception('Invalid security token.'); }

            // Delete from database
            try {
                $conn = dbConnect();
                // Prepare DELETE statement, ensuring the place belongs to the logged-in user
                $stmt = $conn->prepare("DELETE FROM saved_places WHERE id = ? AND user_id = ?");
                if (!$stmt) {
                    // Log detailed error if prepare fails
                    error_log("DB Prepare Error (DELETE): " . $conn->error);
                    throw new Exception("Database error: Could not prepare statement.");
                }

                $stmt->bind_param("ii", $placeIdToDelete, $userId); // Bind place ID and user ID

                if ($stmt->execute()) {
                    // Check if a row was actually deleted
                    if ($stmt->affected_rows > 0) {
                        $response['success'] = true;
                        $response['message'] = 'Place deleted successfully.';
                        error_log("Deleted place ID: $placeIdToDelete for User: $userId");
                        // HTTP 200 OK is fine, or 204 No Content if no response body is needed
                    } else {
                        // No rows deleted - either ID didn't exist or didn't belong to user
                        http_response_code(404); // Not Found (or 403 Forbidden)
                        throw new Exception('Place not found or you do not have permission to delete it.');
                    }
                } else {
                    // Database execution error
                    error_log("DB Execute Error (DELETE): " . $stmt->error);
                    throw new Exception('Database error occurred during deletion.');
                }
                $stmt->close();
                $conn->close();
                $conn = null; // Mark as closed
            } catch (Exception $e) {
                 error_log("Error deleting saved place from DB: " . $e->getMessage());
                 // Re-throw to be caught by the main handler, likely resulting in 500
                 throw new Exception("Could not delete the place due to a server error.");
            }
            break; 
    }

} catch (Exception $e) {
    // --- Global Error Handler ---
    error_log("API Saved Places Exception: " . $e->getMessage());

    // Set error response details
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['error'] = true;

    // Set appropriate HTTP status code if not already set
    if (http_response_code() === 200 || http_response_code() === 201) {
        // Default to 500 for unexpected server errors, unless it was an auth/input error
        http_response_code(500);
    }
}

// --- Cleanup and Output ---
// Ensure connection is closed if an exception occurred after it was opened
if (isset($conn) && $conn && $conn->ping()) {
    $conn->close();
}

// Send the final JSON response
echo json_encode($response);
exit;
?>