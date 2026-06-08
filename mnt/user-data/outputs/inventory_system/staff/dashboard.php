<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';

Auth::requireRole('staff');

$product  = new Product();
$order    = new Order();
$me       = Auth::user();

$myOrders   = array_slice($order->getByStaff($me['id']), 0, 5);
$prodStats  = $product->getStats();
$lowStock   = $product->getLowStock();

// Count my orders by status
$db = Database::getInstance();
$stmt = $db->prepare('SELECT status, COUNT(*) as count FROM orders WHERE staff_id=? GROUP BY status');
$stmt->execute([$me['id']]);
$myStats = array_column($stmt->fetchAll(), 'count', 'status');

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', $me['name'])[0]) ?> 👋</h1>
    <div class="page-subtitle"><?= date('l, F j, Y') ?></div>
</div>

<!-- Low stock notice -->
<?php if (!empty($lowStock)): ?>
<div class="alert-low-stock d-flex align-items-start gap-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 text-warning"></i>
    <div>
        <strong>Heads up:</strong> <?= count($lowStock) ?> product<?= count($lowStock) > 1 ? 's are' : ' is' ?> running low.
        <?php foreach (array_slice($lowStock, 0, 3) as $p): ?>
            <span class="badge badge-low-stock ms-1"><?= htmlspecialchars($p['name']) ?></span>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/staff/inventory.php?filter=low" class="ms-1 fw-600" style="font-size:12px;">See all →</a>
    </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card border-blue">
            <div class="kpi-label">My Orders</div>
            <div class="kpi-value"><?= array_sum($myStats) ?></div>
            <div class="kpi-sub">Total submitted</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-amber">
            <div class="kpi-label">Pending</div>
            <div class="kpi-value text-warning"><?= $myStats['pending'] ?? 0 ?></div>
            <div class="kpi-sub">Awaiting admin review</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-green">
            <div class="kpi-label">Approved</div>
            <div class="kpi-value text-success"><?= $myStats['approved'] ?? 0 ?></div>
            <div class="kpi-sub">Ready to fulfil</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card border-blue">
            <div class="kpi-label">Products Available</div>
            <div class="kpi-value"><?= $prodStats['total'] ?></div>
            <div class="kpi-sub">Active SKUs</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent orders -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                My Recent Orders
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/staff/orders.php" class="btn btn-sm btn-outline-secondary">All orders</a>
                    <a href="<?= APP_URL ?>/staff/new_order.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>New Order
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th class="ps-4">Order #</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($myOrders)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">
                        No orders yet. <a href="<?= APP_URL ?>/staff/new_order.php">Place your first order →</a>
                    </td></tr>
                    <?php else: foreach ($myOrders as $o): ?>
                    <tr>
                        <td class="ps-4 fw-700"><?= htmlspecialchars($o['order_no']) ?></td>
                        <td><?= $o['item_count'] ?></td>
                        <td class="fw-600">₱<?= number_format($o['total_amount'], 2) ?></td>
                        <td><span class="badge badge-<?= $o['status'] ?> rounded-pill px-2"><?= ucfirst($o['status']) ?></span></td>
                        <td class="text-muted" style="font-size:12px;"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Quick actions -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="<?= APP_URL ?>/staff/new_order.php" class="btn btn-primary">
                    <i class="bi bi-cart-plus me-2"></i>Place New Order
                </a>
                <a href="<?= APP_URL ?>/staff/inventory.php" class="btn btn-outline-secondary">
                    <i class="bi bi-box-seam me-2"></i>Browse Inventory
                </a>
                <a href="<?= APP_URL ?>/staff/orders.php" class="btn btn-outline-secondary">
                    <i class="bi bi-clipboard-check me-2"></i>View My Orders
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
