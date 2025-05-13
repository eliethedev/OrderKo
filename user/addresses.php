<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Function to get addresses
function getAddresses($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Handle form submission for adding new address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        // Add new address
        $stmt = $pdo->prepare("INSERT INTO addresses (user_id, address, city, state, postal_code, latitude, longitude, is_default) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        // If setting as default, update all other addresses to not be default
        if ($isDefault) {
            $updateStmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
            $updateStmt->execute([$_SESSION['user_id']]);
        }
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['address_line1'], // Using address_line1 as the main address field
            $_POST['city'],
            $_POST['state'],
            $_POST['postal_code'],
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            $isDefault
        ]);
        
        header('Location: addresses.php');
        exit;
    } elseif ($_POST['action'] === 'delete' && isset($_POST['address_id'])) {
        // Delete address
        $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['address_id'], $_SESSION['user_id']]);
        
        header('Location: addresses.php');
        exit;
    } elseif ($_POST['action'] === 'set_default' && isset($_POST['address_id'])) {
        // Set as default address
        $updateStmt = $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $updateStmt->execute([$_SESSION['user_id']]);
        
        $stmt = $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['address_id'], $_SESSION['user_id']]);
        
        header('Location: addresses.php');
        exit;
    }
}

// Fetch addresses
$addresses = getAddresses($pdo, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Addresses - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .address-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }
        
        .address-card.default {
            border: 2px solid var(--color-primary);
        }
        
        .default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--color-primary);
            color: white;
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
        
        .address-content {
            margin-bottom: 15px;
        }
        
        .address-actions {
            display: flex;
            gap: 10px;
        }
        
        .address-form {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
        }
        
        .form-row {
            margin-bottom: 15px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-row input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
        }
        
        .form-row.two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .checkbox-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-row input {
            margin-right: 10px;
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
        
        @media (max-width: 480px) {
            .form-row.two-columns {
                grid-template-columns: 1fr;
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
            <h1>My Addresses</h1>
            <div style="width: 36px;"></div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Add New Address Button -->
        <button class="primary-button full-width" onclick="toggleAddressForm()" style="margin-bottom: 20px;">
            <i class="fas fa-plus"></i> Add New Address
        </button>
        
        <!-- New Address Form (Hidden by default) -->
        <div id="address-form" class="address-form" style="display: none;">
            <h3 style="margin-bottom: 15px;">Add New Address</h3>
            <form method="POST" action="addresses.php">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <label for="address_line1">Address</label>
                    <input type="text" id="address_line1" name="address_line1" required>
                </div>
                
                <!-- Hidden fields for coordinates -->
                <input type="hidden" id="latitude" name="latitude">
                <input type="hidden" id="longitude" name="longitude">
                
                <div class="form-row">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" required>
                </div>
                
                <div class="form-row two-columns">
                    <div>
                        <label for="state">State/Province</label>
                        <input type="text" id="state" name="state" required>
                    </div>
                    <div>
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" required>
                    </div>
                </div>
                
                <div class="checkbox-row">
                    <input type="checkbox" id="is_default" name="is_default">
                    <label for="is_default">Set as default address</label>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="primary-button" style="flex: 1;">Save Address</button>
                    <button type="button" class="secondary-button" onclick="toggleAddressForm()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Address List -->
        <section class="addresses-list">
            <?php if (empty($addresses)): ?>
                <div class="empty-state">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>No Addresses Found</h3>
                    <p>You haven't added any delivery addresses yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($addresses as $address): ?>
                    <div class="address-card <?php echo $address['is_default'] ? 'default' : ''; ?>">
                        <?php if ($address['is_default']): ?>
                            <div class="default-badge">Default</div>
                        <?php endif; ?>
                        
                        <div class="address-content">
                            <p><?php echo htmlspecialchars($address['address']); ?></p>
                            <p>
                                <?php echo htmlspecialchars($address['city']); ?>, 
                                <?php echo htmlspecialchars($address['state']); ?> 
                                <?php echo htmlspecialchars($address['postal_code']); ?>
                            </p>
                        </div>
                        
                        <div class="address-actions">
                            <?php if (!$address['is_default']): ?>
                                <form method="POST" action="addresses.php" style="display: inline;">
                                    <input type="hidden" name="action" value="set_default">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <button type="submit" class="secondary-button small">
                                        <i class="fas fa-check-circle"></i> Set as Default
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" action="addresses.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                <button type="submit" class="secondary-button small">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>
    <script>
        // Function to toggle address form visibility
        function toggleAddressForm() {
            const form = document.getElementById('address-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            
            // Scroll to the form if it's being shown
            if (form.style.display === 'block') {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Function to get URL parameters
        function getUrlParams() {
            const params = {};
            const queryString = window.location.search.substring(1);
            const pairs = queryString.split('&');
            
            for (const pair of pairs) {
                if (pair === '') continue;
                const parts = pair.split('=');
                params[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
            }
            
            return params;
        }
        
        // Function to fill the address form with URL parameters
        function fillAddressFormFromUrl() {
            const params = getUrlParams();
            const hashFragment = window.location.hash;
            
            // Check if we have address parameters and the add-address hash
            if (params.address && hashFragment === '#add-address') {
                // Show the form
                toggleAddressForm();
                
                // Fill in the form fields
                if (params.address) {
                    document.getElementById('address_line1').value = params.address;
                }
                
                if (params.city) {
                    document.getElementById('city').value = params.city;
                }
                
                if (params.state) {
                    document.getElementById('state').value = params.state;
                }
                
                if (params.postal_code) {
                    document.getElementById('postal_code').value = params.postal_code;
                }
                
                // Store latitude and longitude in hidden fields if available
                if (params.latitude && params.longitude) {
                    // Create hidden fields if they don't exist
                    let latField = document.getElementById('latitude');
                    if (!latField) {
                        latField = document.createElement('input');
                        latField.type = 'hidden';
                        latField.id = 'latitude';
                        latField.name = 'latitude';
                        document.querySelector('form').appendChild(latField);
                    }
                    latField.value = params.latitude;
                    
                    let lngField = document.getElementById('longitude');
                    if (!lngField) {
                        lngField = document.createElement('input');
                        lngField.type = 'hidden';
                        lngField.id = 'longitude';
                        lngField.name = 'longitude';
                        document.querySelector('form').appendChild(lngField);
                    }
                    lngField.value = params.longitude;
                }
                
                // Check the default checkbox
                document.getElementById('is_default').checked = true;
            }
        }
        
        // Run when the page loads
        document.addEventListener('DOMContentLoaded', fillAddressFormFromUrl);
    </script>
</body>
</html>
