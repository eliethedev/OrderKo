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
$stmt = $pdo->prepare("SELECT * FROM products WHERE business_id = ? ORDER BY category, name");
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
            <p class="business-type"><?php echo htmlspecialchars($business['category']); ?> • <?php echo htmlspecialchars($business['sub_category']); ?></p>
            <div class="business-meta">
                <span><i class="fas fa-star"></i> <?php echo $business['rating']; ?> (<?php echo $business['review_count']; ?> reviews)</span>
                <span><i class="fas fa-map-marker-alt"></i> <?php echo $business['distance']; ?> km away</span>
            </div>
            <div class="business-hours">
                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($business['opening_hours']); ?></span>
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
                <button class="category-pill active">All</button>
                <?php foreach (array_unique(array_column($products, 'category')) as $category): ?>
                <button class="category-pill"><?php echo htmlspecialchars($category); ?></button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Products -->
        <section class="products">
            <h3>Best Sellers</h3>
            <div class="product-list">
                <?php foreach ($products as $product): ?>
                <div class="product-item" onclick="openProductModal(<?php echo $product['id']; ?>)">
                    <div class="product-image" style="background-image: url('<?php echo htmlspecialchars($product['image_url']); ?>')"></div>
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        <div class="product-price"><?php echo htmlspecialchars($product['price']); ?></div>
                        <button class="add-to-cart-button" onclick="addToCart(<?php echo $product['id']; ?>)"><i class="fas fa-plus"></i></button>
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
            <span class="cart-count">2</span>
            <span class="cart-total">₱395.00</span>
            <span>View Cart</span>
        </button>
    </div>

    <!-- Product Modal -->
    <div class="modal" id="product-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeProductModal()"><i class="fas fa-times"></i></button>
            <div class="modal-image" style="background-image: url('https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60')"></div>
            <div class="modal-body">
                <h3>Chocolate Cake</h3>
                <p class="modal-price">₱350.00</p>
                <p class="modal-description">Rich chocolate cake with ganache frosting. Perfect for celebrations or as a special treat.</p>
                
                <div class="modal-options">
                    <h4>Size</h4>
                    <div class="option-group">
                        <label class="option-item">
                            <input type="radio" name="size" value="small" checked>
                            <span>Small (6")</span>
                        </label>
                        <label class="option-item">
                            <input type="radio" name="size" value="medium">
                            <span>Medium (8")</span>
                        </label>
                        <label class="option-item">
                            <input type="radio" name="size" value="large">
                            <span>Large (10")</span>
                        </label>
                    </div>
                    
                    <h4>Special Instructions</h4>
                    <textarea placeholder="Add notes for the seller..."></textarea>
                </div>
                
                <div class="quantity-selector">
                    <button class="quantity-button" onclick="decrementQuantity()">-</button>
                    <input type="number" id="quantity" value="1" min="1">
                    <button class="quantity-button" onclick="incrementQuantity()">+</button>
                </div>
                
                <button class="primary-button full-width">Add to Cart - ₱350.00</button>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>