<?php
// includes/header.php
// Usage: include with $pageTitle and $activePage set
if (session_status() === PHP_SESSION_NONE) session_start();
Auth::check();
$user = Auth::user();

// Unread notification count for current user
$_notifDb   = Database::getInstance();
$_notifStmt = $_notifDb->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$_notifStmt->execute([$user['id']]);
$_unreadCount = (int)$_notifStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> · <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 240px;
            --brand: #2563EB;
            --brand-dark: #1D4ED8;
            --success: #16A34A;
            --warning: #D97706;
            --danger: #DC2626;
            --surface: #F8FAFC;
            --border: #E2E8F0;
            --text: #0F172A;
            --muted: #64748B;
        }
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--surface); color: var(--text); }

        /* Sidebar */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background: #0F172A;
            display: flex; flex-direction: column;
            z-index: 100; overflow-y: auto;
        }
        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-brand-name {
            font-size: 18px; font-weight: 800;
            color: white; letter-spacing: -0.03em;
        }
        .sidebar-brand-name span { color: #60A5FA; }
        .sidebar-role-badge {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
            background: rgba(96,165,250,0.15);
            color: #60A5FA; border-radius: 4px;
            padding: 2px 8px; margin-top: 6px; display: inline-block;
        }
        .sidebar-nav { padding: 16px 12px; flex: 1; }
        .sidebar-section-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: #475569; padding: 0 8px; margin: 16px 0 6px;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 8px;
            color: #94A3B8; text-decoration: none;
            font-size: 13.5px; font-weight: 500;
            transition: all 0.15s; margin-bottom: 2px;
        }
        .sidebar-link:hover { background: rgba(255,255,255,0.06); color: white; }
        .sidebar-link.active { background: #2563EB; color: white; }
        .sidebar-link i { font-size: 16px; width: 18px; text-align: center; }
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-user { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .sidebar-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: #2563EB; color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; flex-shrink: 0;
        }
        .sidebar-user-name { font-size: 13px; font-weight: 600; color: white; }
        .sidebar-user-role { font-size: 11px; color: #64748B; }
        .btn-logout {
            display: flex; align-items: center; gap: 8px;
            width: 100%; padding: 8px 12px; border-radius: 8px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: #94A3B8; font-size: 13px; font-weight: 500;
            text-decoration: none; transition: all 0.15s;
        }
        .btn-logout:hover { background: rgba(220,38,38,0.15); color: #FCA5A5; border-color: rgba(220,38,38,0.3); }
        .notif-badge {
            display:inline-flex;align-items:center;justify-content:center;
            min-width:16px;height:16px;padding:0 4px;
            background:#EF4444;color:white;border-radius:99px;
            font-size:10px;font-weight:700;line-height:1;
        }

        /* Main content */
        .main { margin-left: var(--sidebar-width); padding: 32px; min-height: 100vh; }
        .page-header { margin-bottom: 28px; }
        .page-title { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; margin: 0; }
        .page-subtitle { font-size: 13px; color: var(--muted); margin-top: 4px; }

        /* Cards */
        .card { border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .card-header { background: white; border-bottom: 1px solid var(--border); padding: 16px 20px; font-weight: 700; font-size: 14px; }

        /* KPI cards */
        .kpi-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; }
        .kpi-card.border-blue  { border-top: 3px solid var(--brand); }
        .kpi-card.border-green { border-top: 3px solid var(--success); }
        .kpi-card.border-red   { border-top: 3px solid var(--danger); }
        .kpi-card.border-amber { border-top: 3px solid var(--warning); }
        .kpi-label { font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
        .kpi-value { font-size: 26px; font-weight: 800; letter-spacing: -0.03em; }
        .kpi-sub   { font-size: 12px; color: var(--muted); margin-top: 4px; }

        /* Tables */
        .table { font-size: 13.5px; }
        .table th { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); background: #F8FAFC; }
        .table td { vertical-align: middle; border-color: var(--border); }
        .table-hover tbody tr:hover { background: #F8FAFC; }

        /* Badges */
        .badge-pending   { background:#FEF3C7; color:#92400E; font-weight:600; }
        .badge-approved  { background:#DCFCE7; color:#14532D; font-weight:600; }
        .badge-rejected  { background:#FEE2E2; color:#7F1D1D; font-weight:600; }
        .badge-fulfilled { background:#DBEAFE; color:#1E3A5F; font-weight:600; }
        .badge-low-stock { background:#FEF3C7; color:#92400E; font-weight:600; }
        .badge-in-stock  { background:#DCFCE7; color:#14532D; font-weight:600; }
        .badge-out       { background:#FEE2E2; color:#7F1D1D; font-weight:600; }

        /* Alerts */
        .alert-low-stock {
            background: #FFFBEB; border: 1.5px solid #FDE68A;
            border-radius: 10px; padding: 14px 18px;
            font-size: 13px; color: #78350F;
        }

        /* Buttons */
        .btn-primary   { background: var(--brand); border-color: var(--brand); font-weight: 600; }
        .btn-primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
        .btn { font-size: 13px; font-weight: 600; border-radius: 8px; }
        .btn-sm { font-size: 12px; }

        /* Modal */
        .modal-header { border-bottom: 1px solid var(--border); }
        .modal-footer { border-top: 1px solid var(--border); }
        .modal-title  { font-weight: 800; font-size: 16px; }

        /* Forms */
        .form-label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
        .form-control, .form-select { font-size: 13.5px; border-color: var(--border); border-radius: 8px; }
        .form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        /* Toast */
        .toast-container { z-index: 9999; }
        #toast { min-width: 280px; border-radius: 10px; font-size: 13.5px; font-weight: 500; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-name">Stock<span>Wise</span></div>
        <div class="sidebar-role-badge"><?= ucfirst($user['role']) ?></div>
    </div>
    <nav class="sidebar-nav">
        <?php if (Auth::isAdmin()): ?>
        <div class="sidebar-section-label">Main</div>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <div class="sidebar-section-label">Inventory</div>
        <a href="<?= APP_URL ?>/admin/products.php" class="sidebar-link <?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a href="<?= APP_URL ?>/admin/categories.php" class="sidebar-link <?= ($activePage ?? '') === 'categories' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Categories
        </a>
        <div class="sidebar-section-label">Orders</div>
        <a href="<?= APP_URL ?>/admin/orders.php" class="sidebar-link <?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-check"></i> All Orders
        </a>
        <div class="sidebar-section-label">Analytics</div>
        <a href="<?= APP_URL ?>/admin/reports.php" class="sidebar-link <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        <a href="<?= APP_URL ?>/admin/restock.php" class="sidebar-link <?= ($activePage ?? '') === 'restock' ? 'active' : '' ?>">
            <i class="bi bi-stars"></i> AI Restock
        </a>
        <div class="sidebar-section-label">System</div>
        <a href="<?= APP_URL ?>/admin/users.php" class="sidebar-link <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Users
        </a>
        <a href="<?= APP_URL ?>/admin/logs.php" class="sidebar-link <?= ($activePage ?? '') === 'logs' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Activity Log
        </a>
        <?php else: ?>
        <div class="sidebar-section-label">Main</div>
        <a href="<?= APP_URL ?>/staff/dashboard.php" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i> Dashboard
        </a>
        <div class="sidebar-section-label">Inventory</div>
        <a href="<?= APP_URL ?>/staff/inventory.php" class="sidebar-link <?= ($activePage ?? '') === 'inventory' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> View Stock
        </a>
        <div class="sidebar-section-label">Orders</div>
        <a href="<?= APP_URL ?>/staff/orders.php" class="sidebar-link <?= ($activePage ?? '') === 'orders' ? 'active' : '' ?>">
            <i class="bi bi-cart3"></i> My Orders
        </a>
        <a href="<?= APP_URL ?>/staff/new_order.php" class="sidebar-link <?= ($activePage ?? '') === 'new_order' ? 'active' : '' ?>">
            <i class="bi bi-plus-circle"></i> New Order
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($user['name']) ?></div>
                <div class="sidebar-user-role"><?= ucfirst($user['role']) ?></div>
            </div>
        </div>
        <div class="d-flex gap-2 mb-2">
            <a href="<?= APP_URL ?>/notifications.php" class="sidebar-link flex-fill mb-0 position-relative" style="padding:7px 12px;">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($_unreadCount > 0): ?>
                <span class="notif-badge ms-auto"><?= $_unreadCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/profile.php" class="sidebar-link mb-0" style="padding:7px 10px;" title="My Profile">
                <i class="bi bi-person-gear"></i>
            </a>
        </div>
        <a href="<?= APP_URL ?>/logout.php" class="btn-logout">
            <i class="bi bi-box-arrow-left"></i> Sign out
        </a>
    </div>
</aside>

<div class="main">
<!-- Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
