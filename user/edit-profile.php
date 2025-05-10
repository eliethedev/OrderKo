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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email is already taken by another user
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $errors[] = "Email is already in use";
        }
    }
    
    // Password change validation
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password";
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New password and confirm password do not match";
        }
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update basic profile information
            $stmt = $pdo->prepare("UPDATE users SET 
                full_name = ?,
                email = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?");
            $stmt->execute([$full_name, $email, $_SESSION['user_id']]);
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            }
            
            $pdo->commit();
            
            // Update session with user details
            $_SESSION['user_name'] = $full_name;
            $_SESSION['user_email'] = $email;
            
            header('Location: profile.php?success=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "An error occurred while updating your profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<style>
    .edit-profile-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .form-section {
        margin-top: 30px;
        margin-bottom: 30px;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }
    
    .form-section h3 {
        margin-bottom: 20px;
        color: #333;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .input-wrapper {
        display: flex;
        align-items: center;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 0 15px;
        background-color: #fff;
    }
    
    .input-wrapper i {
        color: #666;
        margin-right: 10px;
    }
    
    .input-wrapper input {
        flex: 1;
        padding: 12px 0;
        border: none;
        outline: none;
        font-size: 16px;
    }
    
    .button-group {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    
    .primary-button, .secondary-button {
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .primary-button {
        background-color: #FF6B35;
        color: white;
        border: none;
    }
    
    .secondary-button {
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }
    
    .primary-button i, .secondary-button i {
        margin-right: 8px;
    }
    
    .success-message, .error-message {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }
    
    .success-message {
        background-color: #e6f7e6;
        color: #2e7d32;
    }
    
    .error-message {
        background-color: #ffebee;
        color: #c62828;
    }
    
    .success-message i, .error-message i {
        font-size: 20px;
        margin-right: 10px;
    }
</style>
    <!-- Header -->
    <header>
        <div class="header-container">
            <h1>Edit Profile</h1>
            <button class="icon-button" onclick="window.location.href='profile.php'"><i class="fas fa-arrow-left"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="profile-edit-main">
        <div class="form-container edit-profile-container">
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <p>Profile updated successfully!</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Change Password (Optional)</h3>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="input-group">
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="current_password" name="current_password" 
                                       placeholder="Enter current password">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-group">
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="new_password" name="new_password" 
                                       pattern=".{8,}" title="Password must be at least 8 characters long"
                                       placeholder="Enter new password">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-group">
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="Confirm new password">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <div class="button-group">
                        <button type="submit" class="primary-button">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="secondary-button" onclick="window.location.href='profile.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>
    <script src="js/script.js"></script>
</body>
</html>
