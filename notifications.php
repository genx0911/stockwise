<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

Auth::check();
$me = Auth::user();
$db = Database::getInstance();

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    $db->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([$me['id']]);
    header('Location: ' . APP_URL . '/notifications.php');
    exit;
}

// Mark single as read via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $db->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?')
       ->execute([(int)$_POST['id'], $me['id']]);
    echo json_encode(['success' => true]);
    exit;
}

$notifications = $db->prepare('
    SELECT n.*, o.order_no
    FROM notifications n
    LEFT JOIN orders o ON o.id = n.order_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 100
');
$notifications->execute([$me['id']]);
$notifications = $notifications->fetchAll();

$unread = count(array_filter($notifications, fn($n) => !$n['is_read']));

$pageTitle  = 'Notifications';
$activePage = 'notifications';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title">Notifications</h1>
        <div class="page-subtitle"><?= $unread ?> unread</div>
    </div>
    <?php if ($unread > 0): ?>
    <form method="POST">
        <button type="submit" name="mark_all" value="1" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-check2-all me-1"></i> Mark all as read
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-bell d-block fs-2 mb-2 opacity-25"></i>
            No notifications yet.
        </div>
        <?php else: ?>
        <ul class="list-group list-group-flush" id="notifList">
        <?php foreach ($notifications as $n): ?>
        <?php
            $icons = ['approved' => 'check-circle-fill text-success', 'rejected' => 'x-circle-fill text-danger', 'fulfilled' => 'box-seam-fill text-primary'];
            $icon  = $icons[$n['type']] ?? 'bell-fill text-secondary';
            $detailUrl = $n['order_id']
                ? APP_URL . '/' . ($me['role'] === 'admin' ? 'admin' : 'staff') . '/orders.php'
                : '#';
        ?>
        <li class="list-group-item d-flex align-items-start gap-3 py-3 px-4 <?= !$n['is_read'] ? 'bg-light' : '' ?>"
            id="notif-<?= $n['id'] ?>" style="border-left: 3px solid <?= !$n['is_read'] ? '#2563EB' : 'transparent' ?>;">
            <i class="bi bi-<?= $icon ?> fs-5 mt-1 flex-shrink-0"></i>
            <div class="flex-fill">
                <div style="font-size:13.5px;font-weight:<?= !$n['is_read'] ? '600' : '400' ?>;">
                    <?= htmlspecialchars($n['message']) ?>
                </div>
                <div style="font-size:11px;color:#94A3B8;margin-top:3px;">
                    <?= date('M d, Y g:i A', strtotime($n['created_at'])) ?>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-shrink-0">
                <?php if ($n['order_id']): ?>
                <a href="<?= $detailUrl ?>" class="btn btn-sm btn-outline-secondary" style="font-size:11px;">View Order</a>
                <?php endif; ?>
                <?php if (!$n['is_read']): ?>
                <button class="btn btn-sm btn-outline-primary" style="font-size:11px;" onclick="markRead(<?= $n['id'] ?>)">Mark read</button>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php
$extraJs = <<<JS
function markRead(id) {
    const fd = new FormData();
    fd.append('ajax', 1); fd.append('id', id);
    fetch('', { method: 'POST', body: fd }).then(r => r.json()).then(() => {
        const el = document.getElementById('notif-' + id);
        el.classList.remove('bg-light');
        el.style.borderLeftColor = 'transparent';
        el.querySelector('.fw-600, [style*="font-weight:600"]')?.style.removeProperty('font-weight');
        el.querySelector('button[onclick]')?.remove();
    });
}
JS;
include __DIR__ . '/includes/footer.php';
?>
