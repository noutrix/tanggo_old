<?php
require_once 'config.php';

// Create table if it doesn't exist
try {
    $pdo = get_db();
    
    // Add email_verified field if it doesn't exist
    $pdo->exec("ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER password_hash");
    
    // Create email_verification_tokens table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS email_verification_tokens (
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
    // Tables might already exist, continue
}

$error = '';
$success = '';
$email = sanitize_email($_GET['email'] ?? '');
$full_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('verify_email', 5, 300)) {
        $error = 'Too many attempts. Please try again later.';
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
                    $error = 'Invalid code format. Please enter 6 digits.';
                } else {
                    $pdo = get_db();
                    
                    // Debug: Let's check what tokens exist for this email
                    $debug_stmt = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE email = ? ORDER BY created_at DESC LIMIT 5");
                    $debug_stmt->execute([$email]);
                    $debug_tokens = $debug_stmt->fetchAll();
                    
                    // Debug log the tokens
                    error_log("DEBUG: Looking for code '$code' for email '$email'");
                    error_log("DEBUG: Found " . count($debug_tokens) . " tokens in database");
                    foreach ($debug_tokens as $dt) {
                        error_log("DEBUG: Token code: '{$dt['code']}', expires: {$dt['expires_at']}, used: " . ($dt['used_at'] ? 'yes' : 'no'));
                    }
                    
                    $stmt = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE email = ? AND code = ? AND used_at IS NULL AND expires_at > NOW()");
                    $stmt->execute([$email, $code]);
                    $token = $stmt->fetch();
                    
                    error_log("DEBUG: Query result: " . ($token ? 'Found token' : 'No token found'));
                    
                    if (!$token) {
                        // Check if there's a valid token but maybe the code doesn't match exactly
                        $fallback_stmt = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE email = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
                        $fallback_stmt->execute([$email]);
                        $fallback_token = $fallback_stmt->fetch();
                        
                        if ($fallback_token) {
                            $error = "Invalid or expired code. <br><small>Debug: The most recent valid code is: <strong>{$fallback_token['code']}</strong> (expires: {$fallback_token['expires_at']})</small>";
                        } else {
                            $error = 'Invalid or expired code. No valid tokens found for this email.';
                        }
                    } else {
                        // Mark token as used
                        $stmt = $pdo->prepare("UPDATE email_verification_tokens SET used_at = NOW() WHERE id = ?");
                        $stmt->execute([$token['id']]);
                        
                        // Activate user account
                        $stmt = $pdo->prepare("UPDATE users SET email_verified = TRUE WHERE id = ?");
                        if ($stmt->execute([$token['user_id']])) {
                            $success = 'Account verified successfully! You can now login.';
                            
                            // Get user name for display
                            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                            $stmt->execute([$token['user_id']]);
                            $user = $stmt->fetch();
                            $full_name = $user['full_name'] ?? '';
                        } else {
                            $error = 'Failed to verify account. Please try again.';
                        }
                    }
                }
            } elseif ($action === 'resend_code') {
                $email = sanitize_email($_POST['email'] ?? '');
                
                if (empty($email)) {
                    $error = 'Email is required.';
                } elseif (!validate_email($email)) {
                    $error = 'Invalid email address.';
                } else {
                    $pdo = get_db();
                    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND email_verified = FALSE");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $error = 'Email not found or already verified.';
                    } else {
                        // Delete existing tokens
                        $stmt = $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = ? OR email = ?");
                        $stmt->execute([$user['id'], $email]);
                        
                        // Generate new verification code
                        $token = bin2hex(random_bytes(32));
                        $code = sprintf('%06d', random_int(0, 999999));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        // Store new token
                        $stmt = $pdo->prepare("INSERT INTO email_verification_tokens (user_id, email, token, code, expires_at) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$user['id'], $email, $token, $code, $expires_at]);
                        
                        // Send verification email
                        if (send_verification_email($email, $code, $user['full_name'])) {
                            $success = 'A new verification code has been sent to your email.';
                        } else {
                            $error = 'Failed to send verification email. Please try again.';
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
    <title>Verify Email - TANGO</title>
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
        
        .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--gradient-1);
            position: relative;
            overflow: hidden;
        }
        
        .verify-container::before {
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
        
        .verify-container::after {
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
        
        .verify-card {
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
        
        .verify-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .verify-logo i {
            font-size: 3rem;
            background: var(--gradient-4);
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
        
        .verify-logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .verify-logo p {
            color: #666;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .email-display {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
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
        
        .code-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 8px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            font-family: 'Courier New', monospace;
        }
        
        .code-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            letter-spacing: 10px;
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
            margin-bottom: 1rem;
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
        
        .btn-resend {
            width: 100%;
            background: transparent;
            color: var(--accent-color);
            border: 2px solid var(--accent-color);
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .btn-resend:hover {
            background: var(--accent-color);
            color: white;
            transform: translateY(-2px);
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
            .verify-card {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .code-input {
                font-size: 1.25rem;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-logo">
                <i class="bi bi-envelope-check-fill"></i>
                <h1>Verify Email</h1>
                <p>We have sent a verification code to your email</p>
            </div>
            
            <?php if ($email): ?>
                <div class="email-display">
                    <i class="bi bi-envelope-fill me-2"></i>
                    Code sent to: <?= htmlspecialchars($email) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['dev_email_code']) && isset($_SESSION['dev_email_address']) && $_SESSION['dev_email_address'] === $email && $_SESSION['dev_email_type'] === 'verification'): ?>
                <div class="alert" style="background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; font-weight: 500;">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Email Service Notice:</strong> If you don't receive an email, your verification code is <strong style="font-size: 1.5rem; letter-spacing: 3px;"><?= htmlspecialchars($_SESSION['dev_email_code']) ?></strong>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php if (strpos($success, 'verified successfully') !== false): ?>
                    <div style="text-align: center;">
                        <i class="bi bi-check-circle-fill success-icon"></i>
                        <h3 style="color: var(--success-color); margin-bottom: 1rem;">Welcome, <?= htmlspecialchars($full_name) ?>!</h3>
                        <p style="color: #666; margin-bottom: 2rem;">Your account has been verified successfully. You can now login with your credentials.</p>
                        <a href="login.php" class="btn-submit" style="display: inline-block; text-decoration: none;">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            Go to Login
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!$success || strpos($success, 'verified successfully') === false): ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="verify_code">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="code">6-Digit Verification Code</label>
                        <input type="text" id="code" name="code" class="code-input" 
                               value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required 
                               placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                               autocomplete="off">
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Verify Email
                    </button>
                </form>
                
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="resend_code">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    
                    <button type="submit" class="btn-resend">
                        <i class="bi bi-arrow-clockwise me-2"></i>
                        Resend Code
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
    
    <?php if (!$success || strpos($success, 'verified successfully') === false): ?>
    <script>
        // Auto-focus and select code input
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('code');
            if (codeInput) {
                codeInput.focus();
                
                // Only allow numbers
                codeInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
                
                // Auto-submit when 6 digits entered
                codeInput.addEventListener('input', function(e) {
                    if (this.value.length === 6) {
                        setTimeout(() => {
                            this.form.submit();
                        }, 100);
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
