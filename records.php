<?php
require_once 'config.php';
require_login();

$pdo = get_db();
$user_id = $_SESSION['user_id'];

// Get user balance
$stmt = $pdo->prepare("SELECT balance_usdt FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$balance = $user['balance_usdt'];

// Get all orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.year_range 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Get all withdraws
$stmt = $pdo->prepare("
    SELECT * FROM withdraws 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$withdraws = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1E88E5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TANGO">
    <title>Records - TANGO – Old Group Buy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #1E88E5;
            --primary-dark: #1565C0;
            --primary-light: #42A5F5;
            --secondary: #E3F2FD;
            --accent: #00BCD4;
            --dark: #0D47A1;
            --amber: #FFC107;
            --teal: #009688;
            --red: #F44336;
            --success: #4CAF50;
            --surface: #FFFFFF;
            --surface-variant: #F8F9FA;
            --background: #F5F7FA;
            --text-primary: #212121;
            --text-secondary: #757575;
            --border: #E0E0E0;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }
        
        body {
            background: var(--background);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            color: var(--text-primary);
            line-height: 1.5;
            overscroll-behavior: contain;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Premium Navigation */
        .navbar {
            background: var(--surface);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 0;
            height: 60px;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all var(--transition-fast);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary) !important;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .balance-badge {
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 6px 12px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.875rem;
            border: 1px solid var(--primary-light);
        }
        
        .nav-action-btn {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }
        
        .nav-action-btn:hover {
            background: var(--surface-variant);
            color: var(--primary);
        }
        
        .nav-action-btn:active {
            transform: scale(0.95);
            background: var(--primary);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            padding: 20px 0 80px 0;
            min-height: 100vh;
        }
        
        /* Classic Header - Traditional Style */
        .page-header {
            background: linear-gradient(to right, #2c3e50, #34495e);
            color: white;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #1a252f;
            position: relative;
        }
        
        .page-header h2 {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 1px;
            position: relative;
            font-family: Georgia, 'Times New Roman', serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .page-header p {
            opacity: 0.9;
            font-size: 0.7rem;
            margin: 0;
            position: relative;
            font-style: italic;
        }
        
        /* Premium Tabs - Extra Small (Mobile Optimized) */
        .premium-tabs {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 2px;
            margin-bottom: 8px;
            box-shadow: var(--shadow-sm);
            display: flex;
            gap: 2px;
        }
        
        .premium-tab {
            flex: 1;
            background: transparent;
            border: none;
            padding: 4px 6px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            color: var(--text-secondary);
            transition: all var(--transition-fast);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            font-size: 0.7rem;
        }
        
        .premium-tab:hover {
            background: var(--surface-variant);
        }
        
        .premium-tab.active {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        .premium-tab:active {
            transform: scale(0.98);
        }
        
        /* Premium Cards - Extra Small (Mobile Optimized) */
        .premium-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 6px;
            overflow: hidden;
            transition: all var(--transition-normal);
            border: 1px solid var(--border);
        }
        
        .premium-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .premium-card:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }
        
        /* Mobile Table Cards - Extra Small (Mobile Optimized) */
        .table-card {
            padding: 6px;
        }
        
        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
        }
        
        .table-card-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.75rem;
        }
        
        .table-card-subtitle {
            color: var(--text-secondary);
            font-size: 0.6rem;
            margin-top: 1px;
        }
        
        .table-card-status {
            flex-shrink: 0;
        }
        
        .table-card-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            margin-bottom: 4px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.6rem;
            color: var(--text-secondary);
            margin-bottom: 1px;
            text-transform: uppercase;
            letter-spacing: 0.1px;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.7rem;
        }
        
        .table-card-footer {
            margin-top: 3px;
        }
        
        /* Premium Status Badges - Extra Small (Mobile Optimized) */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 2px 4px;
            border-radius: var(--radius-sm);
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1px;
            border: 1px solid transparent;
            transition: all var(--transition-fast);
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border-color: #e67e22;
            box-shadow: 0 2px 4px rgba(243, 156, 18, 0.3);
        }
        
        .status-completed {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            border-color: #27ae60;
            box-shadow: 0 2px 4px rgba(39, 174, 96, 0.3);
        }
        
        .status-fail {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border-color: #e74c3c;
            box-shadow: 0 2px 4px rgba(231, 76, 60, 0.3);
        }
        
        .status-transfer_owner {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            border-color: #9b59b6;
            box-shadow: 0 2px 4px rgba(155, 89, 182, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .empty-state h5 {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .empty-state p {
            font-size: 0.875rem;
            margin-bottom: 20px;
        }
        
        /* Premium Button */
        .premium-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.875rem;
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .premium-btn:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .premium-btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }
        
        /* Classic Footer - Traditional Style */
        .classic-footer {
            background: linear-gradient(to bottom, #2c3e50, #1a252f);
            color: #ecf0f1;
            padding: 30px 0 20px 0;
            margin-top: 40px;
            border-top: 4px solid #3498db;
            font-family: Georgia, 'Times New Roman', serif;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
        }
        
        .footer-section {
            text-align: center;
        }
        
        .footer-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #3498db;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: Georgia, 'Times New Roman', serif;
        }
        
        .footer-description {
            font-size: 0.85rem;
            line-height: 1.4;
            color: #bdc3c7;
            font-style: italic;
        }
        
        .footer-subtitle {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #ecf0f1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer-contact {
            font-size: 0.8rem;
            color: #bdc3c7;
            margin: 0;
        }
        
        .footer-contact i {
            margin-right: 5px;
            color: #3498db;
        }
        
        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .footer-link {
            color: #bdc3c7;
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.3s ease;
        }
        
        .footer-link:hover {
            color: #3498db;
            text-decoration: underline;
        }
        
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid #34495e;
        }
        
        .footer-copyright p,
        .footer-trademark p {
            font-size: 0.75rem;
            margin: 0;
            color: #95a5a6;
        }
        
        .footer-copyright p {
            font-style: italic;
        }
        
        .footer-trademark p {
            text-align: right;
        }
        
        /* Mobile Footer Styles */
        @media (max-width: 768px) {
            .classic-footer {
                padding: 20px 0 15px 0;
                margin-top: 30px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 20px;
                text-align: center;
            }
            
            .footer-section {
                text-align: center;
            }
            
            .footer-title {
                font-size: 1rem;
            }
            
            .footer-description {
                font-size: 0.8rem;
            }
            
            .footer-subtitle {
                font-size: 0.85rem;
            }
            
            .footer-contact {
                font-size: 0.75rem;
            }
            
            .footer-links {
                align-items: center;
            }
            
            .footer-link {
                font-size: 0.75rem;
            }
            
            .footer-bottom {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .footer-trademark p {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .classic-footer {
                padding: 15px 0 10px 0;
                margin-top: 20px;
            }
            
            .footer-content {
                gap: 15px;
            }
            
            .footer-title {
                font-size: 0.9rem;
            }
            
            .footer-description {
                font-size: 0.75rem;
            }
            
            .footer-subtitle {
                font-size: 0.8rem;
            }
            
            .footer-contact {
                font-size: 0.7rem;
            }
            
            .footer-link {
                font-size: 0.7rem;
            }
            
            .footer-copyright p,
            .footer-trademark p {
                font-size: 0.7rem;
            }
        }
        
        /* Container */
        .container {
            max-width: 100%;
            padding: 0 16px;
        }
        
        /* Hide desktop table on mobile */
        .desktop-table {
            display: none;
        }
        
        /* Performance optimizations */
        .gpu-accelerated {
            transform: translateZ(0);
            backface-visibility: hidden;
            perspective: 1000px;
        }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: var(--radius-md);
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Mobile-first responsive */
        @media (min-width: 768px) {
            .container {
                max-width: 768px;
                margin: 0 auto;
            }
            
            .desktop-table {
                display: table;
            }
            
            .mobile-cards {
                display: none;
            .premium-tab, .premium-btn, .premium-card {
                touch-action: manipulation;
            }
            
            .premium-tab:active, .premium-btn:active {
                transform: scale(0.98);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-lightning-charge-fill"></i>
                    TANGO
                </a>
                <div class="navbar-actions">
                    <div class="balance-badge"><?= format_usdt($balance) ?> USDT</div>
                    <button class="nav-action-btn" onclick="window.location.href='dashboard.php'">
                        <i class="bi bi-house"></i>
                    </button>
                    <button class="nav-action-btn" onclick="window.location.href='withdraw.php'">
                        <i class="bi bi-wallet2"></i>
                    </button>
                    <button class="nav-action-btn" onclick="window.location.href='logout.php'">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <div class="page-header gpu-accelerated">
            <h2>Your Records</h2>
            <p>Track your order history and withdrawal requests</p>
        </div>

        <div class="premium-tabs gpu-accelerated">
            <button class="premium-tab active" onclick="switchTab('orders')">
                <i class="bi bi-box-seam"></i>
                Orders
            </button>
            <button class="premium-tab" onclick="switchTab('withdraws')">
                <i class="bi bi-wallet2"></i>
                Withdrawals
            </button>
        </div>

        <div id="orders-content" class="tab-content">
            <?php if (!empty($orders)): ?>
                <div class="mobile-cards">
                    <?php foreach ($orders as $order): ?>
                        <div class="premium-card gpu-accelerated">
                            <div class="table-card">
                                <div class="table-card-header">
                                    <div>
                                        <div class="table-card-title"><?= htmlspecialchars($order['task_name']) ?></div>
                                        <div class="table-card-subtitle"><?= htmlspecialchars($order['product_name']) ?> • <?= htmlspecialchars($order['year_range']) ?></div>
                                    </div>
                                    <div class="table-card-status">
                                        <?php 
                                        if ($order['status'] === 'transfer_owner') {
                                            echo '<div class="status-badge status-transfer_owner">';
                                            if ($order['admin_note'] && strpos($order['admin_note'], 'Transfer to owner:') === 0) {
                                                $username = str_replace('Transfer to owner: ', '', $order['admin_note']);
                                                echo "Transfer to $username";
                                            } else {
                                                echo 'Transfer Owner';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<div class="status-badge status-' . $order['status'] . '">' . ucfirst($order['status']) . '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="table-card-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Groups</div>
                                        <div class="detail-value"><?= $order['group_count'] ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Price/Group</div>
                                        <div class="detail-value">$<?= number_format($order['price_per_group'], 2) ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Bonus</div>
                                        <div class="detail-value">
                                            <?php if ($order['bonus_per_group'] > 0): ?>
                                                +$<?= number_format($order['bonus_per_group'], 2) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Total</div>
                                        <div class="detail-value fw-bold"><?= format_usdt($order['total_amount']) ?></div>
                                    </div>
                                </div>
                                <div class="table-card-footer">
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                        <i class="bi bi-clock"></i> <?= date('M j, Y H:i', strtotime($order['created_at'])) ?>
                                    </div>
                                    <?php if ($order['admin_note']): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">
                                            <i class="bi bi-info-circle"></i> <?= htmlspecialchars($order['admin_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="desktop-table">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Task Name</th>
                                    <th>Product</th>
                                    <th>Groups</th>
                                    <th>Price/Group</th>
                                    <th>Bonus</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td data-label="Date"><?= date('M j, Y H:i', strtotime($order['created_at'])) ?></td>
                                        <td data-label="Task Name"><?= htmlspecialchars($order['task_name']) ?></td>
                                        <td data-label="Product">
                                            <div><?= htmlspecialchars($order['product_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($order['year_range']) ?></small>
                                        </td>
                                        <td data-label="Groups"><?= $order['group_count'] ?></td>
                                        <td data-label="Price/Group">$<?= number_format($order['price_per_group'], 2) ?></td>
                                        <td data-label="Bonus">
                                            <?php if ($order['bonus_per_group'] > 0): ?>
                                                <span class="text-success">+$<?= number_format($order['bonus_per_group'], 2) ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Total" class="fw-bold"><?= format_usdt($order['total_amount']) ?></td>
                                        <td data-label="Status">
                                            <?php 
                                            if ($order['status'] === 'transfer_owner') {
                                                echo '<span style="background: white; color: #0000FF; border: 3px solid #0000FF; font-weight: bold; padding: 4px 8px; display: inline-block; border-radius: 6px; box-shadow: 0 2px 4px rgba(0, 0, 255, 0.3);">';
                                                if ($order['admin_note'] && strpos($order['admin_note'], 'Transfer to owner:') === 0) {
                                                    $username = str_replace('Transfer to owner: ', '', $order['admin_note']);
                                                    echo "Transfer to owner to $username";
                                                } else {
                                                    echo 'Transfer Owner';
                                                }
                                                echo '</span>';
                                            } else {
                                                echo '<span class="badge badge-' . $order['status'] . '">' . ucfirst($order['status']) . '</span>';
                                            }
                                            ?>
                                            <?php if ($order['admin_note']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($order['admin_note']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-box-seam"></i>
                    <h5>No Orders Yet</h5>
                    <p>You haven't submitted any orders yet.</p>
                    <button class="premium-btn" onclick="window.location.href='dashboard.php'">
                        <i class="bi bi-plus-circle"></i>
                        Start Selling
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div id="withdraws-content" class="tab-content" style="display: none;">
            <?php if (!empty($withdraws)): ?>
                <div class="mobile-cards">
                    <?php foreach ($withdraws as $withdraw): ?>
                        <div class="premium-card gpu-accelerated">
                            <div class="table-card">
                                <div class="table-card-header">
                                    <div>
                                        <div class="table-card-title"><?= format_usdt($withdraw['amount']) ?> USDT</div>
                                        <div class="table-card-subtitle"><?= htmlspecialchars($withdraw['wallet_type']) ?></div>
                                    </div>
                                    <div class="table-card-status">
                                        <div class="status-badge status-<?= $withdraw['status'] ?>"><?= ucfirst($withdraw['status']) ?></div>
                                    </div>
                                </div>
                                <div class="table-card-details">
                                    <div class="detail-item" style="grid-column: 1 / -1;">
                                        <div class="detail-label">Wallet Address</div>
                                        <div class="detail-value" style="word-break: break-all; font-family: monospace; font-size: 0.8rem;">
                                            <?= htmlspecialchars($withdraw['wallet_address']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-card-footer">
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                        <i class="bi bi-clock"></i> <?= date('M j, Y H:i', strtotime($withdraw['created_at'])) ?>
                                    </div>
                                    <?php if ($withdraw['admin_note']): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;">
                                            <i class="bi bi-info-circle"></i> <?= htmlspecialchars($withdraw['admin_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="desktop-table">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Wallet Type</th>
                                    <th>Wallet Address</th>
                                    <th>Status</th>
                                    <th>Admin Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdraws as $withdraw): ?>
                                    <tr>
                                        <td data-label="Date"><?= date('M j, Y H:i', strtotime($withdraw['created_at'])) ?></td>
                                        <td data-label="Amount" class="fw-bold"><?= format_usdt($withdraw['amount']) ?></td>
                                        <td data-label="Wallet Type"><?= htmlspecialchars($withdraw['wallet_type']) ?></td>
                                        <td data-label="Wallet Address"><?= htmlspecialchars($withdraw['wallet_address']) ?></td>
                                        <td data-label="Status"><span class="badge badge-<?= $withdraw['status'] ?>"><?= ucfirst($withdraw['status']) ?></span></td>
                                        <td data-label="Admin Note">
                                            <?php if ($withdraw['admin_note']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($withdraw['admin_note']) ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-wallet2"></i>
                    <h5>No Withdrawals Yet</h5>
                    <p>You haven't made any withdrawal requests yet.</p>
                    <button class="premium-btn" onclick="window.location.href='withdraw.php'">
                        <i class="bi bi-plus-circle"></i>
                        Request Withdrawal
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Simplified Footer -->
    <footer class="simple-footer">
        <div class="container text-center">
            <h5 class="mb-3">TANGO – Old Group Buy</h5>
            <p class="mb-2">Support Email: Old_GP_Tango@outlook.com</p>
            <p class="mb-0">© 2025 TANGO</p>
        </div>
    </footer>

    <style>
        /* Simple Footer */
        .simple-footer {
            background: linear-gradient(to bottom, #2c3e50, #1a252f);
            color: #ecf0f1;
            padding: 30px 0 20px 0;
            margin-top: 40px;
            border-top: 4px solid #3498db;
            font-family: Georgia, 'Times New Roman', serif;
        }
        
        .simple-footer h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #3498db;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .simple-footer p {
            font-size: 0.85rem;
            color: #bdc3c7;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .simple-footer {
                padding: 20px 0 15px 0;
                margin-top: 30px;
            }
            
            .simple-footer h5 {
                font-size: 1rem;
            }
            
            .simple-footer p {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .simple-footer {
                padding: 15px 0 10px 0;
                margin-top: 20px;
            }
            
            .simple-footer h5 {
                font-size: 0.9rem;
            }
            
            .simple-footer p {
                font-size: 0.75rem;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Premium mobile interactions
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.premium-tab');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            contents.forEach(content => content.style.display = 'none');
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-content').style.display = 'block';
            
            // Haptic-like feedback
            if (navigator.vibrate) {
                navigator.vibrate(10);
            }
        }
        
        // Add touch feedback
        document.addEventListener('touchstart', function(e) {
            if (e.target.closest('.premium-card, .premium-btn, .premium-tab')) {
                e.target.closest('.premium-card, .premium-btn, .premium-tab').classList.add('touching');
            }
        });
        
        document.addEventListener('touchend', function(e) {
            if (e.target.closest('.premium-card, .premium-btn, .premium-tab')) {
                setTimeout(() => {
                    e.target.closest('.premium-card, .premium-btn, .premium-tab').classList.remove('touching');
                }, 150);
            }
        });
        
        // Performance optimizations
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            document.querySelectorAll('.premium-card').forEach(card => {
                imageObserver.observe(card);
            });
        }
        
        // Smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
        
        // Prevent pull-to-refresh
        document.body.addEventListener('touchmove', function(e) {
            if (e.touches[0].clientY <= 50) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>
