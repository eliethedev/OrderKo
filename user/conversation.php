<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if conversation parameters are provided
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header('Location: messages.php');
    exit;
}

$conversation_type = $_GET['type'];
$conversation_id = $_GET['id'];
$business_id = isset($_GET['business_id']) ? $_GET['business_id'] : null;

// Check if user owns the business (for business owners)
$is_business_owner = false;
if ($business_id) {
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE id = ? AND user_id = ?");
    $stmt->execute([$business_id, $_SESSION['user_id']]);
    $is_business_owner = $stmt->fetch() ? true : false;
}

// Get conversation partner details
if ($conversation_type == 'business') {
    $stmt = $pdo->prepare("SELECT b.id, b.name, b.image_url, b.address, b.description, u.id as owner_id, u.full_name as owner_name 
                         FROM businesses b 
                         LEFT JOIN users u ON b.user_id = u.id 
                         WHERE b.id = ?");
    $stmt->execute([$conversation_id]);
    $partner = $stmt->fetch();
    $partner_name = $partner['name'];
    $partner_image = $partner['image_url'] ? '../' . $partner['image_url'] : '../uploads/businesses/placeholder.jpg';
    $business_owner_id = $partner['owner_id'];
    $business_owner_name = $partner['owner_name'];
    $business_address = $partner['address'];
    $business_description = $partner['description'];
    
    // If current user is the business owner, update the recipient_id for messages
    $is_business_owner = ($business_owner_id == $_SESSION['user_id']);
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $partner = $stmt->fetch();
    $partner_name = $partner['full_name'];
    $partner_image = $partner['profile_picture'] ? '../' . $partner['profile_picture'] : '../uploads/profile_pictures/user-placeholder.jpg';
    $is_business_owner = false;
}

// Mark all messages from this conversation as read
$current_user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'user';
$stmt = $pdo->prepare("
    UPDATE messages 
    SET is_read = 1 
    WHERE recipient_id = ? 
    AND recipient_type = ? 
    AND sender_id = ? 
    AND sender_type = ?
");
$stmt->execute([$_SESSION['user_id'], $current_user_type, $conversation_id, $conversation_type]);

// Check if user has blocked this conversation partner
$stmt = $pdo->prepare("SELECT id FROM blocked_users WHERE user_id = ? AND user_type = ? AND blocked_id = ? AND blocked_type = ?");
$stmt->execute([$_SESSION['user_id'], $current_user_type, $conversation_id, $conversation_type]);
$is_blocked = $stmt->fetch();

if ($is_blocked) {
    // If blocked, show a message and disable sending
    $blocked = true;
    $messages = [];
} else {
    $blocked = false;
    
    // Get conversation messages - include business context for business owners
    if ($business_id && $is_business_owner) {
        // For business owners viewing messages about their business
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u.full_name as customer_name,
                   u.profile_picture as customer_image,
                   b.name as business_name,
                   b.image_url as business_image
            FROM messages m
            LEFT JOIN users u ON (m.sender_type = 'user' AND m.sender_id = u.id) OR (m.recipient_type = 'user' AND m.recipient_id = u.id)
            LEFT JOIN businesses b ON m.business_context = b.id
            WHERE ((m.sender_id = ? AND m.sender_type = 'user' AND m.recipient_id = ?)
                   OR (m.recipient_id = ? AND m.recipient_type = 'user' AND m.sender_id = ?))
                   OR (m.business_context = ?)
            AND m.is_deleted = 0
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([
            $conversation_id, $_SESSION['user_id'],
            $conversation_id, $_SESSION['user_id'],
            $business_id
        ]);
    } else if ($is_business_owner) {
        // For business owners, show all messages related to their business
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   u.full_name as customer_name,
                   u.profile_picture as customer_image
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'user'
            WHERE ((m.sender_id = ? AND m.sender_type = ?) OR (m.recipient_id = ? AND m.recipient_type = ?))
            AND (m.business_context = ? OR m.recipient_id = ? OR m.sender_id = ?)
            AND m.is_deleted = 0
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([
            $_SESSION['user_id'], $current_user_type, $_SESSION['user_id'], $current_user_type,
            $conversation_id, $conversation_id, $conversation_id
        ]);
    } else {
        // For regular users, show normal conversation
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = ? AND sender_type = ? AND recipient_id = ? AND recipient_type = ?)
            OR (sender_id = ? AND sender_type = ? AND recipient_id = ? AND recipient_type = ?))
            AND is_deleted = 0
            ORDER BY created_at ASC
        ");
        $stmt->execute([
            $conversation_id, $conversation_type, $_SESSION['user_id'], $current_user_type,
            $_SESSION['user_id'], $current_user_type, $conversation_id, $conversation_type
        ]);
    }
    $messages = $stmt->fetchAll();
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    $message_content = isset($_POST['message']) ? trim($_POST['message']) : '';
    $has_attachment = false;
    $attachment_path = '';
    
    // Handle file upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_dir = '../uploads/message_attachments/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            // Generate unique filename
            $new_filename = uniqid('attachment_') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $has_attachment = true;
                $attachment_path = 'uploads/message_attachments/' . $new_filename;
                
                // Add attachment tag to message
                if (!empty($message_content)) {
                    $message_content .= '\n';
                }
                $message_content .= '[attachment]' . $attachment_path . '[/attachment]';
            }
        }
    }
    
    if (!empty($message_content)) {
        try {
            // Begin transaction to ensure database consistency
            $pdo->beginTransaction();
            
            // Handle business context for business owners
            if ($business_id && $is_business_owner) {
                // Business owner replying to a customer about their business
                // Get the customer ID from the conversation
                $recipient_id = $conversation_id;
                $recipient_type = 'user';
                $business_context = $business_id;
            } else if ($conversation_type == 'business' && $is_business_owner) {
                // If the current user is the business owner, get the last customer who messaged this business
                $stmt = $pdo->prepare("
                    SELECT sender_id FROM messages 
                    WHERE recipient_id = ? AND recipient_type = 'business' 
                    AND sender_type = 'user' AND sender_id != ?
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$conversation_id, $_SESSION['user_id']]);
                $last_customer = $stmt->fetch();
                
                if ($last_customer) {
                    // Send message to the customer
                    $recipient_id = $last_customer['sender_id'];
                    $recipient_type = 'user';
                    $business_context = $conversation_id;
                } else {
                    // No customer found, use default recipient
                    $recipient_id = $conversation_id;
                    $recipient_type = $conversation_type;
                    $business_context = $conversation_id;
                }
            } else {
                // Regular user sending to business or another user
                $recipient_id = $conversation_id;
                $recipient_type = $conversation_type;
                
                // If sending to a business, store business_id in a metadata field for context
                if ($conversation_type == 'business') {
                    $business_context = $conversation_id;
                } else {
                    $business_context = $business_id; // Will be NULL if not set
                }
            }
            
            // Insert message
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, sender_type, recipient_id, recipient_type, content, created_at, is_read, business_context)
                VALUES (?, ?, ?, ?, ?, NOW(), 0, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], 
                $current_user_type, 
                $recipient_id, 
                $recipient_type, 
                $message_content,
                $business_context
            ]);
            
            // We'll keep track of the conversation in a simpler way without requiring additional tables
            // This code is commented out as the notifications table may not exist
            /*
            if ($conversation_type == 'business') {
                // Check if the business has a user account associated with it
                $stmt = $pdo->prepare("SELECT user_id FROM businesses WHERE id = ?");
                $stmt->execute([$conversation_id]);
                $business_user = $stmt->fetch();
                
                if ($business_user && $business_user['user_id']) {
                    // This would notify the business owner about the new message
                    // but requires a notifications table which may not exist
                }
            }
            */
            
            // Commit the transaction
            $pdo->commit();
            
            // Redirect to avoid form resubmission
            header("Location: conversation.php?type=$conversation_type&id=$conversation_id");
            exit;
        } catch (Exception $e) {
            // Rollback the transaction if something failed
            $pdo->rollBack();
            $error_message = "Failed to send message: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .conversation-header {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            background-color: #fff;
        }
        
        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-partner-name {
            font-weight: bold;
            margin: 0;
        }
        
        .conversation-status {
            font-size: 12px;
            color: #666;
        }
        
        .message-container {
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 180px);
        }
        
        .message-bubble {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message-sent {
            align-self: flex-end;
            background-color: #0066cc;
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message-received {
            align-self: flex-start;
            background-color: #f1f1f1;
            color: #333;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 11px;
            margin-top: 5px;
            opacity: 0.8;
        }
        
        .message-input-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #fff;
            padding: 10px 15px;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .message-input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 10px 15px;
            margin-right: 10px;
            font-size: 14px;
            outline: none;
        }
        
        .send-button {
            background-color: #0066cc;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .date-divider {
            text-align: center;
            margin: 15px 0;
            position: relative;
        }
        
        .date-divider span {
            background-color: #f9f9f9;
            padding: 0 10px;
            font-size: 12px;
            color: #888;
            position: relative;
            z-index: 1;
        }
        
        .date-divider:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #eee;
            z-index: 0;
        }
        
        .empty-conversation {
            text-align: center;
            color: #888;
            margin-top: 50px;
        }
        
        .empty-conversation i, .blocked-conversation i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        .blocked-conversation {
            text-align: center;
            color: #e74c3c;
            margin-top: 50px;
        }
        
        .blocked-conversation i {
            color: #e74c3c;
        }
        
        .message-image {
            margin: 10px 0;
        }
        
        .message-image img {
            max-width: 100%;
            border-radius: 8px;
        }
        
        .file-upload-button {
            background: none;
            border: none;
            color: #666;
            font-size: 20px;
            padding: 8px 12px;
            cursor: pointer;
            position: relative;
        }
        
        .file-upload-button:hover {
            color: #0066cc;
        }
        
        .file-upload-button input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .message-attachment {
            background-color: rgba(0, 0, 0, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin: 5px 0;
        }
        
        .message-attachment a {
            color: #0066cc;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .message-attachment i {
            font-size: 18px;
        }
        
        .file-preview {
            display: none;
            margin-top: 5px;
            padding: 5px;
            background-color: #f1f1f1;
            border-radius: 8px;
            font-size: 12px;
            align-items: center;
            gap: 5px;
        }
        
        .file-preview.active {
            display: flex;
        }
        
        .file-preview-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        .file-preview-remove {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 14px;
            padding: 0 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="location.href='messages.php'"><i class="fas fa-arrow-left"></i></button>
            <div class="conversation-header">
                <div class="conversation-avatar">
                    <img src="<?php echo htmlspecialchars($partner_image); ?>" alt="<?php echo htmlspecialchars($partner_name); ?>">
                </div>
                <div>
                    <h4 class="conversation-partner-name"><?php echo htmlspecialchars($partner_name); ?></h4>
                    <?php if ($conversation_type == 'business' && !$is_business_owner): ?>
                        <div class="conversation-status">Business</div>
                    <?php elseif ($is_business_owner): ?>
                        <div class="conversation-status">Your Business - Customer Conversation</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <div class="message-container" id="messageContainer">
            <?php 
            $current_date = null;
            
            if ($blocked): ?>
                <div class="blocked-conversation">
                    <i class="fas fa-ban"></i>
                    <p>You have blocked this user. Unblock them from your messages page to continue the conversation.</p>
                </div>
            <?php elseif (empty($messages)): 
            ?>
                <div class="empty-conversation">
                    <i class="far fa-comment-dots"></i>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            <?php 
            else:
                foreach ($messages as $message): 
                    $message_date = date('Y-m-d', strtotime($message['created_at']));
                    
                    // Add date divider if it's a new date
                    if ($message_date != $current_date):
                        $current_date = $message_date;
                        $display_date = date('F j, Y', strtotime($message['created_at']));
                        
                        if ($message_date == date('Y-m-d')) {
                            $display_date = 'Today';
                        } elseif ($message_date == date('Y-m-d', strtotime('-1 day'))) {
                            $display_date = 'Yesterday';
                        }
            ?>
                        <div class="date-divider">
                            <span><?php echo $display_date; ?></span>
                        </div>
            <?php 
                    endif;
                    
                    $is_sent = ($message['sender_id'] == $_SESSION['user_id'] && $message['sender_type'] == $current_user_type);
            ?>
                    <div class="message-bubble <?php echo $is_sent ? 'message-sent' : 'message-received'; ?>">
                        <?php 
                        // Check if message contains an attachment
                        $content = htmlspecialchars($message['content']);
                        if (preg_match('/\[attachment\](.*?)\[\/attachment\]/', $content, $matches)) {
                            $attachment_path = $matches[1];
                            $file_extension = pathinfo($attachment_path, PATHINFO_EXTENSION);
                            $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif']);
                            
                            if ($is_image) {
                                echo '<div class="message-image">';
                                echo '<img src="../' . $attachment_path . '" alt="Attached image" onerror="this.style.display=\'none\'">';
                                echo '</div>';
                            } else {
                                // For non-image files, show a download link
                                $filename = basename($attachment_path);
                                echo '<div class="message-attachment">';
                                echo '<a href="../' . $attachment_path . '" target="_blank" download>';
                                echo '<i class="fas fa-file"></i> ' . $filename . '</a>';
                                echo '</div>';
                            }
                            
                            // Remove the attachment tag from the content
                            $text_content = preg_replace('/\[attachment\].*?\[\/attachment\]/', '', $content);
                            if (!empty(trim($text_content))) {
                                echo nl2br($text_content);
                            }
                        } 
                        // For backward compatibility, also check for image tags
                        else if (preg_match('/\[img\](.*?)\[\/img\]/', $content, $matches)) {
                            $image_url = $matches[1];
                            echo '<div class="message-image">';
                            echo '<img src="'.$image_url.'" alt="Attached image" onerror="this.style.display=\'none\'">';
                            echo '</div>';
                            
                            // Remove the image tag from the content
                            $text_content = preg_replace('/\[img\].*?\[\/img\]/', '', $content);
                            if (!empty(trim($text_content))) {
                                echo nl2br($text_content);
                            }
                        } else {
                            echo nl2br($content);
                        }
                        ?>
                        <div class="message-time">
                            <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                        </div>
                    </div>
            <?php 
                endforeach; 
            endif;
            ?>
        </div>
        
        <?php if (!$blocked): ?>
        <form method="POST" class="message-input-container" id="messageForm" enctype="multipart/form-data">
            <div class="file-upload-button">
                <i class="fas fa-paperclip"></i>
                <input type="file" name="attachment" id="fileInput" accept="image/jpeg,image/png,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            </div>
            <div class="file-preview" id="filePreview">
                <span id="filePreviewName" class="file-preview-name"></span>
                <button type="button" class="file-preview-remove" id="fileRemoveButton">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="text" name="message" class="message-input" placeholder="Type a message..." autocomplete="off" id="messageInput">
            <button type="submit" class="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
        <?php endif; ?>
    </main>

    <script>
        // Scroll to bottom of conversation
        document.addEventListener('DOMContentLoaded', function() {
            const messageContainer = document.getElementById('messageContainer');
            messageContainer.scrollTop = messageContainer.scrollHeight;
            
            // File upload handling
            const fileInput = document.getElementById('fileInput');
            const filePreview = document.getElementById('filePreview');
            const filePreviewName = document.getElementById('filePreviewName');
            const fileRemoveButton = document.getElementById('fileRemoveButton');
            const messageInput = document.getElementById('messageInput');
            const messageForm = document.getElementById('messageForm');
            
            // Handle file selection
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        
                        // Check file size (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size exceeds 5MB limit. Please select a smaller file.');
                            fileInput.value = '';
                            return;
                        }
                        
                        // Show file preview
                        filePreviewName.textContent = file.name;
                        filePreview.classList.add('active');
                    } else {
                        filePreview.classList.remove('active');
                    }
                });
            }
            
            // Handle file removal
            if (fileRemoveButton) {
                fileRemoveButton.addEventListener('click', function() {
                    fileInput.value = '';
                    filePreview.classList.remove('active');
                });
            }
            
            // Form validation
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    // Allow submission if either message or file is provided
                    if (messageInput.value.trim() === '' && (!fileInput.files || fileInput.files.length === 0)) {
                        e.preventDefault();
                        alert('Please enter a message or attach a file');
                    }
                });
            }
        });
    </script>
</body>
</html>
