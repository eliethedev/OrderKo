<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch cart items from database
$stmt = $pdo->prepare("SELECT ci.*, p.name, p.price, p.image_url, b.name as business_name, b.category, b.sub_category 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Your Cart</h1>
            <button class="icon-button"><i class="fas fa-ellipsis-v"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Business Info -->
        <?php if (!empty($cart_items)): ?>
        <section class="cart-business">
            <div class="cart-business-info">
                <h3><?php echo htmlspecialchars($cart_items[0]['business_name']); ?></h3>
                <p><?php echo htmlspecialchars($cart_items[0]['category']); ?> • <?php echo htmlspecialchars($cart_items[0]['sub_category']); ?></p>
            </div>
        </section>

        <!-- Cart Items -->
        <section class="cart-items">
            <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <div class="cart-item-image" style="background-image: url('<?php echo htmlspecialchars($item['image_url']); ?>')"></div>
                <div class="cart-item-details">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <p class="cart-item-options"><?php echo htmlspecialchars($item['options']); ?></p>
                    <div class="cart-item-price"><?php echo htmlspecialchars($item['price']); ?></div>
                    <div class="cart-item-quantity">
                        <button class="quantity-button small" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                        <span><?php echo $item['quantity']; ?></span>
                        <button class="quantity-button small" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                    </div>
                </div>
                <button class="remove-item" onclick="removeItem(<?php echo $item['id']; ?>)"><i class="fas fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Add Note -->
        <section class="cart-note">
            <div class="note-container">
                <i class="fas fa-pencil-alt"></i>
                <input type="text" placeholder="Add a note for the seller..." id="order_note">
            </div>
        </section>

        <!-- Pickup Details -->
        <section class="pickup-details">
            <h3>Pickup Details</h3>
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
        </section>

        <!-- Order Summary -->
        <section class="order-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotal"><?php echo number_format($total, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Service Fee</span>
                <span id="service_fee"><?php echo number_format($service_fee, 2); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="grand_total"><?php echo number_format($grand_total, 2); ?></span>
            </div>
        </section>
    </main>

    <!-- Checkout Button -->
    <div class="checkout-button-container">
        <button class="primary-button full-width" onclick="location.href='checkout.php'">
            Proceed to Checkout
        </button>
    </div>

    <script src="script.js"></script>
</body>
</html>