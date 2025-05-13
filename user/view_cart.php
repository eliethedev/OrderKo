<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch cart items from database
$stmt = $pdo->prepare("SELECT ci.*, p.name, p.price, p.image_url, b.name as business_name, b.id as business_id, b.category 
                       FROM cart_items ci 
                       JOIN products p ON ci.product_id = p.id  
                       JOIN businesses b ON p.business_id = b.id 
                       WHERE ci.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

// Calculate cart totals
$subtotal = 0;
$item_count = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $item_count += $item['quantity'];
}

// Service fee and total
$service_fee = 20;
$total = $subtotal + $service_fee;

// Group items by business
$businesses = [];
foreach ($cart_items as $item) {
    $business_id = $item['business_id'];
    if (!isset($businesses[$business_id])) {
        $businesses[$business_id] = [
            'name' => $item['business_name'],
            'category' => $item['category'],
            'items' => []
        ];
    }
    $businesses[$business_id]['items'][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Cart Styles */
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .cart-count {
            font-size: 14px;
            color: #666;
        }
        
        .cart-business {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .cart-business-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .cart-business-info h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .cart-business-info p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #666;
        }
        
        .cart-business-actions {
            display: flex;
            gap: 10px;
        }
        
        .cart-item {
            display: flex;
            padding: 15px 0;
            border-top: 1px solid var(--color-border);
            position: relative;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            background-size: cover;
            background-position: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-details h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .cart-item-options {
            font-size: 14px;
            color: #666;
            margin: 0 0 5px 0;
        }
        
        .cart-item-price {
            font-weight: 600;
            color: var(--color-primary);
            margin-bottom: 5px;
        }
        
        .cart-item-subtotal {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .quantity-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-button.small {
            width: 26px;
            height: 26px;
            font-size: 12px;
        }
        
        .remove-item {
            position: absolute;
            top: 15px;
            right: 0;
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 14px;
        }
        
        .remove-item:hover {
            color: #e74c3c;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-cart-illustration {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-cart h2 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .empty-cart p {
            color: #666;
            margin-bottom: 25px;
        }
        
        .order-summary {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 80px;
            box-shadow: var(--shadow-sm);
        }
        
        .order-summary h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            border-top: 1px solid var(--color-border);
            padding-top: 10px;
            margin-top: 10px;
            font-weight: 600;
            font-size: 18px;
        }
        
        .checkout-button-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            padding: 15px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }
        
        .checkout-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background-color: #28a745;
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            font-weight: 500;
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
            text-align: center;
            max-width: 90%;
        }
        
        .toast-notification.error {
            background-color: #dc3545;
        }
        
        .toast-notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Your Cart</h1>
            <div style="width: 36px;"></div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <?php if (empty($cart_items)): ?>
        <!-- Empty Cart State -->
        <section class="empty-cart">
            <div class="empty-cart-illustration">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added any items to your cart yet.</p>
            <button class="primary-button" onclick="window.location.href='businesses.php'">
                <i class="fas fa-store"></i> Browse Businesses
            </button>
        </section>
        <?php else: ?>
        <!-- Cart Header -->
        <div class="cart-header">
            <div class="cart-count"><?php echo $item_count; ?> item<?php echo $item_count > 1 ? 's' : ''; ?> in your cart</div>
            <button class="text-button" onclick="clearCart()"><i class="fas fa-trash"></i> Clear Cart</button>
        </div>
        
        <!-- Cart Items by Business -->
        <?php foreach ($businesses as $business_id => $business): ?>
        <section class="cart-business">
            <div class="cart-business-info">
                <div>
                    <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                    <p><?php echo htmlspecialchars($business['category']); ?></p>
                </div>
                <div class="cart-business-actions">
                    <button class="secondary-button small" onclick="window.location.href='business-detail.php?id=<?php echo $business_id; ?>'">
                        <i class="fas fa-plus"></i> Add More
                    </button>
                </div>
            </div>
            
            <!-- Items from this business -->
            <?php foreach ($business['items'] as $item): ?>
            <div class="cart-item" data-item-id="<?php echo $item['id']; ?>">
                <div class="cart-item-image" style="background-image: url('../<?php echo htmlspecialchars($item['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                <div class="cart-item-details">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <?php if (!empty($item['options'])): ?>
                    <p class="cart-item-options"><?php echo htmlspecialchars($item['options']); ?></p>
                    <?php endif; ?>
                    <div class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                    <div class="cart-item-subtotal">Subtotal: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    <div class="cart-item-quantity">
                        <button class="quantity-button small" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-minus"></i>
                        </button>
                        <span id="quantity-<?php echo $item['id']; ?>"><?php echo $item['quantity']; ?></span>
                        <button class="quantity-button small" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)" <?php echo $item['quantity'] >= 10 ? 'disabled' : ''; ?>>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <button class="remove-item" onclick="removeItem(<?php echo $item['id']; ?>)"><i class="fas fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
        
        <!-- Order Summary -->
        <section class="order-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotal">₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Service Fee</span>
                <span>₱<?php echo number_format($service_fee, 2); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="total">₱<?php echo number_format($total, 2); ?></span>
            </div>
        </section>
        
        <!-- Checkout Button -->
        <div class="checkout-button-container">
            <button class="primary-button full-width checkout-button" onclick="window.location.href='checkout.php'">
                <i class="fas fa-shopping-bag"></i> Proceed to Checkout - ₱<?php echo number_format($total, 2); ?>
            </button>
        </div>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>

    <script>
    // Function to update quantity
    function updateQuantity(itemId, change) {
        // Get current quantity
        const quantityElement = document.getElementById(`quantity-${itemId}`);
        let currentQuantity = parseInt(quantityElement.textContent);
        let newQuantity = currentQuantity + change;
        
        // Validate quantity (1-10)
        if (newQuantity < 1 || newQuantity > 10) {
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('quantity', newQuantity);
        
        // Send AJAX request
        fetch('update_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update quantity display
                quantityElement.textContent = newQuantity;
                
                // Update subtotal display
                const subtotalElement = document.getElementById('subtotal');
                subtotalElement.textContent = `₱${data.subtotal.toFixed(2)}`;
                
                // Update total display
                const totalElement = document.getElementById('total');
                totalElement.textContent = `₱${data.total.toFixed(2)}`;
                
                // Update checkout button
                const checkoutButton = document.querySelector('.checkout-button');
                checkoutButton.innerHTML = `<i class="fas fa-shopping-bag"></i> Proceed to Checkout - ₱${data.total.toFixed(2)}`;
                
                // Update quantity buttons state
                const decreaseButton = quantityElement.previousElementSibling;
                const increaseButton = quantityElement.nextElementSibling;
                
                decreaseButton.disabled = (newQuantity <= 1);
                increaseButton.disabled = (newQuantity >= 10);
                
                // Show success message
                showToast('Cart updated successfully');
            } else {
                // Show error message
                showToast(data.message || 'Failed to update cart', 'error');
            }
        })
        .catch(error => {
            console.error('Error updating cart:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }
    
    // Function to remove item
    function removeItem(itemId) {
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('item_id', itemId);
        
        // Send AJAX request
        fetch('remove_from_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove item from DOM
                const itemElement = document.querySelector(`.cart-item[data-item-id="${itemId}"]`);
                itemElement.remove();
                
                // Update subtotal display
                const subtotalElement = document.getElementById('subtotal');
                subtotalElement.textContent = `₱${data.subtotal.toFixed(2)}`;
                
                // Update total display
                const totalElement = document.getElementById('total');
                totalElement.textContent = `₱${data.total.toFixed(2)}`;
                
                // Update checkout button
                const checkoutButton = document.querySelector('.checkout-button');
                checkoutButton.innerHTML = `<i class="fas fa-shopping-bag"></i> Proceed to Checkout - ₱${data.total.toFixed(2)}`;
                
                // If no items left in cart, reload page to show empty state
                if (data.item_count === 0) {
                    location.reload();
                }
                
                // Show success message
                showToast('Item removed from cart');
            } else {
                // Show error message
                showToast(data.message || 'Failed to remove item', 'error');
            }
        })
        .catch(error => {
            console.error('Error removing item:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }
    
    // Function to clear cart
    function clearCart() {
        if (!confirm('Are you sure you want to clear your entire cart?')) {
            return;
        }
        
        // Send AJAX request
        fetch('clear_cart.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show empty state
                location.reload();
            } else {
                // Show error message
                showToast(data.message || 'Failed to clear cart', 'error');
            }
        })
        .catch(error => {
            console.error('Error clearing cart:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }
    
    // Function to show toast notification
    function showToast(message, type = 'success') {
        // Create toast element if it doesn't exist
        let toast = document.getElementById('toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-notification';
            document.body.appendChild(toast);
        }
        
        // Set toast content and class
        toast.textContent = message;
        toast.className = `toast-notification ${type}`;
        
        // Show the toast
        toast.classList.add('show');
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    </script>
</body>
</html>
