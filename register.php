<?php
session_start();
require_once 'config/database.php';

// Create uploads directory if it doesn't exist
$upload_dir = 'uploads/profile_picture';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Validate inputs
    if (empty($full_name) || empty($email) || empty($password)) {
        $errors[] = "All fields are required";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    // Validate phone number (optional)
    if (!empty($phone_number) && !preg_match('/^[0-9\+\-\s\(\)]{10,15}$/', $phone_number)) {
        $errors[] = "Please enter a valid phone number";
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email already registered";
    }
    
    // Handle profile picture upload
    $profile_image_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG and GIF images are allowed";
        } elseif ($_FILES['profile_picture']['size'] > $max_size) {
            $errors[] = "Image size should be less than 5MB";
        } else {
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_image_path = $target_file;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }

    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user as customer
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone_number, password, profile_picture, role, is_business) VALUES (?, ?, ?, ?, ?, 'customer', 0)");
        $stmt->execute([$full_name, $email, $phone_number, $hashed_password, $profile_image_path]);

        // Set session and redirect
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['role'] = 'customer';
        $_SESSION['is_business'] = 0;

        header('Location: index.php');
        exit;   
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - OrderKo</title>
    
    <link rel="stylesheet" href="user/src/styles/common/base.css">
    <link rel="stylesheet" href="user/src/styles/register/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .profile-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 10px;
            border: 2px solid #ddd;
            background-color: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-input-container {
            position: relative;
            margin-top: 5px;
        }
        
        .file-input-container input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 8px 15px;
            background-color: #0066cc;
            color: white;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .file-input-label i {
            margin-right: 5px;
        }
    </style>
</head>
<body class="register-page">
    <div class="register-container">
        <div class="register-content">
            <div class="register-header">
                <h1>OrderKo</h1>
                <p>Create your account</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="register-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="Enter your email">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number (Optional)</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone_number" name="phone_number" placeholder="Enter your phone number">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                            <i class="fas fa-eye password-toggle"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                            <i class="fas fa-eye password-toggle"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="profile_picture">Profile Picture (Optional)</label>
                    <div class="profile-upload">
                        <div class="profile-preview" id="profile-preview">
                            <img src="user/assets/images/default-avatar.jpg" alt="Profile Preview" id="preview-image">
                        </div>
                        <div class="file-input-container">
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="previewImage(this)">
                            <label for="profile_picture" class="file-input-label">
                                <i class="fas fa-camera"></i> Choose Photo
                            </label>
                        </div>
                    </div>
                </div>


                <div class="form-footer">
                    <button type="submit" class="primary-button full-width">Register</button>
                </div>
            </form>

            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Add password toggle functionality
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        const togglePassword = input.nextElementSibling;
        if (togglePassword && togglePassword.classList.contains('password-toggle')) {
            togglePassword.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.className = `fas ${type === 'password' ? 'fa-eye' : 'fa-eye-slash'} password-toggle`;
            });
        }
    });
});

// Function to preview the selected profile image
function previewImage(input) {
    const preview = document.getElementById('preview-image');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
