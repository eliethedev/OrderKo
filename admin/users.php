<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle user status changes
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "User activated successfully";
    } elseif ($action === 'deactivate') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "User deactivated successfully";
    } elseif ($action === 'delete') {
        // Check if user has orders before deleting
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ?");
        $stmt->execute([$id]);
        $has_orders = $stmt->fetch()['count'] > 0;
        
        if ($has_orders) {
            $error_message = "Cannot delete user with existing orders. Deactivate instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "User deleted successfully";
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
$params = [];

if (!empty($search)) {
    $search_condition = "WHERE name LIKE ? OR email LIKE ? OR phone_number LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Get total users for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $search_condition");
$stmt->execute($params);
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $records_per_page);

// Get users with pagination - using direct integer insertion for LIMIT which is safe since we've already validated these are integers
$limit_clause = "LIMIT $offset, $records_per_page";
$stmt = $pdo->prepare("SELECT * FROM users $search_condition ORDER BY created_at DESC $limit_clause");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - OrderKo Admin</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>User Management</h1>
                <div class="admin-actions">
                    <form action="" method="GET" class="search-form">
                        <div class="search-input-group">
                            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                            <button type="submit" class="btn"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>All Users</h2>
                    <a href="add-user.php" class="btn btn-sm"><i class="fas fa-plus"></i> Add User</a>
                </div>
                <div class="card-body">
                    <?php if (empty($users)): ?>
                        <p class="empty-state">No users found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['status'] === 'active' ? 'completed' : 'cancelled'; ?>">
                                                    <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td class="actions">
                                                <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm" title="View"><i class="fas fa-eye"></i></a>
                                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                                
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <?php if ($user['status'] === 'active' || empty($user['status'])): ?>
                                                        <a href="users.php?action=deactivate&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Deactivate" onclick="return confirm('Are you sure you want to deactivate this user?')">
                                                            <i class="fas fa-user-slash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-success" title="Activate" onclick="return confirm('Are you sure you want to activate this user?')">
                                                            <i class="fas fa-user-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm">&laquo; Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="js/admin.js"></script>
<style>
    /* Additional inline styles for user management page */
    .search-input-group {
        display: flex;
        width: 100%;
    }
    
    .search-input-group .form-control {
        flex: 1;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }
    
    .search-input-group .btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .user-info img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .badge-secondary {
        background-color: var(--gray-color);
        color: white;
    }
    
    .actions {
        display: flex;
        gap: 5px;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
        flex-wrap: wrap;
    }
    
    .pagination .btn.active {
        background-color: var(--dark-color);
        color: white;
    }
    
    @media (max-width: 767.98px) {
        .responsive-table td:before {
            content: attr(data-label);
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        
        .actions .btn-sm {
            padding: 5px;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    }
</style>
</body>
</html>
