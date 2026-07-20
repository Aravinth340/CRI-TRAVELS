<?php
require_once '../config/auth.php';
requireRole('client');

$user = getCurrentUser();
$conn = getDBConnection();

$error = '';
$success = '';
$trip = null;

// Fetch trip id from GET or POST
$trip_id = isset($_GET['trip_id']) ? intval($_GET['trip_id']) : (isset($_POST['trip_id']) ? intval($_POST['trip_id']) : 0);

if (!$trip_id) {
    $error = 'Missing trip ID.';
}

// Load trip details
if (empty($error)) {
    $stmt = $conn->prepare("SELECT t.*, u.full_name, u.phone, u.email FROM trips t JOIN users u ON t.client_id = u.id WHERE t.id = ? AND t.client_id = ?");
    if ($stmt) {
        $stmt->bind_param('ii', $trip_id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $trip = $result->fetch_assoc();
        $stmt->close();
        
        if (!$trip) {
            $error = 'Trip not found or access denied.';
        }
    } else {
        $error = 'Database error: ' . $conn->error;
    }
}

// Function to calculate fare based on service type and passengers
function calculate_fare($service_type, $passengers) {
    $base_fares = [
        'airport' => 500,
        'rental' => 1000,
        'tour' => 1500,
        'business' => 800,
        'event' => 1200
    ];
    
    $base = isset($base_fares[$service_type]) ? $base_fares[$service_type] : 1000;
    $passengers = max(1, intval($passengers));
    
    // Base fare + per passenger surcharge
    $total = $base + ($passengers - 1) * 100;
    
    return floatval(number_format($total, 2, '.', ''));
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && isset($_POST['pay_now'])) {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'] ?? 'card';
    $payment_status = 'completed';
    
    // Validate amount
    if ($amount <= 0) {
        $error = 'Invalid payment amount.';
    } else {
        // Create payments table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            trip_id INT NOT NULL,
            client_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(50),
            status ENUM('pending','completed','failed') DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_trip_payment (trip_id),
            FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
            FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        if (!$conn->query($create_table)) {
            $error = 'Database error: ' . $conn->error;
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Check if payment already exists for this trip
                $check_stmt = $conn->prepare("SELECT id FROM payments WHERE trip_id = ? FOR UPDATE");
                if ($check_stmt === false) {
                    throw new Exception("Prepare error: " . $conn->error);
                }
                
                $check_stmt->bind_param('i', $trip_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existing_payment = $check_result->fetch_assoc();
                $check_stmt->close();
                
                $payment_id = null;
                
                if ($existing_payment) {
                    // Update existing payment
                    $update_stmt = $conn->prepare("UPDATE payments SET amount = ?, method = ?, status = 'completed' WHERE trip_id = ?");
                    if ($update_stmt === false) {
                        throw new Exception("Update prepare error: " . $conn->error);
                    }
                    
                    $update_stmt->bind_param('dsi', $amount, $method, $trip_id);
                    if (!$update_stmt->execute()) {
                        throw new Exception("Update execute error: " . $update_stmt->error);
                    }
                    $payment_id = $existing_payment['id'];
                    $update_stmt->close();
                } else {
                    // Insert new payment
                    $insert_stmt = $conn->prepare("INSERT INTO payments (trip_id, client_id, amount, method, status) VALUES (?, ?, ?, ?, 'completed')");
                    if ($insert_stmt === false) {
                        throw new Exception("Insert prepare error: " . $conn->error);
                    }
                    
                    $insert_stmt->bind_param('iids', $trip_id, $user['id'], $amount, $method);
                    if (!$insert_stmt->execute()) {
                        throw new Exception("Insert execute error: " . $insert_stmt->error);
                    }
                    $payment_id = $insert_stmt->insert_id;
                    $insert_stmt->close();
                }
                
                // Update trip fare and status
                $trip_stmt = $conn->prepare("UPDATE trips SET fare_amount = ?, payment_status = 'completed', status = 'confirmed' WHERE id = ? AND client_id = ?");
                if ($trip_stmt === false) {
                    throw new Exception("Trip update prepare error: " . $conn->error);
                }
                
                $trip_stmt->bind_param('dii', $amount, $trip_id, $user['id']);
                if (!$trip_stmt->execute()) {
                    throw new Exception("Trip update execute error: " . $trip_stmt->error);
                }
                $trip_stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to success page
                header('Location: payment_success.php?payment_id=' . intval($payment_id));
                exit;
                
            } catch (Exception $e) {
                // Rollback on any error
                $conn->rollback();
                $error = 'Payment processing failed: ' . $e->getMessage();
            }
        }
    }
}

$calculated_amount = $trip ? calculate_fare($trip['service_type'], $trip['passengers']) : 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - CRI Travels</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        header {
            background: #205887;
            color: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        header img {
            height: 50px;
        }
        
        header h1 {
            font-size: 1.5rem;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .header-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .header-section h1 {
            color: #205887;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        
        .trip-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
        }
        
        .trip-summary h3 {
            color: #205887;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #e0e0e0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            color: #666;
            font-weight: 600;
        }
        
        .summary-value {
            color: #333;
            text-align: right;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section h3 {
            color: #205887;
            margin-bottom: 15px;
            font-size: 1.1rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            color: #205887;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #205887;
            outline: none;
            box-shadow: 0 0 0 3px rgba(32, 88, 135, 0.1);
        }
        
        .form-group input[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .amount-display {
            background: #f0f7ff;
            border: 2px solid #205887;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        
        .amount-display .label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .amount-display .amount {
            color: #205887;
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .method-option {
            position: relative;
        }
        
        .method-option input[type="radio"] {
            display: none;
        }
        
        .method-option label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            margin: 0;
        }
        
        .method-option input[type="radio"]:checked + label {
            border-color: #205887;
            background: #f0f7ff;
            color: #205887;
            font-weight: bold;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 14px 24px;
            border-radius: 25px;
            border: none;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #ffd600;
            color: #205887;
        }
        
        .btn-primary:hover {
            background: #fcb900;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 214, 0, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .security-info {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 12px 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 0.9rem;
            color: #2e7d32;
        }
        
        footer {
            background: #205887;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 30px;
        }
        
        @media (max-width: 600px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .amount-display .amount {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="../img/logo.png" alt="logo">
        <h1>CRI Travels - Complete Your Payment</h1>
    </header>
    
    <div class="container">
        <div class="header-section">
            <h1>Payment</h1>
            <p style="color: #666;">Complete your trip booking payment</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($trip && empty($error)): ?>
        
        <!-- Trip Summary -->
        <div class="trip-summary">
            <h3>Trip Summary</h3>
            <div class="summary-row">
                <span class="summary-label">Service Type:</span>
                <span class="summary-value"><?php echo ucfirst(htmlspecialchars($trip['service_type'])); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Pickup Location:</span>
                <span class="summary-value"><?php echo htmlspecialchars(substr($trip['pickup_location'], 0, 40)); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Destination:</span>
                <span class="summary-value"><?php echo htmlspecialchars(substr($trip['destination'], 0, 40)); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Travel Date:</span>
                <span class="summary-value"><?php echo date('M d, Y', strtotime($trip['travel_date'])); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Travel Time:</span>
                <span class="summary-value"><?php echo htmlspecialchars($trip['travel_time']); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Passengers:</span>
                <span class="summary-value"><?php echo intval($trip['passengers']); ?></span>
            </div>
        </div>
        
        <!-- Amount Display -->
        <div class="amount-display">
            <div class="label">Amount to Pay</div>
            <div class="amount">₹<?php echo number_format($calculated_amount, 2); ?></div>
        </div>
        
        <!-- Payment Form -->
        <form method="POST" name="paymentForm">
            <input type="hidden" name="trip_id" value="<?php echo intval($trip_id); ?>">
            <input type="hidden" name="amount" value="<?php echo $calculated_amount; ?>">
            
            <!-- Payment Method Section -->
            <div class="form-section">
                <h3>Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="method-option">
                        <input type="radio" id="card" name="method" value="card" checked>
                        <label for="card">💳 Card</label>
                    </div>
                    <div class="method-option">
                        <input type="radio" id="upi" name="method" value="upi">
                        <label for="upi">📱 UPI</label>
                    </div>
                    <div class="method-option">
                        <input type="radio" id="netbanking" name="method" value="netbanking">
                        <label for="netbanking">🏦 Net Banking</label>
                    </div>
                </div>
            </div>
            
            <!-- Card Details Section -->
            <div class="form-section">
                <h3>Card Details</h3>
                <div class="form-group">
                    <label for="card_number">Card Number</label>
                    <input type="text" id="card_number" name="card_number" 
                           placeholder="4111 1111 1111 1111" 
                           maxlength="19" required>
                </div>
                
                <div class="form-group">
                    <label for="card_name">Cardholder Name</label>
                    <input type="text" id="card_name" name="card_name" 
                           placeholder="<?php echo htmlspecialchars($trip['full_name']); ?>" 
                           value="<?php echo htmlspecialchars($trip['full_name']); ?>" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="card_expiry">Expiry Date</label>
                        <input type="text" id="card_expiry" name="card_expiry" 
                               placeholder="MM/YY" maxlength="5" required>
                    </div>
                    <div class="form-group">
                        <label for="card_cvv">CVV</label>
                        <input type="password" id="card_cvv" name="card_cvv" 
                               placeholder="123" maxlength="4" required>
                    </div>
                </div>
                
                <div class="security-info">
                    🔒 Your payment information is secure and encrypted
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="button-group">
                <a href="book_trip.php" class="btn btn-secondary">← Cancel</a>
                <button type="submit" name="pay_now" class="btn btn-primary">Pay ₹<?php echo number_format($calculated_amount, 2); ?> →</button>
            </div>
        </form>
        
        <?php else: ?>
        
        <div style="text-align: center; padding: 40px;">
            <p style="color: #666; font-size: 1.1rem;">Unable to load payment details. Please try booking again.</p>
            <a href="book_trip.php" class="btn btn-primary" style="display: inline-block; margin-top: 20px;">
                ← Back to Booking
            </a>
        </div>
        
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 CRI Travels. All rights reserved.</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>
