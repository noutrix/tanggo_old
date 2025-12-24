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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (is_rate_limited('verify_code', 5, 300)) {
        $error = 'Too many attempts. Please try again later.';
    } else {
        $csrf = $_POST['csrf'] ?? '';
        if (!verify_csrf($csrf)) {
            $error = 'Invalid request.';
        } else {
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
                $debug_stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? ORDER BY created_at DESC LIMIT 5");
                $debug_stmt->execute([$email]);
                $debug_tokens = $debug_stmt->fetchAll();
                
                // Debug log the tokens
                error_log("RESET DEBUG: Looking for code '$code' for email '$email'");
                error_log("RESET DEBUG: Found " . count($debug_tokens) . " reset tokens in database");
                foreach ($debug_tokens as $dt) {
                    error_log("RESET DEBUG: Token code: '{$dt['code']}', expires: {$dt['expires_at']}, used: " . ($dt['used_at'] ? 'yes' : 'no'));
                }
                
                $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? AND code = ? AND used_at IS NULL AND expires_at > NOW()");
                $stmt->execute([$email, $code]);
                $token = $stmt->fetch();
                
                error_log("RESET DEBUG: Query result: " . ($token ? 'Found token' : 'No token found'));
                
                if (!$token) {
                    // Check if there's a valid token but maybe the code doesn't match exactly
                    $fallback_stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
                    $fallback_stmt->execute([$email]);
                    $fallback_token = $fallback_stmt->fetch();
                    
                    if ($fallback_token) {
                        $error = "Invalid or expired code. <br><small>Debug: The most recent valid reset code is: <strong>{$fallback_token['code']}</strong> (expires: {$fallback_token['expires_at']})</small>";
                    } else {
                        $error = 'Invalid or expired code. No valid reset tokens found for this email.';
                    }
                } else {
                    // Code is valid, redirect to new password page
                    header("Location: new-password.php?email=" . urlencode($email) . "&token=" . urlencode($token['token']));
                    exit;
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
    <title>Enter Reset Code - TANGO</title>
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
        
        .code-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--gradient-1);
            position: relative;
            overflow: hidden;
        }
        
        .code-container::before {
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
        
        .code-container::after {
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
        
        .code-card {
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
        
        .code-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .code-logo i {
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
        
        .code-logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .code-logo p {
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
        
        .resend-link {
            text-align: center;
            margin-top: 1rem;
        }
        
        .resend-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .resend-link a:hover {
            transform: scale(1.05);
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        @media (max-width: 480px) {
            .code-card {
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
    <div class="code-container">
        <div class="code-card">
            <div class="code-logo">
                <i class="bi bi-envelope-check-fill"></i>
                <h1>Enter Reset Code</h1>
                <p>Check your email for the 6-digit code</p>
            </div>
            
            <?php if ($email): ?>
                <div class="email-display">
                    <i class="bi bi-envelope-fill me-2"></i>
                    Code sent to: <?= htmlspecialchars($email) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['dev_reset_code']) && isset($_SESSION['dev_reset_email']) && $_SESSION['dev_reset_email'] === $email): ?>
                <div class="alert" style="background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; font-weight: 500;">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Email Service Notice:</strong> If you don't receive an email, your reset code is <strong style="font-size: 1.5rem; letter-spacing: 3px;"><?= htmlspecialchars($_SESSION['dev_reset_code']) ?></strong>
                </div>
            <?php elseif (isset($_SESSION['dev_email_code']) && isset($_SESSION['dev_email_address']) && $_SESSION['dev_email_address'] === $email && $_SESSION['dev_email_type'] === 'reset'): ?>
                <div class="alert" style="background: linear-gradient(135deg, #43e97b, #38f9d7); color: white; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; font-weight: 500;">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Email Service Notice:</strong> If you don't receive an email, your reset code is <strong style="font-size: 1.5rem; letter-spacing: 3px;"><?= htmlspecialchars($_SESSION['dev_email_code']) ?></strong>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="code">6-Digit Reset Code</label>
                    <input type="text" id="code" name="code" class="code-input" 
                           value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" required 
                           placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                           autocomplete="off">
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Verify Code
                </button>
            </form>
            
            <div class="resend-link">
                <a href="forgot-password.php">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Didn't receive code? Resend
                </a>
            </div>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus and select code input
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('code');
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
        });
    </script>
</body>
</html>
