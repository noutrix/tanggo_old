<?php
require_once '../config.php';
require_admin();

if (!isset($order)) {
    header('Location: orders.php');
    exit;
}

// Simple reject - no form, just confirm and reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        header('Location: orders.php?error=Invalid request');
        exit;
    }
    
    $reason = sanitize($_POST['reason'] ?? 'Order rejected by admin');
    $stmt = $pdo->prepare("UPDATE orders SET status = 'fail', admin_note = ? WHERE id = ? AND status = 'pending'");
    if ($stmt->execute([$reason, $order['id']])) {
        log_admin_action($_SESSION['admin_id'], 'reject', 'order', $order['id'], "Order rejected: $reason");
        header('Location: orders.php?message=Order rejected successfully');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Order - TANGO Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1E88E5;
            --dark: #0D47A1;
            --red: #F44336;
        }
        body {
            background: #F5F7FA;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: var(--dark);
        }
        .navbar-brand {
            font-weight: bold;
            color: white !important;
        }
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
        }
        .btn-danger {
            background: var(--red);
            border: none;
            border-radius: 10px;
        }
        .btn-danger:hover {
            background: #D32F2F;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">TANGO Admin</a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Order Details #<?= $order['id'] ?></h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Task:</strong> <?= htmlspecialchars($order['task_name']) ?></p>
                        <p><strong>User:</strong> <?= htmlspecialchars($order['user_email']) ?></p>
                        <p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
                        <p><strong>Groups:</strong> <?= $order['group_count'] ?></p>
                        <p><strong>Total Amount:</strong> <?= format_usdt($order['total_amount']) ?> USDT</p>
                        
                        <hr>
                        
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <input type="hidden" name="reason" value="Order rejected by admin">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">Confirm Reject</button>
                                <a href="orders.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
