<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get data from POST request
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$user_id = $_SESSION['user_id'];

// Validate data
if ($product_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

if (empty($comment)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Review comment is required']);
    exit;
}

try {
    // Check if user has already reviewed this product
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$product_id, $user_id]);
    $existing_review = $stmt->fetch();
    
    if ($existing_review) {
        // Update existing review
        $stmt = $pdo->prepare("
            UPDATE reviews 
            SET rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP 
            WHERE product_id = ? AND user_id = ?
        ");
        $stmt->execute([$rating, $comment, $product_id, $user_id]);
        $message = 'Your review has been updated';
    } else {
        // Check if user has purchased this product
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as purchased 
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.customer_id = ? AND oi.product_id = ? AND o.status = 'completed'
        ");
        $stmt->execute([$user_id, $product_id]);
        $result = $stmt->fetch();
        
        if ($result['purchased'] == 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'You can only review products you have purchased']);
            exit;
        }
        
        // Insert new review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (product_id, user_id, rating, comment) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, $user_id, $rating, $comment]);
        $message = 'Your review has been submitted';
    }
    
    // Update product average rating and review count
    $stmt = $pdo->prepare("
        UPDATE products p
        SET 
            review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = p.id),
            average_rating = (SELECT AVG(rating) FROM reviews WHERE product_id = p.id)
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    
    // Get updated product review data
    $stmt = $pdo->prepare("SELECT review_count, average_rating FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'review_count' => $product['review_count'],
        'average_rating' => $product['average_rating']
    ]);
    
} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
