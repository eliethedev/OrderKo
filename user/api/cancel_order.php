<?php
session_start();
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = $data['order_id'] ?? 0;
    $reason = $data['reason'] ?? 'No reason provided';
    
    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if order exists and belongs to the current user
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or does not belong to you']);
        exit;
    }
    
    // Check if order can be cancelled (only pending orders can be cancelled)
    if ($order['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending orders can be cancelled']);
        exit;
    }
    
    // Update order status to cancelled
    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', special_instructions = CONCAT(special_instructions, ' | Cancelled: ', ?) WHERE id = ?");
    $stmt->execute([$reason, $order_id]);
    
    // Create cancellation record in a separate table if it exists
    // This is optional and depends on your database schema
    try {
        $stmt = $pdo->prepare("INSERT INTO order_cancellations (order_id, reason, cancelled_at) VALUES (?, ?, NOW())");
        $stmt->execute([$order_id, $reason]);
    } catch (PDOException $e) {
        // Table might not exist, just continue
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error cancelling order: ' . $e->getMessage()]);
}
?>
