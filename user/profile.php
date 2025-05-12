<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if user was found
if (!$user) {
    header('Location: ../login.php');
    exit;
}

// Fetch all user data
$stmt = $pdo->prepare("SELECT o.*, b.name as business_name, b.image_url as business_image
                       FROM orders o 
                       JOIN businesses b ON o.business_id = b.id 
                       WHERE o.customer_id = ? 
                       ORDER BY o.created_at DESC 
                       LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll();

// Fetch additional user data
$addresses = getAddresses($pdo, $_SESSION['user_id']);
$favorites = getFavorites($pdo, $_SESSION['user_id']);
$unread_notifications = getUnreadNotifications($pdo, $_SESSION['user_id']);

// Function to get addresses
function getAddresses($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}



// Function to get favorite businesses
function getFavorites($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT b.* FROM favorites f 
                           JOIN businesses b ON f.business_id = b.id 
                           WHERE f.user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Function to get unread notifications
function getUnreadNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM notifications 
                           WHERE user_id = ? AND is_read = FALSE 
                           ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Function to get order items
function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT oi.*, p.name, p.price 
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <h1>Profile</h1>
            <button class="icon-button" onclick="window.location.href='settings.php'"><i class="fas fa-cog"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Profile Header -->
        <section class="profile-header">
            <div class="profile-avatar">
                <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'https://randomuser.me/api/portraits/women/65.jpg'); ?>" alt="Profile Picture">
                <button class="edit-avatar" onclick="window.location.href='edit-profile.php'"><i class="fas fa-camera"></i></button>
            </div>
            <h2><?php echo htmlspecialchars($user['full_name'] ?? 'Unknown User'); ?></h2>
            <p class="profile-email"><?php echo htmlspecialchars($user['email'] ?? 'No email available'); ?></p>
            <button class="secondary-button edit-profile-button" onclick="window.location.href='edit-profile.php'">
                <i class="fas fa-user-edit"></i> Edit Profile
            </button>
        </section>

        <!-- Account Options -->
        <section class="account-options">
            <div class="option-card" onclick="window.location.href='addresses.php'">
                <div class="option-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="option-details">
                    <h3>My Addresses</h3>
                    <p><?php echo count($addresses) ?> address(es) saved</p>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
            
            <div class="option-card" onclick="window.location.href='favorites.php'">
                <div class="option-icon"><i class="fas fa-heart"></i></div>
                <div class="option-details">
                    <h3>Favorite Businesses</h3>
                    <p><?php echo count($favorites) ?> favorites</p>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
            
            <div class="option-card" onclick="window.location.href='notifications.php'">
                <div class="option-icon"><i class="fas fa-bell"></i></div>
                <div class="option-details">
                    <h3>Notifications</h3>
                    <p><?php echo count($unread_notifications) ?> unread notifications</p>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
            
            <div class="option-card" onclick="window.location.href='my-businesses.php'" style="background-color: rgba(225, 173, 1, 0.1);">
                <div class="option-icon" style="background-color: var(--color-primary); color: white;"><i class="fas fa-store"></i></div>
                <div class="option-details">
                    <h3>My Businesses</h3>
                    <p>Manage your businesses & products</p>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
        </section>

        <!-- Recent Orders -->
        <section class="recent-orders">
            <div class="section-header">
                <h3>Recent Orders</h3>
                <a href="orders.php" class="view-all">View All</a>
            </div>
            
            <?php foreach ($recent_orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-business">
                        <img src="<?php echo htmlspecialchars($order['business_image']); ?>" alt="<?php echo htmlspecialchars($order['business_name']); ?>">
                        <div>
                            <h4><?php echo htmlspecialchars($order['business_name']); ?></h4>
                            <p class="order-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></div>
                </div>
                <div class="order-items">
                    <?php
                    $items = getOrderItems($pdo, $order['id']);
                    foreach ($items as $item):
                    ?>
                    <p><?php echo $item['quantity']; ?> × <?php echo htmlspecialchars($item['name']); ?></p>
                    <?php endforeach; ?>
                </div>
                <div class="order-footer">
                    <span class="order-total"><?php echo htmlspecialchars($order['total_amount']); ?></span>
                    <button class="secondary-button small" onclick="window.location.href='business-detail.php?id=<?php echo $order['business_id']; ?>'">
                        Order Again
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Account Actions -->
        <section class="account-actions">
            <button class="action-button"><i class="fas fa-question-circle"></i> Help Center</button>
            <button class="action-button"><i class="fas fa-file-alt"></i> Terms & Privacy</button>
            <button class="action-button danger"><i class="fas fa-sign-out-alt"></i> Log Out</button>
        </section>

        <!-- App Version -->
        <section class="app-version">
            <p>OrderKo v1.0.0</p>
        </section>
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

    <script src="js/script.js"></script>
</body>
</html>