<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — SmartSpend</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php
require_once '../config/db.php';
require_once '../includes/session.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            header('Location: ../dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-brand">
            <div class="vi">💰</div>
            <span>SmartSpend</span>
        </div>
        <h2>Take control of your <strong>financial future</strong></h2>
        <p>Track every taka, forecast your spending, and build better money habits with AI-powered insights.</p>
        <div class="auth-stat-row">
            <div class="auth-stat">
                <span class="num">৳0</span>
                <span class="lbl">Hidden fees</span>
            </div>
            <div class="auth-stat">
                <span class="num">ML</span>
                <span class="lbl">Predictions</span>
            </div>
            <div class="auth-stat">
                <span class="num">∞</span>
                <span class="lbl">Categories</span>
            </div>
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="subtitle">Sign in to your account to continue.</p>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="flex-shrink:0;margin-top:2px">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success">Password reset successfully. Please sign in.</div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" name="email" placeholder="you@example.com" required autocomplete="email">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
                <div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem">
                    <a href="forgot_password.php" style="font-size:.82rem;color:var(--text-3)">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-accent btn-lg" style="width:100%">Sign in</button>
                <p style="text-align:center;margin-top:1.25rem;font-size:.84rem;color:var(--text-3)">
                    No account? <a href="register.php" style="color:var(--indigo);font-weight:500">Create one</a>
                </p>
            </form>
        </div>
    </div>
</div>
</body>
</html>
