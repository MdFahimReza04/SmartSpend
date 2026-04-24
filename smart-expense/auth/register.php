<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account — SmartSpend</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php
require_once '../config/db.php';
require_once '../includes/session.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $sq = trim($_POST['security_question'] ?? '');
    $sa = trim($_POST['security_answer'] ?? '');

    if (!$name || !$email || !$password || !$sq || !$sa) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, security_question, security_answer) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $sq, strtolower($sa)]);
            $uid = $pdo->lastInsertId();
            $pdo->prepare("CALL seed_default_categories(?)")->execute([$uid]);
            $success = true;
        }
    }
}
?>

<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-brand">
            <div class="vi">💰</div>
            <span>SmartSpend</span>
        </div>
        <h2>Start your journey to <strong>financial clarity</strong></h2>
        <p>Join thousands of users who track their finances intelligently with AI-powered forecasting and smart budget tracking.</p>
        <div class="auth-stat-row">
            <div class="auth-stat">
                <span class="num">6+</span>
                <span class="lbl">Categories</span>
            </div>
            <div class="auth-stat">
                <span class="num">ML</span>
                <span class="lbl">Forecasts</span>
            </div>
            <div class="auth-stat">
                <span class="num">Free</span>
                <span class="lbl">Always</span>
            </div>
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-card">
            <h1>Create account</h1>
            <p class="subtitle">Free forever, no credit card needed.</p>

            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">Account created! <a href="login.php" style="font-weight:600;color:var(--teal-dim)">Sign in now →</a></div>
            <?php else: ?>

            <form method="POST">
                <div class="form-group">
                    <label>Full name</label>
                    <input type="text" name="name" placeholder="Your full name" required>
                </div>
                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label>Password <span style="color:var(--text-3);font-weight:400">(min. 6 chars)</span></label>
                    <input type="password" name="password" placeholder="••••••••" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Security question</label>
                    <select name="security_question">
                        <option>What is your mother's maiden name?</option>
                        <option>What was the name of your first pet?</option>
                        <option>What city were you born in?</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Security answer</label>
                    <input type="text" name="security_answer" placeholder="Your answer" required>
                </div>
                <button type="submit" class="btn btn-accent btn-lg" style="width:100%">Create account</button>
                <p style="text-align:center;margin-top:1.25rem;font-size:.84rem;color:var(--text-3)">
                    Already have an account? <a href="login.php" style="color:var(--indigo);font-weight:500">Sign in</a>
                </p>
            </form>

            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
