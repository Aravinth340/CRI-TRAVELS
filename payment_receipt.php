<?php
/**
 * Payment Receipt PDF Generator
 * Generates and downloads payment receipts as PDF files
 */

require_once '../config/database.php';
require_once '../config/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Unauthorized access");
}

// Get payment/trip ID
$trip_id = isset($_GET['trip_id']) ? intval($_GET['trip_id']) : 0;

if ($trip_id <= 0) {
    http_response_code(400);
    die("Invalid trip ID");
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch trip and payment details
$query = "SELECT 
    t.id as trip_id,
    t.service_type,
    t.pickup_location,
    t.destination,
    t.travel_date,
    t.travel_time,
    t.passengers,
    t.fare_amount,
    t.payment_status,
    t.created_at,
    c.full_name as client_name,
    c.email as client_email,
    c.phone as client_phone,
    d.full_name as driver_name
FROM trips t
JOIN users c ON t.client_id = c.id
LEFT JOIN users d ON t.driver_id = d.id
WHERE t.id = ? AND (t.client_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("iii", $trip_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();
$stmt->close();

if (!$trip) {
    http_response_code(404);
    die("Trip not found");
}

// Check if TCPDF is available, otherwise use a simpler approach
$use_html2pdf = false;
if (class_exists('TCPDF')) {
    $use_html2pdf = true;
    // Create PDF using TCPDF
    $pdf = new TCPDF();
} elseif (file_exists('../vendor/autoload.php')) {
    // Try to use composer autoloader if available
    require_once '../vendor/autoload.php';
    if (class_exists('TCPDF')) {
        $use_html2pdf = true;
        $pdf = new TCPDF();
    }
}

if ($use_html2pdf) {
    // TCPDF approach
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 20);
    
    // Header
    $pdf->SetTextColor(32, 88, 135);
    $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'CRI Travels', 0, 1, 'C');
    $pdf->Cell(0, 5, 'www.critravels.com', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Receipt Details
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Receipt Details:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 6, 'Receipt ID:', 0, 0);
    $pdf->Cell(0, 6, '#' . str_pad($trip_id, 6, '0', STR_PAD_LEFT), 0, 1);
    
    $pdf->Cell(50, 6, 'Date:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y H:i'), 0, 1);
    
    $pdf->Cell(50, 6, 'Payment Status:', 0, 0);
    $pdf->SetTextColor(76, 175, 80);
    $pdf->Cell(0, 6, ucfirst($trip['payment_status']), 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
    
    // Client Details
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Passenger Information:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 6, 'Name:', 0, 0);
    $pdf->Cell(0, 6, $trip['client_name'], 0, 1);
    
    $pdf->Cell(50, 6, 'Email:', 0, 0);
    $pdf->Cell(0, 6, $trip['client_email'], 0, 1);
    
    $pdf->Cell(50, 6, 'Phone:', 0, 0);
    $pdf->Cell(0, 6, $trip['client_phone'], 0, 1);
    $pdf->Ln(3);
    
    // Trip Details
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Trip Information:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 6, 'Trip ID:', 0, 0);
    $pdf->Cell(0, 6, '#' . str_pad($trip['trip_id'], 6, '0', STR_PAD_LEFT), 0, 1);
    
    $pdf->Cell(50, 6, 'Service Type:', 0, 0);
    $pdf->Cell(0, 6, ucfirst($trip['service_type']), 0, 1);
    
    $pdf->Cell(50, 6, 'From:', 0, 0);
    $pdf->Cell(0, 6, substr($trip['pickup_location'], 0, 50), 0, 1);
    
    $pdf->Cell(50, 6, 'To:', 0, 0);
    $pdf->Cell(0, 6, substr($trip['destination'], 0, 50), 0, 1);
    
    $pdf->Cell(50, 6, 'Travel Date:', 0, 0);
    $pdf->Cell(0, 6, date('M d, Y', strtotime($trip['travel_date'])), 0, 1);
    
    $pdf->Cell(50, 6, 'Travel Time:', 0, 0);
    $pdf->Cell(0, 6, $trip['travel_time'], 0, 1);
    
    $pdf->Cell(50, 6, 'Passengers:', 0, 0);
    $pdf->Cell(0, 6, $trip['passengers'], 0, 1);
    
    if ($trip['driver_name']) {
        $pdf->Cell(50, 6, 'Driver:', 0, 0);
        $pdf->Cell(0, 6, $trip['driver_name'], 0, 1);
    }
    $pdf->Ln(3);
    
    // Amount Details
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'Payment Summary:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(120, 6, 'Fare Amount:', 0, 0, 'R');
    $pdf->Cell(0, 6, '₹' . number_format($trip['fare_amount'], 2), 0, 1, 'R');
    
    // Add border around total
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(32, 88, 135);
    $pdf->Rect(15, $pdf->GetY(), 180, 8);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(120, 8, 'Total Amount:', 0, 0, 'R');
    $pdf->Cell(0, 8, '₹' . number_format($trip['fare_amount'], 2), 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Footer
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5, 'This is an automated receipt generated by CRI Travels System', 0, 1, 'C');
    $pdf->Cell(0, 5, 'For any queries, please contact: critravels@gmail.com | +91 75581 98405', 0, 1, 'C');
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Receipt_#' . str_pad($trip_id, 6, '0', STR_PAD_LEFT) . '.pdf"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $pdf->Output('Receipt_#' . str_pad($trip_id, 6, '0', STR_PAD_LEFT) . '.pdf', 'D');
} else {
    // Fallback: Generate HTML receipt (can be printed to PDF from browser)
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - CRI Travels</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #205887;
            padding-bottom: 20px;
        }
        
        .receipt-header h1 {
            color: #205887;
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        
        .receipt-header p {
            color: #666;
            font-size: 1rem;
        }
        
        .receipt-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            color: #205887;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #e0e0e0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
            flex: 0 0 40%;
        }
        
        .detail-value {
            color: #555;
            flex: 1;
            text-align: right;
        }
        
        .amount-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .amount-row.total {
            border-top: 2px solid #205887;
            border-bottom: 2px solid #205887;
            padding: 12px 0;
            font-size: 1.3rem;
            font-weight: bold;
            color: #205887;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 0.9rem;
            color: #999;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            background: #4caf50;
            color: white;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .button-group {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-print {
            background: #205887;
            color: white;
        }
        
        .btn-download {
            background: #ffd600;
            color: #205887;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            
            .button-group {
                display: none;
            }
        }
        
        @media (max-width: 600px) {
            .receipt-container {
                padding: 20px;
            }
            
            .receipt-header h1 {
                font-size: 1.8rem;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                flex: 1;
                margin-bottom: 5px;
            }
            
            .detail-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>PAYMENT RECEIPT</h1>
            <p>CRI Travels - Reliable Transport Services</p>
            <span class="status-badge">✓ Payment Received</span>
        </div>
        
        <!-- Receipt Details -->
        <div class="receipt-section">
            <div class="section-title">Receipt Details</div>
            <div class="detail-row">
                <span class="detail-label">Receipt ID:</span>
                <span class="detail-value">#<?php echo str_pad($trip['trip_id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date & Time:</span>
                <span class="detail-value"><?php echo date('F d, Y | H:i A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Status:</span>
                <span class="detail-value" style="color: #4caf50;"><?php echo ucfirst($trip['payment_status']); ?></span>
            </div>
        </div>
        
        <!-- Passenger Information -->
        <div class="receipt-section">
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
        <div class="receipt-section">
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
                <span class="detail-value"><?php echo htmlspecialchars(substr($trip['pickup_location'], 0, 50)); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Destination:</span>
                <span class="detail-value"><?php echo htmlspecialchars(substr($trip['destination'], 0, 50)); ?></span>
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
                <span class="detail-label">Passengers:</span>
                <span class="detail-value"><?php echo $trip['passengers']; ?></span>
            </div>
            <?php if ($trip['driver_name']): ?>
            <div class="detail-row">
                <span class="detail-label">Driver:</span>
                <span class="detail-value"><?php echo htmlspecialchars($trip['driver_name']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Amount Details -->
        <div class="amount-section">
            <div class="section-title">Payment Summary</div>
            <div class="amount-row">
                <span>Fare Amount:</span>
                <span>₹<?php echo number_format($trip['fare_amount'], 2); ?></span>
            </div>
            <div class="amount-row total">
                <span>Total Amount:</span>
                <span>₹<?php echo number_format($trip['fare_amount'], 2); ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is an automated receipt generated by CRI Travels System</p>
            <p>For any queries, please contact:</p>
            <p>Email: critravels@gmail.com | Phone: +91 75581 98405</p>
            <p style="margin-top: 15px; font-size: 0.85rem;">Receipt Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <!-- Action Buttons -->
        <div class="button-group">
            <button class="btn btn-print" onclick="window.print()">🖨️ Print Receipt</button>
            <button class="btn btn-download" onclick="window.location.href='?trip_id=<?php echo $trip_id; ?>&format=pdf'">⬇️ Download PDF</button>
            <button class="btn btn-back" onclick="window.history.back()">← Back</button>
        </div>
    </div>
</body>
</html>
    <?php
}

$conn->close();
?>
