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

$order = new Order();
$id    = (int)($_GET['id'] ?? 0);
$o     = $order->getById($id);

if (!$o) {
    header('Location: ' . APP_URL . '/admin/orders.php');
    exit;
}

$items = $order->getItems($id);

$pageTitle  = 'Order ' . $o['order_no'];
$activePage = 'orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($o['order_no']) ?></h1>
        <div class="page-subtitle">
            Placed by <?= htmlspecialchars($o['staff_name']) ?> · <?= date('M d, Y g:i A', strtotime($o['created_at'])) ?>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge badge-<?= $o['status'] ?> rounded-pill px-3 py-2" style="font-size:13px;" id="statusBadge">
            <?= ucfirst($o['status']) ?>
        </span>
        <?php if ($o['status'] === 'pending'): ?>
        <button class="btn btn-success" onclick="updateStatus(<?= $o['id'] ?>, 'approved')">
            <i class="bi bi-check-lg me-1"></i>Approve
        </button>
        <button class="btn btn-outline-danger" onclick="updateStatus(<?= $o['id'] ?>, 'rejected')">
            <i class="bi bi-x-lg me-1"></i>Reject
        </button>
        <?php elseif ($o['status'] === 'approved'): ?>
        <button class="btn btn-primary" onclick="updateStatus(<?= $o['id'] ?>, 'fulfilled')">
            <i class="bi bi-box-seam me-1"></i>Mark as Fulfilled
        </button>
        <?php endif; ?>
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Order items -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Order Items</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr>
                        <th class="ps-4">Product</th><th>SKU</th><th>Unit</th>
                        <th class="text-end">Unit Price</th><th class="text-end">Qty</th>
                        <th class="text-end pe-4">Subtotal</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="ps-4 fw-600"><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><code style="font-size:12px;"><?= htmlspecialchars($item['sku']) ?></code></td>
                        <td style="font-size:12px;color:#64748B;"><?= htmlspecialchars($item['unit']) ?></td>
                        <td class="text-end">₱<?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-end fw-700"><?= $item['quantity'] ?></td>
                        <td class="text-end pe-4 fw-700">₱<?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid #E2E8F0;background:#F8FAFC;">
                            <td colspan="5" class="ps-4 fw-700 py-3">Total</td>
                            <td class="text-end pe-4 fw-800 py-3" style="font-size:16px;">₱<?= number_format($o['total_amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Order info -->
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">Order Info</div>
            <div class="card-body" style="font-size:13.5px;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Staff</span>
                    <span class="fw-600"><?= htmlspecialchars($o['staff_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Status</span>
                    <span class="badge badge-<?= $o['status'] ?> rounded-pill px-2"><?= ucfirst($o['status']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Submitted</span>
                    <span><?= date('M d, Y', strtotime($o['created_at'])) ?></span>
                </div>
                <?php if ($o['reviewer_name']): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Reviewed by</span>
                    <span class="fw-600"><?= htmlspecialchars($o['reviewer_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Reviewed at</span>
                    <span><?= date('M d, Y', strtotime($o['reviewed_at'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($o['notes']): ?>
                <hr class="my-2">
                <div class="text-muted mb-1" style="font-size:11px;text-transform:uppercase;font-weight:700;letter-spacing:.06em;">Notes</div>
                <div style="color:#374151;"><?= nl2br(htmlspecialchars($o['notes'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
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
                setTimeout(() => location.href = '../admin/orders.php', 1000);
            } else {
                showToast(res.message || 'Action failed.', 'danger');
            }
        });
}
JS;
include __DIR__ . '/../includes/footer.php';
?>
