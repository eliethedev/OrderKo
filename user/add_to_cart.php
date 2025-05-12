<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit;
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the request
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $options = isset($_POST['options']) ? $_POST['options'] : '';
    
    // Validate product_id
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product']);
        exit;
    }
    
    // Validate quantity
    if ($quantity <= 0) {
        $quantity = 1;
    }
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Check if the product is already in the cart
    $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $existing_item = $stmt->fetch();
    
    if ($existing_item) {
        // Update quantity if the product is already in the cart
        $new_quantity = $existing_item['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ?, options = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$new_quantity, $options, $existing_item['id']]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Cart updated successfully',
                'cart_count' => getCartCount($pdo, $_SESSION['user_id']),
                'cart_total' => getCartTotal($pdo, $_SESSION['user_id'])
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        }
    } else {
        // Add new item to cart
        $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity, options) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$_SESSION['user_id'], $product_id, $quantity, $options]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Product added to cart',
                'cart_count' => getCartCount($pdo, $_SESSION['user_id']),
                'cart_total' => getCartTotal($pdo, $_SESSION['user_id'])
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add product to cart']);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to get cart count
function getCartCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as count FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'] ?: 0;
}

// Function to get cart total
function getCartTotal($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT SUM(ci.quantity * p.price) as total 
                          FROM cart_items ci 
                          JOIN products p ON ci.product_id = p.id 
                          WHERE ci.user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['total'] ?: 0;
}
?>
