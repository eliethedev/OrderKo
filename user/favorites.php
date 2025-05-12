<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Function to get favorite businesses
function getFavorites($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT b.* FROM favorites f 
                           JOIN businesses b ON f.business_id = b.id 
                           WHERE f.user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Handle remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove' && isset($_POST['business_id'])) {
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND business_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_POST['business_id']]);
    
    header('Location: favorites.php');
    exit;
}

// Fetch favorites
$favorites = getFavorites($pdo, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorite Businesses - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .favorite-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
        }
        
        .favorite-image {
            height: 150px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .favorite-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .favorite-content {
            padding: 15px;
        }
        
        .favorite-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .favorite-name h3 {
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .favorite-category {
            color: var(--color-text-light);
            font-size: 0.85rem;
        }
        
        .favorite-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--color-primary);
            font-weight: 600;
        }
        
        .favorite-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: var(--color-text-light);
        }
        
        .favorite-actions {
            display: flex;
            gap: 10px;
        }
        
        .favorite-actions button {
            flex: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 15px;
            color: var(--color-text-light);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--color-border);
        }
        
        @media (min-width: 768px) {
            .favorites-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .favorite-card {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="back-button" onclick="window.location.href='profile.php'">
                <i class="fas fa-arrow-left"></i>
            </div>
            <h1>Favorite Businesses</h1>
            <div style="width: 36px;"></div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <i class="fas fa-heart"></i>
                <h3>No Favorites Yet</h3>
                <p>You haven't added any businesses to your favorites.</p>
                <button class="primary-button" onclick="window.location.href='businesses.php'" style="margin-top: 15px;">
                    <i class="fas fa-store"></i> Explore Businesses
                </button>
            </div>
        <?php else: ?>
            <div class="favorites-grid">
                <?php foreach ($favorites as $business): ?>
                    <div class="favorite-card">
                        <div class="favorite-image" style="background-image: url('<?php echo htmlspecialchars($business['image_url']); ?>')">
                            <div class="favorite-badge">
                                <i class="fas fa-clock"></i>
                                <span><?php echo htmlspecialchars($business['delivery_time'] ?? '30-45 min'); ?></span>
                            </div>
                        </div>
                        <div class="favorite-content">
                            <div class="favorite-header">
                                <div class="favorite-name">
                                    <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                                    <div class="favorite-category"><?php echo htmlspecialchars($business['category'] ?? 'Restaurant'); ?></div>
                                </div>
                                <div class="favorite-rating">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo number_format($business['rating'] ?? 4.5, 1); ?></span>
                                </div>
                            </div>
                            <div class="favorite-info">
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($business['distance'] ?? '1.2 km'); ?></span>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($business['price_range'] ?? '₱₱'); ?></span>
                            </div>
                            <div class="favorite-actions">
                                <button class="primary-button" onclick="window.location.href='business-detail.php?id=<?php echo $business['id']; ?>'">
                                    <i class="fas fa-shopping-bag"></i> Order
                                </button>
                                <form method="POST" action="favorites.php" style="flex: 1;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">
                                    <button type="submit" class="secondary-button full-width">
                                        <i class="fas fa-heart-broken"></i> Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="businesses.php" class="nav-item">
            <i class="fas fa-store"></i>
            <span>Explore</span>
        </a>
        <a href="orders.php" class="nav-item">
            <i class="fas fa-shopping-bag"></i>
            <span>Orders</span>
        </a>
        <a href="profile.php" class="nav-item active">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>
</body>
</html>
