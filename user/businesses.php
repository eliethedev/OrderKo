<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch businesses from database with calculated distance (if location available)
$query = "SELECT b.*, 
        CASE 
            WHEN b.latitude IS NOT NULL AND b.longitude IS NOT NULL THEN 
                ROUND((
                    6371 * acos(
                        cos(radians(12.8797)) * 
                        cos(radians(b.latitude)) * 
                        cos(radians(b.longitude) - radians(121.7740)) + 
                        sin(radians(12.8797)) * 
                        sin(radians(b.latitude))
                    )
                ), 1)
            ELSE NULL
        END as distance
        FROM businesses b 
        ORDER BY COALESCE(b.rating, 0) DESC, b.name ASC 
        LIMIT 10";

$stmt = $pdo->query($query);
$businesses = $stmt->fetchAll();

// Function to get all available products with business information
function getAllAvailableProducts($pdo, $limit = 20) {
    // Get user coordinates from session or use default Manila coordinates
    $user_latitude = isset($_SESSION['user_latitude']) ? $_SESSION['user_latitude'] : 14.5995;
    $user_longitude = isset($_SESSION['user_longitude']) ? $_SESSION['user_longitude'] : 120.9842;
    
    $query = "SELECT p.*, b.name as business_name, b.category as business_category, b.image_url as business_image, b.verification_status,
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
              WHERE p.is_available = 1
              ORDER BY p.created_at DESC
              LIMIT ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $user_latitude, PDO::PARAM_STR);
    $stmt->bindParam(2, $user_longitude, PDO::PARAM_STR);
    $stmt->bindParam(3, $user_latitude, PDO::PARAM_STR);
    $stmt->bindParam(4, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get all available products with business information
$products = getAllAvailableProducts($pdo);

// Add debugging information
$debug = true;

// Check if tables have data
$businessCountQuery = "SELECT COUNT(*) as count FROM businesses";
$productCountQuery = "SELECT COUNT(*) as count FROM products";

$businessCountStmt = $pdo->query($businessCountQuery);
$productCountStmt = $pdo->query($productCountQuery);

$totalBusinesses = $businessCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
$totalProducts = $productCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

// Group products by business for the business view
$business_products = [];
foreach ($businesses as $business) {
    $business_products[$business['id']] = [];
}

// Populate business products
foreach ($products as $product) {
    if (isset($business_products[$product['business_id']])) {
        if (count($business_products[$product['business_id']]) < 3) {
            $business_products[$product['business_id']][] = $product;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Businesses - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 20px;
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
        
        /* Product Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 10vh auto;
            width: 90%;
            max-width: 500px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            max-height: 80vh;
        }
        
        .modal-header {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        
        .modal-close {
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .modal-body {
            overflow-y: auto;
        }
        
        .product-modal-image {
            height: 220px;
            background-size: cover;
            background-position: center;
            background-color: #f5f5f5;
        }
        
        .product-modal-info {
            padding: 20px;
        }
        
        .product-modal-name {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }
        
        .product-modal-price {
            font-size: 20px;
            font-weight: 600;
            color: #e74c3c;
            margin-bottom: 15px;
        }
        
        .product-modal-business {
            display: flex;
            align-items: center;
            padding: 12px;
            background-color: #f9f9f9;
            border-radius: 10px;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .product-modal-description {
            margin-bottom: 20px;
            line-height: 1.5;
            color: #555;
        }
        
        /* Product Modal Reviews Styles */
        .product-modal-reviews {
            margin-bottom: 20px;
        }
        
        .product-modal-reviews h3 {
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .product-modal-reviews h3 i {
            color: #FFD700;
        }
        
        .modal-reviews-container {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
        }
        
        .modal-reviews-loading {
            text-align: center;
            padding: 15px;
            color: #777;
        }
        
        .modal-no-reviews {
            text-align: center;
            padding: 15px;
            color: #777;
            font-style: italic;
        }
        
        .modal-review-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-review-item:last-child {
            border-bottom: none;
        }
        
        .modal-review-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .modal-reviewer-image {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin-right: 10px;
            border: 1px solid #ddd;
        }
        
        .modal-reviewer-info {
            flex: 1;
        }
        
        .modal-reviewer-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .modal-review-date {
            font-size: 12px;
            color: #777;
        }
        
        .modal-review-rating {
            display: flex;
            margin-bottom: 8px;
            font-size: 14px;
            color: #FFD700;
        }
        
        .modal-review-content {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .modal-view-all-reviews {
            text-align: center;
            padding: 10px;
            margin-top: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
            color: #e74c3c;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
        }
        
        .modal-view-all-reviews:hover {
            background-color: #e8e8e8;
        }
        
        .product-modal-quantity h3 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .quantity-button {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
        }
        
        .quantity-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .quantity-display {
            width: 40px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            background-color: #fff;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-total-price {
            display: flex;
            flex-direction: column;
        }
        
        .modal-total-price span:first-child {
            font-size: 12px;
            color: #777;
        }
        
        .modal-total-price span:last-child {
            font-size: 18px;
            font-weight: 700;
            color: #e74c3c;
        }
        
        .modal-add-to-cart {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s;
        }
        
        .modal-add-to-cart:hover {
            background-color: #c0392b;
        }
        
        .toast-notification.error {
            background-color: #dc3545;
        }
        
        .toast-notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
        
        .business-list-item {
            display: flex;
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .business-list-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .business-list-image {
            width: 100px;
            height: 100px;
            background-size: cover;
            background-position: center;
            background-color: var(--color-border);
        }
        
        .business-list-info {
            flex: 1;
            padding: 12px;
            position: relative;
        }
        
        .business-list-info h4 {
            margin: 0 0 5px 0;
            font-size: 1rem;
        }
        
        .business-type {
            color: var(--color-text-light);
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        
        .business-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
        }
        
        .business-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .business-meta i {
            font-size: 0.9rem;
        }
        
        .fa-star {
            color: #FFD700;
        }
        
        .fa-map-marker-alt {
            color: var(--color-primary);
        }
        
        .fa-check-circle.verified {
            color: #28a745;
        }
        
        .fa-check-circle.pending {
            color: #ffc107;
        }
        
        .business-list-item .fa-chevron-right {
            display: flex;
            align-items: center;
            padding: 0 15px;
            color: var(--color-text-light);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            margin: 20px 0;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--color-border);
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--color-text-light);
        }
        
        /* Product Styles */
        .business-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        /* Product Grid View */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding-bottom: 20px;
        }
        
        .product-card {
            background-color: var(--color-card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .product-card-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            z-index: 2;
        }
        
        .product-card-image {
            height: 160px;
            background-size: cover;
            background-position: center;
            background-color: var(--color-border);
            position: relative;
        }
        
        .product-card-info {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .product-card-name {
            margin: 0 0 5px 0;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 30px; /* Space for add to cart button */
        }
        
        .product-card-price {
            font-weight: 600;
            color: var(--color-primary);
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .product-card-business {
            display: flex;
            align-items: flex-start;
            padding-top: 10px;
            border-top: 1px solid var(--color-border);
            margin-top: auto;
        }
        
        .business-mini-image {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin-right: 8px;
            border: 1px solid var(--color-border);
            flex-shrink: 0;
        }
        
        .business-mini-info {
            flex: 1;
            min-width: 0; /* Helps with text overflow */
        }
        
        .business-mini-name {
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }
        
        .business-mini-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.7rem;
            color: var(--color-text-light);
        }
        
        .business-mini-distance {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .business-mini-verification {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .business-mini-verification i.verified {
            color: #28a745;
        }
        
        .business-mini-verification i.pending {
            color: #ffc107;
        }
        
        .add-to-cart-mini {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--color-primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .add-to-cart-mini:hover {
            background-color: #c0392b;
            transform: scale(1.1);
        }
        
        .business-products {
            padding: 15px;
            border-top: 1px solid var(--color-border);
        }
        
        .products-header {
            font-weight: 500;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--color-text-light);
        }
        
        .products-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .product-item {
            border-radius: var(--border-radius);
            overflow: hidden;
            background-color: var(--color-background);
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: var(--shadow-xs);
        }
        
        .product-item:hover {
            transform: translateY(-2px);
        }
        
        .product-image {
            height: 100px;
            background-size: cover;
            background-position: center;
            background-color: var(--color-border);
        }
        
        .product-info {
            padding: 8px;
        }
        
        .product-name {
            font-size: 0.85rem;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--color-primary);
            font-size: 0.85rem;
        }
        
        .view-all-products {
            grid-column: span 3;
            text-align: center;
            padding: 10px;
            margin-top: 5px;
            background-color: var(--color-background);
            border-radius: var(--border-radius);
            color: var(--color-primary);
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .view-all-products:hover {
            background-color: var(--color-border);
        }
        
        @media (max-width: 768px) {
            .products-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .view-all-products {
                grid-column: span 2;
            }
        }
        
        @media (max-width: 480px) {
            .business-list-image {
                width: 80px;
                height: 80px;
            }
            
            .business-meta {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .products-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .product-image {
                height: 80px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Explore Businesses</h1>
            <?php include_once 'includes/cart_icon.php'; ?>
            <button class="icon-button"><i class="fas fa-search"></i></button>
        </div>
        <div class="location-bar">
            <i class="fas fa-map-marker-alt"></i>
            <span>Manila, Philippines</span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Filter Section -->
        <section class="filter-section">
            <div class="filter-scroll">
                <button class="filter-button active">All</button>
                <button class="filter-button">Food</button>
                <button class="filter-button">Bakery</button>
                <button class="filter-button">Clothing</button>
                <button class="filter-button">Crafts</button>
                <button class="filter-button">Beauty</button>
            </div>
            <button class="sort-button"><i class="fas fa-sliders-h"></i> Filter</button>
        </section>

        <!-- View Toggle -->
        <section class="view-toggle">
            <button class="view-button active" onclick="showView('business')"><i class="fas fa-store"></i> Businesses</button>
            <button class="view-button" onclick="showView('product')"><i class="fas fa-box"></i> Products</button>
        </section>

        <!-- View Tabs -->
        <div id="view-container">
            <!-- Business View -->
            <section id="business-view" class="business-listings">
                <?php if (empty($businesses)): ?>
                <div class="empty-state">
                    <i class="fas fa-store-slash"></i>
                    <h3>No Businesses Found</h3>
                    <p>There are no active businesses to display at this time.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($businesses as $business): ?>
                    <div class="business-card">
                        <div class="business-list-item" onclick="location.href='business-detail.php?id=<?php echo $business['id']; ?>'">
                            <div class="business-list-image" style="background-image: url('../<?php echo htmlspecialchars($business['image_url'] ?: 'assets/images/default-business.jpg'); ?>')"></div>
                            <div class="business-list-info">
                                <h4><?php echo htmlspecialchars($business['name']); ?></h4>
                                <p class="business-type"><?php echo htmlspecialchars($business['category']); ?></p>
                                <div class="business-meta">
                                    <span><i class="fas fa-star"></i> <?php echo isset($business['rating']) ? number_format($business['rating'], 1) : 'New'; ?></span>
                                    <?php if (isset($business['distance']) && $business['distance'] !== null): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $business['distance']; ?> km</span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-check-circle <?php echo $business['verification_status'] === 'verified' ? 'verified' : 'pending'; ?>"></i> <?php echo ucfirst($business['verification_status'] ?: 'pending'); ?></span>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        
                        <?php if (!empty($business_products[$business['id']])): ?>
                        <div class="business-products">
                            <div class="products-header">Popular Products</div>
                            <div class="products-container">
                                <?php foreach ($business_products[$business['id']] as $product): ?>
                                <div class="product-item" onclick="location.href='product-detail.php?id=<?php echo $product['id']; ?>'">
                                    <div class="product-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($business_products[$business['id']]) > 0): ?>
                                <div class="view-all-products" onclick="location.href='business-detail.php?id=<?php echo $business['id']; ?>#products'">
                                    <i class="fas fa-th-list"></i> View All Products
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
            
            <!-- Product View -->
            <section id="product-view" class="product-listings" style="display: none;">
                <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Found</h3>
                    <p>There are no products available at this time.</p>
                </div>
                <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card" onclick="openProductModal(<?php echo $product['id']; ?>)">
                        <div class="product-card-badge">
                            <span><?php echo htmlspecialchars($product['business_category'] ?: 'Product'); ?></span>
                        </div>
                        <div class="product-card-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                        <div class="product-card-info">
                            <h4 class="product-card-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <div class="product-card-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-card-business">
                                <div class="business-mini-image" style="background-image: url('../<?php echo htmlspecialchars($product['business_image'] ?: 'assets/images/default-business.jpg'); ?>')"></div>
                                <div class="business-mini-info">
                                    <div class="business-mini-name"><?php echo htmlspecialchars($product['business_name']); ?></div>
                                    <div class="business-mini-meta">
                                        <?php if (isset($product['distance']) && $product['distance'] !== null): ?>
                                        <span class="business-mini-distance"><i class="fas fa-map-marker-alt"></i> <?php echo $product['distance']; ?> km</span>
                                        <?php endif; ?>
                                        <span class="business-mini-verification">
                                            <i class="fas fa-check-circle <?php echo $product['verification_status'] === 'verified' ? 'verified' : 'pending'; ?>"></i>
                                            <?php echo $product['verification_status'] === 'verified' ? 'Verified' : 'Pending'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button class="add-to-cart-mini" data-product-id="<?php echo $product['id']; ?>" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>, 1)">
                                <i class="fas fa-cart-plus"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
        <?php include_once 'includes/chatbot/index.php'; ?>
    </main>

    <!-- Product Modal -->
    <div id="product-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="product-modal-image"></div>
                <div class="product-modal-info">
                    <h2 class="product-modal-name"></h2>
                    <div class="product-modal-price"></div>
                    <div class="product-modal-business">
                        <div class="business-mini-image"></div>
                        <div class="business-mini-info">
                            <div class="business-mini-name"></div>
                            <div class="business-mini-meta">
                                <span class="business-mini-distance"></span>
                                <span class="business-mini-verification"></span>
                            </div>
                        </div>
                    </div>
                    <div class="product-modal-description"></div>
                    
                    <!-- Product Reviews Section -->
                    <div class="product-modal-reviews">
                        <h3><i class="fas fa-star"></i> Reviews</h3>
                        <div class="modal-reviews-container">
                            <div class="modal-reviews-loading">
                                <i class="fas fa-spinner fa-spin"></i> Loading reviews...
                            </div>
                            <div class="modal-reviews-list"></div>
                            <div class="modal-no-reviews" style="display: none;">
                                <p>No reviews yet for this product.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="product-modal-quantity">
                        <h3>Quantity</h3>
                        <div class="quantity-selector">
                            <button class="quantity-button" id="modal-decrease-quantity" disabled><i class="fas fa-minus"></i></button>
                            <div class="quantity-display" id="modal-quantity-value">1</div>
                            <button class="quantity-button" id="modal-increase-quantity"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="modal-total-price">
                    <span>Total Price</span>
                    <span id="modal-total-price"></span>
                </div>
                <button class="modal-add-to-cart" id="modal-add-to-cart-btn">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>

    <script src="js/script.js"></script>
    <script src="src/location.js"></script>
    <script>
        // Function to toggle between business and product views
        function showView(viewType) {
            const businessView = document.getElementById('business-view');
            const productView = document.getElementById('product-view');
            const viewButtons = document.querySelectorAll('.view-button');
            
            // Update view display
            if (viewType === 'business') {
                businessView.style.display = 'block';
                productView.style.display = 'none';
                viewButtons[0].classList.add('active');
                viewButtons[1].classList.remove('active');
            } else if (viewType === 'product') {
                businessView.style.display = 'none';
                productView.style.display = 'block';
                viewButtons[0].classList.remove('active');
                viewButtons[1].classList.add('active');
            }
            
            // Save the current view preference
            localStorage.setItem('orderko_view_preference', viewType);
        }
        
        // Function to open product modal
        function openProductModal(productId) {
            // Fetch product details via AJAX
            fetch(`get_product.php?id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show product modal with details
                        showProductModal(data.product);
                    } else {
                        showToast('Could not load product details.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching product:', error);
                    showToast('An error occurred while loading the product.', 'error');
                });
        }
        
        // Function to show product modal
        function showProductModal(product) {
            // Set product details in modal
            const modal = document.getElementById('product-modal');
            const modalImage = modal.querySelector('.product-modal-image');
            const modalName = modal.querySelector('.product-modal-name');
            const modalPrice = modal.querySelector('.product-modal-price');
            const modalBusinessImage = modal.querySelector('.business-mini-image');
            const modalBusinessName = modal.querySelector('.business-mini-name');
            const modalBusinessDistance = modal.querySelector('.business-mini-distance');
            const modalBusinessVerification = modal.querySelector('.business-mini-verification');
            const modalDescription = modal.querySelector('.product-modal-description');
            const modalTotalPrice = document.getElementById('modal-total-price');
            const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');
            
            // Set modal content
            modalImage.style.backgroundImage = `url('../${product.image_url || 'assets/images/default-product.jpg'}')`;            
            modalName.textContent = product.name;
            modalPrice.textContent = `₱${parseFloat(product.price).toFixed(2)}`;
            modalBusinessImage.style.backgroundImage = `url('../${product.business_image || 'assets/images/default-business.jpg'}')`;            
            modalBusinessName.textContent = product.business_name;
            
            // Set distance if available
            if (product.distance) {
                modalBusinessDistance.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${product.distance} km`;
                modalBusinessDistance.style.display = 'inline-flex';
            } else {
                modalBusinessDistance.style.display = 'none';
            }
            
            // Set verification status
            const verificationClass = product.verification_status === 'verified' ? 'verified' : 'pending';
            modalBusinessVerification.innerHTML = `<i class="fas fa-check-circle ${verificationClass}"></i> ${product.verification_status === 'verified' ? 'Verified' : 'Pending'}`;
            
            // Set description
            modalDescription.textContent = product.description || 'No description available';
            
            // Set total price
            modalTotalPrice.textContent = `₱${parseFloat(product.price).toFixed(2)}`;
            
            // Set add to cart button
            modalAddToCartBtn.onclick = () => {
                const quantity = parseInt(document.getElementById('modal-quantity-value').textContent);
                addToCart(product.id, quantity);
                closeProductModal();
            };
            
            // Load product reviews
            loadProductReviews(product.id);
            
            // Initialize quantity selector
            let modalQuantity = 1;
            const modalQuantityValue = document.getElementById('modal-quantity-value');
            const modalDecreaseBtn = document.getElementById('modal-decrease-quantity');
            const modalIncreaseBtn = document.getElementById('modal-increase-quantity');
            
            modalDecreaseBtn.onclick = () => {
                if (modalQuantity > 1) {
                    modalQuantity--;
                    modalQuantityValue.textContent = modalQuantity;
                    modalTotalPrice.textContent = `₱${(parseFloat(product.price) * modalQuantity).toFixed(2)}`;
                    modalDecreaseBtn.disabled = modalQuantity <= 1;
                }
            };
            
            modalIncreaseBtn.onclick = () => {
                if (modalQuantity < 10) {
                    modalQuantity++;
                    modalQuantityValue.textContent = modalQuantity;
                    modalTotalPrice.textContent = `₱${(parseFloat(product.price) * modalQuantity).toFixed(2)}`;
                    modalDecreaseBtn.disabled = false;
                    modalIncreaseBtn.disabled = modalQuantity >= 10;
                }
            };
            
            // Show the modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
            
            // Add event listener to close button
            const closeBtn = modal.querySelector('.modal-close');
            closeBtn.onclick = closeProductModal;
            
            // Close modal when clicking outside
            window.onclick = (event) => {
                if (event.target === modal) {
                    closeProductModal();
                }
            };
        }
        
        // Function to close product modal
        function closeProductModal() {
            const modal = document.getElementById('product-modal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
            
            // Reset reviews section
            const reviewsList = document.querySelector('.modal-reviews-list');
            const reviewsLoading = document.querySelector('.modal-reviews-loading');
            const noReviews = document.querySelector('.modal-no-reviews');
            
            if (reviewsList && reviewsLoading && noReviews) {
                reviewsList.innerHTML = '';
                reviewsLoading.style.display = 'block';
                noReviews.style.display = 'none';
            }
        }
        
        // Function to load product reviews
        function loadProductReviews(productId) {
            const reviewsList = document.querySelector('.modal-reviews-list');
            const reviewsLoading = document.querySelector('.modal-reviews-loading');
            const noReviews = document.querySelector('.modal-no-reviews');
            
            // Show loading state
            reviewsList.innerHTML = '';
            reviewsLoading.style.display = 'block';
            noReviews.style.display = 'none';
            
            // Fetch reviews via AJAX
            fetch(`get_product_reviews.php?product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    // Hide loading state
                    reviewsLoading.style.display = 'none';
                    
                    if (data.success && data.reviews && data.reviews.length > 0) {
                        // Display reviews (limited to 3 in the modal)
                        const reviews = data.reviews.slice(0, 3);
                        
                        reviews.forEach(review => {
                            const reviewItem = document.createElement('div');
                            reviewItem.className = 'modal-review-item';
                            
                            // Create review header with user info
                            const reviewHeader = document.createElement('div');
                            reviewHeader.className = 'modal-review-header';
                            
                            const reviewerImage = document.createElement('div');
                            reviewerImage.className = 'modal-reviewer-image';
                            reviewerImage.style.backgroundImage = `url('${review.user_image || '../assets/images/default-user.jpg'}')`;                            
                            
                            const reviewerInfo = document.createElement('div');
                            reviewerInfo.className = 'modal-reviewer-info';
                            
                            const reviewerName = document.createElement('div');
                            reviewerName.className = 'modal-reviewer-name';
                            reviewerName.textContent = review.user_name;
                            
                            const reviewDate = document.createElement('div');
                            reviewDate.className = 'modal-review-date';
                            reviewDate.textContent = new Date(review.created_at).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            });
                            
                            reviewerInfo.appendChild(reviewerName);
                            reviewerInfo.appendChild(reviewDate);
                            
                            reviewHeader.appendChild(reviewerImage);
                            reviewHeader.appendChild(reviewerInfo);
                            
                            // Create rating stars
                            const reviewRating = document.createElement('div');
                            reviewRating.className = 'modal-review-rating';
                            
                            for (let i = 1; i <= 5; i++) {
                                const star = document.createElement('i');
                                star.className = i <= review.rating ? 'fas fa-star' : 'far fa-star';
                                reviewRating.appendChild(star);
                            }
                            
                            // Create review content
                            const reviewContent = document.createElement('div');
                            reviewContent.className = 'modal-review-content';
                            reviewContent.textContent = review.comment;
                            
                            // Append all elements to review item
                            reviewItem.appendChild(reviewHeader);
                            reviewItem.appendChild(reviewRating);
                            reviewItem.appendChild(reviewContent);
                            
                            // Add review item to list
                            reviewsList.appendChild(reviewItem);
                        });
                        
                        // Add view all reviews button if there are more than 3 reviews
                        if (data.reviews.length > 3) {
                            const viewAllButton = document.createElement('div');
                            viewAllButton.className = 'modal-view-all-reviews';
                            viewAllButton.innerHTML = `<i class="fas fa-external-link-alt"></i> View All ${data.reviews.length} Reviews`;
                            viewAllButton.onclick = () => {
                                window.location.href = `product-detail.php?id=${productId}#reviews`;
                            };
                            
                            reviewsList.appendChild(viewAllButton);
                        }
                    } else {
                        // Show no reviews message
                        noReviews.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading reviews:', error);
                    reviewsLoading.style.display = 'none';
                    noReviews.style.display = 'block';
                    noReviews.querySelector('p').textContent = 'Error loading reviews. Please try again.';
                });
        }
        
        // Function to add product to cart
        function addToCart(productId, quantity) {
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
        
        // Initialize filter buttons
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-button');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Here you would implement category filtering
                    // For now, we're just updating the UI
                });
            });
            
            // Check for saved view preference
            const savedView = localStorage.getItem('orderko_view_preference');
            if (savedView) {
                showView(savedView);
            }
            
            // Initialize user location
            getUserLocation()
                .then(location => {
                    // Update location display
                    const locationBar = document.querySelector('.location-bar span');
                    if (locationBar && location.address) {
                        locationBar.textContent = location.address.split(',')[0] || 'Current Location';
                    }
                    
                    // Save location to session
                    saveUserLocation(location);
                })
                .catch(error => {
                    console.error('Error getting location:', error);
                });
        });
    </script>
</body>
</html>