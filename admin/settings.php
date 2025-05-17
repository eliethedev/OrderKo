<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update general settings
        if (isset($_POST['general'])) {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE name = ?");
            $stmt->execute([$_POST['business_name'], 'business_name']);
            $stmt->execute([$_POST['business_email'], 'business_email']);
            $stmt->execute([$_POST['business_phone'], 'business_phone']);
            $stmt->execute([$_POST['business_address'], 'business_address']);
            
            $_SESSION['message'] = 'General settings updated successfully';
        }
        
        // Update SMS settings
        if (isset($_POST['sms'])) {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE name = ?");
            $stmt->execute([$_POST['philsms_token'], 'philsms_token']);
            $stmt->execute([$_POST['sender_id'], 'sender_id']);
            
            $_SESSION['message'] = 'SMS settings updated successfully';
        }
        
        // Update order settings
        if (isset($_POST['order'])) {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE name = ?");
            $stmt->execute([$_POST['order_confirmation_message'], 'order_confirmation_message']);
            $stmt->execute([$_POST['order_ready_message'], 'order_ready_message']);
            $stmt->execute([$_POST['order_completed_message'], 'order_completed_message']);
            
            $_SESSION['message'] = 'Order settings updated successfully';
        }
        
        // Update business settings
        if (isset($_POST['business'])) {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE name = ?");
            $stmt->execute([$_POST['business_registration_fee'], 'business_registration_fee']);
            $stmt->execute([$_POST['commission_rate'], 'commission_rate']);
            $stmt->execute([$_POST['delivery_fee'], 'delivery_fee']);
            
            $_SESSION['message'] = 'Business settings updated successfully';
        }
        
        // Update user settings
        if (isset($_POST['user'])) {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE name = ?");
            $stmt->execute([$_POST['min_order_amount'], 'min_order_amount']);
            $stmt->execute([$_POST['max_order_items'], 'max_order_items']);
            $stmt->execute([$_POST['order_expiry_minutes'], 'order_expiry_minutes']);
            
            $_SESSION['message'] = 'User settings updated successfully';
        }
        
        $pdo->commit();
        header('Location: settings.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error updating settings: ' . $e->getMessage();
        header('Location: settings.php');
        exit;
    }
}

// Get current settings
$stmt = $pdo->query("SELECT name, value FROM settings");
$settings = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'value', 'name');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - OrderKo</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .settings-section {
            margin-bottom: 30px;
        }
        .settings-section h3 {
            margin-bottom: 20px;
            color: #333;
        }
        .settings-form {
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .save-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 20px;
        }
        .save-button:hover {
            background-color: #45a049;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>Settings</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message success">
                    <?php 
                        echo htmlspecialchars($_SESSION['message']);
                        unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="message error">
                    <?php 
                        echo htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- General Settings -->
            <div class="settings-container">
                <h3>General Settings</h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="general" value="1">
                    <div class="form-group">
                        <label for="business_name">Business Name</label>
                        <input type="text" id="business_name" name="business_name" 
                               value="<?php echo htmlspecialchars($settings['business_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="business_email">Business Email</label>
                        <input type="email" id="business_email" name="business_email" 
                               value="<?php echo htmlspecialchars($settings['business_email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="business_phone">Business Phone</label>
                        <input type="tel" id="business_phone" name="business_phone" 
                               value="<?php echo htmlspecialchars($settings['business_phone'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="business_address">Business Address</label>
                        <textarea id="business_address" name="business_address" required><?php echo htmlspecialchars($settings['business_address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="save-button">
                        <i class="fas fa-save"></i> Save General Settings
                    </button>
                </form>
            </div>

            <!-- SMS Settings -->
            <div class="settings-container">
                <h3>SMS Settings</h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="sms" value="1">
                    <div class="form-group">
                        <label for="philsms_token">PhilSMS API Token</label>
                        <input type="text" id="philsms_token" name="philsms_token" 
                               value="<?php echo htmlspecialchars($settings['philsms_token'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="sender_id">Sender ID</label>
                        <input type="text" id="sender_id" name="sender_id" 
                               value="<?php echo htmlspecialchars($settings['sender_id'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="save-button">
                        <i class="fas fa-save"></i> Save SMS Settings
                    </button>
                </form>
            </div>

       

            <!-- User Settings -->
            <div class="settings-container">
                <h3>User Settings</h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="user" value="1">
                    <div class="form-group">
                        <label for="min_order_amount">Minimum Order Amount (₱)</label>
                        <input type="number" id="min_order_amount" name="min_order_amount" 
                               value="<?php echo htmlspecialchars($settings['min_order_amount'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="max_order_items">Maximum Order Items</label>
                        <input type="number" id="max_order_items" name="max_order_items" 
                               value="<?php echo htmlspecialchars($settings['max_order_items'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="order_expiry_minutes">Order Expiry Minutes</label>
                        <input type="number" id="order_expiry_minutes" name="order_expiry_minutes" 
                               value="<?php echo htmlspecialchars($settings['order_expiry_minutes'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="save-button">
                        <i class="fas fa-save"></i> Save User Settings
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>