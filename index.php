<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → redirect
if (!empty($_SESSION['logged_in'])) {
    $dest = $_SESSION['user_role'] === 'admin' ? '/admin/dashboard.php' : '/staff/dashboard.php';
    header('Location: ' . APP_URL . $dest);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth   = new Auth();
    $result = $auth->login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($result['success']) {
        $dest = $result['role'] === 'admin' ? '/admin/dashboard.php' : '/staff/dashboard.php';
        header('Location: ' . APP_URL . $dest);
        exit;
    }
    $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · StockWise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        * { font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: #050D1F;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            overflow: hidden;
            position: relative;
        }

        /* ── Animated background blobs ── */
        .bg-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            z-index: 0;
        }
        .bg-blob-1 {
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(37,99,235,0.45) 0%, transparent 70%);
            top: -220px; left: -220px;
            animation: blob1 12s ease-in-out infinite;
        }
        .bg-blob-2 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(124,58,237,0.35) 0%, transparent 70%);
            bottom: -180px; right: -180px;
            animation: blob2 15s ease-in-out infinite;
        }
        .bg-blob-3 {
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(8,145,178,0.3) 0%, transparent 70%);
            top: 55%; left: 58%;
            animation: blob3 10s ease-in-out infinite;
        }
        .bg-blob-4 {
            width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(219,39,119,0.2) 0%, transparent 70%);
            top: 20%; right: 18%;
            animation: blob4 18s ease-in-out infinite;
        }

        @keyframes blob1 {
            0%,100% { transform: translate(0,0) scale(1);    }
            40%     { transform: translate(40px,-40px) scale(1.08); }
            70%     { transform: translate(-20px, 30px) scale(0.94); }
        }
        @keyframes blob2 {
            0%,100% { transform: translate(0,0) scale(1);    }
            35%     { transform: translate(-50px, 30px) scale(1.06); }
            65%     { transform: translate(30px,-20px) scale(0.96); }
        }
        @keyframes blob3 {
            0%,100% { transform: translate(0,0) scale(1);   }
            50%     { transform: translate(-60px,-40px) scale(1.12); }
        }
        @keyframes blob4 {
            0%,100% { transform: translate(0,0) scale(1);   }
            45%     { transform: translate(30px, 50px) scale(1.1); }
        }

        /* ── Subtle dot-grid overlay ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        /* ── Outer card ── */
        .login-wrap {
            display: flex;
            width: 920px;
            max-width: 100%;
            border-radius: 20px;
            overflow: hidden;
            position: relative; z-index: 1;
            box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 4px 24px rgba(0,0,0,0.3);
        }

        /* ── Left panel ── */
        .login-left {
            width: 420px;
            flex-shrink: 0;
            background: #0F172A;
            padding: 44px 40px;
            display: flex;
            flex-direction: column;
            gap: 32px;
            position: relative;
            overflow: hidden;
        }

        /* subtle grid pattern */
        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        /* glowing orb */
        .login-left::after {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37,99,235,0.35) 0%, transparent 70%);
            pointer-events: none;
        }

        .brand-name {
            font-size: 22px; font-weight: 800;
            color: white; letter-spacing: -0.03em;
            position: relative; z-index: 1;
        }
        .brand-name span { color: #60A5FA; }

        .left-body { position: relative; z-index: 1; flex: 1; }

        .login-tagline {
            font-size: 30px; font-weight: 800;
            color: white; letter-spacing: -0.03em;
            line-height: 1.2; margin-bottom: 14px;
        }
        .login-tagline span { color: #60A5FA; }

        .login-desc {
            font-size: 13px; color: #64748B;
            line-height: 1.7; margin-bottom: 28px;
        }

        /* Feature list */
        .feature-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
        .feature-list li {
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; color: #94A3B8;
        }
        .feature-list li .feat-icon {
            width: 28px; height: 28px; border-radius: 8px;
            background: rgba(37,99,235,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }

        /* Demo box */
        .demo-box {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 14px 16px;
        }
        .demo-label {
            font-size: 10px; font-weight: 700;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: #475569; margin-bottom: 10px;
        }
        .demo-row {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 6px 8px; border-radius: 8px;
            cursor: pointer; transition: background 0.15s;
            margin-bottom: 2px;
        }
        .demo-row:last-child { margin-bottom: 0; }
        .demo-row:hover { background: rgba(255,255,255,0.06); }
        .demo-role { font-size: 12px; font-weight: 700; color: #60A5FA; }
        .demo-cred { font-size: 11.5px; color: #94A3B8; font-family: monospace; }
        .demo-fill {
            font-size: 10px; color: #475569; font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600; letter-spacing: 0.05em; opacity: 0;
            transition: opacity 0.15s;
        }
        .demo-row:hover .demo-fill { opacity: 1; }

        /* ── Right panel ── */
        .login-right {
            flex: 1;
            background: white;
            padding: 44px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-title {
            font-size: 24px; font-weight: 800;
            letter-spacing: -0.02em; color: #0F172A;
            margin-bottom: 4px;
        }
        .login-subtitle { font-size: 13px; color: #64748B; margin-bottom: 32px; }

        /* Error / warning alerts */
        .login-alert {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 14px; border-radius: 10px;
            font-size: 13px; font-weight: 500;
            margin-bottom: 20px;
        }
        .login-alert.error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
        .login-alert.warning { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }

        /* Form */
        .form-label {
            font-size: 11px; font-weight: 700;
            color: #64748B; text-transform: uppercase;
            letter-spacing: 0.07em; margin-bottom: 6px;
        }
        .form-control {
            border: 1.5px solid #E2E8F0; border-radius: 10px;
            font-size: 14px; padding: 11px 14px;
            color: #0F172A; transition: border-color 0.15s, box-shadow 0.15s;
        }
        .form-control:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
            outline: none;
        }
        .form-control::placeholder { color: #CBD5E1; }

        /* Password field wrapper */
        .pw-wrap { position: relative; }
        .pw-wrap .form-control { padding-right: 44px; }
        .pw-toggle {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; padding: 0;
            color: #94A3B8; cursor: pointer; font-size: 16px;
            line-height: 1; transition: color 0.15s;
        }
        .pw-toggle:hover { color: #2563EB; }

        /* Submit button */
        .btn-login {
            width: 100%; background: #2563EB; color: white;
            border: none; border-radius: 10px;
            padding: 13px; font-weight: 700; font-size: 14px;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.3);
        }
        .btn-login:hover {
            background: #1D4ED8;
            box-shadow: 0 6px 20px rgba(37,99,235,0.4);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }
        .btn-login:disabled { background: #93C5FD; box-shadow: none; transform: none; cursor: not-allowed; }

        /* ── Responsive: hide left panel on small screens ── */
        @media (max-width: 700px) {
            .login-left { display: none; }
            .login-right { padding: 36px 28px; border-radius: 20px; }
            .login-wrap { border-radius: 20px; }
        }
    </style>
</head>
<body>

<!-- Background blobs -->
<div class="bg-blob bg-blob-1"></div>
<div class="bg-blob bg-blob-2"></div>
<div class="bg-blob bg-blob-3"></div>
<div class="bg-blob bg-blob-4"></div>

<div class="login-wrap">

    <!-- ── Left panel ── -->
    <div class="login-left">
        <div class="brand-name">Stock<span>Wise</span></div>

        <div class="left-body">
            <div class="login-tagline">Inventory<br><span>under control.</span></div>
            <div class="login-desc">Manage stock, approve orders, and keep your whole team in sync — all in one place.</div>

            <ul class="feature-list">
                <li>
                    <div class="feat-icon">📦</div>
                    Real-time inventory tracking & alerts
                </li>
                <li>
                    <div class="feat-icon">📋</div>
                    Order management with approval workflow
                </li>
                <li>
                    <div class="feat-icon">📊</div>
                    Analytics, reports & CSV exports
                </li>
                <li>
                    <div class="feat-icon">🤖</div>
                    AI-powered restock suggestions
                </li>
            </ul>
        </div>

        <div class="demo-box">
            <div class="demo-label">🔑 Demo Accounts — click to fill</div>
            <div class="demo-row" onclick="fillDemo('admin@stockwise.com','admin123')" title="Click to auto-fill">
                <span class="demo-role">Admin</span>
                <span class="demo-cred">admin@stockwise.com / admin123</span>
                <span class="demo-fill">Fill ↗</span>
            </div>
            <div class="demo-row" onclick="fillDemo('staff@stockwise.com','staff123')" title="Click to auto-fill">
                <span class="demo-role">Staff</span>
                <span class="demo-cred">staff@stockwise.com / staff123</span>
                <span class="demo-fill">Fill ↗</span>
            </div>
        </div>
    </div>

    <!-- ── Right panel ── -->
    <div class="login-right">
        <div class="login-title">Welcome back 👋</div>
        <div class="login-subtitle">Sign in to your StockWise account</div>

        <?php if ($error): ?>
        <div class="login-alert error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
        <div class="login-alert warning">
            <i class="bi bi-shield-exclamation"></i>
            You don't have permission to access that page.
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="mb-3">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" name="email" id="email" class="form-control"
                       placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus autocomplete="email">
            </div>

            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="password" class="form-control"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" id="pwToggle" tabindex="-1" aria-label="Show/hide password">
                        <i class="bi bi-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="submitBtn">
                <span id="btnText">Sign In</span>
                <i class="bi bi-arrow-right" id="btnArrow"></i>
            </button>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Password show/hide toggle
document.getElementById('pwToggle').addEventListener('click', function() {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('pwIcon');
    const show = pw.type === 'password';
    pw.type        = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});

// Click-to-fill demo credentials
function fillDemo(email, password) {
    document.getElementById('email').value    = email;
    document.getElementById('password').value = password;
    document.getElementById('email').focus();
}

// Loading state on submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn  = document.getElementById('submitBtn');
    const text = document.getElementById('btnText');
    const arrow = document.getElementById('btnArrow');
    btn.disabled   = true;
    text.textContent = 'Signing in…';
    arrow.className  = 'bi bi-arrow-repeat spin';
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { display: inline-block; animation: spin 0.8s linear infinite; }
</style>
</body>
</html>
