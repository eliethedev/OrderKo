    <link rel="stylesheet" href="../src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bottom Navigation -->
    <?php
    // Get the current page filename
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>
    <nav class="bottom-nav">
        <a href="index.php" class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="businesses.php" class="nav-item <?php echo ($current_page == 'businesses.php') ? 'active' : ''; ?>">
            <i class="fas fa-store"></i>
            <span>Explore</span>
        </a>
        <a href="orders.php" class="nav-item <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
            <i class="fas fa-shopping-bag"></i>
            <span>Orders</span>
        </a>
        <a href="messages.php" class="nav-item <?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>">
            <i class="fas fa-message"></i>
            <span>Messages</span>
        </a>
        <a href="profile.php" class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="src/script.js"></script>