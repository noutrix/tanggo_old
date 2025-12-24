<?php
require_once '../config.php';
require_admin();

$pdo = get_db();
$message = '';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        $message = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $name = sanitize($_POST['name'] ?? '');
            $year_range = sanitize($_POST['year_range'] ?? '');
            $base_price = floatval($_POST['base_price'] ?? 0);
            $bonus_rule = sanitize($_POST['bonus_rule'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($year_range) || $base_price <= 0) {
                $message = 'Please fill in all required fields.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, year_range, base_price, bonus_rule, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$name, $year_range, $base_price, $bonus_rule, $is_active])) {
                    log_admin_action($_SESSION['admin_id'], 'create', 'product', $pdo->lastInsertId(), "Created product: $name");
                    $message = 'Product added successfully!';
                } else {
                    $message = 'Failed to add product.';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $year_range = sanitize($_POST['year_range'] ?? '');
            $base_price = floatval($_POST['base_price'] ?? 0);
            $bonus_rule = sanitize($_POST['bonus_rule'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($year_range) || $base_price <= 0) {
                $message = 'Please fill in all required fields.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, year_range = ?, base_price = ?, bonus_rule = ?, is_active = ?
                    WHERE id = ?
                ");
                if ($stmt->execute([$name, $year_range, $base_price, $bonus_rule, $is_active, $id])) {
                    log_admin_action($_SESSION['admin_id'], 'update', 'product', $id, "Updated product: $name");
                    $message = 'Product updated successfully!';
                } else {
                    $message = 'Failed to update product.';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt->execute([$id])) {
                log_admin_action($_SESSION['admin_id'], 'delete', 'product', $id, "Deleted product ID: $id");
                $message = 'Product deleted successfully!';
            } else {
                $message = 'Failed to delete product.';
            }
        }
    }
}

// Get all products
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY base_price DESC");
$stmt->execute();
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - TANGO Admin</title>
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
        
        .product-form {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
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
        
        .btn-edit {
            background: var(--nike-blue);
            color: white;
        }
        
        .btn-delete {
            background: var(--nike-red);
            color: white;
        }
        
        .product-card {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--nike-black);
            margin: 0;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--nike-orange);
            margin: 0.5rem 0;
        }
        
        .product-details {
            color: var(--nike-gray);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: var(--nike-green);
            color: white;
        }
        
        .status-inactive {
            background: var(--nike-gray);
            color: white;
        }
        
        @media (max-width: 768px) {
            .product-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-buttons {
                justify-content: flex-start;
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
                    <a class="admin-nav-link active" href="products.php">
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
                <h2 class="mb-4">Product Management</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Add Product Form -->
                <div class="product-form">
                    <h3 class="mb-4">Add New Product</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Year Range</label>
                                <input type="text" class="form-control" name="year_range" placeholder="e.g., 2024 (01-03)" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Base Price (USDT)</label>
                                <input type="number" class="form-control" name="base_price" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bonus Rule</label>
                                <input type="text" class="form-control" name="bonus_rule" placeholder="e.g., +0.5 for 10+ groups">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-orange">
                            <i class="bi bi-plus-circle me-2"></i>Add Product
                        </button>
                    </form>
                </div>

                <!-- Products List -->
                <div class="card">
                    <h3 class="mb-4">Existing Products</h3>
                    
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-header">
                                <div>
                                    <h4 class="product-title"><?= htmlspecialchars($product['name']) ?></h4>
                                    <span class="status-badge <?= $product['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn-action btn-edit" onclick="editProduct(<?= $product['id'] ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn-action btn-delete" onclick="deleteProduct(<?= $product['id'] ?>)">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            
                            <div class="product-details">
                                <div><strong>Year Range:</strong> <?= htmlspecialchars($product['year_range']) ?></div>
                                <div><strong>Base Price:</strong> <span class="product-price">$<?= number_format($product['base_price'], 2) ?></span></div>
                                <?php if ($product['bonus_rule']): ?>
                                    <div><strong>Bonus Rule:</strong> <?= htmlspecialchars($product['bonus_rule']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal" id="editModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button class="btn-close" onclick="closeEditModal()"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="POST">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="editId" name="id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Year Range</label>
                                <input type="text" class="form-control" id="editYearRange" name="year_range" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Base Price (USDT)</label>
                                <input type="number" class="form-control" id="editBasePrice" name="base_price" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bonus Rule</label>
                                <input type="text" class="form-control" id="editBonusRule" name="bonus_rule">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="editIsActive" name="is_active">
                                <label class="form-check-label" for="editIsActive">
                                    Active
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" form="editForm" class="btn btn-orange">Update Product</button>
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

        // Edit product functions
        function editProduct(id) {
            const products = <?= json_encode($products) ?>;
            const product = products.find(p => p.id == id);
            
            if (product) {
                document.getElementById('editId').value = product.id;
                document.getElementById('editName').value = product.name;
                document.getElementById('editYearRange').value = product.year_range;
                document.getElementById('editBasePrice').value = product.base_price;
                document.getElementById('editBonusRule').value = product.bonus_rule || '';
                document.getElementById('editIsActive').checked = product.is_active;
                
                document.getElementById('editModal').classList.add('show');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
