<?php
require_once '../config.php';
require_admin();

$pdo = get_db();
$message = '';

// Handle balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        $message = 'Invalid request.';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'adjust_balance') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $amount_change = floatval($_POST['amount_change'] ?? 0);
            $reason = sanitize($_POST['reason'] ?? '');
            
            if ($user_id > 0 && $amount_change != 0 && !empty($reason)) {
                $pdo->beginTransaction();
                try {
                    // Get current balance
                    $stmt = $pdo->prepare("SELECT balance_usdt FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        throw new Exception('User not found');
                    }
                    
                    $balance_before = $user['balance_usdt'];
                    $balance_after = $balance_before + $amount_change;
                    
                    if ($balance_after < 0) {
                        throw new Exception('Insufficient balance for negative adjustment');
                    }
                    
                    // Update user balance
                    $stmt = $pdo->prepare("UPDATE users SET balance_usdt = ? WHERE id = ?");
                    $stmt->execute([$balance_after, $user_id]);
                    
                    // Log balance change
                    $stmt = $pdo->prepare("
                        INSERT INTO balance_logs (user_id, amount_change, balance_before, balance_after, reason, admin_id)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $amount_change, $balance_before, $balance_after, $reason, $_SESSION['admin_id']]);
                    
                    log_admin_action($_SESSION['admin_id'], 'adjust_balance', 'user', $user_id, "Balance adjusted: $amount_change USDT, Reason: $reason");
                    
                    $pdo->commit();
                    $message = 'Balance adjusted successfully!';
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    $message = 'Error: ' . $e->getMessage();
                }
            } else {
                $message = 'Please fill in all fields correctly.';
            }
        }
    }
}

// Get all users with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND status = 'completed') as total_orders,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND DATE(created_at) = CURDATE() AND status = 'completed') as today_orders
    FROM users u 
    ORDER BY u.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$total_users = $stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - TANGO Admin</title>
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
        
        .user-card {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .user-info h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--nike-black);
        }
        
        .user-email {
            color: var(--nike-gray);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.5rem;
            background: var(--nike-gray-light);
            border-radius: 6px;
        }
        
        .stat-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--nike-orange);
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--nike-gray);
            margin-top: 0.25rem;
        }
        
        .balance-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--nike-green);
            text-align: center;
            margin: 1rem 0;
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
        
        .btn-adjust {
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
        
        @media (max-width: 768px) {
            .user-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .user-stats {
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
                    <a class="admin-nav-link" href="orders.php">
                        <i class="bi bi-list-check"></i>
                        Orders
                    </a>
                    <a class="admin-nav-link" href="withdraws.php">
                        <i class="bi bi-wallet2"></i>
                        Withdrawals
                    </a>
                    <a class="admin-nav-link active" href="users.php">
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
                <h2 class="mb-4">User Management</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Users List -->
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-info">
                                <h4>User #<?= $user['id'] ?></h4>
                                <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                <div class="user-email">Joined: <?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                            </div>
                            <div class="action-buttons">
                                <button class="btn-action btn-adjust" onclick="openBalanceModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>', <?= $user['balance_usdt'] ?>)">
                                    <i class="bi bi-wallet2"></i> Adjust Balance
                                </button>
                            </div>
                        </div>
                        
                        <div class="user-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= $user['total_orders'] ?></span>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= $user['today_orders'] ?></span>
                                <div class="stat-label">Today Orders</div>
                            </div>
                        </div>
                        
                        <div class="balance-display">
                            <?= format_usdt($user['balance_usdt']) ?> USDT
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
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

    <!-- Balance Adjustment Modal -->
    <div class="modal" id="balanceModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust User Balance</h5>
                    <button class="btn-close" onclick="closeBalanceModal()"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>User:</strong> <span id="modalUserEmail"></span>
                    </div>
                    <div class="mb-3">
                        <strong>Current Balance:</strong> <span id="modalCurrentBalance"></span>
                    </div>
                    
                    <form id="balanceForm" method="POST">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="adjust_balance">
                        <input type="hidden" id="modalUserId" name="user_id">
                        
                        <div class="form-group">
                            <label class="form-label">Amount Change (USDT)</label>
                            <input type="number" class="form-control" id="amountChange" name="amount_change" 
                                   step="0.01" required placeholder="Use positive for credit, negative for debit">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required
                                      placeholder="Reason for balance adjustment"></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>New Balance:</strong> <span id="newBalance">0.00</span> USDT
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeBalanceModal()">Cancel</button>
                    <button type="submit" form="balanceForm" class="btn btn-orange">Adjust Balance</button>
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

        // Balance adjustment functions
        function openBalanceModal(userId, userEmail, currentBalance) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUserEmail').textContent = userEmail;
            document.getElementById('modalCurrentBalance').textContent = formatUSDT(currentBalance);
            document.getElementById('amountChange').value = '';
            document.getElementById('reason').value = '';
            updateNewBalance();
            
            document.getElementById('balanceModal').classList.add('show');
        }

        function closeBalanceModal() {
            document.getElementById('balanceModal').classList.remove('show');
        }

        function updateNewBalance() {
            const currentBalance = parseFloat(document.getElementById('modalCurrentBalance').textContent.replace(/[^0-9.-]/g, ''));
            const amountChange = parseFloat(document.getElementById('amountChange').value) || 0;
            const newBalance = currentBalance + amountChange;
            document.getElementById('newBalance').textContent = formatUSDT(newBalance);
        }

        function formatUSDT(amount) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        // Event listeners
        document.getElementById('amountChange').addEventListener('input', updateNewBalance);

        // Close modal when clicking outside
        document.getElementById('balanceModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeBalanceModal();
            }
        });
    </script>
</body>
</html>
