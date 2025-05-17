<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's location (default to Manila if not set)
$user_latitude = isset($_SESSION['user_latitude']) ? $_SESSION['user_latitude'] : 14.5995;
$user_longitude = isset($_SESSION['user_longitude']) ? $_SESSION['user_longitude'] : 120.9842;
$user_location = isset($_SESSION['user_location']) ? $_SESSION['user_location'] : 'Manila, Philippines';

// Fetch featured businesses (those with highest ratings)
try {
    // Simplified query to ensure businesses are displayed
    $sql = "SELECT b.*, 
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
            FROM businesses b 
            ORDER BY b.id DESC 
            LIMIT 4";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_latitude, $user_longitude, $user_latitude]);
    $featured_businesses = $stmt->fetchAll();
    
    // If no results, try a simpler query without the distance calculation
    if (empty($featured_businesses)) {
        $stmt = $pdo->query("SELECT * FROM businesses ORDER BY id DESC LIMIT 4");
        $featured_businesses = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Fallback to a simpler query if there's an error
    $stmt = $pdo->query("SELECT * FROM businesses ORDER BY id DESC LIMIT 4");
    $featured_businesses = $stmt->fetchAll();
    // Log the error
    error_log('Error fetching featured businesses: ' . $e->getMessage());
}

// Fetch nearby businesses
try {
    // Simplified query to ensure businesses are displayed
    $sql = "SELECT b.*, 
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
            FROM businesses b 
            ORDER BY b.id DESC 
            LIMIT 5";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_latitude, $user_longitude, $user_latitude]);
    $nearby_businesses = $stmt->fetchAll();
    
    // If no results, try a simpler query without the distance calculation
    if (empty($nearby_businesses)) {
        $stmt = $pdo->query("SELECT * FROM businesses ORDER BY id DESC LIMIT 5");
        $nearby_businesses = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Fallback to a simpler query if there's an error
    $stmt = $pdo->query("SELECT * FROM businesses ORDER BY id DESC LIMIT 5");
    $nearby_businesses = $stmt->fetchAll();
    // Log the error
    error_log('Error fetching nearby businesses: ' . $e->getMessage());
}

// Fetch all available categories
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM businesses WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fallback to default categories if there's an error
    $categories = ['Food', 'Restaurant', 'Bakery', 'Grocery', 'Clothing'];
    // Log the error
    error_log('Error fetching categories: ' . $e->getMessage());
}

// Fetch popular products
try {
    $sql = "SELECT p.*, b.name as business_name, b.id as business_id 
            FROM products p 
            JOIN businesses b ON p.business_id = b.id 
            WHERE p.is_available = 1 
            ORDER BY p.rating DESC, p.created_at DESC 
            LIMIT 6";
    $stmt = $pdo->query($sql);
    $popular_products = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback to empty array if there's an error
    $popular_products = [];
    // Log the error
    error_log('Error fetching popular products: ' . $e->getMessage());
}

// Fetch all products (limited to 10 for performance)
try {
    $sql = "SELECT p.*, b.name as business_name, b.id as business_id, b.category as business_category 
            FROM products p 
            JOIN businesses b ON p.business_id = b.id 
            WHERE p.is_available = 1 
            ORDER BY p.created_at DESC 
            LIMIT 10";
    $stmt = $pdo->query($sql);
    $all_products = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback to empty array if there's an error
    $all_products = [];
    // Log the error
    error_log('Error fetching all products: ' . $e->getMessage());
}

// Function to get category icon
function getCategoryIcon($category) {
    $category = strtolower($category);
    $icons = [
        'food' => 'fas fa-utensils',
        'restaurant' => 'fas fa-utensils',
        'clothing' => 'fas fa-tshirt',
        'fashion' => 'fas fa-tshirt',
        'crafts' => 'fas fa-gift',
        'handmade' => 'fas fa-gift',
        'bakery' => 'fas fa-bread-slice',
        'pastries' => 'fas fa-cookie',
        'beauty' => 'fas fa-spa',
        'cosmetics' => 'fas fa-spa',
        'grocery' => 'fas fa-shopping-basket',
        'produce' => 'fas fa-carrot',
        'organic' => 'fas fa-seedling',
        'electronics' => 'fas fa-laptop',
        'home' => 'fas fa-home',
        'furniture' => 'fas fa-couch',
        'books' => 'fas fa-book',
        'toys' => 'fas fa-gamepad',
        'sports' => 'fas fa-running',
        'jewelry' => 'fas fa-gem',
        'accessories' => 'fas fa-glasses',
        'health' => 'fas fa-heartbeat',
        'wellness' => 'fas fa-heart',
        'pets' => 'fas fa-paw',
        'art' => 'fas fa-palette',
        'stationery' => 'fas fa-pencil-alt',
        'drinks' => 'fas fa-coffee',
        'coffee' => 'fas fa-coffee',
        'tea' => 'fas fa-mug-hot',
        'alcohol' => 'fas fa-wine-bottle',
        'farmers' => 'fas fa-seedling',
        'fishers' => 'fas fa-fish',
        'filipino' => 'fas fa-utensils',
        'dessert' => 'fas fa-ice-cream'
    ];
    
    // Check if category contains any of our keywords
    foreach ($icons as $key => $icon) {
        if (strpos($category, $key) !== false) {
            return $icon;
        }
    }
    
    // Default icon
    return 'fas fa-store';
}

// Function to get business tag
function getBusinessTag($business) {
    if ($business['rating'] >= 4.8) {
        return '<span class="business-tag">Popular</span>';
    } elseif (strtotime($business['created_at']) > strtotime('-30 days')) {
        return '<span class="business-tag">New</span>';
    } elseif ($business['distance'] < 1) {
        return '<span class="business-tag">Nearby</span>';
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrderKo - Local Business Pre-Order App</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Splash Screen
    <div class="splash-screen" id="splash-screen">
        <div class="splash-content">
            <h1>OrderKo</h1>
            <p>Support local businesses</p>
            <div class="loader"></div>
        </div>
    </div>
     -->

    <!-- Header -->
    <header>
        <div class="header-container">
            <h1>OrderKo</h1>
            <div class="header-icons">
                <button class="icon-button"><i class="fas fa-search"></i></button>
                <?php include_once 'includes/cart_icon.php'; ?>
                <button class="icon-button" onclick="window.location.href='profile.php'"><i class="fas fa-user"></i></button>
            </div>
        </div>
        <div class="location-bar" onclick="updateLocation()">
            <i class="fas fa-map-marker-alt"></i>
            <span>Delivering to: <?php echo htmlspecialchars($user_location); ?></span>
            <i class="fas fa-chevron-down"></i>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <h2>Support Local Businesses</h2>
                <p>Pre-order and pickup from your favorite local shops</p>
                <button class="primary-button">Explore Nearby</button>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="categories">
            <h3>Categories</h3>
            <div class="category-scroll">
                <?php 
                // Display up to 5 categories, then add a "More" option
                $displayed_categories = 0;
                $max_categories = 5;
                
                foreach ($categories as $category):
                    if ($displayed_categories < $max_categories):
                        $icon_class = getCategoryIcon($category);
                ?>
                <div class="category-item" onclick="window.location.href='businesses.php?category=<?php echo urlencode($category); ?>'">
                    <div class="category-icon"><i class="<?php echo $icon_class; ?>"></i></div>
                    <span><?php echo htmlspecialchars($category); ?></span>
                </div>
                <?php 
                        $displayed_categories++;
                    endif;
                endforeach; 
                
                // Add "More" option if we have more categories
                if (count($categories) > $max_categories):
                ?>
                <div class="category-item" onclick="window.location.href='businesses.php'">
                    <div class="category-icon"><i class="fas fa-ellipsis-h"></i></div>
                    <span>More</span>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Featured Businesses -->
        <section class="featured">
            <div class="section-header">
                <h3>Featured Businesses</h3>
                <a href="businesses.php" class="view-all">View All</a>
            </div>
            <div class="business-scroll">
                <?php if (empty($featured_businesses)): ?>
                <div class="empty-state">
                    <i class="fas fa-store"></i>
                    <p>No featured businesses available</p>
                </div>
                <?php else: ?>
                <?php foreach ($featured_businesses as $business): ?>
                <div class="business-card" onclick="location.href='business-detail.php?id=<?php echo $business['id']; ?>'">
                    <div class="business-image" style="background-image: url('../<?php echo htmlspecialchars($business['image_url'] ?: 'assets/images/default-business.jpg'); ?>')"></div>
                    <div class="business-info">
                        <h4><?php echo htmlspecialchars($business['name']); ?></h4>
                        <p class="business-type"><?php echo htmlspecialchars($business['category'] ?? 'General'); ?></p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> <?php echo number_format($business['rating'] ?? 0, 1); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo $business['distance'] ? $business['distance'] . ' km' : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Nearby Businesses -->
        <section class="nearby">
            <div class="section-header">
                <h3>Nearby Businesses</h3>
                <a href="businesses.php?sort=distance" class="view-all">View All</a>
            </div>
            <div class="business-scroll horizontal">
                <?php if (empty($nearby_businesses)): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marker-alt"></i>
                    <p>No nearby businesses found</p>
                </div>
                <?php else: ?>
                <?php foreach ($nearby_businesses as $business): ?>
                <div class="business-card nearby-card" onclick="location.href='business-detail.php?id=<?php echo $business['id']; ?>'">
                    <div class="business-image" style="background-image: url('../<?php echo htmlspecialchars($business['image_url'] ?: 'assets/images/default-business.jpg'); ?>')"></div>
                    <div class="business-info">
                        <h4><?php echo htmlspecialchars($business['name']); ?></h4>
                        <p class="business-type"><?php echo htmlspecialchars($business['category'] ?? 'General'); ?></p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> <?php echo number_format($business['rating'] ?? 0, 1); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo $business['distance'] ? $business['distance'] . ' km' : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- Popular Products Section -->
        <section class="popular-products">
            <div class="section-header">
                <h3>Popular Products</h3>
                <a href="businesses.php" class="view-all">View All</a>
            </div>
            <div class="product-scroll">
                <?php if (empty($popular_products)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p>No popular products available</p>
                </div>
                <?php else: ?>
                <?php foreach ($popular_products as $product): ?>
                <div class="product-card" onclick="location.href='business-detail.php?id=<?php echo $product['business_id']; ?>#product-<?php echo $product['id']; ?>'">
                    <div class="product-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')">
                        <?php if (isset($product['discount_price']) && !empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                        <span class="product-tag">Sale</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p class="product-business"><?php echo htmlspecialchars($product['business_name']); ?></p>
                        <div class="product-meta">
                            <span class="product-price">
                                <?php if (isset($product['discount_price']) && !empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                <span class="original-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                ₱<?php echo number_format($product['discount_price'], 2); ?>
                                <?php else: ?>
                                ₱<?php echo number_format($product['price'], 2); ?>
                                <?php endif; ?>
                            </span>
                            <?php if (isset($product['rating'])): ?>
                            <span><i class="fas fa-star"></i> <?php echo number_format($product['rating'], 1); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- All Products Section -->
        <section class="all-products">
            <div class="section-header">
                <h3>All Products</h3>
                <a href="businesses.php" class="view-all">View All</a>
            </div>
            <div class="product-scroll">
                <?php if (empty($all_products)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p>No products available</p>
                </div>
                <?php else: ?>
                <?php foreach ($all_products as $product): ?>
                <div class="product-card" onclick="location.href='business-detail.php?id=<?php echo $product['business_id']; ?>#product-<?php echo $product['id']; ?>'">
                    <div class="product-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')">
                        <?php if (isset($product['discount_price']) && !empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                        <span class="product-tag">Sale</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <p class="product-business"><?php echo htmlspecialchars($product['business_name']); ?></p>
                        <div class="product-meta">
                            
                            <span class="product-price">
                                <?php if (isset($product['discount_price']) && !empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                <span class="original-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                ₱<?php echo number_format($product['discount_price'], 2); ?>
                                <?php else: ?>
                                ₱<?php echo number_format($product['price'], 2); ?>
                                <?php endif; ?>
                            </span>
                            <?php if (isset($product['rating'])): ?>
                            <span><i class="fas fa-star"></i> <?php echo number_format($product['rating'], 1); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>
    <script src="src/script.js"></script>
<style>
    /* Scrollable Sections Styles */
    .business-scroll, .product-scroll {
        display: flex;
        overflow-x: auto;
        padding: 10px 0;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        gap: 15px;
        scrollbar-width: thin;
        scrollbar-color: #ddd #f5f5f5;
    }
    
    .business-scroll::-webkit-scrollbar, .product-scroll::-webkit-scrollbar {
        height: 4px;
    }
    
    .business-scroll::-webkit-scrollbar-track, .product-scroll::-webkit-scrollbar-track {
        background: #f5f5f5;
        border-radius: 10px;
    }
    
    .business-scroll::-webkit-scrollbar-thumb, .product-scroll::-webkit-scrollbar-thumb {
        background: #ddd;
        border-radius: 10px;
    }
    
    .business-card, .product-card {
        flex: 0 0 auto;
        width: 200px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        background-color: #fff;
        scroll-snap-align: start;
        transition: transform 0.3s ease;
        margin-bottom: 5px;
    }
    
    .nearby-card {
        width: 220px;
    }
    
    .product-card {
        width: 160px;
    }
    
    .business-card:hover, .product-card:hover {
        transform: translateY(-5px);
    }
    
    .business-image, .product-image {
        height: 120px;
        background-size: cover;
        background-position: center;
        position: relative;
    }
    
    .product-tag {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: #ff4757;
        color: white;
        font-size: 0.7rem;
        padding: 3px 8px;
        border-radius: 10px;
    }
    
    .business-info, .product-info {
        padding: 10px;
    }
    
    .business-info h4, .product-info h4 {
        margin: 0 0 5px;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .business-type, .product-business {
        color: #666;
        font-size: 0.8rem;
        margin: 0 0 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .business-meta, .product-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
    }
    
    .product-price {
        font-weight: bold;
        color: #2ecc71;
    }
    
    .original-price {
        text-decoration: line-through;
        color: #999;
        font-size: 0.7rem;
        margin-right: 5px;
    }
    
    /* Section Spacing */
    .featured, .nearby, .popular-products, .all-products {
        padding: 15px;
    }
    
    .all-products {
        margin-bottom: 70px;
    }
</style>
    <script>
        // Function to handle location updates
        function updateLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    // Send location to server
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'update_location.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    // Reload page to update content based on new location
                                    window.location.reload();
                                } else {
                                    alert('Could not update location: ' + response.message);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                            }
                        }
                    };
                    
                    xhr.send('latitude=' + position.coords.latitude + '&longitude=' + position.coords.longitude);
                }, function(error) {
                    console.error('Error getting location:', error);
                    alert('Could not get your location. Please check your browser settings.');
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
        });
    </script>
</body>
</html>
