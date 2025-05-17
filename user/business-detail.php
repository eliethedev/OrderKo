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
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""/>
    <style>
        /* Favorite button styles */
        #favorite-button.active i {
            color: #ff5252;
        }
        
        /* Bottom Navigation Enhancements */
        .bottom-nav {
            display: flex;
            justify-content: space-around;
            align-items: center;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #fff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            padding: 10px 0;
            z-index: 1000;
        }
        
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #777;
            font-size: 12px;
            padding: 5px 0;
            width: 20%;
        }
        
        .nav-item i {
            font-size: 20px;
            margin-bottom: 3px;
        }
        
        .nav-item.active {
            color: #0066cc;
        }
        
        /* Back Button Enhancement */
        .back-button {
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }
        
        .back-button:hover {
            background-color: #fff;
            transform: scale(1.05);
        }
        
        .back-button i {
            color: #333;
            font-size: 16px;
        }
        
        /* Add bottom padding to main content to prevent overlap with fixed nav */
        main {
            padding-bottom: 70px;
        }
        
        .message-button {
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .message-button i {
            font-size: 16px;
        }
        
        .business-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .business-actions button {
            flex: 1;
            min-width: 100px;
        }
        
        .map-container {
            margin: 15px 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .map-container .leaflet-map {
            width: 100%;
            height: 250px;
        }
        
        .location-section {
            padding: 15px;
            background-color: #fff;
            border-radius: 10px;
            margin: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .location-section h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 0;
            color: #333;
        }
        
        .location-section h3 i {
            color: #0066cc;
        }
        
        .location-address {
            margin-bottom: 15px;
            color: #555;
            line-height: 1.4;
        }
        
        .map-actions {
            display: flex;
            gap: 10px;
        }
        
        .map-actions button {
            flex: 1;
            padding: 8px 0;
            border: none;
            border-radius: 5px;
            background-color: #f5f5f5;
            color: #333;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .map-actions button i {
            color: #0066cc;
        }
        
        .business-header-map {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        #business-map {
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .map-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            z-index: 10;
        }
        
        /* Contact Modal Styles */
        .contact-modal-header {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .contact-modal-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 15px;
            border: 2px solid #0066cc;
        }
        
        .contact-modal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .contact-modal-header h3 {
            margin: 0 0 5px;
            color: #333;
        }
        
        .contact-modal-header .business-type {
            color: #666;
            margin: 0;
        }
        
        .contact-modal-body {
            padding: 20px;
        }
        
        .contact-item {
            display: flex;
            margin-bottom: 20px;
            align-items: flex-start;
        }
        
        .contact-item i {
            font-size: 20px;
            color: #0066cc;
            margin-right: 15px;
            width: 24px;
            text-align: center;
            margin-top: 5px;
        }
        
        .contact-details {
            flex: 1;
        }
        
        .contact-details h4 {
            margin: 0 0 5px;
            color: #333;
        }
        
        .contact-details p {
            margin: 0 0 10px;
            color: #555;
            line-height: 1.4;
        }
        
        .contact-details .not-available {
            color: #999;
            font-style: italic;
        }
        
        .contact-action-button {
            background-color: #f5f5f5;
            color: #333;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .contact-action-button i {
            color: #0066cc;
            font-size: 14px;
            margin: 0;
            width: auto;
        }
        
        .contact-actions {
            margin-top: 20px;
        }
        
        /* Product Reviews Styles */
        .product-rating-summary {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .rating-stars {
            display: flex;
            margin-right: 8px;
        }
        
        .rating-stars i {
            color: #ffc107;
            margin-right: 2px;
        }
        
        .average-rating {
            font-weight: bold;
            color: #333;
        }
        
        .reviews-section {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .reviews-section h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .product-reviews {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .review-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
            margin-bottom: 10px;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .review-user {
            display: flex;
            align-items: center;
        }
        
        .review-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 1px solid #ddd;
        }
        
        .review-user h5 {
            margin: 0 0 5px;
            font-size: 14px;
            color: #333;
        }
        
        .review-rating {
            display: flex;
        }
        
        .review-rating i {
            color: #ffc107;
            font-size: 12px;
            margin-right: 2px;
        }
        
        .review-date {
            font-size: 12px;
            color: #777;
        }
        
        .review-comment {
            font-size: 14px;
            line-height: 1.4;
            color: #555;
        }
        
        .no-reviews {
            padding: 15px;
            text-align: center;
            color: #777;
            font-style: italic;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .loading-reviews {
            padding: 15px;
            text-align: center;
            color: #777;
        }
        
        .loading-reviews i {
            margin-right: 5px;
            color: var(--color-primary);
        }
        
        .error-message {
            padding: 15px;
            text-align: center;
            color: #d9534f;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
    </style>
    <title><?php echo htmlspecialchars($business['name']); ?> - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>
</head>
<body>
    <!-- Header with Business Image -->
    <div class="business-header" style="padding: 15px;">
        <?php if (isset($business['latitude']) && isset($business['longitude']) && !empty($business['latitude']) && !empty($business['longitude'])): ?>
        <div class="business-header-map">
            <div id="business-map"></div>
            <div class="map-overlay">
                <button class="back-button" onclick="goBack()"><i class="fas fa-arrow-left"></i></button>
                <div class="business-header-actions">
                    <button class="icon-button" onclick="zoomMap()"><i class="fas fa-expand"></i></button>
                    <button class="icon-button" onclick="getDirections()"><i class="fas fa-directions"></i></button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="business-header-image" style="background-image: url('<?php echo htmlspecialchars($business['image_url']); ?>')">
            <div class="business-header-overlay">
                <button class="back-button" onclick="goBack()"><i class="fas fa-arrow-left"></i></button>
            </div>
        </div>  
        <?php endif; ?>
        <div class="business-header-info">
            <h2><?php echo htmlspecialchars($business['name']); ?></h2>
            <div class="business-header-actions">
                    <button class="icon-button" style="background-color: var(--color-primary);" onclick="shareBusiness()"><i class="fas fa-share-alt"></i></button>
                    <button id="favorite-button" class="icon-button" style="background-color: var(--color-primary);" onclick="toggleFavorite(<?php echo $business_id; ?>)"><i id="favorite-icon" class="far fa-heart"></i></button>
            </div>
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
                <button class="message-button" onclick="location.href='conversation.php?type=business&id=<?php echo $business_id; ?>'"><i class="fas fa-comment"></i> Message</button>
                <?php if (isset($business['latitude']) && isset($business['longitude']) && !empty($business['latitude']) && !empty($business['longitude'])): ?>
                <button class="secondary-button" onclick="getDirections()"><i class="fas fa-map-marked-alt"></i> Directions</button>
                <?php else: ?>
                <button class="secondary-button" disabled title="Location not available"><i class="fas fa-map-marked-alt"></i> Directions</button>
                <?php endif; ?>
                <button class="secondary-button" onclick="openContactModal()"><i class="fas fa-phone"></i> Call</button>
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

        <!-- Bottom Navigation -->
        <?php include_once 'includes/bottom_navigation.php'; ?>
    </main>

    <!-- Cart Button -->
    <div class="cart-button-container" style="margin-bottom: 60px;">
        <button class="cart-button" onclick="location.href='cart.php'">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count" id="cart-count">0</span>
            <span class="cart-total" id="cart-total">₱0.00</span>
            <span>View Cart</span>
        </button>
    </div>

    <!-- Contact Modal -->
    <div class="modal" id="contact-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeContactModal()"><i class="fas fa-times"></i></button>
            <div class="contact-modal-header">
                <div class="contact-modal-image">
                    <img src="<?php echo htmlspecialchars($business['image_url'] ? '../' . $business['image_url'] : '../uploads/businesses/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($business['name']); ?>">
                </div>
                <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                <p class="business-type"><?php echo htmlspecialchars($business['category']); ?></p>
            </div>
            <div class="contact-modal-body">
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <div class="contact-details">
                        <h4>Phone</h4>
                        <?php if (!empty($business['phone_number'])): ?>
                        <p><?php echo htmlspecialchars($business['phone_number']); ?></p>
                        <button class="contact-action-button" onclick="window.location.href='tel:<?php echo htmlspecialchars($business['phone_number']); ?>'">
                            <i class="fas fa-phone"></i> Call
                        </button>
                        <?php else: ?>
                        <p class="not-available">Phone number not available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div class="contact-details">
                        <h4>Email</h4>
                        <?php if (!empty($business['email'])): ?>
                        <p><?php echo htmlspecialchars($business['email']); ?></p>
                        <button class="contact-action-button" onclick="window.location.href='mailto:<?php echo htmlspecialchars($business['email']); ?>'">
                            <i class="fas fa-envelope"></i> Email
                        </button>
                        <?php else: ?>
                        <p class="not-available">Email not available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="contact-details">
                        <h4>Address</h4>
                        <?php if (!empty($business['address'])): ?>
                        <p><?php echo htmlspecialchars($business['address']); ?></p>
                        <?php if (isset($business['latitude']) && isset($business['longitude']) && !empty($business['latitude']) && !empty($business['longitude'])): ?>
                        <button class="contact-action-button" onclick="getDirections()">
                            <i class="fas fa-directions"></i> Get Directions
                        </button>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="not-available">Address not available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <div class="contact-details">
                        <h4>Business Hours</h4>
                        <?php if (!empty($business['operating_hours'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($business['operating_hours'])); ?></p>
                        <?php else: ?>
                        <p class="not-available">Hours not available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="contact-actions">
                    <button class="primary-button full-width" onclick="location.href='conversation.php?type=business&id=<?php echo $business_id; ?>'">
                        <i class="fas fa-comment"></i> Send Message
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div class="modal" id="product-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeProductModal()"><i class="fas fa-times"></i></button>
            <div class="modal-image" id="modal-image"></div>
            <div class="modal-body">
                <h3 id="modal-product-name"></h3>
                <div class="product-rating-summary">
                    <div id="rating-stars" class="rating-stars"></div>
                    <span id="average-rating" class="average-rating">0.0</span>
                </div>
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
                
                <div class="reviews-section">
                    <h4>Customer Reviews</h4>
                    <div id="product-reviews" class="product-reviews">
                        <div class="no-reviews">Loading reviews...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Store the current product ID for the modal
    let currentModalProductId = null;
    let businessMap = null;
    
    <?php if (isset($business['latitude']) && isset($business['longitude']) && !empty($business['latitude']) && !empty($business['longitude'])): ?>
    // Initialize map when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a moment to ensure the DOM is fully loaded
        setTimeout(function() {
            // Check if the map element exists
            if (document.getElementById('business-map')) {
                try {
                    // Initialize the map
                    businessMap = L.map('business-map').setView([<?php echo $business['latitude']; ?>, <?php echo $business['longitude']; ?>], 15);
                    
                    // Add the OpenStreetMap tiles
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap contributors</a>'
                    }).addTo(businessMap);
                    
                    // Add a marker for the business location
                    const marker = L.marker([<?php echo $business['latitude']; ?>, <?php echo $business['longitude']; ?>]).addTo(businessMap);
                    marker.bindPopup("<?php echo htmlspecialchars(addslashes($business['name'])); ?>").openPopup();
                    
                    // Invalidate size to handle any rendering issues   
                    businessMap.invalidateSize();
                    console.log('Map initialized successfully');
                } catch (e) {
                    console.error('Error initializing map:', e);
                }
            } else {
                console.error('Map container not found');
            }
        }, 100); // Short delay to ensure DOM is ready
    });
    <?php endif; ?>
    
    // Function to zoom map
    function zoomMap(event) {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Open a new window with a larger map
        const mapUrl = `https://www.openstreetmap.org/?mlat=<?php echo $business['latitude']; ?>&mlon=<?php echo $business['longitude']; ?>&zoom=15`;
        window.open(mapUrl, '_blank');
    }
    
    // Function to get directions
    function getDirections() {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Open directions in OpenStreetMap
        const directionsUrl = `https://www.openstreetmap.org/directions?from=&to=<?php echo $business['latitude']; ?>%2C<?php echo $business['longitude']; ?>`;
        window.open(directionsUrl, '_blank');
    }
    
    // Function to open product modal
    function openProductModal(productId) {
        // Prevent event bubbling
        event.preventDefault();
        event.stopPropagation();
        
        // Store the current product ID
        currentModalProductId = productId;
        
        // Get product details via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_product_details.php?id=' + productId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
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
                        
                        // Load reviews for this product
                        loadProductReviews(productId);
                        
                        // Show the modal
                        const modal = document.getElementById('product-modal');
                        modal.style.display = 'flex';
                    } else {
                        // Show error message
                        alert(response.message);
                    }
                } catch (e) {
                    console.error('Error parsing product details:', e);
                    alert('Error loading product details');
                }
            }
        };
        
        xhr.onerror = function() {
            alert('Network error while loading product details');
        };
        
        xhr.send();
    }
    
    // Function to load product reviews
    function loadProductReviews(productId) {
        const reviewsContainer = document.getElementById('product-reviews');
        reviewsContainer.innerHTML = '<div class="loading-reviews"><i class="fas fa-spinner fa-spin"></i> Loading reviews...</div>';
        
        // Get reviews via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_product_reviews.php?product_id=' + productId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        const reviews = response.reviews;
                        const averageRating = response.average_rating;
                        
                        // Update the average rating
                        document.getElementById('average-rating').textContent = averageRating ? averageRating.toFixed(1) : 'No ratings';
                        
                        // Update the stars
                        const starsContainer = document.getElementById('rating-stars');
                        starsContainer.innerHTML = '';
                        
                        if (averageRating) {
                            // Create filled stars
                            const filledStars = Math.floor(averageRating);
                            for (let i = 0; i < filledStars; i++) {
                                starsContainer.innerHTML += '<i class="fas fa-star"></i>';
                            }
                            
                            // Create half star if needed
                            if (averageRating % 1 >= 0.5) {
                                starsContainer.innerHTML += '<i class="fas fa-star-half-alt"></i>';
                            }
                            
                            // Create empty stars
                            const emptyStars = 5 - Math.ceil(averageRating);
                            for (let i = 0; i < emptyStars; i++) {
                                starsContainer.innerHTML += '<i class="far fa-star"></i>';
                            }
                        } else {
                            // No ratings yet
                            for (let i = 0; i < 5; i++) {
                                starsContainer.innerHTML += '<i class="far fa-star"></i>';
                            }
                        }
                        
                        // Update the reviews list
                        if (reviews.length > 0) {
                            let reviewsHtml = '';
                            
                            reviews.forEach(review => {
                                // Create stars for this review
                                let reviewStars = '';
                                for (let i = 0; i < 5; i++) {
                                    if (i < review.rating) {
                                        reviewStars += '<i class="fas fa-star"></i>';
                                    } else {
                                        reviewStars += '<i class="far fa-star"></i>';
                                    }
                                }
                                
                                // Format date
                                const reviewDate = new Date(review.created_at);
                                const formattedDate = reviewDate.toLocaleDateString('en-US', { 
                                    year: 'numeric', 
                                    month: 'short', 
                                    day: 'numeric' 
                                });
                                
                                reviewsHtml += `
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="review-user">
                                            <img src="${review.user_image || '../uploads/profile_picture/default.jpg'}" alt="${review.user_name}">
                                            <div>
                                                <h5>${review.user_name}</h5>
                                                <div class="review-rating">${reviewStars}</div>
                                            </div>
                                        </div>
                                        <div class="review-date">${formattedDate}</div>
                                    </div>
                                    <div class="review-comment">${review.comment}</div>
                                </div>
                                `;
                            });
                            
                            reviewsContainer.innerHTML = reviewsHtml;
                        } else {
                            reviewsContainer.innerHTML = '<div class="no-reviews">No reviews yet. Be the first to review this product!</div>';
                        }
                    } else {
                        reviewsContainer.innerHTML = '<div class="error-message">Error loading reviews: ' + response.message + '</div>';
                    }
                } catch (e) {
                    console.error('Error parsing reviews:', e);
                    reviewsContainer.innerHTML = '<div class="error-message">Error loading reviews</div>';
                }
            } else {
                reviewsContainer.innerHTML = '<div class="error-message">Error loading reviews</div>';
            }
        };
        
        xhr.onerror = function() {
            reviewsContainer.innerHTML = '<div class="error-message">Network error while loading reviews</div>';
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
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Send AJAX request to add product to cart
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'add_to_cart.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        // Update cart count and total
                        document.getElementById('cart-count').textContent = response.cart_count;
                        document.getElementById('cart-total').textContent = '₱' + parseFloat(response.cart_total).toFixed(2);
                        
                        // Show success message
                        showNotification(response.message);
                        
                        // Close modal if open
                        closeProductModal();
                    } else {
                        // Show error message
                        alert(response.message);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('Error adding product to cart');
                }
            }
        };
        
        xhr.onerror = function() {
            alert('Network error while adding to cart');
        };
        
        xhr.send('product_id=' + productId + '&quantity=' + quantity + '&options=' + encodeURIComponent(options));
    }
    
    // Function to open contact modal
    function openContactModal() {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        const modal = document.getElementById('contact-modal');
        if (modal) {
            modal.style.display = 'flex';
        } else {
            console.error('Contact modal element not found');
        }
    }
    
    // Function to close contact modal
    function closeContactModal() {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        const modal = document.getElementById('contact-modal');
        if (modal) {
            modal.style.display = 'none';
        } else {
            console.error('Contact modal element not found');
        }
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const productModal = document.getElementById('product-modal');
        const contactModal = document.getElementById('contact-modal');
        
        if (event.target === productModal) {
            closeProductModal();
        } else if (event.target === contactModal) {
            closeContactModal();
        }
    };
    
    // Function for better back button handling
    function goBack() {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Check if there's a previous page in history
        if (window.history.length > 1) {
            window.history.back();
        } else {
            // Fallback to businesses page if no history
            window.location.href = 'businesses.php';
        }
    }
    
    // Function to toggle favorite status
    function toggleFavorite(businessId) {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'toggle_favorite.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Update the favorite icon
                        const favoriteIcon = document.getElementById('favorite-icon');
                        if (response.is_favorite) {
                            favoriteIcon.classList.remove('far');
                            favoriteIcon.classList.add('fas');
                            favoriteIcon.style.color = '#ff5252';
                        } else {
                            favoriteIcon.classList.remove('fas');
                            favoriteIcon.classList.add('far');
                            favoriteIcon.style.color = '';
                        }
                        
                        // Show a brief notification
                        showNotification(response.message);
                    } else {
                        console.error('Error:', response.message);
                        showNotification('Error: ' + response.message);
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showNotification('Error processing request');
                }
            }
        };
        
        xhr.onerror = function() {
            showNotification('Network error');
        };
        
        xhr.send('business_id=' + businessId);
    }
    
    // Function to share business
    function shareBusiness() {
        // Prevent event bubbling
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Check if Web Share API is available
        if (navigator.share) {
            navigator.share({
                title: '<?php echo htmlspecialchars(addslashes($business['name'])); ?>',
                text: 'Check out <?php echo htmlspecialchars(addslashes($business['name'])); ?> on OrderKo!',
                url: window.location.href
            })
            .then(() => showNotification('Shared successfully'))
            .catch((error) => {
                console.error('Error sharing:', error);
                showNotification('Error sharing');
            });
        } else {
            // Fallback for browsers that don't support Web Share API
            // Copy link to clipboard
            try {
                const tempInput = document.createElement('input');
                tempInput.value = window.location.href;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                
                showNotification('Link copied to clipboard');
            } catch (e) {
                console.error('Error copying to clipboard:', e);
                showNotification('Error copying link');
            }
        }
    }
    
    // Function to show a brief notification
    function showNotification(message) {
        // Create notification element if it doesn't exist
        let notification = document.getElementById('notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'notification';
            notification.style.position = 'fixed';
            notification.style.bottom = '70px';
            notification.style.left = '50%';
            notification.style.transform = 'translateX(-50%)';
            notification.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
            notification.style.color = 'white';
            notification.style.padding = '10px 20px';
            notification.style.borderRadius = '20px';
            notification.style.zIndex = '9999';
            notification.style.transition = 'opacity 0.3s ease';
            document.body.appendChild(notification);
        }
        
        // Update and show notification
        notification.textContent = message;
        notification.style.opacity = '1';
        
        // Hide after 2 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
        }, 2000);
    }
    
    // Check if business is a favorite
    function checkFavoriteStatus(businessId) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'check_favorite.php?business_id=' + businessId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Update the favorite icon
                        const favoriteIcon = document.getElementById('favorite-icon');
                        if (response.is_favorite) {
                            favoriteIcon.classList.remove('far');
                            favoriteIcon.classList.add('fas');
                            favoriteIcon.style.color = '#ff5252';
                        }
                    }
                } catch (e) {
                    console.error('Error parsing favorite status:', e);
                }
            }
        };
        
        xhr.send();
    }
    
    // Initialize cart count and total on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check if this business is a favorite
        checkFavoriteStatus(<?php echo $business_id; ?>);
        // Get current cart count and total via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_cart_info.php', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        document.getElementById('cart-count').textContent = response.cart_count;
                        document.getElementById('cart-total').textContent = '₱' + parseFloat(response.cart_total).toFixed(2);
                    }
                } catch (e) {
                    console.error('Error parsing cart info:', e);
                }
            }
        };
        
        xhr.send();
        
        // Highlight the current navigation item
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (currentPath.includes('business-detail.php')) {
                // We're on a business detail page, so highlight the Explore tab
                if (href === 'businesses.php') {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            }
        });
    });
    </script>
</body>
</html>