<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';

Auth::requireRole('admin');

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $user   = new User();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        echo json_encode($user->create(
            trim($_POST['name']), trim($_POST['email']),
            $_POST['password'], $_POST['role']
        ));
    } elseif ($action === 'update') {
        echo json_encode($user->update(
            (int)$_POST['id'], trim($_POST['name']),
            trim($_POST['email']), $_POST['role'],
            $_POST['password'] ?: null
        ));
    } elseif ($action === 'toggle') {
        $ok = $user->toggleActive((int)$_POST['id']);
        echo json_encode(['success' => $ok]);
    } elseif ($action === 'delete') {
        echo json_encode($user->delete((int)$_POST['id']));
    } elseif ($action === 'get') {
        echo json_encode($user->getById((int)$_POST['id']));
    }
    exit;
}

$userModel = new User();
$users     = $userModel->getAll();

$pageTitle  = 'Users';
$activePage = 'users';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Users</h1>
        <div class="page-subtitle"><?= count($users) ?> accounts · Manage access and roles</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAdd()">
        <i class="bi bi-person-plus me-1"></i> Add User
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-4">Name</th><th>Email</th><th>Role</th>
                <th>Status</th><th>Created</th><th class="text-end pe-4">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td class="ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <span class="fw-600"><?= htmlspecialchars($u['name']) ?></span>
                    </div>
                </td>
                <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge rounded-pill px-2 <?= $u['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge rounded-pill px-2 <?= $u['is_active'] ? 'badge-in-stock' : 'badge-out' ?>">
                        <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="text-muted" style="font-size:12px;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openEdit(<?= $u['id'] ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php if ($u['id'] != 1): ?>
                    <button class="btn btn-sm btn-outline-warning me-1" onclick="toggleUser(<?= $u['id'] ?>)" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="bi bi-toggle-<?= $u['is_active'] ? 'on text-success' : 'off text-muted' ?>"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="userId" name="id">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="action" id="userAction" value="create">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="name" id="u_name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email Address *</label>
                            <input type="email" class="form-control" name="email" id="u_email" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" id="u_role">
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password <span id="pwHint" class="text-muted fw-normal">(required)</span></label>
                            <input type="password" class="form-control" name="password" id="u_pass" minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="userSubmit">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<JS
function openAdd() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userAction').value = 'create';
    document.getElementById('userForm').reset();
    document.getElementById('u_pass').required = true;
    document.getElementById('pwHint').textContent = '(required)';
}

function openEdit(id) {
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'get'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(u => {
            document.getElementById('userModalTitle').textContent = 'Edit User';
            document.getElementById('userAction').value = 'update';
            document.getElementById('userId').value = u.id;
            document.getElementById('u_name').value = u.name;
            document.getElementById('u_email').value = u.email;
            document.getElementById('u_role').value = u.role;
            document.getElementById('u_pass').value = '';
            document.getElementById('u_pass').required = false;
            document.getElementById('pwHint').textContent = '(leave blank to keep)';
            new bootstrap.Modal(document.getElementById('userModal')).show();
        });
}

function toggleUser(id) {
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'toggle'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) { showToast('Status updated.'); setTimeout(() => location.reload(), 800); }
        });
}

function deleteUser(id, name) {
    if (!confirm('Delete user "' + name + '"? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('action', 'delete'); fd.append('id', id);
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            if (res.success) { showToast('User deleted.'); setTimeout(() => location.reload(), 800); }
            else showToast(res.message, 'danger');
        });
}

document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const btn = document.getElementById('userSubmit');
    btn.disabled = true; btn.textContent = 'Saving…';
    fetch('', { method: 'POST', body: fd })
        .then(r => r.json()).then(res => {
            btn.disabled = false; btn.textContent = 'Save User';
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                showToast('User saved!');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Error saving.', 'danger');
            }
        });
});
JS;
include __DIR__ . '/../includes/footer.php';
?>
