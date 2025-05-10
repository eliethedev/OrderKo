<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch businesses from database
$stmt = $pdo->query("SELECT * FROM businesses ORDER BY rating DESC LIMIT 5");
$businesses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Businesses - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        <!-- Map View Toggle -->
        <section class="view-toggle">
            <button class="view-button active"><i class="fas fa-list"></i> List</button>
            <button class="view-button"><i class="fas fa-map-marked-alt"></i> Map</button>
        </section>

        <!-- Business Listings -->
        <section class="business-listings">
            <?php foreach ($businesses as $business): ?>
            <div class="business-list-item" onclick="location.href='business-detail.php?id=<?php echo $business['id']; ?>'">
                <div class="business-list-image" style="background-image: url('<?php echo htmlspecialchars($business['image_url']); ?>')"></div>
                <div class="business-list-info">
                    <h4><?php echo htmlspecialchars($business['name']); ?></h4>
                    <p class="business-type"><?php echo htmlspecialchars($business['category']); ?> • <?php echo htmlspecialchars($business['sub_category']); ?></p>
                    <div class="business-meta">
                        <span><i class="fas fa-star"></i> <?php echo $business['rating']; ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo $business['distance']; ?> km</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
            <?php endforeach; ?>
            </div>
        </section>
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
</body>
</html>