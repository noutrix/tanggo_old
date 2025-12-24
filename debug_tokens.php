<?php
require_once 'config.php';

echo "<h1>TANGO Token Debug Tool</h1>";

$pdo = get_db();

// Show all verification tokens
echo "<h2>Email Verification Tokens</h2>";
$stmt = $pdo->query("SELECT * FROM email_verification_tokens ORDER BY created_at DESC LIMIT 10");
$tokens = $stmt->fetchAll();

if ($tokens) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Email</th><th>Code</th><th>Expires</th><th>Used</th><th>Created</th></tr>";
    foreach ($tokens as $token) {
        echo "<tr>";
        echo "<td>{$token['id']}</td>";
        echo "<td>{$token['user_id']}</td>";
        echo "<td>{$token['email']}</td>";
        echo "<td><strong style='color: blue; font-size: 1.2em;'>{$token['code']}</strong></td>";
        echo "<td>{$token['expires_at']}</td>";
        echo "<td>" . ($token['used_at'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$token['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No verification tokens found.</p>";
}

// Show all users
echo "<h2>Users</h2>";
$stmt = $pdo->query("SELECT id, full_name, email, email_verified, created_at FROM users ORDER BY created_at DESC LIMIT 10");
$users = $stmt->fetchAll();

if ($users) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Verified</th><th>Created</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>" . ($user['email_verified'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$user['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found.</p>";
}

// Show password reset tokens
echo "<h2>Password Reset Tokens</h2>";
$stmt = $pdo->query("SELECT * FROM password_reset_tokens ORDER BY created_at DESC LIMIT 10");
$reset_tokens = $stmt->fetchAll();

if ($reset_tokens) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>User ID</th><th>Email</th><th>Code</th><th>Expires</th><th>Used</th><th>Created</th></tr>";
    foreach ($reset_tokens as $token) {
        echo "<tr>";
        echo "<td>{$token['id']}</td>";
        echo "<td>{$token['user_id']}</td>";
        echo "<td>{$token['email']}</td>";
        echo "<td><strong style='color: red; font-size: 1.2em;'>{$token['code']}</strong></td>";
        echo "<td>{$token['expires_at']}</td>";
        echo "<td>" . ($token['used_at'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$token['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No password reset tokens found.</p>";
}

// Session debug
echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><a href='register.php'>Register New User</a> | <a href='login.php'>Login</a> | <a href='verify-email.php'>Verify Email</a></p>";
?>
