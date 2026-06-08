<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Order.php';

Auth::requireRole('staff');

// AJAX: submit order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $items = json_decode($_POST['items'] ?? '[]', true);
    $notes = trim($_POST['notes'] ?? '');
    $order = new Order();
    $result = $order->create(Auth::user()['id'], $items, $notes);
    echo json_encode($result);
    exit;
}

$product  = new Product();
$products = $product->getAll(true); // active only

$pageTitle  = 'New Order';
$activePage = 'new_order';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">New Order</h1>
    <div class="page-subtitle">Add products to your cart, then submit for admin approval</div>
</div>

<div class="row g-3">
    <!-- Product list -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Select Products</span>
                <input type="text" id="searchBox" class="form-control form-control-sm w-auto" placeholder="🔍 Search…" style="width:180px!important;">
            </div>
            <div class="card-body p-0" style="max-height:520px;overflow-y:auto;">
                <table class="table table-hover mb-0" id="productTable">
                    <thead style="position:sticky;top:0;z-index:1;">
                        <tr>
                            <th class="ps-4">Product</th><th>Stock</th><th>Price</th><th class="text-end pe-4">Add</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                        <td class="ps-4">
                            <div class="fw-600"><?= htmlspecialchars($p['name']) ?></div>
                            <div style="font-size:11px;color:#94A3B8;"><?= htmlspecialchars($p['sku']) ?> · <?= htmlspecialchars($p['unit']) ?></div>
                        </td>
                        <td>
                            <span class="fw-600 <?= $p['stock_quantity'] == 0 ? 'text-danger' : ($p['stock_quantity'] <= $p['low_stock_threshold'] ? 'text-warning' : 'text-success') ?>">
                                <?= $p['stock_quantity'] ?>
                            </span>
                        </td>
                        <td class="fw-600">₱<?= number_format($p['price'], 2) ?></td>
                        <td class="text-end pe-4">
                            <?php if ($p['stock_quantity'] > 0): ?>
                            <button class="btn btn-sm btn-outline-primary"
                                onclick="addToCart(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['price'] ?>, <?= $p['stock_quantity'] ?>)">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:11px;">Out of stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cart -->
    <div class="col-md-5">
        <div class="card" style="position:sticky;top:24px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart3 me-2"></i>Order Cart</span>
                <span class="badge bg-primary rounded-pill" id="cartCount">0</span>
            </div>
            <div class="card-body p-0">
                <div id="cartEmpty" class="text-center text-muted py-5" style="font-size:13px;">
                    <i class="bi bi-cart3 d-block fs-2 mb-2 opacity-25"></i>
                    Cart is empty.<br>Add products from the left.
                </div>
                <table class="table mb-0" id="cartTable" style="display:none;">
                    <thead><tr>
                        <th class="ps-3">Item</th><th>Qty</th><th>Subtotal</th><th></th>
                    </tr></thead>
                    <tbody id="cartBody"></tbody>
                    <tfoot>
                        <tr style="border-top:2px solid #E2E8F0;">
                            <td colspan="2" class="ps-3 fw-700">Total</td>
                            <td class="fw-800" id="cartTotal" style="font-size:16px;">₱0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-body border-top">
                <label class="form-label">Notes / Remarks</label>
                <textarea class="form-control mb-3" id="orderNotes" rows="2" placeholder="e.g. Urgent restock needed…"></textarea>
                <button class="btn btn-primary w-100" id="submitOrderBtn" disabled onclick="submitOrder()">
                    <i class="bi bi-send me-2"></i>Submit Order for Approval
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
let cart = {};

function addToCart(id, name, price, maxStock) {
    if (cart[id]) {
        if (cart[id].qty >= maxStock) { showToast('Max stock reached for this item.', 'danger'); return; }
        cart[id].qty++;
    } else {
        cart[id] = { id, name, price, qty: 1, maxStock };
    }
    renderCart();
}

function removeFromCart(id) {
    delete cart[id];
    renderCart();
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty = Math.max(1, Math.min(cart[id].qty + delta, cart[id].maxStock));
    renderCart();
}

function renderCart() {
    const keys = Object.keys(cart);
    document.getElementById('cartCount').textContent = keys.length;
    document.getElementById('cartEmpty').style.display = keys.length ? 'none' : '';
    document.getElementById('cartTable').style.display = keys.length ? '' : 'none';
    document.getElementById('submitOrderBtn').disabled = keys.length === 0;

    let total = 0;
    const tbody = document.getElementById('cartBody');
    tbody.innerHTML = '';
    keys.forEach(id => {
        const item = cart[id];
        const sub = item.price * item.qty;
        total += sub;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="ps-3">
                <div style="font-size:13px;font-weight:600;">\${item.name}</div>
                <div style="font-size:11px;color:#94A3B8;">₱\${item.price.toFixed(2)} each</div>
            </td>
            <td>
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="changeQty(\${id}, -1)">−</button>
                    <span style="min-width:24px;text-align:center;font-weight:700;">\${item.qty}</span>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="changeQty(\${id}, 1)">+</button>
                </div>
            </td>
            <td class="fw-600">₱\${sub.toFixed(2)}</td>
            <td><button class="btn btn-sm text-danger border-0" onclick="removeFromCart(\${id})"><i class="bi bi-x-lg"></i></button></td>
        `;
        tbody.appendChild(tr);
    });
    document.getElementById('cartTotal').textContent = '₱' + total.toFixed(2);
}

function submitOrder() {
    const items = Object.values(cart).map(i => ({ product_id: i.id, quantity: i.qty }));
    const fd = new FormData();
    fd.append('ajax', 1);
    fd.append('items', JSON.stringify(items));
    fd.append('notes', document.getElementById('orderNotes').value);

    const btn = document.getElementById('submitOrderBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';

    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) {
                showToast('Order ' + res.order_no + ' submitted!');
                setTimeout(() => window.location = '../staff/orders.php', 1000);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Order for Approval';
                showToast(res.message || 'Submission failed.', 'danger');
            }
        });
}

// Search filter
document.getElementById('searchBox').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#productTable tbody tr').forEach(row => {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
});
JS;
include __DIR__ . '/../includes/footer.php';
?>
