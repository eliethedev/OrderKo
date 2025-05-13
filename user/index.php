<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
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
    <!-- Splash Screen -->
    <div class="splash-screen" id="splash-screen">
        <div class="splash-content">
            <h1>OrderKo</h1>
            <p>Support local businesses</p>
            <div class="loader"></div>
        </div>
    </div>

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
        <div class="location-bar">
            <i class="fas fa-map-marker-alt"></i>
            <span>Delivering to: Manila, Philippines</span>
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
                <div class="category-item">
                    <div class="category-icon"><i class="fas fa-utensils"></i></div>
                    <span>Food</span>
                </div>
                <div class="category-item">
                    <div class="category-icon"><i class="fas fa-tshirt"></i></div>
                    <span>Clothing</span>
                </div>
                <div class="category-item">
                    <div class="category-icon"><i class="fas fa-gift"></i></div>
                    <span>Crafts</span>
                </div>
                <div class="category-item">
                    <div class="category-icon"><i class="fas fa-bread-slice"></i></div>
                    <span>Bakery</span>
                </div>
                <div class="category-item">
                    <div class="category-icon"><i class="fas fa-spa"></i></div>
                    <span>Beauty</span>
                </div>
                <div class="category-item">
                    <div class="category-icon"><i class="fas fa-ellipsis-h"></i></div>
                    <span>More</span>
                </div>
            </div>
        </section>

        <!-- Featured Businesses -->
        <section class="featured">
            <div class="section-header">
                <h3>Featured Businesses</h3>
                <a href="businesses.php" class="view-all">View All</a>
            </div>
            <div class="business-grid">
                <div class="business-card" onclick="location.href='business-detail.php'">
                    <div class="business-image" style="background-image: url('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60')">
                        <span class="business-tag">Popular</span>
                    </div>
                    <div class="business-info">
                        <h4>Maria's Bakeshop</h4>
                        <p class="business-type">Bakery • Pastries</p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> 4.8</span>
                            <span><i class="fas fa-map-marker-alt"></i> 1.2 km</span>
                        </div>
                    </div>
                </div>
                <div class="business-card" onclick="location.href='business-detail.php'">
                    <div class="business-image" style="background-image: url('https://images.unsplash.com/photo-1467003909585-2f8a72700288?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60')">
                        <span class="business-tag">New</span>
                    </div>
                    <div class="business-info">
                        <h4>Lola's Kitchen</h4>
                        <p class="business-type">Home Cooked • Filipino</p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> 4.6</span>
                            <span><i class="fas fa-map-marker-alt"></i> 0.8 km</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Nearby Businesses -->
        <section class="nearby">
            <div class="section-header">
                <h3>Nearby Businesses</h3>
                <a href="businesses.php" class="view-all">View All</a>
            </div>
            <div class="business-list">
                <div class="business-list-item" onclick="location.href='business-detail.php'">
                    <div class="business-list-image" style="background-image: url('https://images.unsplash.com/photo-1516684732162-798a0062be99?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60')"></div>
                    <div class="business-list-info">
                        <h4>Craft Corner</h4>
                        <p class="business-type">Handmade • Crafts</p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> 4.7</span>
                            <span><i class="fas fa-map-marker-alt"></i> 1.5 km</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="business-list-item" onclick="location.href='business-detail.php'">
                    <div class="business-list-image" style="background-image: url('https://images.unsplash.com/photo-1470309864661-68328b2cd0a5?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60')"></div>
                    <div class="business-list-info">
                        <h4>Tita's Clothing</h4>
                        <p class="business-type">Fashion • Accessories</p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> 4.5</span>
                            <span><i class="fas fa-map-marker-alt"></i> 2.1 km</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="business-list-item" onclick="location.href='business-detail.php'">
                    <div class="business-list-image" style="background-image: url('https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60')"></div>
                    <div class="business-list-info">
                        <h4>Fresh Farms</h4>
                        <p class="business-type">Produce • Organic</p>
                        <div class="business-meta">
                            <span><i class="fas fa-star"></i> 4.9</span>
                            <span><i class="fas fa-map-marker-alt"></i> 1.7 km</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        </section>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>
    <script src="src/script.js"></script>
</body>
</html>