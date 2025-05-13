<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Function to get notifications
function getNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM notifications 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Function to get unread notifications count
function getUnreadNotificationsCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications 
                           WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        // Mark single notification as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['notification_id'], $_SESSION['user_id']]);
        
        header('Location: notifications.php');
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
        // Mark all notifications as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        header('Location: notifications.php');
        exit;
    }
}

// Fetch notifications
$notifications = getNotifications($pdo, $_SESSION['user_id']);
$unread_count = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .notification-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
            position: relative;
            transition: background-color 0.2s;
        }
        
        .notification-card.unread {
            background-color: rgba(225, 173, 1, 0.1);
            border-left: 3px solid var(--color-primary);
        }
        
        .notification-icon {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--color-background);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-primary);
        }
        
        .notification-content {
            padding-left: 55px;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: var(--color-text);
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .notification-time {
            color: var(--color-text-light);
            font-size: 0.8rem;
        }
        
        .notification-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
        
        .notification-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--color-primary);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
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
        
        .date-divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: var(--color-text-light);
        }
        
        .date-divider::before,
        .date-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: var(--color-border);
        }
        
        .date-divider::before {
            margin-right: 10px;
        }
        
        .date-divider::after {
            margin-left: 10px;
        }
        
        @media (max-width: 480px) {
            .notification-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
            
            .notification-content {
                padding-left: 50px;
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
            <h1>Notifications</h1>
            <div style="width: 36px;"></div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <?php if ($unread_count > 0): ?>
        <div class="notification-header">
            <span><?php echo $unread_count; ?> unread notification<?php echo $unread_count > 1 ? 's' : ''; ?></span>
            <form method="POST" action="notifications.php">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="secondary-button small">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No Notifications</h3>
                <p>You don't have any notifications at the moment.</p>
            </div>
        <?php else: ?>
            <?php 
            $current_date = null;
            foreach ($notifications as $notification): 
                $notification_date = date('Y-m-d', strtotime($notification['created_at']));
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                
                $display_date = '';
                if ($notification_date === $today) {
                    $display_date = 'Today';
                } elseif ($notification_date === $yesterday) {
                    $display_date = 'Yesterday';
                } else {
                    $display_date = date('F j, Y', strtotime($notification['created_at']));
                }
                
                if ($current_date !== $display_date):
                    $current_date = $display_date;
            ?>
                <div class="date-divider"><?php echo $display_date; ?></div>
            <?php endif; ?>
            
            <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                <?php if (!$notification['is_read']): ?>
                    <div class="notification-badge">New</div>
                <?php endif; ?>
                
                <div class="notification-icon">
                    <?php
                    $icon = 'fa-bell';
                    if (stripos($notification['title'], 'order') !== false) {
                        $icon = 'fa-shopping-bag';
                    } elseif (stripos($notification['title'], 'promo') !== false || stripos($notification['title'], 'discount') !== false) {
                        $icon = 'fa-tag';
                    } elseif (stripos($notification['title'], 'delivery') !== false) {
                        $icon = 'fa-truck';
                    }
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                
                <div class="notification-content">
                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                    <div class="notification-time"><?php echo date('g:i A', strtotime($notification['created_at'])); ?></div>
                    
                    <?php if (!$notification['is_read']): ?>
                    <div class="notification-actions">
                        <form method="POST" action="notifications.php">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" class="secondary-button small">
                                <i class="fas fa-check"></i> Mark as Read
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>
</body>
</html>
