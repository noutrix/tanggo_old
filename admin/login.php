<?php
require_once '../config.php';

// Redirect logged-in admins
if (is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('admin_login', 5, 300)) {
        $error = 'Too many login attempts. Please try again later.';
    } else {
        $csrf = $_POST['csrf'] ?? '';
        if (!verify_csrf($csrf)) {
            $error = 'Invalid request.';
        } else {
            $email = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Check against fixed admin credentials
            if ($email === ADMIN_EMAIL && $password === ADMIN_PASSWORD) {
                // Verify admin exists in database or create if not exists
                $pdo = get_db();
                $stmt = $pdo->prepare("SELECT id, email FROM admins WHERE email = ?");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();
                
                if (!$admin) {
                    // Create admin record if not exists
                    $hash = hash_password(ADMIN_PASSWORD);
                    $stmt = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
                    $stmt->execute([$email, $hash]);
                    $admin_id = $pdo->lastInsertId();
                } else {
                    $admin_id = $admin['id'];
                }
                
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['admin_email'] = $email;
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid admin credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - TANGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1E88E5;
            --secondary: #E3F2FD;
            --accent: #00BCD4;
            --dark: #0D47A1;
        }
        body {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        .admin-header {
            background: var(--dark);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .admin-body {
            padding: 2rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(30,136,229,0.25);
        }
        .btn-admin {
            background: var(--dark);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30,136,229,0.3);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .text-primary {
            color: var(--primary) !important;
        }
    </style>
</head>
<body>
    <div class="admin-card">
        <div class="admin-header">
            <h3 class="mb-0">TANGO Admin</h3>
            <small>Control Panel</small>
        </div>
        <div class="admin-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <div class="mb-3">
                    <label for="email" class="form-label">Admin Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Admin Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-admin w-100">Login to Admin</button>
            </form>
            <div class="text-center mt-3">
                <small><a href="../login.php" class="text-muted">User Login</a></small>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
