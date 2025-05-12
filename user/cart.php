<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch cart items from database
$stmt = $pdo->prepare("SELECT ci.*, p.name, p.price, p.image_url, b.name as business_name, b.category 
                       FROM cart_items ci 
                       JOIN products p ON ci.product_id = p.id  
                       JOIN businesses b ON p.business_id = b.id 
                       WHERE ci.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

// Get business location if cart has items
$business_latitude = 0;
$business_longitude = 0;
if (!empty($cart_items)) {
    // Get business_id from product_id
    $product_id = $cart_items[0]['product_id'];
    $stmt = $pdo->prepare("SELECT business_id FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product && isset($product['business_id'])) {
        $business_id = $product['business_id'];
        $stmt = $pdo->prepare("SELECT latitude, longitude FROM businesses WHERE id = ?");
        $stmt->execute([$business_id]);
        $business_location = $stmt->fetch();
        if ($business_location) {
            $business_latitude = $business_location['latitude'];
            $business_longitude = $business_location['longitude'];
        }
    }
}

// Get user location from session or use default
$user_latitude = isset($_POST['user_latitude']) ? floatval($_POST['user_latitude']) : (isset($_SESSION['user_latitude']) ? $_SESSION['user_latitude'] : 14.5995);
$user_longitude = isset($_POST['user_longitude']) ? floatval($_POST['user_longitude']) : (isset($_SESSION['user_longitude']) ? $_SESSION['user_longitude'] : 120.9842);
$user_address = isset($_POST['user_address']) ? $_POST['user_address'] : (isset($_SESSION['user_address']) ? $_SESSION['user_address'] : 'Manila, Philippines');

// Save user location to session
if (isset($_POST['user_latitude']) && isset($_POST['user_longitude'])) {
    $_SESSION['user_latitude'] = $user_latitude;
    $_SESSION['user_longitude'] = $user_longitude;
    $_SESSION['user_address'] = $user_address;
}

// Calculate distance between business and user (in kilometers)
$distance = 0;
if ($business_latitude && $business_longitude) {
    $distance = calculateDistance($user_latitude, $user_longitude, $business_latitude, $business_longitude);
}

// Calculate delivery fee based on distance
$delivery_fee = calculateDeliveryFee($distance);

// Calculate totals
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
$service_fee = 20;
$delivery_option = isset($_POST['delivery_option']) ? $_POST['delivery_option'] : 'pickup';
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';

// Calculate grand total based on delivery option
$grand_total = $total + $service_fee;
if ($delivery_option === 'delivery') {
    $grand_total += $delivery_fee;
}

// Function to calculate distance between two points using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Radius of the earth in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c; // Distance in km
    return round($distance, 1);
}

// Function to calculate delivery fee based on distance
function calculateDeliveryFee($distance) {
    // Base fee
    $base_fee = 50;
    
    // Additional fee per kilometer
    $per_km_fee = 10;
    
    // Calculate total fee (base fee + distance fee)
    $total_fee = $base_fee;
    
    // Add distance fee if distance is greater than 2 km
    if ($distance > 2) {
        $total_fee += ($distance - 2) * $per_km_fee;
    }
    
    return round($total_fee, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <button class="back-button" onclick="history.back()"><i class="fas fa-arrow-left"></i></button>
            <h1>Your Cart</h1>
            <?php include_once 'includes/cart_icon.php'; ?>
            <button class="icon-button"><i class="fas fa-ellipsis-v"></i></button>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <?php if (empty($cart_items)): ?>
        <!-- Empty Cart State -->
        <section class="empty-cart">
            <div class="empty-cart-illustration">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added any items to your cart yet.</p>
            <button class="primary-button" onclick="window.location.href='businesses.php'">
                <i class="fas fa-store"></i> Browse Businesses
            </button>
        </section>
        <?php else: ?>
        <!-- Business Info -->
        <section class="cart-business">
            <div class="cart-business-info">
                <h3><?php echo htmlspecialchars($cart_items[0]['business_name']); ?></h3>
                <p><?php echo htmlspecialchars($cart_items[0]['category']); ?></p>
            </div>
        </section>

        <!-- Cart Items -->
        <section class="cart-items">
            <?php foreach ($cart_items as $item): ?>
            <div class="cart-item" data-item-id="<?php echo $item['id']; ?>">
                <div class="cart-item-image" style="background-image: url('../<?php echo htmlspecialchars($item['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                <div class="cart-item-details">
                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                    <?php if (!empty($item['options'])): ?>
                    <p class="cart-item-options"><?php echo htmlspecialchars($item['options']); ?></p>
                    <?php endif; ?>
                    <div class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                    <div class="cart-item-subtotal">Subtotal: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    <div class="cart-item-quantity">
                        <button class="quantity-button small" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-minus"></i>
                        </button>
                        <span id="quantity-<?php echo $item['id']; ?>"><?php echo $item['quantity']; ?></span>
                        <button class="quantity-button small" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)" <?php echo $item['quantity'] >= 10 ? 'disabled' : ''; ?>>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <button class="remove-item" onclick="removeItem(<?php echo $item['id']; ?>)"><i class="fas fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Add Note -->
        <section class="cart-note">
            <div class="note-container">
                <i class="fas fa-pencil-alt"></i>
                <input type="text" placeholder="Add a note for the seller..." id="order_note">
            </div>
        </section>

        <!-- Delivery Options -->
        <section class="delivery-options">
            <h3>Delivery Options</h3>
            <div class="option-toggle">
                <label class="toggle-option <?php echo $delivery_option === 'pickup' ? 'active' : ''; ?>">
                    <input type="radio" name="delivery_option" value="pickup" <?php echo $delivery_option === 'pickup' ? 'checked' : ''; ?> onchange="updateDeliveryOption('pickup')">
                    <i class="fas fa-store"></i> Pickup
                </label>
                <label class="toggle-option <?php echo $delivery_option === 'delivery' ? 'active' : ''; ?>">
                    <input type="radio" name="delivery_option" value="delivery" <?php echo $delivery_option === 'delivery' ? 'checked' : ''; ?> onchange="updateDeliveryOption('delivery')">
                    <i class="fas fa-motorcycle"></i> Delivery
                </label>
            </div>
            
            <!-- Pickup Details (shown when pickup is selected) -->
            <div id="pickup-details" class="delivery-details" <?php echo $delivery_option === 'delivery' ? 'style="display: none;"' : ''; ?>>
                <div class="pickup-option">
                    <div class="pickup-option-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h4>Pickup Date</h4>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <p id="pickup_date"><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="pickup-option">
                    <div class="pickup-option-header">
                        <i class="fas fa-clock"></i>
                        <h4>Pickup Time</h4>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <p id="pickup_time"><?php echo date('g:i A'); ?></p>
                </div>
            </div>
            
            <!-- Delivery Details (shown when delivery is selected) -->
            <div id="delivery-details" class="delivery-details" <?php echo $delivery_option === 'pickup' ? 'style="display: none;"' : ''; ?>>
                <div class="delivery-address">
                    <div class="pickup-option-header" onclick="showAddressSelector()">
                        <i class="fas fa-map-marker-alt"></i>
                        <h4>Delivery Address</h4>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    <p id="delivery_address"><?php echo htmlspecialchars($user_address); ?></p>
                    <p class="delivery-distance" id="delivery_distance">Distance: <?php echo $distance; ?> km</p>
                    <button class="location-button" onclick="getUserLocationNow()"><i class="fas fa-location-arrow"></i> Use Current Location</button>
                </div>
                <div class="delivery-time">
                    <div class="pickup-option-header">
                        <i class="fas fa-clock"></i>
                        <h4>Estimated Delivery Time</h4>
                    </div>
                    <p id="delivery_time">
                        <?php 
                        $estimated_time = "30-45 minutes";
                        if ($distance > 5) {
                            $estimated_time = "45-60 minutes";
                        } else if ($distance > 10) {
                            $estimated_time = "60-90 minutes";
                        }
                        echo $estimated_time;
                        ?>
                    </p>
                </div>
                <div class="delivery-fee">
                    <div class="pickup-option-header">
                        <i class="fas fa-money-bill-wave"></i>
                        <h4>Delivery Fee</h4>
                    </div>
                    <p id="delivery_fee">₱<?php echo number_format($delivery_fee, 2); ?></p>
                    <p class="fee-explanation">Base fee: ₱50.00 + ₱10.00 per km after first 2 km</p>
                </div>
                
                <!-- Address Selector Modal -->
                <div id="address-modal" class="modal">
                    <div class="modal-content address-modal-content">
                        <div class="modal-header">
                            <h3>Select Delivery Address</h3>
                            <button class="modal-close" onclick="hideAddressSelector()"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="modal-body">
                            <div class="address-search">
                                <input type="text" id="address-search" placeholder="Search for an address...">
                                <button onclick="searchAddress()"><i class="fas fa-search"></i></button>
                            </div>
                            <div class="address-options">
                                <div class="address-option" onclick="selectAddress('current')">
                                    <i class="fas fa-location-arrow"></i>
                                    <div>
                                        <h4>Current Location</h4>
                                        <p>Use your device's GPS</p>
                                    </div>
                                </div>
                                <div class="address-option" onclick="selectAddress('home')">
                                    <i class="fas fa-home"></i>
                                    <div>
                                        <h4>Home</h4>
                                        <p>Add your home address</p>
                                    </div>
                                </div>
                                <div class="address-option" onclick="selectAddress('work')">
                                    <i class="fas fa-building"></i>
                                    <div>
                                        <h4>Work</h4>
                                        <p>Add your work address</p>
                                    </div>
                                </div>
                            </div>
                            <div class="address-map" id="address-map">
                                <!-- Map will be inserted here by JavaScript -->
                            </div>
                            <button class="primary-button full-width" onclick="confirmAddress()">Confirm Address</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Payment Method -->
        <section class="payment-method">
            <div class="section-header">
                <h3>Payment Method</h3>
                <span class="section-subtitle">Choose how you want to pay</span>
            </div>
            <div class="payment-options">
                <label class="payment-option <?php echo $payment_method === 'cash' ? 'active' : ''; ?>">
                    <input type="radio" name="payment_method" value="cash" <?php echo $payment_method === 'cash' ? 'checked' : ''; ?> onchange="updatePaymentMethod('cash')">
                    <div class="payment-icon cash">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Cash on Delivery/Pickup</span>
                        <span class="payment-description">Pay when you receive your order</span>
                    </div>
                    <div class="payment-check">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </label>
                <label class="payment-option <?php echo $payment_method === 'gcash' ? 'active' : ''; ?>">
                    <input type="radio" name="payment_method" value="gcash" <?php echo $payment_method === 'gcash' ? 'checked' : ''; ?> onchange="updatePaymentMethod('gcash')">
                    <div class="payment-icon gcash">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">GCash</span>
                        <span class="payment-description">Pay with your GCash wallet</span>
                    </div>
                    <div class="payment-check">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </label>
                <label class="payment-option <?php echo $payment_method === 'card' ? 'active' : ''; ?>">
                    <input type="radio" name="payment_method" value="card" <?php echo $payment_method === 'card' ? 'checked' : ''; ?> onchange="updatePaymentMethod('card')">
                    <div class="payment-icon card">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Credit/Debit Card</span>
                        <span class="payment-description">Pay with Visa, Mastercard, etc.</span>
                    </div>
                    <div class="payment-check">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </label>
            </div>
        </section>
        
        <!-- Order Summary -->
        <section class="order-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotal">₱<?php echo number_format($total, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Service Fee</span>
                <span id="service_fee">₱<?php echo number_format($service_fee, 2); ?></span>
            </div>
            <?php if ($delivery_option === 'delivery'): ?>
            <div class="summary-row" id="delivery-fee-row">
                <span>Delivery Fee</span>
                <span id="delivery_fee_summary">₱<?php echo number_format($delivery_fee, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span>Total</span>
                <span id="grand_total">₱<?php echo number_format($grand_total, 2); ?></span>
            </div>
        </section>
        
        <!-- Checkout Button -->
        <div class="checkout-button-container">
            <button class="primary-button full-width" onclick="location.href='checkout.php'">
                Proceed to Checkout
            </button>
        </div>
        <?php endif; ?>
        </main>

    <!-- Script for cart functionality -->
    <!-- Include location.js script -->
    <script src="src/location.js"></script>
    
    <script>
    // Global variables for cart calculations
    const serviceFee = 20; // This should match the PHP value
    let subtotal = <?php echo $total; ?>;
    let deliveryFee = <?php echo $delivery_fee; ?>;
    let deliveryOption = '<?php echo $delivery_option; ?>';
    let paymentMethod = '<?php echo $payment_method; ?>';
    
    // Business location from PHP
    const businessLocation = {
        latitude: <?php echo $business_latitude ?: 0; ?>,
        longitude: <?php echo $business_longitude ?: 0; ?>
    };
    
    // User location from PHP
    let currentUserLocation = {
        latitude: <?php echo $user_latitude; ?>,
        longitude: <?php echo $user_longitude; ?>,
        address: "<?php echo addslashes($user_address); ?>",
        isDefault: <?php echo isset($_SESSION['user_latitude']) ? 'false' : 'true'; ?>
    };
    
    // Function to update quantity
    function updateQuantity(itemId, change) {
        // Send AJAX request to update cart
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'update_cart.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // If item was removed due to quantity becoming 0
                    if (response.removed) {
                        // Reload the page to refresh the cart
                        location.reload();
                        return;
                    }
                    
                    // Update item quantity display
                    const quantityElement = document.querySelector(`[data-item-id="${itemId}"] .cart-item-quantity span`);
                    if (quantityElement) {
                        quantityElement.textContent = response.item_quantity;
                    }
                    
                    // Update subtotal
                    subtotal = parseFloat(response.cart_total);
                    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
                    
                    // Update grand total
                    updateGrandTotal();
                } else {
                    // Show error message
                    alert(response.message);
                }
            }
        };
        
        xhr.send('item_id=' + itemId + '&change=' + change);
    }
    
    // Function to remove item from cart
    function removeItem(itemId) {
        // Confirm before removing
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }
        
        // Send AJAX request to remove item from cart
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'remove_from_cart.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // Reload the page to refresh the cart
                    location.reload();
                } else {
                    // Show error message
                    alert(response.message);
                }
            }
        };
        
        xhr.send('item_id=' + itemId);
    }
    
    // Function to update delivery option
    function updateDeliveryOption(option) {
        deliveryOption = option;
        
        // Show/hide the appropriate details section
        if (option === 'pickup') {
            document.getElementById('pickup-details').style.display = 'block';
            document.getElementById('delivery-details').style.display = 'none';
            
            // Hide delivery fee in summary
            const deliveryFeeRow = document.getElementById('delivery-fee-row');
            if (deliveryFeeRow) {
                deliveryFeeRow.style.display = 'none';
            }
        } else {
            document.getElementById('pickup-details').style.display = 'none';
            document.getElementById('delivery-details').style.display = 'block';
            
            // Show delivery fee in summary
            const deliveryFeeRow = document.getElementById('delivery-fee-row');
            if (deliveryFeeRow) {
                deliveryFeeRow.style.display = 'flex';
            } else {
                // Create delivery fee row if it doesn't exist
                const orderSummary = document.querySelector('.order-summary');
                const totalRow = document.querySelector('.order-summary .total').parentNode;
                
                const newRow = document.createElement('div');
                newRow.id = 'delivery-fee-row';
                newRow.className = 'summary-row';
                newRow.innerHTML = `
                    <span>Delivery Fee</span>
                    <span id="delivery_fee_summary">₱${deliveryFee.toFixed(2)}</span>
                `;
                
                orderSummary.insertBefore(newRow, totalRow);
            }
        }
        
        // Update the active class on toggle buttons
        document.querySelectorAll('.toggle-option').forEach(el => {
            if (el.querySelector('input').value === option) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
        
        // Update grand total
        updateGrandTotal();
        
        // Save the delivery option in a form
        const formData = new FormData();
        formData.append('delivery_option', option);
        
        // Use fetch to send the data
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
    }
    
    // Function to update payment method
    function updatePaymentMethod(method) {
        paymentMethod = method;
        
        // Update the active class on payment options
        document.querySelectorAll('.payment-option').forEach(el => {
            if (el.querySelector('input').value === method) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
        
        // Save the payment method in a form
        const formData = new FormData();
        formData.append('payment_method', method);
        
        // Use fetch to send the data
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
    }
    
    // Function to update the grand total
    function updateGrandTotal() {
        let grandTotal = subtotal + serviceFee;
        
        // Add delivery fee if delivery option is selected
        if (deliveryOption === 'delivery') {
            grandTotal += deliveryFee;
        }
        
        // Update the grand total display
        document.getElementById('grand_total').textContent = '₱' + grandTotal.toFixed(2);
    }
    
    // Location handling functions
    function getUserLocationNow() {
        // Show loading indicator
        document.getElementById('delivery_address').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting your location...';
        
        // Get user location
        getUserLocation()
            .then(location => {
                // Update current user location
                currentUserLocation = location;
                
                // Update UI
                document.getElementById('delivery_address').textContent = location.address;
                
                // Update delivery details
                const result = updateDeliveryDetails(currentUserLocation, businessLocation);
                
                // Update global variables
                deliveryFee = result.deliveryFee;
                
                // Update grand total
                updateGrandTotal();
                
                // Save to session
                saveUserLocation(location);
            })
            .catch(error => {
                console.error('Error getting location:', error);
                document.getElementById('delivery_address').textContent = currentUserLocation.address;
                alert('Could not get your location. Please try again or enter it manually.');
            });
    }
    
    // Show address selector modal
    function showAddressSelector() {
        document.getElementById('address-modal').style.display = 'block';
        
        // Initialize map if needed
        // This would require integrating with a mapping API like Google Maps or Leaflet
        // For simplicity, we'll just show a placeholder for now
        document.getElementById('address-map').innerHTML = '<div class="map-placeholder">Map loading...</div>';
    }
    
    // Hide address selector modal
    function hideAddressSelector() {
        document.getElementById('address-modal').style.display = 'none';
    }
    
    // Search for an address
    function searchAddress() {
        const searchTerm = document.getElementById('address-search').value;
        if (!searchTerm) return;
        
        // Here you would typically call a geocoding API
        // For demonstration, we'll just show a placeholder result
        const addressOptions = document.querySelector('.address-options');
        addressOptions.innerHTML += `
            <div class="address-option search-result" onclick="selectCustomAddress('${searchTerm}')">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <h4>${searchTerm}</h4>
                    <p>Search result</p>
                </div>
            </div>
        `;
    }
    
    // Select a predefined address type
    function selectAddress(type) {
        // Highlight the selected option
        document.querySelectorAll('.address-option').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Find the clicked option and add selected class
        const selectedOption = Array.from(document.querySelectorAll('.address-option')).find(el => {
            return el.querySelector('h4').textContent.toLowerCase().includes(type);
        });
        
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }
        
        // If current location is selected, get it immediately
        if (type === 'current') {
            getUserLocation()
                .then(location => {
                    currentUserLocation = location;
                    document.getElementById('address-map').innerHTML = `
                        <div class="map-info">
                            <h4>Selected Location</h4>
                            <p>${location.address}</p>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error getting location:', error);
                    alert('Could not get your location. Please try again or enter it manually.');
                });
        }
    }
    
    // Select a custom address from search results
    function selectCustomAddress(address) {
        // Here you would typically use a geocoding API to get coordinates
        // For demonstration, we'll use a placeholder
        currentUserLocation = {
            latitude: currentUserLocation.latitude,
            longitude: currentUserLocation.longitude,
            address: address,
            isDefault: false
        };
        
        document.getElementById('address-map').innerHTML = `
            <div class="map-info">
                <h4>Selected Location</h4>
                <p>${address}</p>
            </div>
        `;
        
        // Highlight the selected option
        document.querySelectorAll('.address-option').forEach(el => {
            el.classList.remove('selected');
        });
        
        // Find the clicked option and add selected class
        const selectedOption = Array.from(document.querySelectorAll('.address-option.search-result')).find(el => {
            return el.querySelector('h4').textContent === address;
        });
        
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }
    }
    
    // Confirm selected address
    function confirmAddress() {
        // Update UI with selected address
        document.getElementById('delivery_address').textContent = currentUserLocation.address;
        
        // Update delivery details
        const result = updateDeliveryDetails(currentUserLocation, businessLocation);
        
        // Update global variables
        deliveryFee = result.deliveryFee;
        
        // Update grand total
        updateGrandTotal();
        
        // Save to session
        saveUserLocation(currentUserLocation);
        
        // Hide modal
        hideAddressSelector();
    }
    
    // Initialize the page when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Make sure back button works properly
        const backButton = document.querySelector('.back-button');
        if (backButton) {
            backButton.addEventListener('click', function(e) {
                e.preventDefault();
                window.history.back();
            });
        }
        
        // Initialize location if cart has items
        if (businessLocation.latitude && businessLocation.longitude) {
            // If user has default location, try to get their actual location
            if (currentUserLocation.isDefault) {
                getUserLocation()
                    .then(location => {
                        currentUserLocation = location;
                        document.getElementById('delivery_address').textContent = location.address;
                        const result = updateDeliveryDetails(currentUserLocation, businessLocation);
                        deliveryFee = result.deliveryFee;
                        updateGrandTotal();
                        saveUserLocation(location);
                    })
                    .catch(error => {
                        console.error('Error getting location:', error);
                        // Continue with default location
                    });
            }
        }
    });
    </script>

    <script src="script.js"></script>
</body>
</html>