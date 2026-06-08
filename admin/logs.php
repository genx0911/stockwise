<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::requireRole('admin');

$db     = Database::getInstance();
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$total = $db->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
$pages = (int)ceil($total / $limit);

$logs = $db->query("
    SELECT al.*, u.name AS user_name, u.role AS user_role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT $limit OFFSET $offset
")->fetchAll();

$pageTitle  = 'Activity Log';
$activePage = 'logs';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Activity Log</h1>
    <div class="page-subtitle"><?= number_format($total) ?> entries recorded</div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th class="ps-4">User</th><th>Role</th><th>Action</th>
                <th>Description</th><th>IP Address</th><th>Time</th>
            </tr></thead>
            <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">No activity recorded yet.</td></tr>
            <?php else: foreach ($logs as $log): ?>
            <tr>
                <td class="ps-4 fw-600"><?= $log['user_name'] ? htmlspecialchars($log['user_name']) : '<span class="text-muted">Deleted user</span>' ?></td>
                <td>
                    <?php if ($log['user_role']): ?>
                    <span class="badge rounded-pill px-2 <?= $log['user_role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                        <?= ucfirst($log['user_role']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="fw-600" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;
                        color:<?= $log['action'] === 'login' ? '#16A34A' : ($log['action'] === 'logout' ? '#64748B' : '#2563EB') ?>;">
                        <?= htmlspecialchars($log['action']) ?>
                    </span>
                </td>
                <td style="font-size:13px;color:#374151;"><?= htmlspecialchars($log['description'] ?? '—') ?></td>
                <td style="font-size:12px;" class="text-muted font-monospace"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                <td style="font-size:12px;" class="text-muted"><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-body border-top d-flex justify-content-between align-items-center" style="font-size:13px;">
        <span class="text-muted">Page <?= $page ?> of <?= $pages ?></span>
        <div class="d-flex gap-1">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-secondary">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-secondary">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
