<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get date range from query parameter
$range = isset($_GET['range']) ? intval($_GET['range']) : 30;

try {
    // Get order trends based on selected range
    $stmt = $pdo->prepare("
        SELECT 
            DATE(orders.created_at) as date,
            COUNT(*) as orders,
            SUM(order_items.quantity * products.price) as revenue
        FROM orders
        JOIN order_items ON orders.id = order_items.order_id
        JOIN products ON order_items.product_id = products.id
        WHERE orders.status = 'completed'
        AND orders.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(orders.created_at)
        ORDER BY date ASC
    ");
    
    $stmt->execute([$range]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for chart
    $labels = [];
    $orders = [];
    $revenue = [];

    foreach ($results as $row) {
        $labels[] = $row['date'];
        $orders[] = $row['orders'];
        $revenue[] = $row['revenue'];
    }

    echo json_encode([
        'labels' => $labels,
        'orders' => $orders,
        'revenue' => $revenue
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
