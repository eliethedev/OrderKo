<?php
session_start();
require_once '../config/database.php';

// Initialize response
$response = [
    'success' => false,
    'is_favorite' => false,
    'message' => ''
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

// Check if business_id is provided
if (!isset($_POST['business_id']) || empty($_POST['business_id'])) {
    $response['message'] = 'Business ID is required';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$business_id = intval($_POST['business_id']);

try {
    // Check if the business is already a favorite
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND business_id = ?");
    $stmt->execute([$user_id, $business_id]);
    $favorite = $stmt->fetch();
    
    if ($favorite) {
        // Remove from favorites
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND business_id = ?");
        $stmt->execute([$user_id, $business_id]);
        
        $response['success'] = true;
        $response['is_favorite'] = false;
        $response['message'] = 'Removed from favorites';
    } else {
        // Add to favorites
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, business_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $business_id]);
        
        $response['success'] = true;
        $response['is_favorite'] = true;
        $response['message'] = 'Added to favorites';
    }
} catch (PDOException $e) {
    $response['message'] = 'Error updating favorites: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
