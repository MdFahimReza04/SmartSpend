<?php
$pageTitle = 'Dashboard';
require_once 'config/db.php';
require_once 'includes/header.php';

$uid = currentUserId();

// ── Handle Add Income POST ─────────────────────────────────────────────────
$income_success = $income_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    $inc_amount = filter_input(INPUT_POST, 'income_amount', FILTER_VALIDATE_FLOAT);
    $inc_month  = $_POST['income_month'] ?? date('Y-m');
    $inc_note   = trim($_POST['income_note'] ?? '');
    $inc_date   = $inc_month . '-01';

    if (!$inc_amount || $inc_amount <= 0) {
        $income_error = 'Please enter a valid income amount.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM income WHERE user_id=? AND month=?");
        $chk->execute([$uid, $inc_date]);
        $existing = $chk->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE income SET amount=amount+?, note=? WHERE id=?")
                ->execute([$inc_amount, $inc_note, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO income (user_id, amount, month, note) VALUES (?,?,?,?)")
                ->execute([$uid, $inc_amount, $inc_date, $inc_note]);
        }
        $income_success = 'Income of ৳ ' . number_format($inc_amount, 0) . ' recorded for ' . date('F Y', strtotime($inc_date)) . '!';
    }
}

// ── Handle Reduce Income POST ──────────────────────────────────────────────
$reduce_success = $reduce_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reduce_income'])) {
    $red_amount = filter_input(INPUT_POST, 'reduce_amount', FILTER_VALIDATE_FLOAT);
    $red_month  = $_POST['reduce_month'] ?? date('Y-m');
    $red_note   = trim($_POST['reduce_note'] ?? '');
    $red_date   = $red_month . '-01';

    if (!$red_amount || $red_amount <= 0) {
        $reduce_error = 'Please enter a valid amount to reduce.';
    } else {
        $chk = $pdo->prepare("SELECT id, amount FROM income WHERE user_id=? AND month=?");
        $chk->execute([$uid, $red_date]);
        $existing = $chk->fetch();
        if (!$existing) {
            $reduce_error = 'No income recorded for that month to reduce.';
        } elseif ($red_amount > $existing['amount']) {
            $reduce_error = 'Reduction amount (৳ ' . number_format($red_amount,0) . ') exceeds recorded income (৳ ' . number_format($existing['amount'],0) . ').';
        } else {
            $new_amount = $existing['amount'] - $red_amount;
            if ($new_amount == 0) {
                $pdo->prepare("DELETE FROM income WHERE id=?")->execute([$existing['id']]);
            } else {
                $pdo->prepare("UPDATE income SET amount=?, note=? WHERE id=?")
                    ->execute([$new_amount, $red_note ?: $existing['note'] ?? '', $existing['id']]);
            }
            $reduce_success = 'Income reduced by ৳ ' . number_format($red_amount, 0) . ' for ' . date('F Y', strtotime($red_date)) . '.';
        }
    }
}

// ── KPI queries ────────────────────────────────────────────────────────────
$exp = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM expenses WHERE user_id=? AND MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE())");
$exp->execute([$uid]); $total_expense = (float)$exp->fetchColumn();

$inc = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM income WHERE user_id=? AND YEAR(month)=YEAR(CURDATE()) AND MONTH(month)=MONTH(CURDATE())");
$inc->execute([$uid]); $total_income = (float)$inc->fetchColumn();

$total_savings = $total_income - $total_expense;

// Overall budget left
$bud = $pdo->prepare("SELECT IFNULL(SUM(amount),0) FROM budgets WHERE user_id=? AND category_id IS NULL AND month=DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$bud->execute([$uid]); $total_budget = (float)$bud->fetchColumn();
$budget_left = $total_budget - $total_expense;

// ── 12-month trend data ────────────────────────────────────────────────────
$trend = $pdo->prepare("SELECT DATE_FORMAT(expense_date,'%b %Y') AS mon, SUM(amount) AS total FROM expenses WHERE user_id=? AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(expense_date,'%Y-%m') ORDER BY MIN(expense_date)");
$trend->execute([$uid]);
$trend_data = $trend->fetchAll();

// ── Category breakdown for filter chart ───────────────────────────────────
$cat_breakdown = $pdo->prepare("
    SELECT c.name AS cat_name,
           DATE_FORMAT(e.expense_date,'%Y-%m') AS ym,
           YEARWEEK(e.expense_date,1) AS yw,
           DATE_FORMAT(MIN(e.expense_date),'%d %b %Y') AS week_start,
           SUM(e.amount) AS total
    FROM expenses e JOIN categories c ON e.category_id=c.id
    WHERE e.user_id=? AND e.expense_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY c.id, ym, yw
    ORDER BY ym, yw
");
$cat_breakdown->execute([$uid]);
$cat_breakdown_data = $cat_breakdown->fetchAll();

// ── Recent expenses ────────────────────────────────────────────────────────
$recent = $pdo->prepare("SELECT e.*, c.name AS cat FROM expenses e JOIN categories c ON e.category_id=c.id WHERE e.user_id=? ORDER BY e.expense_date DESC, e.id DESC LIMIT 5");
$recent->execute([$uid]);
$recent_expenses = $recent->fetchAll();

// ── Mini budgets ───────────────────────────────────────────────────────────
$bstmt = $pdo->prepare("SELECT b.amount AS budget, IFNULL(SUM(e.amount),0) AS spent, CASE WHEN b.category_id IS NULL THEN 'Overall' ELSE c.name END AS label FROM budgets b LEFT JOIN expenses e ON e.user_id=b.user_id AND (b.category_id IS NULL OR e.category_id=b.category_id) AND MONTH(e.expense_date)=MONTH(CURDATE()) AND YEAR(e.expense_date)=YEAR(CURDATE()) LEFT JOIN categories c ON b.category_id=c.id WHERE b.user_id=? AND b.month=DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY b.id LIMIT 4");
$bstmt->execute([$uid]);
$mini_budgets = $bstmt->fetchAll();

$cat_icons = ['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬'];
?>

<!-- ── Income Modal (Add / Reduce) ──────────────────────────────────────── -->
<div id="income-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:2rem;width:100%;max-width:420px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.25)">
        <button onclick="closeIncomeModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;cursor:pointer;color:var(--text-3);font-size:1.3rem;line-height:1">✕</button>

        <!-- Tabs -->
        <div style="display:flex;gap:0;margin-bottom:1.5rem;border:1px solid var(--border);border-radius:8px;overflow:hidden">
            <button id="tab-add" onclick="switchTab('add')" style="flex:1;padding:.55rem;font-size:.85rem;font-weight:600;border:none;cursor:pointer;background:var(--indigo);color:#fff;transition:all .2s">
                + Add Income
            </button>
            <button id="tab-reduce" onclick="switchTab('reduce')" style="flex:1;padding:.55rem;font-size:.85rem;font-weight:600;border:none;cursor:pointer;background:var(--surface-3);color:var(--text-2);transition:all .2s">
                − Reduce Income
            </button>
        </div>

        <!-- Add Income Form -->
        <div id="panel-add">
            <?php if ($income_error): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem"><?= htmlspecialchars($income_error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="add_income" value="1">
                <div class="form-group">
                    <label>Month</label>
                    <input type="month" name="income_month" value="<?= date('Y-m') ?>" required>
                </div>
                <div class="form-group">
                    <label>Amount (BDT)</label>
                    <input type="number" name="income_amount" step="0.01" min="1" placeholder="e.g. 30000" required>
                </div>
                <div class="form-group">
                    <label>Note <span style="color:var(--text-3);font-weight:400">(optional)</span></label>
                    <input type="text" name="income_note" placeholder="e.g. Salary, Freelance…">
                </div>
                <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn-accent" style="flex:1">Save income</button>
                    <button type="button" onclick="closeIncomeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Reduce Income Form -->
        <div id="panel-reduce" style="display:none">
            <?php if ($reduce_error): ?>
            <div class="alert alert-danger" style="margin-bottom:1rem"><?= htmlspecialchars($reduce_error) ?></div>
            <?php endif; ?>
            <p style="font-size:.8rem;color:var(--text-3);margin-bottom:1rem">Use this to correct an over-recorded income or remove a portion of it.</p>
            <form method="POST">
                <input type="hidden" name="reduce_income" value="1">
                <div class="form-group">
                    <label>Month</label>
                    <input type="month" name="reduce_month" value="<?= date('Y-m') ?>" required>
                </div>
                <div class="form-group">
                    <label>Amount to Reduce (BDT)</label>
                    <input type="number" name="reduce_amount" step="0.01" min="1" placeholder="e.g. 5000" required>
                </div>
                <div class="form-group">
                    <label>Reason <span style="color:var(--text-3);font-weight:400">(optional)</span></label>
                    <input type="text" name="reduce_note" placeholder="e.g. Correction, Refund…">
                </div>
                <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn-secondary" style="flex:1;background:var(--rose);color:#fff;border-color:var(--rose)">Reduce income</button>
                    <button type="button" onclick="closeIncomeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- ── KPI Grid ────────────────────────────────────────────────────────── -->
<div class="kpi-grid">

    <div class="kpi-card income">
        <div class="kpi-label"><span class="dot" style="background:var(--teal)"></span>Total Savings</div>
        <div class="kpi-value" style="color:<?= $total_savings >= 0 ? 'var(--teal)' : 'var(--rose)' ?>">
            <?= $total_savings < 0 ? '−' : '' ?>৳ <?= number_format(abs($total_savings), 0) ?>
        </div>
        <div class="kpi-sub" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap">
            <span>Income: ৳ <?= number_format($total_income, 0) ?></span>
            <button onclick="openIncomeModal('add')" style="background:var(--surface-3);border:1px solid var(--border);border-radius:20px;padding:.15rem .6rem;font-size:.7rem;cursor:pointer;color:var(--text-2);font-weight:500;line-height:1.6">+ Add income</button>
            <button onclick="openIncomeModal('reduce')" style="background:var(--surface-3);border:1px solid var(--border);border-radius:20px;padding:.15rem .6rem;font-size:.7rem;cursor:pointer;color:var(--rose);font-weight:500;line-height:1.6">− Reduce</button>
        </div>
    </div>

    <div class="kpi-card expense">
        <div class="kpi-label"><span class="dot" style="background:var(--rose)"></span>Total Expenses</div>
        <div class="kpi-value">৳ <?= number_format($total_expense, 0) ?></div>
        <div class="kpi-sub"><?= count($recent_expenses) ?> recent transactions</div>
    </div>

    <div class="kpi-card balance">
        <div class="kpi-label"><span class="dot" style="background:var(--indigo)"></span>Budget Left</div>
        <?php if ($total_budget > 0): ?>
        <div class="kpi-value" style="color:<?= $budget_left >= 0 ? 'var(--indigo)' : 'var(--rose)' ?>">
            <?= $budget_left < 0 ? '−' : '' ?>৳ <?= number_format(abs($budget_left), 0) ?>
        </div>
        <div class="kpi-sub">of ৳ <?= number_format($total_budget,0) ?> · <?= $total_budget > 0 ? round(($total_expense/$total_budget)*100,1) : 0 ?>% used</div>
        <?php else: ?>
        <div class="kpi-value" style="font-size:1rem;color:var(--text-3)">No budget set</div>
        <div class="kpi-sub"><a href="/smart-expense/budget/set.php" style="color:var(--indigo)">Set a budget →</a></div>
        <?php endif; ?>
    </div>

    <div class="kpi-card forecast">
        <div class="kpi-label"><span class="dot" style="background:var(--accent)"></span>Next Month Forecast</div>
        <div class="kpi-value" id="predicted-value" style="font-size:1.3rem">
            <span style="font-size:.9rem;color:var(--text-3)">…</span>
        </div>
        <div class="kpi-sub" id="predicted-trend">Calculating…</div>
    </div>
</div>

<!-- ── Health Banner ──────────────────────────────────────────────────── -->
<div class="health-banner health-gray" id="health-banner">
    <div class="health-label">
        <div class="health-dot"></div>
        <div>
            <div class="health-class" id="health-class">Analyzing your spending…</div>
            <div class="health-advice" id="health-advice" style="margin-top:.2rem"></div>
        </div>
    </div>
    <span class="badge badge-default" id="health-badge" style="font-size:.72rem"></span>
</div>

<!-- ── Charts Grid ────────────────────────────────────────────────────── -->
<div class="charts-grid">

    <!-- Spending by Category – filterable -->
    <div class="chart-card">
        <div class="chart-header" style="flex-wrap:wrap;gap:.5rem;align-items:flex-start">
            <h3>Spending by category</h3>
            <div style="display:flex;gap:.4rem;align-items:center;margin-left:auto">
                <select id="cat-mode" style="font-size:.75rem;padding:.25rem .5rem;border:1px solid var(--border);border-radius:6px;background:var(--surface-3);color:var(--text-2)">
                    <option value="month">Month</option>
                    <option value="week">Week</option>
                </select>
                <select id="cat-period" style="font-size:.75rem;padding:.25rem .5rem;border:1px solid var(--border);border-radius:6px;background:var(--surface-3);color:var(--text-2)">
                </select>
            </div>
        </div>
        <canvas id="pieChart" height="220"></canvas>
        <div id="pie-empty" class="empty-state" style="display:none"><span class="ei">📊</span><p>No expenses for this period.</p></div>
    </div>

    <!-- Spending Trend – dynamic month range -->
    <div class="chart-card">
        <div class="chart-header">
            <h3>Spending trend</h3>
            <div style="display:flex;align-items:center;gap:.4rem">
                <span style="font-size:.75rem;color:var(--text-3)">Last</span>
                <select id="trend-n" style="font-size:.75rem;padding:.25rem .5rem;border:1px solid var(--border);border-radius:6px;background:var(--surface-3);color:var(--text-2)">
                    <option value="1">1 month</option>
                    <option value="2">2 months</option>
                    <option value="3" selected>3 months</option>
                    <option value="6">6 months</option>
                    <option value="9">9 months</option>
                    <option value="12">12 months</option>
                </select>
            </div>
        </div>
        <canvas id="lineChart" height="220"></canvas>
        <div id="trend-empty" class="empty-state" style="display:none"><span class="ei">📈</span><p>Not enough data yet.</p></div>
    </div>
</div>

<!-- Prediction Chart -->
<div id="predict-chart-wrap" style="display:none">
    <div class="chart-header">
        <h3>Expense forecast</h3>
        <span class="badge badge-warning">ML Prediction</span>
    </div>
    <canvas id="predictChart" height="140"></canvas>
</div>

<!-- Smart Insights -->
<div id="insights-grid" class="insights-grid" style="display:none"></div>

<!-- ── Bottom Grid ────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:0">

    <div class="table-card">
        <div class="table-card-header">
            <h3>Recent transactions</h3>
            <a href="/smart-expense/expenses/list.php" class="btn btn-ghost btn-sm">View all →</a>
        </div>
        <?php if (!empty($recent_expenses)):
            $icons=['Food'=>'🍜','Transport'=>'🚗','Bills'=>'💡','Shopping'=>'🛍️','Health'=>'💊','Entertainment'=>'🎬'];
            foreach ($recent_expenses as $r): ?>
        <div class="tx-item">
            <div class="tx-icon"><?= $icons[$r['cat']] ?? '💸' ?></div>
            <div class="tx-info">
                <div class="tx-name"><?= htmlspecialchars($r['note'] ?: $r['cat']) ?></div>
                <div class="tx-meta"><?= htmlspecialchars($r['cat']) ?> · <?= date('M j', strtotime($r['expense_date'])) ?></div>
            </div>
            <div class="tx-amount">−৳ <?= number_format($r['amount'], 0) ?></div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state"><span class="ei">💳</span><p>No transactions yet. <a href="/smart-expense/expenses/add.php">Add one →</a></p></div>
        <?php endif; ?>
    </div>

    <div>
        <div class="section-title">
            <h3>Budget status</h3>
            <a href="/smart-expense/budget/track.php" class="btn btn-ghost btn-sm">Full view →</a>
        </div>
        <?php if (!empty($mini_budgets)): ?>
        <div class="budget-list">
        <?php foreach ($mini_budgets as $b):
            $pct = $b['budget'] > 0 ? round(($b['spent']/$b['budget'])*100, 1) : 0;
            $cls = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warn' : 'good');
        ?>
        <div class="budget-card">
            <div class="budget-header">
                <div class="budget-name"><?= htmlspecialchars($b['label']) ?></div>
                <div class="budget-amounts"><strong>৳ <?= number_format($b['spent'],0) ?></strong> of ৳ <?= number_format($b['budget'],0) ?></div>
            </div>
            <div class="progress-track"><div class="progress-fill <?= $cls ?>" style="width:<?= min($pct,100) ?>%"></div></div>
            <div class="budget-footer">
                <span class="budget-pct <?= $cls ?>"><?= $pct ?>% used<?= $pct>=100?' — Exceeded!':($pct>=80?' — Approaching limit':'') ?></span>
                <span>৳ <?= number_format(max($b['budget']-$b['spent'],0),0) ?> left</span>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="table-card"><div class="empty-state"><span class="ei">🎯</span><p>No budgets set yet.</p><a href="/smart-expense/budget/set.php" class="btn btn-accent btn-sm">Set a budget</a></div></div>
        <?php endif; ?>
    </div>
</div>

<!-- ── JavaScript ─────────────────────────────────────────────────────── -->
<script>
const allTrendLabels = <?= json_encode(array_column($trend_data,'mon')) ?>;
const allTrendValues = <?= json_encode(array_column($trend_data,'total')) ?>;
const catData        = <?= json_encode($cat_breakdown_data) ?>;

// Modal
function switchTab(tab) {
    const isAdd = tab === 'add';
    document.getElementById('panel-add').style.display    = isAdd ? '' : 'none';
    document.getElementById('panel-reduce').style.display = isAdd ? 'none' : '';
    document.getElementById('tab-add').style.background    = isAdd ? 'var(--indigo)' : 'var(--surface-3)';
    document.getElementById('tab-add').style.color         = isAdd ? '#fff' : 'var(--text-2)';
    document.getElementById('tab-reduce').style.background = isAdd ? 'var(--surface-3)' : 'var(--rose)';
    document.getElementById('tab-reduce').style.color      = isAdd ? 'var(--text-2)' : '#fff';
}
function openIncomeModal(tab = 'add') { switchTab(tab); document.getElementById('income-modal').style.display='flex'; }
function closeIncomeModal() { document.getElementById('income-modal').style.display='none'; }
document.getElementById('income-modal').addEventListener('click', function(e){ if(e.target===this) closeIncomeModal(); });
<?php if ($income_success): ?>
window.addEventListener('load', () => { alert('<?= addslashes($income_success) ?>'); });
<?php endif; ?>
<?php if ($reduce_success): ?>
window.addEventListener('load', () => { alert('<?= addslashes($reduce_success) ?>'); });
<?php endif; ?>
<?php if ($reduce_error): ?>
window.addEventListener('load', () => { openIncomeModal('reduce'); });
<?php endif; ?>

// ── Trend chart ─────────────────────────────────────────────────────────
function renderTrend() {
    const n      = parseInt(document.getElementById('trend-n').value);
    const labels = allTrendLabels.slice(-n);
    const values = allTrendValues.slice(-n);
    const canvas = document.getElementById('lineChart');
    const empty  = document.getElementById('trend-empty');
    if (!labels.length) { canvas.style.display='none'; empty.style.display='block'; return; }
    canvas.style.display=''; empty.style.display='none';
    safeRender(renderLineChart,'lineChart',labels,[
        {label:'Monthly Expense',data:values.map(Number),color:'#5b7fff',fill:true}
    ]);
}

// ── Category chart ──────────────────────────────────────────────────────
function populatePeriodSelect() {
    const mode   = document.getElementById('cat-mode').value;
    const sel    = document.getElementById('cat-period');
    const seen   = new Set();
    const opts   = [];

    catData.forEach(r => {
        if (mode === 'month') {
            if (!seen.has(r.ym)) {
                seen.add(r.ym);
                // Format nicely: "2026-04" → "Apr 2026"
                const d = new Date(r.ym + '-01');
                const lbl = d.toLocaleDateString('en-US', {month:'short', year:'numeric'});
                opts.push({val: r.ym, lbl});
            }
        } else {
            const key = String(r.yw);
            if (!seen.has(key)) {
                seen.add(key);
                opts.push({val: key, lbl: 'w/c ' + r.week_start});
            }
        }
    });

    opts.reverse(); // most recent first
    sel.innerHTML = '';
    if (!opts.length) { sel.innerHTML='<option>No data</option>'; return; }
    opts.forEach((o,i) => {
        const el = document.createElement('option');
        el.value = o.val; el.textContent = o.lbl;
        if (i===0) el.selected=true;
        sel.appendChild(el);
    });
}

function renderCatChart() {
    const mode   = document.getElementById('cat-mode').value;
    const period = document.getElementById('cat-period').value;
    const canvas = document.getElementById('pieChart');
    const empty  = document.getElementById('pie-empty');

    const totals = {};
    catData.forEach(r => {
        const key = mode === 'month' ? r.ym : String(r.yw);
        if (key === period) totals[r.cat_name] = (totals[r.cat_name]||0) + parseFloat(r.total);
    });

    const labels = Object.keys(totals);
    const values = Object.values(totals);
    if (!labels.length) { canvas.style.display='none'; empty.style.display='block'; return; }
    canvas.style.display=''; empty.style.display='none';
    safeRender(renderPieChart,'pieChart',labels,values,true);
}

// ── ML prediction ───────────────────────────────────────────────────────
function loadPrediction() {
    fetch('/smart-expense/ml/predict.php',{credentials:'same-origin'})
        .then(r=>{ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
        .then(data=>{
            document.getElementById('predicted-value').textContent =
                '৳ '+parseFloat(data.regression.prediction).toLocaleString('en-BD',{minimumFractionDigits:0});
            document.getElementById('predicted-trend').textContent='Trend: '+data.regression.trend;
            const hb=document.getElementById('health-banner');
            const cm={green:'health-green',yellow:'health-yellow',red:'health-red',gray:'health-gray'};
            hb.className='health-banner '+(cm[data.classification.color]||'health-gray');
            document.getElementById('health-class').textContent=data.classification.class+' — '+data.classification.health;
            document.getElementById('health-advice').textContent=data.classification.advice||'';
            document.getElementById('health-badge').textContent=data.classification.health;
            const ig=document.getElementById('insights-grid');
            if(data.insights&&data.insights.length){
                ig.style.display='grid';
                data.insights.forEach(txt=>{
                    const e=txt.slice(0,2),m=txt.slice(2).trim();
                    ig.innerHTML+=`<div class="insight-card"><div class="insight-icon">${e}</div><div class="insight-text">${m}</div></div>`;
                });
            }
            if(data.monthly_labels&&data.monthly_labels.length>=2){
                document.getElementById('predict-chart-wrap').style.display='block';
                safeRender(renderPredictionChart,'predictChart',data.monthly_labels,data.monthly_totals.map(Number),data.regression.prediction);
            }
        })
        .catch(err=>{
            console.error('ML predict:',err);
            document.getElementById('predicted-value').textContent='—';
            document.getElementById('predicted-trend').textContent='Not enough data';
        });
}

// ── Init ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    populatePeriodSelect();
    renderCatChart();
    renderTrend();
    loadPrediction();

    document.getElementById('cat-mode').addEventListener('change', ()=>{ populatePeriodSelect(); renderCatChart(); });
    document.getElementById('cat-period').addEventListener('change', renderCatChart);
    document.getElementById('trend-n').addEventListener('change', renderTrend);
});
</script>

<?php require_once 'includes/footer.php'; ?>
