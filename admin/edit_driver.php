<?php
require_once '../config/auth.php';
requireRole('admin');

$conn = getDBConnection();
$success = '';
$error = '';
$driver_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];
    $license_number = trim($_POST['license_number']);
    $experience_years = intval($_POST['experience_years']);
    $availability = $_POST['availability'];
    $vehicle_id = !empty($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : null;
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($license_number)) {
        $error = "Full name, email, and license number are required.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update users table with full_name, email, phone, and status
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ?");
            if ($stmt === false) {
                throw new Exception("Error preparing user update: " . $conn->error);
            }
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $status, $driver_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating user: " . $stmt->error);
            }
            $stmt->close();
            
            // Update drivers table with license, experience, availability, and vehicle
            $stmt = $conn->prepare("UPDATE drivers SET license_number = ?, experience_years = ?, availability = ?, vehicle_id = ? WHERE user_id = ?");
            if ($stmt === false) {
                throw new Exception("Error preparing driver update: " . $conn->error);
            }
            $stmt->bind_param("sissi", $license_number, $experience_years, $availability, $vehicle_id, $driver_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating driver: " . $stmt->error);
            }
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            $success = "Driver updated successfully!";
            
            // Refresh driver data
            $query = "SELECT u.*, d.license_number, d.experience_years, d.rating, d.availability, d.vehicle_id 
                      FROM users u 
                      LEFT JOIN drivers d ON u.id = d.user_id 
                      WHERE u.id = ? AND u.user_type = 'driver'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $driver_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $driver = $result->fetch_assoc();
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get driver details
$query = "SELECT u.*, d.license_number, d.experience_years, d.rating, d.availability, d.vehicle_id 
          FROM users u 
          LEFT JOIN drivers d ON u.id = d.user_id 
          WHERE u.id = ? AND u.user_type = 'driver'";
$stmt = $conn->prepare($query);

if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();
$stmt->close();

if (!$driver) {
    header('Location: manage_drivers.php');
    exit();
}

// Get available vehicles
$vehiclesQuery = "SELECT id, vehicle_number, vehicle_type, make, model FROM vehicles ORDER BY vehicle_number";
$vehiclesResult = $conn->query($vehiclesQuery);
$vehicles = [];
if ($vehiclesResult) {
    while ($vehicle = $vehiclesResult->fetch_assoc()) {
        $vehicles[] = $vehicle;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Driver - CRI Travels</title>
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
            max-width: 900px;
            margin: 30px auto;
            padding: 40px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .header-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .header-section h2 {
            color: #205887;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #205887;
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #205887;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
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
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .submit-btn, .back-btn {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .submit-btn {
            background: #ffd600;
            color: #205887;
        }
        
        .submit-btn:hover {
            background: #fcb900;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 214, 0, 0.3);
        }
        
        .back-btn {
            background: #6c757d;
            color: #fff;
        }
        
        .back-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .info-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        footer {
            background: #205887;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 25px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="../img/logo.png" alt="logo">
        <h1>CRI Travels - Admin Panel</h1>
    </header>
    
    <div class="container">
        <div class="header-section">
            <h2>Edit Driver</h2>
            <p style="color: #666;">Update driver information and settings</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <!-- Personal Information Section -->
            <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($driver['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($driver['email'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($driver['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Account Status *</label>
                        <select id="status" name="status" required>
                            <option value="active" <?php echo ($driver['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($driver['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Driver Information Section -->
            <div class="form-section">
                <h3>Driver Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="license_number">License Number *</label>
                        <input type="text" id="license_number" name="license_number" 
                               value="<?php echo htmlspecialchars($driver['license_number'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="experience_years">Years of Experience *</label>
                        <input type="number" id="experience_years" name="experience_years" 
                               min="0" max="50" 
                               value="<?php echo $driver['experience_years'] ?? 0; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="availability">Availability Status *</label>
                        <select id="availability" name="availability" required>
                            <option value="available" <?php echo ($driver['availability'] ?? 'available') == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="busy" <?php echo ($driver['availability'] ?? '') == 'busy' ? 'selected' : ''; ?>>Busy</option>
                            <option value="offline" <?php echo ($driver['availability'] ?? '') == 'offline' ? 'selected' : ''; ?>>Offline</option>
                        </select>
                        <span class="info-text">Set driver's current availability status</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="vehicle_id">Assigned Vehicle</label>
                        <select id="vehicle_id" name="vehicle_id">
                            <option value="">No Vehicle Assigned</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['id']; ?>" 
                                    <?php echo ($driver['vehicle_id'] ?? null) == $vehicle['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehicle['vehicle_number']); ?> - 
                                    <?php echo ucfirst($vehicle['vehicle_type']); ?>
                                    <?php if ($vehicle['make'] || $vehicle['model']): ?>
                                        (<?php echo htmlspecialchars(trim($vehicle['make'] . ' ' . $vehicle['model'])); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="info-text">Select a vehicle to assign to this driver</span>
                    </div>
                </div>
                
                <?php if (isset($driver['rating'])): ?>
                <div class="form-group">
                    <label>Current Rating</label>
                    <input type="text" value="<?php echo number_format($driver['rating'], 2); ?> / 5.00" disabled 
                           style="background: #f5f5f5; cursor: not-allowed;">
                    <span class="info-text">Rating is calculated automatically based on trip reviews</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="button-group">
                <a href="manage_drivers.php" class="back-btn">Cancel</a>
                <button type="submit" class="submit-btn">Update Driver</button>
            </div>
        </form>
    </div>
    
    <footer>
        <p>&copy; 2025 CRI Travels. All rights reserved.</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>
