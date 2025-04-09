<?php
/**
 * Payment processing functionality
 * Place this file in your includes directory
 */

/**
 * Process a ride payment
 * 
 * @param int $rideId The ride ID
 * @param string $paymentMethod Payment method (cash, card, etc.)
 * @param float $amount Amount to charge (in cents)
 * @param int $userId User ID making the payment
 * @return array Result of payment processing
 */
function processPayment($rideId, $paymentMethod, $amount, $userId) {
    try {
        $conn = dbConnect();
        $conn->begin_transaction();
        
        // First, check if the ride exists and isn't already paid
        $checkStmt = $conn->prepare("
            SELECT status, payment_status, user_id, driver_id
            FROM rides
            WHERE id = ?
        ");
        
        $checkStmt->bind_param("i", $rideId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => 'Ride not found'
            ];
        }
        
        $ride = $result->fetch_assoc();
        $checkStmt->close();
        
        // Validate ride can be paid for
        if ($ride['payment_status'] === 'paid') {
            return [
                'success' => false,
                'message' => 'Ride has already been paid'
            ];
        }
        
        if ($ride['status'] !== 'completed') {
            return [
                'success' => false,
                'message' => 'Ride must be completed before payment'
            ];
        }
        
        // For cash payments, just mark as paid
        if ($paymentMethod === 'cash') {
            $paymentStatus = 'completed';
            
            // Create payment record
            $paymentStmt = $conn->prepare("
                INSERT INTO payments (
                    user_id,
                    ride_id,
                    amount,
                    payment_method,
                    status,
                    created_at
                ) VALUES (?, ?, ?, 'cash', 'completed', NOW())
            ");
            
            $paymentStmt->bind_param("iid", $userId, $rideId, $amount);
            $paymentStmt->execute();
            $paymentId = $conn->insert_id;
            $paymentStmt->close();
            
            // Update ride as paid
            $updateRideStmt = $conn->prepare("
                UPDATE rides
                SET payment_status = 'paid',
                    payment_method = 'cash',
                    payment_completed_at = NOW()
                WHERE id = ?
            ");
            
            $updateRideStmt->bind_param("i", $rideId);
            $updateRideStmt->execute();
            $updateRideStmt->close();
            
            // Create driver payment/earnings record (typically 80% of fare)
            if ($ride['driver_id']) {
                $driverShare = $amount * 0.8; // 80% to driver
                
                $driverPaymentStmt = $conn->prepare("
                    INSERT INTO driver_payments (
                        driver_id,
                        ride_id,
                        amount,
                        status,
                        payment_method,
                        description,
                        created_at
                    ) VALUES (?, ?, ?, 'pending', 'system', 'Earnings from ride #' || ?, NOW())
                ");
                
                $driverPaymentStmt->bind_param("iidi", $ride['driver_id'], $rideId, $driverShare, $rideId);
                $driverPaymentStmt->execute();
                $driverPaymentStmt->close();
            }
            
            $conn->commit();
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'message' => 'Cash payment recorded successfully',
                'status' => 'completed'
            ];
        }
        
        // For card or online payments, integrate with a payment gateway
        if ($paymentMethod === 'card') {
            // This is where you would integrate with a payment gateway
            // For example, with Stripe, PayPal, or a local payment processor
            
            // For now, we'll simulate a successful payment
            $paymentStatus = 'completed';
            $transactionId = 'SIMULATED_' . time();
            
            // Create payment record
            $paymentStmt = $conn->prepare("
                INSERT INTO payments (
                    user_id,
                    ride_id,
                    amount,
                    payment_method,
                    status,
                    transaction_id,
                    created_at
                ) VALUES (?, ?, ?, 'card', ?, ?, NOW())
            ");
            
            $paymentStmt->bind_param("iidss", $userId, $rideId, $amount, $paymentStatus, $transactionId);
            $paymentStmt->execute();
            $paymentId = $conn->insert_id;
            $paymentStmt->close();
            
            // Update ride as paid
            $updateRideStmt = $conn->prepare("
                UPDATE rides
                SET payment_status = 'paid',
                    payment_method = 'card',
                    payment_completed_at = NOW()
                WHERE id = ?
            ");
            
            $updateRideStmt->bind_param("i", $rideId);
            $updateRideStmt->execute();
            $updateRideStmt->close();
            
            // Create driver payment/earnings record
            if ($ride['driver_id']) {
                $driverShare = $amount * 0.8; // 80% to driver
                
                $driverPaymentStmt = $conn->prepare("
                    INSERT INTO driver_payments (
                        driver_id,
                        ride_id,
                        amount,
                        status,
                        payment_method,
                        description,
                        created_at
                    ) VALUES (?, ?, ?, 'pending', 'system', 'Earnings from ride #' || ?, NOW())
                ");
                
                $driverPaymentStmt->bind_param("iidi", $ride['driver_id'], $rideId, $driverShare, $rideId);
                $driverPaymentStmt->execute();
                $driverPaymentStmt->close();
            }
            
            $conn->commit();
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'transaction_id' => $transactionId,
                'message' => 'Card payment processed successfully',
                'status' => $paymentStatus
            ];
        }
        
        // Unsupported payment method
        return [
            'success' => false,
            'message' => 'Unsupported payment method'
        ];
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        
        error_log("Payment processing error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'An error occurred during payment processing',
            'error' => $e->getMessage()
        ];
    }
}