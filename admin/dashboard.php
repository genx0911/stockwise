<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';
require_once __DIR__ . '/../classes/User.php';

Auth::requireRole('admin');

$product  = new Product();
$order    = new Order();
$user     = new User();

$prodStats  = $product->getStats();
$orderStats = $order->getStats();
$lowStock   = $product->getLowStock();
$recentOrders = array_slice($order->getAll(), 0, 5);

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <div class="page-subtitle">Welcome back, <?= htmlspecialchars(Auth::user()['name']) ?> · <?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Low stock alert -->
<?php if (!empty($lowStock)): ?>
<div class="alert-low-stock d-flex align-items-start gap-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 text-warning"></i>
    <div>
        <strong><?= count($lowStock) ?> product<?= count($lowStock) > 1 ? 's are' : ' is' ?> running low on stock.</strong>
        <?php foreach ($lowStock as $p): ?>
        <span class="badge badge-low-stock ms-1"><?= htmlspecialchars($p['name']) ?> (<?= $p['stock_quantity'] ?> left)</span>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/admin/products.php?filter=low" class="ms-2 fw-600" style="font-size:12px;">View all →</a>
    </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card border-blue">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-label mb-0">Total Products</div>
                <span style="font-size:22px;line-height:1;">📦</span>
            </div>
            <div class="kpi-value"><?= number_format($prodStats['total']) ?></div>
            <div class="kpi-sub">Active SKUs in stock</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-red">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-label mb-0">Low Stock</div>
                <span style="font-size:22px;line-height:1;">⚠️</span>
            </div>
            <div class="kpi-value text-danger"><?= $prodStats['low_stock_count'] ?></div>
            <div class="kpi-sub"><?= $prodStats['out_of_stock'] ?> out of stock</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-green">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-label mb-0">Inventory Value</div>
                <span style="font-size:22px;line-height:1;">💰</span>
            </div>
            <div class="kpi-value" style="font-size:22px;">₱<?= number_format($prodStats['total_value'], 0) ?></div>
            <div class="kpi-sub">At current prices</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-amber">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-label mb-0">Pending Orders</div>
                <span style="font-size:22px;line-height:1;">🕐</span>
            </div>
            <div class="kpi-value text-warning"><?= $orderStats['pending'] ?></div>
            <div class="kpi-sub"><?= $orderStats['total'] ?> orders total</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Orders -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                Recent Orders
                <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th class="ps-4">Order #</th><th>Staff</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4" style="font-size:13px;">No orders yet.</td></tr>
                    <?php else: foreach ($recentOrders as $o): ?>
                    <tr>
                        <td class="ps-4"><a href="<?= APP_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="fw-600 text-decoration-none"><?= htmlspecialchars($o['order_no']) ?></a></td>
                        <td><?= htmlspecialchars($o['staff_name']) ?></td>
                        <td><?= $o['item_count'] ?></td>
                        <td>₱<?= number_format($o['total_amount'], 2) ?></td>
                        <td><span class="badge badge-<?= $o['status'] ?> rounded-pill px-2"><?= ucfirst($o['status']) ?></span></td>
                        <td class="text-muted"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Order Stats -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">Order Summary</div>
            <div class="card-body">
                <?php
                $statRows = [
                    ['Pending',   $orderStats['pending'],   'warning'],
                    ['Approved',  $orderStats['approved'],  'success'],
                    ['Fulfilled', $orderStats['fulfilled'], 'primary'],
                    ['Rejected',  $orderStats['rejected'],  'danger'],
                ];
                foreach ($statRows as [$label, $count, $color]):
                $pct = $orderStats['total'] > 0 ? round($count / $orderStats['total'] * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                        <span class="fw-600"><?= $label ?></span>
                        <span class="text-muted"><?= $count ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
