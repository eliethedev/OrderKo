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
                       WHERE o.customer_id = ? AND o.status != 'completed'
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
    <link rel="stylesheet" href="src/order-progress.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <button class="tab-button <?php echo empty($completed_orders) ? 'active' : ''; ?>" onclick="showOrderTab('active')">Active</button>
            <button class="tab-button <?php echo !empty($completed_orders) ? 'active' : ''; ?>" onclick="showOrderTab('completed')">Completed</button>
        </section>

        <!-- Active Orders -->
        <section id="active-orders" class="order-section">
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
                            <p><?php echo date('l, F j, Y, g:i A', strtotime($order['pickup_date'])); ?></p>
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
        </section>

        <!-- Completed Orders -->
        <section id="completed-orders" class="order-section">
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
                            <p><?php echo date('l, F j, Y, g:i A', strtotime($order['pickup_date'])); ?></p>
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
                    <button class="primary-button" onclick="window.open('https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($order['business_address']); ?>', '_blank')">
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

    <script>
        // Function to switch between active and completed order tabs
        function showOrderTab(tabName) {
            // Hide all tabs
            document.getElementById('active-orders').style.display = 'none';
            document.getElementById('completed-orders').style.display = 'none';
            
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
        
        // Function to show the cancel order modal
        function showCancelOrderModal(orderId) {
            document.getElementById('order-id-to-cancel').value = orderId;
            document.getElementById('cancel-order-modal').style.display = 'block';
        }
        
        // Function to close the cancel order modal
        function closeCancelOrderModal() {
            document.getElementById('cancel-order-modal').style.display = 'none';
        }
        
        // Initialize tabs on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show active orders by default, or completed if there are no active orders
            const activeOrders = document.querySelectorAll('#active-orders .order-card');
            const completedOrders = document.querySelectorAll('#completed-orders .order-card');
            
            if (activeOrders.length === 0 && completedOrders.length === 0) {
                // No orders at all
                document.getElementById('active-orders').style.display = 'none';
                document.getElementById('completed-orders').style.display = 'none';
                document.getElementById('no-orders').style.display = 'block';
            } else if (activeOrders.length === 0) {
                // No active orders, show completed
                showOrderTab('completed');
            } else {
                // Show active orders by default
                showOrderTab('active');
            }
        });
    </script>
</body>
</html>