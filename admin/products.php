<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';

Auth::requireRole('admin');

// Handle AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $product = new Product();
    $action  = $_POST['action'] ?? '';

    if ($action === 'create') {
        echo json_encode($product->create([
            'category_id'         => (int)$_POST['category_id'],
            'sku'                 => trim($_POST['sku']),
            'name'                => trim($_POST['name']),
            'description'         => trim($_POST['description'] ?? ''),
            'unit'                => trim($_POST['unit']),
            'price'               => (float)$_POST['price'],
            'stock_quantity'      => (int)$_POST['stock_quantity'],
            'low_stock_threshold' => (int)$_POST['low_stock_threshold'],
        ]));
    } elseif ($action === 'update') {
        echo json_encode($product->update((int)$_POST['id'], [
            'category_id'         => (int)$_POST['category_id'],
            'sku'                 => trim($_POST['sku']),
            'name'                => trim($_POST['name']),
            'description'         => trim($_POST['description'] ?? ''),
            'unit'                => trim($_POST['unit']),
            'price'               => (float)$_POST['price'],
            'stock_quantity'      => (int)$_POST['stock_quantity'],
            'low_stock_threshold' => (int)$_POST['low_stock_threshold'],
        ]));
    } elseif ($action === 'delete') {
        $ok = $product->delete((int)$_POST['id']);
        echo json_encode(['success' => $ok]);
    } elseif ($action === 'get') {
        echo json_encode($product->getById((int)$_POST['id']));
    }
    exit;
}

$product    = new Product();
$products   = $product->getAll();
$categories = $product->getCategories();
$filter     = $_GET['filter'] ?? '';
if ($filter === 'low') {
    $products = $product->getLowStock();
}

$pageTitle  = 'Products';
$activePage = 'products';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Products</h1>
        <div class="page-subtitle"><?= count($products) ?> items · Manage your inventory</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openAdd()">
        <i class="bi bi-plus-lg me-1"></i> Add Product
    </button>
</div>

<!-- Filters + Search -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <a href="?filter=" class="btn btn-sm <?= $filter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    <a href="?filter=low" class="btn btn-sm <?= $filter === 'low' ? 'btn-warning' : 'btn-outline-warning' ?>">
        <i class="bi bi-exclamation-triangle me-1"></i>Low Stock
    </a>
    <input type="text" id="searchBox" class="form-control form-control-sm ms-auto"
           placeholder="Search products…" style="max-width:220px;">
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0" id="productTable">
            <thead><tr>
                <th class="ps-4">SKU</th><th>Product</th><th>Category</th>
                <th>Price</th><th>Stock</th><th>Status</th><th class="text-end pe-4">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
            <tr data-name="<?= strtolower(htmlspecialchars($p['name'] . ' ' . $p['sku'] . ' ' . ($p['category_name'] ?? ''))) ?>">
                <td class="ps-4"><code style="font-size:12px;"><?= htmlspecialchars($p['sku']) ?></code></td>
                <td>
                    <div class="fw-600"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:11px;color:#94A3B8;"><?= htmlspecialchars($p['unit']) ?></div>
                </td>
                <td><span style="font-size:12px;color:#64748B;"><?= htmlspecialchars($p['category_name'] ?? '—') ?></span></td>
                <td class="fw-600">₱<?= number_format($p['price'], 2) ?></td>
                <td>
                    <span class="fw-700 <?= $p['stock_quantity'] == 0 ? 'text-danger' : ($p['is_low_stock'] ? 'text-warning' : 'text-success') ?>">
                        <?= $p['stock_quantity'] ?>
                    </span>
                    <span style="font-size:11px;color:#94A3B8;"> / <?= $p['low_stock_threshold'] ?> min</span>
                </td>
                <td>
                    <?php if ($p['stock_quantity'] == 0): ?>
                        <span class="badge badge-out rounded-pill px-2">Out of Stock</span>
                    <?php elseif ($p['is_low_stock']): ?>
                        <span class="badge badge-low-stock rounded-pill px-2">Low Stock</span>
                    <?php else: ?>
                        <span class="badge badge-in-stock rounded-pill px-2">In Stock</span>
                    <?php endif; ?>
                </td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openEdit(<?= $p['id'] ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm">
                <div class="modal-body">
                    <input type="hidden" id="productId" name="id">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" id="f_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SKU *</label>
                            <input type="text" class="form-control" name="sku" id="f_sku" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category_id" id="f_cat">
                                <option value="">— No category —</option>
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" id="f_unit" value="pcs">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price (₱) *</label>
                            <input type="number" class="form-control" name="price" id="f_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" class="form-control" name="stock_quantity" id="f_stock" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Low Stock Threshold *</label>
                            <input type="number" class="form-control" name="low_stock_threshold" id="f_threshold" min="1" value="10" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="f_desc" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'ENDJS'
// Search filter
document.getElementById('searchBox').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#productTable tbody tr[data-name]').forEach(function(row) {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
});

function openAdd() {
    document.getElementById('modalTitle').textContent = 'Add Product';
    document.getElementById('formAction').value = 'create';
    document.getElementById('productForm').reset();
    document.getElementById('f_threshold').value = 10;
    document.getElementById('f_unit').value = 'pcs';
}

function openEdit(id) {
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'get'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(p => {
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('formAction').value = 'update';
            document.getElementById('productId').value = p.id;
            document.getElementById('f_name').value = p.name;
            document.getElementById('f_sku').value = p.sku;
            document.getElementById('f_cat').value = p.category_id ?? '';
            document.getElementById('f_unit').value = p.unit;
            document.getElementById('f_price').value = p.price;
            document.getElementById('f_stock').value = p.stock_quantity;
            document.getElementById('f_threshold').value = p.low_stock_threshold;
            document.getElementById('f_desc').value = p.description ?? '';
            new bootstrap.Modal(document.getElementById('productModal')).show();
        });
}

function deleteProduct(id, name) {
    if (!confirm('Archive "' + name + '"? It will no longer appear in orders.')) return;
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'delete'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) { showToast('Product archived.'); setTimeout(() => location.reload(), 800); }
            else showToast('Failed to archive.', 'danger');
        });
}

document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = 'Saving…';
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            btn.disabled = false; btn.textContent = 'Save Product';
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                showToast('Product saved!');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Error saving.', 'danger');
            }
        });
});
ENDJS;
include __DIR__ . '/../includes/footer.php';
?>
