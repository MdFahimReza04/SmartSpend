<?php
$pageTitle = 'My Profile';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid = currentUserId();

// Fetch current user data
$stmt = $pdo->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update name & email ────────────────────────────────
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$name || !$email) {
            $error = 'Name and email cannot be empty.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email not taken by another user
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $uid]);
            if ($check->fetch()) {
                $error = 'That email address is already in use by another account.';
            } else {
                $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")
                    ->execute([$name, $email, $uid]);
                $_SESSION['name'] = $name;
                $user['name']  = $name;
                $user['email'] = $email;
                $success = 'Profile updated successfully!';
            }
        }
    }

    // ── Change password ────────────────────────────────────
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (!$current || !$new || !$confirm) {
            $error = 'All password fields are required.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $row->execute([$uid]);
            $hash = $row->fetchColumn();

            if (!password_verify($current, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
                $success = 'Password changed successfully!';
            }
        }
    }
}

// Build initials
$initials = strtoupper(substr($user['name'], 0, 1));
if (strpos($user['name'], ' ') !== false) {
    $parts    = explode(' ', $user['name']);
    $initials = strtoupper($parts[0][0] . end($parts)[0]);
}
?>

<div class="page-header">
    <div>
        <h2>My Profile</h2>
        <p>Manage your account details and password.</p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger" style="max-width:560px;margin-bottom:1.25rem">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;margin-top:2px">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success" style="max-width:560px;margin-bottom:1.25rem">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;margin-top:2px">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;max-width:1100px">

    <!-- ── Left: Avatar + Profile Info ── -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">

        <!-- Avatar card -->
        <div class="form-card" style="max-width:none;display:flex;align-items:center;gap:1.25rem">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--indigo);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0;letter-spacing:.02em">
                <?= $initials ?>
            </div>
            <div>
                <div style="font-size:1.1rem;font-weight:600;color:var(--text)"><?= htmlspecialchars($user['name']) ?></div>
                <div style="font-size:.83rem;color:var(--text-3);margin-top:.2rem"><?= htmlspecialchars($user['email']) ?></div>
                <div style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center">
                    <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-info' ?>"><?= ucfirst($user['role']) ?></span>
                    <span style="font-size:.75rem;color:var(--text-3)">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Update name & email -->
        <div class="form-card" style="max-width:none">
            <h3 style="font-size:.92rem;font-weight:600;color:var(--text);margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Account Information
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label>Full name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <button type="submit" class="btn btn-accent">Save changes</button>
            </form>
        </div>
    </div>

    <!-- ── Right: Change Password ── -->
    <div class="form-card" style="max-width:none">
        <h3 style="font-size:.92rem;font-weight:600;color:var(--text);margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Change Password
        </h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Current password</label>
                <input type="password" name="current_password" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label>New password <span style="color:var(--text-3);font-weight:400">(min. 6 characters)</span></label>
                <input type="password" name="new_password" placeholder="••••••••" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm new password</label>
                <input type="password" name="confirm_password" placeholder="••••••••" required minlength="6">
            </div>

            <!-- Password strength indicator -->
            <div style="margin-bottom:1.1rem">
                <div style="font-size:.78rem;color:var(--text-3);margin-bottom:.4rem">Password strength</div>
                <div style="height:4px;background:var(--surface-3);border-radius:20px;overflow:hidden">
                    <div id="strength-bar" style="height:100%;width:0%;border-radius:20px;transition:width .3s,background .3s;background:#e8c547"></div>
                </div>
                <div id="strength-label" style="font-size:.75rem;color:var(--text-3);margin-top:.3rem"></div>
            </div>

            <button type="submit" class="btn btn-accent">Change password</button>
        </form>
    </div>

</div>

<script>
// Password strength meter
const newPwInput = document.querySelector('input[name="new_password"]');
const bar        = document.getElementById('strength-bar');
const lbl        = document.getElementById('strength-label');

newPwInput.addEventListener('input', function() {
    const v = this.value;
    let score = 0;
    if (v.length >= 6)  score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;

    const levels = [
        { w: '0%',   color: 'var(--surface-3)', text: '' },
        { w: '25%',  color: '#f05c6a',           text: 'Weak' },
        { w: '50%',  color: '#e8c547',           text: 'Fair' },
        { w: '75%',  color: '#00b4a0',           text: 'Good' },
        { w: '100%', color: '#5b7fff',           text: 'Strong' },
    ];
    const lvl = v.length === 0 ? levels[0] : levels[Math.min(score, 4)];
    bar.style.width      = lvl.w;
    bar.style.background = lvl.color;
    lbl.textContent      = lvl.text;
});
</script>

<?php require_once '../includes/footer.php'; ?>
