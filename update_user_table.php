<?php
require_once 'config.php';

try {
    $pdo = get_db();
    
    // Add full_name field to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(255) AFTER email");
    
    // Add email_verified field to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER password_hash");
    
    // Create email_verification_tokens table
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
    
    echo "Database tables updated successfully!\n";
    echo "- Added full_name field to users table\n";
    echo "- Added email_verified field to users table\n";
    echo "- Created email_verification_tokens table\n";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
