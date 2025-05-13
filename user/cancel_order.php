<?php
session_start();
require_once '../config/database.php';

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

// Check if required data is provided
if (!isset($_POST['order_id']) || empty($_POST['order_id']) || !isset($_POST['reason'])) {
    $response['message'] = 'Missing required information';
    echo json_encode($response);
    exit;
}

$order_id = intval($_POST['order_id']);
$reason = trim($_POST['reason']);
$user_id = $_SESSION['user_id'];

try {
    // First, verify that the order belongs to the current user and is in a cancellable state
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ? AND status = 'pending'");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $response['message'] = 'Order not found or cannot be cancelled';
        echo json_encode($response);
        exit;
    }
    
    // Update the order status to cancelled
    // Note: We're only updating the status since the cancel_reason column doesn't exist yet
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Store the cancellation reason in a log or display it to the user
    // This can be implemented later when the cancel_reason column is added
    
    $response['success'] = true;
    $response['message'] = 'Order cancelled successfully';
    
} catch (PDOException $e) {
    $response['message'] = 'Error cancelling order: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
