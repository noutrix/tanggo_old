<?php
require_once 'config.php';
require_login();

$pdo = get_db();
$user_id = $_SESSION['user_id'];

// Get stats
$stmt = $pdo->prepare("SELECT COUNT(*) as today_sold FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->execute([$user_id]);
$today_sold = $stmt->fetch()['today_sold'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_sold FROM orders WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$total_sold = $stmt->fetch()['total_sold'];

$stmt = $pdo->prepare("SELECT balance_usdt FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetch()['balance_usdt'];

// Get recent orders
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name, p.year_range 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Get recent withdraws
$stmt = $pdo->prepare("
    SELECT * FROM withdraws 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$withdraws = $stmt->fetchAll();

// Get active products
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = 1 ORDER BY base_price DESC");
$stmt->execute();
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TANGO</title>
    <link rel="stylesheet" href="assets/mobile-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-lightning-fill"></i>
                TANGO
            </a>
            
            <!-- Mobile Navigation Buttons -->
            <div class="mobile-nav-buttons">
                <a href="dashboard.php" class="mobile-nav-btn active">
                    <i class="bi bi-house"></i>
                </a>
            </div>
            
            <!-- Balance Display (Mobile) -->
            <div class="mobile-balance">
                <?= format_usdt($balance) ?> USDT
            </div>
            
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <i class="bi bi-list"></i>
            </button>
            <div class="topbar-menu" id="navbarNav">
                <a class="topbar-menu-item" href="withdraw.php">
                    <i class="bi bi-wallet-fill"></i>
                    Withdraw
                </a>
                <a class="topbar-menu-item" href="#">
                    <i class="bi bi-person-circle"></i>
                    Profile
                </a>
                <a class="topbar-menu-item" href="#">
                    <i class="bi bi-shield-lock-fill"></i>
                    Change Password
                </a>
                <a class="topbar-menu-item" href="records.php">
                    <i class="bi bi-clock-history"></i>
                    Records
                </a>
                <a class="topbar-menu-item danger" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-content fade-in">

        <div class="page-header">
            <div>
                <div class="greeting">Dashboard</div>
                <div class="date"><?= date('D, M j') ?></div>
            </div>
            <div class="chip"><i class="bi bi-check2-circle"></i> <?= (int)$today_sold ?> sold today</div>
        </div>

        <!-- Balance / Earnings Card -->
        <div class="balance-card">
            <div class="title">Total Earnings</div>
            <div class="amount"><?= format_usdt($balance) ?> USDT</div>
            <div class="subtitle">Your total income</div>
        </div>

        <!-- Quick Action Buttons -->
        <div class="action-buttons">
            <a href="#" class="action-btn" data-bs-toggle="modal" data-bs-target="#sellModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Sell Group</span>
            </a>
            <a href="withdraw.php" class="action-btn">
                <i class="bi bi-wallet-fill"></i>
                <span>Withdraw</span>
            </a>
            <a href="records.php" class="action-btn">
                <i class="bi bi-clock-history"></i>
                <span>Records</span>
            </a>
            <a href="#" class="action-btn" data-bs-toggle="modal" data-bs-target="#rulesModal">
                <i class="bi bi-shield-check-fill"></i>
                <span>Rules</span>
            </a>
        </div>

        <!-- Sell Your Telegram Groups -->
        <h3 class="sell-section-title">Sell Your Old Telegram Groups</h3>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg" alt="Telegram" class="telegram-logo">
                    </div>
                    <div class="product-title">Old <?= htmlspecialchars($product['name']) ?></div>
                    <div class="product-year"><?= htmlspecialchars($product['year_range']) ?></div>
                    <div class="product-price"><?= format_usdt($product['base_price']) ?></div>
                    <button class="sell-btn" data-bs-toggle="modal" data-bs-target="#sellModal" data-product="Old <?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['base_price'] ?>">
                        Sell Now
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($orders)): ?>
            <h3 class="sell-section-title">Recent Activity</h3>
            <div class="activity-preview">
                <?php foreach (array_slice($orders, 0, 3) as $order): // Show only 3 recent items ?>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="bi bi-arrow-down-left-circle-fill"></i></div>
                        <div class="activity-details">
                            <div class="activity-title"><?= htmlspecialchars($order['task_name']) ?></div>
                            <div class="activity-subtitle"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                        </div>
                        <div class="activity-amount">+<?= format_usdt($order['total_amount']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    
    <!-- Simplified Footer -->
    <footer class="simple-footer">
        <div class="container text-center">
            <h5 class="mb-3">TANGO – Old Group Buy</h5>
            <p class="mb-2">Support Email: Old_GP_Tango@outlook.com</p>
            <p class="mb-0"> 2025 TANGO</p>
        </div>
    </footer>

    <!-- Sell Modal -->
    <div class="modal fade" id="sellModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-modal-header">
                    <div class="modal-title-section">
                        <h5 class="modal-title modern-title">Sell Groups</h5>
                        <div class="product-badge" id="modalProductName">Select Product</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white modern-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body modern-modal-body">
                    <form id="sellForm">
                        <div class="product-info-card" id="productInfoCard">
                            <div class="product-icon">
                                <i class="bi bi-telegram-fill"></i>
                            </div>
                            <div class="product-details">
                                <div class="product-name-display" id="productNameDisplay">Old 2024 (04) Group Sell</div>
                                <div class="product-price-display" id="productPriceDisplay">$4.00</div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-cash-stack"></i>
                                Sell Type
                            </div>
                            <div class="sell-type-options">
                                <div class="sell-type-card">
                                    <input class="form-check-input" type="radio" name="sellType" id="singleGroup" value="single" checked>
                                    <label class="sell-type-label" for="singleGroup">
                                        <div class="sell-type-icon">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <div class="sell-type-content">
                                            <div class="sell-type-title">Single Group</div>
                                            <div class="sell-type-desc">Sell one group at a time</div>
                                        </div>
                                    </label>
                                </div>
                                <div class="sell-type-card">
                                    <input class="form-check-input" type="radio" name="sellType" id="bulkGroup" value="bulk">
                                    <label class="sell-type-label" for="bulkGroup">
                                        <div class="sell-type-icon">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <div class="sell-type-content">
                                            <div class="sell-type-title">Bulk group</div>
                                            <div class="sell-type-desc">Extra bonus for multiple groups</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">
                                <i class="bi bi-pencil-square"></i>
                                Task Details
                            </div>
                            <div class="form-group-modern">
                                <label for="taskName" class="modern-label">Task Name</label>
                                <input type="text" class="form-control modern-input" id="taskName" name="taskName" placeholder="Enter task name..." required>
                            </div>
                            
                            <div class="form-group-modern">
                                <label for="telegramLink" class="modern-label">Telegram Invite Link</label>
                                <input type="url" class="form-control modern-input" id="telegramLink" name="telegramLink" placeholder="https://t.me/yourgroup" required>
                            </div>
                        </div>
                        
                        <input type="hidden" id="productType" name="productType">
                        <input type="hidden" id="productPrice" name="productPrice">
                    </form>
                </div>
                <div class="modal-footer modern-modal-footer">
                    <button type="button" class="btn btn-modern btn-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-modern btn-submit" onclick="submitSellForm()">
                        <i class="bi bi-check-circle"></i>
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rulesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 24px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-subtle);">
                    <h5 class="modal-title" style="margin: 0;">GENERAL RULES (APPLIES TO ALL GROUPS)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-unstyled" style="margin: 0;">
                        <li style="margin-bottom: 0.75rem;">• Group creation date must be visible in Telegram group info.</li>
                        <li style="margin-bottom: 0.75rem;">• Group must be a Private Telegram Group.</li>
                        <li style="margin-bottom: 0.75rem;">• Group must contain real messages, not empty or fake chats.</li>
                        <li style="margin-bottom: 0.75rem;">• You must be able to transfer group ownership to our admin after approval.</li>
                        <li style="margin-bottom: 0;">• Admin decision is final in all cases.</li>
                    </ul>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-subtle);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const navbar = document.getElementById('navbarNav');
            navbar.classList.toggle('is-open');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navbar = document.getElementById('navbarNav');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (!navbar.contains(event.target) && !toggle.contains(event.target)) {
                navbar.classList.remove('is-open');
            }
        });

        // Sell Modal - Update product info when opened
        const sellModal = document.getElementById('sellModal');
        sellModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const productName = button.getAttribute('data-product');
            const productPrice = button.getAttribute('data-price');
            
            console.log('Modal opened with:', { productName, productPrice }); // Debug log
            
            // Update modal with product information
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('productNameDisplay').textContent = productName;
            document.getElementById('productPriceDisplay').textContent = '$' + productPrice;
            document.getElementById('productType').value = productName;
            document.getElementById('productPrice').value = productPrice;
        });

        // Submit sell form
        function submitSellForm() {
            const form = document.getElementById('sellForm');
            const formData = new FormData(form);
            
            // Basic validation
            const taskName = formData.get('taskName');
            const telegramLink = formData.get('telegramLink');
            
            if (!taskName || !telegramLink) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Here you would normally submit to server
            console.log('Submitting sell form:', {
                productType: formData.get('productType'),
                productPrice: formData.get('productPrice'),
                sellType: formData.get('sellType'),
                taskName: taskName,
                telegramLink: telegramLink
            });
            
            // Close modal and reset form
            const modal = bootstrap.Modal.getInstance(sellModal);
            modal.hide();
            form.reset();
            
            // Show success message
            alert('Sell request submitted successfully!');
        }
    </script>

    <style>
        /* Premium Dark Color System */
        :root {
            --bg-primary: #0f0e11;
            --bg-card: #1a191d;
            --bg-card-hover: #1f1e22;
            --accent-gold: #c18b4a;
            --accent-orange: #ff6b35;
            --text-primary: #ffffff;
            --text-secondary: #9a9a9a;
            --text-muted: #6a6a6a;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --shadow-soft: 0 4px 16px rgba(0, 0, 0, 0.3);
            --shadow-glow: 0 0 20px rgba(193, 139, 74, 0.2);
        }
        
        /* Base Styles */
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        
        /* Premium Header */
        .navbar {
            background: linear-gradient(135deg, #1a191d 0%, #0f0e11 100%);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-subtle);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.4rem;
            text-shadow: 0 0 20px rgba(193, 139, 74, 0.3);
        }
        
        /* Premium Profile Card */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 24px;
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }
        
        .card h2 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
        }
        
        .card p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 0;
        }
        
        /* Premium Stats Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--bg-card), #1f1e22);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-gold), var(--accent-orange));
            opacity: 0.8;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
        }
        
        .stat-number {
            color: var(--text-primary);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 0 10px rgba(193, 139, 74, 0.2);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Premium Action Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-orange));
            border: none;
            border-radius: 50px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 16px rgba(193, 139, 74, 0.3);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(193, 139, 74, 0.4);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 50px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            color: var(--text-primary);
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--bg-card-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        
        /* Premium Product Cards */
        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent-gold), var(--accent-orange));
            opacity: 0.6;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
        }
        
        .product-card h3 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .product-card .price {
            color: var(--accent-gold);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 0 10px rgba(193, 139, 74, 0.3);
        }
        
        .product-card .year-range {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        /* Mobile Navigation Premium */
        .mobile-nav-buttons {
            display: none;
            align-items: center;
            gap: 0.3rem;
            margin-left: auto;
            margin-right: 0.5rem;
        }
        
        .mobile-nav-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            position: relative;
        }
        
        .mobile-nav-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .mobile-nav-btn.active {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-orange));
            box-shadow: 0 4px 16px rgba(193, 139, 74, 0.4);
        }
        
        .mobile-nav-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
        }
        
        .mobile-balance {
            display: none;
            color: var(--text-primary);
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .mobile-menu-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--text-primary);
            width: 44px;
            height: 44px;
            border-radius: 12px;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .mobile-menu-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: scale(1.05);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .mobile-nav-buttons {
                display: flex;
            }
            
            .mobile-balance {
                display: block;
            }
            
            .navbar-nav {
                display: none !important;
            }
            
            .mobile-menu-toggle {
                display: flex !important;
                align-items: center;
                justify-content: center;
            }
            
            .navbar {
                padding: 0.6rem 0;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
            
            .card {
                margin-bottom: 1rem;
                border-radius: 20px;
            }
            
            .stat-card {
                padding: 1.2rem;
                border-radius: 16px;
            }
            
            .stat-number {
                font-size: 1.6rem;
            }
            
            .btn-primary,
            .btn-secondary {
                padding: 0.7rem 1.8rem;
                font-size: 0.9rem;
            }
            
            .product-card {
                padding: 1.2rem;
                border-radius: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .navbar {
                padding: 0.5rem 0;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .mobile-nav-btn {
                width: 38px;
                height: 38px;
                font-size: 1rem;
            }
            
            .mobile-balance {
                font-size: 0.8rem;
                padding: 0.25rem 0.5rem;
            }
            
            .mobile-menu-toggle {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }
            
            .card {
                padding: 1.2rem;
                border-radius: 18px;
            }
            
            .card h2 {
                font-size: 1.4rem;
            }
            
            .stat-card {
                padding: 1rem;
                border-radius: 14px;
            }
            
            .stat-number {
                font-size: 1.4rem;
            }
            
            .btn-primary,
            .btn-secondary {
                padding: 0.6rem 1.5rem;
                font-size: 0.85rem;
            }
            
            .product-card {
                padding: 1rem;
                border-radius: 14px;
            }
            
            .product-card h3 {
                font-size: 1.2rem;
            }
            
            .product-card .price {
                font-size: 1.3rem;
            }
        }
        
        /* Status Badge Styles */
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            box-shadow: 0 2px 8px rgba(243, 156, 18, 0.4);
        }
        
        .badge-completed {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            box-shadow: 0 2px 8px rgba(39, 174, 96, 0.4);
        }
        
        .badge-failed, .badge-fail {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.4);
        }
        
        .badge-transfer {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
            box-shadow: 0 2px 8px rgba(155, 89, 182, 0.4);
        }
        
        .bonus-alert {
            background: rgba(193, 139, 74, 0.1);
            border: 1px solid var(--accent-gold);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        /* Modal Fixes */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1055;
            display: none;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            outline: 0;
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 0.5rem;
            pointer-events: none;
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }
        
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: var(--bg-card);
            background-clip: padding-box;
            border: 1px solid var(--border-subtle);
            border-radius: 0.3rem;
            outline: 0;
        }
        
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050;
            width: 100vw;
            height: 100vh;
            background-color: #000;
            opacity: 0.5;
        }
        
        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 1rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
            border-top-left-radius: calc(0.3rem - 1px);
            border-top-right-radius: calc(0.3rem - 1px);
        }
        
        .modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem;
        }
        
        .modal-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            padding: 0.75rem;
            border-top: 1px solid var(--border-subtle);
            border-bottom-right-radius: calc(0.3rem - 1px);
            border-bottom-left-radius: calc(0.3rem - 1px);
        }
        
        .btn-close {
            box-sizing: content-box;
            width: 1em;
            height: 1em;
            padding: 0.25em 0.25em;
            color: #fff;
            background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='m.235.867 8.832 8.832-8.832 8.832a.5.5 0 0 0 .707.707L9.774 10.4l8.832 8.832a.5.5 0 0 0 .707-.707L10.381 9.774l8.932-8.932a.5.5 0 0 0-.707-.707L9.774 9.894.942.942A.5.5 0 0 0 .235.867Z'/%3e%3c/svg%3e") center/1em auto no-repeat;
            border: 0;
            border-radius: 0.25rem;
            opacity: 0.5;
        }
        
        .btn-close:hover {
            color: #fff;
            text-decoration: none;
            opacity: 0.75;
        }
        
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #fff;
            background-color: var(--bg-card);
            background-clip: padding-box;
            border: 1px solid var(--border-subtle);
            appearance: none;
            border-radius: 0.25rem;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        
        .form-control:focus {
            color: #fff;
            background-color: var(--bg-card);
            border-color: var(--accent-gold);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(193, 139, 74, 0.25);
        }
        
        .form-label {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .form-check {
            display: block;
            min-height: 1.5rem;
            padding-left: 1.5em;
            margin-bottom: 0.125rem;
        }
        
        .form-check-input {
            width: 1em;
            height: 1em;
            margin-top: 0.25em;
            vertical-align: top;
            background-color: var(--bg-card);
            border: 1px solid var(--border-subtle);
            appearance: none;
            color-adjust: exact;
        }
        
        .form-check-input:checked {
            background-color: var(--accent-gold);
            border-color: var(--accent-gold);
        }
        
        .form-check-input:focus {
            border-color: var(--accent-gold);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(193, 139, 74, 0.25);
        }
        
        .form-check-label {
            color: var(--text-primary);
        }
        
        /* Modern Modal Styles */
        .modern-modal {
            background: linear-gradient(135deg, #1a191d, #2d2b35);
            border: 2px solid transparent;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.9), 0 0 60px rgba(193, 139, 74, 0.2);
            overflow: hidden;
            position: relative;
            animation: modalGlow 3s ease-in-out infinite alternate;
        }
        
        @keyframes modalGlow {
            0% {
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.9), 0 0 60px rgba(193, 139, 74, 0.2);
            }
            100% {
                box-shadow: 0 25px 80px rgba(0, 0, 0, 0.9), 0 0 80px rgba(255, 107, 53, 0.3);
            }
        }
        
        .modern-modal::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ff6b35, #c18b4a, #ff6b35, #ffd700, #ff6b35);
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .modern-modal-header {
            background: linear-gradient(135deg, #2d2b35, #1a191d);
            border-bottom: 2px solid rgba(255, 107, 53, 0.3);
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .modern-modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 107, 53, 0.1), transparent);
            animation: shimmer 4s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }
        
        .modal-title-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .modern-title {
            color: var(--text-primary);
            font-weight: 800;
            font-size: 1.8rem;
            margin: 0;
            background: linear-gradient(135deg, #ffd700, #ff6b35, #c18b4a);
            background-size: 200% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textGradient 3s ease-in-out infinite;
        }
        
        @keyframes textGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .product-badge {
            background: linear-gradient(135deg, #ff6b35, #c18b4a);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
            animation: badgePulse 2s ease-in-out infinite;
        }
        
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .modern-close {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.2), rgba(193, 139, 74, 0.2));
            border: 2px solid rgba(255, 107, 53, 0.3);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #ff6b35;
            font-size: 1.2rem;
            position: relative;
            z-index: 1;
        }
        
        .modern-close:hover {
            background: linear-gradient(135deg, rgba(255, 107, 53, 0.4), rgba(193, 139, 74, 0.4));
            color: #ffd700;
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.6);
        }
        
        .modern-modal-body {
            padding: 2.5rem;
            background: linear-gradient(135deg, #1a191d, #2d2b35);
            position: relative;
        }
        
        .product-info-card {
            background: linear-gradient(135deg, #2d2b35, #1a191d);
            border: 2px solid rgba(255, 107, 53, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
        }
        
        .product-info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 215, 0, 0.2), transparent);
            animation: cardShimmer 3s ease-in-out infinite;
        }
        
        @keyframes cardShimmer {
            0% { left: -100%; }
            50% { left: 100%; }
            100% { left: 100%; }
        }
        
        .product-icon {
            background: linear-gradient(135deg, #0088cc, #00a8ff, #0088cc);
            background-size: 200% 100%;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 15px 40px rgba(0, 136, 204, 0.5);
            animation: iconGradient 3s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }
        
        @keyframes iconGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .product-details {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .product-name-display {
            color: var(--text-primary);
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #ffd700, #ff6b35);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .product-price-display {
            color: #ffd700;
            font-size: 1.8rem;
            font-weight: 800;
            text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            animation: priceGlow 2s ease-in-out infinite;
        }
        
        @keyframes priceGlow {
            0%, 100% { text-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }
            50% { text-shadow: 0 0 30px rgba(255, 215, 0, 0.8); }
        }
        
        .form-section {
            margin-bottom: 2.5rem;
        }
        
        .section-title {
            color: var(--text-primary);
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            background: linear-gradient(135deg, #ff6b35, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-title i {
            color: #ff6b35;
            font-size: 1.5rem;
        }
        
        .sell-type-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }
        
        .sell-type-card {
            position: relative;
        }
        
        .sell-type-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .sell-type-label {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            background: linear-gradient(135deg, #2d2b35, #1a191d);
            border: 2px solid rgba(255, 107, 53, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .sell-type-label::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 215, 0, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .sell-type-label:hover::before {
            left: 100%;
        }
        
        .sell-type-card input[type="radio"]:checked + .sell-type-label {
            border-color: #ffd700;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 107, 53, 0.2));
            box-shadow: 0 15px 40px rgba(255, 215, 0, 0.3);
            transform: translateY(-3px);
        }
        
        .sell-type-icon {
            background: linear-gradient(135deg, #ff6b35, #c18b4a);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
        }
        
        .sell-type-content {
            flex: 1;
        }
        
        .sell-type-title {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.3rem;
            font-size: 1.1rem;
        }
        
        .sell-type-desc {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .form-group-modern {
            margin-bottom: 2rem;
        }
        
        .modern-label {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.8rem;
            display: block;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #ff6b35, #ffd700);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .modern-input {
            background: linear-gradient(135deg, #2d2b35, #1a191d);
            border: 2px solid rgba(255, 107, 53, 0.2);
            border-radius: 16px;
            padding: 1.2rem;
            color: var(--text-primary);
            font-size: 1.1rem;
            transition: all 0.4s ease;
        }
        
        .modern-input:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 0.3rem rgba(255, 215, 0, 0.3), 0 15px 40px rgba(255, 215, 0, 0.2);
            outline: none;
            transform: translateY(-2px);
        }
        
        .modern-input::placeholder {
            color: var(--text-muted);
        }
        
        .modern-modal-footer {
            background: linear-gradient(135deg, #2d2b35, #1a191d);
            border-top: 2px solid rgba(255, 107, 53, 0.3);
            padding: 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 1.5rem;
        }
        
        .btn-modern {
            padding: 1rem 2rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.4s ease;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-modern:hover::before {
            left: 100%;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        .btn-cancel:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(108, 117, 125, 0.5);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #ff6b35, #ffd700, #ff6b35);
            background-size: 200% 100%;
            color: #1a191d;
            font-weight: 800;
            box-shadow: 0 15px 40px rgba(255, 107, 53, 0.4);
            animation: btnGradient 3s ease-in-out infinite;
        }
        
        @keyframes btnGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 50px rgba(255, 107, 53, 0.6);
        }
        
        /* Mobile Responsive Modal */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }
            
            .modern-modal {
                border-radius: 16px;
                box-shadow: 0 15px 40px rgba(0, 0, 0, 0.8), 0 0 30px rgba(193, 139, 74, 0.2);
            }
            
            .modern-modal-header {
                padding: 1.2rem;
            }
            
            .modern-title {
                font-size: 1.4rem;
            }
            
            .product-badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .modern-close {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .modern-modal-body {
                padding: 1.5rem;
            }
            
            .product-info-card {
                padding: 1.2rem;
                margin-bottom: 1.5rem;
                gap: 1rem;
            }
            
            .product-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .product-name-display {
                font-size: 1.1rem;
            }
            
            .product-price-display {
                font-size: 1.4rem;
            }
            
            .form-section {
                margin-bottom: 1.5rem;
            }
            
            .section-title {
                font-size: 1.1rem;
                margin-bottom: 1rem;
            }
            
            .sell-type-options {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .sell-type-label {
                padding: 1rem;
                gap: 1rem;
            }
            
            .sell-type-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .sell-type-title {
                font-size: 1rem;
            }
            
            .sell-type-desc {
                font-size: 0.85rem;
            }
            
            .form-group-modern {
                margin-bottom: 1.2rem;
            }
            
            .modern-label {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .modern-input {
                padding: 0.8rem;
                font-size: 1rem;
            }
            
            .modern-modal-footer {
                padding: 1.2rem;
                gap: 1rem;
            }
            
            .btn-modern {
                padding: 0.8rem 1.5rem;
                font-size: 0.9rem;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modern-modal {
                border-radius: 12px;
            }
            
            .modern-modal-header {
                padding: 1rem;
            }
            
            .modal-title-section {
                gap: 1rem;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .modern-title {
                font-size: 1.2rem;
            }
            
            .product-badge {
                align-self: flex-start;
            }
            
            .modern-close {
                position: absolute;
                top: 1rem;
                right: 1rem;
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            .modern-modal-body {
                padding: 1rem;
            }
            
            .product-info-card {
                padding: 1rem;
                margin-bottom: 1rem;
                flex-direction: column;
                text-align: center;
                gap: 0.8rem;
            }
            
            .product-icon {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            
            .product-name-display {
                font-size: 1rem;
                margin-bottom: 0.3rem;
            }
            
            .product-price-display {
                font-size: 1.2rem;
            }
            
            .form-section {
                margin-bottom: 1rem;
            }
            
            .section-title {
                font-size: 1rem;
                margin-bottom: 0.8rem;
                gap: 0.5rem;
            }
            
            .section-title i {
                font-size: 1.2rem;
            }
            
            .sell-type-label {
                padding: 0.8rem;
                gap: 0.8rem;
            }
            
            .sell-type-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
            
            .sell-type-title {
                font-size: 0.95rem;
            }
            
            .sell-type-desc {
                font-size: 0.8rem;
            }
            
            .form-group-modern {
                margin-bottom: 1rem;
            }
            
            .modern-label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }
            
            .modern-input {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            .modern-modal-footer {
                padding: 1rem;
                gap: 0.8rem;
                flex-direction: column;
            }
            
            .btn-modern {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
                gap: 0.4rem;
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Sell Section */
        .sell-section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 2.5rem 0 1.5rem 0;
            text-align: center;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-orange));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(193, 139, 74, 0.3);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(193, 139, 74, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .product-card:hover::before {
            left: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(193, 139, 74, 0.3);
            border-color: var(--accent-gold);
        }
        
        .product-image {
            margin-bottom: 0.75rem;
            text-align: center;
        }
        
        .telegram-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: drop-shadow(0 2px 6px rgba(0, 136, 204, 0.3));
            transition: all 0.3s ease;
        }
        
        .product-card:hover .telegram-logo {
            transform: scale(1.1);
            filter: drop-shadow(0 3px 10px rgba(0, 136, 204, 0.5));
        }
        
        .product-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .product-year {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-gold);
            margin-bottom: 0.75rem;
        }
        
        .sell-btn {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-orange));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            box-shadow: 0 4px 12px rgba(193, 139, 74, 0.3);
        }
        
        .sell-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .sell-btn:hover::before {
            left: 100%;
        }
        
        .sell-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(193, 139, 74, 0.5);
            background: linear-gradient(135deg, var(--accent-orange), var(--accent-gold));
        }
        
        .sell-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(193, 139, 74, 0.4);
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            
            .product-card {
                padding: 0.75rem;
            }
            
            .sell-section-title {
                font-size: 1.5rem;
                margin: 2rem 0 1rem 0;
            }
            
            .telegram-logo {
                width: 35px;
                height: 35px;
            }
            
            .product-title {
                font-size: 0.85rem;
            }
            
            .product-year {
                font-size: 0.8rem;
            }
            
            .product-price {
                font-size: 1.1rem;
            }
            
            .sell-btn {
                font-size: 0.8rem;
                padding: 0.5rem 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .product-card {
                padding: 0.6rem;
            }
            
            .telegram-logo {
                width: 30px;
                height: 30px;
            }
            
            .product-title {
                font-size: 0.8rem;
                line-height: 1.1;
            }
            
            .product-year {
                font-size: 0.75rem;
            }
            
            .product-price {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .sell-btn {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }
        }
        .simple-footer {
            background: linear-gradient(to bottom, #1a191d, #0f0e11);
            color: var(--text-secondary);
            padding: 30px 0 20px 0;
            margin-top: 40px;
            border-top: 2px solid var(--accent-gold);
        }
        
        .simple-footer h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent-gold);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .simple-footer p {
            font-size: 0.85rem;
            color: var(--text-muted);
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

    <!-- Premium Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="bottom-nav-container">
            <a href="dashboard.php" class="nav-item active">
                <i class="bi bi-house-fill"></i>
                <span>Home</span>
            </a>
            <a href="withdraw.php" class="nav-item">
                <i class="bi bi-wallet-fill"></i>
                <span>Withdraw</span>
            </a>
            <a href="records.php" class="nav-item">
                <i class="bi bi-clock-fill"></i>
                <span>Records</span>
            </a>
        </div>
    </nav>
</body>
</html>
