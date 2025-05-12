<?php
/*
 * This is an example of how to include the cart icon in your pages.
 * You can include it in the header or as a floating button.
 */

// Example 1: Including the cart icon in the header
?>
<header>
    <div class="header-container">
        <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
        <h1>Page Title</h1>
        <div class="header-cart-icon">
            <?php include_once 'includes/cart_icon.php'; ?>
        </div>
    </div>
</header>

<?php
// Example 2: Including the cart icon as a floating button
?>
<div class="floating-cart-icon">
    <?php include_once 'includes/cart_icon.php'; ?>
</div>
