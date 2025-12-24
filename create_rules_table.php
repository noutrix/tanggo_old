<?php
require_once 'config.php';

$pdo = get_db();

// Create product_rules table
$sql = "CREATE TABLE IF NOT EXISTS product_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    rule_title VARCHAR(255) NOT NULL,
    rule_content TEXT NOT NULL,
    rule_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";

try {
    $pdo->exec($sql);
    echo "Product rules table created successfully!";
    
    // Add some sample rules for each product
    $sampleRules = [
        1 => [
            ['Group creation year must be between 2016 and 2022', 'Group creation date must be clearly visible in Telegram info. Group must contain at least 5 messages from the creation year. Group message permission must be enabled. Group must be transferable to admin after approval.', 1],
            ['Group must be private and contain real messages', 'Group must be a Private Telegram Group with real messages, not empty or fake chats. Chat history for new members must be ON (Visible).', 2],
            ['Ownership transfer required', 'You must be able to transfer group ownership to our admin after approval. Group must not violate Telegram rules (no illegal, scam, or banned content).', 3]
        ],
        2 => [
            ['Group creation year must be 2023', 'Group creation date must be visible and verifiable. Group must contain at least 5 messages from 2023. Group must be private and chat history visible.', 1],
            ['Message permissions and transfer', 'Message permission must be ON. Ownership transfer must be possible immediately after approval. Multiple groups must be submitted using Telegram folder link.', 2]
        ],
        3 => [
            ['Group creation date: January to March 2024', 'Group creation date must be January to March 2024 only. Group creation date must be shown clearly in Telegram info. Group must contain minimum 5 messages from creation period.', 1],
            ['Group requirements', 'Group must not have fake messages. Group must be private with visible chat history. Group message permission must be ON. Ownership transfer must be allowed without delay.', 2]
        ],
        4 => [
            ['Group creation date: April 2024 only', 'Group creation date must be April 2024 only. Creation date must be clearly visible in group details. Group must contain minimum 5 messages from April 2024.', 1],
            ['Group specifications', 'Group must be private and chat history visible. Message permission must be ON. Group must be 100% transferable to admin. No spam, illegal, adult, or restricted content allowed.', 2]
        ]
    ];
    
    foreach ($sampleRules as $productId => $rules) {
        foreach ($rules as $rule) {
            $stmt = $pdo->prepare("INSERT INTO product_rules (product_id, rule_title, rule_content, rule_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$productId, $rule[0], $rule[1], $rule[2]]);
        }
    }
    
    echo "<br>Sample rules added successfully!";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
