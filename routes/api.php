<?php
require_once '../config/database.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Set content type
header('Content-Type: application/json');

// Route handler
function route($method, $path) {
    $routes = [
        'GET' => [
            '/businesses' => 'getBusinesses',
            '/products' => 'getProducts',
            '/orders' => 'getOrders',
            '/profile' => 'getProfile'
        ],
        'POST' => [
            '/login' => 'login',
            '/register' => 'register',
            '/orders' => 'createOrder',
            '/products' => 'createProduct'
        ],
        'PUT' => [
            '/orders/:id' => 'updateOrder',
            '/products/:id' => 'updateProduct',
            '/profile' => 'updateProfile'
        ],
        'DELETE' => [
            '/products/:id' => 'deleteProduct'
        ]
    ];

    // Handle OPTIONS requests for CORS
    if ($method === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    // Check if method exists
    if (!isset($routes[$method])) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Find matching route
    $matched = false;
    foreach ($routes[$method] as $routePath => $handler) {
        // Handle dynamic routes (with :id)
        if (strpos($routePath, ':id') !== false) {
            $pattern = str_replace(':id', '([0-9]+)', $routePath);
            if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
                $matched = true;
                $id = $matches[1];
                break;
            }
        } else {
            if ($path === $routePath) {
                $matched = true;
                break;
            }
        }
    }

    if (!$matched) {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        return;
    }

    if (isset($routes[$method][$routeKey])) {
        $function = $routes[$method][$routeKey];
        return $function();
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found']);
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle the request
route($method, $path);
?>
