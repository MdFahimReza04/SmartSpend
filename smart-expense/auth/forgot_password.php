<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — SmartSpend</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php
require_once '../config/db.php';
require_once '../includes/session.php';

$step  = 1;
$error = '';
$user  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([trim($_POST['email'])]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['reset_uid'] = $user['id'];
            $step = 2;
        } else {
            $error = 'Email not found.';
        }
    } elseif (isset($_POST['security_answer']) && isset($_SESSION['reset_uid'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['reset_uid']]);
        $user = $stmt->fetch();
        if ($user && strtolower(trim($_POST['security_answer'])) === $user['security_answer']) {
            $step = 3;
        } else {
            $error = 'Incorrect answer.';
            $step  = 2;
        }
    } elseif (isset($_POST['new_password']) && isset($_SESSION['reset_uid'])) {
        $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $_SESSION['reset_uid']]);
        unset($_SESSION['reset_uid']);
        header('Location: login.php?reset=1');
        exit;
    }
}

$stepLabels = ['Enter email', 'Verify identity', 'New password'];
?>
<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-brand">
            <div class="vi">💰</div>
            <span>SmartSpend</span>
        </div>
        <h2>Account <strong>recovery</strong></h2>
        <p>Answer your security question to verify your identity and reset your password securely.</p>
    </div>

    <div class="auth-form-side">
        <div class="auth-card">
            <h1>Reset password</h1>
            <p class="subtitle">Step <?= $step ?> of 3 — <?= $stepLabels[$step-1] ?></p>

            <!-- Step indicators -->
            <div style="display:flex;gap:.5rem;margin-bottom:1.75rem">
                <?php for($i=1;$i<=3;$i++): ?>
                <div style="flex:1;height:4px;border-radius:20px;background:<?= $i <= $step ? 'var(--indigo)' : 'var(--border)' ?>"></div>
                <?php endfor; ?>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>
                <button type="submit" class="btn btn-accent btn-lg" style="width:100%">Continue</button>
            </form>

            <?php elseif ($step === 2 && $user): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Security question</label>
                    <div style="padding:.65rem .9rem;background:var(--surface-2);border:1.5px solid var(--border);border-radius:var(--radius);font-size:.9rem;color:var(--text-2)">
                        <?= htmlspecialchars($user['security_question']) ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Your answer</label>
                    <input type="text" name="security_answer" placeholder="Your answer" required>
                </div>
                <button type="submit" class="btn btn-accent btn-lg" style="width:100%">Verify</button>
            </form>

            <?php elseif ($step === 3): ?>
            <form method="POST">
                <div class="form-group">
                    <label>New password <span style="color:var(--text-3);font-weight:400">(min. 6 chars)</span></label>
                    <input type="password" name="new_password" placeholder="••••••••" required minlength="6">
                </div>
                <button type="submit" class="btn btn-accent btn-lg" style="width:100%">Reset password</button>
            </form>
            <?php endif; ?>

            <p style="text-align:center;margin-top:1.25rem;font-size:.84rem;color:var(--text-3)">
                <a href="login.php" style="color:var(--indigo)">← Back to sign in</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
