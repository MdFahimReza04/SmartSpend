<?php
$pageTitle = 'Add Expense';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid = currentUserId();
$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$cats->execute([$uid]);
$categories = $cats->fetchAll();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $cat_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $date   = $_POST['expense_date'] ?? '';
    $note   = trim($_POST['note'] ?? '');

    if (!$amount || $amount <= 0 || !$cat_id || !$date) {
        $error = 'Please fill in all required fields correctly.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO expenses (user_id, category_id, amount, note, expense_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $cat_id, $amount, $note, $date]);
        $success = 'Expense recorded successfully!';
    }
}
?>

<div class="page-header">
    <div>
        <h2>Add expense</h2>
        <p>Record a new expense transaction.</p>
    </div>
    <a href="list.php" class="btn btn-secondary">← Back to list</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <?= $success ?>
    <a href="list.php" style="margin-left:.5rem;font-weight:600;color:var(--teal-dim)">View expenses →</a>
</div>
<?php endif; ?>

<div class="form-card">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Amount (BDT) *</label>
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Category *</label>
            <select name="category_id" required>
                <option value="">— Select category —</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Note <span style="color:var(--text-3);font-weight:400">(optional)</span></label>
            <textarea name="note" placeholder="e.g. Lunch at office, monthly electricity bill…"></textarea>
        </div>

        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-accent">Save expense</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
