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

// Get recent withdraws
$stmt = $pdo->prepare("
    SELECT * FROM withdraws 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$withdraws = $stmt->fetchAll();

// Handle URL parameters
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Withdraw request submitted successfully! Amount deducted from your balance.';
}

// Initialize error and success variables
$error = '';
$success = $success ?? '';

// Handle withdraw request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf($csrf)) {
        $error = 'Invalid request.';
    } else {
        $amount = floatval($_POST['amount'] ?? 0);
        $wallet_type = $_POST['wallet_type'] ?? '';
        $wallet_address = sanitize($_POST['wallet_address'] ?? '');
        
        if ($amount < MIN_WITHDRAW) {
            $error = 'Minimum withdraw amount is ' . MIN_WITHDRAW . ' USDT.';
        } elseif ($amount > $balance) {
            $error = 'Insufficient balance.';
        } elseif (!in_array($wallet_type, ['binance', 'trc20', 'bep20', 'polygon'])) {
            $error = 'Invalid wallet type.';
        } elseif (empty($wallet_address)) {
            $error = 'Wallet address/UID is required.';
        } else {
            // Show confirmation page
            $_SESSION['withdraw_confirm'] = [
                'amount' => $amount,
                'wallet_type' => $wallet_type,
                'wallet_address' => $wallet_address,
                'csrf' => csrf_token()
            ];
            header('Location: withdraw_confirm.php?from_withdraw=1');
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
    <title>Withdraw - TANGO</title>
    <link rel="stylesheet" href="assets/mobile-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .wallet-option {
            background: var(--nike-white);
            border: 2px solid var(--nike-gray-light);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .wallet-option:hover {
            border-color: var(--nike-orange);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .wallet-option.selected {
            border-color: var(--nike-orange);
            background: rgba(255,124,76,0.05);
        }
        
        .wallet-icon {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--nike-gray-light);
        }
        
        .wallet-info h4 {
            margin: 0 0 0.25rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--nike-black);
        }
        
        .wallet-info p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--nike-gray);
        }
        
        .withdraw-form {
            background: var(--nike-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-top: 1rem;
        }
        
        .balance-display {
            font-size: 2rem;
            font-weight: 700;
            color: var(--nike-orange);
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .min-amount-info {
            text-align: center;
            color: var(--nike-gray);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .wallet-option {
                padding: 0.75rem;
            }
            
            .wallet-icon {
                width: 40px;
                height: 40px;
                font-size: 1.5rem;
            }
            
            .wallet-info h4 {
                font-size: 1rem;
            }
            
            .wallet-info p {
                font-size: 0.8rem;
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
                <a class="topbar-menu-item" href="dashboard.php">
                    <i class="bi bi-house-fill"></i>
                    Home
                </a>
                <a class="topbar-menu-item" href="withdraw.php">
                    <i class="bi bi-wallet-fill"></i>
                    Withdraw
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
        <div class="card mb-4">
            <h2>Withdraw Funds</h2>
            <p>Withdraw your earnings to your preferred wallet</p>
        </div>

        <div class="balance-display">
            <?= format_usdt($balance) ?> USDT
        </div>
        
        <div class="min-amount-info">
            Minimum withdraw amount: <?= MIN_WITHDRAW ?> USDT
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Wallet Selection -->
        <div class="card">
            <h3 class="mb-4">Select Wallet Type</h3>
            
            <div class="wallet-option" onclick="selectWallet('binance')">
                <div class="wallet-icon" style="background: #F3BA2F; color: white;">
                    <i class="bi bi-currency-bitcoin"></i>
                </div>
                <div class="wallet-info">
                    <h4>Binance</h4>
                    <p>Withdraw to Binance Pay</p>
                </div>
            </div>

            <div class="wallet-option" onclick="selectWallet('trc20')">
                <div class="wallet-icon" style="background: #E53E3E; color: white;">
                    <i class="bi bi-triangle"></i>
                </div>
                <div class="wallet-info">
                    <h4>TRC20 (TRON)</h4>
                    <p>Withdraw to TRC20 wallet address</p>
                </div>
            </div>

            <div class="wallet-option" onclick="selectWallet('bep20')">
                <div class="wallet-icon" style="background: #F0B90B; color: white;">
                    <i class="bi bi-hexagon"></i>
                </div>
                <div class="wallet-info">
                    <h4>BEP20 (BSC)</h4>
                    <p>Withdraw to BEP20 wallet address</p>
                </div>
            </div>

            <div class="wallet-option" onclick="selectWallet('polygon')">
                <div class="wallet-icon" style="background: #8247E5; color: white;">
                    <i class="bi bi-pentagon"></i>
                </div>
                <div class="wallet-info">
                    <h4>Polygon (MATIC)</h4>
                    <p>Withdraw to Polygon wallet address</p>
                </div>
            </div>
        </div>

        <!-- Withdraw Form -->
        <div class="withdraw-form" id="withdrawForm" style="display: none;">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="wallet_type" id="walletType">
                
                <div class="form-group">
                    <label class="form-label" id="walletLabel">Wallet Address/UID</label>
                    <input type="text" class="form-control" id="walletInput" name="wallet_address" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="amount">Amount (USDT)</label>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           min="<?= MIN_WITHDRAW ?>" max="<?= $balance ?>" step="0.01" required>
                </div>
                
                <button type="submit" class="btn btn-orange w-100">
                    <i class="bi bi-wallet2 me-2"></i>Submit Withdraw Request
                </button>
            </form>
        </div>

        <!-- Recent Withdraws -->
        <?php if (!empty($withdraws)): ?>
            <div class="card mt-4">
                <h3 class="mb-4">Recent Withdraw Requests</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Wallet Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdraws as $withdraw): ?>
                                <tr>
                                    <td data-label="Date"><?= date('M j, Y H:i', strtotime($withdraw['created_at'])) ?></td>
                                    <td data-label="Amount"><?= format_usdt($withdraw['amount']) ?></td>
                                    <td data-label="Wallet Type"><?= strtoupper($withdraw['wallet_type']) ?></td>
                                    <td data-label="Status">
                                        <span class="badge badge-<?= $withdraw['status'] ?>">
                                            <?= ucfirst($withdraw['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Premium Bottom Navigation -->
    <nav class="bottom-nav">
        <div class="bottom-nav-container">
            <a href="dashboard.php" class="nav-item">
                <i class="bi bi-house-fill"></i>
                <span>Home</span>
            </a>
            <a href="withdraw.php" class="nav-item active">
                <i class="bi bi-wallet-fill"></i>
                <span>Withdraw</span>
            </a>
            <a href="records.php" class="nav-item">
                <i class="bi bi-clock-fill"></i>
                <span>Records</span>
            </a>
        </div>
    </nav>

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

        // Wallet selection
        function selectWallet(type) {
            // Remove selected class from all options
            document.querySelectorAll('.wallet-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Update form based on wallet type
            const walletType = document.getElementById('walletType');
            const walletLabel = document.getElementById('walletLabel');
            const walletInput = document.getElementById('walletInput');
            const withdrawForm = document.getElementById('withdrawForm');
            
            walletType.value = type;
            
            switch(type) {
                case 'binance':
                    walletLabel.textContent = 'Enter your Binance UID';
                    walletInput.placeholder = 'Enter your Binance User ID';
                    break;
                case 'trc20':
                    walletLabel.textContent = 'TRC20 Wallet Address';
                    walletInput.placeholder = 'Enter your TRC20 wallet address';
                    break;
                case 'bep20':
                    walletLabel.textContent = 'BEP20 Wallet Address';
                    walletInput.placeholder = 'Enter your BEP20 wallet address';
                    break;
                case 'polygon':
                    walletLabel.textContent = 'Polygon Wallet Address';
                    walletInput.placeholder = 'Enter your Polygon wallet address';
                    break;
            }
            
            // Show the form
            withdrawForm.style.display = 'block';
            
            // Scroll to form
            withdrawForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

            </script>
</body>
</html>
