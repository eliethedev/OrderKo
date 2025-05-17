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
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Get statistics
// Total businesses
$stmt = $pdo->query("SELECT COUNT(*) as total FROM businesses");
$totalBusinesses = $stmt->fetch()['total'];

// Total products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
$totalProducts = $stmt->fetch()['total'];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$totalOrders = $stmt->fetch()['total'];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetch()['total'];

// Get revenue statistics
$stmt = $pdo->query("SELECT 
    SUM(order_items.quantity * products.price) as total_revenue,
    COUNT(DISTINCT orders.id) as total_orders,
    COUNT(DISTINCT users.id) as total_customers
    FROM orders
    JOIN order_items ON orders.id = order_items.order_id
    JOIN products ON order_items.product_id = products.id
    JOIN users ON orders.customer_id = users.id
    WHERE orders.status = 'completed'");
$revenueStats = $stmt->fetch();

// Get top performing businesses
$stmt = $pdo->query("SELECT 
    businesses.name,
    COUNT(orders.id) as total_orders,
    SUM(order_items.quantity * products.price) as total_revenue
    FROM orders
    JOIN order_items ON orders.id = order_items.order_id
    JOIN products ON order_items.product_id = products.id
    JOIN businesses ON products.business_id = businesses.id
    WHERE orders.status = 'completed'
    GROUP BY businesses.id
    ORDER BY total_revenue DESC
    LIMIT 5");
$topBusinesses = $stmt->fetchAll();

// Get order status distribution
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$orderStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order trends (last 30 days)
$stmt = $pdo->query("SELECT 
    DATE(orders.created_at) as date,
    COUNT(*) as orders,
    SUM(order_items.quantity * products.price) as revenue
    FROM orders
    JOIN order_items ON orders.id = order_items.order_id
    JOIN products ON order_items.product_id = products.id
    WHERE orders.status = 'completed'
    AND orders.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(orders.created_at)
    ORDER BY date ASC");
$orderTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - OrderKo</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .dashboard-card h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .stat-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .stat-box i {
            font-size: 24px;
            color: #4CAF50;
        }
        .stat-box .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .stat-box .label {
            color: #666;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-badge.pending {
            background: #ffc107;
            color: #333;
        }
        .status-badge.confirmed {
            background: #17a2b8;
            color: white;
        }
        .status-badge.preparing {
            background: #6c757d;
            color: white;
        }
        .status-badge.ready {
            background: #28a745;
            color: white;
        }
        .status-badge.completed {
            background: #4CAF50;
            color: white;
        }
        .status-badge.cancelled {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>Reports & Analytics</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>

            <!-- Overview Cards -->
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="stat-box">
                        <i class="fas fa-store"></i>
                        <div>
                            <span class="value"><?php echo $totalBusinesses; ?></span>
                            <span class="label">Total Businesses</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="stat-box">
                        <i class="fas fa-box"></i>
                        <div>
                            <span class="value"><?php echo $totalProducts; ?></span>
                            <span class="label">Total Products</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="stat-box">
                        <i class="fas fa-users"></i>
                        <div>
                            <span class="value"><?php echo $totalUsers; ?></span>
                            <span class="label">Total Users</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="stat-box">
                        <i class="fas fa-shopping-cart"></i>
                        <div>
                            <span class="value"><?php echo $totalOrders; ?></span>
                            <span class="label">Total Orders</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Statistics -->
            <div class="dashboard-card">
                <h3>Revenue Statistics</h3>
                <div class="stat-box">
                    <i class="fas fa-money-bill-wave"></i>
                    <div>
                        <span class="value">₱<?php echo number_format($revenueStats['total_revenue'], 2); ?></span>
                        <span class="label">Total Revenue</span>
                    </div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-shopping-bag"></i>
                    <div>
                        <span class="value"><?php echo $revenueStats['total_orders']; ?></span>
                        <span class="label">Total Orders</span>
                    </div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-user"></i>
                    <div>
                        <span class="value"><?php echo $revenueStats['total_customers']; ?></span>
                        <span class="label">Total Customers</span>
                    </div>
                </div>
            </div>

            <!-- Order Trends Chart -->
            <div class="chart-container">
                <h3>Order Trends (Last 30 Days)</h3>
                <canvas id="orderTrendsChart"></canvas>
            </div>

            <!-- Top Performing Businesses -->
            <div class="dashboard-card">
                <h3>Top Performing Businesses</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Total Orders</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topBusinesses as $business): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($business['name']); ?></td>
                                <td><?php echo $business['total_orders']; ?></td>
                                <td>₱<?php echo number_format($business['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Order Status Distribution -->
            <div class="dashboard-card">
                <h3>Order Status Distribution</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalOrders = array_sum(array_column($orderStatus, 'count'));
                            foreach ($orderStatus as $status): 
                            ?>
                            <tr>
                                <td>
                                    <span class="status-badge <?php echo strtolower($status['status']); ?>">
                                        <?php echo ucfirst($status['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $status['count']; ?></td>
                                <td>
                                    <?php 
                                    $percentage = ($status['count'] / $totalOrders) * 100;
                                    echo number_format($percentage, 1) . '%';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Order Trends Chart
        const ctx = document.getElementById('orderTrendsChart').getContext('2d');
        const orderTrendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($orderTrends, 'date')); ?>,
                datasets: [
                    {
                        label: 'Number of Orders',
                        data: <?php echo json_encode(array_column($orderTrends, 'orders')); ?>,
                        borderColor: '#4CAF50',
                        tension: 0.1,
                        yAxisID: 'orders'
                    },
                    {
                        label: 'Revenue (₱)',
                        data: <?php echo json_encode(array_column($orderTrends, 'revenue')); ?>,
                        borderColor: '#2196F3',
                        tension: 0.1,
                        yAxisID: 'revenue'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    orders: {
                        type: 'linear',
                        position: 'left',
                        ticks: {
                            beginAtZero: true
                        }
                    },
                    revenue: {
                        type: 'linear',
                        position: 'right',
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '₱' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
                            <?php 
                            $totalOrders = array_sum(array_column($orderStatus, 'count'));
                            foreach ($orderStatus as $status): 
                            ?>
                            <tr>
                                <td>
                                    <span class="status-badge <?php echo strtolower($status['status']); ?>">
                                        <?php echo ucfirst($status['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $status['count']; ?></td>
                                <td>
                                    <?php 
                                    $percentage = ($status['count'] / $totalOrders) * 100;
                                    echo number_format($percentage, 1) . '%';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Chart
        let orderTrendsChart;
        let currentRange = 30;

        function updateChart(range) {
            document.getElementById('chartLoading').style.display = 'block';
            
            // Get data based on selected range
            fetch(`get_order_trends.php?range=${range}`)
                .then(response => response.json())
                .then(data => {
                    // Initialize chart if not exists
                    if (!orderTrendsChart) {
                        const ctx = document.getElementById('orderTrendsChart').getContext('2d');
                        orderTrendsChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: data.labels,
                                datasets: [
                                    {
                                        label: 'Number of Orders',
                                        data: data.orders,
                                        borderColor: '#4CAF50',
                                        tension: 0.1,
                                        yAxisID: 'orders',
                                        fill: true,
                                        backgroundColor: 'rgba(76, 175, 80, 0.1)'
                                    },
                                    {
                                        label: 'Revenue (₱)',
                                        data: data.revenue,
                                        borderColor: '#2196F3',
                                        tension: 0.1,
                                        yAxisID: 'revenue',
                                        fill: true,
                                        backgroundColor: 'rgba(33, 150, 243, 0.1)'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    orders: {
                                        type: 'linear',
                                        position: 'left',
                                        ticks: {
                                            beginAtZero: true,
                                            font: {
                                                size: 12
                                            }
                                        },
                                        grid: {
                                            color: '#e2e8f0'
                                        }
                                    },
                                    revenue: {
                                        type: 'linear',
                                        position: 'right',
                                        ticks: {
                                            beginAtZero: true,
                                            callback: function(value) {
                                                return '₱' + value;
                                            },
                                            font: {
                                                size: 12
                                            }
                                        },
                                        grid: {
                                            color: '#e2e8f0'
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: {
                                            font: {
                                                size: 12
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        // Update existing chart
                        orderTrendsChart.data.labels = data.labels;
                        orderTrendsChart.data.datasets[0].data = data.orders;
                        orderTrendsChart.data.datasets[1].data = data.revenue;
                        orderTrendsChart.update();
                    }
                    document.getElementById('chartLoading').style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('chartLoading').style.display = 'none';
                    document.getElementById('orderTrendsChart').innerHTML = `
                        <div class="error-message">
                            Error loading data. Please try again later.
                        </div>
                    `;
                });
        }

        // Initial load
        updateChart(currentRange);

        // Handle date range change
        document.getElementById('dateRange').addEventListener('change', function() {
            currentRange = this.value;
            updateChart(currentRange);
        });
    </script>
</body>
</html>