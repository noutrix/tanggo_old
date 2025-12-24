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
$email = sanitize_email($_GET['email'] ?? '');
$token = $_GET['token'] ?? '';

// Verify token first
$valid_token = false;
if ($email && $token) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? AND token = ? AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute([$email, $token]);
    $token_data = $stmt->fetch();
    
    if ($token_data) {
        $valid_token = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('reset_password', 3, 300)) {
        $error = 'Too many requests. Please try again later.';
    } else {
        $csrf = $_POST['csrf'] ?? '';
        if (!verify_csrf($csrf)) {
            $error = 'Invalid request.';
        } elseif (!$valid_token) {
            $error = 'Invalid or expired reset session.';
        } else {
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = 'Both password fields are required.';
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } else {
                // Update user password
                $hash = hash_password($new_password);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                
                if ($stmt->execute([$hash, $token_data['user_id']])) {
                    // Mark token as used
                    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
                    $stmt->execute([$token_data['id']]);
                    
                    $success = 'Password reset successfully! You can now login with your new password.';
                    $valid_token = false; // Hide form after success
                } else {
                    $error = 'Failed to reset password. Please try again.';
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
    <title>New Password - TANGO</title>
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
        
        .newpass-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--gradient-1);
            position: relative;
            overflow: hidden;
        }
        
        .newpass-container::before {
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
        
        .newpass-container::after {
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
        
        .newpass-card {
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
        
        .newpass-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .newpass-logo i {
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
        
        .newpass-logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .newpass-logo p {
            color: #666;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .newpass-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }
        
        .form-label {
            font-weight: 600;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-control {
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
        
        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1rem;
        }
        
        .password-strength {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .strength-bar {
            height: 4px;
            flex: 1;
            background: #e0e0e0;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-weak .strength-bar:nth-child(1) { background: #ff6b6b; }
        .strength-medium .strength-bar:nth-child(1),
        .strength-medium .strength-bar:nth-child(2) { background: #ffd93d; }
        .strength-strong .strength-bar:nth-child(1),
        .strength-strong .strength-bar:nth-child(2),
        .strength-strong .strength-bar:nth-child(3) { background: #43e97b; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 480px) {
            .newpass-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="newpass-container">
        <div class="newpass-card">
            <div class="newpass-logo">
                <i class="bi bi-shield-lock-fill"></i>
                <h1>Set New Password</h1>
                <p>Choose a strong password for your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <div style="text-align: center;">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                    <h3 style="color: var(--success-color); margin-bottom: 1rem;">Success!</h3>
                    <p style="color: #666; margin-bottom: 2rem;">Your password has been reset successfully. You can now login with your new password.</p>
                    <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none;">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Go to Login
                    </a>
                </div>
            <?php elseif ($valid_token): ?>
                <form method="POST" class="newpass-form">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    
                    <div class="form-group-custom">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               required placeholder="Enter new password (min 6 characters)">
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                            <div class="strength-bar"></div>
                        </div>
                    </div>
                    
                    <div class="form-group-custom">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               required placeholder="Confirm new password">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-shield-check-fill me-2"></i>
                        Reset Password
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-error">
                    Invalid or expired reset link. Please request a new password reset.
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <div class="back-link">
                    <a href="login.php">
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($valid_token && !$success): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('passwordStrength');
            
            // Password strength checker
            newPassword.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.length >= 10) strength++;
                if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                strengthIndicator.className = 'password-strength';
                if (strength <= 2) {
                    strengthIndicator.classList.add('strength-weak');
                } else if (strength <= 3) {
                    strengthIndicator.classList.add('strength-medium');
                } else {
                    strengthIndicator.classList.add('strength-strong');
                }
            });
            
            // Password confirmation validation
            confirmPassword.addEventListener('input', function() {
                if (this.value !== newPassword.value) {
                    this.style.borderColor = '#ff6b6b';
                } else {
                    this.style.borderColor = '#43e97b';
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>
