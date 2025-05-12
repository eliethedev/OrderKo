<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to remove items from cart']);
    exit;
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from the request
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
    // Validate item_id
    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
        exit;
    }
    
    // Check if the cart item exists and belongs to the current user
    $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$item_id, $_SESSION['user_id']]);
    $cart_item = $stmt->fetch();
    
    if (!$cart_item) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }
    
    // Remove item from cart
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
    $result = $stmt->execute([$item_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Item removed from cart',
            'cart_count' => getCartCount($pdo, $_SESSION['user_id']),
            'cart_total' => getCartTotal($pdo, $_SESSION['user_id'])
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove item from cart']);
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
