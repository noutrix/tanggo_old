<?php
require_once 'config.php';

$error = '';
$success = '';
$email = '';
$code = '';
$new_password = '';
$confirm_password = '';
$step = 1; // 1 = verify code, 2 = reset password

// Get email from URL or form
$email = sanitize_email($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('reset_password', 3, 300)) {
        $error = 'Too many requests. Please try again later.';
    } else {
        $csrf = $_POST['csrf'] ?? '';
        if (!verify_csrf($csrf)) {
            $error = 'Invalid request.';
        } else {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'verify_code') {
                $email = sanitize_email($_POST['email'] ?? '');
                $code = $_POST['code'] ?? '';
                
                if (empty($email) || empty($code)) {
                    $error = 'Email and code are required.';
                } elseif (!validate_email($email)) {
                    $error = 'Invalid email address.';
                } elseif (!preg_match('/^\d{6}$/', $code)) {
                    $error = 'Invalid code format.';
                } else {
                    $pdo = get_db();
                    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? AND code = ? AND used_at IS NULL AND expires_at > NOW()");
                    $stmt->execute([$email, $code]);
                    $token = $stmt->fetch();
                    
                    if (!$token) {
                        $error = 'Invalid or expired code.';
                    } else {
                        $step = 2; // Move to password reset step
                    }
                }
            } elseif ($action === 'reset_password') {
                $email = sanitize_email($_POST['email'] ?? '');
                $code = $_POST['code'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($email) || empty($code) || empty($new_password) || empty($confirm_password)) {
                    $error = 'All fields are required.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    $pdo = get_db();
                    $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? AND code = ? AND used_at IS NULL AND expires_at > NOW()");
                    $stmt->execute([$email, $code]);
                    $token = $stmt->fetch();
                    
                    if (!$token) {
                        $error = 'Invalid or expired session.';
                    } else {
                        // Update user password
                        $hash = hash_password($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        if ($stmt->execute([$hash, $token['user_id']])) {
                            // Mark token as used
                            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
                            $stmt->execute([$token['id']]);
                            
                            $success = 'Password reset successfully! You can now login with your new password.';
                            $step = 3; // Success step
                        } else {
                            $error = 'Failed to reset password. Please try again.';
                        }
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
    <title>Reset Password - TANGO</title>
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
        
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--gradient-1);
            position: relative;
            overflow: hidden;
        }
        
        .reset-container::before {
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
        
        .reset-container::after {
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
        
        .reset-card {
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
        
        .reset-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-logo i {
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
        
        .reset-logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .reset-logo p {
            color: #666;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .reset-form {
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
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 480px) {
            .reset-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-logo">
                <i class="bi bi-shield-lock-fill"></i>
                <h1>Reset Password</h1>
                <?php if ($step == 1): ?>
                    <p>Enter the code sent to your email</p>
                <?php elseif ($step == 2): ?>
                    <p>Enter your new password</p>
                <?php else: ?>
                    <p>Password successfully reset</p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <form method="POST" class="reset-form">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="verify_code">
                    
                    <div class="form-group-custom">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($email) ?>" required 
                               placeholder="Enter your email address">
                    </div>
                    
                    <div class="form-group-custom">
                        <label class="form-label" for="code">Reset Code</label>
                        <input type="text" id="code" name="code" class="form-control" 
                               value="<?= htmlspecialchars($code) ?>" required 
                               placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Verify Code
                    </button>
                </form>
                
            <?php elseif ($step == 2): ?>
                <form method="POST" class="reset-form">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
                    
                    <div class="form-group-custom">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               required placeholder="Enter new password (min 6 characters)">
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
                
            <?php elseif ($step == 3): ?>
                <div style="text-align: center;">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                    <h3 style="color: var(--success-color); margin-bottom: 1rem;">Success!</h3>
                    <p style="color: #666; margin-bottom: 2rem;">Your password has been reset successfully. You can now login with your new password.</p>
                    <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none;">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Go to Login
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if ($step < 3): ?>
                <div class="back-link">
                    <a href="login.php">
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
