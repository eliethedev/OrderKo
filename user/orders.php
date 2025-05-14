<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch active orders (excluding completed and cancelled orders)
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.image_url as business_image, 
                              b.address as business_address, b.category
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.customer_id = ? AND o.status NOT IN ('completed', 'cancelled')
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$active_orders = $stmt->fetchAll();

// Fetch cancelled orders
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.image_url as business_image, 
                              b.address as business_address, b.category
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.customer_id = ? AND o.status = 'cancelled'
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$cancelled_orders = $stmt->fetchAll();

// Fetch completed orders
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.image_url as business_image, 
                              b.address as business_address, b.category
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.customer_id = ? AND o.status = 'completed'
                       ORDER BY o.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$completed_orders = $stmt->fetchAll();

// Function to get order items
function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT oi.quantity, oi.price as item_price, p.name, p.price, p.description, p.image_url 
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

// Function to get order total
function getOrderTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item['item_price'] * $item['quantity'];
    }
    return $total;
}

// Function to generate reference number
function formatReferenceNumber($order_id) {
    return 'ORD' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Orders - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/order-progress.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Empty state styling */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .empty-state p {
            color: #777;
            margin-bottom: 20px;
        }
        
        /* Order item styling */
        .order-item-detail {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .item-image-container {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-info {
            flex-grow: 1;
        }
        
        .item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .item-description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .item-price {
            font-weight: bold;
            color: #ff6b6b;
        }
        
        /* Order details styling */
        .order-details {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .order-summary, .order-info, .pickup-info {
            margin-bottom: 15px;
        }
        
        .order-summary h4, .order-info h4, .pickup-info h4 {
            margin-bottom: 10px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .summary-row, .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .summary-row.total {
            font-weight: bold;
            color: #ff6b6b;
            border-top: 1px dashed #ddd;
            padding-top: 8px;
            margin-top: 8px;
        }
        
        .info-row i {
            margin-right: 5px;
            color: #666;
        }
        
        /* Responsive styling for pickup info */
        .pickup-info .info-row {
            flex-direction: column;
            margin-bottom: 12px;
        }
        
        .pickup-info .info-label {
            font-weight: bold;
            margin-bottom: 4px;
            color: #555;
        }
        
        .pickup-info .info-value {
            color: #333;
            word-break: break-word;
        }
        
        /* Collapsible section styling */
        .collapsible-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        
        .collapsible-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            color: #333;
        }
        
        .collapsible-header h4 i {
            margin-right: 8px;
            color: #666;
        }
        
        .collapsible-header .toggle-icon {
            transition: transform 0.3s ease;
            color: #666;
        }
        
        .collapsible-header .toggle-icon.open {
            transform: rotate(180deg);
        }
        
        .collapsible-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .collapsible-content.open {
            max-height: 1000px; /* Arbitrary large value */
        }
        
        .order-card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .order-summary-preview {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            background-color: #f5f5f5;
            font-weight: bold;
            border-top: 1px dashed #ddd;
        }
        
        /* Media queries for better responsiveness */
        @media (max-width: 480px) {
            .info-row {
                flex-direction: column;
            }
            
            .info-row span:first-child {
                margin-bottom: 4px;
                font-weight: bold;
            }
            
            .summary-row span:last-child {
                font-weight: bold;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
        <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Your Orders</h1>
            <?php include_once 'includes/cart_icon.php'; ?>
            <button class="icon-button"><i class="fas fa-search"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Order Tabs -->
        <section class="order-tabs">
            <button class="tab-button active" onclick="showOrderTab('active')">Active</button>
            <button class="tab-button" onclick="showOrderTab('completed')">Completed</button>
            <?php if (!empty($cancelled_orders)): ?>
            <button class="tab-button" onclick="showOrderTab('cancelled')">Cancelled</button>
            <?php endif; ?>
        </section>

        <!-- Active Orders -->
        <section id="active-orders" class="order-section">
            <?php if (empty($active_orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>No active orders</h3>
                <p>Your active orders will appear here</p>
                <button class="primary-button" onclick="location.href='businesses.php'">Order Now</button>
            </div>
            <?php else: ?>
            <?php foreach ($active_orders as $order): ?>
            <div class="order-card active">
                <div class="order-header">
                    <div class="order-business">
                        <img src="<?php echo htmlspecialchars($order['business_image'] ?? 'images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                        <div>
                            <h4><?php echo htmlspecialchars($order['business_name']); ?></h4>
                            <p class="order-date"><?php echo date('l, F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></div>
                </div>
                <div class="order-progress">
                    <!-- Progress Status Text -->
                    <div class="progress-status-text">
                        <?php 
                        $status_text = '';
                        switch($order['status']) {
                            case 'pending':
                                $status_text = 'Your order is waiting for confirmation from the business';
                                break;
                            case 'confirmed':
                                $status_text = 'Your order has been confirmed and will be prepared soon';
                                break;
                            case 'preparing':
                                $status_text = 'Your order is being prepared by the business';
                                break;
                            case 'ready':
                                $status_text = 'Your order is ready for pickup!';
                                break;
                            case 'on_delivery':
                                $status_text = 'Your order is on the way to you!';
                                break;
                            case 'completed':
                                $status_text = 'Your order has been completed';
                                break;
                            case 'cancelled':
                                $status_text = 'This order has been cancelled';
                                break;
                        }
                        echo $status_text;
                        ?>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="progress-track">
                        <?php 
                        $progress_width = '0%';
                        switch($order['status']) {
                            case 'pending':
                                $progress_width = '10%';
                                break;
                            case 'confirmed':
                                $progress_width = '35%';
                                break;
                            case 'preparing':
                                $progress_width = '60%';
                                break;
                            case 'ready':
                                $progress_width = '85%';
                                break;
                            case 'on_delivery':
                                $progress_width = '95%';
                                break;
                            case 'completed':
                                $progress_width = '100%';
                                break;
                            case 'cancelled':
                                $progress_width = '0%';
                                break;
                        }
                        ?>
                        <div class="progress-fill <?php echo $order['status'] == 'cancelled' ? 'cancelled' : ''; ?>" style="width: <?php echo $progress_width; ?>"></div>
                    </div>
                    
                    <!-- Progress Steps -->
                    <div class="progress-steps">
                        <?php if ($order['status'] == 'cancelled'): ?>
                        <!-- Special display for cancelled orders -->
                        <div class="progress-step cancelled active">
                            <div class="step-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <span>Cancelled</span>
                        </div>
                        <?php else: ?>
                        <div class="progress-step <?php echo in_array($order['status'], ['confirmed', 'preparing', 'ready', 'completed']) ? 'completed' : ($order['status'] == 'pending' ? 'active' : ''); ?>">
                            <div class="step-icon">
                                <?php if ($order['status'] == 'pending'): ?>
                                <i class="fas fa-spinner fa-pulse"></i>
                                <?php else: ?>
                                <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <span>Pending</span>
                        </div>
                        <div class="progress-step <?php echo in_array($order['status'], ['preparing', 'ready', 'completed']) ? 'completed' : ($order['status'] == 'confirmed' ? 'active' : ''); ?>">
                            <div class="step-icon">
                                <?php if ($order['status'] == 'confirmed'): ?>
                                <i class="fas fa-spinner fa-pulse"></i>
                                <?php else: ?>
                                <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <span>Confirmed</span>
                        </div>
                        <div class="progress-step <?php echo in_array($order['status'], ['ready', 'on_delivery', 'completed']) ? 'completed' : ($order['status'] == 'preparing' ? 'active' : ''); ?>">
                            <div class="step-icon">
                                <?php if ($order['status'] == 'preparing'): ?>
                                <i class="fas fa-sync fa-spin"></i>
                                <?php else: ?>
                                <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <span>Processing</span>
                        </div>
                        <div class="progress-step <?php echo in_array($order['status'], ['on_delivery', 'completed']) ? 'completed' : ($order['status'] == 'ready' ? 'active' : ''); ?>">
                            <div class="step-icon">
                                <?php if ($order['status'] == 'ready'): ?>
                                <i class="fas fa-box"></i>
                                <?php else: ?>
                                <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <span>Ready</span>
                        </div>
                        <div class="progress-step <?php echo $order['status'] == 'completed' ? 'completed' : ($order['status'] == 'on_delivery' ? 'active' : ''); ?>">
                            <div class="step-icon">
                                <?php if ($order['status'] == 'on_delivery'): ?>
                                <i class="fas fa-shipping-fast"></i>
                                <?php else: ?>
                                <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </div>
                            <span>On Delivery</span>
                        </div>
                        <div class="progress-step <?php echo $order['status'] == 'completed' ? 'active' : ''; ?>">
                            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                            <span>Completed</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="order-summary-preview">
                    <span>Items (<?php echo count(getOrderItems($pdo, $order['id'])); ?>)</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                
                <div class="collapsible-header" onclick="toggleCollapsible(this)">
                    <h4><i class="fas fa-shopping-basket"></i> Order Items</h4>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="collapsible-content">
                    <div class="order-items">
                        <?php
                        $items = getOrderItems($pdo, $order['id']);
                        $order_total = getOrderTotal($items);
                        foreach ($items as $item):
                        ?>
                        <div class="order-item-detail">
                            <div class="item-image-container">
                                <?php 
                                $image_path = !empty($item['image_url']) ? $item['image_url'] : '../images/placeholder.jpg';
                                // Make sure image path is absolute if it's not already
                                if (!empty($item['image_url']) && strpos($item['image_url'], 'http') !== 0 && strpos($item['image_url'], '/') !== 0) {
                                    $image_path = '../' . $item['image_url'];
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                            </div>
                            <div class="item-info">
                                <p class="item-name"><?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['quantity']; ?>×)</p>
                                <p class="item-description"><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 50) . (strlen($item['description'] ?? '') > 50 ? '...' : '')); ?></p>
                                <p class="item-price">₱<?php echo number_format($item['item_price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="order-details">
                    <div class="collapsible-header" onclick="toggleCollapsible(this)">
                        <h4><i class="fas fa-receipt"></i> Order Summary</h4>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="collapsible-content">
                        <div class="order-summary">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span>₱<?php echo number_format($order_total, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Service Fee:</span>
                                <span>₱20.00</span>
                            </div>
                            <div class="summary-row total">
                                <span>Total Amount:</span>
                                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="collapsible-header" onclick="toggleCollapsible(this)">
                        <h4><i class="fas fa-info-circle"></i> Order Details</h4>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="collapsible-content">
                        <div class="order-info">
                            <div class="info-row">
                                <span><i class="fas fa-hashtag"></i> Reference Number:</span>
                                <span><?php echo formatReferenceNumber($order['id']); ?></span>
                            </div>
                            <div class="info-row">
                                <span><i class="fas fa-calendar-alt"></i> Order Date:</span>
                                <span><?php echo date('F j, Y, g:i A', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span><i class="fas fa-money-bill-wave"></i> Payment Method:</span>
                                <span>Cash on Pickup</span>
                            </div>
                            <?php if (!empty($order['special_instructions'])): ?>
                            <div class="info-row">
                                <span><i class="fas fa-comment"></i> Special Instructions:</span>
                                <span><?php echo htmlspecialchars($order['special_instructions']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="collapsible-header" onclick="toggleCollapsible(this)">
                        <h4><i class="fas fa-map-marker-alt"></i> Pickup Details</h4>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="collapsible-content">
                        <div class="pickup-info">
                            <div class="info-row pickup-time">
                                <div class="info-label"><i class="fas fa-clock"></i> Pickup Time:</div>
                                <div class="info-value"><?php echo date('l, F j, Y, g:i A', strtotime($order['pickup_date'])); ?></div>
                            </div>
                            <div class="info-row pickup-location">
                                <div class="info-label"><i class="fas fa-map-marker-alt"></i> Pickup Location:</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['business_address']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="order-actions">
                    <button class="primary-button" onclick="window.open('https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($order['business_address']); ?>', '_blank')">
                        <i class="fas fa-map-marked-alt"></i> Get Directions
                    </button>
                    <?php if ($order['status'] == 'pending'): ?>
                    <button class="danger-button full-width" onclick="showCancelOrderModal(<?php echo $order['id']; ?>)">
                        <i class="fas fa-times-circle"></i> Cancel Order
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Completed Orders -->
        <section id="completed-orders" class="order-section">
            <?php if (empty($completed_orders)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No completed orders</h3>
                <p>Your completed orders will appear here</p>
            </div>
            <?php else: ?>
            <?php foreach ($completed_orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-business">
                        <img src="<?php echo htmlspecialchars($order['business_image'] ?? 'images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                        <div>
                            <h4><?php echo htmlspecialchars($order['business_name']); ?></h4>
                            <p class="order-date"><?php echo date('l, F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="order-status completed">Completed</div>
                </div>
                <div class="order-summary-preview">
                    <span>Items (<?php echo count(getOrderItems($pdo, $order['id'])); ?>)</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                
                <div class="collapsible-header" onclick="toggleCollapsible(this)">
                    <h4><i class="fas fa-shopping-basket"></i> Order Items</h4>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="collapsible-content">
                    <div class="order-items">
                        <?php
                        $items = getOrderItems($pdo, $order['id']);
                        $order_total = getOrderTotal($items);
                        foreach ($items as $item):
                        ?>
                        <div class="order-item-detail">
                            <div class="item-image-container">
                                <?php 
                                $image_path = !empty($item['image_url']) ? $item['image_url'] : '../images/placeholder.jpg';
                                // Make sure image path is absolute if it's not already
                                if (!empty($item['image_url']) && strpos($item['image_url'], 'http') !== 0 && strpos($item['image_url'], '/') !== 0) {
                                    $image_path = '../' . $item['image_url'];
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                            </div>
                            <div class="item-info">
                                <p class="item-name"><?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['quantity']; ?>×)</p>
                                <p class="item-description"><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 50) . (strlen($item['description'] ?? '') > 50 ? '...' : '')); ?></p>
                                <p class="item-price">₱<?php echo number_format($item['item_price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="order-details">
                    <div class="order-summary">
                        <h4>Order Summary</h4>
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>₱<?php echo number_format($order_total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Service Fee:</span>
                            <span>₱20.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Amount:</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="order-info">
                        <div class="info-row">
                            <span><i class="fas fa-hashtag"></i> Reference Number:</span>
                            <span><?php echo formatReferenceNumber($order['id']); ?></span>
                        </div>
                        <div class="info-row">
                            <span><i class="fas fa-calendar-alt"></i> Order Date:</span>
                            <span><?php echo date('F j, Y, g:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span><i class="fas fa-money-bill-wave"></i> Payment Method:</span>
                            <span>Cash on Pickup</span>
                        </div>
                        <?php if (!empty($order['special_instructions'])): ?>
                        <div class="info-row">
                            <span><i class="fas fa-comment"></i> Special Instructions:</span>
                            <span><?php echo htmlspecialchars($order['special_instructions']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pickup-info">
                        <div class="info-row">
                            <span><i class="fas fa-clock"></i> Pickup Time:</span>
                            <span><?php echo date('l, F j, Y, g:i A', strtotime($order['pickup_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span><i class="fas fa-map-marker-alt"></i> Pickup Location:</span>
                            <span><?php echo htmlspecialchars($order['business_address']); ?></span>
                        </div>

                    </div>
                </div>
                <div class="order-actions">
                    <button class="primary-button" onclick="window.open('https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($order['business_address']); ?>', '_blank')">
                        <i class="fas fa-map-marked-alt"></i> Get Directions
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>



        <!-- Cancelled Orders -->
        <section id="cancelled-orders" class="order-section" style="display: none;">
            <?php if (empty($cancelled_orders)): ?>
            <div class="empty-state">
                <i class="fas fa-times-circle"></i>
                <h3>No cancelled orders</h3>
                <p>Your cancelled orders will appear here</p>
            </div>
            <?php else: ?>
            <?php foreach ($cancelled_orders as $order): ?>
            <div class="order-card cancelled">
                <div class="order-header">
                    <div class="order-business">
                        <img src="<?php echo htmlspecialchars($order['business_image'] ?? 'images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                        <div>
                            <h4><?php echo htmlspecialchars($order['business_name']); ?></h4>
                            <p class="order-date"><?php echo date('l, F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="order-status cancelled">Cancelled</div>
                </div>
                <div class="order-progress">
                    <!-- Progress Status Text -->
                    <div class="progress-status-text">
                        This order has been cancelled
                    </div>
                    
                    <!-- Progress Steps -->
                    <div class="progress-steps">
                        <div class="progress-step cancelled active">
                            <div class="step-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <span>Cancelled</span>
                        </div>
                    </div>
                </div>
                <div class="order-summary-preview">
                    <span>Items (<?php echo count(getOrderItems($pdo, $order['id'])); ?>)</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
                
                <div class="collapsible-header" onclick="toggleCollapsible(this)">
                    <h4><i class="fas fa-shopping-basket"></i> Order Items</h4>
                    <i class="fas fa-chevron-down toggle-icon"></i>
                </div>
                <div class="collapsible-content">
                    <div class="order-items">
                        <?php
                        $items = getOrderItems($pdo, $order['id']);
                        $order_total = getOrderTotal($items);
                        foreach ($items as $item):
                        ?>
                        <div class="order-item-detail">
                            <div class="item-image-container">
                                <?php 
                                $image_path = !empty($item['image_url']) ? $item['image_url'] : '../images/placeholder.jpg';
                                // Make sure image path is absolute if it's not already
                                if (!empty($item['image_url']) && strpos($item['image_url'], 'http') !== 0 && strpos($item['image_url'], '/') !== 0) {
                                    $image_path = '../' . $item['image_url'];
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                            </div>
                            <div class="item-info">
                                <p class="item-name"><?php echo htmlspecialchars($item['name']); ?> (<?php echo $item['quantity']; ?>×)</p>
                                <p class="item-description"><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 50) . (strlen($item['description'] ?? '') > 50 ? '...' : '')); ?></p>
                                <p class="item-price">₱<?php echo number_format($item['item_price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="order-pickup">
                    <div class="pickup-info">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h5>Cancelled On</h5>
                            <p><?php echo date('l, F j, Y, g:i A', strtotime($order['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- No Orders State (Hidden by default) -->
        <section id="no-orders" class="no-orders" style="display: none;">
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>No orders yet</h3>
                <p>Your order history will appear here</p>
                <button class="primary-button" onclick="location.href='businesses.php'">Explore Businesses</button>
            </div>
        </section>
    </main>

    <!-- Cancel Order Modal -->
    <div id="cancel-order-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Order</h3>
                <button class="close-button" onclick="closeCancelOrderModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this order? This action cannot be undone.</p>
                <div class="form-group">
                    <label for="cancel-reason">Reason for cancellation:</label>
                    <select id="cancel-reason" class="form-control">
                        <option value="Changed my mind">Changed my mind</option>
                        <option value="Ordered by mistake">Ordered by mistake</option>
                        <option value="Found better alternative">Found better alternative</option>
                        <option value="Taking too long">Taking too long</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group" id="other-reason-container" style="display: none;">
                    <label for="other-reason">Please specify:</label>
                    <textarea id="other-reason" class="form-control" rows="3"></textarea>
                </div>
                <input type="hidden" id="order-id-to-cancel" value="">
            </div>
            <div class="modal-footer">
                <button class="secondary-button" onclick="closeCancelOrderModal()">Cancel</button>
                <button class="danger-button" onclick="cancelOrder()">Confirm Cancellation</button>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>

    <script>
        // Function to switch between active and completed order tabs
        function showOrderTab(tabName) {
            // Hide all tabs
            document.getElementById('active-orders').style.display = 'none';
            document.getElementById('completed-orders').style.display = 'none';
            document.getElementById('cancelled-orders').style.display = 'none';
            
            // Show selected tab
            document.getElementById(tabName + '-orders').style.display = 'block';
            
            // Update active class on tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Find the clicked button and add active class
            const activeButton = Array.from(tabButtons).find(button => {
                return button.onclick.toString().includes(tabName);
            });
            
            if (activeButton) {
                activeButton.classList.add('active');
            }
        }
        
        // Function to toggle collapsible sections
        function toggleCollapsible(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            
            // Toggle the content
            if (content.classList.contains('open')) {
                content.classList.remove('open');
                icon.classList.remove('open');
            } else {
                content.classList.add('open');
                icon.classList.add('open');
            }
        }
        
        // Function to show the cancel order modal
        function showCancelOrderModal(orderId) {
            document.getElementById('order-id-to-cancel').value = orderId;
            document.getElementById('cancel-order-modal').style.display = 'block';
            
            // Reset the form
            document.getElementById('cancel-reason').value = 'Changed my mind';
            document.getElementById('other-reason').value = '';
            document.getElementById('other-reason-container').style.display = 'none';
        }
        
        // Function to close the cancel order modal
        function closeCancelOrderModal() {
            document.getElementById('cancel-order-modal').style.display = 'none';
        }
        
        // Function to cancel the order
        function cancelOrder() {
            const orderId = document.getElementById('order-id-to-cancel').value;
            const cancelReasonSelect = document.getElementById('cancel-reason');
            let reason = cancelReasonSelect.value;
            
            // If reason is 'Other', get the text from the textarea
            if (reason === 'Other') {
                const otherReason = document.getElementById('other-reason').value.trim();
                if (otherReason === '') {
                    alert('Please specify your cancellation reason');
                    return;
                }
                reason = otherReason;
            }
            
            // Show loading state
            const confirmButton = document.querySelector('.modal-footer .danger-button');
            const originalText = confirmButton.innerHTML;
            confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmButton.disabled = true;
            
            // Send cancellation request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'cancel_order.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Close the modal
                            closeCancelOrderModal();
                            
                            // Show success message
                            alert('Order cancelled successfully');
                            
                            // Reload the page to update the order status
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.message);
                            // Reset button
                            confirmButton.innerHTML = originalText;
                            confirmButton.disabled = false;
                        }
                    } catch (e) {
                        alert('Error processing response');
                        console.error('Error parsing response:', e);
                        // Reset button
                        confirmButton.innerHTML = originalText;
                        confirmButton.disabled = false;
                    }
                } else {
                    alert('Error: Server returned status ' + xhr.status);
                    // Reset button
                    confirmButton.innerHTML = originalText;
                    confirmButton.disabled = false;
                }
            };
            
            xhr.onerror = function() {
                alert('Error: Could not connect to the server');
                // Reset button
                confirmButton.innerHTML = originalText;
                confirmButton.disabled = false;
            };
            
            xhr.send('order_id=' + encodeURIComponent(orderId) + '&reason=' + encodeURIComponent(reason));
        }
        
        // Initialize tabs on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show active orders by default, or completed if there are no active orders
            const activeOrders = document.querySelectorAll('#active-orders .order-card');
            const completedOrders = document.querySelectorAll('#completed-orders .order-card');
            
            const cancelledOrders = document.querySelectorAll('#cancelled-orders .order-card');
            
            if (activeOrders.length === 0 && completedOrders.length === 0 && cancelledOrders.length === 0) {
                // No orders at all
                document.getElementById('active-orders').style.display = 'none';
                document.getElementById('completed-orders').style.display = 'none';
                document.getElementById('cancelled-orders').style.display = 'none';
                document.getElementById('no-orders').style.display = 'block';
            } else if (activeOrders.length === 0 && completedOrders.length > 0) {
                // No active orders, show completed
                showOrderTab('completed');
            } else {
                // Show active orders by default
                showOrderTab('active');
            } 
            
            // Add event listener for cancel reason dropdown
            const cancelReasonSelect = document.getElementById('cancel-reason');
            cancelReasonSelect.addEventListener('change', function() {
                const otherReasonContainer = document.getElementById('other-reason-container');
                if (this.value === 'Other') {
                    otherReasonContainer.style.display = 'block';
                } else {
                    otherReasonContainer.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
<?php /* End of file */ ?>
