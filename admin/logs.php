<?php
require_once '../config.php';
require_admin();

$pdo = get_db();

// Get admin logs with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$filter_target = $_GET['target'] ?? '';
$filter_action = $_GET['action'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_target)) {
    $where_conditions[] = "target_type = ?";
    $params[] = $filter_target;
}

if (!empty($filter_action)) {
    $where_conditions[] = "action = ?";
    $params[] = $filter_action;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT al.*, a.email as admin_email 
    FROM admin_logs al 
    JOIN admins a ON al.admin_id = a.id 
    $where_clause
    ORDER BY al.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM admin_logs $where_clause");
$stmt->execute($params);
$total_logs = $stmt->fetch()['total'];
$total_pages = ceil($total_logs / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - TANGO Admin</title>
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
        
        .log-entry {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--nike-gray-light);
            transition: var(--transition);
        }
        
        .log-entry:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .log-entry.create { border-left-color: var(--nike-green); }
        .log-entry.update { border-left-color: var(--nike-blue); }
        .log-entry.delete { border-left-color: var(--nike-red); }
        .log-entry.approve { border-left-color: var(--nike-green); }
        .log-entry.reject { border-left-color: var(--nike-red); }
        .log-entry.adjust_balance { border-left-color: var(--nike-orange); }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .log-action {
            font-weight: 600;
            color: var(--nike-black);
            text-transform: uppercase;
            font-size: 0.875rem;
        }
        
        .log-timestamp {
            color: var(--nike-gray);
            font-size: 0.75rem;
        }
        
        .log-details {
            color: var(--nike-gray);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .log-admin {
            color: var(--nike-blue);
            font-weight: 500;
            font-size: 0.875rem;
        }
        
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
            .log-header {
                flex-direction: column;
                gap: 0.25rem;
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
                    <a class="admin-nav-link" href="orders.php">
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
                    <a class="admin-nav-link active" href="logs.php">
                        <i class="bi bi-file-text"></i>
                        Logs
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-12 col-md-9 col-lg-10">
                <h2 class="mb-4">Admin Logs</h2>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="filter-row">
                        <div class="filter-group">
                            <label class="form-label">Target Type</label>
                            <select class="form-control" name="target">
                                <option value="">All Targets</option>
                                <option value="user" <?= $filter_target === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="product" <?= $filter_target === 'product' ? 'selected' : '' ?>>Product</option>
                                <option value="order" <?= $filter_target === 'order' ? 'selected' : '' ?>>Order</option>
                                <option value="withdraw" <?= $filter_target === 'withdraw' ? 'selected' : '' ?>>Withdraw</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="form-label">Action</label>
                            <select class="form-control" name="action">
                                <option value="">All Actions</option>
                                <option value="create" <?= $filter_action === 'create' ? 'selected' : '' ?>>Create</option>
                                <option value="update" <?= $filter_action === 'update' ? 'selected' : '' ?>>Update</option>
                                <option value="delete" <?= $filter_action === 'delete' ? 'selected' : '' ?>>Delete</option>
                                <option value="approve" <?= $filter_action === 'approve' ? 'selected' : '' ?>>Approve</option>
                                <option value="reject" <?= $filter_action === 'reject' ? 'selected' : '' ?>>Reject</option>
                                <option value="adjust_balance" <?= $filter_action === 'adjust_balance' ? 'selected' : '' ?>>Adjust Balance</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="bi bi-funnel me-2"></i>Filter
                            </button>
                        </div>
                        
                        <?php if ($filter_target || $filter_action): ?>
                            <div class="filter-group">
                                <label class="form-label">&nbsp;</label>
                                <a href="logs.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-x-circle me-2"></i>Clear
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Logs List -->
                <?php if (empty($logs)): ?>
                    <div class="card">
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: var(--nike-gray);"></i>
                            <p class="mt-3 text-muted">No logs found matching your criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry <?= htmlspecialchars($log['action']) ?>">
                            <div class="log-header">
                                <div>
                                    <span class="log-action"><?= htmlspecialchars($log['action']) ?></span>
                                    <span class="text-muted">on <?= htmlspecialchars(ucfirst($log['target_type'])) ?> #<?= $log['target_id'] ?></span>
                                </div>
                                <div class="log-timestamp">
                                    <?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?>
                                </div>
                            </div>
                            
                            <?php if ($log['details']): ?>
                                <div class="log-details">
                                    <?= htmlspecialchars($log['details']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="log-admin">
                                <i class="bi bi-person-fill me-1"></i>
                                <?= htmlspecialchars($log['admin_email']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&target=<?= $filter_target ?>&action=<?= $filter_action ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&target=<?= $filter_target ?>&action=<?= $filter_action ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&target=<?= $filter_target ?>&action=<?= $filter_action ?>">
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
