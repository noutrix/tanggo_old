<?php
require_once '../config.php';
require_admin();

if (!isset($order)) {
    header('Location: orders.php');
    exit;
}

// Process approval with username
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $username = sanitize($_POST['username'] ?? '');
    
    if (!verify_csrf($csrf)) {
        header('Location: orders.php?error=Invalid request');
        exit;
    }
    
    if (empty($username)) {
        $error = 'Please enter the username for transfer ownership';
    } else {
        $admin_note = "Transfer to owner: $username";
        $stmt = $pdo->prepare("UPDATE orders SET status = 'transfer_owner', admin_note = ? WHERE id = ? AND status = 'pending'");
        if ($stmt->execute([$admin_note, $order['id']])) {
            log_admin_action($_SESSION['admin_id'], 'approve', 'order', $order['id'], "Order approved and transferred to: $username");
            header('Location: orders.php?message=Order approved successfully');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Order - TANGO Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1E88E5;
            --dark: #0D47A1;
            --green: #4CAF50;
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
        .btn-success {
            background: var(--green);
            border: none;
            border-radius: 10px;
        }
        .btn-success:hover {
            background: #45a049;
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
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Transfer Owner Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="Enter the username who will receive the transfer" 
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                <small class="text-muted">Enter the Telegram username that will receive the group ownership</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">Approve & Transfer</button>
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
