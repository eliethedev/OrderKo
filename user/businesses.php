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
    $query = "SELECT p.*, b.name as business_name, b.image_url as business_image, b.verification_status,
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
              FROM products p
              JOIN businesses b ON p.business_id = b.id
              ORDER BY p.created_at DESC
              LIMIT ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $limit, PDO::PARAM_INT);
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
        }
        
        .product-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .product-card-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            background-color: var(--color-border);
        }
        
        .product-card-info {
            padding: 12px;
        }
        
        .product-card-name {
            margin: 0 0 5px 0;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-card-price {
            font-weight: 600;
            color: var(--color-primary);
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .product-card-business {
            display: flex;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid var(--color-border);
        }
        
        .business-mini-image {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin-right: 8px;
            border: 1px solid var(--color-border);
        }
        
        .business-mini-info {
            flex: 1;
        }
        
        .business-mini-name {
            font-size: 0.8rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .business-mini-distance {
            font-size: 0.75rem;
            color: var(--color-text-light);
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .business-mini-verification {
            margin-left: 5px;
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
                    <div class="product-card" onclick="location.href='product-detail.php?id=<?php echo $product['id']; ?>'">
                        <div class="product-card-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                        <div class="product-card-info">
                            <h4 class="product-card-name"><?php echo htmlspecialchars($product['name']); ?></h4>
                            <div class="product-card-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-card-business">
                                <div class="business-mini-image" style="background-image: url('../<?php echo htmlspecialchars($product['business_image'] ?: 'assets/images/default-business.jpg'); ?>')"></div>
                                <div class="business-mini-info">
                                    <div class="business-mini-name"><?php echo htmlspecialchars($product['business_name']); ?></div>
                                    <?php if (isset($product['distance']) && $product['distance'] !== null): ?>
                                    <div class="business-mini-distance"><i class="fas fa-map-marker-alt"></i> <?php echo $product['distance']; ?> km</div>
                                    <?php endif; ?>
                                </div>
                                <div class="business-mini-verification">
                                    <i class="fas fa-check-circle <?php echo $product['verification_status'] === 'verified' ? 'verified' : 'pending'; ?>"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="businesses.php" class="nav-item active">
            <i class="fas fa-store"></i>
            <span>Explore</span>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-shopping-bag"></i>
            <span>Orders</span>
        </a>
        <a href="profile.php" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="js/script.js"></script>
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
        });
    </script>
</body>
</html>