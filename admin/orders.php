<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle order status changes
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
    
    if (in_array($action, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$action, $id]);
        $success_message = "Order status updated to " . ucfirst($action);
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter and search functionality
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_conditions = [];
$params = [];

if ($filter && in_array($filter, ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'])) {
    $where_conditions[] = "o.status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $where_conditions[] = "(o.id LIKE ? OR u.full_name LIKE ? OR b.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total orders for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    JOIN businesses b ON o.business_id = b.id
    $where_clause
");
$stmt->execute($params);
$total_orders = $stmt->fetch()['total'];
$total_pages = ceil($total_orders / $records_per_page);

// Get orders with pagination
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name as user_name, u.email as user_email, b.name as business_name 
    FROM orders o
    JOIN users u ON o.customer_id = u.id
    JOIN businesses b ON o.business_id = b.id
    $where_clause
    ORDER BY o.created_at DESC 
    LIMIT $offset, $records_per_page
");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - OrderKo Admin</title>
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
                <h1>Order Management</h1>
                <div class="admin-actions">
                    <form action="" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <?php if (!empty($filter)): ?>
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="orders.php" class="filter-tab <?php echo empty($filter) ? 'active' : ''; ?>">All</a>
                <a href="orders.php?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="orders.php?filter=confirmed" class="filter-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
                <a href="orders.php?filter=preparing" class="filter-tab <?php echo $filter === 'preparing' ? 'active' : ''; ?>">Preparing</a>
                <a href="orders.php?filter=ready" class="filter-tab <?php echo $filter === 'ready' ? 'active' : ''; ?>">Ready</a>
                <a href="orders.php?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                <a href="orders.php?filter=cancelled" class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>
                        <?php 
                        if (!empty($filter)) {
                            echo ucfirst($filter) . ' Orders';
                        } else {
                            echo 'All Orders';
                        }
                        ?>
                    </h2>
                    <span class="count-badge"><?php echo $total_orders; ?> orders</span>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <p class="empty-state">No orders found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Business</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <span><?php echo htmlspecialchars($order['user_name']); ?></span>
                                                    <small><?php echo htmlspecialchars($order['user_email']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['business_name']); ?></td>
                                            <td>
                                                <?php
                                                // Get order items count
                                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE order_id = ?");
                                                $stmt->execute([$order['id']]);
                                                $items_count = $stmt->fetch()['count'];
                                                echo $items_count . ' ' . ($items_count != 1 ? 's' : '');
                                                ?>
                                            </td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td><?php echo isset($order['payment_method']) ? ucfirst($order['payment_method']) : 'N/A'; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                            <td class="actions">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm" title="View Details"><i class="fas fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm">&laquo; Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        /* Additional styles for order management page */
        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }
        
        .filter-tab {
            padding: 10px 15px;
            color: var(--gray-color);
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        
        .filter-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        
        .count-badge {
            background-color: #f0f0f0;
            color: var(--gray-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-info small {
            color: var(--gray-color);
            font-size: 0.8rem;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        /* Dropdown menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            cursor: pointer;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .dropdown-item {
            display: block;
            padding: 8px 15px;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        
        .dropdown-item:hover {
            background-color: #f5f5f5;
        }
        
        .dropdown:hover .dropdown-menu {
            display: block;
        }
    </style>
    
    <script src="js/admin.js"></script>
</body>
</html>
