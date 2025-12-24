CREATE DATABASE IF NOT EXISTS tango_tg_sell;
USE tango_tg_sell;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance_usdt DECIMAL(20,8) DEFAULT 0.00000000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins table (fixed credentials)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table (editable by admin)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    year_range VARCHAR(50) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    bonus_rule VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Product Rules table
CREATE TABLE product_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    rule_title VARCHAR(255) NOT NULL,
    rule_content TEXT NOT NULL,
    rule_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders/Tasks table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    group_links TEXT NOT NULL,
    group_count INT NOT NULL,
    price_per_group DECIMAL(10,2) NOT NULL,
    bonus_per_group DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(20,8) NOT NULL,
    status ENUM('pending', 'transfer_owner', 'fail', 'completed') DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Withdraw requests
CREATE TABLE withdraws (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    wallet_type ENUM('TRC20', 'Binance') NOT NULL,
    wallet_address VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Balance change logs (admin adjustments)
CREATE TABLE balance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount_change DECIMAL(20,8) NOT NULL,
    balance_before DECIMAL(20,8) NOT NULL,
    balance_after DECIMAL(20,8) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT
);

-- Admin action logs
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type ENUM('user', 'order', 'withdraw', 'product') NOT NULL,
    target_id INT NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT
);

-- Insert default admin (email: noutrix@gmail.com, password: Noufelalways)
-- Password hash for 'Noufelalways' using password_hash()
INSERT INTO admins (email, password_hash) VALUES 
('noutrix@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert default products (editable by admin)
INSERT INTO products (name, year_range, base_price, bonus_rule, is_active) VALUES 
('2016–2022 Group Sell', '2016–2022', 12.00, NULL, TRUE),
('2023 Group Sell', '2023', 8.50, NULL, TRUE),
('2024 (01–03) Group Sell', '2024 (01–03)', 5.00, NULL, TRUE),
('2024 (04) Group Sell', '2024 (04)', 4.00, NULL, TRUE);
