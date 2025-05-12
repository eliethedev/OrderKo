<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get business ID from URL
$business_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch business details
$stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: businesses.php');
    exit;
}

// Fetch products for this business
$stmt = $pdo->prepare("SELECT * FROM products WHERE business_id = ? ORDER BY name");
$stmt->execute([$business_id]);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($business['name']); ?> - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header with Business Image -->
    <div class="business-header">
        <div class="business-header-image" style="background-image: url('<?php echo htmlspecialchars($business['image_url']); ?>')">
            <div class="business-header-overlay">
                <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
                <div class="business-header-actions">
                    <button class="icon-button"><i class="fas fa-share-alt"></i></button>
                    <button class="icon-button"><i class="far fa-heart"></i></button>
                </div>
            </div>
        </div>
        <div class="business-header-info">
            <h2><?php echo htmlspecialchars($business['name']); ?></h2>
            <p class="business-type"><?php echo htmlspecialchars($business['category']); ?></p>
            <div class="business-meta">
                <span><i class="fas fa-star"></i> <?php echo isset($business['rating']) ? number_format($business['rating'], 1) : 'New'; ?></span>
                <?php if (isset($business['latitude']) && isset($business['longitude'])): ?>
                <span><i class="fas fa-map-marker-alt"></i> Location available</span>
                <?php endif; ?>
                <span><i class="fas fa-check-circle <?php echo $business['verification_status'] === 'verified' ? 'verified' : 'pending'; ?>"></i> <?php echo ucfirst($business['verification_status'] ?: 'pending'); ?></span>
            </div>
            <div class="business-hours">
                <span><i class="fas fa-clock"></i> <?php echo isset($business['operating_hours']) ? 'Hours available' : 'Contact for hours'; ?></span>
            </div>
            <div class="business-actions">
                <button class="secondary-button"><i class="fas fa-map-marked-alt"></i> Directions</button>
                <button class="secondary-button"><i class="fas fa-phone"></i> Call</button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <!-- Product Categories -->
        <section class="product-categories">
            <div class="category-scroll">
                <button class="category-pill active">All Products</button>
                <?php if (count($products) > 5): ?>
                <button class="category-pill">Best Sellers</button>
                <button class="category-pill">New Arrivals</button>
                <?php endif; ?>
            </div>
        </section>

        <!-- Products -->
        <section class="products">
            <h3>Best Sellers</h3>
            <div class="product-list">
                <?php foreach ($products as $product): ?>
                <div class="product-item" onclick="openProductModal(<?php echo $product['id']; ?>)">
                    <div class="product-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 80) . (strlen($product['description']) > 80 ? '...' : '')); ?></p>
                        <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                        <button class="add-to-cart-button" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>)"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <!-- Cart Button -->
    <div class="cart-button-container">
        <button class="cart-button" onclick="location.href='cart.php'">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count" id="cart-count">0</span>
            <span class="cart-total" id="cart-total">₱0.00</span>
            <span>View Cart</span>
        </button>
    </div>

    <!-- Product Modal -->
    <div class="modal" id="product-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeProductModal()"><i class="fas fa-times"></i></button>
            <div class="modal-image" id="modal-image"></div>
            <div class="modal-body">
                <h3 id="modal-product-name"></h3>
                <p class="modal-price" id="modal-price"></p>
                <p class="modal-description" id="modal-description"></p>
                
                <div class="modal-options">
                    <h4>Special Instructions</h4>
                    <textarea id="modal-special-instructions" placeholder="Add notes for the seller..."></textarea>
                </div>
                
                <div class="quantity-selector">
                    <button class="quantity-button" onclick="decrementQuantity()">-</button>
                    <input type="number" id="modal-quantity" value="1" min="1">
                    <button class="quantity-button" onclick="incrementQuantity()">+</button>
                </div>
                
                <button class="primary-button full-width" id="modal-add-to-cart-button" onclick="addToCartFromModal()">Add to Cart</button>
            </div>
        </div>
    </div>

    <script>
    // Store the current product ID for the modal
    let currentModalProductId = null;
    
    // Function to open product modal
    function openProductModal(productId) {
        // Store the current product ID
        currentModalProductId = productId;
        
        // Get product details via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_product_details.php?id=' + productId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    const product = response.product;
                    
                    // Update modal with product details
                    document.getElementById('modal-product-name').textContent = product.name;
                    document.getElementById('modal-price').textContent = '₱' + parseFloat(product.price).toFixed(2);
                    document.getElementById('modal-description').textContent = product.description;
                    
                    // Set the modal image
                    const imageUrl = product.image_url ? '../' + product.image_url : '../assets/images/default-product.jpg';
                    document.getElementById('modal-image').style.backgroundImage = `url('${imageUrl}')`;
                    
                    // Reset quantity and special instructions
                    document.getElementById('modal-quantity').value = 1;
                    document.getElementById('modal-special-instructions').value = '';
                    
                    // Update the add to cart button
                    document.getElementById('modal-add-to-cart-button').textContent = 'Add to Cart - ₱' + parseFloat(product.price).toFixed(2);
                    
                    // Show the modal
                    const modal = document.getElementById('product-modal');
                    modal.style.display = 'flex';
                } else {
                    // Show error message
                    alert(response.message);
                }
            }
        };
        
        xhr.send();
    }
    
    // Function to close product modal
    function closeProductModal() {
        const modal = document.getElementById('product-modal');
        modal.style.display = 'none';
        currentModalProductId = null;
    }
    
    // Function to increment quantity in modal
    function incrementQuantity() {
        const quantityInput = document.getElementById('modal-quantity');
        const newValue = parseInt(quantityInput.value) + 1;
        quantityInput.value = newValue;
        updateModalPrice();
    }
    
    // Function to decrement quantity in modal
    function decrementQuantity() {
        const quantityInput = document.getElementById('modal-quantity');
        const currentValue = parseInt(quantityInput.value);
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
            updateModalPrice();
        }
    }
    
    // Function to update the price in the modal based on quantity
    function updateModalPrice() {
        const priceElement = document.getElementById('modal-price');
        const basePrice = parseFloat(priceElement.textContent.replace('₱', ''));
        const quantity = parseInt(document.getElementById('modal-quantity').value);
        const totalPrice = basePrice * quantity;
        
        // Update the add to cart button text
        document.getElementById('modal-add-to-cart-button').textContent = 'Add to Cart - ₱' + totalPrice.toFixed(2);
    }
    
    // Function to add product to cart from the modal
    function addToCartFromModal() {
        if (!currentModalProductId) return;
        
        const quantity = parseInt(document.getElementById('modal-quantity').value);
        const options = document.getElementById('modal-special-instructions').value;
        
        addToCart(currentModalProductId, quantity, options);
    }
    
    // Function to add product to cart
    function addToCart(productId, quantity = 1, options = '') {
        // Send AJAX request to add product to cart
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'add_to_cart.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // Update cart count and total
                    document.getElementById('cart-count').textContent = response.cart_count;
                    document.getElementById('cart-total').textContent = '₱' + parseFloat(response.cart_total).toFixed(2);
                    
                    // Show success message
                    alert(response.message);
                    
                    // Close modal if open
                    closeProductModal();
                } else {
                    // Show error message
                    alert(response.message);
                }
            }
        };
        
        xhr.send('product_id=' + productId + '&quantity=' + quantity + '&options=' + encodeURIComponent(options));
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('product-modal');
        if (event.target === modal) {
            closeProductModal();
        }
    };
    
    // Initialize cart count and total on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Make sure back button works properly
        const backButton = document.querySelector('.back-button');
        if (backButton) {
            backButton.addEventListener('click', function(e) {
                e.preventDefault();
                window.history.back();
            });
        }
    });
    </script>
</body>
</html>