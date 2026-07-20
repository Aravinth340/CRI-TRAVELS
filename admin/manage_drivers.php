<?php
require_once '../config/auth.php';
requireRole('admin');

$conn = getDBConnection();

// Handle delete action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id = $id AND user_type = 'driver'");
    header('Location: manage_drivers.php');
    exit();
}

// Get all drivers with vehicle information
$query = "SELECT u.*, d.license_number, d.experience_years, d.rating, d.availability, 
          v.vehicle_number, v.vehicle_type, v.make, v.model 
          FROM users u 
          LEFT JOIN drivers d ON u.id = d.user_id 
          LEFT JOIN vehicles v ON d.vehicle_id = v.id 
          WHERE u.user_type = 'driver' 
          ORDER BY u.created_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - CRI Travels</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(32,88,135,0.1);
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .back-btn {
            background: #205887;
            color: #fff;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #205887;
            color: #fff;
            font-weight: bold;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .action-btn {
            padding: 6px 12px;
            margin: 0 5px;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .edit-btn {
            background: #ffd600;
            color: #205887;
        }
        .delete-btn {
            background: #f44336;
            color: #fff;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-block;
            margin-right: 5px;
        }
        .status-active {
            background: #4caf50;
            color: #fff;
        }
        .status-inactive {
            background: #f44336;
            color: #fff;
        }
        .status-available {
            background: #4caf50;
            color: #fff;
        }
        .status-busy {
            background: #ff9800;
            color: #fff;
        }
        .status-offline {
            background: #9e9e9e;
            color: #fff;
        }
        .status-column {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .status-label {
            font-size: 0.75rem;
            color: #666;
            font-weight: 600;
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
            <h2 style="color: #205887;">Manage Drivers</h2>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>License</th>
                    <th>Vehicle</th>
                    <th>Account Status</th>
                    <th>Availability</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($driver = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $driver['id']; ?></td>
                    <td><?php echo htmlspecialchars($driver['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($driver['email']); ?></td>
                    <td><?php echo htmlspecialchars($driver['phone'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($driver['license_number'] ?? 'N/A'); ?></td>
                    <!-- Display vehicle information -->
                    <td>
                        <?php 
                        if ($driver['vehicle_number']) {
                            echo htmlspecialchars($driver['vehicle_number']) . '<br>';
                            echo '<small>' . ucfirst($driver['vehicle_type']) . ' - ' . htmlspecialchars($driver['make'] . ' ' . $driver['model']) . '</small>';
                        } else {
                            echo '<small style="color: #999;">Not Assigned</small>';
                        }
                        ?>
                    </td>
                    <!-- Account Status -->
                    <td>
                        <span class="status-badge status-<?php echo $driver['status']; ?>">
                            <?php echo ucfirst($driver['status']); ?>
                        </span>
                    </td>
                    <!-- Availability Status -->
                    <td>
                        <span class="status-badge status-<?php echo $driver['availability'] ?? 'offline'; ?>">
                            <?php echo ucfirst($driver['availability'] ?? 'Offline'); ?>
                        </span>
                    </td>
                    <td>
                        <a href="edit_driver.php?id=<?php echo $driver['id']; ?>" class="action-btn edit-btn">Edit</a>
                        <a href="manage_drivers.php?delete=<?php echo $driver['id']; ?>" 
                           class="action-btn delete-btn" 
                           onclick="return confirm('Are you sure you want to delete this driver?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <footer>
        <p>&copy; 2025 CRI Travels. All rights reserved.</p>
    </footer>
</body>
</html>
<?php $conn->close(); ?>
