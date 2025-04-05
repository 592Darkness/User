<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $conn = dbConnect();
    echo "Database connection successful!";
    $conn->close();
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}