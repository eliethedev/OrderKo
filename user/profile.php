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
    <link rel="stylesheet" href="src/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">
    <!-- Leaflet Geocoder CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
        <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Profile</h1>
            <?php include_once 'includes/cart_icon.php'; ?>
            <button class="icon-button" onclick="window.location.href='settings.php'"><i class="fas fa-cog"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Profile Header -->
        <section class="profile-header">
            <div class="profile-avatar">
                <img id="profile-image" src="<?php echo htmlspecialchars($user['profile_picture'] ? '../' . $user['profile_picture'] : '../assets/images/default-profile.svg'); ?>" alt="Profile Picture">
                <button class="edit-avatar" onclick="document.getElementById('profile-picture-input').click();"><i class="fas fa-camera"></i></button>
                <input type="file" id="profile-picture-input" accept="image/*" style="display: none;" onchange="uploadProfilePicture(this.files[0])">
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
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="anonymous"></script>
    <!-- Leaflet Geocoder JS -->
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <!-- OrderKo Location Scripts -->
    <script src="src/location.js"></script>
    <script src="src/map.js"></script>
    
    <!-- Location Modal -->
    <div id="location-modal" class="modal">
        <div class="modal-content location-modal-content">
            <div class="modal-header">
                <h3>Set Your Location</h3>
                <button class="modal-close" onclick="closeLocationModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="location-map-container">
                    <div id="location-map" style="height: 300px;"></div>
                </div>
                <div class="selected-location-info">
                    <h4>Selected Location</h4>
                    <p id="selected-address">No location selected</p>
                </div>
                <div class="location-actions">
                    <button class="secondary-button" onclick="closeLocationModal()">Cancel</button>
                    <button class="primary-button" onclick="saveSelectedLocation()">Save Location</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Global variables
    let selectedLocation = null;
    let locationMap = null;
    
    // Initialize location functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handler to address option card
        const addressCard = document.querySelector('.option-card[onclick="window.location.href=\'addresses.php\'"]');
        if (addressCard) {
            // Override the default click behavior
            addressCard.onclick = function(e) {
                e.preventDefault();
                openLocationModal();
                return false;
            };
        }
    });
    
    // Open location modal with map
    function openLocationModal() {
        // Show modal
        const modal = document.getElementById('location-modal');
        modal.style.display = 'flex';
        
        // Initialize map if not already done
        if (!locationMap) {
            // Get user's current location
            getUserLocation()
                .then(location => {
                    // Create location picker map
                    const mapOptions = createLocationPicker('location-map', onLocationSelect, [location.latitude, location.longitude]);
                    locationMap = mapOptions.map;
                    
                    // Set initial selected location
                    selectedLocation = location;
                    document.getElementById('selected-address').textContent = location.address;
                })
                .catch(error => {
                    console.error('Error getting location:', error);
                    
                    // Create map with default location
                    const mapOptions = createLocationPicker('location-map', onLocationSelect);
                    locationMap = mapOptions.map;
                });
        } else {
            // If map already exists, just update its size (needed after display: none)
            setTimeout(() => {
                locationMap.invalidateSize();
            }, 100);
        }
    }
    
    // Close location modal
    function closeLocationModal() {
        const modal = document.getElementById('location-modal');
        modal.style.display = 'none';
    }
    
    // Handle location selection
    function onLocationSelect(location) {
        selectedLocation = location;
        document.getElementById('selected-address').textContent = location.address;
    }
    
    // Save selected location
    function saveSelectedLocation() {
        if (!selectedLocation) {
            alert('Please select a location first');
            return;
        }
        
        // Save location to session
        saveUserLocation(selectedLocation)
            .then(() => {
                // Show success message using toast instead of alert
                showToast('Location saved successfully!');
                
                // Close modal
                closeLocationModal();
                
                // Redirect to addresses page with location parameters
                const params = new URLSearchParams();
                params.append('address', selectedLocation.address);
                params.append('latitude', selectedLocation.latitude);
                params.append('longitude', selectedLocation.longitude);
                
                // If we have detailed address components, add them too
                if (selectedLocation.details) {
                    if (selectedLocation.details.street) params.append('street', selectedLocation.details.street);
                    if (selectedLocation.details.city) params.append('city', selectedLocation.details.city);
                    if (selectedLocation.details.state) params.append('state', selectedLocation.details.state);
                    if (selectedLocation.details.country) params.append('country', selectedLocation.details.country);
                    if (selectedLocation.details.postal_code) params.append('postal_code', selectedLocation.details.postal_code);
                }
                
                // Redirect with the parameters
                window.location.href = 'addresses.php?' + params.toString() + '#add-address';
            })
            .catch(error => {
                console.error('Error saving location:', error);
                showToast('Failed to save location. Please try again.', 'error');
            });
    }
    
    // Profile Picture Upload Functions
    function uploadProfilePicture(file) {
        if (!file) {
            return;
        }
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Please select a valid image file (JPEG, PNG, or GIF).');
            return;
        }
        
        // Validate file size (max 5MB)
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            alert('File size exceeds the maximum limit of 5MB.');
            return;
        }
        
        // Show loading state
        const profileImage = document.getElementById('profile-image');
        const originalSrc = profileImage.src;
        profileImage.classList.add('uploading');
        
        // Create form data
        const formData = new FormData();
        formData.append('profile_picture', file);
        
        // Send AJAX request
        fetch('upload_profile_picture.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update profile image
                profileImage.src = '../' + data.profile_picture_url;
                showToast('Profile picture updated successfully!');
            } else {
                // Show error message
                showToast(data.message || 'Failed to update profile picture', 'error');
                profileImage.src = originalSrc;
            }
        })
        .catch(error => {
            console.error('Error uploading profile picture:', error);
            showToast('An error occurred. Please try again.', 'error');
            profileImage.src = originalSrc;
        })
        .finally(() => {
            profileImage.classList.remove('uploading');
        });
    }
    
    // Function to show toast notification
    function showToast(message, type = 'success') {
        // Create toast element if it doesn't exist
        let toast = document.getElementById('toast-notification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-notification';
            document.body.appendChild(toast);
        }
        
        // Set toast content and class
        toast.textContent = message;
        toast.className = `toast-notification ${type}`;
        
        // Show the toast
        toast.classList.add('show');
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
    </script>
    
    <style>
    /* Profile Picture Styles */
    .profile-avatar {
        position: relative;
        width: 100px;
        height: 100px;
        margin: 0 auto 15px;
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .profile-avatar img.uploading {
        opacity: 0.5;
        filter: blur(2px);
    }
    
    .edit-avatar {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: var(--color-primary);
        color: white;
        border: 2px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        transition: all 0.2s ease;
    }
    
    .edit-avatar:hover {
        background-color: #c0392b;
        transform: scale(1.1);
    }
    
    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background-color: #28a745;
        color: white;
        padding: 12px 24px;
        border-radius: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        font-weight: 500;
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
        text-align: center;
        max-width: 90%;
    }
    
    .toast-notification.error {
        background-color: #dc3545;
    }
    
    .toast-notification.show {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
    
    /* Location Modal Styles */
    .location-modal-content {
        max-width: 600px;
        max-height: 90vh;
    }
    
    .location-map-container {
        margin-bottom: 15px;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .selected-location-info {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 15px;
    }
    
    .selected-location-info h4 {
        margin-top: 0;
        margin-bottom: 8px;
        font-size: 16px;
        color: #333;
    }
    
    .selected-location-info p {
        margin: 0;
        color: #666;
        word-break: break-word;
    }
    
    .location-actions {
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }
    
    .location-actions button {
        flex: 1;
    }
    
    /* Leaflet Custom Styles */
    .leaflet-control-locate a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
    }
    
    .leaflet-control-locate a i {
        font-size: 14px;
        color: #333;
    }
    </style>
</body>
</html>