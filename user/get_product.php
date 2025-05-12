<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

$product_id = $_GET['id'];

// Get user location from session or use default
$user_latitude = isset($_SESSION['user_latitude']) ? $_SESSION['user_latitude'] : 14.5995;
$user_longitude = isset($_SESSION['user_longitude']) ? $_SESSION['user_longitude'] : 120.9842;

// Fetch product details with business information
$query = "SELECT p.*, b.name as business_name, b.category as business_category, 
          b.image_url as business_image, b.verification_status, b.id as business_id,
          b.latitude, b.longitude,
          CASE 
            WHEN b.latitude IS NOT NULL AND b.longitude IS NOT NULL THEN 
                ROUND((
                    6371 * acos(
                        cos(radians(?)) * 
                        cos(radians(b.latitude)) * 
                        cos(radians(b.longitude) - radians(?)) + 
                        sin(radians(?)) * 
                        sin(radians(b.latitude))
                    )
                ), 1)
            ELSE NULL
          END as distance
          FROM products p
          JOIN businesses b ON p.business_id = b.id
          WHERE p.id = ?";

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $user_latitude, PDO::PARAM_STR);
$stmt->bindParam(2, $user_longitude, PDO::PARAM_STR);
$stmt->bindParam(3, $user_latitude, PDO::PARAM_STR);
$stmt->bindParam(4, $product_id, PDO::PARAM_INT);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// If product not found, return error
if (!$product) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Format the product data for JSON response
$response = [
    'success' => true,
    'product' => [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'description' => $product['description'],
        'image_url' => $product['image_url'],
        'business_id' => $product['business_id'],
        'business_name' => $product['business_name'],
        'business_category' => $product['business_category'],
        'business_image' => $product['business_image'],
        'verification_status' => $product['verification_status'],
        'distance' => $product['distance']
    ]
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
