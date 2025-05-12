<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get cart items
$stmt = $pdo->prepare("SELECT ci.*, p.name, p.price, p.image_url, b.name as business_name, b.category 
                       FROM cart_items ci 
                       JOIN products p ON ci.product_id = p.id 
                       JOIN businesses b ON p.business_id = b.id 
                       WHERE ci.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
$service_fee = 20;
$grand_total = $total + $service_fee;

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get default address
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? AND is_default = 1");
$stmt->execute([$_SESSION['user_id']]);
$default_address = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Checkout</h1>
            <button class="icon-button"><i class="fas fa-ellipsis-v"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Delivery Address -->
        <section class="checkout-section">
            <h3>Delivery Address</h3>
            <div class="address-card <?php echo empty($default_address) ? 'empty' : ''; ?>">
                <?php if (empty($default_address)): ?>
                    <div class="empty-state">
                        <i class="fas fa-home"></i>
                        <p>No address saved</p>
                        <button class="secondary-button" onclick="location.href='addresses.php'">Add Address</button>
                    </div>
                <?php else: ?>
                    <div class="address-details">
                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p><?php echo htmlspecialchars($default_address['address']); ?></p>
                        <p><?php echo htmlspecialchars($default_address['city']); ?>, <?php echo htmlspecialchars($default_address['state']); ?></p>
                        <p><?php echo htmlspecialchars($default_address['postal_code']); ?></p>
                        <p><?php echo htmlspecialchars($user['phone'] ?? ''); ?></p>
                    </div>
                    <div class="address-actions">
                        <button class="icon-button" onclick="location.href='addresses.php'">
                            <i class="fas fa-edit" style="color: green; font-size: 1.2rem;"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Order Summary -->
        <section class="checkout-section">
            <h3>Order Summary</h3>
            <div class="order-summary">
                <?php foreach ($cart_items as $item): ?>
                <div class="order-item">
                    <div class="item-details">
                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                        <p class="item-options"><?php echo htmlspecialchars($item['options']); ?></p>
                    </div>
                    <div class="item-quantity"><?php echo $item['quantity']; ?> ×</div>
                    <div class="item-price"><?php echo htmlspecialchars($item['price']); ?></div>
                </div>
                <?php endforeach; ?>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo number_format($total, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Service Fee</span>
                    <span><?php echo number_format($service_fee, 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span><?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </section>

        <!-- Payment Method -->
        <section class="checkout-section">
            <h3>Payment Method</h3>
            <div class="payment-methods">
                <div class="payment-method active" data-method="cash">
                    <i class="fas fa-money-bill-wave"></i>
                    <div>
                        <h4>Cash on Delivery</h4>
                        <p>Pay when you pick up your order</p>
                    </div>
                </div>
                <div class="payment-method" data-method="card">
                    <i class="fas fa-credit-card"></i>
                    <div>
                        <h4>Credit/Debit Card</h4>
                        <p>Pay with your card</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pickup Details -->
        <section class="checkout-section">
            <h3>Pickup Details</h3>
            <div class="pickup-info">
                <div class="pickup-option">
                    <div class="pickup-option-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h4>Pickup Date</h4>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <p id="pickup_date"><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="pickup-option">
                    <div class="pickup-option-header">
                        <i class="fas fa-clock"></i>
                        <h4>Pickup Time</h4>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <p id="pickup_time"><?php echo date('g:i A'); ?></p>
                </div>
            </div>
        </section>

        <!-- Order Note -->
        <section class="checkout-section">
            <h3>Order Note</h3>
            <div class="note-container">
                <i class="fas fa-pencil-alt"></i>
                <input type="text" placeholder="Add a note for the seller..." id="order_note">
            </div>
        </section>
    </main>

    <!-- Place Order Button -->
    <div class="place-order-button-container">
        <button class="primary-button full-width" onclick="placeOrder()">
            Place Order - ₱<?php echo number_format($grand_total, 2); ?>
        </button>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
