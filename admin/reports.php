<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::requireRole('admin');

$db = Database::getInstance();

// ── Date range ───────────────────────────────────────────────────────────────
$preset   = $_GET['range'] ?? '30';
$dateFrom = $_GET['from']  ?? '';
$dateTo   = $_GET['to']    ?? '';

if ($dateFrom && $dateTo) {
    $from = $dateFrom;
    $to   = $dateTo . ' 23:59:59';
    $preset = 'custom';
} else {
    $to = date('Y-m-d 23:59:59');
    switch ($preset) {
        case '7':   $from = date('Y-m-d', strtotime('-6 days'));  break;
        case '90':  $from = date('Y-m-d', strtotime('-89 days')); break;
        case 'all': $from = '2000-01-01'; break;
        default:    $preset = '30'; $from = date('Y-m-d', strtotime('-29 days')); break;
    }
    $dateFrom = substr($from, 0, 10);
    $dateTo   = substr($to, 0, 10);
}

// ── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');

    // Helper: escape a CSV field
    $csvField = fn($v) => '"' . str_replace('"', '""', (string)$v) . '"';

    if ($type === 'orders') {
        header('Content-Disposition: attachment; filename="orders_' . $dateFrom . '_' . $dateTo . '.csv"');
        $rows = $db->prepare('
            SELECT o.order_no, u.name AS staff, o.status,
                   COALESCE(SUM(oi.quantity * oi.unit_price),0) AS total,
                   o.created_at
            FROM orders o
            JOIN users u ON u.id = o.staff_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY o.id, o.order_no, u.name, o.status, o.created_at
            ORDER BY o.created_at DESC
        ');
        $rows->execute([$from, $to]);
        echo "Order #,Staff,Status,Total,Date\n";
        foreach ($rows->fetchAll() as $r) {
            echo implode(',', [
                $csvField($r['order_no']), $csvField($r['staff']),
                $csvField($r['status']),   $csvField(number_format($r['total'],2)),
                $csvField($r['created_at'])
            ]) . "\n";
        }
    } elseif ($type === 'inventory') {
        header('Content-Disposition: attachment; filename="inventory_' . date('Y-m-d') . '.csv"');
        $rows = $db->query('
            SELECT p.sku, p.name, COALESCE(c.name,\'\') AS category,
                   p.unit, p.price, p.stock_quantity, p.low_stock_threshold,
                   (p.stock_quantity * p.price) AS stock_value
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active=1 ORDER BY p.name
        ');
        echo "SKU,Product,Category,Unit,Price,Stock,Min Threshold,Stock Value\n";
        foreach ($rows->fetchAll() as $r) {
            echo implode(',', [
                $csvField($r['sku']),  $csvField($r['name']),     $csvField($r['category']),
                $csvField($r['unit']), $csvField($r['price']),    $csvField($r['stock_quantity']),
                $csvField($r['low_stock_threshold']),              $csvField(number_format($r['stock_value'],2))
            ]) . "\n";
        }
    }
    exit;
}

// ── KPIs ─────────────────────────────────────────────────────────────────────
$kpis = $db->prepare("
    SELECT
        COUNT(DISTINCT o.id) AS total_orders,
        COUNT(DISTINCT CASE WHEN o.status='fulfilled' THEN o.id END) AS fulfilled_orders,
        COUNT(DISTINCT CASE WHEN o.status='pending'   THEN o.id END) AS pending_orders,
        COUNT(DISTINCT CASE WHEN o.status='rejected'  THEN o.id END) AS rejected_orders,
        COALESCE(SUM(CASE WHEN o.status='fulfilled' THEN oi.quantity * oi.unit_price END),0) AS revenue,
        COUNT(DISTINCT o.staff_id) AS active_staff
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
");
$kpis->execute([$from, $to]);
$kpis = $kpis->fetch();

$avgOrderValue = $kpis['fulfilled_orders'] > 0
    ? $kpis['revenue'] / $kpis['fulfilled_orders'] : 0;
$fulfillRate = $kpis['total_orders'] > 0
    ? round($kpis['fulfilled_orders'] / $kpis['total_orders'] * 100) : 0;

// Inventory snapshot (not date-filtered)
$invStats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(stock_quantity * price) AS total_value,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock
    FROM products WHERE is_active=1
")->fetch();

// ── Chart data ────────────────────────────────────────────────────────────────

// Orders + revenue per day
$perDay = $db->prepare("
    SELECT DATE(o.created_at) AS day,
           COUNT(DISTINCT o.id) AS order_cnt,
           COALESCE(SUM(CASE WHEN o.status='fulfilled' THEN oi.quantity * oi.unit_price END),0) AS revenue
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY DATE(o.created_at) ORDER BY day ASC
");
$perDay->execute([$from, $to]);
$perDay = $perDay->fetchAll();

// Status breakdown
$statusBreakdown = $db->prepare("
    SELECT status, COUNT(*) AS cnt FROM orders
    WHERE created_at BETWEEN ? AND ? GROUP BY status
");
$statusBreakdown->execute([$from, $to]);
$statusBreakdown = $statusBreakdown->fetchAll(PDO::FETCH_KEY_PAIR);

// Top 8 products by fulfilled qty
$topProducts = $db->prepare("
    SELECT p.name, SUM(oi.quantity) AS total_qty,
           SUM(oi.quantity * oi.unit_price) AS total_value
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id AND o.status='fulfilled'
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY p.id, p.name ORDER BY total_qty DESC LIMIT 8
");
$topProducts->execute([$from, $to]);
$topProducts = $topProducts->fetchAll();

// Category demand
$catDemand = $db->prepare("
    SELECT COALESCE(c.name,'Uncategorised') AS cat,
           SUM(oi.quantity) AS qty
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN orders o ON o.id = oi.order_id AND o.status='fulfilled'
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY c.id, c.name ORDER BY qty DESC
");
$catDemand->execute([$from, $to]);
$catDemand = $catDemand->fetchAll();

// Staff performance
$staffPerf = $db->prepare("
    SELECT u.name, COUNT(DISTINCT o.id) AS total_orders,
           COUNT(DISTINCT CASE WHEN o.status='fulfilled' THEN o.id END) AS fulfilled,
           COUNT(DISTINCT CASE WHEN o.status='rejected'  THEN o.id END) AS rejected,
           COALESCE(SUM(CASE WHEN o.status='fulfilled' THEN oi.quantity * oi.unit_price END),0) AS value
    FROM users u
    JOIN orders o ON o.staff_id = u.id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY u.id, u.name ORDER BY total_orders DESC
");
$staffPerf->execute([$from, $to]);
$staffPerf = $staffPerf->fetchAll();

// Inventory health — low/out of stock
$stockHealth = $db->query("
    SELECT p.name, p.sku,
           COALESCE(c.name, 'Uncategorised') AS category,
           p.stock_quantity, p.low_stock_threshold,
           COALESCE(SUM(oi.quantity),0) AS demand_30d
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    LEFT JOIN orders o ON o.id = oi.order_id
        AND o.status='fulfilled'
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.is_active=1 AND p.stock_quantity <= p.low_stock_threshold
    GROUP BY p.id, p.name, p.sku, c.name, p.stock_quantity, p.low_stock_threshold
    ORDER BY p.stock_quantity ASC
")->fetchAll();

$pageTitle  = 'Reports';
$activePage = 'reports';
include __DIR__ . '/../includes/header.php';

// JS data
$jsPerDayLabels  = json_encode(array_column($perDay, 'day'));
$jsPerDayCounts  = json_encode(array_map('intval', array_column($perDay, 'order_cnt')));
$jsPerDayRevenue = json_encode(array_map('floatval', array_column($perDay, 'revenue')));
$jsStatusLabels  = json_encode(array_keys($statusBreakdown));
$jsStatusCounts  = json_encode(array_values($statusBreakdown));
$jsTopNames      = json_encode(array_column($topProducts, 'name'));
$jsTopQtys       = json_encode(array_map('intval', array_column($topProducts, 'total_qty')));
$jsCatLabels     = json_encode(array_column($catDemand, 'cat'));
$jsCatQtys       = json_encode(array_map('intval', array_column($catDemand, 'qty')));
?>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1 class="page-title">Reports & Analytics</h1>
        <div class="page-subtitle">
            <?= date('M d, Y', strtotime($dateFrom)) ?> — <?= date('M d, Y', strtotime($dateTo)) ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="?<?= http_build_query(['export'=>'orders','range'=>$preset,'from'=>$dateFrom,'to'=>$dateTo]) ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Orders CSV
        </a>
        <a href="?export=inventory" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Inventory CSV
        </a>
    </div>
</div>

<!-- Date range filter -->
<div class="card mb-4">
    <div class="card-body py-2 px-3 d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex gap-1">
            <?php foreach (['7'=>'7 days','30'=>'30 days','90'=>'90 days','all'=>'All time'] as $k=>$label): ?>
            <a href="?range=<?= $k ?>" class="btn btn-sm <?= $preset===$k ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2 ms-auto">
            <input type="hidden" name="range" value="custom">
            <label style="font-size:12px;font-weight:700;color:#64748B;">Custom</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>" style="max-width:145px;">
            <span style="font-size:12px;color:#94A3B8;">to</span>
            <input type="date" name="to"   class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>"   style="max-width:145px;">
            <button class="btn btn-sm btn-primary">Apply</button>
        </form>
    </div>
</div>

<!-- KPI row 1 -->
<div class="row g-3 mb-3">
    <div class="col-md-2 col-sm-4">
        <div class="kpi-card border-blue">
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value"><?= number_format($kpis['total_orders']) ?></div>
            <div class="kpi-sub"><?= $kpis['pending_orders'] ?> pending</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="kpi-card border-green">
            <div class="kpi-label">Revenue</div>
            <div class="kpi-value" style="font-size:20px;">₱<?= number_format($kpis['revenue'],0) ?></div>
            <div class="kpi-sub">Fulfilled orders</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="kpi-card border-blue">
            <div class="kpi-label">Avg Order Value</div>
            <div class="kpi-value" style="font-size:20px;">₱<?= number_format($avgOrderValue,0) ?></div>
            <div class="kpi-sub">Per fulfilled order</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="kpi-card border-green">
            <div class="kpi-label">Fulfillment Rate</div>
            <div class="kpi-value <?= $fulfillRate >= 70 ? 'text-success' : ($fulfillRate >= 40 ? 'text-warning' : 'text-danger') ?>">
                <?= $fulfillRate ?>%
            </div>
            <div class="kpi-sub"><?= $kpis['fulfilled_orders'] ?> of <?= $kpis['total_orders'] ?> orders</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="kpi-card border-red">
            <div class="kpi-label">Rejected Orders</div>
            <div class="kpi-value text-danger"><?= number_format($kpis['rejected_orders']) ?></div>
            <div class="kpi-sub">In period</div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4">
        <div class="kpi-card border-amber">
            <div class="kpi-label">Active Staff</div>
            <div class="kpi-value"><?= $kpis['active_staff'] ?></div>
            <div class="kpi-sub">Placed orders</div>
        </div>
    </div>
</div>

<!-- KPI row 2 — inventory snapshot -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card border-blue">
            <div class="kpi-label">Total Products</div>
            <div class="kpi-value"><?= $invStats['total'] ?></div>
            <div class="kpi-sub">Active SKUs</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-amber">
            <div class="kpi-label">Inventory Value</div>
            <div class="kpi-value" style="font-size:20px;">₱<?= number_format($invStats['total_value']??0,0) ?></div>
            <div class="kpi-sub">At current prices</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-red">
            <div class="kpi-label">Low Stock Items</div>
            <div class="kpi-value text-warning"><?= $invStats['low_stock'] ?></div>
            <div class="kpi-sub">Below threshold</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-red">
            <div class="kpi-label">Out of Stock</div>
            <div class="kpi-value text-danger"><?= $invStats['out_of_stock'] ?></div>
            <div class="kpi-sub">Zero quantity</div>
        </div>
    </div>
</div>

<!-- Charts row 1 -->
<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                Orders & Revenue Over Time
                <?php if (!empty($perDay)): ?>
                <div class="d-flex gap-1">
                    <button class="btn btn-primary btn-sm py-0 px-2" onclick="toggleChart('orders')" id="btn-orders" style="font-size:11px;">Orders</button>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="toggleChart('revenue')" id="btn-revenue" style="font-size:11px;">Revenue</button>
                    <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="toggleChart('both')" id="btn-both" style="font-size:11px;">Both</button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (empty($perDay)): ?>
                <p class="text-muted text-center py-4 mb-0" style="font-size:13px;">No orders in this period.</p>
                <?php else: ?>
                <canvas id="trendChart" height="110" style="width:100%;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Order Status Breakdown</div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="statusChart" style="max-height:180px;"></canvas>
                <div id="statusLegend" class="mt-3 w-100" style="font-size:12px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts row 2 -->
<div class="row g-3 mb-3">
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header">Top Products by Fulfilled Quantity</div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                <p class="text-center text-muted py-4" style="font-size:13px;">No fulfilled orders in this period.</p>
                <?php else: ?>
                <canvas id="topProductsChart" height="<?= min(count($topProducts) * 28 + 20, 220) ?>"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header">Demand by Category</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (empty($catDemand)): ?>
                <p class="text-center text-muted py-4" style="font-size:13px;">No data in this period.</p>
                <?php else: ?>
                <canvas id="catChart" style="max-height:220px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Staff performance table -->
<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">Staff Performance</div>
            <div class="card-body p-0">
                <?php if (empty($staffPerf)): ?>
                <p class="text-center text-muted py-4" style="font-size:13px;">No orders in this period.</p>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th class="ps-4">Staff Member</th>
                        <th>Total Orders</th>
                        <th>Fulfilled</th>
                        <th>Rejected</th>
                        <th>Fulfillment Rate</th>
                        <th class="text-end pe-4">Total Value</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($staffPerf as $i => $s):
                        $rate = $s['total_orders'] > 0 ? round($s['fulfilled'] / $s['total_orders'] * 100) : 0;
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:28px;height:28px;border-radius:50%;background:#EFF6FF;
                                            color:#2563EB;display:flex;align-items:center;justify-content:center;
                                            font-size:11px;font-weight:700;flex-shrink:0;">
                                    <?= strtoupper(substr($s['name'],0,1)) ?>
                                </div>
                                <span class="fw-600"><?= htmlspecialchars($s['name']) ?></span>
                                <?php if ($i === 0): ?>
                                <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Top</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="fw-600"><?= $s['total_orders'] ?></td>
                        <td><span class="text-success fw-600"><?= $s['fulfilled'] ?></span></td>
                        <td><span class="text-danger"><?= $s['rejected'] ?></span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-fill" style="height:6px;border-radius:4px;min-width:60px;">
                                    <div class="progress-bar bg-<?= $rate>=70?'success':($rate>=40?'warning':'danger') ?>"
                                         style="width:<?= $rate ?>%"></div>
                                </div>
                                <span style="font-size:12px;min-width:32px;"><?= $rate ?>%</span>
                            </div>
                        </td>
                        <td class="text-end pe-4 fw-700">₱<?= number_format($s['value'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Inventory health table -->
<?php if (!empty($stockHealth)): ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                Inventory Health — Items Needing Attention
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th class="ps-4">Product</th><th>SKU</th><th>Category</th>
                        <th>Current Stock</th><th>Min Threshold</th>
                        <th>30-Day Demand</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($stockHealth as $p): ?>
                    <tr>
                        <td class="ps-4 fw-600"><?= htmlspecialchars($p['name']) ?></td>
                        <td><code style="font-size:12px;"><?= htmlspecialchars($p['sku']) ?></code></td>
                        <td style="font-size:12px;color:#64748B;"><?= htmlspecialchars($p['category']??'—') ?></td>
                        <td>
                            <span class="fw-700 <?= $p['stock_quantity']==0 ? 'text-danger' : 'text-warning' ?>">
                                <?= $p['stock_quantity'] ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= $p['low_stock_threshold'] ?></td>
                        <td>
                            <span class="fw-600 <?= $p['demand_30d']>0 ? 'text-primary' : 'text-muted' ?>">
                                <?= $p['demand_30d'] ?> units
                            </span>
                        </td>
                        <td>
                            <?php if ($p['stock_quantity']==0): ?>
                            <span class="badge badge-out rounded-pill px-2">Out of Stock</span>
                            <?php else: ?>
                            <span class="badge badge-low-stock rounded-pill px-2">Low Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<!-- PHP data injected safely — no heredoc interpolation conflicts -->
<script>
const _DAYS   = <?= $jsPerDayLabels ?>;
const _ORD    = <?= $jsPerDayCounts ?>;
const _REV    = <?= $jsPerDayRevenue ?>;
const _SLBLS  = <?= $jsStatusLabels ?>;
const _SCNTS  = <?= $jsStatusCounts ?>;
const _TNAMES = <?= $jsTopNames ?>;
const _TQTYS  = <?= $jsTopQtys ?>;
const _CLBLS  = <?= $jsCatLabels ?>;
const _CQTYS  = <?= $jsCatQtys ?>;
</script>
<?php
// Nowdoc (single-quoted delimiter) — PHP does NOT interpolate anything inside,
// so JS template literals like ${statusBg[i]} are safe.
$extraJs = <<<'ENDJS'
const COLORS       = ['#2563EB','#16A34A','#D97706','#DC2626','#7C3AED','#0891B2','#DB2777','#65A30D'];
const STATUS_COLOR = { pending:'#FCD34D', approved:'#86EFAC', fulfilled:'#93C5FD', rejected:'#FCA5A5' };

// ── Trend chart ───────────────────────────────────────────────────────────────
let trendChart = null;
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
    trendChart = new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: _DAYS,
            datasets: [
                {
                    label: 'Orders',
                    data: _ORD,
                    backgroundColor: 'rgba(37,99,235,0.75)',
                    borderRadius: 4,
                    yAxisID: 'yOrders'
                },
                {
                    label: 'Revenue (₱)',
                    data: _REV,
                    type: 'line',
                    borderColor: '#16A34A',
                    backgroundColor: 'rgba(22,163,74,0.08)',
                    tension: 0.3, fill: true, pointRadius: 3,
                    yAxisID: 'yRevenue',
                    hidden: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } },
            scales: {
                yOrders:  { beginAtZero: true, position: 'left',
                            ticks: { stepSize: 1, font: { size: 11 } } },
                yRevenue: { beginAtZero: true, position: 'right',
                            ticks: { font: { size: 11 }, callback: function(v) { return '₱' + v.toLocaleString(); } },
                            grid: { drawOnChartArea: false } },
                x: { ticks: { maxTicksLimit: 12, font: { size: 11 } } }
            }
        }
    });
}

function toggleChart(mode) {
    if (!trendChart) return;
    const ds = trendChart.data.datasets;
    ds[0].hidden = (mode === 'revenue');
    ds[1].hidden = (mode === 'orders');
    trendChart.update();
    ['orders', 'revenue', 'both'].forEach(function(m) {
        const btn = document.getElementById('btn-' + m);
        if (!btn) return;
        btn.className = (m === mode)
            ? 'btn btn-primary btn-sm py-0 px-2'
            : 'btn btn-outline-secondary btn-sm py-0 px-2';
        btn.style.fontSize = '11px';
    });
}

// ── Status doughnut ───────────────────────────────────────────────────────────
const statusBg  = _SLBLS.map(function(l) { return STATUS_COLOR[l] || '#CBD5E1'; });
const statusCtx = document.getElementById('statusChart');
if (statusCtx) {
    if (_SLBLS.length === 0) {
        statusCtx.parentElement.innerHTML =
            '<p class="text-muted text-center py-4 mb-0" style="font-size:13px;">No orders in this period.</p>';
    } else {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: _SLBLS,
                datasets: [{ data: _SCNTS, backgroundColor: statusBg, borderWidth: 2 }]
            },
            options: { plugins: { legend: { display: false } }, cutout: '68%' }
        });
    }
}

// Status legend (string concat — avoids JS template literal / PHP heredoc conflict)
const legendEl = document.getElementById('statusLegend');
if (legendEl && _SLBLS.length > 0) {
    const total = _SCNTS.reduce(function(a, b) { return a + Number(b); }, 0);
    _SLBLS.forEach(function(label, i) {
        const pct = total > 0 ? Math.round(Number(_SCNTS[i]) / total * 100) : 0;
        legendEl.innerHTML +=
            '<div class="d-flex justify-content-between align-items-center mb-1">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<div style="width:10px;height:10px;border-radius:2px;background:' + statusBg[i] + ';flex-shrink:0;"></div>' +
                    '<span style="text-transform:capitalize;">' + label + '</span>' +
                '</div>' +
                '<span class="fw-600">' + _SCNTS[i] +
                    ' <span class="text-muted fw-normal">(' + pct + '%)</span>' +
                '</span>' +
            '</div>';
    });
}

// ── Top products ──────────────────────────────────────────────────────────────
if (_TNAMES.length > 0) {
    new Chart(document.getElementById('topProductsChart'), {
        type: 'bar',
        data: {
            labels: _TNAMES,
            datasets: [{
                label: 'Units Fulfilled',
                data: _TQTYS,
                backgroundColor: _TNAMES.map(function(_, i) { return COLORS[i % COLORS.length]; }),
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } } },
                y: { ticks: { font: { size: 12 } } }
            }
        }
    });
}

// ── Category pie ──────────────────────────────────────────────────────────────
if (_CLBLS.length > 0) {
    new Chart(document.getElementById('catChart'), {
        type: 'pie',
        data: {
            labels: _CLBLS,
            datasets: [{ data: _CQTYS, backgroundColor: COLORS, borderWidth: 2 }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, boxWidth: 12 } } }
        }
    });
}
ENDJS;
include __DIR__ . '/../includes/footer.php';
?>
