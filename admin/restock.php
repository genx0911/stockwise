<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::requireRole('admin');

// ── AJAX: call Claude API ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if (ANTHROPIC_API_KEY === 'your-api-key-here') {
        echo json_encode(['success' => false, 'message' => 'API key not configured. Edit config/database.php and set ANTHROPIC_API_KEY.']);
        exit;
    }

    $db = Database::getInstance();

    // Gather all products with demand data
    $products = $db->query('
        SELECT p.name, p.sku, p.unit, p.stock_quantity, p.low_stock_threshold,
               c.name AS category,
               COALESCE(SUM(oi.quantity), 0) AS total_fulfilled,
               COUNT(DISTINCT o.id) AS fulfilled_orders
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN order_items oi ON oi.product_id = p.id
        LEFT JOIN orders o ON o.id = oi.order_id AND o.status = "fulfilled"
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY p.stock_quantity ASC
    ')->fetchAll();

    // Pending demand (approved orders not yet fulfilled)
    $pending = $db->query('
        SELECT p.name, SUM(oi.quantity) AS pending_qty
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        JOIN orders o ON o.id = oi.order_id
        WHERE o.status IN ("pending","approved")
        GROUP BY p.id
    ')->fetchAll(PDO::FETCH_KEY_PAIR);

    // Format product list for prompt
    $lines = [];
    foreach ($products as $p) {
        $pendingQty = $pending[$p['name']] ?? 0;
        $gap  = max(0, $p['low_stock_threshold'] - $p['stock_quantity']);
        $line = "- {$p['name']} (SKU: {$p['sku']}, Category: {$p['category']}): "
              . "stock={$p['stock_quantity']} {$p['unit']}, "
              . "min_threshold={$p['low_stock_threshold']}, "
              . "fulfilled_qty={$p['total_fulfilled']}, "
              . "pending_demand={$pendingQty}";
        $lines[] = $line;
    }
    $productList = implode("\n", $lines);

    $prompt = <<<PROMPT
You are an inventory analyst for a school supply store called StockWise.

Here is the current inventory status (stock, minimum threshold, historical fulfilled quantity, and pending demand):

$productList

Tasks:
1. Identify which products need restocking most urgently (below threshold or will be after pending demand).
2. For each product that needs restocking, suggest a specific reorder quantity that covers at least 2x the minimum threshold or 1.5x recent demand — whichever is higher — and briefly explain why.
3. Flag any products with zero stock as CRITICAL.
4. Give a short executive summary (2-3 sentences) at the top.

Format your response in clean HTML using Bootstrap 5 classes only (no inline styles except color). Use a table for the restock list with columns: Product, Current Stock, Suggested Reorder Qty, Priority (Critical/High/Medium), Reason. Put the executive summary in a <p> tag before the table.
PROMPT;

    // Call Claude API with prompt caching on the static product context
    $payload = [
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 1024,
        'system' => [
            [
                'type' => 'text',
                'text' => 'You are an inventory analyst assistant. Respond with clean Bootstrap 5 HTML only — no markdown fences, no extra commentary outside the HTML.',
                'cache_control' => ['type' => 'ephemeral'],
            ]
        ],
        'messages' => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: prompt-caching-2024-07-31',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpCode !== 200) {
        $err = json_decode($raw, true);
        echo json_encode(['success' => false, 'message' => $err['error']['message'] ?? 'API request failed (HTTP ' . $httpCode . ').']);
        exit;
    }

    $data = json_decode($raw, true);
    $html = $data['content'][0]['text'] ?? '';

    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

// ── Page ─────────────────────────────────────────────────────────────────────
$db      = Database::getInstance();
$lowStock = $db->query('
    SELECT COUNT(*) FROM products
    WHERE is_active=1 AND stock_quantity <= low_stock_threshold
')->fetchColumn();

$pageTitle  = 'Restock Suggestions';
$activePage = 'restock';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="page-title">AI Restock Suggestions</h1>
        <div class="page-subtitle">Claude analyses your inventory and demand to recommend reorder quantities</div>
    </div>
    <button class="btn btn-primary" id="analyseBtn" onclick="runAnalysis()">
        <i class="bi bi-stars me-1"></i> Analyse Now
    </button>
</div>

<?php if (ANTHROPIC_API_KEY === 'your-api-key-here'): ?>
<div class="alert alert-warning d-flex gap-2 align-items-center" style="border-radius:10px;">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <strong>API key not set.</strong>
        Open <code>config/database.php</code> and replace <code>your-api-key-here</code> with your
        <a href="https://console.anthropic.com" target="_blank">Anthropic API key</a>.
    </div>
</div>
<?php endif; ?>

<!-- Stats bar -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card border-red">
            <div class="kpi-label">Products Below Threshold</div>
            <div class="kpi-value text-danger"><?= $lowStock ?></div>
            <div class="kpi-sub">Need attention</div>
        </div>
    </div>
    <div class="col-md-9 d-flex align-items-center">
        <div style="font-size:13.5px;color:#64748B;line-height:1.7;">
            Click <strong>Analyse Now</strong> to send your current inventory snapshot to Claude AI.
            It will identify urgent restocks, suggest order quantities based on demand history,
            and flag critical out-of-stock items.
        </div>
    </div>
</div>

<!-- Result area -->
<div id="resultArea" style="display:none;">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-stars me-2 text-primary"></i>AI Analysis</span>
            <span id="resultTime" class="text-muted" style="font-size:12px;"></span>
        </div>
        <div class="card-body" id="resultBody"></div>
    </div>
</div>

<!-- Loading state -->
<div id="loadingArea" style="display:none;">
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3" style="width:2.5rem;height:2.5rem;"></div>
            <div class="fw-600" style="font-size:14px;">Analysing inventory…</div>
            <div class="text-muted" style="font-size:13px;margin-top:6px;">Claude is reviewing your stock levels and demand history</div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function runAnalysis() {
    document.getElementById('resultArea').style.display  = 'none';
    document.getElementById('loadingArea').style.display = '';
    document.getElementById('analyseBtn').disabled = true;

    const fd = new FormData();
    fd.append('ajax', 1);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            document.getElementById('loadingArea').style.display = 'none';
            document.getElementById('analyseBtn').disabled = false;

            if (res.success) {
                document.getElementById('resultBody').innerHTML = res.html;
                document.getElementById('resultTime').textContent =
                    'Generated ' + new Date().toLocaleTimeString();
                document.getElementById('resultArea').style.display = '';
            } else {
                showToast(res.message || 'Analysis failed.', 'danger');
            }
        })
        .catch(() => {
            document.getElementById('loadingArea').style.display = 'none';
            document.getElementById('analyseBtn').disabled = false;
            showToast('Network error. Please try again.', 'danger');
        });
}
JS;
include __DIR__ . '/../includes/footer.php';
?>
