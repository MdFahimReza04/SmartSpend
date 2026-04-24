<?php
require_once '../config/db.php';
require_once '../includes/session.php';
requireLogin();

$uid = currentUserId();

function linearRegression(array $data): array {
    $n = count($data);
    if ($n < 2) return ['prediction' => 0, 'trend' => 'not enough data'];
    $xs = range(1, $n);
    $ys = array_values($data);
    $sum_x = array_sum($xs); $sum_y = array_sum($ys);
    $sum_xy = 0; $sum_xx = 0;
    for ($i = 0; $i < $n; $i++) { $sum_xy += $xs[$i] * $ys[$i]; $sum_xx += $xs[$i] * $xs[$i]; }
    $denom = ($n * $sum_xx - $sum_x ** 2);
    if ($denom == 0) return ['prediction' => $ys[0] ?? 0, 'trend' => 'stable', 'slope' => 0, 'months' => $ys];
    $m = ($n * $sum_xy - $sum_x * $sum_y) / $denom;
    $b = ($sum_y - $m * $sum_x) / $n;
    $prediction = max(0, round($m * ($n + 1) + $b, 2));
    $trend = $m > 5 ? 'increasing' : ($m < -5 ? 'decreasing' : 'stable');
    return ['prediction' => $prediction, 'trend' => $trend, 'slope' => round($m, 2), 'months' => $ys];
}

function classifySpender(float $expense, float $income, float $budget, float $predicted): array {
    if ($income <= 0) return ['class' => 'Unknown', 'health' => 'No income data', 'color' => 'gray', 'advice' => 'Add your income to get financial health insights.'];
    $ratio = $expense / $income;
    if ($ratio < 0.5) {
        if ($budget > 0 && $expense <= $budget)
            return ['class' => 'Careful spender', 'health' => 'Excellent', 'color' => 'green', 'advice' => 'Great job! You are well within budget. Consider investing your surplus.'];
        return ['class' => 'Careful spender', 'health' => 'Excellent', 'color' => 'green', 'advice' => 'Excellent spending habits. You are saving over 50% of your income.'];
    }
    if ($ratio < 0.8) {
        if ($budget > 0 && $expense > $budget)
            return ['class' => 'Moderate spender', 'health' => 'Average', 'color' => 'yellow', 'advice' => 'You have slightly exceeded your budget. Review non-essential spending.'];
        return ['class' => 'Moderate spender', 'health' => 'Average', 'color' => 'yellow', 'advice' => 'Spending is moderate. Trimming discretionary costs could boost savings.'];
    }
    if ($ratio >= 1.0)
        return ['class' => 'Overspender', 'health' => 'Critical', 'color' => 'red', 'advice' => 'You are spending more than your income. Immediate review of expenses needed!'];
    return ['class' => 'High spender', 'health' => 'Risky', 'color' => 'red', 'advice' => 'Expense ratio is very high. Cut non-essential spending to avoid deficit.'];
}

$stmt = $pdo->prepare("SELECT DATE_FORMAT(expense_date,'%Y-%m') AS mon, SUM(amount) AS total FROM expenses WHERE user_id=? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY mon ORDER BY mon ASC");
$stmt->execute([$uid]);
$monthly = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$regression = linearRegression(array_values($monthly));

$income_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=? AND YEAR(month)=YEAR(CURDATE()) AND MONTH(month)=MONTH(CURDATE())");
$income_stmt->execute([$uid]);
$income = (float)$income_stmt->fetchColumn();

$exp_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())");
$exp_stmt->execute([$uid]);
$this_month = (float)$exp_stmt->fetchColumn();

$bud_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM budgets WHERE user_id=? AND category_id IS NULL AND month=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$bud_stmt->execute([$uid]);
$budget = (float)$bud_stmt->fetchColumn();

$classification = classifySpender($this_month, $income, $budget, $regression['prediction']);

$insights = [];
if (count($monthly) >= 2) {
    $vals = array_values($monthly);
    $last = (float)end($vals);
    $prev = (float)prev($vals);
    if ($prev > 0) {
        $change = round((($last - $prev) / $prev) * 100, 1);
        $dir = $change > 0 ? 'higher' : 'lower';
        $insights[] = ($change > 0 ? '📈' : '📉') . " Spending is " . abs($change) . "% " . $dir . " than last month.";
    }
}
if ($budget > 0) {
    $pct = round(($this_month / $budget) * 100, 1);
    if ($pct >= 100) $insights[] = "🚨 Monthly budget exceeded by ৳ " . number_format($this_month - $budget, 0) . ".";
    elseif ($pct >= 80) $insights[] = "⚡ " . $pct . "% of monthly budget used. Plan spending carefully.";
}
$cat_stmt = $pdo->prepare("SELECT c.name, SUM(e.amount) AS total FROM expenses e JOIN categories c ON e.category_id=c.id WHERE e.user_id=? AND MONTH(e.expense_date)=MONTH(CURDATE()) AND YEAR(e.expense_date)=YEAR(CURDATE()) GROUP BY c.id ORDER BY total DESC LIMIT 1");
$cat_stmt->execute([$uid]);
$top_cat = $cat_stmt->fetch();
if ($top_cat) $insights[] = "🏆 Highest category: " . $top_cat['name'] . " (৳ " . number_format($top_cat['total'], 0) . ")";

header('Content-Type: application/json');
echo json_encode([
    'regression'     => $regression,
    'classification' => $classification,
    'insights'       => $insights,
    'this_month'     => $this_month,
    'income'         => $income,
    'budget'         => $budget,
    'monthly_labels' => array_keys($monthly),
    'monthly_totals' => array_values($monthly),
]);
