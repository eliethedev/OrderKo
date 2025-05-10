<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch order details
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.category, b.sub_category, b.address as business_address, 
                              b.phone as business_phone, b.image_url as business_image 
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Fetch order items
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.price, p.image_url 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Calculate progress
$statuses = ['confirmed', 'preparing', 'ready', 'completed'];
$current_status = array_search($order['status'], $statuses);
$progress_width = ($current_status + 1) / count($statuses) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Order #<?php echo $order['order_number']; ?></h1>
            <button class="icon-button"><i class="fas fa-ellipsis-v"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Order Status -->
        <section class="order-status-card">
            <div class="status-header">
                <h3>Order Status</h3>
                <div class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></div>
            </div>
            <div class="order-progress">
                <div class="progress-track">
                    <div class="progress-fill" style="width: <?php echo $progress_width; ?>%;"></div>
                </div>
                <div class="progress-steps">
                    <div class="progress-step <?php echo $current_status >= 0 ? 'completed' : ''; ?>">
                        <div class="step-icon"><i class="fas fa-check"></i></div>
                        <span>Confirmed</span>
                        <small><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                    </div>
                    <div class="progress-step <?php echo $current_status >= 1 ? 'completed' : ($current_status == 1 ? 'active' : ''); ?>">
                        <div class="step-icon"><i class="fas fa-utensils"></i></div>
                        <span>Preparing</span>
                        <small><?php echo $order['status'] == 'preparing' ? date('g:i A') : '-'; ?></small>
                    </div>
                    <div class="progress-step <?php echo $current_status >= 2 ? 'completed' : ($current_status == 2 ? 'active' : ''); ?>">
                        <div class="step-icon"><i class="fas fa-box"></i></div>
                        <span>Ready</span>
                        <small><?php echo $order['status'] == 'ready' ? date('g:i A') : '-'; ?></small>
                    </div>
                    <div class="progress-step <?php echo $current_status >= 3 ? 'completed' : ($current_status == 3 ? 'active' : ''); ?>">
                        <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                        <span>Completed</span>
                        <small><?php echo $order['status'] == 'completed' ? date('g:i A') : '-'; ?></small>
                    </div>
                </div>
            </div>
            <div class="status-message">
                <i class="fas fa-info-circle"></i>
                <p><?php echo ucfirst($order['status']); ?>: <?php echo htmlspecialchars($order['status_message']); ?></p>
            </div>
        </section>

        <!-- Business Info -->
        <section class="order-business-card">
            <div class="business-header">
                <img src="<?php echo htmlspecialchars($order['business_image']); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                <div>
                    <h3><?php echo htmlspecialchars($order['business_name']); ?></h3>
                    <p><?php echo htmlspecialchars($order['category']); ?> • <?php echo htmlspecialchars($order['sub_category']); ?></p>
                </div>
            </div>
            <div class="business-actions">
                <button class="action-button" onclick="window.location.href='tel:<?php echo htmlspecialchars($order['business_phone']); ?>'">
                    <i class="fas fa-phone"></i> Call
                </button>
                <button class="action-button" onclick="window.location.href='https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($order['business_address']); ?>'">
                    <i class="fas fa-map-marked-alt"></i> Directions
                </button>
            </div>
        </section>

        <!-- Pickup Details -->
        <section class="pickup-details-card">
            <h3>Pickup Details</h3>
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
        </section>

        <!-- Order Items -->
        <section class="order-items-card">
            <h3>Order Items</h3>
            <?php foreach ($order_items as $item): ?>
            <div class="order-item">
                <div class="item-quantity"><?php echo $item['quantity']; ?> ×</div>
                <div class="item-details">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <p class="item-options">Size: Small (6")</p>
                </div>
                <div class="item-price">₱350.00</div>
            </div>
            <div class="order-item">
                <div class="item-quantity">1 ×</div>
                <div class="item-details">
                    <h4>Pandesal</h4>
                    <p class="item-options">10 pieces</p>
                </div>
                <div class="item-price">₱45.00</div>
            </div>
            <div class="order-note">
                <h5>Note to Seller</h5>
                <p>Please write "Happy Birthday" on the cake. Thank you!</p>
            </div>
        </section>

        <!-- Payment Summary -->
        <section class="payment-summary-card">
            <h3>Payment Summary</h3>
            <div class="summary-row">
                <span>Subtotal</span>
                <span>₱395.00</span>
            </div>
            <div class="summary-row">
                <span>Service Fee</span>
                <span>₱20.00</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span>₱415.00</span>
            </div>
            <div class="payment-method">
                <h5>Payment Method</h5>
                <div class="payment-info">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Cash on Pickup</span>
                </div>
            </div>
        </section>

        <!-- Order Info -->
        <section class="order-info-card">
            <div class="info-row">
                <span>Order Number</span>
                <span>ORD12345</span>
            </div>
            <div class="info-row">
                <span>Order Date</span>
                <span>May 6, 2025, 1:30 PM</span>
            </div>
        </section>

        <!-- Help Section -->
        <section class="help-section">
            <button class="help-button">
                <i class="fas fa-question-circle"></i>
                <span>Need help with this order?</span>
                <i class="fas fa-chevron-right"></i>
            </button>
        </section>
    </main>

    <script src="js/script.js"></script>
</body>
</html>