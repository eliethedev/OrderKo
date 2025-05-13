<?php
session_start();
require_once '../config/database.php';

// Initialize response
$response = [
    'success' => false,
    'cart_count' => 0,
    'cart_total' => 0,
    'message' => ''
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get cart information
try {
    // Get cart items count
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total_items, SUM(quantity * price) as total_price 
                          FROM cart_items ci 
                          JOIN products p ON ci.product_id = p.id 
                          WHERE ci.user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['cart_count'] = $cart['total_items'] ? intval($cart['total_items']) : 0;
    $response['cart_total'] = $cart['total_price'] ? floatval($cart['total_price']) : 0;
} catch (PDOException $e) {
    $response['message'] = 'Error retrieving cart information';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
