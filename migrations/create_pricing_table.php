<?php
// migrations/create_pricing_table.php

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if the pricing table already exists
try {
    $conn = dbConnect();
    $result = $conn->query("SHOW TABLES LIKE 'pricing'");
    
    if ($result->num_rows > 0) {
        echo "Pricing table already exists.\n";
    } else {
        // Create the pricing table
        $sql = "CREATE TABLE IF NOT EXISTS `pricing` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `vehicle_type` varchar(50) NOT NULL,
            `base_rate` decimal(10,2) NOT NULL,
            `price_per_km` decimal(10,2) NOT NULL,
            `multiplier` decimal(5,2) NOT NULL,
            `min_fare` decimal(10,2) NOT NULL,
            `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `updated_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `vehicle_type` (`vehicle_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if ($conn->query($sql)) {
            echo "Pricing table created successfully.\n";
            
            // Insert initial values
            $initialData = [
                ['vehicle_type' => 'standard', 'base_rate' => 1000.00, 'price_per_km' => 100.00, 'multiplier' => 1.00, 'min_fare' => 1000.00],
                ['vehicle_type' => 'suv', 'base_rate' => 1500.00, 'price_per_km' => 150.00, 'multiplier' => 1.50, 'min_fare' => 1500.00],
                ['vehicle_type' => 'premium', 'base_rate' => 2000.00, 'price_per_km' => 200.00, 'multiplier' => 2.00, 'min_fare' => 2000.00]
            ];
            
            foreach ($initialData as $data) {
                $stmt = $conn->prepare("INSERT INTO pricing (vehicle_type, base_rate, price_per_km, multiplier, min_fare) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdddd", $data['vehicle_type'], $data['base_rate'], $data['price_per_km'], $data['multiplier'], $data['min_fare']);
                
                if ($stmt->execute()) {
                    echo "Inserted {$data['vehicle_type']} pricing.\n";
                } else {
                    echo "Error inserting {$data['vehicle_type']} pricing: " . $stmt->error . "\n";
                }
                
                $stmt->close();
            }
        } else {
            echo "Error creating pricing table: " . $conn->error . "\n";
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Migration completed!\n";
?>