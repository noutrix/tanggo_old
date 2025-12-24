<?php
require_once 'config.php';
require_login();

// Check if withdrawal data exists in session
if (!isset($_SESSION['withdraw_confirm'])) {
    header('Location: withdraw.php');
    exit;
}

// Clear session data if user is just visiting (not from form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['from_withdraw'])) {
    unset($_SESSION['withdraw_confirm']);
    header('Location: withdraw.php');
    exit;
}

$withdraw_data = $_SESSION['withdraw_confirm'];
$pdo = get_db();
$user_id = $_SESSION['user_id'];

// Get user balance for display
$stmt = $pdo->prepare("SELECT balance_usdt FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$balance = $user['balance_usdt'];

// Handle confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        $error = 'Invalid request.';
    } else {
        // Process the withdrawal
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Deduct amount from user balance
            $stmt = $pdo->prepare("UPDATE users SET balance_usdt = balance_usdt - ? WHERE id = ?");
            $stmt->execute([$withdraw_data['amount'], $user_id]);
            
            // Create withdraw request
            $stmt = $pdo->prepare("
                INSERT INTO withdraws (user_id, amount, wallet_type, wallet_address, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $withdraw_data['amount'], $withdraw_data['wallet_type'], $withdraw_data['wallet_address']]);
            
            // Commit transaction
            $pdo->commit();
            
            // Clear session data
            unset($_SESSION['withdraw_confirm']);
            
            // Redirect to success page
            header('Location: withdraw.php?success=1');
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            $error = 'Failed to submit withdraw request. Please try again.';
        }
    }
}

// Handle cancellation
if (isset($_GET['cancel'])) {
    unset($_SESSION['withdraw_confirm']);
    header('Location: withdraw.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Withdrawal - TANGO</title>
    <link rel="stylesheet" href="assets/mobile-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .confirm-card {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border-left: 4px solid #ff6b35;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .confirm-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .confirm-header i {
            font-size: 3rem;
            color: #ff6b35;
            margin-bottom: 1rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            font-weight: bold;
            color: #ff6b35;
            font-size: 1.1rem;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            color: #856404;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-confirm {
            background: #ff6b35;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            flex: 1;
        }
        
        .btn-confirm:hover {
            background: #e55a2b;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            flex: 1;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
        }
        
        @media (max-width: 576px) {
            .confirm-card {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-lightning-fill"></i>
                TANGO
            </a>
            <a href="withdraw.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </nav>

    <div class="container main-content">
        <div class="confirm-card">
            <div class="confirm-header">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h2>Confirm Withdrawal</h2>
                <p class="text-muted">Please review your withdrawal details carefully</p>
            </div>
            
            <div class="withdraw-details">
                <div class="detail-row">
                    <span>Amount to Withdraw:</span>
                    <span><?= format_usdt($withdraw_data['amount']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Wallet Type:</span>
                    <span><?= ucfirst($withdraw_data['wallet_type']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Wallet Address/UID:</span>
                    <span style="font-size: 0.9rem; word-break: break-all;"><?= htmlspecialchars($withdraw_data['wallet_address']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Current Balance:</span>
                    <span><?= format_usdt($balance) ?></span>
                </div>
                <div class="detail-row">
                    <span>Balance After Withdrawal:</span>
                    <span><?= format_usdt($balance - $withdraw_data['amount']) ?></span>
                </div>
            </div>
            
            <div class="warning-box">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Important:</strong> Once confirmed, this withdrawal cannot be cancelled. The amount will be deducted from your balance immediately.
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <div class="action-buttons">
                    <button type="submit" class="btn-confirm">
                        <i class="bi bi-check-circle me-2"></i>Confirm Withdrawal
                    </button>
                    <a href="?cancel=1" class="btn-cancel text-center text-decoration-none">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
