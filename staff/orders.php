<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Order.php';

Auth::requireRole('staff');

$order = new Order();
$me    = Auth::user();

// AJAX: cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($order->cancel((int)$_POST['order_id'], $me['id']));
    exit;
}

$allOrders = $order->getByStaff($me['id']);

// Filter
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$orders = array_filter($allOrders, function($o) use ($filterStatus, $search) {
    if ($filterStatus && $o['status'] !== $filterStatus) return false;
    if ($search && stripos($o['order_no'], $search) === false) return false;
    return true;
});

$pageTitle  = 'My Orders';
$activePage = 'orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">My Orders</h1>
        <div class="page-subtitle"><?= count($orders) ?> orders</div>
    </div>
    <a href="<?= APP_URL ?>/staff/new_order.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Order
    </a>
</div>

<!-- Search + Filter -->
<form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search order number…"
           value="<?= htmlspecialchars($search) ?>" style="max-width:200px;">
    <select name="status" class="form-select form-select-sm" style="max-width:150px;" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach (['pending','approved','rejected','fulfilled'] as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($filterStatus || $search): ?>
    <a href="?" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Clear</a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-4">Order #</th><th>Items</th><th>Total</th>
                <th>Status</th><th>Reviewed By</th><th>Date</th><th class="text-end pe-4">Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($orders)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">
                No orders found. <?php if (!$filterStatus && !$search): ?><a href="<?= APP_URL ?>/staff/new_order.php">Place your first order →</a><?php endif; ?>
            </td></tr>
            <?php else: foreach ($orders as $o): ?>
            <tr id="row-<?= $o['id'] ?>">
                <td class="ps-4 fw-700"><?= htmlspecialchars($o['order_no']) ?></td>
                <td><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
                <td class="fw-600">₱<?= number_format($o['total_amount'], 2) ?></td>
                <td><span class="badge badge-<?= $o['status'] ?> rounded-pill px-2"><?= ucfirst($o['status']) ?></span></td>
                <td class="text-muted" style="font-size:12px;"><?= $o['reviewer_name'] ? htmlspecialchars($o['reviewer_name']) : '—' ?></td>
                <td class="text-muted" style="font-size:12px;"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></td>
                <td class="text-end pe-4">
                    <?php if ($o['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="cancelOrder(<?= $o['id'] ?>, '<?= htmlspecialchars(addslashes($o['order_no'])) ?>')">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <?php else: ?>
                    <span class="text-muted" style="font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraJs = <<<JS
function cancelOrder(id, orderNo) {
    if (!confirm('Cancel order ' + orderNo + '? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('order_id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                showToast('Order cancelled.');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Could not cancel.', 'danger');
            }
        });
}
JS;
include __DIR__ . '/../includes/footer.php';
?>
