<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update an order']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get order details from POST data
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$business_id = isset($_POST['business_id']) ? intval($_POST['business_id']) : 0;

// Validate input
if (!$order_id || !$status || !$business_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status value
$valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

try {
    // Verify that the business belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ? AND user_id = ?");
    $stmt->execute([$business_id, $_SESSION['user_id']]);
    $business = $stmt->fetch();
    
    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this order']);
        exit;
    }
    
    // Verify that the order belongs to this business
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND business_id = ?");
    $stmt->execute([$order_id, $business_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    // Add notification for the customer (if notifications table exists)
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                           VALUES (?, ?, ?, 'order', ?, NOW())");
        $title = "Order Status Update";
        $message = "Your order #" . $order_id . " has been updated to " . ucfirst($status);
        $stmt->execute([$order['customer_id'], $title, $message, $order_id]);
    } catch (Exception $e) {
        // Notifications table might not exist, just continue
    }
    
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
