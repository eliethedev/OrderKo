<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    // If somehow a non-admin accessed this page
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Dashboard statistics
try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
    $total_users = $stmt->fetch()['total'];
    
    // Total businesses
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM businesses");
    $total_businesses = $stmt->fetch()['total'];
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    // Recent orders (last 5)
    $stmt = $pdo->query("SELECT o.*, u.full_name as user_name, b.name as business_name 
                         FROM orders o
                         JOIN users u ON o.id = u.id
                         JOIN businesses b ON o.business_id = b.id
                         ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();
    
    // Pending business verifications
    $stmt = $pdo->query("SELECT b.*, u.full_name as owner_name 
                         FROM businesses b
                         JOIN users u ON b.user_id = u.id
                         WHERE b.status = 'pending' LIMIT 5");
    $pending_verifications = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OrderKo</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card" onclick="window.location.href = 'users.php';">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Total Users</h3>
                        <p class="stat-number"><?php echo isset($total_users) ? $total_users : 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href = 'businesses.php';">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Businesses</h3>
                        <p class="stat-number"><?php echo isset($total_businesses) ? $total_businesses : 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href = 'orders.php';">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Orders</h3>
                        <p class="stat-number"><?php echo isset($total_orders) ? $total_orders : 0; ?></p>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href= 'products.php';">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Products</h3>
                        <p class="stat-number"><?php echo isset($total_products) ? $total_products : 0; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Orders</h2>
                    <a href="orders.php" class="btn btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <p class="empty-state">No recent orders found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Business</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['business_name']); ?></td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Verifications -->
            <div class="card">
                <div class="card-header">
                    <h2>Pending Business Verifications</h2>
                    <a href="businesses.php?filter=pending" class="btn btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_verifications)): ?>
                        <p class="empty-state">No pending verifications</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Business Name</th>
                                        <th>Owner</th>
                                        <th>Category</th>
                                        <th>Submitted On</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_verifications as $business): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($business['name']); ?></td>
                                            <td><?php echo htmlspecialchars($business['owner_name']); ?></td>
                                            <td><?php echo htmlspecialchars($business['category']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($business['created_at'])); ?></td>
                                            <td>
                                                <a href="verify-business.php?id=<?php echo $business['id']; ?>" class="btn btn-sm btn-success">Review</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/admin.js"></script>
</body>
</html>
