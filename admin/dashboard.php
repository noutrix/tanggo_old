<?php
require_once '../config.php';
require_admin();

$pdo = get_db();

// Get dashboard stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
$stmt->execute();
$total_users = $stmt->fetch()['total_users'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_groups FROM orders WHERE status = 'completed'");
$stmt->execute();
$total_groups = $stmt->fetch()['total_groups'];

$stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'pending'");
$stmt->execute();
$pending_orders = $stmt->fetch()['pending_orders'];

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_paid FROM orders WHERE status = 'completed'");
$stmt->execute();
$total_paid = $stmt->fetch()['total_paid'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as pending_withdraws FROM withdraws WHERE status = 'pending'");
$stmt->execute();
$pending_withdraws = $stmt->fetch()['pending_withdraws'];

// Get recent orders
$stmt = $pdo->prepare("
    SELECT o.*, u.email as user_email, p.name as product_name, p.year_range 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    JOIN products p ON o.product_id = p.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Get recent withdraws
$stmt = $pdo->prepare("
    SELECT w.*, u.email as user_email 
    FROM withdraws w 
    JOIN users u ON w.user_id = u.id 
    ORDER BY w.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_withdraws = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TANGO Admin</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1rem;
        }
        
        .stat-icon.users { background: rgba(53, 112, 242, 0.1); color: var(--nike-blue); }
        .stat-icon.groups { background: rgba(255, 124, 76, 0.1); color: var(--nike-orange); }
        .stat-icon.orders { background: rgba(255, 193, 7, 0.1); color: var(--nike-amber); }
        .stat-icon.paid { background: rgba(16, 185, 129, 0.1); color: var(--nike-green); }
        .stat-icon.withdraws { background: rgba(239, 68, 68, 0.1); color: var(--nike-red); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--nike-black);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--nike-gray);
            font-weight: 500;
        }
        
        .recent-section {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--nike-black);
            margin: 0;
        }
        
        .view-all-link {
            color: var(--nike-orange);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .view-all-link:hover {
            color: var(--nike-orange-dark);
        }
        
        .recent-item {
            padding: 1rem;
            border-bottom: 1px solid var(--nike-gray-light);
            transition: var(--transition);
        }
        
        .recent-item:hover {
            background: var(--nike-gray-light);
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .recent-title {
            font-weight: 600;
            color: var(--nike-black);
            margin: 0;
        }
        
        .recent-meta {
            font-size: 0.75rem;
            color: var(--nike-gray);
            margin-bottom: 0.5rem;
        }
        
        .recent-amount {
            font-weight: 700;
            color: var(--nike-orange);
        }
        
        .badge-pending { background: var(--nike-orange); color: white; }
        .badge-transfer { background: var(--nike-gray-dark); color: white; }
        .badge-fail { background: var(--nike-red); color: white; }
        .badge-completed { background: var(--nike-green); color: white; }
        .badge-approved { background: var(--nike-blue); color: white; }
        .badge-rejected { background: var(--nike-red); color: white; }
        .badge-paid { background: var(--nike-green); color: white; }
        
        @media (max-width: 768px) {
            .recent-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
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
                    <a class="admin-nav-link active" href="dashboard.php">
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
                    <a class="admin-nav-link" href="logs.php">
                        <i class="bi bi-file-text"></i>
                        Logs
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-12 col-md-9 col-lg-10">
                <h2 class="mb-4">Admin Dashboard</h2>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon users">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-number"><?= number_format($total_users) ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon groups">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <div class="stat-number"><?= number_format($total_groups) ?></div>
                        <div class="stat-label">Total Groups Sold</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orders">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                        <div class="stat-number"><?= number_format($pending_orders) ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon paid">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div class="stat-number"><?= format_usdt($total_paid) ?></div>
                        <div class="stat-label">Total Paid (USDT)</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon withdraws">
                            <i class="bi bi-wallet-fill"></i>
                        </div>
                        <div class="stat-number"><?= number_format($pending_withdraws) ?></div>
                        <div class="stat-label">Pending Withdraws</div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="recent-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Orders</h3>
                        <a href="orders.php" class="view-all-link">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($recent_orders)): ?>
                        <div class="text-center py-3">
                            <p class="text-muted">No recent orders found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="recent-item">
                                <div class="recent-header">
                                    <div>
                                        <div class="recent-title"><?= htmlspecialchars($order['product_name']) ?></div>
                                        <div class="recent-meta">
                                            <?= htmlspecialchars($order['user_email']) ?> • 
                                            <?= date('M j, Y H:i', strtotime($order['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="recent-amount"><?= format_usdt($order['total_amount']) ?></div>
                                        <span class="badge badge-<?= $order['status'] ?>">
                                            <?= ucfirst($order['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Withdraws -->
                <div class="recent-section">
                    <div class="section-header">
                        <h3 class="section-title">Recent Withdrawals</h3>
                        <a href="withdraws.php" class="view-all-link">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($recent_withdraws)): ?>
                        <div class="text-center py-3">
                            <p class="text-muted">No recent withdrawals found.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_withdraws as $withdraw): ?>
                            <div class="recent-item">
                                <div class="recent-header">
                                    <div>
                                        <div class="recent-title"><?= strtoupper($withdraw['wallet_type']) ?></div>
                                        <div class="recent-meta">
                                            <?= htmlspecialchars($withdraw['user_email']) ?> • 
                                            <?= date('M j, Y H:i', strtotime($withdraw['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="recent-amount"><?= format_usdt($withdraw['amount']) ?></div>
                                        <span class="badge badge-<?= $withdraw['status'] ?>">
                                            <?= ucfirst($withdraw['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
