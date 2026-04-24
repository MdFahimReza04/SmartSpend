<?php
$pageTitle = 'Admin Panel';
require_once '../config/db.php';
require_once '../includes/header.php';
requireAdmin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_id = filter_input(INPUT_POST, 'delete_user', FILTER_VALIDATE_INT);
    if ($del_id && $del_id !== currentUserId()) {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$del_id]);
        $message = 'User deleted successfully.';
    }
}

$users = $pdo->query("
    SELECT u.*, COUNT(e.id) AS expense_count,
           IFNULL(SUM(e.amount),0) AS total_spent
    FROM users u
    LEFT JOIN expenses e ON e.user_id=u.id
    GROUP BY u.id ORDER BY u.created_at DESC
")->fetchAll();

$totals = $pdo->query("
    SELECT COUNT(*) AS total_users,
           (SELECT COUNT(*) FROM expenses) AS total_expenses,
           (SELECT IFNULL(SUM(amount),0) FROM expenses) AS total_spent,
           (SELECT COUNT(*) FROM categories WHERE is_default=0) AS custom_cats
    FROM users
")->fetch();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(expense_date,'%b %Y') AS mon, SUM(amount) AS total
    FROM expenses
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(expense_date,'%Y-%m')
    ORDER BY MIN(expense_date)
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Admin panel</h2>
        <p>Platform overview and user management.</p>
    </div>
    <span class="badge badge-admin">Admin</span>
</div>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Platform Stats -->
<div class="kpi-grid mb-3">
    <div class="kpi-card income">
        <div class="kpi-label"><span class="dot" style="background:var(--teal)"></span>Total users</div>
        <div class="kpi-value"><?= $totals['total_users'] ?></div>
        <div class="kpi-sub">Registered accounts</div>
    </div>
    <div class="kpi-card expense">
        <div class="kpi-label"><span class="dot" style="background:var(--rose)"></span>Total expenses</div>
        <div class="kpi-value"><?= number_format($totals['total_expenses']) ?></div>
        <div class="kpi-sub">All-time records</div>
    </div>
    <div class="kpi-card balance">
        <div class="kpi-label"><span class="dot" style="background:var(--indigo)"></span>Platform spending</div>
        <div class="kpi-value" style="font-size:1.3rem">৳ <?= number_format($totals['total_spent'], 0) ?></div>
        <div class="kpi-sub">All users combined</div>
    </div>
    <div class="kpi-card forecast">
        <div class="kpi-label"><span class="dot" style="background:var(--accent)"></span>Custom categories</div>
        <div class="kpi-value"><?= $totals['custom_cats'] ?></div>
        <div class="kpi-sub">User-created</div>
    </div>
</div>

<!-- Platform Trend Chart -->
<?php if (!empty($monthly)): ?>
<div class="chart-card mb-3">
    <div class="chart-header">
        <h3>Platform spending — last 6 months</h3>
    </div>
    <canvas id="adminTrend" height="140"></canvas>
</div>
<?php endif; ?>

<!-- Users Table -->
<div class="table-card">
    <div class="table-card-header">
        <h3>All users (<?= count($users) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th style="text-align:right">Expenses</th>
                <th style="text-align:right">Total spent</th>
                <th>Joined</th>
                <th style="text-align:center">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u):
            $initials = strtoupper(substr($u['name'], 0, 1));
            if (strpos($u['name'], ' ') !== false) {
                $parts = explode(' ', $u['name']);
                $initials = strtoupper($parts[0][0] . end($parts)[0]);
            }
        ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div style="width:34px;height:34px;border-radius:50%;background:var(--surface-3);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;color:var(--text-2);border:1px solid var(--border);flex-shrink:0">
                        <?= $initials ?>
                    </div>
                    <div>
                        <div style="font-weight:500;font-size:.87rem"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size:.75rem;color:var(--text-3)"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                </div>
            </td>
            <td>
                <?= $u['role'] === 'admin'
                    ? '<span class="badge badge-admin">admin</span>'
                    : '<span class="badge badge-default">user</span>' ?>
            </td>
            <td style="text-align:right;color:var(--text-3);font-family:'DM Mono',monospace;font-size:.82rem"><?= $u['expense_count'] ?></td>
            <td style="text-align:right" class="amount-neutral">৳ <?= number_format($u['total_spent'], 0) ?></td>
            <td style="color:var(--text-3);font-size:.8rem"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td style="text-align:center">
                <?php if ($u['id'] !== currentUserId() && $u['role'] !== 'admin'): ?>
                <form method="POST" style="display:inline">
                    <button name="delete_user" value="<?= $u['id'] ?>"
                        onclick="return confirm('Delete user and all their data? This cannot be undone.')"
                        class="btn btn-danger btn-sm">Delete</button>
                </form>
                <?php else: ?>
                <span style="color:var(--text-3);font-size:.8rem">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($monthly)): ?>
<script>
safeRender(renderBarChart, 'adminTrend',
    <?= json_encode(array_column($monthly, 'mon')) ?>,
    <?= json_encode(array_column($monthly, 'total')) ?>,
    'Platform spending (৳)', '#5b7fff');
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
