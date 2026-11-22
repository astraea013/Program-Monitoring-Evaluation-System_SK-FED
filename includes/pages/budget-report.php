<?php
/**
 * Budget monitoring report: approved budget against actual spending, with
 * variance and utilisation rate — monthly, annual and per program.
 * Barangay: its own budget, and it can submit the report to the federation.
 * Federation: any barangay, chosen with ?barangay=.
 * All figures come from stored records. There is no AI interpretation.
 */
$isBarangay = role() === ROLE_BARANGAY;
$barangays = $isBarangay ? [] : q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');
$b = $isBarangay ? my_barangay() : (get_int('barangay') ?: (int) ($barangays[0]['barangay_id'] ?? 0));
$bName = q_val('SELECT barangay_name FROM barangays WHERE barangay_id = ?', [$b]);
$allocations = q_all('SELECT * FROM budget_allocations ORDER BY date_entered DESC');
$allocId = get_int('allocation') ?: (int) ($allocations[0]['allocation_id'] ?? 0);
$period = q_val('SELECT period FROM budget_allocations WHERE allocation_id = ?', [$allocId]) ?: '—';
$type = in_array(get('type'), ['monthly', 'annual', 'program'], true) ? get('type') : 'annual';
$year = get_int('year') ?: (preg_match('/(\d{4})/', $period, $m) ? (int) $m[1] : (int) date('Y'));
$month = max(1, min(12, get_int('month') ?: (int) date('n')));

if ($isBarangay && is_post()) {
    csrf_check();
    $label = ['monthly' => 'Monthly', 'annual' => 'Annual', 'program' => 'Per-Program'][post('type')] ?? 'Annual';
    $per = post('type') === 'monthly' ? date('F Y', mktime(0, 0, 0, post_int('month'), 1, post_int('year'))) : $period;
    q('INSERT INTO reports (generated_by, barangay_id, report_type, period) VALUES (?, ?, ?, ?)', [uid(), $b, 'Budget Utilisation Report (' . $label . ')', mb_substr($per, 0, 50)]);
    audit('REPORT_SUBMITTED', 'Budget utilisation report (' . $label . ') ' . $per);
    notify_role(ROLE_FEDERATION, $bName . ' submitted a budget report', $label . ' budget utilisation · ' . $per, 'federation/budget-report.php?barangay=' . $b . '&allocation=' . $allocId);
    flash('success', $label . ' budget report for ' . $per . ' submitted to the SK Federation.');
    redirect('barangay/budget-report.php?' . http_build_query(['allocation' => $allocId, 'type' => post('type'), 'year' => post_int('year'), 'month' => post_int('month')]));
}

$approved = (string) q_val('SELECT COALESCE(SUM(approved_amount), 0) FROM budget_proposals WHERE barangay_id = ? AND allocation_id = ? AND prop_status = "approved"', [$b, $allocId]);
$programs = q_all('SELECT pb.*, p.program_code, p.title, (pb.allocated_amount - pb.utilised_amount) AS variance,
                          (SELECT COUNT(*) FROM budget_expenses e WHERE e.program_budget_id = pb.program_budget_id) AS entries
                     FROM program_budgets pb JOIN programs p ON p.program_id = pb.program_id JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id
                    WHERE bp.barangay_id = ? AND bp.allocation_id = ? AND bp.prop_status = "approved" ORDER BY p.program_code', [$b, $allocId]);
$actual = (string) q_val('SELECT COALESCE(SUM(pb.utilised_amount), 0) FROM program_budgets pb JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id WHERE bp.barangay_id = ? AND bp.allocation_id = ? AND bp.prop_status = "approved"', [$b, $allocId]);
$byMonth = [];
foreach (q_all('SELECT MONTH(e.expense_date) m, SUM(e.amount) amt, COUNT(*) n FROM budget_expenses e JOIN program_budgets pb ON pb.program_budget_id = e.program_budget_id
                  JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id WHERE bp.barangay_id = ? AND bp.allocation_id = ? AND bp.prop_status = "approved" AND YEAR(e.expense_date) = ?
                 GROUP BY MONTH(e.expense_date)', [$b, $allocId, $year]) as $r) {
    $byMonth[(int) $r['m']] = $r;
}
$monthExpenses = $type === 'monthly' ? q_all('SELECT e.*, p.title, p.program_code FROM budget_expenses e JOIN program_budgets pb ON pb.program_budget_id = e.program_budget_id JOIN programs p ON p.program_id = pb.program_id
                  JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id WHERE bp.barangay_id = ? AND bp.allocation_id = ? AND bp.prop_status = "approved" AND YEAR(e.expense_date) = ? AND MONTH(e.expense_date) = ? ORDER BY e.expense_date', [$b, $allocId, $year, $month]) : [];
$appF = (float) $approved;
$rows = [];
$cum = 0.0;
$lastMonth = $year < (int) date('Y') ? 12 : ($year > (int) date('Y') ? 0 : (int) date('n'));
for ($mo = 1; $mo <= max($lastMonth, $byMonth ? max(array_keys($byMonth)) : 0); $mo++) {
    $spent = (float) ($byMonth[$mo]['amt'] ?? 0);
    $cum += $spent;
    $rows[] = ['month' => date('F', mktime(0, 0, 0, $mo, 1)), 'spent' => $spent, 'cum' => $cum, 'remaining' => $appF - $cum, 'rate' => $appF ? $cum / $appF * 100 : 0, 'entries' => (int) ($byMonth[$mo]['n'] ?? 0)];
}
$spending = array_filter($rows, fn ($r) => $r['spent'] > 0);
$hi = $spending ? array_reduce($spending, fn ($c, $r) => !$c || $r['spent'] > $c['spent'] ? $r : $c) : null;
$lo = $spending ? array_reduce($spending, fn ($c, $r) => !$c || $r['spent'] < $c['spent'] ? $r : $c) : null;
$complete = count(array_filter($programs, fn ($p) => $p['completion_status'] === 'complete'));
$notes = array_filter($programs, fn ($p) => (float) $p['variance'] > 0 && $p['balance_note']);
$submissions = q_all('SELECT * FROM reports WHERE barangay_id = ? AND report_type LIKE "Budget Utilisation Report%" ORDER BY date_generated DESC LIMIT 5', [$b]);

if (get('export') === 'csv') {
    $head = ['Item', 'Approved / Allocated', 'Actual', 'Variance', 'Utilisation %'];
    $out = [['Barangay', $bName, '', '', ''], ['Period', $period, '', '', ''], ['Report', ucfirst($type), '', '', ''], ['', '', '', '', ''], ['TOTAL', $approved, $actual, number_format($appF - (float) $actual, 2, '.', ''), pct($actual, $approved, 1)]];
    if ($type === 'program') {
        foreach ($programs as $p) {
            $out[] = [$p['program_code'] . ' ' . $p['title'] . ' (' . $p['completion_status'] . ')', $p['allocated_amount'], $p['utilised_amount'], $p['variance'], pct($p['utilised_amount'], $p['allocated_amount'], 1)];
        }
    } elseif ($type === 'monthly') {
        foreach ($monthExpenses as $x) {
            $out[] = [$x['expense_date'] . ' ' . $x['particulars'] . ' (' . $x['program_code'] . ')', '', $x['amount'], '', ''];
        }
    } else {
        foreach ($rows as $r) {
            $out[] = [$r['month'] . ' (spent ' . number_format($r['spent'], 2, '.', '') . ')', $approved, number_format($r['cum'], 2, '.', ''), number_format($r['remaining'], 2, '.', ''), number_format($r['rate'], 1)];
        }
    }
    send_csv('budget-report-' . preg_replace('/\W+/', '-', $bName . '-' . $period . '-' . $type) . '.csv', $head, $out);
}

$title = 'Budget Report';
$nav = 'budget';
$crumb = 'Budget · Reports';
require APP_ROOT . '/includes/header.php';
$qs = fn (array $o) => '?' . http_build_query(array_merge(['barangay' => $isBarangay ? null : $b, 'allocation' => $allocId, 'type' => $type, 'year' => $year, 'month' => $month], $o));
?>
<div class="page-head">
  <div><h1 class="page-title">Budget Report</h1><div class="page-sub"><?= e($bName) ?> · <?= e($period) ?> · Approved budget against actual spending</div></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(url(($isBarangay ? 'barangay' : 'federation') . '/budget.php')) ?>">← Budget</a>
    <button class="btn btn-secondary" data-print>Export PDF</button>
    <a class="btn btn-secondary" href="<?= e($qs(['export' => 'csv'])) ?>">↓ Spreadsheet</a>
  </div>
</div>

<form class="card filters mb" method="get">
  <?php if (!$isBarangay): ?><select class="input" name="barangay" data-autosubmit><?php foreach ($barangays as $x): ?><option value="<?= (int) $x['barangay_id'] ?>"<?= (int) $x['barangay_id'] === $b ? ' selected' : '' ?>><?= e($x['barangay_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
  <span class="label-mono" style="margin:0">Period</span>
  <select class="input" name="allocation" data-autosubmit><?php foreach ($allocations as $a): ?><option value="<?= (int) $a['allocation_id'] ?>"<?= (int) $a['allocation_id'] === $allocId ? ' selected' : '' ?>><?= e($a['period']) ?></option><?php endforeach; ?></select>
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <select class="input" name="year" data-autosubmit><?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--): ?><option<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
  <?php if ($type === 'monthly'): ?><select class="input" name="month" data-autosubmit><?php for ($mo = 1; $mo <= 12; $mo++): ?><option value="<?= $mo ?>"<?= $mo === $month ? ' selected' : '' ?>><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></option><?php endfor; ?></select><?php endif; ?>
</form>

<div class="tabs">
  <?php foreach (['monthly' => 'Monthly', 'annual' => 'Annual', 'program' => 'Per Program'] as $k => $l): ?><a class="tab<?= $type === $k ? ' active' : '' ?>" href="<?= e($qs(['type' => $k])) ?>"><?= $l ?></a><?php endforeach; ?>
</div>

<?php $mSpent = (float) ($byMonth[$month]['amt'] ?? 0); ?>
<div class="grid grid-4 mb">
  <div class="card stat"><div class="stat-label">Approved Budget</div><div class="stat-value"><?= e(peso0($approved)) ?></div></div>
  <div class="card stat"><div class="stat-label"><?= $type === 'monthly' ? 'Spent in ' . date('F', mktime(0, 0, 0, $month, 1)) : 'Actual Spending' ?></div><div class="stat-value blue"><?= e(peso0($type === 'monthly' ? $mSpent : $actual)) ?></div></div>
  <div class="card stat"><div class="stat-label">Unutilised (Variance)</div><div class="stat-value" style="color:#C99400"><?= e(peso0($appF - (float) $actual)) ?></div><div class="stat-sub">approved − actual to date</div></div>
  <div class="card stat"><div class="stat-label">Utilisation Rate</div><div class="stat-value green"><?= pct($actual, $approved, 1) ?><small>%</small></div></div>
</div>

<div class="split">
  <div class="card table-wrap">
    <?php if ($type === 'annual'): ?>
      <div class="card-head"><div class="section-title">Monthly Breakdown — <?= $year ?></div><span class="mono small muted">Cumulative against the approved budget</span></div>
      <table class="table">
        <thead><tr><th>Month</th><th class="num">Spent</th><th class="num">Cumulative Actual</th><th class="num">Remaining</th><th style="width:150px">Utilisation</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td style="font-weight:500"><?= e($r['month']) ?></td><td class="num"><?= e(peso($r['spent'])) ?></td><td class="num"><?= e(peso($r['cum'])) ?></td><td class="num"><?= e(peso($r['remaining'])) ?></td>
            <td><?= bar($r['rate'], $r['rate'] >= 90 ? 'green' : 'blue') ?><div class="mono small muted"><?= number_format($r['rate'], 1) ?>%</div></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty">No months to report for <?= $year ?> yet.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td>TOTAL <div class="sub"><?= e($period) ?></div></td><td class="num"><?= e(peso($actual)) ?></td><td class="num"><?= e(peso($actual)) ?></td><td class="num"><?= e(peso($appF - (float) $actual)) ?></td><td><?= bar((float) pct($actual, $approved, 1), 'green') ?><div class="mono small"><?= pct($actual, $approved, 1) ?>%</div></td></tr></tfoot>
      </table>
    <?php elseif ($type === 'monthly'): ?>
      <div class="card-head"><div class="section-title">Expenses — <?= e(date('F', mktime(0, 0, 0, $month, 1)) . ' ' . $year) ?></div><span class="mono small muted"><?= count($monthExpenses) ?> entries</span></div>
      <table class="table">
        <thead><tr><th>Date</th><th>Program</th><th>Particulars</th><th class="num">Amount</th></tr></thead>
        <tbody>
        <?php foreach ($monthExpenses as $x): ?><tr><td class="sub"><?= e(fmt_date($x['expense_date'])) ?></td><td><?= e($x['title']) ?><div class="sub"><?= e($x['program_code']) ?></div></td><td><?= e($x['particulars']) ?></td><td class="num"><?= e(peso($x['amount'])) ?></td></tr><?php endforeach; ?>
        <?php if (!$monthExpenses): ?><tr><td colspan="4" class="empty">No expenses recorded this month.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="3">Spent this month · cumulative to date <?= e(peso(($rows[$month - 1]['cum'] ?? (float) $actual))) ?> of <?= e(peso($approved)) ?></td><td class="num"><?= e(peso($mSpent)) ?></td></tr></tfoot>
      </table>
    <?php else: ?>
      <div class="card-head"><div class="section-title">Per Program — <?= e($period) ?></div></div>
      <table class="table">
        <thead><tr><th>Program</th><th class="num">Allocated</th><th class="num">Actual</th><th class="num">Variance</th><th class="num">Rate</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($programs as $p): ?>
          <tr><td style="font-weight:500"><?= e($p['title']) ?><div class="sub"><?= e($p['program_code']) ?></div></td><td class="num"><?= e(peso($p['allocated_amount'])) ?></td><td class="num"><?= e(peso($p['utilised_amount'])) ?></td><td class="num"><?= e(peso($p['variance'])) ?></td><td class="num"><?= pct($p['utilised_amount'], $p['allocated_amount'], 1) ?>%</td>
            <td><?= pill($p['completion_status']) ?><?php if ($p['balance_note']): ?><div class="muted" style="font-size:10.5px;max-width:200px"><?= e($p['balance_note']) ?></div><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$programs): ?><tr><td colspan="6" class="empty">No approved program budgets for this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <aside class="card panel">
    <div class="panel-title">Report Settings</div><div class="panel-sub"><?= e($bName) ?></div>
    <div class="panel-section"><div class="label-mono">Report Type</div><div class="row" style="gap:6px"><?php foreach (['monthly' => 'Monthly', 'annual' => 'Annual', 'program' => 'Per Program'] as $k => $l): ?><?= pill($type === $k ? 'completed' : 'planned', $l) ?><?php endforeach; ?></div></div>
    <div class="panel-section"><div class="label-mono">Coverage</div>
      <div class="kv"><span>Period</span><span><?= e($period) ?></span></div>
      <div class="kv"><span>Programs covered</span><span><?= count($programs) ?></span></div>
      <div class="kv"><span>Expense entries</span><span><?= array_sum(array_column($programs, 'entries')) ?></span></div></div>
    <div class="panel-section"><div class="label-mono">Summary</div>
      <div class="kv"><span>Highest spending</span><span class="green"><?= $hi ? e(substr($hi['month'], 0, 3) . ' — ' . peso0($hi['spent'])) : '—' ?></span></div>
      <div class="kv"><span>Lowest spending</span><span style="color:#8A6A05"><?= $lo ? e(substr($lo['month'], 0, 3) . ' — ' . peso0($lo['spent'])) : '—' ?></span></div>
      <div class="kv"><span>Programs complete</span><span><?= $complete ?> of <?= count($programs) ?></span></div></div>
    <div class="panel-section"><div class="label-mono">Note on Unutilised Amount</div>
      <div class="note-box"><?= e(peso($appF - (float) $actual)) ?> remains unutilised.<?php foreach ($notes as $n): ?><br>• <?= e($n['title']) ?> (<?= e(peso($n['variance'])) ?>): <?= e($n['balance_note']) ?><?php endforeach; ?></div>
      <div class="hint">Compiled from the notes recorded on each program budget.</div></div>
    <div class="panel-section"><div class="label-mono">Submission</div>
      <?= $submissions ? pill('submitted', 'Last submitted ' . fmt_date($submissions[0]['date_generated'])) : pill('pending', 'Not yet submitted') ?></div>
    <?php if ($isBarangay): ?>
      <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>"><button class="btn btn-primary btn-block">Submit to SK Federation</button></form>
    <?php endif; ?>
    <button class="btn btn-secondary btn-block mt" data-print>Export as PDF</button>
  </aside>
</div>
<?php require APP_ROOT . '/includes/footer.php';
