<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: businesses.php');
    exit;
}

$product_id = $_GET['id'];

// Get user location from session or use default
$user_latitude = isset($_SESSION['user_latitude']) ? $_SESSION['user_latitude'] : 14.5995;
$user_longitude = isset($_SESSION['user_longitude']) ? $_SESSION['user_longitude'] : 120.9842;

// Fetch product details with business information
$query = "SELECT p.*, b.name as business_name, b.category as business_category, 
          b.image_url as business_image, b.verification_status, b.id as business_id,
          b.latitude, b.longitude, b.address as business_address,
          CASE 
            WHEN b.latitude IS NOT NULL AND b.longitude IS NOT NULL THEN 
                ROUND((
                    6371 * acos(
                        cos(radians(?)) * 
                        cos(radians(b.latitude)) * 
                        cos(radians(b.longitude) - radians(?)) + 
                        sin(radians(?)) * 
                        sin(radians(b.latitude))
                    )
                ), 1)
            ELSE NULL
          END as distance
          FROM products p
          JOIN businesses b ON p.business_id = b.id
          WHERE p.id = ?";

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $user_latitude, PDO::PARAM_STR);
$stmt->bindParam(2, $user_longitude, PDO::PARAM_STR);
$stmt->bindParam(3, $user_latitude, PDO::PARAM_STR);
$stmt->bindParam(4, $product_id, PDO::PARAM_INT);
$stmt->execute();
$product = $stmt->fetch();

// If product not found, redirect to businesses page
if (!$product) {
    header('Location: businesses.php');
    exit;
}

// Fetch related products from the same business
$query = "SELECT p.* FROM products p 
          WHERE p.business_id = ? AND p.id != ? AND p.is_available = 1
          ORDER BY p.created_at DESC LIMIT 6";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $product['business_id'], PDO::PARAM_INT);
$stmt->bindParam(2, $product_id, PDO::PARAM_INT);
$stmt->execute();
$related_products = $stmt->fetchAll();

// Fetch reviews for this product
$query = "SELECT r.*, u.full_name as user_name, u.profile_picture as profile_image 
              FROM reviews r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.product_id = ? 
          ORDER BY r.created_at DESC LIMIT 5";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $product_id, PDO::PARAM_INT);
$stmt->execute();
$reviews = $stmt->fetchAll();

// Calculate average rating
$avg_rating = 0;
if (!empty($reviews)) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $avg_rating = round($total_rating / count($reviews), 1);
}

// Calculate distance between business and user (in kilometers)
$distance = isset($product['distance']) ? $product['distance'] : 0;

// Calculate delivery fee based on distance
function calculateDeliveryFee($distance) {
    $base_fee = 50;
    $per_km_fee = 10;
    $total_fee = $base_fee;
    if ($distance > 2) {
        $total_fee += ($distance - 2) * $per_km_fee;
    }
    return round($total_fee, 2);
}

$delivery_fee = calculateDeliveryFee($distance);

// Check if product is in cart
$query = "SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindParam(2, $product_id, PDO::PARAM_INT);
$stmt->execute();
$in_cart = $stmt->fetch() ? true : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Product Detail Styles */
        .product-detail-container {
            padding-bottom: 100px; /* Space for add to cart button */
        }
        
        .product-image-gallery {
            position: relative;
            height: 250px;
            background-size: cover;
            background-position: center;
            background-color: #f5f5f5;
        }
        
        .product-back-button {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .product-share-button {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .product-info-section {
            background-color: white;
            border-radius: 20px 20px 0 0;
            margin-top: -20px;
            position: relative;
            padding: 20px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .product-name {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        
        .product-price {
            font-size: 22px;
            font-weight: 600;
            color: #e74c3c;
            margin-bottom: 15px;
        }
        
        .product-rating {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .rating-stars {
            display: flex;
            margin-right: 10px;
        }
        
        .rating-stars i {
            color: #FFD700;
            font-size: 16px;
            margin-right: 2px;
        }
        
        .rating-count {
            color: #666;
            font-size: 14px;
        }
        
        .product-business {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 12px;
            margin-bottom: 20px;
            cursor: pointer;
        }
        
        .business-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin-right: 15px;
            border: 1px solid #eee;
        }
        
        .business-info {
            flex: 1;
        }
        
        .business-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .business-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .business-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .business-meta i.verified {
            color: #28a745;
        }
        
        .business-meta i.pending {
            color: #ffc107;
        }
        
        .business-address {
            margin-top: 8px;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .business-address i {
            color: #e74c3c;
        }
        
        .product-description {
            margin-bottom: 20px;
            line-height: 1.6;
            color: #333;
        }
        
        .product-section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 25px 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .product-section-title i {
            color: #e74c3c;
        }
        
        .delivery-info {
            background-color: #f9f9f9;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .delivery-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .delivery-row:last-child {
            margin-bottom: 0;
        }
        
        .delivery-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
        
        .delivery-value {
            font-weight: 500;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        
        .quantity-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background-color: #f5f5f5;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .quantity-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-display {
            width: 60px;
            text-align: center;
            font-size: 18px;
            font-weight: 500;
        }
        
        .related-products {
            margin-bottom: 20px;
        }
        
        .related-products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .related-product-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        .related-product-image {
            height: 120px;
            background-size: cover;
            background-position: center;
        }
        
        .related-product-info {
            padding: 10px;
        }
        
        .related-product-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .related-product-price {
            font-size: 14px;
            font-weight: 600;
            color: #e74c3c;
        }
        
        .reviews-section {
            margin-bottom: 20px;
        }
        
        .review-card {
            background-color: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reviewer-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin-right: 10px;
        }
        
        .reviewer-info {
            flex: 1;
        }
        
        .reviewer-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .review-date {
            font-size: 12px;
            color: #666;
        }
        
        .review-rating {
            display: flex;
            margin-bottom: 8px;
        }
        
        .review-rating i {
            color: #FFD700;
            font-size: 14px;
            margin-right: 2px;
        }
        
        .review-content {
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        
        .view-all-reviews {
            text-align: center;
            padding: 12px;
            background-color: #f9f9f9;
            border-radius: 12px;
            color: #e74c3c;
            font-weight: 500;
            cursor: pointer;
        }
        
        .add-to-cart-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            padding: 15px 20px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }
        
        .cart-price-info {
            display: flex;
            flex-direction: column;
        }
        
        .cart-price-label {
            font-size: 12px;
            color: #666;
        }
        
        .cart-price-value {
            font-size: 18px;
            font-weight: 600;
            color: #e74c3c;
        }
        
        .add-to-cart-button {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 30px;
            padding: 12px 24px;
            font-size: 16px;
            width: 100px;
            font-size: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .add-to-cart-button:hover {
            background-color: #c0392b;
        }
        
        .add-to-cart-button.in-cart {
            background-color: #28a745;
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
            border-radius: 8px;
        }
        
        /* Review Form Styles */
        .review-form-container {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .review-form-container h3 {
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        
        .rating-selector {
            margin-bottom: 15px;
        }
        
        .rating-selector p {
            margin: 0 0 5px 0;
            font-weight: 500;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
            font-size: 24px;
            color: #ffc107;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            resize: vertical;
        }
        
        .submit-review-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .submit-review-btn:hover {
            background-color: #c0392b;
        }
        
        .no-reviews-message {
            text-align: center;
            padding: 20px;
            color: #777;
            font-style: italic;
        }
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
        
        @media (max-width: 480px) {
            .product-image-gallery {
                height: 200px;
            }
            
            .product-name {
                font-size: 20px;
            }
            
            .product-price {
                font-size: 18px;
            }
            
            .related-products-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .related-product-image {
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <main class="product-detail-container">
        <!-- Product Image Gallery -->
        <div class="product-image-gallery" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')">
            <button class="product-back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <button class="product-share-button"><i class="fas fa-share-alt"></i></button>
        </div>
        
        <!-- Product Info Section -->
        <div class="product-info-section">
            <h1 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
            
            <?php if (!empty($reviews)): ?>
            <div class="product-rating">
                <div class="rating-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= floor($avg_rating)): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif ($i - 0.5 <= $avg_rating): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <div class="rating-count"><?php echo $avg_rating; ?> (<?php echo count($reviews); ?> reviews)</div>
            </div>
            <?php endif; ?>
            
            <!-- Business Info -->
            <div class="product-business" onclick="location.href='business-detail.php?id=<?php echo $product['business_id']; ?>'">
                <div class="business-image" style="background-image: url('../<?php echo htmlspecialchars($product['business_image'] ?: 'assets/images/default-business.jpg'); ?>')"></div>
                <div class="business-info">
                    <div class="business-name"><?php echo htmlspecialchars($product['business_name']); ?></div>
                    <div class="business-meta">
                        <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['business_category']); ?></span>
                        <?php if (isset($product['distance']) && $product['distance'] !== null): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo $product['distance']; ?> km</span>
                        <?php endif; ?>
                        <span>
                            <i class="fas fa-check-circle <?php echo $product['verification_status'] === 'verified' ? 'verified' : 'pending'; ?>"></i>
                            <?php echo $product['verification_status'] === 'verified' ? 'Verified' : 'Pending'; ?>
                        </span>
                    </div>
                    <?php if (!empty($product['business_address'])): ?>
                    <div class="business-address">
                        <i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($product['business_address']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
            
            <!-- Product Description -->
            <?php if (!empty($product['description'])): ?>
            <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>
            <?php endif; ?>
            
            <!-- Delivery Information -->
            <h2 class="product-section-title"><i class="fas fa-truck"></i> Delivery Information</h2>
            <div class="delivery-info">
                <div class="delivery-row">
                    <div class="delivery-label"><i class="fas fa-motorcycle"></i> Delivery Fee</div>
                    <div class="delivery-value">₱<?php echo number_format($delivery_fee, 2); ?></div>
                </div>
                <div class="delivery-row">
                    <div class="delivery-label"><i class="fas fa-clock"></i> Estimated Delivery Time</div>
                    <div class="delivery-value">
                        <?php 
                        $estimated_time = "30-45 minutes";
                        if ($distance > 5) {
                            $estimated_time = "45-60 minutes";
                        } else if ($distance > 10) {
                            $estimated_time = "60-90 minutes";
                        }
                        echo $estimated_time;
                        ?>
                    </div>
                </div>
                <div class="delivery-row">
                    <div class="delivery-label"><i class="fas fa-store"></i> Pickup Available</div>
                    <div class="delivery-value">Yes</div>
                </div>
            </div>
            
            <!-- Quantity Selector -->
            <h2 class="product-section-title"><i class="fas fa-shopping-basket"></i> Quantity</h2>
            <div class="quantity-selector">
                <button class="quantity-button" id="decrease-quantity" onclick="updateQuantity(-1)" disabled><i class="fas fa-minus"></i></button>
                <div class="quantity-display" id="quantity-value">1</div>
                <button class="quantity-button" id="increase-quantity" onclick="updateQuantity(1)"><i class="fas fa-plus"></i></button>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
            <h2 class="product-section-title"><i class="fas fa-th-large"></i> More from this Business</h2>
            <div class="related-products">
                <div class="related-products-grid">
                    <?php foreach ($related_products as $related): ?>
                    <div class="related-product-card" onclick="location.href='product-detail.php?id=<?php echo $related['id']; ?>'">
                        <div class="related-product-image" style="background-image: url('../<?php echo htmlspecialchars($related['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                        <div class="related-product-info">
                            <div class="related-product-name"><?php echo htmlspecialchars($related['name']); ?></div>
                            <div class="related-product-price">₱<?php echo number_format($related['price'], 2); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Reviews Section -->
            <h2 class="product-section-title"><i class="fas fa-star"></i> Reviews</h2>
            
            <!-- Review Submission Form -->
            <div class="review-form-container">
                <h3>Write a Review</h3>
                <form id="review-form">
                    <div class="rating-selector">
                        <p>Your Rating:</p>
                        <div class="star-rating">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" id="rating-value" name="rating" value="0">
                    </div>
                    <div class="form-group">
                        <label for="review-comment">Your Review:</label>
                        <textarea id="review-comment" name="comment" rows="4" placeholder="Share your experience with this product" required></textarea>
                    </div>
                    <button type="submit" class="submit-review-btn">Submit Review</button>
                </form>
            </div>
            
            <!-- Existing Reviews Section -->
            <?php if (!empty($reviews)): ?>
            <div class="reviews-section">
                <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-image" style="background-image: url('../<?php echo htmlspecialchars($review['profile_image'] ?: 'assets/images/default-user.jpg'); ?>')"></div>
                        <div class="reviewer-info">
                            <div class="reviewer-name"><?php echo htmlspecialchars($review['user_name']); ?></div>
                            <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="review-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['rating']): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="review-content"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($reviews) >= 5): ?>
                <div class="view-all-reviews" onclick="location.href='reviews.php?product_id=<?php echo $product_id; ?>'">
                    View All Reviews
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="no-reviews-message">
                <p>No reviews yet. Be the first to review this product!</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Add to Cart Button -->
    <div class="add-to-cart-container">
        <div class="cart-price-info">
            <div class="cart-price-label">Total Price</div>
            <div class="cart-price-value" id="total-price">₱<?php echo number_format($product['price'], 2); ?></div>
        </div>
        <button class="add-to-cart-button <?php echo $in_cart ? 'in-cart' : ''; ?>" id="add-to-cart-btn" onclick="addToCart()">
            <?php if ($in_cart): ?>
            <i class="fas fa-check"></i> In Cart
            <?php else: ?>
            <i class="fas fa-cart-plus"></i>  <p>Add to Cart</p>
            <?php endif; ?>
        </button>
    </div>

    <!-- Include location.js script -->
    <script src="src/location.js"></script>
    
    <script>
    // Product details
    const productId = <?php echo $product_id; ?>;
    const productPrice = <?php echo $product['price']; ?>;
    let quantity = 1;
    let inCart = <?php echo $in_cart ? 'true' : 'false'; ?>;
    
    // Update quantity function
    function updateQuantity(change) {
        const newQuantity = quantity + change;
        if (newQuantity >= 1 && newQuantity <= 10) {
            quantity = newQuantity;
            document.getElementById('quantity-value').textContent = quantity;
            document.getElementById('total-price').textContent = '₱' + (productPrice * quantity).toFixed(2);
            
            // Enable/disable buttons based on quantity
            document.getElementById('decrease-quantity').disabled = (quantity <= 1);
            document.getElementById('increase-quantity').disabled = (quantity >= 10);
        }
    }
    
    // Add to cart function
    function addToCart() {
        // If already in cart, go to cart page
        if (inCart) {
            window.location.href = 'cart.php';
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        
        // Send AJAX request
        fetch('add_to_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast('Product added to cart!');
                
                // Update button to show "In Cart"
                const button = document.getElementById('add-to-cart-btn');
                button.innerHTML = '<i class="fas fa-check"></i> In Cart';
                button.classList.add('in-cart');
                inCart = true;
                
                // Update cart count if available
                if (document.querySelector('.cart-count')) {
                    document.querySelector('.cart-count').textContent = data.cart_count;
                }
            } else {
                // Show error message
                showToast(data.message || 'Failed to add product to cart', 'error');
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
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
    
    // Initialize the page when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize quantity buttons
        document.getElementById('decrease-quantity').disabled = true;
        
        // Initialize user location
        getUserLocation()
            .then(location => {
                // Save location to session
                saveUserLocation(location);
            })
            .catch(error => {
                console.error('Error getting location:', error);
            });
            
        // Initialize star rating system
        const stars = document.querySelectorAll('.star-rating i');
        const ratingInput = document.getElementById('rating-value');
        
        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const rating = this.getAttribute('data-rating');
                highlightStars(rating);
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = ratingInput.value;
                highlightStars(currentRating);
            });
            
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                ratingInput.value = rating;
                highlightStars(rating);
            });
        });
        
        function highlightStars(rating) {
            stars.forEach(star => {
                const starRating = star.getAttribute('data-rating');
                if (starRating <= rating) {
                    star.classList.remove('far');
                    star.classList.add('fas');
                } else {
                    star.classList.remove('fas');
                    star.classList.add('far');
                }
            });
        }
        
        // Handle review form submission
        const reviewForm = document.getElementById('review-form');
        if (reviewForm) {
            reviewForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const rating = document.getElementById('rating-value').value;
                const comment = document.getElementById('review-comment').value;
                
                if (rating < 1) {
                    showToast('Please select a rating', 'error');
                    return;
                }
                
                if (!comment.trim()) {
                    showToast('Please enter a review comment', 'error');
                    return;
                }
                
                // Create form data
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('rating', rating);
                formData.append('comment', comment);
                
                // Send AJAX request
                fetch('submit_review.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        // Reset form
                        reviewForm.reset();
                        ratingInput.value = 0;
                        highlightStars(0);
                        
                        // Reload page to show the new review
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error submitting review:', error);
                    showToast('An error occurred. Please try again.', 'error');
                });
            });
        }
    });
    </script>
</body>
</html>
