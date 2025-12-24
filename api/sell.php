<?php
require_once '../config.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (is_rate_limited('sell', 3, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait.']);
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$product_id = (int)($_POST['product_id'] ?? 0);
$task_name = sanitize($_POST['task_name'] ?? '');
$group_links = trim($_POST['group_links'] ?? '');

if (empty($product_id) || empty($task_name) || empty($group_links)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$pdo = get_db();

// Validate product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) {
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

// Process group links
$links = array_filter(array_map('trim', explode("\n", $group_links)));
$group_count = count($links);

if ($group_count === 0) {
    echo json_encode(['success' => false, 'error' => 'No valid group links provided']);
    exit;
}

// Validate links format (basic check)
foreach ($links as $link) {
    if (!filter_var($link, FILTER_VALIDATE_URL) || strpos($link, 't.me/') === false) {
        echo json_encode(['success' => false, 'error' => 'Invalid Telegram link: ' . htmlspecialchars($link)]);
        exit;
    }
}

// Calculate pricing
$price_per_group = $product['base_price'];
$bonus_per_group = 0.0;

if ($group_count <= BULK_BONUS_THRESHOLD && $group_count > 1) {
    $bonus_per_group = BULK_BONUS_AMOUNT;
}

$total_amount = ($price_per_group + $bonus_per_group) * $group_count;

// Create order
$stmt = $pdo->prepare("
    INSERT INTO orders (user_id, product_id, task_name, group_links, group_count, price_per_group, bonus_per_group, total_amount, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
");
$stmt->execute([
    $_SESSION['user_id'],
    $product_id,
    $task_name,
    implode("\n", $links),
    $group_count,
    $price_per_group,
    $bonus_per_group,
    $total_amount
]);

$order_id = $pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'order_id' => $order_id,
    'message' => 'Order submitted successfully'
]);
?>
