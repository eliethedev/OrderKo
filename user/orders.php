<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch active orders
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.image_url as business_image, 
                              b.address as business_address
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.customer_id = ? AND o.status IN ('confirmed', 'preparing', 'ready')
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$active_orders = $stmt->fetchAll();

// Fetch completed orders
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.image_url as business_image, 
                              b.address as business_address
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.customer_id = ? AND o.status = 'completed'
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$completed_orders = $stmt->fetchAll();

// Function to get order items
function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT oi.quantity, p.name, p.price 
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Orders - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <h1>Your Orders</h1>
            <button class="icon-button"><i class="fas fa-search"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Order Tabs -->
        <section class="order-tabs">
            <button class="tab-button <?php echo empty($completed_orders) ? 'active' : ''; ?>" onclick="showOrderTab('active')">Active</button>
            <button class="tab-button <?php echo !empty($completed_orders) ? 'active' : ''; ?>" onclick="showOrderTab('completed')">Completed</button>
        </section>

        <!-- Active Orders -->
        <section id="active-orders" class="order-section">
            <?php foreach ($active_orders as $order): ?>
            <div class="order-card active">
                <div class="order-header">
                    <div class="order-business">
                        <img src="<?php echo htmlspecialchars($order['logo']); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                        <div>
                            <h4><?php echo htmlspecialchars($order['business_name']); ?></h4>
                            <p class="order-date"><?php echo date('l, F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></div>
                </div>
                <div class="order-progress">
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo ($order['status'] == 'confirmed' ? '25%' : ($order['status'] == 'preparing' ? '50%' : ($order['status'] == 'ready' ? '75%' : '100%'))); ?>;"></div>
                    </div>
                    <div class="progress-steps">
                        <div class="progress-step <?php echo $order['status'] != 'confirmed' ? 'completed' : ''; ?>">
                            <div class="step-icon"><i class="fas fa-check"></i></div>
                            <span>Confirmed</span>
                        </div>
                        <div class="progress-step <?php echo $order['status'] != 'preparing' ? 'completed' : ($order['status'] == 'preparing' ? 'active' : ''); ?>">
                            <div class="step-icon"><i class="fas fa-utensils"></i></div>
                            <span>Preparing</span>
                        </div>
                        <div class="progress-step <?php echo $order['status'] != 'ready' ? 'completed' : ($order['status'] == 'ready' ? 'active' : ''); ?>">
                            <div class="step-icon"><i class="fas fa-box"></i></div>
                            <span>Ready</span>
                        </div>
                        <div class="progress-step <?php echo $order['status'] != 'completed' ? 'completed' : ($order['status'] == 'completed' ? 'active' : ''); ?>">
                            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                            <span>Completed</span>
                        </div>
                    </div>
                </div>
                <div class="order-items">
                    <?php
                    $items = getOrderItems($pdo, $order['id']);
                    foreach ($items as $item):
                    ?>
                    <p><?php echo $item['quantity']; ?> × <?php echo htmlspecialchars($item['name']); ?></p>
                    <?php endforeach; ?>
                </div>
                <div class="order-pickup">
                    <div class="pickup-info">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h5>Pickup Time</h5>
                            <p><?php echo date('l, F j, Y', strtotime($order['pickup_date'])); ?>, <?php echo $order['pickup_time']; ?></p>
                        </div>
                    </div>
                    <div class="pickup-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h5>Pickup Location</h5>
                            <p><?php echo htmlspecialchars($order['business_address']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="order-actions">
                    <button class="secondary-button" onclick="window.location.href='tel:<?php echo htmlspecialchars($order['business_phone']); ?>'">
                        <i class="fas fa-phone"></i> Call Business
                    </button>
                    <button class="primary-button" onclick="window.location.href='https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($order['business_address']); ?>'">
                        <i class="fas fa-map-marked-alt"></i> Get Directions
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Completed Orders -->
            <section id="completed-orders" class="order-section">
                <?php foreach ($completed_orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-business">
                            <img src="<?php echo htmlspecialchars($order['logo']); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                            <div>
                                <h4><?php echo htmlspecialchars($order['business_name']); ?></h4>
                                <p class="order-date"><?php echo date('l, F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="order-status completed">Completed</div>
                    </div>
                    <div class="order-items">
                        <?php
                        $items = getOrderItems($pdo, $order['id']);
                        foreach ($items as $item):
                        ?>
                        <p><?php echo $item['quantity']; ?> × <?php echo htmlspecialchars($item['name']); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-pickup">
                        <div class="pickup-info">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h5>Pickup Time</h5>
                                <p><?php echo date('l, F j, Y', strtotime($order['pickup_date'])); ?>, <?php echo $order['pickup_time']; ?></p>
                            </div>
                        </div>
                        <div class="pickup-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h5>Pickup Location</h5>
                                <p><?php echo htmlspecialchars($order['business_address']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="order-actions">
                        <button class="secondary-button" onclick="window.location.href='tel:<?php echo htmlspecialchars($order['business_phone']); ?>'">
                            <i class="fas fa-phone"></i> Call Business
                        </button>
                        <button class="primary-button" onclick="window.location.href='https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($order['business_address']); ?>'">
                            <i class="fas fa-map-marked-alt"></i> Get Directions
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
        </section>



        <!-- No Orders State (Hidden by default) -->
        <section id="no-orders" class="no-orders" style="display: none;">
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>No orders yet</h3>
                <p>Your order history will appear here</p>
                <button class="primary-button" onclick="location.href='businesses.html'">Explore Businesses</button>
            </div>
        </section>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="businesses.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Explore</span>
        </a>
        <a href="orders.php" class="nav-item active">
            <i class="fas fa-shopping-bag"></i>
            <span>Orders</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="js/script.js"></script>
</body>
</html>