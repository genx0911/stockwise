<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';

Auth::requireRole('admin');

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $product = new Product();
    $db      = Database::getInstance();
    $action  = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name) { echo json_encode(['success' => false, 'message' => 'Name is required.']); exit; }
        $stmt = $db->prepare('SELECT id FROM categories WHERE name = ?');
        $stmt->execute([$name]);
        if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Category already exists.']); exit; }
        $product->createCategory($name, $desc);
        echo json_encode(['success' => true]);
    } elseif ($action === 'update') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name) { echo json_encode(['success' => false, 'message' => 'Name is required.']); exit; }
        $stmt = $db->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Name already in use.']); exit; }
        $stmt = $db->prepare('UPDATE categories SET name=?, description=? WHERE id=?');
        $stmt->execute([$name, $desc, $id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete: category has products.']);
        } else {
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            echo json_encode(['success' => true]);
        }
    } elseif ($action === 'get') {
        $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([(int)$_POST['id']]);
        echo json_encode($stmt->fetch());
    }
    exit;
}

$product    = new Product();
$db         = Database::getInstance();
$categories = $db->query('
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
    GROUP BY c.id ORDER BY c.name
')->fetchAll();

$pageTitle  = 'Categories';
$activePage = 'categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Categories</h1>
        <div class="page-subtitle"><?= count($categories) ?> categories · Organise your inventory</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick="openAdd()">
        <i class="bi bi-plus-lg me-1"></i> Add Category
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-4">Name</th><th>Description</th><th>Products</th>
                <th>Created</th><th class="text-end pe-4">Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($categories)): ?>
            <tr><td colspan="5" class="text-center text-muted py-5">No categories yet.</td></tr>
            <?php else: foreach ($categories as $c): ?>
            <tr>
                <td class="ps-4 fw-600"><?= htmlspecialchars($c['name']) ?></td>
                <td class="text-muted" style="font-size:13px;"><?= htmlspecialchars($c['description'] ?? '—') ?></td>
                <td>
                    <span class="badge bg-light text-dark border"><?= $c['product_count'] ?> product<?= $c['product_count'] != 1 ? 's' : '' ?></span>
                </td>
                <td class="text-muted" style="font-size:12px;"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openEdit(<?= $c['id'] ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="catForm">
                <div class="modal-body">
                    <input type="hidden" id="catId" name="id">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" id="catAction" value="create">
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" class="form-control" name="name" id="c_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="c_desc" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="catSubmit">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function openAdd() {
    document.getElementById('catModalTitle').textContent = 'Add Category';
    document.getElementById('catAction').value = 'create';
    document.getElementById('catForm').reset();
}

function openEdit(id) {
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'get'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(c => {
            document.getElementById('catModalTitle').textContent = 'Edit Category';
            document.getElementById('catAction').value = 'update';
            document.getElementById('catId').value = c.id;
            document.getElementById('c_name').value = c.name;
            document.getElementById('c_desc').value = c.description ?? '';
            new bootstrap.Modal(document.getElementById('catModal')).show();
        });
}

function deleteCategory(id, name) {
    if (!confirm('Delete category "' + name + '"?')) return;
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'delete'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) { showToast('Category deleted.'); setTimeout(() => location.reload(), 800); }
            else showToast(res.message, 'danger');
        });
}

document.getElementById('catForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const btn = document.getElementById('catSubmit');
    btn.disabled = true; btn.textContent = 'Saving…';
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            btn.disabled = false; btn.textContent = 'Save Category';
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('catModal')).hide();
                showToast('Category saved!');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Error saving.', 'danger');
            }
        });
});
JS;
include __DIR__ . '/../includes/footer.php';
?>
