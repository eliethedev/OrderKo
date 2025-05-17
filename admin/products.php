<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get admin information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_product'])) {
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        $_SESSION['message'] = "Product deleted successfully";
        header('Location: products.php');
        exit;
    } elseif (isset($_POST['save_product'])) {
        // Add/edit product
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'price' => $_POST['price'],
            'business_id' => $_POST['business_id'],
            'is_available' => isset($_POST['is_available']) ? 1 : 0
        ];

        if (!empty($_POST['product_id'])) {
            // Update existing product
            $data['id'] = $_POST['product_id'];
            $stmt = $pdo->prepare("UPDATE products SET 
                name = :name, 
                description = :description, 
                price = :price, 
                business_id = :business_id, 
                is_available = :is_available, 
                updated_at = NOW() 
                WHERE id = :id");
            $stmt->execute($data);
            $_SESSION['message'] = "Product updated successfully";
        } else {
            // Add new product
            $stmt = $pdo->prepare("INSERT INTO products 
                (name, description, price, business_id, is_available, created_at, updated_at) 
                VALUES (:name, :description, :price, :business_id, :is_available, NOW(), NOW())");
            $stmt->execute($data);
            $_SESSION['message'] = "Product added successfully";
        }
        
        header('Location: products.php');
        exit;
    }
}

// Get all products with business names
$stmt = $pdo->query("SELECT p.*, b.name as business_name 
                     FROM products p 
                     JOIN businesses b ON p.business_id = b.id 
                     ORDER BY p.created_at DESC");
$products = $stmt->fetchAll();

// Get all businesses for dropdown
$stmt = $pdo->query("SELECT id, name FROM businesses ORDER BY name");
$businesses = $stmt->fetchAll();

// Get product categories
$categories = ['Food', 'Drink', 'Dessert', 'Other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - OrderKo</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>Manage Products</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>
            
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            
            <!-- Add Product Button -->
            <div class="mb-3">
                <button class="btn btn-primary" onclick="showProductModal()">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>
            
            <!-- Products Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <p class="empty-state">No products found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Business</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['business_name']); ?></td>
                                            <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $product['is_available'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $product['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Add Product</h2>
            <form method="POST" id="productForm">
                <input type="hidden" name="product_id" id="product_id">
                
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="price">Price</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="business_id">Business</label>
                    <select id="business_id" name="business_id" required>
                        <option value="">Select Business</option>
                        <?php foreach ($businesses as $business): ?>
                            <option value="<?php echo $business['id']; ?>"><?php echo htmlspecialchars($business['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-container">
                        <input type="checkbox" id="is_available" name="is_available" checked>
                        <span class="checkmark"></span>
                        Available for order
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_product" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/admin.js"></script>
    <script>
        // Modal functions
        function showProductModal() {
            document.getElementById('productForm').reset();
            document.getElementById('product_id').value = '';
            document.getElementById('modalTitle').innerText = 'Add Product';
            document.getElementById('is_available').checked = true;
            document.getElementById('productModal').style.display = 'block';
        }
        
        function editProduct(product) {
            document.getElementById('productForm').reset();
            document.getElementById('product_id').value = product.id;
            document.getElementById('modalTitle').innerText = 'Edit Product';
            document.getElementById('name').value = product.name;
            document.getElementById('description').value = product.description;
            document.getElementById('price').value = product.price;
            document.getElementById('business_id').value = product.business_id;
            document.getElementById('category').value = product.category;
            document.getElementById('is_available').checked = product.is_available == 1;
            document.getElementById('productModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>