<?php
$pageTitle = 'Expenses';
require_once '../config/db.php';
require_once '../includes/header.php';

$uid = currentUserId();

$search_date = $_GET['date']   ?? '';
$search_cat  = (int)($_GET['cat'] ?? 0);
$min_amount  = (float)($_GET['min'] ?? 0);
$max_amount  = (float)($_GET['max'] ?? 0);
$period      = $_GET['period'] ?? '';
$sort        = $_GET['sort']   ?? 'latest';

$where  = ["e.user_id = ?"];
$params = [$uid];

if ($search_date) { $where[] = "e.expense_date = ?";  $params[] = $search_date; }
if ($search_cat)  { $where[] = "e.category_id = ?";   $params[] = $search_cat; }
if ($min_amount > 0) { $where[] = "e.amount >= ?";    $params[] = $min_amount; }
if ($max_amount > 0) { $where[] = "e.amount <= ?";    $params[] = $max_amount; }
if ($period === 'daily')   { $where[] = "e.expense_date = CURDATE()"; }
if ($period === 'weekly')  { $where[] = "YEARWEEK(e.expense_date,1) = YEARWEEK(CURDATE(),1)"; }
if ($period === 'monthly') { $where[] = "MONTH(e.expense_date)=MONTH(CURDATE()) AND YEAR(e.expense_date)=YEAR(CURDATE())"; }

$order = ($sort === 'highest') ? "e.amount DESC" : "e.expense_date DESC, e.created_at DESC";

$sql = "SELECT e.*, c.name AS category_name FROM expenses e JOIN categories c ON e.category_id = c.id WHERE " . implode(' AND ', $where) . " ORDER BY $order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$cats->execute([$uid]);
$categories = $cats->fetchAll();

$total_shown = array_sum(array_column($expenses, 'amount'));
$cat_icons = ['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬'];
?>

<div class="page-header">
    <div>
        <h2>Expenses</h2>
        <p><?= count($expenses) ?> records · ৳ <?= number_format($total_shown, 2) ?> total</p>
    </div>
    <a href="add.php" class="btn btn-accent">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add expense
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" class="filter-bar">
    <div class="filter-group">
        <label>Date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($search_date) ?>">
    </div>
    <div class="filter-group">
        <label>Category</label>
        <select name="cat">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $search_cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group" style="min-width:100px">
        <label>Min amount</label>
        <input type="number" name="min" value="<?= $min_amount ?: '' ?>" placeholder="৳ 0" step="0.01">
    </div>
    <div class="filter-group" style="min-width:100px">
        <label>Max amount</label>
        <input type="number" name="max" value="<?= $max_amount ?: '' ?>" placeholder="Any" step="0.01">
    </div>
    <div class="filter-group">
        <label>Period</label>
        <select name="period">
            <option value="">All time</option>
            <option value="daily"   <?= $period==='daily'  ?'selected':'' ?>>Today</option>
            <option value="weekly"  <?= $period==='weekly' ?'selected':'' ?>>This week</option>
            <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>This month</option>
        </select>
    </div>
    <div class="filter-group">
        <label>Sort by</label>
        <select name="sort">
            <option value="latest"  <?= $sort==='latest' ?'selected':'' ?>>Latest first</option>
            <option value="highest" <?= $sort==='highest'?'selected':'' ?>>Highest first</option>
        </select>
    </div>
    <div style="display:flex;gap:.5rem;align-self:flex-end">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="list.php" class="btn btn-secondary btn-sm">Clear</a>
    </div>
</form>

<div class="table-card">
    <?php if (!empty($expenses)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Note</th>
                <th style="text-align:right">Amount</th>
                <th style="text-align:center">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $e): ?>
        <tr>
            <td style="white-space:nowrap;color:var(--text-3);font-size:.8rem"><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
            <td>
                <span class="cat-chip">
                    <?= $cat_icons[$e['category_name']] ?? '💸' ?>&nbsp;<?= htmlspecialchars($e['category_name']) ?>
                </span>
            </td>
            <td style="color:var(--text-2)"><?= htmlspecialchars($e['note'] ?: '—') ?></td>
            <td style="text-align:right" class="amount-negative">৳ <?= number_format($e['amount'], 2) ?></td>
            <td style="text-align:center;white-space:nowrap">
                <a href="edit.php?id=<?= $e['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <a href="delete.php?id=<?= $e['id'] ?>"
                   onclick="return confirm('Delete this expense?')"
                   class="btn btn-danger btn-sm">Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <span class="ei">🧾</span>
        <p>No expenses found matching your filters.</p>
        <a href="add.php" class="btn btn-accent btn-sm">Add your first expense</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
