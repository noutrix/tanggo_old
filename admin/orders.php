<?php
require_once '../config.php';
require_admin();

$pdo = get_db();
$message = '';

// Handle order actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = (int)($_GET['id'] ?? 0);

    if ($action === 'approve' && $id > 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_username'])) {
            $transfer_username = trim($_POST['transfer_username']);
            $csrf = $_POST['csrf'] ?? '';
            if (!verify_csrf($csrf)) {
                $message = 'Invalid request.';
            } elseif (empty($transfer_username)) {
                $message = 'Please enter the username for transfer.';
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'transfer_owner', admin_note = ? WHERE id = ? AND status = 'pending'");
                $result = $stmt->execute(["Transfer to owner: $transfer_username", $id]);
                if ($result && $stmt->rowCount() > 0) {
                    log_admin_action($_SESSION['admin_id'], 'approve', 'order', $id, "Order approved and transferred to: $transfer_username");
                    header('Location: orders.php?message=Order approved and transferred successfully');
                    exit;
                } else {
                    $message = 'Failed to approve order. Order may not be in pending status.';
                }
            }
        } else {
            // Show approve confirmation form
            $stmt = $pdo->prepare("
                SELECT o.*, u.email as user_email, p.name as product_name, p.year_range 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                JOIN products p ON o.product_id = p.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if ($order) {
                include 'order_approve.php';
                exit;
            }
        }
    } elseif ($action === 'reject' && $id > 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reason = sanitize($_POST['reason'] ?? '');
            $csrf = $_POST['csrf'] ?? '';
            if (!verify_csrf($csrf)) {
                $message = 'Invalid request.';
            } elseif (empty($reason)) {
                $message = 'Please provide a reason for rejection.';
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'fail', admin_note = ? WHERE id = ? AND status = 'pending'");
                $result = $stmt->execute([$reason, $id]);
                if ($result && $stmt->rowCount() > 0) {
                    log_admin_action($_SESSION['admin_id'], 'reject', 'order', $id, "Order rejected: $reason");
                    header('Location: orders.php?message=Order rejected successfully');
                    exit;
                } else {
                    $message = 'Failed to reject order. Order may not be in pending status.';
                }
            }
        } else {
            // Show reject confirmation form
            $stmt = $pdo->prepare("
                SELECT o.*, u.email as user_email, p.name as product_name, p.year_range 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                JOIN products p ON o.product_id = p.id 
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if ($order) {
                include 'order_reject.php';
                exit;
            }
        }
    } elseif ($action === 'complete' && $id > 0) {
        // Get order details before updating
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'transfer_owner'");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Update order status to completed
                $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'transfer_owner'");
                $stmt->execute([$id]);
                
                // Add order amount to user balance
                $stmt = $pdo->prepare("UPDATE users SET balance_usdt = balance_usdt + ? WHERE id = ?");
                $stmt->execute([$order['total_amount'], $order['user_id']]);
                
                // Log the completion action
                log_admin_action($_SESSION['admin_id'], 'complete', 'order', $id, "Order completed and {$order['total_amount']} USDT added to user balance");
                
                // Commit transaction
                $pdo->commit();
                
                header('Location: orders.php?message=Order completed successfully and payment added to user account');
                exit;
            } catch (Exception $e) {
                // Rollback on error
                $pdo->rollback();
                $message = 'Failed to complete order. Please try again.';
            }
        } else {
            $message = 'Failed to complete order. Order may not be in transfer_owner status.';
        }
    }
}

// Handle URL message parameter
if (isset($_GET['message'])) {
    $message = sanitize($_GET['message']);
}

// Get all orders with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$filter_status = $_GET['status'] ?? '';
$where_conditions = [];
$params = [];

if (!empty($filter_status)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $filter_status;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT o.*, u.email as user_email, p.name as product_name, p.year_range 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    $where_clause
    ORDER BY o.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders o $where_clause");
$stmt->execute($params);
$total_orders = $stmt->fetch()['total'];
$total_pages = ceil($total_orders / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - TANGO Admin</title>
    <link rel="stylesheet" href="../assets/mobile-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .admin-sidebar {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
        }
        
        .admin-nav-link {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: var(--nike-black);
            text-decoration: none;
            transition: var(--transition);
            border-radius: 8px;
            margin: 0.25rem;
        }
        
        .admin-nav-link:hover,
        .admin-nav-link.active {
            background: var(--nike-gray-light);
            color: var(--nike-orange);
        }
        
        .admin-nav-link i {
            margin-right: 0.75rem;
        }
        
        @media (min-width: 768px) {
            .admin-sidebar {
                position: sticky;
                top: 80px;
                margin-bottom: 0;
            }
            
            .admin-nav-link {
                margin: 0.5rem 0;
            }
        }
        
        .filter-section {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
        }
        
        .order-card {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--nike-gray-light);
            transition: var(--transition);
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .order-card.pending { border-left-color: var(--nike-orange); }
        .order-card.transfer_owner { border-left-color: var(--nike-blue); }
        .order-card.completed { border-left-color: var(--nike-green); }
        .order-card.rejected { border-left-color: var(--nike-red); }
        
        .group-links-container {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            border: 1px solid #e9ecef;
        }
        
        .group-link-item a {
            display: inline-flex;
            align-items: center;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .group-link-item a:hover {
            background-color: #007bff;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
        }
        
        .group-link-item a i {
            margin-right: 5px;
            font-size: 0.8rem;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .order-info h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--nike-black);
        }
        
        .order-meta {
            color: var(--nike-gray);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            text-align: center;
            padding: 0.5rem;
            background: var(--nike-gray-light);
            border-radius: 6px;
        }
        
        .detail-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--nike-orange);
            display: block;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: var(--nike-gray);
            margin-top: 0.25rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-approve {
            background: var(--nike-green);
            color: white;
        }
        
        .btn-reject {
            background: var(--nike-red);
            color: white;
        }
        
        .btn-complete {
            background: var(--nike-blue);
            color: white;
        }
        
        .badge-pending { background: var(--nike-orange); color: white; }
        .badge-transfer { background: var(--nike-blue); color: white; }
        .badge-completed { background: var(--nike-green); color: white; }
        .badge-rejected { background: var(--nike-red); color: white; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            border: 1px solid var(--nike-gray-light);
            color: var(--nike-black);
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background: var(--nike-orange);
            color: white;
            border-color: var(--nike-orange);
        }
        
        .pagination .current {
            background: var(--nike-orange);
            color: white;
            border-color: var(--nike-orange);
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .order-details {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-gear-fill"></i>
                TANGO Admin
            </a>
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="bi bi-list"></i>
            </button>
            <div class="navbar-nav ms-auto" id="navbarNav">
                <span class="nav-link">Admin: <?= htmlspecialchars($_SESSION['admin_email']) ?></span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <!-- Admin Sidebar -->
            <div class="col-12 col-md-3 col-lg-2">
                <div class="admin-sidebar">
                    <a class="admin-nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                    <a class="admin-nav-link" href="products.php">
                        <i class="bi bi-box-seam"></i>
                        Products
                    </a>
                    <a class="admin-nav-link active" href="orders.php">
                        <i class="bi bi-list-check"></i>
                        Orders
                    </a>
                    <a class="admin-nav-link" href="withdraws.php">
                        <i class="bi bi-wallet2"></i>
                        Withdrawals
                    </a>
                    <a class="admin-nav-link" href="users.php">
                        <i class="bi bi-people"></i>
                        Users
                    </a>
                    <a class="admin-nav-link" href="logs.php">
                        <i class="bi bi-file-text"></i>
                        Logs
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-12 col-md-9 col-lg-10">
                <h2 class="mb-4">Order Management</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="transfer_owner" <?= $filter_status === 'transfer_owner' ? 'selected' : '' ?>>Transfer Owner</option>
                                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="bi bi-funnel me-2"></i>Filter
                            </button>
                        </div>
                        
                        <?php if ($filter_status): ?>
                            <div class="filter-group">
                                <label class="form-label">&nbsp;</label>
                                <a href="orders.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle me-2"></i>Clear
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Orders List -->
                <?php if (empty($orders)): ?>
                    <div class="card">
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: var(--nike-gray);"></i>
                            <p class="mt-3 text-muted">No orders found matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card <?= htmlspecialchars($order['status']) ?>">
                            <div class="order-header">
                                <div class="order-info">
                                    <h4><?= htmlspecialchars($order['product_name']) ?></h4>
                                    <div class="order-meta">
                                        <?= htmlspecialchars($order['user_email']) ?> • 
                                        <?= date('M j, Y H:i', strtotime($order['created_at'])) ?>
                                    </div>
                                    <div class="order-meta">
                                        Task: <?= htmlspecialchars($order['task_name']) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge badge-<?= $order['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="order-details">
                                <div class="detail-item">
                                    <span class="detail-value"><?= $order['group_count'] ?></span>
                                    <div class="detail-label">Groups</div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-value"><?= format_usdt($order['total_amount']) ?></span>
                                    <div class="detail-label">Amount</div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-value"><?= htmlspecialchars($order['year_range']) ?></span>
                                    <div class="detail-label">Year Range</div>
                                </div>
                            </div>
                            
                            <?php if ($order['admin_note']): ?>
                                <div class="alert alert-info mt-2">
                                    <strong>Note:</strong> <?= htmlspecialchars($order['admin_note']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($order['group_links'])): ?>
                                <div class="mt-3">
                                    <h6 class="text-muted mb-2">
                                        <i class="bi bi-link-45deg me-1"></i>
                                        Telegram Group Links:
                                    </h6>
                                    <div class="group-links-container">
                                        <?php 
                                        $links = explode("\n", $order['group_links']);
                                        foreach ($links as $link): 
                                            $link = trim($link);
                                            if (!empty($link)): 
                                        ?>
                                            <div class="group-link-item mb-1">
                                                <a href="<?= htmlspecialchars($link) ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-outline-primary me-1 mb-1"
                                                   title="Open in new tab">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                    <?= htmlspecialchars($link) ?>
                                                </a>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <a href="?action=approve&id=<?= $order['id'] ?>" class="btn-action btn-approve">
                                        <i class="bi bi-check"></i> Approve
                                    </a>
                                    <a href="?action=reject&id=<?= $order['id'] ?>" class="btn-action btn-reject">
                                        <i class="bi bi-x"></i> Reject
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'transfer_owner'): ?>
                                    <a href="?action=complete&id=<?= $order['id'] ?>" 
                                       class="btn-action btn-complete"
                                       onclick="return confirm('Mark this order as completed?')">
                                        <i class="bi bi-check-circle"></i> Complete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= $filter_status ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&status=<?= $filter_status ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= $filter_status ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const navbar = document.getElementById('navbarNav');
            if (navbar.style.display === 'flex') {
                navbar.style.display = 'none';
            } else {
                navbar.style.display = 'flex';
                navbar.style.position = 'absolute';
                navbar.style.top = '100%';
                navbar.style.left = '0';
                navbar.style.right = '0';
                navbar.style.background = 'var(--nike-black)';
                navbar.style.flexDirection = 'column';
                navbar.style.padding = '1rem';
                navbar.style.boxShadow = 'var(--shadow-md)';
            }
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navbar = document.getElementById('navbarNav');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (!navbar.contains(event.target) && !toggle.contains(event.target)) {
                navbar.style.display = 'none';
            }
        });
    </script>
</body>
</html>
