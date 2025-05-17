<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle business status changes
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'verify') {
        $stmt = $pdo->prepare("UPDATE businesses SET status = 'verified' WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "Business verified successfully";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE businesses SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "Business rejected successfully";
    } elseif ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE businesses SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "Business suspended successfully";
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE businesses SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "Business activated successfully";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter and search functionality
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where_conditions = [];
$params = [];

if ($filter === 'pending') {
    $where_conditions[] = "b.status = 'pending'";
} elseif ($filter === 'verified') {
    $where_conditions[] = "b.status = 'verified'";
} elseif ($filter === 'rejected') {
    $where_conditions[] = "b.status = 'rejected'";
} elseif ($filter === 'suspended') {
    $where_conditions[] = "b.status = 'suspended'";
}

if (!empty($search)) {
    $where_conditions[] = "(b.name LIKE ? OR u.full_name LIKE ? OR b.category LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total businesses for pagination
$count_query = "SELECT COUNT(*) as total 
              FROM businesses b 
              LEFT JOIN users u ON b.user_id = u.id 
              $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_businesses = $stmt->fetch()['total'];
$total_pages = ceil($total_businesses / $records_per_page);

// Get businesses with pagination - using direct integer insertion for LIMIT which is safe since we've already validated these are integers
$limit_clause = "LIMIT $offset, $records_per_page";

// We need to join with users table to get the owner's name
$query = "SELECT b.*, u.full_name as owner_name 
          FROM businesses b 
          LEFT JOIN users u ON b.user_id = u.id 
          $where_clause 
          ORDER BY b.created_at DESC $limit_clause";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Management - OrderKo Admin</title>
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
                <h1>Business Management</h1>
                <div class="admin-actions">
                    <form action="" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search businesses..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <?php if (!empty($filter)): ?>
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="businesses.php" class="filter-tab <?php echo empty($filter) ? 'active' : ''; ?>">All</a>
                <a href="businesses.php?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending Verification</a>
                <a href="businesses.php?filter=verified" class="filter-tab <?php echo $filter === 'verified' ? 'active' : ''; ?>">Verified</a>
                <a href="businesses.php?filter=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
                <a href="businesses.php?filter=suspended" class="filter-tab <?php echo $filter === 'suspended' ? 'active' : ''; ?>">Suspended</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>
                        <?php 
                        if ($filter === 'pending') echo 'Pending Verification';
                        elseif ($filter === 'verified') echo 'Verified Businesses';
                        elseif ($filter === 'rejected') echo 'Rejected Businesses';
                        elseif ($filter === 'suspended') echo 'Suspended Businesses';
                        else echo 'All Businesses';
                        ?>
                    </h2>
                    <span class="count-badge"><?php echo $total_businesses; ?> businesses</span>
                </div>
                <div class="card-body">
                    <?php if (empty($businesses)): ?>
                        <p class="empty-state">No businesses found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Business</th>
                                        <th>Owner</th>
                                        <th>Category</th>
                                        <th>Verification</th>
                                        <th>Status</th>
                                        <th>Created On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($businesses as $business): ?>
                                        <tr>
                                            <td><?php echo $business['id']; ?></td>
                                            <td>
                                                <div class="business-info">
                                                    <img src="<?php echo !empty($business['image_url']) ? '../' . $business['image_url'] : '../assets/business-placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($business['name']); ?>">
                                                    <span><?php echo htmlspecialchars($business['name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($business['owner_name']); ?></td>
                                            <td><?php echo htmlspecialchars($business['category']); ?></td>
                                            <td>
                                                <?php 
                                                $status = $business['status'] ?? 'pending';
                                                $verification_class = '';
                                                
                                                if ($status === 'verified') {
                                                    $verification_class = 'completed';
                                                } elseif ($status === 'rejected') {
                                                    $verification_class = 'cancelled';
                                                } elseif ($status === 'pending') {
                                                    $verification_class = 'pending';
                                                } elseif ($status === 'active') {
                                                    $verification_class = 'completed';
                                                    $status = 'verified'; // For display purposes
                                                }
                                                ?>
                                                <span class="status-badge status-<?php echo $verification_class; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo ($business['status'] ?? 'active') === 'active' ? 'completed' : 'cancelled'; ?>">
                                                    <?php echo ucfirst($business['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($business['created_at'])); ?></td>
                                            <td class="actions">
                                                <a href="view-business.php?id=<?php echo $business['id']; ?>" class="btn btn-sm" title="View"><i class="fas fa-eye"></i></a>
                                                
                                                <?php if (($business['status'] ?? '') === 'pending'): ?>
                                                    <a href="businesses.php?action=verify&id=<?php echo $business['id']; ?>" class="btn btn-sm btn-success" title="Verify" onclick="return confirm('Are you sure you want to verify this business?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="businesses.php?action=reject&id=<?php echo $business['id']; ?>" class="btn btn-sm btn-danger" title="Reject" onclick="return confirm('Are you sure you want to reject this business?')">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if (($business['status'] ?? 'active') === 'active'): ?>
                                                    <a href="businesses.php?action=suspend&id=<?php echo $business['id']; ?>" class="btn btn-sm btn-warning" title="Suspend" onclick="return confirm('Are you sure you want to suspend this business?')">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="businesses.php?action=activate&id=<?php echo $business['id']; ?>" class="btn btn-sm btn-success" title="Activate" onclick="return confirm('Are you sure you want to activate this business?')">
                                                        <i class="fas fa-check-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="edit-business.php?id=<?php echo $business['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
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
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm">&laquo; Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filter) ? '&filter=' . urlencode($filter) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        /* Additional styles for business management page */
        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }
        
        .filter-tab {
            padding: 10px 15px;
            color: var(--gray-color);
            border-bottom: 3px solid transparent;
            white-space: nowrap;
        }
        
        .filter-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 600;
        }
        
        .count-badge {
            background-color: #f0f0f0;
            color: var(--gray-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .business-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .business-info img {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            object-fit: cover;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
    </style>
    
    <script src="js/admin.js"></script>
</body>
</html>
