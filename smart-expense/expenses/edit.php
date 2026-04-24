<?php
$pageTitle = 'Edit Expense';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid = currentUserId();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $uid]);
$expense = $stmt->fetch();
if (!$expense) { header('Location: list.php'); exit; }

$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ?");
$cats->execute([$uid]);
$categories = $cats->fetchAll();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $cat_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $date   = $_POST['expense_date'] ?? '';
    $note   = trim($_POST['note'] ?? '');

    if (!$amount || !$cat_id || !$date) {
        $error = 'All required fields must be filled.';
    } else {
        $pdo->prepare("UPDATE expenses SET amount=?, category_id=?, note=?, expense_date=? WHERE id=? AND user_id=?")
            ->execute([$amount, $cat_id, $note, $date, $id, $uid]);
        $success = 'Expense updated!';
        $expense = array_merge($expense, ['amount'=>$amount,'category_id'=>$cat_id,'note'=>$note,'expense_date'=>$date]);
    }
}
?>

<div class="page-header">
    <div>
        <h2>Edit expense</h2>
        <p>Update the details of this transaction.</p>
    </div>
    <a href="list.php" class="btn btn-secondary">← Back to list</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?> <a href="list.php" style="font-weight:600;color:var(--teal-dim)">Back to list →</a></div>
<?php endif; ?>

<div class="form-card">
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Amount (BDT)</label>
                <input type="number" name="amount" step="0.01" value="<?= $expense['amount'] ?>" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="expense_date" value="<?= $expense['expense_date'] ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label>Category</label>
            <select name="category_id" required>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id']==$expense['category_id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Note <span style="color:var(--text-3);font-weight:400">(optional)</span></label>
            <textarea name="note"><?= htmlspecialchars($expense['note'] ?? '') ?></textarea>
        </div>
        <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-accent">Save changes</button>
            <a href="list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
