<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to view product details']);
    exit;
}

// Get product ID from request
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Fetch product details
$stmt = $pdo->prepare("SELECT p.*, b.name as business_name 
                       FROM products p 
                       JOIN businesses b ON p.business_id = b.id 
                       WHERE p.id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Return product details as JSON
echo json_encode([
    'success' => true,
    'product' => [
        'id' => $product['id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => $product['price'],
        'image_url' => $product['image_url'],
        'business_name' => $product['business_name']
    ]
]);
?>
