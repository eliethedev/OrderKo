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

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_category'])) {
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$_POST['category_id']]);
        $_SESSION['message'] = "Category deleted successfully";
        header('Location: categories.php');
        exit;
    } elseif (isset($_POST['save_category'])) {
        // Add/edit category
        $data = [
            'name' => $_POST['name'],
            'description' => $_POST['description']
        ];

        if (!empty($_POST['category_id'])) {
            // Update existing category
            $data['id'] = $_POST['category_id'];
            $stmt = $pdo->prepare("UPDATE categories SET 
                name = :name, 
                description = :description,
                updated_at = NOW() 
                WHERE id = :id");
            $stmt->execute($data);
            $_SESSION['message'] = "Category updated successfully";
        } else {
            // Add new category
            $stmt = $pdo->prepare("INSERT INTO categories 
                (name, description, is_active, created_at, updated_at) 
                VALUES (:name, :description, 1, NOW(), NOW())");
            $stmt->execute($data);
            $_SESSION['message'] = "Category added successfully";
        }
        
        header('Location: categories.php');
        exit;
    }
}

// Get all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - OrderKo</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-badge.active {
            background-color: #4CAF50;
            color: white;
        }
        .status-badge.inactive {
            background-color: #f44336;
            color: white;
        }
        .table-container {
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th, .admin-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .admin-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .btn-icon i {
            font-size: 16px;
        }
        .btn-icon.success {
            background-color: #4CAF50;
            color: white;
        }
        .btn-icon.warning {
            background-color: #ff9800;
            color: white;
        }
        .btn-icon.danger {
            background-color: #f44336;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            position: relative;
            background-color: white;
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .close {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>Manage Categories</h1>
                <div class="admin-user">
                    <span><?php echo htmlspecialchars($admin['full_name']); ?></span>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert success">
                    <?php 
                        echo htmlspecialchars($_SESSION['message']);
                        unset($_SESSION['message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Add Category Button -->
            <button class="btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add Category
            </button>

            <!-- Categories Table -->
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $category['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($category['created_at'])); ?></td>
                            <td>
                                <button class="btn-icon" onclick="editCategory(<?php echo $category['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon <?php echo $category['is_active'] ? 'warning' : 'success'; ?>" 
                                        onclick="toggleStatus(<?php echo $category['id']; ?>, <?php echo $category['is_active'] ? 'true' : 'false'; ?>)">
                                    <i class="fas fa-<?php echo $category['is_active'] ? 'ban' : 'check'; ?>"></i>
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?')">
                                    <input type="hidden" name="delete_category" value="1">
                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <button type="submit" class="btn-icon danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Category Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Add Category</h2>
            <form method="POST" id="categoryForm">
                <input type="hidden" id="category_id" name="category_id">
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" name="save_category" class="btn-primary">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('categoryModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('category_id').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
        }

        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function editCategory(id) {
            openModal();
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('category_id').value = id;
            
            // Fetch category data and populate form
            fetch('get_category.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('name').value = data.name;
                    document.getElementById('description').value = data.description;
                });
        }

        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                document.getElementById('category_id').value = id;
                document.getElementById('categoryForm').submit();
            }
        }

        function toggleStatus(id, isActive) {
            if (confirm('Are you sure you want to ' + (isActive ? 'deactivate' : 'activate') + ' this category?')) {
                fetch('toggle_category_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        is_active: !isActive
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating status');
                    }
                });
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('categoryModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>