<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get product ID from request
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    // Get average rating for this product
    $stmt = $pdo->prepare("SELECT AVG(rating) as average_rating FROM reviews WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $result = $stmt->fetch();
    $average_rating = $result['average_rating'] ? floatval($result['average_rating']) : null;
    
    // Get reviews for this product with user information
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as user_name, u.profile_picture as user_image
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll();
    
    // Format the reviews for JSON response
    $formatted_reviews = [];
    foreach ($reviews as $review) {
        $formatted_reviews[] = [
            'id' => $review['id'],
            'rating' => $review['rating'],
            'comment' => $review['comment'],
            'created_at' => $review['created_at'],
            'user_name' => $review['user_name'],
            'user_image' => $review['user_image'] ? '../' . $review['user_image'] : null
        ];
    }
    
    // Return success response with reviews and average rating
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'reviews' => $formatted_reviews,
        'average_rating' => $average_rating
    ]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
