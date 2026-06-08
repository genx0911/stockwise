<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Order.php';

Auth::requireRole('admin');

// AJAX: update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $order  = new Order();
    $result = $order->updateStatus((int)$_POST['order_id'], $_POST['status'], Auth::user()['id']);
    echo json_encode($result);
    exit;
}

$order   = new Order();
$filter  = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');
$allOrders = $order->getAll($filter ?: null);
$stats   = $order->getStats();

$orders = $search
    ? array_filter($allOrders, fn($o) =>
        stripos($o['order_no'], $search) !== false ||
        stripos($o['staff_name'], $search) !== false)
    : $allOrders;

$pageTitle  = 'Orders';
$activePage = 'orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">All Orders</h1>
    <div class="page-subtitle">Review, approve, and fulfil staff orders</div>
</div>

<!-- Search -->
<form method="GET" class="d-flex gap-2 mb-3">
    <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
    <input type="text" name="q" class="form-control form-control-sm" placeholder="Search order # or staff name…"
           value="<?= htmlspecialchars($search) ?>" style="max-width:260px;">
    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    <?php if ($search): ?><a href="?status=<?= htmlspecialchars($filter) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></a><?php endif; ?>
</form>

<!-- Status filter tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php
    $tabs = [
        ''          => ['All',       $stats['total'],     'secondary'],
        'pending'   => ['Pending',   $stats['pending'],   'warning'],
        'approved'  => ['Approved',  $stats['approved'],  'success'],
        'fulfilled' => ['Fulfilled', $stats['fulfilled'], 'primary'],
        'rejected'  => ['Rejected',  $stats['rejected'],  'danger'],
    ];
    foreach ($tabs as $val => [$label, $count, $color]):
    $active = $filter === $val;
    ?>
    <a href="?status=<?= $val ?>" class="btn btn-sm btn-<?= $active ? $color : 'outline-' . $color ?>">
        <?= $label ?> <span class="badge bg-white text-<?= $color ?> ms-1"><?= $count ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-4">Order #</th><th>Staff</th><th>Items</th>
                <th>Total</th><th>Status</th><th>Date</th><th class="text-end pe-4">Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($orders)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">No orders found.</td></tr>
            <?php else: foreach ($orders as $o): ?>
            <tr id="order-row-<?= $o['id'] ?>">
                <td class="ps-4">
                    <a href="<?= APP_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="fw-700 text-decoration-none">
                        <?= htmlspecialchars($o['order_no']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($o['staff_name']) ?></td>
                <td><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
                <td class="fw-600">₱<?= number_format($o['total_amount'], 2) ?></td>
                <td>
                    <span class="badge badge-<?= $o['status'] ?> rounded-pill px-2" id="status-<?= $o['id'] ?>">
                        <?= ucfirst($o['status']) ?>
                    </span>
                </td>
                <td class="text-muted" style="font-size:12px;"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></td>
                <td class="text-end pe-4">
                    <a href="<?= APP_URL ?>/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                        <i class="bi bi-eye"></i>
                    </a>
                    <?php if ($o['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success me-1" onclick="updateStatus(<?= $o['id'] ?>, 'approved')">
                        <i class="bi bi-check-lg"></i> Approve
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="updateStatus(<?= $o['id'] ?>, 'rejected')">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                    <?php elseif ($o['status'] === 'approved'): ?>
                    <button class="btn btn-sm btn-primary" onclick="updateStatus(<?= $o['id'] ?>, 'fulfilled')">
                        <i class="bi bi-box-seam"></i> Fulfil
                    </button>
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
function updateStatus(orderId, status) {
    const labels = { approved: 'approve', rejected: 'reject', fulfilled: 'mark as fulfilled' };
    if (!confirm('Are you sure you want to ' + labels[status] + ' this order?')) return;

    const fd = new FormData();
    fd.append('ajax', 1); fd.append('order_id', orderId); fd.append('status', status);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                showToast('Order ' + status + '!');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Action failed.', 'danger');
            }
        });
}
JS;
include __DIR__ . '/../includes/footer.php';
?>
