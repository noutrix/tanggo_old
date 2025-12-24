<?php
require_once '../config.php';
require_admin();

$pdo = get_db();
$message = '';

// Handle withdraw actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = (int)($_GET['id'] ?? 0);
    
    if ($action === 'approve' && $id > 0) {
        $stmt = $pdo->prepare("UPDATE withdraws SET status = 'approved' WHERE id = ? AND status = 'pending'");
        if ($stmt->execute([$id])) {
            log_admin_action($_SESSION['admin_id'], 'approve', 'withdraw', $id, "Withdraw request approved");
            $message = 'Withdraw request approved!';
        }
    } elseif ($action === 'reject' && $id > 0) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reason = sanitize($_POST['reason'] ?? '');
            $stmt = $pdo->prepare("UPDATE withdraws SET status = 'rejected', admin_note = ? WHERE id = ? AND status = 'pending'");
            if ($stmt->execute([$reason, $id])) {
                log_admin_action($_SESSION['admin_id'], 'reject', 'withdraw', $id, "Withdraw rejected: $reason");
                $message = 'Withdraw request rejected.';
                header('Location: withdraws.php');
                exit;
            }
        } else {
            // Show reject form
            $stmt = $pdo->prepare("
                SELECT w.*, u.email as user_email 
                FROM withdraws w 
                JOIN users u ON w.user_id = u.id 
                WHERE w.id = ?
            ");
            $stmt->execute([$id]);
            $withdraw = $stmt->fetch();
            
            if ($withdraw) {
                include 'withdraw_reject.php';
                exit;
            }
        }
    } elseif ($action === 'mark_paid' && $id > 0) {
        $stmt = $pdo->prepare("UPDATE withdraws SET status = 'paid' WHERE id = ? AND status = 'approved'");
        if ($stmt->execute([$id])) {
            log_admin_action($_SESSION['admin_id'], 'mark_paid', 'withdraw', $id, "Withdraw marked as paid");
            $message = 'Withdraw marked as paid!';
        }
    }
}

// Get all withdraws with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT w.*, u.email as user_email 
    FROM withdraws w 
    JOIN users u ON w.user_id = u.id 
    ORDER BY w.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute();
$withdraws = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM withdraws");
$stmt->execute();
$total_withdraws = $stmt->fetch()['total'];
$total_pages = ceil($total_withdraws / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - TANGO Admin</title>
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
        
        .badge-pending { background: var(--nike-orange); color: white; }
        .badge-transfer { background: var(--nike-gray-dark); color: white; }
        .badge-fail { background: var(--nike-red); color: white; }
        .badge-completed { background: var(--nike-green); color: white; }
        .badge-approved { background: var(--nike-blue); color: white; }
        .badge-rejected { background: var(--nike-red); color: white; }
        .badge-paid { background: var(--nike-green); color: white; }
        
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
        
        .btn-paid {
            background: var(--nike-blue);
            color: white;
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
                    <a class="admin-nav-link active" href="withdraws.php">
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
                <h2 class="mb-4">Withdrawal Requests</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Wallet Type</th>
                                    <th>Wallet Address</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdraws as $withdraw): ?>
                                    <tr>
                                        <td data-label="ID"><?= $withdraw['id'] ?></td>
                                        <td data-label="User"><?= htmlspecialchars($withdraw['user_email']) ?></td>
                                        <td data-label="Amount"><?= format_usdt($withdraw['amount']) ?></td>
                                        <td data-label="Wallet Type"><?= strtoupper($withdraw['wallet_type']) ?></td>
                                        <td data-label="Wallet Address">
                                            <small><?= htmlspecialchars(substr($withdraw['wallet_address'], 0, 20)) ?>...</small>
                                        </td>
                                        <td data-label="Date"><?= date('M j, Y H:i', strtotime($withdraw['created_at'])) ?></td>
                                        <td data-label="Status">
                                            <span class="badge badge-<?= $withdraw['status'] ?>">
                                                <?= ucfirst($withdraw['status']) ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-buttons">
                                                <?php if ($withdraw['status'] === 'pending'): ?>
                                                    <a href="?action=approve&id=<?= $withdraw['id'] ?>" 
                                                       class="btn-action btn-approve" 
                                                       onclick="return confirm('Approve this withdrawal?')">
                                                        <i class="bi bi-check"></i> Approve
                                                    </a>
                                                    <a href="?action=reject&id=<?= $withdraw['id'] ?>" 
                                                       class="btn-action btn-reject">
                                                        <i class="bi bi-x"></i> Reject
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($withdraw['status'] === 'approved'): ?>
                                                    <a href="?action=mark_paid&id=<?= $withdraw['id'] ?>" 
                                                       class="btn-action btn-paid"
                                                       onclick="return confirm('Mark this withdrawal as paid?')">
                                                        <i class="bi bi-check-circle"></i> Mark Paid
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
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
