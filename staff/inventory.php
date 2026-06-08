<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';

Auth::requireRole('staff');

$product  = new Product();
$filter   = $_GET['filter'] ?? '';
$products = $filter === 'low' ? $product->getLowStock() : $product->getAll(true);

$pageTitle  = 'View Stock';
$activePage = 'inventory';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Inventory</h1>
        <div class="page-subtitle"><?= count($products) ?> products available</div>
    </div>
    <a href="<?= APP_URL ?>/staff/new_order.php" class="btn btn-primary">
        <i class="bi bi-cart-plus me-1"></i> Place Order
    </a>
</div>

<!-- Filters + Search -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <a href="?filter=" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    <a href="?filter=low" class="btn btn-sm <?= $filter === 'low' ? 'btn-warning' : 'btn-outline-warning' ?>">
        <i class="bi bi-exclamation-triangle me-1"></i>Low Stock
    </a>
    <input type="text" id="searchBox" class="form-control form-control-sm ms-auto" placeholder="Search products…" style="max-width:200px;">
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0" id="stockTable">
            <thead><tr>
                <th class="ps-4">SKU</th><th>Product</th><th>Category</th>
                <th>Unit</th><th>Price</th><th>Stock</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php if (empty($products)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">No products found.</td></tr>
            <?php else: foreach ($products as $p): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                <td class="ps-4"><code style="font-size:12px;"><?= htmlspecialchars($p['sku']) ?></code></td>
                <td>
                    <div class="fw-600"><?= htmlspecialchars($p['name']) ?></div>
                    <?php if ($p['description']): ?>
                    <div style="font-size:11px;color:#94A3B8;"><?= htmlspecialchars(mb_strimwidth($p['description'], 0, 60, '…')) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#64748B;"><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($p['unit']) ?></td>
                <td class="fw-600">₱<?= number_format($p['price'], 2) ?></td>
                <td>
                    <span class="fw-700 <?= $p['stock_quantity'] == 0 ? 'text-danger' : ($p['stock_quantity'] <= $p['low_stock_threshold'] ? 'text-warning' : 'text-success') ?>">
                        <?= $p['stock_quantity'] ?>
                    </span>
                </td>
                <td>
                    <?php if ($p['stock_quantity'] == 0): ?>
                        <span class="badge badge-out rounded-pill px-2">Out of Stock</span>
                    <?php elseif ($p['stock_quantity'] <= $p['low_stock_threshold']): ?>
                        <span class="badge badge-low-stock rounded-pill px-2">Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-in-stock rounded-pill px-2">In Stock</span>
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
document.getElementById('searchBox').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#stockTable tbody tr[data-name]').forEach(row => {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
});
JS;
include __DIR__ . '/../includes/footer.php';
?>
