<?php
require_once 'config.php';

// Create table if it doesn't exist
try {
    $pdo = get_db();
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(64) NOT NULL,
        code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_code (code),
        INDEX idx_email (email),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
} catch (Exception $e) {
    // Table might already exist, continue
}

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('forgot_password', 3, 300)) {
        $error = 'Too many requests. Please try again later.';
    } else {
        $csrf = $_POST['csrf'] ?? '';
        if (!verify_csrf($csrf)) {
            $error = 'Invalid request.';
        } else {
            $email = sanitize_email($_POST['email'] ?? '');
            
            if (empty($email)) {
                $error = 'Email address is required.';
            } elseif (!validate_email($email)) {
                $error = 'Invalid email address.';
            } else {
                $pdo = get_db();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token and code
                    $token = bin2hex(random_bytes(32));
                    $code = sprintf('%06d', random_int(0, 999999));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    // Delete any existing tokens for this user
                    $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? OR email = ?");
                    $stmt->execute([$user['id'], $email]);
                    
                    // Insert new token
                    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, email, token, code, expires_at) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user['id'], $email, $token, $code, $expires_at]);
                    
                    // Send email with reset code
                    if (send_reset_email($email, $code)) {
                        // Redirect to enter code page
                        header("Location: enter-code.php?email=" . urlencode($email));
                        exit;
                    } else {
                        $error = "Failed to send reset email. Please try again.";
                    }
                } else {
                    // Don't reveal if email exists or not
                    $success = "If an account with this email exists, a reset code will be sent.";
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
    <title>Forgot Password - TANGO</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --primary-color: #667eea;
            --secondary-color: #f093fb;
            --accent-color: #4facfe;
            --success-color: #43e97b;
        }
        
        .forgot-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--gradient-1);
            position: relative;
            overflow: hidden;
        }
        
        .forgot-container::before {
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
        
        .forgot-container::after {
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
        
        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(255, 255, 255, 0.2);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
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
        
        .forgot-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .forgot-logo i {
            font-size: 3rem;
            background: var(--gradient-2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .forgot-logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .forgot-logo p {
            color: #666;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-submit {
            width: 100%;
            background: var(--gradient-1);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .back-link a {
            background: var(--gradient-3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .back-link a:hover {
            transform: scale(1.05);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ff6b6b, #ff8e53);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
            box-shadow: 0 4px 15px rgba(67, 233, 123, 0.3);
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 480px) {
            .forgot-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-logo">
                <i class="bi bi-key-fill"></i>
                <h1>Forgot Password</h1>
                <p>Enter your email to receive a reset code</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($email) ?>" required 
                           placeholder="Enter your email address">
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="bi bi-send-fill me-2"></i>
                    Send Reset Code
                </button>
            </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
