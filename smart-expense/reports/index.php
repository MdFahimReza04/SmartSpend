<?php
$pageTitle = 'Reports';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid = currentUserId();

$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE()) THEN amount ELSE 0 END) AS this_month,
        SUM(CASE WHEN MONTH(expense_date)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(expense_date)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) THEN amount ELSE 0 END) AS last_month
    FROM expenses WHERE user_id=?
");
$stmt->execute([$uid]);
$comparison = $stmt->fetch();

$cat_stmt = $pdo->prepare("
    SELECT c.name, SUM(e.amount) AS total, COUNT(*) AS cnt
    FROM expenses e JOIN categories c ON e.category_id=c.id
    WHERE e.user_id=? AND MONTH(e.expense_date)=MONTH(CURDATE()) AND YEAR(e.expense_date)=YEAR(CURDATE())
    GROUP BY c.id ORDER BY total DESC
");
$cat_stmt->execute([$uid]);
$cat_report = $cat_stmt->fetchAll();

$daily_stmt = $pdo->prepare("
    SELECT expense_date, SUM(amount) AS total
    FROM expenses WHERE user_id=? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY expense_date ORDER BY expense_date
");
$daily_stmt->execute([$uid]);
$daily_data = $daily_stmt->fetchAll();

$diff = $comparison['this_month'] - $comparison['last_month'];
$pct  = $comparison['last_month'] > 0 ? round(($diff / $comparison['last_month']) * 100, 1) : 0;

$cat_total = array_sum(array_column($cat_report, 'total'));
$cat_icons = ['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬'];
?>

<div class="page-header">
    <div>
        <h2>Reports & analytics</h2>
        <p>Spending insights for <?= date('F Y') ?>.</p>
    </div>
</div>

<!-- Month comparison -->
<div class="kpi-grid mb-3" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi-card">
        <div class="kpi-label"><span class="dot" style="background:var(--indigo)"></span>This month</div>
        <div class="kpi-value">৳ <?= number_format($comparison['this_month'], 0) ?></div>
        <div class="kpi-sub"><?= date('F Y') ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label"><span class="dot" style="background:var(--text-3)"></span>Last month</div>
        <div class="kpi-value">৳ <?= number_format($comparison['last_month'], 0) ?></div>
        <div class="kpi-sub"><?= date('F Y', strtotime('-1 month')) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label"><span class="dot" style="background:<?= $diff > 0 ? 'var(--rose)' : 'var(--teal)' ?>"></span>Month-over-month</div>
        <div class="kpi-value" style="color:<?= $diff > 0 ? 'var(--rose)' : 'var(--teal)' ?>">
            <?= $diff > 0 ? '+' : '' ?><?= $pct ?>%
        </div>
        <div class="kpi-sub"><?= $diff > 0 ? 'More than last month' : ($diff < 0 ? 'Less than last month' : 'Same as last month') ?></div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Category breakdown</h3>
            <span class="chart-meta"><?= date('F') ?></span>
        </div>
        <?php if (!empty($cat_report)): ?>
        <canvas id="catPie" style="max-height:260px"></canvas>
        <?php else: ?>
        <div class="empty-state"><span class="ei">📊</span><p>No data for this month.</p></div>
        <?php endif; ?>
    </div>
    <div class="chart-card">
        <div class="chart-header">
            <h3>Daily spending — last 30 days</h3>
        </div>
        <?php if (!empty($daily_data)): ?>
        <canvas id="dailyBar" style="max-height:260px"></canvas>
        <?php else: ?>
        <div class="empty-state"><span class="ei">📅</span><p>No data for this period.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Table -->
<div class="table-card">
    <div class="table-card-header">
        <h3>Category breakdown — <?= date('F Y') ?></h3>
    </div>
    <?php if (!empty($cat_report)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Category</th>
                <th style="text-align:right">Amount</th>
                <th style="text-align:right">Transactions</th>
                <th style="text-align:right">% of total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cat_report as $r):
            $share = $cat_total > 0 ? round(($r['total'] / $cat_total) * 100, 1) : 0;
        ?>
        <tr>
            <td>
                <span style="display:flex;align-items:center;gap:.6rem">
                    <span><?= $cat_icons[$r['name']] ?? '📂' ?></span>
                    <span style="font-weight:500"><?= htmlspecialchars($r['name']) ?></span>
                </span>
            </td>
            <td style="text-align:right" class="amount-negative">৳ <?= number_format($r['total'], 2) ?></td>
            <td style="text-align:right;color:var(--text-3)"><?= $r['cnt'] ?></td>
            <td style="text-align:right">
                <div style="display:flex;align-items:center;gap:.5rem;justify-content:flex-end">
                    <div style="width:60px;height:5px;background:var(--surface-3);border-radius:20px;overflow:hidden">
                        <div style="width:<?= $share ?>%;height:100%;background:var(--indigo);border-radius:20px"></div>
                    </div>
                    <span style="font-size:.8rem;color:var(--text-3);min-width:32px;text-align:right"><?= $share ?>%</span>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state"><span class="ei">📊</span><p>No expenses this month.</p></div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
<?php if (!empty($cat_report)): ?>
    safeRender(renderPieChart, 'catPie',
        <?= json_encode(array_column($cat_report,'name')) ?>,
        <?= json_encode(array_map('floatval', array_column($cat_report,'total'))) ?>,
        true
    );
<?php endif; ?>
<?php if (!empty($daily_data)): ?>
    safeRender(renderBarChart, 'dailyBar',
        <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['expense_date'])), $daily_data)) ?>,
        <?= json_encode(array_map('floatval', array_column($daily_data,'total'))) ?>,
        'Daily expense (৳)', '#5b7fff'
    );
<?php endif; ?>
});
</script>

<?php require_once '../includes/footer.php'; ?>
