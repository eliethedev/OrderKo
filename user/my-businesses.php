<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Function to get user's businesses
function getUserBusinesses($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Function to get products for a business
function getBusinessProducts($pdo, $business_id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE business_id = ? ORDER BY name ASC");
    $stmt->execute([$business_id]);
    return $stmt->fetchAll();
}

// Function to get orders for a business
function getBusinessOrders($pdo, $business_id, $status = null) {
    $sql = "SELECT o.*, u.full_name as customer_name, u.phone_number as customer_phone 
           FROM orders o 
           LEFT JOIN users u ON o.customer_id = u.id 
           WHERE o.business_id = ?";
    
    $params = [$business_id];
    
    if ($status) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Function to get order items for an order
function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT oi.*, p.name, p.price 
                           FROM order_items oi 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

// Function to get categories
function getCategories($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Handle business actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Add new business
    if ($_POST['action'] === 'add_business') {
        // Process main business image upload
        $image_url = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/businesses/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . $_FILES['image']['name'];
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/businesses/' . $file_name;
            }
        }
        
        // Process business permit upload
        $permit_url = '';
        if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/permits/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . $_FILES['business_permit']['name'];
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['business_permit']['tmp_name'], $upload_path)) {
                $permit_url = 'uploads/permits/' . $file_name;
            }
        }
        
        // Process operating hours
        $operating_hours = [];
        if (isset($_POST['open_time']) && isset($_POST['close_time']) && isset($_POST['closed'])) {
            foreach ($_POST['open_time'] as $day => $open_time) {
                $is_closed = isset($_POST['closed'][$day]) ? 1 : 0;
                $close_time = $_POST['close_time'][$day];
                
                $operating_hours[$day] = [
                    'open' => $is_closed ? null : $open_time,
                    'close' => $is_closed ? null : $close_time,
                    'closed' => $is_closed
                ];
            }
        }
        
        // Convert operating hours to JSON for storage
        $operating_hours_json = json_encode($operating_hours);
        
        // Insert business into database
        $stmt = $pdo->prepare("INSERT INTO businesses (name, description, category, address, latitude, longitude, 
                               user_id, image_url, business_permit_url, operating_hours, phone_number, email, 
                               verification_status, terms_agreed, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending')");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['category'],
            $_POST['address'],
            !empty($_POST['latitude']) ? $_POST['latitude'] : null,
            !empty($_POST['longitude']) ? $_POST['longitude'] : null,
            $_SESSION['user_id'],
            $image_url,
            $permit_url,
            $operating_hours_json,
            $_POST['phone_number'],
            $_POST['business_email'],
            isset($_POST['terms_agreed']) ? 1 : 0
        ]);
        
        // Get the newly inserted business ID
        $business_id = $pdo->lastInsertId();
        
        // Process additional business photos
        if (isset($_FILES['business_photos']) && !empty($_FILES['business_photos']['name'][0])) {
            $upload_dir = '../uploads/business_photos/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Loop through each uploaded photo (max 5)
            $max_photos = 5;
            $photo_count = min(count($_FILES['business_photos']['name']), $max_photos);
            
            for ($i = 0; $i < $photo_count; $i++) {
                if ($_FILES['business_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . $i . '_' . $_FILES['business_photos']['name'][$i];
                    $upload_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['business_photos']['tmp_name'][$i], $upload_path)) {
                        $photo_url = 'uploads/business_photos/' . $file_name;
                        
                        // Insert photo into business_photos table
                        $photoStmt = $pdo->prepare("INSERT INTO business_photos (business_id, photo_url, is_featured) VALUES (?, ?, ?)");
                        $photoStmt->execute([$business_id, $photo_url, $i === 0 ? 1 : 0]); // First photo is featured
                    }
                }
            }
        }
        
        // Add business_owner role to user if they don't have it already
        $roleStmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role) VALUES (?, 'business_owner')");
        $roleStmt->execute([$_SESSION['user_id']]);
        
        header('Location: my-businesses.php');
        exit;
    }
    
    // Delete business
    elseif ($_POST['action'] === 'delete_business' && isset($_POST['business_id'])) {
        // First delete all products associated with the business
        $stmt = $pdo->prepare("DELETE FROM products WHERE business_id = ?");
        $stmt->execute([$_POST['business_id']]);
        
        // Then delete the business
        $stmt = $pdo->prepare("DELETE FROM businesses WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['business_id'], $_SESSION['user_id']]);
        
        header('Location: my-businesses.php');
        exit;
    }
    
    // Add new product
    elseif ($_POST['action'] === 'add_product' && isset($_POST['business_id'])) {
        // Process image upload if provided
        $image_url = '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/products/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . $_FILES['product_image']['name'];
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/products/' . $file_name;
            }
        }
        
        // Insert product into database
        $stmt = $pdo->prepare("INSERT INTO products (business_id, name, description, price, image_url, is_available) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['business_id'],
            $_POST['product_name'],
            $_POST['product_description'],
            $_POST['product_price'],
            $image_url,
            isset($_POST['is_available']) ? 1 : 0
        ]);
        
        header('Location: my-businesses.php?view_business=' . $_POST['business_id']);
        exit;
    }
    
    // Delete product
    elseif ($_POST['action'] === 'delete_product' && isset($_POST['product_id']) && isset($_POST['business_id'])) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND business_id = ?");
        $stmt->execute([$_POST['product_id'], $_POST['business_id']]);
        
        header('Location: my-businesses.php?view_business=' . $_POST['business_id']);
        exit;
    }
    
    // Toggle product availability
    elseif ($_POST['action'] === 'toggle_availability' && isset($_POST['product_id']) && isset($_POST['business_id'])) {
        $stmt = $pdo->prepare("UPDATE products SET is_available = NOT is_available WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        
        header('Location: my-businesses.php?view_business=' . $_POST['business_id']);
        exit;
    }
    
    // Update order status
    elseif ($_POST['action'] === 'update_order_status' && isset($_POST['order_id']) && isset($_POST['status']) && isset($_POST['business_id'])) {
        // Verify that the order belongs to this business
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND business_id = ?");
        $stmt->execute([$_POST['order_id'], $_POST['business_id']]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Update order status
            $new_status = trim($_POST['status']);
            
            // Log the status value for debugging
            error_log("Updating order #{$_POST['order_id']} status to: '{$new_status}'");
            
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$new_status, $_POST['order_id']]);
            
            if (!$result) {
                // Log error if update fails
                error_log("Failed to update order status: " . print_r($stmt->errorInfo(), true));
            }
            
            // Verify the update was successful
            $verify = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
            $verify->execute([$_POST['order_id']]);
            $updated_order = $verify->fetch();
            error_log("After update, order #{$_POST['order_id']} status is: '{$updated_order['status']}'");
            
            // Add notification for the customer (if you have a notifications table)
            try {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                                   VALUES (?, ?, ?, 'order', ?, NOW())");
                $title = "Order Status Update";
                $message = "Your order #" . $_POST['order_id'] . " has been updated to " . ucfirst($_POST['status']);
                $stmt->execute([$order['customer_id'], $title, $message, $_POST['order_id']]);
            } catch (Exception $e) {
                // Notifications table might not exist, just continue
            }
        }
        
        // Clear any output buffer to ensure headers work correctly
        if (ob_get_length()) ob_clean();
        
        // Redirect with a cache-busting parameter to force a fresh page load
        header('Location: my-businesses.php?view_business=' . $_POST['business_id'] . '&tab=orders&refresh=' . time());
        exit;
    }
    
    // Send order notification
    elseif ($_POST['action'] === 'send_notification' && isset($_POST['order_id']) && isset($_POST['message']) && isset($_POST['business_id'])) {
        // Verify that the order belongs to this business
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND business_id = ?");
        $stmt->execute([$_POST['order_id'], $_POST['business_id']]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Add notification for the customer
            try {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                                   VALUES (?, ?, ?, 'order', ?, NOW())");
                $title = "Message from Business";
                $stmt->execute([$order['customer_id'], $title, $_POST['message'], $_POST['order_id']]);
            } catch (Exception $e) {
                // Notifications table might not exist, just continue
            }
        }
        
        header('Location: my-businesses.php?view_business=' . $_POST['business_id'] . '&tab=orders');
        exit;
    }
}

// Fetch user's businesses
$businesses = getUserBusinesses($pdo, $_SESSION['user_id']);

// Fetch categories for the add business form
$categories = getCategories($pdo);

// Check if viewing a specific business
$view_business = null;
$business_products = [];
$business_orders = [];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'products';

if (isset($_GET['view_business'])) {
    $business_id = $_GET['view_business'];
    
    // Find the business in the user's businesses
    foreach ($businesses as $business) {
        if ($business['id'] == $business_id) {
            $view_business = $business;
            break;
        }
    }
    
    // Fetch data for this business
    if ($view_business) {
        // Fetch products
        $business_products = getBusinessProducts($pdo, $business_id);
        
        // Fetch orders
        $business_orders = getBusinessOrders($pdo, $business_id);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Businesses - OrderKo</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        .business-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
        }
        
        #map-container, .map-container {
            height: 300px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            border: 1px solid var(--color-border);
        }
        
        .form-hint {
            font-size: 0.8rem;
            color: var(--color-text-light);
            margin-top: 5px;
            margin-bottom: 0;
        }
        
        .operating-hours-container {
            background-color: var(--color-background);
            border-radius: var(--border-radius);
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .operating-day {
            display: flex;
            flex-direction: row;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--color-border);
        }
        
        .operating-day:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .day-label {
            width: 100px;
            font-weight: 500;
            padding-top: 8px;
        }
        
        .hours-inputs {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .time-inputs {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .time-inputs input[type="time"] {
            width: 120px;
            padding: 8px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
        }
        
        .time-inputs.disabled {
            opacity: 0.5;
        }
        
        .closed-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .terms-checkbox label {
            font-size: 0.9rem;
        }
        
        .terms-checkbox a {
            color: var(--color-primary);
            text-decoration: underline;
        }
        
        .business-image {
            height: 150px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .business-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.6);
            color: white;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .business-content {
            padding: 15px;
        }
        
        .business-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .business-name h3 {
            margin-bottom: 5px;
            font-size: 1.1rem;
        }
        
        .business-category {
            color: var(--color-text-light);
            font-size: 0.85rem;
        }
        
        .business-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .business-actions button {
            flex: 1;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .section-header h3 {
            font-size: 1.1rem;
        }
        
        .view-all {
            color: var(--color-primary);
            font-size: 0.9rem;
            font-weight: 500;
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
        
        .form-container {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
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
        
        .form-row input, .form-row textarea, .form-row select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
        }
        
        .form-row textarea {
            min-height: 80px;
            resize: vertical;
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
        
        .product-card {
            display: flex;
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: var(--shadow-sm);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            background-size: cover;
            background-position: center;
        }
        
        .product-details {
            flex: 1;
            padding: 15px;
            position: relative;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .product-description {
            color: var(--color-text-light);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .product-price {
            font-weight: 600;
            color: var(--color-primary);
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .product-status {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
        
        .product-status.available {
            background-color: var(--color-success);
            color: white;
        }
        
        .product-status.unavailable {
            background-color: var(--color-text-light);
            color: white;
        }
        
        /* Orders Dashboard Styles */
        .order-filter {
            display: flex;
            align-items: center;
        }
        
        .order-filter select {
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid var(--color-border);
            background-color: white;
        }
        
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .order-card {
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 15px;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-bottom: 1px solid var(--color-border);
            background-color: #f9f9f9;
        }
        
        .order-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .order-id {
            display: flex;
            flex-direction: column;
        }
        
        .order-date {
            font-size: 0.8rem;
            color: var(--color-text-light);
        }
        
        .order-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            margin-top: 5px;
        }
        
        .order-status-badge.pending {
            background-color: #f0f0f0;
            color: #666;
        }
        
        .order-status-badge.confirmed {
            background-color: var(--color-primary);
            color: white;
        }
        
        .order-status-badge.preparing {
            background-color: #3498db;
            color: white;
        }
        
        .order-status-badge.ready {
            background-color: #2ecc71;
            color: white;
        }
        
        .order-status-badge.completed {
            background-color: #27ae60;
            color: white;
        }
        
        .order-status-badge.cancelled {
            background-color: #e74c3c;
            color: white;
        }
        
        .customer-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }
        
        .customer-name, .customer-phone {
            font-size: 0.9rem;
        }
        
        .customer-name i, .customer-phone i {
            margin-right: 5px;
            color: var(--color-text-light);
        }
        
        .order-details {
            padding: 15px;
        }
        
        .order-details h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .order-items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 8px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        
        .item-quantity {
            font-weight: 500;
            margin-right: 10px;
            min-width: 30px;
        }
        
        .item-name {
            flex: 1;
        }
        
        .item-price {
            font-weight: 500;
            color: var(--color-primary);
        }
        
        .order-total {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .total-amount {
            color: var(--color-primary);
            font-size: 1.1rem;
        }
        
        .order-notes {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #fff9e6;
            border-radius: 5px;
            border-left: 3px solid var(--color-primary);
        }
        
        .order-pickup {
            margin-bottom: 15px;
        }
        
        .order-pickup p {
            margin: 5px 0;
        }
        
        .order-pickup i {
            margin-right: 5px;
            color: var(--color-text-light);
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            padding: 15px;
            border-top: 1px solid var(--color-border);
            background-color: #f9f9f9;
        }
        
        .order-actions form {
            flex: 1;
        }
        
        .order-actions button {
            width: 100%;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: var(--color-card);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .tab.active {
            background-color: var(--color-primary);
            color: white;
            font-weight: 500;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: var(--color-card);
            margin: 15% auto;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .modal-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--color-border);
        }
        
        .modal-footer {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid var(--color-border);
            text-align: right;
        }
        
        @media (max-width: 768px) {
            .operating-day {
                flex-direction: column;
            }
            
            .day-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .hours-inputs {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .time-inputs {
                width: 100%;
                justify-content: space-between;
            }
            
            .time-inputs input[type="time"] {
                width: 45%;
            }
            
            .closed-checkbox {
                align-self: flex-end;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 90%;
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .form-row.two-columns {
                grid-template-columns: 1fr;
            }
            
            .business-actions, .product-actions {
                flex-direction: column;
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
            <h1>My Businesses</h1>
            <div style="width: 36px;"></div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <?php if ($view_business): ?>
            <!-- Business Detail View -->
            <div class="business-card">
                <div class="business-image" style="background-image: url('../<?php echo htmlspecialchars($view_business['image_url'] ?: 'assets/images/default-business.jpg'); ?>')">
                </div>
                <div class="business-content">
                    <div class="business-header">
                        <div class="business-name">
                            <h3><?php echo htmlspecialchars($view_business['name']); ?></h3>
                            <div class="business-category"><?php echo htmlspecialchars($view_business['category']); ?></div>
                        </div>
                    </div>
                    <p><?php echo htmlspecialchars($view_business['description']); ?></p>
                    
                    <?php if (!empty($view_business['latitude']) && !empty($view_business['longitude'])): ?>
                    <div class="form-row">
                        <label>Business Location</label>
                        <div id="view-map-container" class="map-container" data-lat="<?php echo htmlspecialchars($view_business['latitude']); ?>" data-lng="<?php echo htmlspecialchars($view_business['longitude']); ?>"></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="business-actions">
                        <button class="secondary-button" onclick="window.location.href='my-businesses.php'">
                            <i class="fas fa-arrow-left"></i> Back to Businesses
                        </button>
                        <form method="POST" action="my-businesses.php" onsubmit="return confirm('Are you sure you want to delete this business? This will also delete all products.');">
                            <input type="hidden" name="action" value="delete_business">
                            <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                            <button type="submit" class="secondary-button danger">
                                <i class="fas fa-trash"></i> Delete Business
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Tabs for Products and Orders -->
            <div class="tabs">
                <div class="tab <?php echo $active_tab == 'products' ? 'active' : ''; ?>" onclick="showTab('products')">Products</div>
                <div class="tab <?php echo $active_tab == 'orders' ? 'active' : ''; ?>" onclick="showTab('orders')">Orders</div>
            </div>
            
            <!-- Products Tab -->
            <div id="products-tab" style="<?php echo $active_tab == 'products' ? 'display: block;' : 'display: none;'; ?>">
                <div class="section-header">
                    <h3>Products</h3>
                    <button class="secondary-button small" onclick="toggleProductForm()">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                </div>
                
                <!-- Add Product Form -->
                <div id="product-form" class="form-container" style="display: none;">
                    <h3>Add New Product</h3>
                    <form method="POST" action="my-businesses.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_product">
                        <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                        
                        <div class="form-row">
                            <label for="product_name">Product Name</label>
                            <input type="text" id="product_name" name="product_name" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="product_description">Description</label>
                            <textarea id="product_description" name="product_description" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <label for="product_price">Price (₱)</label>
                            <input type="number" id="product_price" name="product_price" step="0.01" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="product_image">Product Image</label>
                            <input type="file" id="product_image" name="product_image" accept="image/*">
                        </div>
                        
                        <div class="checkbox-row">
                            <input type="checkbox" id="is_available" name="is_available" checked>
                            <label for="is_available">Product is available</label>
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" class="primary-button full-width">Save Product</button>
                        </div>
                        
                        <div class="form-row">
                            <button type="button" class="secondary-button full-width" onclick="toggleProductForm()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Products List -->
                <?php if (empty($business_products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Products Yet</h3>
                        <p>Add products to your business to start selling.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($business_products as $product): ?>
                        <div class="product-card">
                            <div class="product-image" style="background-image: url('../<?php echo htmlspecialchars($product['image_url'] ?: 'assets/images/default-product.jpg'); ?>')"></div>
                            <div class="product-details">
                                <div class="product-status <?php echo $product['is_available'] ? 'available' : 'unavailable'; ?>">
                                    <?php echo $product['is_available'] ? 'Available' : 'Unavailable'; ?>
                                </div>
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-description"><?php echo htmlspecialchars($product['description']); ?></div>
                                <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                
                                <div class="product-actions">
                                    <form method="POST" action="my-businesses.php">
                                        <input type="hidden" name="action" value="toggle_availability">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                        <button type="submit" class="secondary-button small">
                                            <i class="fas <?php echo $product['is_available'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                            <?php echo $product['is_available'] ? 'Mark Unavailable' : 'Mark Available'; ?>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="my-businesses.php" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                        <button type="submit" class="secondary-button small">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Orders Tab -->
            <div id="orders-tab" style="<?php echo $active_tab == 'orders' ? 'display: block;' : 'display: none;'; ?>">
                <div class="section-header">
                    <h3>Orders Dashboard</h3>
                    <div class="order-filter">
                        <select id="order-status-filter" onchange="filterOrders(this.value)">
                            <option value="all">All Orders</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="ready">Ready</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <?php if (empty($business_orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Yet</h3>
                    <p>Orders from customers will appear here.</p>
                </div>
                <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($business_orders as $order): ?>
                    <?php 
                    // Ensure status is a valid string
                    $status = isset($order['status']) ? trim($order['status']) : '';
                    ?>
                    <div class="order-card" data-status="<?php echo htmlspecialchars($status); ?>">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-id">
                                    <strong>Order #<?php echo $order['id']; ?></strong>
                                    <span class="order-date"><?php echo date('M j, Y, g:i A', strtotime($order['created_at'])); ?></span>
                                </div>
                                <div class="order-status-badge <?php echo htmlspecialchars($status); ?>">
                                    <?php echo ucfirst(htmlspecialchars($status ?: 'unknown')); ?>
                                </div>
                            </div>
                            <div class="customer-info">
                                <div class="customer-name">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($order['customer_name'] ?? 'Customer'); ?>
                                </div>
                                <?php if (!empty($order['customer_phone'])): ?>
                                <div class="customer-phone">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="order-details">
                            <h4>Order Items</h4>
                            <div class="order-items-list">
                                <?php 
                                $items = getOrderItems($pdo, $order['id']);
                                $total = 0;
                                foreach ($items as $item): 
                                    $total += $item['price'] * $item['quantity'];
                                ?>
                                <div class="order-item">
                                    <div class="item-quantity"><?php echo $item['quantity']; ?> ×</div>
                                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-total">
                                <span>Total:</span>
                                <span class="total-amount">₱<?php echo number_format($total, 2); ?></span>
                            </div>
                            
                            <?php if (!empty($order['special_instructions'])): ?>
                            <div class="order-notes">
                                <h4>Special Instructions</h4>
                                <p><?php echo htmlspecialchars($order['special_instructions']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="order-pickup">
                                <h4>Pickup Details</h4>
                                <p><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y, g:i A', strtotime($order['pickup_date'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="order-actions">
                            <?php 
                            // Debug information for troubleshooting
                            error_log("Order #{$order['id']} status: '{$status}'");
                            
                            // Show appropriate action buttons based on status
                            if ($status == 'pending'): 
                            ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="primary-button"><i class="fas fa-check"></i> Confirm Order</button>
                            </form>
                            <?php elseif ($status == 'confirmed'): ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="preparing">
                                <button type="submit" class="primary-button"><i class="fas fa-utensils"></i> Start Preparing</button>
                            </form>
                            <?php elseif ($status == 'preparing'): ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="ready">
                                <button type="submit" class="primary-button"><i class="fas fa-box"></i> Mark as Ready</button>
                            </form>
                            <?php elseif ($status == 'ready'): ?>
                            <?php 
                            // Check if this is a delivery order or pickup order
                            $deliveryCheck = $pdo->prepare("SELECT delivery_option FROM orders WHERE id = ?");
                            $deliveryCheck->execute([$order['id']]);
                            $deliveryOption = $deliveryCheck->fetchColumn();
                            
                            if ($deliveryOption == 'delivery'): // Show delivery option only for delivery orders
                            ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="on_delivery">
                                <button type="submit" class="primary-button"><i class="fas fa-shipping-fast"></i> Mark as On Delivery</button>
                            </form>
                            <?php else: // For pickup orders, go straight to completed ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="primary-button"><i class="fas fa-check-circle"></i> Complete Order</button>
                            </form>
                            <?php endif; ?>
                            <?php elseif ($status == 'on_delivery'): ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="primary-button"><i class="fas fa-check-circle"></i> Complete Order</button>
                            </form>
                            <?php else: ?>
                            <!-- If status is empty or unknown, provide a way to fix it -->
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="pending">
                                <button type="submit" class="primary-button"><i class="fas fa-sync"></i> Reset Order Status</button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if (!in_array($status, ['cancelled', 'completed'])): ?>
                            <button class="secondary-button" onclick="openNotificationModal(<?php echo $order['id']; ?>)"><i class="fas fa-bell"></i> Send Notification</button>
                            <?php endif; ?>
                            
                            <?php if (!in_array($status, ['cancelled', 'completed'])): ?>
                            <form method="POST" action="my-businesses.php">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="secondary-button danger"><i class="fas fa-times-circle"></i> Cancel Order</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Notification Modal -->
            <div id="notification-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal" onclick="closeNotificationModal()">&times;</span>
                    <div class="modal-header">
                        <h3>Send Notification to Customer</h3>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="my-businesses.php" id="notification-form">
                            <input type="hidden" name="action" value="send_notification">
                            <input type="hidden" name="order_id" id="notification-order-id" value="">
                            <input type="hidden" name="business_id" value="<?php echo $view_business['id']; ?>">
                            
                            <div class="form-row">
                                <label for="notification-message">Message</label>
                                <textarea id="notification-message" name="message" rows="4" required placeholder="Enter a message for the customer..."></textarea>
                            </div>
                            
                            <div class="form-row">
                                <button type="submit" class="primary-button full-width">Send Notification</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Businesses List View -->
            <div class="section-header">
                <h3>Your Businesses</h3>
                <button class="secondary-button small" onclick="toggleBusinessForm()">
                    <i class="fas fa-plus"></i> Add Business
                </button>
            </div>
            
            <!-- Add Business Form -->
            <div id="business-form" class="form-container" style="display: none;">
                <h3>Add New Business</h3>
                <form method="POST" action="my-businesses.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_business">
                    
                    <div class="form-row">
                        <label for="name">Business Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                                <option value="Restaurant">Restaurant</option>
                                <option value="Grocery">Grocery</option>
                                <option value="Pharmacy">Pharmacy</option>
                                <option value="Retail">Retail</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" required>
                        <p class="form-hint">You can also click on the map to set your business location</p>
                    </div>
                    
                    <div class="form-row">
                        <label>Location on Map</label>
                        <div id="map-container"></div>
                        <input type="hidden" id="latitude" name="latitude" value="">
                        <input type="hidden" id="longitude" name="longitude" value="">
                    </div>
                    
                    <div class="form-row">
                        <label for="image">Business Logo/Image</label>
                        <input type="file" id="image" name="image" accept="image/*" required>
                        <p class="form-hint">Upload a clear image of your business logo or storefront</p>
                    </div>
                    
                    <div class="form-row">
                        <label for="business_permit">Business Permit</label>
                        <input type="file" id="business_permit" name="business_permit" accept="image/*,application/pdf">
                        <p class="form-hint">Upload a scanned copy of your business permit (for verification)</p>
                    </div>
                    
                    <div class="form-row two-columns">
                        <div>
                            <label for="phone_number">Phone Number</label>
                            <input type="tel" id="phone_number" name="phone_number" required>
                        </div>
                        <div>
                            <label for="business_email">Business Email</label>
                            <input type="email" id="business_email" name="business_email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label>Operating Hours</label>
                        <div class="operating-hours-container">
                            <?php $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']; ?>
                            <?php foreach ($days as $index => $day): ?>
                                <div class="operating-day">
                                    <div class="day-label"><?php echo $day; ?></div>
                                    <div class="hours-inputs">
                                        <div class="time-inputs">
                                            <input type="time" name="open_time[<?php echo strtolower($day); ?>]" id="open_<?php echo strtolower($day); ?>" value="08:00">
                                            <span>to</span>
                                            <input type="time" name="close_time[<?php echo strtolower($day); ?>]" id="close_<?php echo strtolower($day); ?>" value="17:00">
                                        </div>
                                        <div class="closed-checkbox">
                                            <input type="checkbox" name="closed[<?php echo strtolower($day); ?>]" id="closed_<?php echo strtolower($day); ?>" onchange="toggleDayHours(this, '<?php echo strtolower($day); ?>')">
                                            <label for="closed_<?php echo strtolower($day); ?>">Closed</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label>Additional Business Photos</label>
                        <input type="file" id="business_photos" name="business_photos[]" accept="image/*" multiple>
                        <p class="form-hint">Upload photos of your products, store interior, or other relevant images (up to 5)</p>
                    </div>
                    
                    <div class="checkbox-row terms-checkbox">
                        <input type="checkbox" id="terms_agreed" name="terms_agreed" required>
                        <label for="terms_agreed">I agree to the <a href="javascript:void(0);" onclick="openTermsModal()">Terms and Conditions</a> and certify that all information provided is accurate</label>
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="primary-button full-width">Create Business</button>
                    </div>
                    
                    <div class="form-row">
                        <button type="button" class="secondary-button full-width" onclick="toggleBusinessForm()">Cancel</button>
                    </div>
                </form>
            </div>
            
            <!-- Businesses List -->
            <?php if (empty($businesses)): ?>
                <div class="empty-state">
                    <i class="fas fa-store"></i>
                    <h3>No Businesses Yet</h3>
                    <p>Create your first business to start selling products.</p>
                    <button class="primary-button" onclick="toggleBusinessForm()" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Add Business
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($businesses as $business): ?>
                    <div class="business-card">
                        <div class="business-image" style="background-image: url('../<?php echo htmlspecialchars($business['image_url'] ?: 'assets/images/default-business.jpg'); ?>')">
                        </div>
                        <div class="business-content">
                            <div class="business-header">
                                <div class="business-name">
                                    <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                                    <div class="business-category"><?php echo htmlspecialchars($business['category']); ?></div>
                                </div>
                            </div>
                            <p><?php echo htmlspecialchars(substr($business['description'], 0, 100) . (strlen($business['description']) > 100 ? '...' : '')); ?></p>
                            <div class="business-actions">
                                <button class="primary-button" onclick="window.location.href='my-businesses.php?view_business=<?php echo $business['id']; ?>'">
                                    <i class="fas fa-edit"></i> Manage
                                </button>
                                <form method="POST" action="my-businesses.php" onsubmit="return confirm('Are you sure you want to delete this business? This will also delete all products.');">
                                    <input type="hidden" name="action" value="delete_business">
                                    <input type="hidden" name="business_id" value="<?php echo $business['id']; ?>">
                                    <button type="submit" class="secondary-button">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Bottom Navigation -->
    <?php include_once 'includes/bottom_navigation.php'; ?>

    <script>
        // Initialize view map if it exists on page load
        document.addEventListener('DOMContentLoaded', function() {
            const viewMapContainer = document.getElementById('view-map-container');
            if (viewMapContainer) {
                initViewMap(viewMapContainer);
            }
            
            // Check URL parameters for active tab
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                showTab(tab);
            }
        });
        
        function toggleBusinessForm() {
            const form = document.getElementById('business-form');
            const display = form.style.display === 'none' ? 'block' : 'none';
            form.style.display = display;
            
            // Initialize map when form is shown
            if (display === 'block') {
                setTimeout(initMap, 100); // Small delay to ensure the container is visible
            }
        }
        
        function toggleProductForm() {
            const form = document.getElementById('product-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function showTab(tabName) {
            // Hide all tabs
            document.getElementById('products-tab').style.display = 'none';
            document.getElementById('orders-tab').style.display = 'none';
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Update active tab
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            if (tabName === 'products') {
                tabs[0].classList.add('active');
            } else {
                tabs[1].classList.add('active');
            }
            
            // Update URL without refreshing the page
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        // Filter orders by status
        function filterOrders(status) {
            const orderCards = document.querySelectorAll('.order-card');
            
            orderCards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Notification modal functions
        function openNotificationModal(orderId) {
            document.getElementById('notification-order-id').value = orderId;
            document.getElementById('notification-modal').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function closeNotificationModal() {
            document.getElementById('notification-modal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
            document.getElementById('notification-message').value = '';
        }
        
        // Map functionality
        let map, marker;
        
        function initMap() {
            // Default coordinates (Philippines center)
            const defaultLat = 12.8797;
            const defaultLng = 121.7740;
            
            // Initialize map
            map = L.map('map-container').setView([defaultLat, defaultLng], 6);
            
            // Add OpenStreetMap tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Try to get user's current location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        // Update map view
                        map.setView([lat, lng], 15);
                        
                        // Add marker at user's location
                        addMarker(lat, lng);
                        
                        // Reverse geocode to get address
                        reverseGeocode(lat, lng);
                    },
                    function(error) {
                        console.log('Geolocation error:', error);
                    }
                );
            }
            
            // Add click event to map
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                
                // Add marker at clicked location
                addMarker(lat, lng);
                
                // Reverse geocode to get address
                reverseGeocode(lat, lng);
            });
            
            // Add search functionality to address input
            const addressInput = document.getElementById('address');
            addressInput.addEventListener('change', function() {
                geocodeAddress(this.value);
            });
        }
        
        function addMarker(lat, lng) {
            // Update hidden inputs
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Remove existing marker if any
            if (marker) {
                map.removeLayer(marker);
            }
            
            // Add new marker
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            
            // Add drag end event
            marker.on('dragend', function(e) {
                const position = marker.getLatLng();
                reverseGeocode(position.lat, position.lng);
            });
        }
        
        function reverseGeocode(lat, lng) {
            // Use Nominatim for reverse geocoding
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        document.getElementById('address').value = data.display_name;
                    }
                })
                .catch(error => console.error('Reverse geocoding error:', error));
        }
        
        function geocodeAddress(address) {
            if (!address) return;
            
            // Use Nominatim for geocoding
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        
                        // Update map view
                        map.setView([lat, lng], 15);
                        
                        // Add marker
                        addMarker(lat, lng);
                    }
                })
                .catch(error => console.error('Geocoding error:', error));
        }
        
        function initViewMap(container) {
            // Get coordinates from data attributes
            const lat = parseFloat(container.getAttribute('data-lat'));
            const lng = parseFloat(container.getAttribute('data-lng'));
            
            if (isNaN(lat) || isNaN(lng)) return;
            
            // Initialize map
            const viewMap = L.map(container.id).setView([lat, lng], 15);
            
            // Add OpenStreetMap tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(viewMap);
            
            // Add marker at business location
            L.marker([lat, lng]).addTo(viewMap);
        }
        
        // Function to toggle time inputs when a day is marked as closed
        function toggleDayHours(checkbox, day) {
            const openInput = document.getElementById(`open_${day}`);
            const closeInput = document.getElementById(`close_${day}`);
            
            if (checkbox.checked) {
                // Day is closed, disable time inputs
                openInput.disabled = true;
                closeInput.disabled = true;
                openInput.parentElement.classList.add('disabled');
            } else {
                // Day is open, enable time inputs
                openInput.disabled = false;
                closeInput.disabled = false;
                openInput.parentElement.classList.remove('disabled');
            }
        }
    </script>
    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeTermsModal()">&times;</span>
            <div class="modal-header">
                <h3>Terms and Conditions for Business Registration</h3>
            </div>
            <div class="modal-body">
                <h4>1. Business Verification</h4>
                <p>All businesses registered on OrderKo must undergo a verification process. By submitting your business information, you agree to the following:</p>
                <ul>
                    <li>All information provided is accurate and truthful</li>
                    <li>You are authorized to represent and operate this business</li>
                    <li>You have the necessary permits and licenses to operate legally</li>
                    <li>OrderKo reserves the right to verify all submitted information</li>
                </ul>
                
                <h4>2. Business Permit Requirements</h4>
                <p>To operate on OrderKo, you must provide a valid business permit or equivalent documentation. This helps us ensure that all businesses on our platform are legitimate and comply with local regulations.</p>
                
                <h4>3. Operating Hours</h4>
                <p>You are responsible for maintaining accurate operating hours. Customers will rely on this information to place orders and visit your business.</p>
                
                <h4>4. Product Listings</h4>
                <p>All products listed must:</p>
                <ul>
                    <li>Be accurately described</li>
                    <li>Have clear pricing information</li>
                    <li>Comply with all applicable laws and regulations</li>
                    <li>Not infringe on any intellectual property rights</li>
                </ul>
                
                <h4>5. Order Fulfillment</h4>
                <p>As a business owner, you agree to:</p>
                <ul>
                    <li>Fulfill orders promptly and accurately</li>
                    <li>Maintain adequate inventory of listed products</li>
                    <li>Update product availability in a timely manner</li>
                    <li>Provide quality products and services to customers</li>
                </ul>
                
                <h4>6. Fees and Payments</h4>
                <p>OrderKo may charge service fees for using the platform. These fees will be clearly communicated and may include:</p>
                <ul>
                    <li>Commission on sales</li>
                    <li>Subscription fees for premium features</li>
                    <li>Payment processing fees</li>
                </ul>
                
                <h4>7. Account Suspension</h4>
                <p>OrderKo reserves the right to suspend or terminate your business account if:</p>
                <ul>
                    <li>You violate these terms and conditions</li>
                    <li>You receive consistent negative feedback from customers</li>
                    <li>Your business permit expires or is revoked</li>
                    <li>You engage in fraudulent or illegal activities</li>
                </ul>
                
                <h4>8. Privacy and Data Protection</h4>
                <p>You agree to protect customer data and comply with all applicable privacy laws. OrderKo will handle your business information in accordance with our Privacy Policy.</p>
            </div>
            <div class="modal-footer">
                <button class="primary-button" onclick="agreeToTerms()">I Agree</button>
                <button class="secondary-button" onclick="closeTermsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Terms and Conditions Modal Functions
        function openTermsModal() {
            document.getElementById('termsModal').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
        }
        
        function closeTermsModal() {
            document.getElementById('termsModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }
        
        function agreeToTerms() {
            document.getElementById('terms_agreed').checked = true;
            closeTermsModal();
        }
        
        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target === modal) {
                closeTermsModal();
            }
        };
    </script>
</body>
</html>
