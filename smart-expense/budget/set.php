<?php
$pageTitle = 'Set Budget';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid   = currentUserId();
$error = $success = '';

$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$cats->execute([$uid]);
$categories = $cats->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount  = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $cat_id  = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $month   = $_POST['month'] ?? date('Y-m');
    $month_d = $month . '-01';

    if (!$amount || $amount <= 0) {
        $error = 'Please enter a valid budget amount.';
    } else {
        $check = $pdo->prepare("SELECT id FROM budgets WHERE user_id=? AND category_id<=>? AND month=?");
        $check->execute([$uid, $cat_id, $month_d]);
        if ($check->fetch()) {
            $pdo->prepare("UPDATE budgets SET amount=? WHERE user_id=? AND category_id<=>? AND month=?")
                ->execute([$amount, $uid, $cat_id, $month_d]);
            $success = 'Budget updated!';
        } else {
            $pdo->prepare("INSERT INTO budgets (user_id, category_id, month, amount) VALUES (?,?,?,?)")
                ->execute([$uid, $cat_id, $month_d, $amount]);
            $success = 'Budget set!';
        }
    }
}

$bstmt = $pdo->prepare("SELECT b.*, c.name AS cat_name FROM budgets b LEFT JOIN categories c ON b.category_id = c.id WHERE b.user_id = ? AND b.month = ? ORDER BY b.category_id IS NULL DESC, c.name");
$bstmt->execute([$uid, date('Y-m-01')]);
$budgets = $bstmt->fetchAll();

$cat_icons = ['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬'];
?>

<div class="page-header">
    <div>
        <h2>Set budget</h2>
        <p>Define spending limits by category or for the whole month.</p>
    </div>
    <a href="track.php" class="btn btn-secondary">Track budgets →</a>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">
    <div class="form-card" style="max-width:none">
        <h3 style="font-size:.9rem;font-weight:600;margin-bottom:1.25rem">Configure budget</h3>
        <form method="POST">
            <div class="form-group">
                <label>Month</label>
                <input type="month" name="month" value="<?= date('Y-m') ?>" required>
            </div>
            <div class="form-group">
                <label>Category <span style="color:var(--text-3);font-weight:400">(leave blank for overall budget)</span></label>
                <select name="category_id">
                    <option value="">— Overall monthly budget —</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Budget amount (BDT)</label>
                <input type="number" name="amount" step="0.01" min="1" placeholder="e.g. 5000" required>
            </div>
            <button type="submit" class="btn btn-accent">Save budget</button>
        </form>
    </div>

    <div>
        <div class="section-title">
            <h3><?= date('F Y') ?> budgets</h3>
            <span style="font-size:.8rem;color:var(--text-3)"><?= count($budgets) ?> set</span>
        </div>
        <?php if (!empty($budgets)): ?>
        <div class="budget-list">
        <?php foreach ($budgets as $b): ?>
        <div class="budget-card">
            <div class="budget-header">
                <div class="budget-name">
                    <?php if (!$b['category_id']): ?>
                    🎯 Overall
                    <?php else: ?>
                    <?= $cat_icons[$b['cat_name']] ?? '📂' ?> <?= htmlspecialchars($b['cat_name']) ?>
                    <?php endif; ?>
                </div>
                <div class="budget-amounts">
                    <strong>৳ <?= number_format($b['amount'], 0) ?></strong>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="table-card">
            <div class="empty-state">
                <span class="ei">🎯</span>
                <p>No budgets for this month yet.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
