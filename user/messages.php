<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Set current user type
$current_user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'user';

// Set view mode (normal, archived, blocked)
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'normal';

// Handle unblock action
if (isset($_POST['action']) && $_POST['action'] === 'unblock' && isset($_POST['block_id'])) {
    $block_id = $_POST['block_id'];
    $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE id = ? AND user_id = ? AND user_type = ?");
    $stmt->execute([$block_id, $_SESSION['user_id'], $current_user_type]);
    header("Location: messages.php?view=blocked");
    exit;
}

// Handle unarchive action
if (isset($_POST['action']) && $_POST['action'] === 'unarchive' && isset($_POST['archive_id'])) {
    $archive_id = $_POST['archive_id'];
    $stmt = $pdo->prepare("DELETE FROM archived_conversations WHERE id = ? AND user_id = ? AND user_type = ?");
    $stmt->execute([$archive_id, $_SESSION['user_id'], $current_user_type]);
    header("Location: messages.php?view=archived");
    exit;
}

// Handle message actions
if (isset($_POST['action']) && isset($_POST['conversation_id']) && isset($_POST['conversation_type'])) {
    $action = $_POST['action'];
    $conversation_id = $_POST['conversation_id'];
    $conversation_type = $_POST['conversation_type'];
    
    try {
        switch ($action) {
            case 'delete':
                // Soft delete conversation (mark as deleted)
                $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 
                    WHERE ((sender_id = ? AND sender_type = ?) OR (recipient_id = ? AND recipient_type = ?))
                    AND ((sender_id = ? AND sender_type = ?) OR (recipient_id = ? AND recipient_type = ?))");
                $stmt->execute([
                    $_SESSION['user_id'], $current_user_type, $_SESSION['user_id'], $current_user_type,
                    $conversation_id, $conversation_type, $conversation_id, $conversation_type
                ]);
                break;
                
            case 'archive':
                // Archive conversation
                $stmt = $pdo->prepare("INSERT INTO archived_conversations (user_id, user_type, partner_id, partner_type) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $current_user_type, $conversation_id, $conversation_type]);
                break;
                
            case 'block':
                // Block user/business
                $stmt = $pdo->prepare("INSERT INTO blocked_users (user_id, user_type, blocked_id, blocked_type) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $current_user_type, $conversation_id, $conversation_type]);
                break;
        }
        
        // Refresh page after action
        header("Location: messages.php");
        exit;
    } catch (Exception $e) {
        $error_message = "Failed to complete action: " . $e->getMessage();
    }
}

// Check if user owns any businesses
$businesses_owned = [];
$stmt = $pdo->prepare("SELECT id FROM businesses WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
while ($row = $stmt->fetch()) {
    $businesses_owned[] = $row['id'];
}

// Fetch messages for the user with complete conversation partner information
$query = "
    SELECT m.*, 
           -- Get sender information
           CASE 
               WHEN m.sender_type = 'business' THEN b_sender.name 
               WHEN m.sender_type = 'user' THEN u_sender.full_name
           END as sender_name,
           CASE 
               WHEN m.sender_type = 'business' THEN CONCAT('../', IFNULL(b_sender.image_url, 'uploads/businesses/placeholder.jpg'))
               ELSE CONCAT('../', IFNULL(u_sender.profile_picture, 'uploads/profile_pictures/user-placeholder.jpg'))
           END as sender_image,
           
           -- Get recipient information
           CASE 
               WHEN m.recipient_type = 'business' THEN b_recipient.name 
               WHEN m.recipient_type = 'user' THEN u_recipient.full_name
           END as recipient_name,
           CASE 
               WHEN m.recipient_type = 'business' THEN CONCAT('../', IFNULL(b_recipient.image_url, 'uploads/businesses/placeholder.jpg'))
               ELSE CONCAT('../', IFNULL(u_recipient.profile_picture, 'uploads/profile_pictures/user-placeholder.jpg'))
           END as recipient_image,
           
           -- Get business context information (for business owners)
           CASE 
               WHEN m.business_context IS NOT NULL THEN b_context.name
               ELSE NULL
           END as business_context_name,
           CASE 
               WHEN m.business_context IS NOT NULL THEN CONCAT('../', IFNULL(b_context.image_url, 'uploads/businesses/placeholder.jpg'))
               ELSE NULL
           END as business_context_image
           
    FROM messages m
    -- Join for sender information
    LEFT JOIN businesses b_sender ON m.sender_id = b_sender.id AND m.sender_type = 'business'
    LEFT JOIN users u_sender ON m.sender_id = u_sender.id AND m.sender_type = 'user'
    
    -- Join for recipient information
    LEFT JOIN businesses b_recipient ON m.recipient_id = b_recipient.id AND m.recipient_type = 'business'
    LEFT JOIN users u_recipient ON m.recipient_id = u_recipient.id AND m.recipient_type = 'user'
    
    -- Join for business context (when user is a business owner)
    LEFT JOIN businesses b_context ON m.business_context = b_context.id
    
    -- Exclude blocked users and deleted messages
    LEFT JOIN blocked_users bu ON 
        (bu.user_id = ? AND bu.user_type = ? AND 
        ((bu.blocked_id = m.sender_id AND bu.blocked_type = m.sender_type) OR 
         (bu.blocked_id = m.recipient_id AND bu.blocked_type = m.recipient_type)))
    
    WHERE ((m.recipient_id = ? AND m.recipient_type = ?) 
        OR (m.sender_id = ? AND m.sender_type = ?)
";

// Add business owner condition if user owns businesses
if (!empty($businesses_owned)) {
    $placeholders = implode(',', array_fill(0, count($businesses_owned), '?'));
    $query .= " OR (m.business_context IN ($placeholders))";
}

$query .= ")
        AND m.is_deleted = 0
        AND bu.id IS NULL
    
    ORDER BY m.created_at DESC
";

$params = [
    $_SESSION['user_id'], $current_user_type,
    $_SESSION['user_id'], $current_user_type, 
    $_SESSION['user_id'], $current_user_type
];

// Add business IDs to params if user owns businesses
if (!empty($businesses_owned)) {
    $params = array_merge($params, $businesses_owned);
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Simplified query to get the latest message for each conversation and count unread messages
$query = "SELECT m.*, 
       CASE WHEN m.sender_type = 'business' THEN b_sender.name ELSE u_sender.full_name END as sender_name,
       CASE WHEN m.sender_type = 'business' THEN CONCAT('../', IFNULL(b_sender.image_url, 'uploads/businesses/placeholder.jpg'))
            ELSE CONCAT('../', IFNULL(u_sender.profile_picture, 'uploads/profile_pictures/user-placeholder.jpg')) END as sender_image,
       CASE WHEN m.recipient_type = 'business' THEN b_recipient.name ELSE u_recipient.full_name END as recipient_name,
       CASE WHEN m.recipient_type = 'business' THEN CONCAT('../', IFNULL(b_recipient.image_url, 'uploads/businesses/placeholder.jpg'))
            ELSE CONCAT('../', IFNULL(u_recipient.profile_picture, 'uploads/profile_pictures/user-placeholder.jpg')) END as recipient_image,
       CASE WHEN m.business_context IS NOT NULL THEN b_context.name ELSE NULL END as business_context_name,
       CASE WHEN m.business_context IS NOT NULL THEN CONCAT('../', IFNULL(b_context.image_url, 'uploads/businesses/placeholder.jpg'))
            ELSE NULL END as business_context_image,
       (SELECT COUNT(*) FROM messages 
        WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0 AND is_deleted = 0 AND
        ((sender_id = m.sender_id AND sender_type = m.sender_type) OR 
         (sender_id = m.recipient_id AND sender_type = m.recipient_type) OR
         (business_context = m.business_context))) as unread_count
       FROM (
           SELECT DISTINCT 
               CASE 
                   WHEN sender_id = ? AND sender_type = ? THEN CONCAT(recipient_type, '_', recipient_id)
                   WHEN recipient_id = ? AND recipient_type = ? THEN CONCAT(sender_type, '_', sender_id)
                   WHEN business_context IS NOT NULL THEN CONCAT('business_', business_context, '_user_', 
                       CASE WHEN sender_type = 'user' THEN sender_id ELSE recipient_id END)
               END as conversation_key,
               FIRST_VALUE(id) OVER (PARTITION BY 
                   CASE 
                       WHEN sender_id = ? AND sender_type = ? THEN CONCAT(recipient_type, '_', recipient_id)
                       WHEN recipient_id = ? AND recipient_type = ? THEN CONCAT(sender_type, '_', sender_id)
                       WHEN business_context IS NOT NULL THEN CONCAT('business_', business_context, '_user_', 
                           CASE WHEN sender_type = 'user' THEN sender_id ELSE recipient_id END)
                   END 
                   ORDER BY created_at DESC
               ) as latest_msg_id
           FROM messages
           WHERE is_deleted = 0 AND
                 ((sender_id = ? AND sender_type = ?) OR 
                  (recipient_id = ? AND recipient_type = ?) OR
                  (business_context IN (";

// Add placeholders for business IDs if user owns businesses
if (!empty($businesses_owned)) {
    $placeholders = implode(',', array_fill(0, count($businesses_owned), '?'));
    $query .= $placeholders;
}

$query .= ")))
       ) as latest_msgs
       JOIN messages m ON m.id = latest_msgs.latest_msg_id
       LEFT JOIN businesses b_sender ON m.sender_id = b_sender.id AND m.sender_type = 'business'
       LEFT JOIN users u_sender ON m.sender_id = u_sender.id AND m.sender_type = 'user'
       LEFT JOIN businesses b_recipient ON m.recipient_id = b_recipient.id AND m.recipient_type = 'business'
       LEFT JOIN users u_recipient ON m.recipient_id = u_recipient.id AND m.recipient_type = 'user'
       LEFT JOIN businesses b_context ON m.business_context = b_context.id
       LEFT JOIN blocked_users bu ON 
           (bu.user_id = ? AND bu.user_type = ? AND 
           ((bu.blocked_id = m.sender_id AND bu.blocked_type = m.sender_type) OR 
            (bu.blocked_id = m.recipient_id AND bu.blocked_type = m.recipient_type)))
       WHERE bu.id IS NULL
       ORDER BY m.created_at DESC";

// Prepare parameters
$params = [
    $_SESSION['user_id'], $current_user_type,  // For unread count
    $_SESSION['user_id'], $current_user_type,  // For conversation_key case 1
    $_SESSION['user_id'], $current_user_type,  // For conversation_key case 2
    $_SESSION['user_id'], $current_user_type,  // For PARTITION BY case 1
    $_SESSION['user_id'], $current_user_type,  // For PARTITION BY case 2
    $_SESSION['user_id'], $current_user_type,  // For WHERE conditions
    $_SESSION['user_id'], $current_user_type   // For WHERE conditions
];

// Add business IDs if user owns businesses
if (!empty($businesses_owned)) {
    $params = array_merge($params, $businesses_owned);
}

// Add parameters for blocked users join
$params = array_merge($params, [$_SESSION['user_id'], $current_user_type]);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$latest_messages = $stmt->fetchAll();

// Now create the conversations array from the latest messages
$conversations = [];
foreach ($latest_messages as $message) {
    // Handle business context for business owners
    if (!empty($message['business_context_name']) && in_array($message['business_context'], $businesses_owned)) {
        // This is a message related to the user's business
        $partner_key = 'business_' . $message['business_context'] . '_user_' . 
            ($message['sender_type'] == 'user' ? $message['sender_id'] : $message['recipient_id']);
        
        // Determine if the partner is the sender or recipient
        if ($message['sender_type'] == 'user') {
            $partner = [
                'id' => $message['sender_id'],
                'type' => 'user',
                'name' => $message['sender_name'],
                'image' => $message['sender_image'],
                'business_context' => $message['business_context'],
                'business_name' => $message['business_context_name'],
                'business_image' => $message['business_context_image']
            ];
        } else {
            $partner = [
                'id' => $message['recipient_id'],
                'type' => 'user',
                'name' => $message['recipient_name'],
                'image' => $message['recipient_image'],
                'business_context' => $message['business_context'],
                'business_name' => $message['business_context_name'],
                'business_image' => $message['business_context_image']
            ];
        }
    } else {
        // Regular message handling
        $current_user_is_sender = ($message['sender_id'] == $_SESSION['user_id'] && $message['sender_type'] == $current_user_type);
        
        if ($current_user_is_sender) {
            $partner_key = $message['recipient_type'].'_'.$message['recipient_id'];
            $partner = [
                'id' => $message['recipient_id'],
                'type' => $message['recipient_type'],
                'name' => $message['recipient_name'],
                'image' => $message['recipient_image']
            ];
        } else {
            $partner_key = $message['sender_type'].'_'.$message['sender_id'];
            $partner = [
                'id' => $message['sender_id'],
                'type' => $message['sender_type'],
                'name' => $message['sender_name'],
                'image' => $message['sender_image']
            ];
        }
    }
    
    // Add conversation to the list
    $conversations[$partner_key] = array_merge($partner, [
        'last_message' => $message['content'],
        'timestamp' => $message['created_at'],
        'unread' => ($message['unread_count'] > 0),
        'unread_count' => $message['unread_count']
    ]);
}

// Fetch archived conversations if in archived view
$archived_conversations = [];
if ($view_mode === 'archived') {
    $stmt = $pdo->prepare("
        SELECT ac.*, 
               -- Get partner information
               CASE 
                   WHEN ac.partner_type = 'business' THEN b.name 
                   WHEN ac.partner_type = 'user' THEN u.full_name
               END as partner_name,
               CASE 
                   WHEN ac.partner_type = 'business' THEN CONCAT('../', IFNULL(b.image_url, 'uploads/businesses/placeholder.jpg'))
                   ELSE CONCAT('../', IFNULL(u.profile_picture, 'uploads/profile_pictures/user-placeholder.jpg'))
               END as partner_image
        FROM archived_conversations ac
        LEFT JOIN businesses b ON ac.partner_id = b.id AND ac.partner_type = 'business'
        LEFT JOIN users u ON ac.partner_id = u.id AND ac.partner_type = 'user'
        WHERE ac.user_id = ? AND ac.user_type = ?
        ORDER BY ac.archived_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $current_user_type]);
    $archived_conversations = $stmt->fetchAll();
}

// Fetch blocked users if in blocked view
$blocked_users = [];
if ($view_mode === 'blocked') {
    $stmt = $pdo->prepare("
        SELECT bu.*, 
               -- Get blocked user/business information
               CASE 
                   WHEN bu.blocked_type = 'business' THEN b.name 
                   WHEN bu.blocked_type = 'user' THEN u.full_name
               END as blocked_name,
               CASE 
                   WHEN bu.blocked_type = 'business' THEN CONCAT('../', IFNULL(b.image_url, 'uploads/businesses/placeholder.jpg'))
                   ELSE CONCAT('../', IFNULL(u.profile_picture, 'uploads/profile_pictures/user-placeholder.jpg'))
               END as blocked_image
        FROM blocked_users bu
        LEFT JOIN businesses b ON bu.blocked_id = b.id AND bu.blocked_type = 'business'
        LEFT JOIN users u ON bu.blocked_id = u.id AND bu.blocked_type = 'user'
        WHERE bu.user_id = ? AND bu.user_type = ?
        ORDER BY bu.blocked_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $current_user_type]);
    $blocked_users = $stmt->fetchAll();
}

// Fetch nearby businesses if no messages
$nearby_businesses = [];
if ($view_mode === 'normal' && empty($conversations)) {
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, b.image_url, b.address, 
               b.description, AVG(r.rating) as avg_rating
        FROM businesses b
        LEFT JOIN reviews r ON b.id = r.user_id
        GROUP BY b.id
        ORDER BY RAND()
        LIMIT 5
    ");
    $stmt->execute();
    $nearby_businesses = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-list {
            padding: 10px 0;
        }
        
        .message-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
            background-color: #fff;
            transition: background-color 0.2s;
            justify-content: space-between;
        }
        
        .message-content-wrapper {
            display: flex;
            align-items: center;
            flex: 1;
            cursor: pointer;
        }
        
        .message-item:hover {
            background-color: #f9f9f9;
        }
        
        .message-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .message-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .message-content {
            flex: 1;
            min-width: 0;
        }
        
        .view-button.small {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            flex-shrink: 0;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 5px;
        }
        
        .message-name {
            font-weight: bold;
            font-size: 16px;
            margin: 0;
        }
        
        .business-context-label {
            display: inline-block;
            font-size: 12px;
            color: #0066cc;
            background-color: #f0f7ff;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
            font-weight: normal;
        }
        
        .message-time {
            color: #888;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .message-preview {
            color: #666;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .message-unread {
            background-color: #f0f7ff;
        }
        
        .message-unread .message-name {
            color: #0066cc;
        }
        
        .message-unread-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
            background-color: #0066cc;
            color: white;
            border-radius: 50%;
            margin-right: 5px;
            font-size: 10px;
            font-weight: bold;
            padding: 0 2px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .suggested-section {
            margin-top: 30px;
        }
        
        .suggested-section h3 {
            padding: 0 15px;
            margin-bottom: 15px;
        }
        
        .suggested-business {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
        }
        
        .suggested-business .business-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .suggested-business .business-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .suggested-business .business-info {
            flex: 1;
        }
        
        .suggested-business .business-name {
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .suggested-business .business-meta {
            display: flex;
            font-size: 12px;
            color: #666;
        }
        
        .suggested-business .business-meta span {
            margin-right: 10px;
        }
        
        .business-actions {
            display: flex;
            gap: 10px;
        }
        
        .business-actions-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 100px;
        }
        
        .message-button {
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 13px;
            cursor: pointer;
            flex: 1;
        }
        
        .view-button {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 13px;
            cursor: pointer;
            flex: 1;
        }
        
        .action-menu-button {
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            color: #666;
        }
        
        .action-menu {
            display: none;
            position: absolute;
            right: 15px;
            top: 50px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 100;
            min-width: 150px;
        }
        
        .action-menu-item {
            display: block;
            width: 100%;
            text-align: left;
            padding: 10px 15px;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .action-menu-item:hover {
            background-color: #f5f5f5;
        }
        
        .action-menu-item.danger {
            color: #e74c3c;
        }
        
        /* Message Tabs */
        .message-tabs {
            display: flex;
            background-color: #fff;
            border-bottom: 1px solid #eee;
        }
        
        .message-tab {
            flex: 1;
            text-align: center;
            padding: 15px 0;
            cursor: pointer;
            color: #666;
            font-weight: 500;
            position: relative;
        }
        
        .message-tab.active {
            color: #0066cc;
            font-weight: bold;
        }
        
        .message-tab.active:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #0066cc;
        }
        
        .message-count {
            display: inline-block;
            background-color: #eee;
            color: #666;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .archived-item, .blocked-item {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid #eee;
            align-items: center;
            background-color: #fff;
            transition: background-color 0.2s;
            justify-content: space-between;
        }
        
        .archived-item:hover, .blocked-item:hover {
            background-color: #f9f9f9;
        }
        
        .archived-content, .blocked-content {
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .archived-actions, .blocked-actions {
            display: flex;
            gap: 8px;
        }
        
        .archived-button, .blocked-button {
            background-color: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 15px;
            font-size: 13px;
            cursor: pointer;
        }
        
        .unarchive-button {
            background-color: #0066cc;
            color: white;
        }
        
        .unblock-button {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Messages</h1>
            <div class="header-icons">
                <button class="icon-button"><i class="fas fa-search"></i></button>
                <?php include_once 'includes/cart_icon.php'; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Message Tabs -->
        <div class="message-tabs">
            <a href="messages.php" class="message-tab <?php echo $view_mode === 'normal' ? 'active' : ''; ?>">
                Messages
                <?php if (!empty($conversations)): ?>
                    <span class="message-count"><?php echo count($conversations); ?></span>
                <?php endif; ?>
            </a>
            <a href="messages.php?view=archived" class="message-tab <?php echo $view_mode === 'archived' ? 'active' : ''; ?>">
                Archived
                <?php if (!empty($archived_conversations)): ?>
                    <span class="message-count"><?php echo count($archived_conversations); ?></span>
                <?php endif; ?>
            </a>
            <a href="messages.php?view=blocked" class="message-tab <?php echo $view_mode === 'blocked' ? 'active' : ''; ?>">
                Blocked
                <?php if (!empty($blocked_users)): ?>
                    <span class="message-count"><?php echo count($blocked_users); ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- Normal Messages View -->
        <?php if ($view_mode === 'normal'): ?>
            <?php if (!empty($conversations)): ?>
                <div class="message-list">
                <?php foreach ($conversations as $conversation): ?>
                    <div class="message-item <?php echo $conversation['unread'] ? 'message-unread' : ''; ?>">
                        <button class="action-menu-button" onclick="toggleActionMenu(this)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="action-menu">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this conversation?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                <input type="hidden" name="conversation_type" value="<?php echo $conversation['type']; ?>">
                                <button type="submit" class="action-menu-item danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                <input type="hidden" name="conversation_type" value="<?php echo $conversation['type']; ?>">
                                <button type="submit" class="action-menu-item">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to block this user?');">
                                <input type="hidden" name="action" value="block">
                                <input type="hidden" name="conversation_id" value="<?php echo $conversation['id']; ?>">
                                <input type="hidden" name="conversation_type" value="<?php echo $conversation['type']; ?>">
                                <button type="submit" class="action-menu-item danger">
                                    <i class="fas fa-ban"></i> Block
                                </button>
                            </form>
                        </div>
                        <div class="message-content-wrapper" onclick="location.href='conversation.php?type=<?php echo $conversation['type']; ?>&id=<?php echo $conversation['id']; ?><?php echo isset($conversation['business_context']) ? '&business_id='.$conversation['business_context'] : ''; ?>'">
                            <div class="message-avatar">
                                <img src="<?php echo htmlspecialchars($conversation['image']); ?>" alt="<?php echo htmlspecialchars($conversation['name']); ?>">
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <h4 class="message-name">
                                        <?php if ($conversation['unread']): ?>
                                            <span class="message-unread-badge"><?php echo $conversation['unread_count'] > 1 ? $conversation['unread_count'] : ''; ?></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($conversation['name']); ?>
                                        <?php if (isset($conversation['business_context'])): ?>
                                            <small class="business-context-label">via <?php echo htmlspecialchars($conversation['business_name']); ?></small>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="message-time"><?php echo date('M j', strtotime($conversation['timestamp'])); ?></span>
                                </div>
                                <p class="message-preview">
                                    <?php 
                                    $preview_content = htmlspecialchars($conversation['last_message']);
                                    
                                    // Check if message contains an attachment
                                    if (preg_match('/\[attachment\](.*?)\[\/attachment\]/', $preview_content)) {
                                        $file_path = preg_replace('/.*\[attachment\](.*?)\[\/attachment\].*/', '$1', $preview_content);
                                        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
                                        $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif']);
                                        
                                        if ($is_image) {
                                            echo '<i class="fas fa-image"></i> Sent a photo';
                                        } else {
                                            echo '<i class="fas fa-file"></i> Sent a file';
                                        }
                                        
                                        // Get any text content outside the attachment tag
                                        $text_content = trim(preg_replace('/\[attachment\].*?\[\/attachment\]/', '', $preview_content));
                                        if (!empty($text_content)) {
                                            echo ': ' . $text_content;
                                        }
                                    } 
                                    // For backward compatibility, also check for image tags
                                    else if (preg_match('/\[img\](.*?)\[\/img\]/', $preview_content)) {
                                        echo '<i class="fas fa-image"></i> Sent a photo';
                                        
                                        // Get any text content outside the image tag
                                        $text_content = trim(preg_replace('/\[img\].*?\[\/img\]/', '', $preview_content));
                                        if (!empty($text_content)) {
                                            echo ': ' . $text_content;
                                        }
                                    } else {
                                        echo $preview_content;
                                    }
                                    ?>
                                </p>
                            </div>
                            <?php if ($conversation['type'] == 'business'): ?>
                                <button class="view-button small" onclick="event.stopPropagation(); location.href='business-detail.php?id=<?php echo $conversation['id']; ?>'">
                                    <i class="fas fa-store"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-comment-dots"></i>
                    <h3>No messages yet</h3>
                    <p>Start a conversation with a local business to place orders or ask questions.</p>
                </div>
            <?php endif; ?>
        
        <!-- Archived Messages View -->
        <?php elseif ($view_mode === 'archived'): ?>
            <?php if (!empty($archived_conversations)): ?>
                <div class="message-list">
                    <?php foreach ($archived_conversations as $archived): ?>
                        <div class="archived-item">
                            <div class="archived-content">
                                <div class="message-avatar">
                                    <img src="<?php echo htmlspecialchars($archived['partner_image']); ?>" alt="<?php echo htmlspecialchars($archived['partner_name']); ?>">
                                </div>
                                <div class="message-content">
                                    <div class="message-header">
                                        <h4 class="message-name"><?php echo htmlspecialchars($archived['partner_name']); ?></h4>
                                        <span class="message-time"><?php echo date('M j, Y', strtotime($archived['archived_at'])); ?></span>
                                    </div>
                                    <p class="message-preview">Archived on <?php echo date('F j, Y', strtotime($archived['archived_at'])); ?></p>
                                </div>
                            </div>
                            <div class="archived-actions">
                                <form method="POST">
                                    <input type="hidden" name="action" value="unarchive">
                                    <input type="hidden" name="archive_id" value="<?php echo $archived['id']; ?>">
                                    <button type="submit" class="archived-button unarchive-button">
                                        <i class="fas fa-box-open"></i> Unarchive
                                    </button>
                                </form>
                                <button class="archived-button" onclick="location.href='conversation.php?type=<?php echo $archived['partner_type']; ?>&id=<?php echo $archived['partner_id']; ?>'">
                                    <i class="fas fa-comment"></i> View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>No archived conversations</h3>
                    <p>When you archive conversations, they will appear here.</p>
                </div>
            <?php endif; ?>
            
        <!-- Blocked Users View -->
        <?php elseif ($view_mode === 'blocked'): ?>
            <?php if (!empty($blocked_users)): ?>
                <div class="message-list">
                    <?php foreach ($blocked_users as $blocked): ?>
                        <div class="blocked-item">
                            <div class="blocked-content">
                                <div class="message-avatar">
                                    <img src="<?php echo htmlspecialchars($blocked['blocked_image']); ?>" alt="<?php echo htmlspecialchars($blocked['blocked_name']); ?>">
                                </div>
                                <div class="message-content">
                                    <div class="message-header">
                                        <h4 class="message-name"><?php echo htmlspecialchars($blocked['blocked_name']); ?></h4>
                                        <span class="message-time"><?php echo date('M j, Y', strtotime($blocked['blocked_at'])); ?></span>
                                    </div>
                                    <p class="message-preview">Blocked on <?php echo date('F j, Y', strtotime($blocked['blocked_at'])); ?></p>
                                </div>
                            </div>
                            <div class="blocked-actions">
                                <form method="POST" onsubmit="return confirm('Are you sure you want to unblock this user?');">
                                    <input type="hidden" name="action" value="unblock">
                                    <input type="hidden" name="block_id" value="<?php echo $blocked['id']; ?>">
                                    <button type="submit" class="blocked-button unblock-button">
                                        <i class="fas fa-user-check"></i> Unblock
                                    </button>
                                </form>
                                <button class="blocked-button" onclick="location.href='conversation.php?type=<?php echo $blocked['blocked_type']; ?>&id=<?php echo $blocked['blocked_id']; ?>'">
                                    <i class="fas fa-comment"></i> View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-ban"></i>
                    <h3>No blocked users</h3>
                    <p>When you block users or businesses, they will appear here.</p>
                </div>
            <?php endif; ?>
        
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-comment-dots"></i>
                <h3>No messages yet</h3>
                <p>Start a conversation with a local business to place orders or ask questions.</p>
            </div>
            
            <?php if (!empty($nearby_businesses)): ?>
                <div class="suggested-section">
                    <h3>Nearby Businesses to Message</h3>
                    <?php foreach ($nearby_businesses as $business): ?>
                        <div class="suggested-business">
                            <div class="business-image">
                                <img src="<?php echo htmlspecialchars($business['image_url'] ? '../' . $business['image_url'] : '../uploads/businesses/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($business['name']); ?>">
                            </div>
                            <div class="business-info">
                                <h4 class="business-name"><?php echo htmlspecialchars($business['name']); ?></h4>
                                <div class="business-meta">
                                    <span><i class="fas fa-star"></i> <?php echo number_format($business['avg_rating'], 1); ?></span>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($business['address'], 0, 20) . (strlen($business['address']) > 20 ? '...' : '')); ?></span>
                                </div>
                            </div>
                            <div class="business-actions-column">
                                <button class="message-button" onclick="location.href='conversation.php?type=business&id=<?php echo $business['id']; ?>'">
                                    <i class="fas fa-comment"></i> Message
                                </button>
                                <button class="view-button" onclick="location.href='business-detail.php?id=<?php echo $business['id']; ?>'">
                                    <i class="fas fa-store"></i> View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>

    <script>
        // Mark messages as read when clicked
        document.querySelectorAll('.message-item').forEach(item => {
            item.addEventListener('click', function() {
                // You could add AJAX call here to mark message as read in the database
                this.classList.remove('message-unread');
                const badge = this.querySelector('.message-unread-badge');
                if (badge) badge.remove();
            });
        });
        
        // Toggle action menu
        function toggleActionMenu(button) {
            event.stopPropagation();
            const menu = button.nextElementSibling;
            const allMenus = document.querySelectorAll('.action-menu');
            
            // Close all other menus
            allMenus.forEach(m => {
                if (m !== menu) m.style.display = 'none';
            });
            
            // Toggle current menu
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }
        }
        
        // Close menu when clicking elsewhere
        document.addEventListener('click', function() {
            document.querySelectorAll('.action-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        });
    </script>
</body>
</html>
