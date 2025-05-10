<?php
session_start();
require_once 'config/database.php';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
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

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Email already registered";
    }

    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user as customer
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, is_business) VALUES (?, ?, ?, 'customer', 0)");
        $stmt->execute([$full_name, $email, $hashed_password]);

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

            <form method="POST" class="register-form">
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
</script>
</body>
</html>
