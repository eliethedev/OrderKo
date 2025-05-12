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
    $payment_method = $data['payment_method'] ?? 'cash';
    
    // Format pickup date and time into a single DATETIME value
    $pickup_date_str = $data['pickup_date'] ?? date('l, F j, Y');
    $pickup_time_str = $data['pickup_time'] ?? date('g:i A');
    
    // Convert to MySQL DATETIME format (YYYY-MM-DD HH:MM:SS)
    $datetime = date_create_from_format('l, F j, Y g:i A', "$pickup_date_str $pickup_time_str");
    if (!$datetime) {
        // Fallback if parsing fails
        $datetime = new DateTime();
    }
    $pickup_datetime = $datetime->format('Y-m-d H:i:s');
    
    $note = $data['note'] ?? '';
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get cart items
    $stmt = $pdo->prepare("SELECT ci.*, p.name, p.price, p.business_id 
                           FROM cart_items ci 
                           JOIN products p ON ci.product_id = p.id 
                           WHERE ci.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
    
    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }
    
    // Calculate totals
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    $service_fee = 20;
    $grand_total = $total + $service_fee;
    
    // Group items by business
    $business_items = [];
    foreach ($cart_items as $item) {
        if (!isset($business_items[$item['business_id']])) {
            $business_items[$item['business_id']] = [];
        }
        $business_items[$item['business_id']][] = $item;
    }
    
    // Create an order for each business
    $order_ids = [];
    foreach ($business_items as $business_id => $items) {
        // Calculate business total
        $business_total = 0;
        foreach ($items as $item) {
            $business_total += $item['price'] * $item['quantity'];
        }
        
        // Create order
        $stmt = $pdo->prepare("INSERT INTO orders (customer_id, business_id, status, pickup_date, total_amount, special_instructions) 
                               VALUES (?, ?, 'pending', ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $business_id,
            $pickup_datetime,
            $business_total,
            $note
        ]);
        $order_id = $pdo->lastInsertId();
        $order_ids[] = $order_id;
        
        // Add order items
        foreach ($items as $item) {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);
        }
    }
    
    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'order_ids' => $order_ids]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error processing order: ' . $e->getMessage()]);
}
?>
