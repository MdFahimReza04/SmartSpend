<?php
$pageTitle = 'Categories';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid   = currentUserId();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        $error = 'Category name cannot be empty.';
    } else {
        $check = $pdo->prepare("SELECT id FROM categories WHERE user_id=? AND LOWER(name)=LOWER(?)");
        $check->execute([$uid, $name]);
        if ($check->fetch()) {
            $error = 'Category already exists.';
        } else {
            $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?,?)")->execute([$uid, $name]);
            $success = 'Category "' . htmlspecialchars($name) . '" added!';
        }
    }
}

$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id=c.id AND e.user_id=c.user_id) AS expense_count FROM categories c WHERE c.user_id = ? ORDER BY c.is_default DESC, c.name");
$stmt->execute([$uid]);
$categories = $stmt->fetchAll();

$cat_icons = ['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬'];
?>

<div class="page-header">
    <div>
        <h2>Categories</h2>
        <p>Manage your expense categories.</p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="form-card mb-3" style="max-width:420px">
    <h3 style="font-size:.9rem;font-weight:600;color:var(--text);margin-bottom:1rem">Add new category</h3>
    <form method="POST" class="form-inline">
        <div style="flex:1">
            <input type="text" name="name" placeholder="e.g. Groceries, Gym, Rent…" required>
        </div>
        <button type="submit" class="btn btn-accent">Add</button>
    </form>
</div>

<div class="table-card">
    <div class="table-card-header">
        <h3><?= count($categories) ?> categories</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Type</th>
                <th style="text-align:right">Expenses</th>
                <th style="text-align:center">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
        <tr>
            <td>
                <span style="display:flex;align-items:center;gap:.6rem">
                    <span style="font-size:18px"><?= $cat_icons[$c['name']] ?? '📂' ?></span>
                    <span style="font-weight:500"><?= htmlspecialchars($c['name']) ?></span>
                </span>
            </td>
            <td><?= $c['is_default'] ? '<span class="badge badge-info">Default</span>' : '<span class="badge badge-default">Custom</span>' ?></td>
            <td style="text-align:right;color:var(--text-3);font-family:\'DM Mono\',monospace;font-size:.82rem"><?= $c['expense_count'] ?> records</td>
            <td style="text-align:center">
                <?php if (!$c['is_default']): ?>
                <a href="delete.php?id=<?= $c['id'] ?>"
                   onclick="return confirm('Delete this category? This may affect existing expenses.')"
                   class="btn btn-danger btn-sm">Delete</a>
                <?php else: ?>
                <span style="color:var(--text-3);font-size:.8rem">Protected</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once '../includes/footer.php'; ?>
