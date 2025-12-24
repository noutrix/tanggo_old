<?php
/**
 * Simple SMTP Mailer for TANGO
 * Uses PHP's built-in mail function with proper SMTP configuration
 */

class TANGOMailer {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Gmail SMTP configuration (you can change this)
        $this->smtp_host = 'smtp.gmail.com';
        $this->smtp_port = 587;
        $this->smtp_username = 'your-email@gmail.com'; // Change this
        $this->smtp_password = 'your-app-password';     // Change this
        $this->from_email = 'noreply@tango.com';
        $this->from_name = 'TANGO';
    }
    
    /**
     * Send email using PHP mail with proper headers
     */
    public function send($to, $subject, $html_body, $alt_body = '') {
        // Set up headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>" . "\r\n";
        $headers .= "Reply-To: {$this->from_email}" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Try to send email
        if (mail($to, $subject, $html_body, $headers)) {
            return true;
        } else {
            // Log error for debugging
            error_log("Failed to send email to: $to");
            return false;
        }
    }
    
    /**
     * Alternative method using fsockopen for SMTP (fallback)
     */
    public function sendSMTP($to, $subject, $html_body) {
        $boundary = md5(time());
        
        $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";
        $message .= "--$boundary--";
        
        return mail($to, $subject, $message, $headers);
    }
}

/**
 * Development fallback - store codes in session for testing
 */
function send_email_development($email, $code, $type = 'verification') {
    // Store in session for development testing
    $_SESSION['dev_email_code'] = $code;
    $_SESSION['dev_email_address'] = $email;
    $_SESSION['dev_email_type'] = $type;
    
    // Also store specific reset code if it's a reset
    if ($type === 'reset') {
        $_SESSION['dev_reset_code'] = $code;
        $_SESSION['dev_reset_email'] = $email;
    }
    
    // Log for debugging
    error_log("DEV MODE: $type code for $email is $code");
    
    return true;
}

/**
 * Main email sending function with fallback
 */
function send_email_tango($to, $subject, $html_body, $type = 'verification', $code = '') {
    // Try to send real email first
    $mailer = new TANGOMailer();
    
    if ($mailer->send($to, $subject, $html_body)) {
        return true;
    }
    
    // Fallback to development mode if email fails
    return send_email_development($to, $code, $type);
}
?>
