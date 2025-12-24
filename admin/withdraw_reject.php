<?php
require_once '../config.php';
require_admin();

if (!isset($withdraw)) {
    header('Location: withdraws.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitize($_POST['reason'] ?? '');
    $stmt = $pdo->prepare("UPDATE withdraws SET status = 'rejected', admin_note = ? WHERE id = ? AND status = 'pending'");
    if ($stmt->execute([$reason, $withdraw['id']])) {
        log_admin_action($_SESSION['admin_id'], 'reject', 'withdraw', $withdraw['id'], "Withdraw rejected: $reason");
        header('Location: withdraws.php?message=Withdraw rejected');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Withdraw - TANGO Admin</title>
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
                        <h5>Reject Withdraw #<?= $withdraw['id'] ?></h5>
                    </div>
                    <div class="card-body">
                        <p><strong>User:</strong> <?= htmlspecialchars($withdraw['user_email']) ?></p>
                        <p><strong>Amount:</strong> <?= format_usdt($withdraw['amount']) ?> USDT</p>
                        <p><strong>Wallet Type:</strong> <?= htmlspecialchars($withdraw['wallet_type']) ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($withdraw['wallet_address']) ?></p>
                        
                        <hr>
                        
                        <form method="post">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Rejection Reason</label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" required 
                                          placeholder="Please provide a reason for rejecting this withdraw request..."></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">Reject Withdraw</button>
                                <a href="withdraws.php" class="btn btn-secondary">Cancel</a>
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
