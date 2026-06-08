<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();
Auth::check();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['reply' => 'Invalid request.']);
    exit;
}

$message = trim($_POST['message'] ?? '');
if (!$message) {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

$history = [];
if (!empty($_POST['history'])) {
    $decoded = json_decode($_POST['history'], true);
    if (is_array($decoded)) {
        $history = array_slice($decoded, -10); // last 10 exchanges max
    }
}

$user = Auth::user();
$db   = Database::getInstance();

// Inventory context
$products = $db->query(
    'SELECT p.name, p.sku, p.stock_quantity AS quantity, p.unit, p.low_stock_threshold,
            COALESCE(c.name, "Uncategorized") as category
     FROM products p LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.is_active = 1
     ORDER BY p.name'
)->fetchAll(PDO::FETCH_ASSOC);

$inventoryLines = [];
foreach ($products as $p) {
    $status = $p['quantity'] == 0
        ? 'OUT OF STOCK'
        : ($p['quantity'] <= $p['low_stock_threshold'] ? 'LOW STOCK' : 'in stock');
    $inventoryLines[] = "- {$p['name']} (SKU: {$p['sku']}, Category: {$p['category']}): "
        . "{$p['quantity']} {$p['unit']} [{$status}, reorder at {$p['low_stock_threshold']}]";
}
$inventoryContext = implode("\n", $inventoryLines) ?: 'No products found.';

// Orders context
if (Auth::isAdmin()) {
    $rows = $db->query(
        'SELECT o.order_no, u.name AS placed_by, o.status,
                o.total_amount, o.created_at,
                COUNT(oi.id) AS item_count
         FROM orders o
         JOIN users u ON o.staff_id = u.id
         LEFT JOIN order_items oi ON oi.order_id = o.id
         GROUP BY o.id
         ORDER BY o.created_at DESC LIMIT 30'
    )->fetchAll(PDO::FETCH_ASSOC);

    $orderLines = [];
    foreach ($rows as $r) {
        $orderLines[] = "- {$r['order_no']} by {$r['placed_by']}: "
            . "{$r['status']}, {$r['item_count']} item(s), ₱"
            . number_format($r['total_amount'], 2)
            . ', placed ' . date('M d Y', strtotime($r['created_at']));
    }
    $ordersLabel   = 'RECENT ORDERS (last 30 across all staff)';
    $ordersContext = implode("\n", $orderLines) ?: 'No orders found.';
} else {
    $stmt = $db->prepare(
        'SELECT o.order_no, o.status, o.total_amount, o.created_at,
                COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.staff_id = ?
         GROUP BY o.id
         ORDER BY o.created_at DESC LIMIT 15'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orderLines = [];
    foreach ($rows as $r) {
        $orderLines[] = "- {$r['order_no']}: {$r['status']}, {$r['item_count']} item(s), "
            . '₱' . number_format($r['total_amount'], 2)
            . ', placed ' . date('M d Y', strtotime($r['created_at']));
    }
    $ordersLabel   = "YOUR RECENT ORDERS (last 15)";
    $ordersContext = implode("\n", $orderLines) ?: 'No orders placed yet.';
}

if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === 'your-api-key-here') {
    echo json_encode(['reply' => '⚠️ StockBot needs an Anthropic API key to work. Ask your admin to add it in config/database.php.']);
    exit;
}

$systemPrompt =
    "You are StockBot, a friendly and concise inventory assistant for StockWise. " .
    "You help " . ($user['role'] === 'admin' ? 'admins' : 'staff members') . " answer questions about stock and orders. " .
    "Always answer based on the live data below. If something is not in the data, say so honestly. " .
    "Format numbers with commas. Use ₱ for currency. Today is " . date('F j, Y') . ".\n\n" .
    "CURRENT INVENTORY:\n" . $inventoryContext . "\n\n" .
    $ordersLabel . ":\n" . $ordersContext;

// Build messages array for multi-turn
$messages = [];
foreach ($history as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], ['user', 'assistant'])) {
        $messages[] = ['role' => $h['role'], 'content' => (string)$h['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

$payload = [
    'model'      => 'claude-haiku-4-5-20251001',
    'max_tokens' => 512,
    'system'     => [
        [
            'type'          => 'text',
            'text'          => $systemPrompt,
            'cache_control' => ['type' => 'ephemeral'],
        ]
    ],
    'messages' => $messages,
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: '         . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: prompt-caching-2024-07-31',
    ],
    CURLOPT_TIMEOUT => 25,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode(['reply' => 'Sorry, I couldn\'t reach the AI service right now. Please try again.']);
    exit;
}

$data  = json_decode($response, true);
$reply = $data['content'][0]['text'] ?? 'Sorry, I couldn\'t generate a response.';

echo json_encode(['reply' => $reply]);
