<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Auth.php';

Auth::check();
$me = Auth::user();
$db = Database::getInstance();

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $current = $_POST['current_password'] ?? '';
    $newPw   = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$name)  $errors[] = 'Name is required.';
    if (!$email) $errors[] = 'Email is required.';

    // Check email not taken by someone else
    if ($email) {
        $chk = $db->prepare('SELECT id FROM users WHERE email=? AND id!=?');
        $chk->execute([$email, $me['id']]);
        if ($chk->fetch()) $errors[] = 'Email already in use by another account.';
    }

    // Password change (optional)
    $changePassword = !empty($newPw) || !empty($current);
    if ($changePassword) {
        $row = $db->prepare('SELECT password FROM users WHERE id=?');
        $row->execute([$me['id']]);
        $row = $row->fetch();
        if (!password_verify($current, $row['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
    }

    if (empty($errors)) {
        if ($changePassword) {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE users SET name=?, email=?, password=? WHERE id=?')
               ->execute([$name, $email, $hash, $me['id']]);
        } else {
            $db->prepare('UPDATE users SET name=?, email=? WHERE id=?')
               ->execute([$name, $email, $me['id']]);
        }
        $_SESSION['user_name'] = $name;
        $success = 'Profile updated successfully.';
    }
}

// Reload fresh data
$row = $db->prepare('SELECT name, email, role, created_at FROM users WHERE id=?');
$row->execute([$me['id']]);
$profile = $row->fetch();

$pageTitle  = 'My Profile';
$activePage = 'profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">My Profile</h1>
    <div class="page-subtitle">Update your name, email, or password</div>
</div>

<div class="row g-3 justify-content-center">
    <div class="col-md-6">

        <?php if ($success): ?>
        <div class="alert alert-success d-flex gap-2 align-items-center mb-3" style="border-radius:10px;font-size:13.5px;">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?>
        <div class="alert alert-danger mb-3" style="border-radius:10px;font-size:13.5px;">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Account Details</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4 pb-3" style="border-bottom:1px solid #E2E8F0;">
                    <div style="width:56px;height:56px;border-radius:50%;background:#EFF6FF;color:#2563EB;
                                display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;">
                        <?= strtoupper(substr($profile['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700;"><?= htmlspecialchars($profile['name']) ?></div>
                        <div style="font-size:12px;color:#64748B;">
                            <?= ucfirst($profile['role']) ?> · Member since <?= date('M Y', strtotime($profile['created_at'])) ?>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="name"
                               value="<?= htmlspecialchars($profile['name']) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="form-control" name="email"
                               value="<?= htmlspecialchars($profile['email']) ?>" required>
                    </div>

                    <div class="mb-3" style="font-size:12px;font-weight:700;text-transform:uppercase;
                                            letter-spacing:.06em;color:#64748B;padding-bottom:8px;
                                            border-bottom:1px solid #E2E8F0;">
                        Change Password <span class="fw-normal text-muted">(leave blank to keep current)</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" placeholder="••••••••">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" placeholder="Min. 6 characters" minlength="6">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
