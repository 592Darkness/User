<?php
/**
 * Database functions for Salaam Rides
 * Enhanced with better error handling and charset support
 */

/**
 * Establish a connection to the database
 * 
 * @return mysqli Database connection
 * @throws Exception If connection fails
 */
function dbConnect() {
    // Capture connection errors for better debugging
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    // Attempt to connect to the database with better error handling
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection for errors
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set character set to ensure proper encoding
        if (!$conn->set_charset("utf8mb4")) {
            error_log("Error setting character set: " . $conn->error);
        }
        
        return $conn;
        
    } catch (Exception $e) {
        error_log("Database connection exception: " . $e->getMessage());
        
        // Attempt to return a fallback connection without strict error reporting
        mysqli_report(MYSQLI_REPORT_OFF);
        
        try {
            // One more attempt without strict mode
            $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if (!$conn->connect_error) {
                $conn->set_charset("utf8mb4");
                return $conn;
            }
        } catch (Exception $innerEx) {
            // Nothing to do here, we'll throw the original exception
        }
        
        // If we still can't connect, throw the original exception
        throw $e;
    }
}

/**
 * Execute a query with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return mysqli_result|bool Query result
 * @throws Exception If query fails
 */
function dbQuery($sql, $params = []) {
    try {
        $conn = dbConnect();
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error . " for SQL: " . $sql);
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            $bindParams = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
                $bindParams[] = $param;
            }
            
            // Create array with references as bind_param requires
            $bindParamsRef = [];
            $bindParamsRef[] = &$types;
            
            foreach($bindParams as $key => $value) {
                $bindParamsRef[] = &$bindParams[$key];
            }
            
            call_user_func_array([$stmt, 'bind_param'], $bindParamsRef);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error . " for SQL: " . $sql);
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $stmt->close();
        $conn->close();
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Database query exception: " . $e->getMessage());
        throw $e; // Re-throw to be handled by caller
    }
}

/**
 * Fetch a single row from the database
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array|null Single row as associative array or null if no results
 * @throws Exception If query fails
 */
function dbFetchOne($sql, $params = []) {
    try {
        $result = dbQuery($sql, $params);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    } catch (Exception $e) {
        error_log("dbFetchOne exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Fetch all rows from the database
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array Array of associative arrays representing rows
 * @throws Exception If query fails
 */
function dbFetchAll($sql, $params = []) {
    try {
        $result = dbQuery($sql, $params);
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    } catch (Exception $e) {
        error_log("dbFetchAll exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Insert a new record into the database
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int|bool Inserted ID on success, false on failure
 * @throws Exception If insertion fails
 */
function dbInsert($table, $data) {
    try {
        $keys = array_keys($data);
        $values = array_values($data);
        
        $sql = "INSERT INTO " . $table . " (" . implode(", ", $keys) . ") 
                VALUES (" . implode(", ", array_fill(0, count($values), "?")) . ")";
        
        $conn = dbConnect();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed for INSERT: " . $conn->error . " SQL: " . $sql);
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        // Create array with references
        $bindParams = [];
        $bindParams[] = &$types;
        
        foreach($values as $key => $value) {
            $bindParams[] = &$values[$key];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        
        if (!$stmt->execute()) {
            error_log("Execute failed for INSERT: " . $stmt->error);
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $id = $stmt->insert_id;
        
        $stmt->close();
        $conn->close();
        
        return $id;
    } catch (Exception $e) {
        error_log("dbInsert exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update records in the database
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value to update
 * @param string $where WHERE clause (without the "WHERE" keyword)
 * @param array $whereParams Parameters to bind to WHERE clause
 * @return bool True on success, false on failure
 * @throws Exception If update fails
 */
function dbUpdate($table, $data, $where, $whereParams = []) {
    try {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = $key . " = ?";
        }
        
        $sql = "UPDATE " . $table . " SET " . implode(", ", $sets) . " WHERE " . $where;
        
        $conn = dbConnect();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed for UPDATE: " . $conn->error . " SQL: " . $sql);
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $values = array_values($data);
        $params = array_merge($values, $whereParams);
        
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        // Create array with references
        $bindParams = [];
        $bindParams[] = &$types;
        
        foreach($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
        
        if (!$stmt->execute()) {
            error_log("Execute failed for UPDATE: " . $stmt->error);
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->affected_rows > 0;
        
        $stmt->close();
        $conn->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("dbUpdate exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Delete records from the database
 * 
 * @param string $table Table name
 * @param string $where WHERE clause (without the "WHERE" keyword)
 * @param array $params Parameters to bind to WHERE clause
 * @return bool True on success, false on failure
 * @throws Exception If deletion fails
 */
function dbDelete($table, $where, $params = []) {
    try {
        $sql = "DELETE FROM " . $table . " WHERE " . $where;
        
        $conn = dbConnect();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Prepare failed for DELETE: " . $conn->error . " SQL: " . $sql);
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
            
            // Create array with references
            $bindParams = [];
            $bindParams[] = &$types;
            
            foreach($params as $key => $value) {
                $bindParams[] = &$params[$key];
            }
            
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed for DELETE: " . $stmt->error);
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->affected_rows > 0;
        
        $stmt->close();
        $conn->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("dbDelete exception: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Check if a table exists in the database
 *
 * @param string $tableName Name of the table to check
 * @return bool True if table exists, false otherwise
 */
function dbTableExists($tableName) {
    try {
        $conn = dbConnect();
        $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
        $exists = ($result->num_rows > 0);
        $conn->close();
        return $exists;
    } catch (Exception $e) {
        error_log("dbTableExists exception: " . $e->getMessage());
        return false;
    }
}
?>