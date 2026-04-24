<?php
$pageTitle = 'Budget Tracker';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid   = currentUserId();
$month = date('Y-m-01');

$stmt = $pdo->prepare("
    SELECT b.amount AS budget,
           IFNULL(SUM(e.amount), 0) AS spent,
           CASE WHEN b.category_id IS NULL THEN 'Overall' ELSE c.name END AS label,
           b.category_id
    FROM budgets b
    LEFT JOIN expenses e
        ON e.user_id = b.user_id
        AND (b.category_id IS NULL OR e.category_id = b.category_id)
        AND MONTH(e.expense_date) = MONTH(b.month)
        AND YEAR(e.expense_date)  = YEAR(b.month)
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND b.month = ?
    GROUP BY b.id
    ORDER BY b.category_id IS NULL DESC, c.name
");
$stmt->execute([$uid, $month]);
$tracks = $stmt->fetchAll();

$cat_icons = ['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬','Overall'=>'🎯'];

$total_budget = array_sum(array_column($tracks, 'budget'));
$total_spent  = array_sum(array_column($tracks, 'spent'));
$total_pct    = $total_budget > 0 ? round(($total_spent / $total_budget) * 100, 1) : 0;
?>

<div class="page-header">
    <div>
        <h2>Budget tracker</h2>
        <p><?= date('F Y') ?> — Monitor your spending against goals.</p>
    </div>
    <a href="set.php" class="btn btn-accent">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Set budget
    </a>
</div>

<?php if (!empty($tracks)): ?>

<!-- Summary strip -->
<div class="kpi-grid mb-3" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card">
        <div class="kpi-label">Total budget</div>
        <div class="kpi-value">৳ <?= number_format($total_budget, 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total spent</div>
        <div class="kpi-value" style="color:var(--rose)">৳ <?= number_format($total_spent, 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Remaining</div>
        <div class="kpi-value" style="color:<?= ($total_budget - $total_spent) >= 0 ? 'var(--teal)' : 'var(--rose)' ?>">
            ৳ <?= number_format(abs($total_budget - $total_spent), 0) ?>
            <?= ($total_budget - $total_spent) < 0 ? '<span style="font-size:.8rem;font-weight:400"> over</span>' : '' ?>
        </div>
    </div>
</div>

<div class="budget-list">
<?php foreach ($tracks as $t):
    $pct = $t['budget'] > 0 ? round(($t['spent'] / $t['budget']) * 100, 1) : 0;
    $cls = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warn' : 'good');
    $remaining = max($t['budget'] - $t['spent'], 0);
    $over = $t['spent'] - $t['budget'];
?>
<div class="budget-card">
    <div class="budget-header">
        <div style="display:flex;align-items:center;gap:.65rem">
            <span style="font-size:20px"><?= $cat_icons[$t['label']] ?? '📂' ?></span>
            <div>
                <div class="budget-name"><?= htmlspecialchars($t['label']) ?></div>
                <?php if ($pct >= 100): ?>
                <div style="font-size:.72rem;color:var(--rose);margin-top:.1rem">⚠️ Exceeded by ৳ <?= number_format($over, 0) ?></div>
                <?php elseif ($pct >= 80): ?>
                <div style="font-size:.72rem;color:var(--accent-dim);margin-top:.1rem">⚡ <?= 100 - $pct ?>% remaining</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="budget-amounts">
            <strong>৳ <?= number_format($t['spent'], 0) ?></strong>
            / ৳ <?= number_format($t['budget'], 0) ?>
        </div>
    </div>
    <div class="progress-track">
        <div class="progress-fill <?= $cls ?>" style="width:<?= min($pct, 100) ?>%"></div>
    </div>
    <div class="budget-footer">
        <span class="budget-pct <?= $cls ?>"><?= $pct ?>% used</span>
        <span><?= $remaining > 0 ? '৳ ' . number_format($remaining, 0) . ' remaining' : 'Budget exhausted' ?></span>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php else: ?>
<div class="table-card">
    <div class="empty-state">
        <span class="ei">🎯</span>
        <p>No budgets set for this month.</p>
        <a href="set.php" class="btn btn-accent btn-sm">Set your first budget</a>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
