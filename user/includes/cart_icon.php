<?php
// Check if session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection if not already included
if (!isset($pdo)) {
    require_once '../../config/database.php';
}

// Function to get cart count
function getCartCount($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['total_items'] ? (int)$result['total_items'] : 0;
    } catch (PDOException $e) {
        // Silent error handling
        return 0;
    }
}

// Get cart count if user is logged in
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $cart_count = getCartCount($pdo, $_SESSION['user_id']);
}
?>

<!-- Cart Icon Component -->
<a href="cart.php" class="cart-icon-container">
    <div class="cart-icon">
        <i class="fas fa-shopping-cart"></i>
        <?php if ($cart_count > 0): ?>
        <span class="cart-count"><?php echo $cart_count; ?></span>
        <?php endif; ?>
    </div>
</a>

<style>
/* Cart Icon Styles */
.cart-icon-container {
    position: relative;
    display: inline-block;
    text-decoration: none;
    color: inherit;
}

.cart-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--color-card);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    position: relative;
    transition: all 0.2s ease;
}

.cart-icon:hover {
    background-color: var(--color-primary-light, #f8d7da);
    transform: scale(1.05);
}

.cart-icon i {
    font-size: 18px;
    color: var(--color-primary, #e74c3c);
}

.cart-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: var(--color-primary, #e74c3c);
    color: white;
    font-size: 10px;
    font-weight: bold;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 3px rgba(0, 0, 0, 0.2);
}

/* For fixed position in the header */
.header-cart-icon {
    position: absolute;
    top: 12px;
    right: 15px;
    z-index: 10;
}

/* For floating position */
.floating-cart-icon {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 100;
}

.floating-cart-icon .cart-icon {
    width: 50px;
    height: 50px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
}

.floating-cart-icon .cart-icon i {
    font-size: 22px;
}

.floating-cart-icon .cart-count {
    width: 22px;
    height: 22px;
    font-size: 12px;
}
</style>
