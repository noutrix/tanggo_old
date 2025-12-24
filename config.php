<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'tango_tg_sell');
define('DB_USER', 'root');
define('DB_PASS', '');

// Admin credentials (fixed)
define('ADMIN_EMAIL', 'noutrix@gmail.com');
define('ADMIN_PASSWORD', 'Noufelalways');

// Security
define('HASH_COST', 10);
define('SESSION_NAME', 'tango_session');
define('CSRF_TOKEN_NAME', 'tango_csrf');

// App settings
define('MIN_WITHDRAW', 5.0);
define('WITHDRAW_FEE', 0.0);
define('BULK_BONUS_THRESHOLD', 10);
define('BULK_BONUS_AMOUNT', 0.5);

// Initialize session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_name(SESSION_NAME);
session_start();

// CSRF token helper
function csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf($token) {
    return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

// Rate limiting (simple session-based)
function is_rate_limited($action = 'default', $limit = 5, $window = 60) {
    $key = 'rate_' . $action;
    $now = time();
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key] = array_filter($_SESSION[$key], function($t) use ($now, $window) {
        return $t > $now - $window;
    });
    if (count($_SESSION[$key]) >= $limit) {
        return true;
    }
    $_SESSION[$key][] = $now;
    return false;
}

// Database connection
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Auth helpers
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function is_admin() {
    return !empty($_SESSION['admin_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        header('Location: admin/login.php');
        exit;
    }
}

// Password helpers
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Format USDT
function format_usdt($amount) {
    // Show 2 decimal places for display, 8 for precision
    if ($amount == 0) {
        return number_format($amount, 2, '.', '');
    }
    return number_format($amount, 2, '.', '');
}

// Log admin action
function log_admin_action($admin_id, $action, $target_type, $target_id, $details = '') {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$admin_id, $action, $target_type, $target_id, $details]);
}

// Send email function
function send_reset_email($email, $code) {
    require_once __DIR__ . '/mailer.php';
    
    $subject = "TANGO - Password Reset Code";
    
    // Email body
    $message = "
    <html>
    <head>
        <title>TANGO Password Reset</title>
    </head>
    <body style='font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #667eea; font-size: 2rem; margin: 0;'>TANGO</h1>
                <p style='color: #666; margin: 5px 0 0 0;'>Password Reset Request</p>
            </div>
            
            <div style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 15px; margin: 20px 0; text-align: center;'>
                <h2 style='margin: 0 0 10px 0; font-size: 1.5rem;'>Your Reset Code</h2>
                <div style='font-size: 2.5rem; font-weight: bold; letter-spacing: 5px; background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; display: inline-block; margin: 10px 0;'>
                    $code
                </div>
            </div>
            
            <div style='color: #666; line-height: 1.6;'>
                <p style='margin: 15px 0;'>Hello,</p>
                <p style='margin: 15px 0;'>You requested to reset your password for your TANGO account. Use the code above to proceed with the password reset.</p>
                <p style='margin: 15px 0;'><strong>Important:</strong></p>
                <ul style='margin: 15px 0; padding-left: 20px;'>
                    <li>This code will expire in 15 minutes</li>
                    <li>Never share this code with anyone</li>
                    <li>If you didn't request this, please ignore this email</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #999; font-size: 0.875rem; margin: 0;'>© 2024 TANGO - Old TG Group Buy</p>
                <p style='color: #999; font-size: 0.875rem; margin: 5px 0 0 0;'>Sell your old Telegram groups and earn USDT</p>
            </div>
        </div>
    </body>
    </html>";
    
    return send_email_tango($email, $subject, $message, 'reset', $code);
}

// Send email verification function
function send_verification_email($email, $code, $full_name) {
    require_once __DIR__ . '/mailer.php';
    
    $subject = "TANGO - Verify Your Email Address";
    
    // Email body
    $message = "
    <html>
    <head>
        <title>TANGO Email Verification</title>
    </head>
    <body style='font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #667eea; font-size: 2rem; margin: 0;'>TANGO</h1>
                <p style='color: #666; margin: 5px 0 0 0;'>Email Verification</p>
            </div>
            
            <div style='background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 15px; margin: 20px 0; text-align: center;'>
                <h2 style='margin: 0 0 10px 0; font-size: 1.5rem;'>Welcome, $full_name!</h2>
                <p style='margin: 0 0 15px 0;'>Please verify your email address to activate your account</p>
                <div style='font-size: 2.5rem; font-weight: bold; letter-spacing: 5px; background: rgba(255,255,255,0.2); padding: 15px; border-radius: 10px; display: inline-block; margin: 10px 0;'>
                    $code
                </div>
            </div>
            
            <div style='color: #666; line-height: 1.6;'>
                <p style='margin: 15px 0;'>Hello $full_name,</p>
                <p style='margin: 15px 0;'>Thank you for registering with TANGO! To complete your registration and activate your account, please use the verification code above.</p>
                <p style='margin: 15px 0;'><strong>Important:</strong></p>
                <ul style='margin: 15px 0; padding-left: 20px;'>
                    <li>This code will expire in 10 minutes</li>
                    <li>Never share this code with anyone</li>
                    <li>If you didn't create an account, please ignore this email</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                <p style='color: #999; font-size: 0.875rem; margin: 0;'>© 2024 TANGO - Old TG Group Buy</p>
                <p style='color: #999; font-size: 0.875rem; margin: 5px 0 0 0;'>Sell your old Telegram groups and earn USDT</p>
            </div>
        </div>
    </body>
    </html>";
    
    return send_email_tango($email, $subject, $message, 'verification', $code);
}

// Simple input sanitization
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitize_email($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
?>
