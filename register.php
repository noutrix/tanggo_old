<?php
require_once 'config.php';

$error = '';
$email = '';
$full_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('register', 3, 300)) {
        $error = 'Too many registration attempts. Please try again later.';
    } else {
        $csrf = $_POST['csrf'] ?? '';
        if (!verify_csrf($csrf)) {
            $error = 'Invalid request.';
        } else {
            $full_name = sanitize($_POST['full_name'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            
            if (empty($full_name) || empty($email) || empty($password) || empty($password_confirm)) {
                $error = 'All fields are required.';
            } elseif (strlen($full_name) < 2) {
                $error = 'Full name must be at least 2 characters.';
            } elseif (!validate_email($email)) {
                $error = 'Invalid email address.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif ($password !== $password_confirm) {
                $error = 'Passwords do not match.';
            } else {
                $pdo = get_db();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already registered.';
                } else {
                    // Create user account (verified by default)
                    $hash = hash_password($password);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, email_verified) VALUES (?, ?, ?, TRUE)");
                    if ($stmt->execute([$full_name, $email, $hash])) {
                        $_SESSION['user_id'] = $pdo->lastInsertId();
                        $_SESSION['user_email'] = $email;
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
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
    <title>Register - TANGO – Old Group Buy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --gradient-5: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --primary-color: #667eea;
            --secondary-color: #f093fb;
            --accent-color: #4facfe;
            --success-color: #43e97b;
        }
        
        body {
            background: var(--gradient-1);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: var(--gradient-2);
            opacity: 0.3;
            animation: float 20s ease-in-out infinite;
        }
        
        body::after {
            content: '';
            position: absolute;
            top: -30%;
            right: -30%;
            width: 150%;
            height: 150%;
            background: var(--gradient-3);
            opacity: 0.2;
            animation: float 15s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: slideIn 0.6s ease-out;
            position: relative;
            z-index: 1;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-header {
            background: var(--gradient-1);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .register-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .register-header h3 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -2px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .register-header small {
            opacity: 0.9;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .register-body {
            padding: 1.5rem;
        }
        
        .register-body h5 {
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            margin-bottom: 1.25rem;
            text-align: center;
            font-size: 1.125rem;
        }
        
        .form-label {
            font-weight: 600;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: var(--gradient-1);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 700;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(-1px);
        }
        
        .text-primary {
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .text-primary:hover {
            transform: scale(1.05);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            animation: shake 0.5s ease-in-out;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b, #ff8e53);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 480px) {
            .register-card {
                margin: 1rem;
            }
            .register-header {
                padding: 1.5rem;
            }
            .register-header h3 {
                font-size: 2rem;
            }
            .register-body {
                padding: 1.5rem;
            }
        }
        
        @media (min-width: 768px) {
            .register-card {
                max-width: 500px;
            }
        }
        
        .register-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
        
        .form-group-custom .form-label {
            margin-bottom: 0;
        }
        
        .form-group-custom .form-control {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <i class="bi bi-lightning-fill"></i>
            <h3 class="mb-0">TANGO</h3>
            <small>Old TG Group Buy</small>
        </div>
        <div class="register-body">
            <h5 class="text-center mb-4">Create Account</h5>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="post" class="register-form">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <div class="form-group-custom">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required placeholder="Enter your full name">
                </div>
                <div class="form-group-custom">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required placeholder="Enter your email address">
                </div>
                <div class="form-group-custom">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password (min 6 characters)">
                </div>
                <div class="form-group-custom">
                    <label for="password_confirm" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required placeholder="Confirm your password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Sign Up</button>
            </form>
            <div class="text-center mt-3">
                <span>Already have an account? <a href="login.php" class="text-primary">Login</a></span>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
