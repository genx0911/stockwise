<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Order.php';

Auth::requireRole('staff');

$order  = new Order();
$me     = Auth::user();
$orders = $order->getByStaff($me['id']);

$pageTitle  = 'My Orders';
$activePage = 'orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">My Orders</h1>
        <div class="page-subtitle"><?= count($orders) ?> orders submitted</div>
    </div>
    <a href="<?= APP_URL ?>/staff/new_order.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Order
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-4">Order #</th><th>Items</th><th>Total</th>
                <th>Status</th><th>Reviewed By</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php if (empty($orders)): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">
                No orders yet. <a href="<?= APP_URL ?>/staff/new_order.php">Place your first order →</a>
            </td></tr>
            <?php else: foreach ($orders as $o): ?>
            <tr>
                <td class="ps-4 fw-700"><?= htmlspecialchars($o['order_no']) ?></td>
                <td><?= $o['item_count'] ?> item<?= $o['item_count'] != 1 ? 's' : '' ?></td>
                <td class="fw-600">₱<?= number_format($o['total_amount'], 2) ?></td>
                <td><span class="badge badge-<?= $o['status'] ?> rounded-pill px-2"><?= ucfirst($o['status']) ?></span></td>
                <td class="text-muted" style="font-size:12px;"><?= $o['reviewer_name'] ? htmlspecialchars($o['reviewer_name']) : '—' ?></td>
                <td class="text-muted" style="font-size:12px;"><?= date('M d, Y g:i A', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
