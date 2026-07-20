<?php
require_once '../config/auth.php';
requireRole('client');

$conn = getDBConnection();
$payment = null;
$trip = null;
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;

if ($payment_id) {
    $user = getCurrentUser();
    
    // Fetch payment and trip details
    $stmt = $conn->prepare("
        SELECT 
            p.id as payment_id,
            p.amount,
            p.method,
            p.status,
            p.created_at as payment_date,
            t.id as trip_id,
            t.service_type,
            t.pickup_location,
            t.destination,
            t.travel_date,
            t.travel_time,
            t.passengers,
            t.fare_amount,
            t.payment_status,
            u.full_name as client_name,
            u.email as client_email,
            u.phone as client_phone,
            d.full_name as driver_name
        FROM payments p
        LEFT JOIN trips t ON p.trip_id = t.id
        LEFT JOIN users u ON p.client_id = u.id
        LEFT JOIN users d ON t.driver_id = d.id
        WHERE p.id = ? AND p.client_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param('ii', $payment_id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        $stmt->close();
        
        if ($payment) {
            $trip = [
                'trip_id' => $payment['trip_id'],
                'service_type' => $payment['service_type'],
                'pickup_location' => $payment['pickup_location'],
                'destination' => $payment['destination'],
                'travel_date' => $payment['travel_date'],
                'travel_time' => $payment['travel_time'],
                'passengers' => $payment['passengers'],
                'fare_amount' => $payment['fare_amount'],
                'payment_status' => $payment['payment_status'],
                'client_name' => $payment['client_name'],
                'client_email' => $payment['client_email'],
                'client_phone' => $payment['client_phone'],
                'driver_name' => $payment['driver_name']
            ];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - CRI Travels</title>
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
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .success-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .success-header {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
        }
        
        .success-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .success-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }
        
        .receipt-content {
            padding: 40px;
        }
        
        .receipt-number {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .receipt-number span {
            display: block;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .receipt-number strong {
            display: block;
            font-size: 1.3rem;
            color: #205887;
            font-weight: bold;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #205887;
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dotted #e0e0e0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-weight: 600;
        }
        
        .detail-value {
            color: #333;
            text-align: right;
        }
        
        .amount-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 1rem;
        }
        
        .amount-row.total {
            border-top: 2px solid #205887;
            border-bottom: 2px solid #205887;
            padding: 15px 0;
            font-size: 1.3rem;
            font-weight: bold;
            color: #205887;
            margin-top: 10px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            min-width: 200px;
            padding: 14px 24px;
            border-radius: 8px;
            border: none;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #205887;
            color: white;
        }
        
        .btn-primary:hover {
            background: #164a6b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 88, 135, 0.3);
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #ffd600;
            color: #205887;
        }
        
        .btn-secondary:hover {
            background: #fcb900;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 214, 0, 0.3);
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #1565c0;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .payment-method-badge {
            display: inline-block;
            background: #e0e0e0;
            color: #333;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        footer {
            background: white;
            color: #666;
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            border-radius: 8px;
        }
        
        @media (max-width: 600px) {
            .success-header {
                padding: 30px 20px;
            }
            
            .success-header h1 {
                font-size: 1.5rem;
            }
            
            .receipt-content {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                min-width: 100%;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="../img/logo.png" alt="logo">
        <h1>CRI Travels - Payment Successful</h1>
    </header>
    
    <div class="container">
        <?php if ($payment && $trip): ?>
        
        <div class="success-card">
            <div class="success-header">
                <div class="success-icon">✓</div>
                <h1>Payment Successful!</h1>
                <p>Your trip has been confirmed and payment received</p>
            </div>
            
            <div class="receipt-content">
                <!-- Receipt Number -->
                <div class="receipt-number">
                    <span>Receipt Number</span>
                    <strong>#<?php echo str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                </div>
                
                <!-- Info Box -->
                <div class="info-box">
                    <strong>Receipt Date & Time</strong>
                    <?php echo date('F d, Y | h:i A', strtotime($payment['payment_date'])); ?>
                </div>
                
                <!-- Passenger Information -->
                <div class="section">
                    <div class="section-title">Passenger Information</div>
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($trip['client_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($trip['client_email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($trip['client_phone']); ?></span>
                    </div>
                </div>
                
                <!-- Trip Information -->
                <div class="section">
                    <div class="section-title">Trip Information</div>
                    <div class="detail-row">
                        <span class="detail-label">Trip ID:</span>
                        <span class="detail-value">#<?php echo str_pad($trip['trip_id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Service Type:</span>
                        <span class="detail-value"><?php echo ucfirst($trip['service_type']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Pickup Location:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(substr($trip['pickup_location'], 0, 60)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Destination:</span>
                        <span class="detail-value"><?php echo htmlspecialchars(substr($trip['destination'], 0, 60)); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Travel Date:</span>
                        <span class="detail-value"><?php echo date('M d, Y', strtotime($trip['travel_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Travel Time:</span>
                        <span class="detail-value"><?php echo $trip['travel_time']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Number of Passengers:</span>
                        <span class="detail-value"><?php echo $trip['passengers']; ?></span>
                    </div>
                    <?php if ($trip['driver_name']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Assigned Driver:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($trip['driver_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Payment Details -->
                <div class="section">
                    <div class="section-title">Payment Details</div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value">
                            <span class="payment-method-badge">
                                <?php 
                                $method_icons = [
                                    'card' => '💳',
                                    'upi' => '📱',
                                    'netbanking' => '🏦'
                                ];
                                echo ($method_icons[$payment['method']] ?? '💰') . ' ' . ucfirst($payment['method']);
                                ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Status:</span>
                        <span class="detail-value">
                            <span style="color: #4caf50; font-weight: bold;">✓ <?php echo ucfirst($payment['status']); ?></span>
                        </span>
                    </div>
                </div>
                
                <!-- Amount Summary -->
                <div class="amount-summary">
                    <div class="section-title">Amount Summary</div>
                    <div class="amount-row">
                        <span>Fare Amount:</span>
                        <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    <div class="amount-row">
                        <span>Taxes & Fees:</span>
                        <span>₹0.00</span>
                    </div>
                    <div class="amount-row total">
                        <span>Total Amount Paid:</span>
                        <span>₹<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="button-group">
                    <a href="payment_receipt.php?trip_id=<?php echo $trip['trip_id']; ?>" class="btn btn-success">
                        📄 Download Receipt (PDF)
                    </a>
                    <a href="my_trips.php" class="btn btn-primary">
                        📋 View My Trips
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        🏠 Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        
        <div class="success-card">
            <div class="success-header" style="background: linear-gradient(135deg, #f44336 0%, #e53935 100%);">
                <div class="success-icon">✕</div>
                <h1>Payment Not Found</h1>
                <p>We could not retrieve your payment details</p>
            </div>
            
            <div class="receipt-content" style="text-align: center;">
                <p style="color: #666; margin-bottom: 20px;">The payment record you're looking for doesn't exist or you don't have access to it.</p>
                <div class="button-group">
                    <a href="dashboard.php" class="btn btn-primary" style="flex: none;">
                        🏠 Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <footer>
        <p>&copy; 2025 CRI Travels. All rights reserved.</p>
        <p style="font-size: 0.9rem; margin-top: 10px;">For support, contact: critravels@gmail.com | +91 75581 98405</p>
    </footer>
</body>
</html>
