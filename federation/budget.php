<?php
/**
 * Federation budget: enter the allocation for the period, review pending
 * barangay proposals with their attachments, approve (deducting the
 * approved amount from the allocation, in one transaction) or return with
 * remarks, and monitor utilisation per barangay. The federation holds no
 * inventory and records no barangay expenses.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_FEDERATION);

if (is_post()) {
    csrf_check();
    $action = post('action');
    $pdo = db();

    if ($action === 'save_allocation') {
        $id = post_int('allocation_id');
        $period = post('period');
        $total = money_input(post('total_amount'));
        if ($period === '' || $total === null) {
            flash('error', 'Enter the period (e.g. CY 2027) and the total allocation in pesos.');
        } elseif (q_val('SELECT 1 FROM budget_allocations WHERE period = ? AND allocation_id <> ?', [$period, $id])) {
            flash('error', 'An allocation for ' . $period . ' already exists.');
        } elseif ($id) {
            $pdo->beginTransaction();
            q('SELECT allocation_id FROM budget_allocations WHERE allocation_id = ? FOR UPDATE', [$id]);
            // remaining = new total − already approved. Refuse if the new total is below what was approved.
            $st = q('UPDATE budget_allocations SET period = ?, remaining_amount = CAST(? AS DECIMAL(12,2)) - (total_amount - remaining_amount), total_amount = CAST(? AS DECIMAL(12,2))
                      WHERE allocation_id = ? AND CAST(? AS DECIMAL(12,2)) >= total_amount - remaining_amount', [$period, $total, $total, $id, $total]);
            if ($st->rowCount() || q_val('SELECT total_amount = CAST(? AS DECIMAL(12,2)) FROM budget_allocations WHERE allocation_id = ?', [$total, $id])) {
                $pdo->commit();
                audit('ALLOCATION_UPDATED', $period . ' total set to ' . peso($total));
                flash('success', 'Allocation for ' . $period . ' updated.');
            } else {
                $pdo->rollBack();
                flash('error', 'The total cannot be lower than the amount already approved to barangays.');
            }
        } else {
            q('INSERT INTO budget_allocations (entered_by, period, total_amount, remaining_amount) VALUES (?, ?, ?, ?)', [uid(), $period, $total, $total]);
            audit('ALLOCATION_CREATED', $period . ' allocation of ' . peso($total));
            flash('success', 'Allocation for ' . $period . ' entered. Barangays can now submit proposals against it.');
        }
        redirect('federation/budget.php?tab=setup');
    }

    if ($action === 'approve' || $action === 'return') {
        $pid = post_int('proposal_id');
        $remarks = post('remarks');
        $pdo->beginTransaction();
        $p = q_one('SELECT bp.*, b.barangay_name FROM budget_proposals bp JOIN barangays b ON b.barangay_id = bp.barangay_id WHERE bp.proposal_id = ? FOR UPDATE', [$pid]);
        if (!$p || $p['prop_status'] !== 'pending') {
            $pdo->rollBack();
            flash('error', 'That proposal is no longer pending.');
            redirect('federation/budget.php');
        }
        if ($action === 'return') {
            if ($remarks === '') {
                $pdo->rollBack();
                flash('error', 'Remarks are required when a proposal is returned, so the barangay knows what to revise.');
                redirect('federation/budget.php?proposal=' . $pid);
            }
            q('UPDATE budget_proposals SET prop_status = "returned", remarks = ?, reviewed_by = ?, date_reviewed = NOW() WHERE proposal_id = ?', [$remarks, uid(), $pid]);
            $pdo->commit();
            audit('PROPOSAL_RETURNED', $p['proposal_code'] . ': ' . mb_substr($remarks, 0, 200));
            notify((int) $p['submitted_by'], 'Budget proposal returned: ' . $p['proposal_code'], $remarks, 'barangay/budget.php');
            flash('success', $p['proposal_code'] . ' returned to ' . $p['barangay_name'] . ' for revision.');
            redirect('federation/budget.php');
        }
        $amount = money_input(post('approved_amount'));
        if ($amount === null || $amount === '0.00' || !(int) q_val('SELECT CAST(? AS DECIMAL(12,2)) <= proposed_amount FROM budget_proposals WHERE proposal_id = ?', [$amount, $pid])) {
            $pdo->rollBack();
            flash('error', 'The approved amount must be more than zero and not more than the ' . peso($p['proposed_amount']) . ' proposed.');
            redirect('federation/budget.php?proposal=' . $pid);
        }
        q('SELECT allocation_id FROM budget_allocations WHERE allocation_id = ? FOR UPDATE', [$p['allocation_id']]);
        $st = q('UPDATE budget_allocations SET remaining_amount = remaining_amount - CAST(? AS DECIMAL(12,2)) WHERE allocation_id = ? AND remaining_amount >= CAST(? AS DECIMAL(12,2))', [$amount, $p['allocation_id'], $amount]);
        if (!$st->rowCount()) {
            $pdo->rollBack();
            flash('error', 'The remaining allocation is not enough to approve ' . peso($amount) . '.');
            redirect('federation/budget.php?proposal=' . $pid);
        }
        q('UPDATE budget_proposals SET prop_status = "approved", approved_amount = ?, remarks = ?, reviewed_by = ?, date_reviewed = NOW() WHERE proposal_id = ?', [$amount, $remarks ?: null, uid(), $pid]);
        $pdo->commit();
        audit('PROPOSAL_APPROVED', $p['proposal_code'] . ' approved at ' . peso($amount) . ' (deducted from allocation)');
        notify((int) $p['submitted_by'], 'Budget proposal approved: ' . $p['proposal_code'], 'Approved ' . peso($amount) . '.' . ($remarks ? ' Remarks: ' . $remarks : ''), 'barangay/budget.php');
        flash('success', $p['proposal_code'] . ' approved at ' . peso($amount) . '. The amount was deducted from the allocation.');
        redirect('federation/budget.php?tab=acted');
    }
}

$allocations = q_all('SELECT * FROM budget_allocations ORDER BY date_entered DESC');
$allocId = get_int('allocation') ?: (int) ($allocations[0]['allocation_id'] ?? 0);
$alloc = null;
foreach ($allocations as $a) {
    if ((int) $a['allocation_id'] === $allocId) {
        $alloc = $a;
    }
}
$tab = in_array(get('tab'), ['pending', 'acted', 'setup', 'utilisation'], true) ? get('tab') : 'pending';
$proposalSql = 'SELECT bp.*, b.barangay_name, u.username AS submitter, r.username AS reviewer, d.title AS doc_title, d.file_size,
                       (SELECT COUNT(*) FROM program_budgets pb WHERE pb.proposal_id = bp.proposal_id) AS line_count
                  FROM budget_proposals bp JOIN barangays b ON b.barangay_id = bp.barangay_id JOIN users u ON u.user_id = bp.submitted_by
                  LEFT JOIN users r ON r.user_id = bp.reviewed_by LEFT JOIN documents d ON d.document_id = bp.document_id
                 WHERE bp.allocation_id = ? AND bp.prop_status ';
$pending = q_all($proposalSql . '= "pending" ORDER BY bp.date_submitted', [$allocId]);
$acted = q_all($proposalSql . 'IN ("approved","returned") ORDER BY bp.date_reviewed DESC', [$allocId]);
$util = q_all('SELECT b.barangay_id, b.barangay_name,
                      COALESCE(SUM(bp.approved_amount), 0) approved,
                      COALESCE((SELECT SUM(pb.utilised_amount) FROM program_budgets pb JOIN budget_proposals x ON x.proposal_id = pb.proposal_id WHERE x.barangay_id = b.barangay_id AND x.allocation_id = ? AND x.prop_status = "approved"), 0) utilised
                 FROM barangays b JOIN budget_proposals bp ON bp.barangay_id = b.barangay_id AND bp.allocation_id = ? AND bp.prop_status = "approved"
                GROUP BY b.barangay_id ORDER BY b.barangay_name', [$allocId, $allocId]);
$reporting = (int) q_val('SELECT COUNT(DISTINCT barangay_id) FROM budget_proposals WHERE allocation_id = ? AND prop_status <> "draft"', [$allocId]);
$totalBrgy = (int) q_val('SELECT COUNT(*) FROM barangays');
$sel = null;
if ($sid = get_int('proposal')) {
    $sel = q_one(str_replace('WHERE bp.allocation_id = ? AND bp.prop_status ', 'WHERE bp.proposal_id = ? AND 1 ', $proposalSql) . '= 1', [$sid]);
} elseif ($tab === 'pending' && $pending) {
    $sel = $pending[0];
}
$selLines = $sel ? q_all('SELECT pb.*, p.program_code, p.title, p.centre FROM program_budgets pb JOIN programs p ON p.program_id = pb.program_id WHERE pb.proposal_id = ? ORDER BY p.program_code', [$sel['proposal_id']]) : [];

if (get('export') === 'csv') {
    send_csv('budget-utilisation-' . preg_replace('/\W+/', '-', $alloc['period'] ?? 'none') . '.csv', ['Barangay', 'Approved', 'Utilised', 'Balance', 'Utilisation %'],
        array_map(fn ($u) => [$u['barangay_name'], $u['approved'], $u['utilised'], number_format((float) $u['approved'] - (float) $u['utilised'], 2, '.', ''), pct($u['utilised'], $u['approved'], 1)], $util));
}

$title = 'Budget Allocation & Approval';
$nav = 'budget';
$crumb = 'Budget & Reports · Budget';
require __DIR__ . '/../includes/header.php';
$approvedTotal = $alloc ? (float) $alloc['total_amount'] - (float) $alloc['remaining_amount'] : 0;
$qs = fn (array $o) => '?' . http_build_query(array_merge(['allocation' => $allocId, 'tab' => $tab], $o));
?>
<div class="page-head">
  <div><h1 class="page-title">Budget Allocation &amp; Approval</h1><div class="page-sub">SK Federation · <?= e($alloc['period'] ?? 'No allocation entered') ?></div></div>
  <div class="page-actions">
    <?php if (count($allocations) > 1): ?><form method="get"><input type="hidden" name="tab" value="<?= e($tab) ?>"><select class="input" name="allocation" data-autosubmit style="width:auto"><?php foreach ($allocations as $a): ?><option value="<?= (int) $a['allocation_id'] ?>"<?= (int) $a['allocation_id'] === $allocId ? ' selected' : '' ?>><?= e($a['period']) ?></option><?php endforeach; ?></select></form><?php endif; ?>
    <?= $pending ? pill('pending', count($pending) . ' proposal' . (count($pending) > 1 ? 's' : '') . ' for review') : '' ?>
  </div>
</div>

<?php if (!$alloc): ?>
  <div class="alert alert-info">Enter the budget allocation for the period first. Barangays submit their proposals against it.</div>
<?php else: ?>
<div class="grid grid-4 mb">
  <div class="card stat"><div class="stat-label">Allocation for the Period</div><div class="stat-value"><?= e(peso0($alloc['total_amount'])) ?></div></div>
  <div class="card stat"><div class="stat-label">Approved to Barangays</div><div class="stat-value green"><?= e(peso0($approvedTotal)) ?></div></div>
  <div class="card stat"><div class="stat-label">Remaining Allocation</div><div class="stat-value blue"><?= e(peso0($alloc['remaining_amount'])) ?></div></div>
  <div class="card stat"><div class="stat-label">Barangays Reporting</div><div class="stat-value"><?= $reporting ?> <small>of <?= $totalBrgy ?></small></div></div>
</div>
<div class="mb" title="<?= pct($approvedTotal, $alloc['total_amount']) ?>% of allocation approved"><?= bar((float) pct($approvedTotal, $alloc['total_amount'], 1), 'green') ?><div class="mono small muted" style="margin-top:4px"><?= pct($approvedTotal, $alloc['total_amount'], 1) ?>% of the allocation approved</div></div>
<?php endif; ?>

<div class="tabs">
  <a class="tab<?= $tab === 'pending' ? ' active' : '' ?>" href="<?= e($qs(['tab' => 'pending', 'proposal' => null])) ?>">Pending Proposals (<?= count($pending) ?>)</a>
  <a class="tab<?= $tab === 'acted' ? ' active' : '' ?>" href="<?= e($qs(['tab' => 'acted', 'proposal' => null])) ?>">Acted Upon</a>
  <a class="tab<?= $tab === 'setup' ? ' active' : '' ?>" href="<?= e($qs(['tab' => 'setup', 'proposal' => null])) ?>">Allocation Setup</a>
  <a class="tab<?= $tab === 'utilisation' ? ' active' : '' ?>" href="<?= e($qs(['tab' => 'utilisation', 'proposal' => null])) ?>">Utilisation by Barangay</a>
</div>

<?php if ($tab === 'setup'): ?>
  <div class="grid settings-layout">
    <div class="card table-wrap">
      <table class="table"><thead><tr><th>Period</th><th class="num">Total</th><th class="num">Approved</th><th class="num">Remaining</th><th>Entered</th><th></th></tr></thead><tbody>
      <?php foreach ($allocations as $a): ?>
        <tr><td style="font-weight:600"><?= e($a['period']) ?></td><td class="num"><?= e(peso($a['total_amount'])) ?></td><td class="num"><?= e(peso((float) $a['total_amount'] - (float) $a['remaining_amount'])) ?></td><td class="num"><?= e(peso($a['remaining_amount'])) ?></td><td class="sub"><?= e(fmt_date($a['date_entered'])) ?></td>
          <td class="right"><button class="btn btn-secondary btn-sm" data-open="dlg-alloc" data-fill='<?= e(json_encode(['allocation_id' => $a['allocation_id'], 'period' => $a['period'], 'total_amount' => $a['total_amount']])) ?>'>Edit</button></td></tr>
      <?php endforeach; ?>
      <?php if (!$allocations): ?><tr><td colspan="6" class="empty">No allocations yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="card card-pad">
      <div class="section-title">Enter Allocation</div>
      <p class="muted small mb">The allocation is the federation-wide amount for the period. Approved proposals are deducted from it.</p>
      <button class="btn btn-primary btn-block" data-open="dlg-alloc" data-fill='{"allocation_id":"0","period":"CY <?= (int) date('Y') + 1 ?>","total_amount":""}'>+ New allocation period</button>
    </div>
  </div>
  <dialog class="modal" id="dlg-alloc">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_allocation"><input type="hidden" name="allocation_id">
      <div class="modal-head"><div class="modal-title">Budget Allocation</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
      <div class="field"><label>Period</label><input class="input" name="period" required maxlength="50" placeholder="CY 2027"></div>
      <div class="field"><label>Total allocation (₱)</label><input class="input mono" name="total_amount" required inputmode="decimal" placeholder="1,500,000.00"><div class="hint">When editing, the remaining amount is recomputed as the new total less what has already been approved.</div></div>
      <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Save allocation</button></div>
    </form>
  </dialog>

<?php elseif ($tab === 'utilisation'): ?>
  <div class="card table-wrap">
    <div class="card-head"><div class="section-title">Utilisation by Barangay</div><a class="btn btn-secondary btn-sm" href="<?= e($qs(['export' => 'csv'])) ?>">↓ Export</a></div>
    <table class="table">
      <thead><tr><th>Barangay</th><th class="num">Approved</th><th class="num">Utilised</th><th class="num">Balance</th><th style="width:180px">Progress</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($util as $u): $rate = (float) pct($u['utilised'], $u['approved'], 1); ?>
        <tr><td style="font-weight:500"><?= e($u['barangay_name']) ?></td><td class="num"><?= e(peso($u['approved'])) ?></td><td class="num"><?= e(peso($u['utilised'])) ?></td><td class="num"><?= e(peso((float) $u['approved'] - (float) $u['utilised'])) ?></td>
          <td><?= bar($rate, $rate >= 95 ? 'green' : ($rate < 40 ? 'amber' : 'blue')) ?><div class="mono small muted"><?= $rate ?>%</div></td>
          <td class="right"><a class="btn btn-secondary btn-sm" href="<?= e(url('federation/budget-report.php?barangay=' . $u['barangay_id'] . '&allocation=' . $allocId)) ?>">Report</a></td></tr>
      <?php endforeach; ?>
      <?php if (!$util): ?><tr><td colspan="6" class="empty">No approved proposals yet for this period.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

<?php else: $list = $tab === 'pending' ? $pending : $acted; ?>
  <div class="split">
    <div>
      <?php foreach ($list as $p): $st = $p['prop_status']; ?>
        <div class="row-card <?= ['pending' => 'amber', 'approved' => 'green', 'returned' => 'red'][$st] ?>"<?= $sel && (int) $sel['proposal_id'] === (int) $p['proposal_id'] ? ' style="background:var(--surface-accent)"' : '' ?>>
          <div>
            <div class="row" style="gap:8px"><span class="record-id"><?= e($p['proposal_code']) ?></span><?= pill($st) ?></div>
            <div class="row-card-title"><?= e($p['barangay_name']) ?> — <?= e($p['title']) ?></div>
            <div class="row-card-detail">Proposed <?= e(peso($p['proposed_amount'])) ?> · Covers <?= (int) $p['line_count'] ?> program<?= (int) $p['line_count'] === 1 ? '' : 's' ?> · Submitted by <?= e($p['submitter']) ?><?= $st === 'approved' ? ' · Approved ' . e(peso($p['approved_amount'])) : '' ?></div>
            <div class="row-card-detail"><?= $p['document_id'] ? 'Attachment: ' . e($p['doc_title']) : 'No attachment' ?><?= $p['remarks'] && $st !== 'pending' ? ' · Remarks: ' . e($p['remarks']) : '' ?></div>
          </div>
          <div class="row-card-side">
            <span class="row-card-time"><?= $st === 'pending' ? 'Submitted ' . e(fmt_date($p['date_submitted'])) : ucfirst($st) . ' ' . e(fmt_date($p['date_reviewed'])) ?></span>
            <a class="btn <?= $st === 'pending' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" href="<?= e($qs(['proposal' => $p['proposal_id']])) ?>"><?= $st === 'pending' ? 'Review' : 'View' ?></a>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$list): ?><div class="card empty"><?= $tab === 'pending' ? 'No proposals are waiting for review.' : 'No proposals have been acted on yet.' ?></div><?php endif; ?>
    </div>
    <aside class="card panel">
      <?php if (!$sel): ?><div class="empty">Select a proposal to review it.</div><?php else: ?>
        <div class="panel-title"><?= e($sel['proposal_code']) ?></div>
        <div class="panel-sub"><?= e($sel['barangay_name']) ?> · <?= e($sel['title']) ?></div>
        <div class="panel-section">
          <div class="kv"><span>Proposed</span><span><?= e(peso($sel['proposed_amount'])) ?></span></div>
          <?php if ($sel['prop_status'] === 'pending' && $alloc): ?>
            <div class="kv"><span>Remaining allocation</span><span><?= e(peso($alloc['remaining_amount'])) ?></span></div>
            <div class="kv"><span>If approved in full</span><span class="<?= (float) $alloc['remaining_amount'] < (float) $sel['proposed_amount'] ? 'red' : 'blue' ?>"><?= e(peso((float) $alloc['remaining_amount'] - (float) $sel['proposed_amount'])) ?></span></div>
          <?php else: ?>
            <div class="kv"><span>Approved</span><span><?= e($sel['approved_amount'] !== null ? peso($sel['approved_amount']) : '—') ?></span></div>
            <div class="kv plain"><span>Reviewed by</span><span><?= e($sel['reviewer'] ?: '—') ?></span></div>
          <?php endif; ?>
        </div>
        <div class="panel-section">
          <div class="label-mono">Programs Covered</div>
          <?php foreach ($selLines as $l): ?><div class="kv"><span><?= e($l['title']) ?> <span class="muted small">· <?= e($l['program_code']) ?></span></span><span><?= e(peso($l['allocated_amount'])) ?></span></div><?php endforeach; ?>
        </div>
        <div class="panel-section">
          <div class="label-mono">Attached Documents</div>
          <?php if ($sel['document_id']): ?><a class="note-box" style="display:flex;justify-content:space-between;text-decoration:none" href="<?= e(url('file.php?id=' . $sel['document_id'])) ?>" target="_blank" rel="noopener"><span><?= e($sel['doc_title']) ?></span><span class="mono small muted"><?= e(file_size_label((int) $sel['file_size'])) ?></span></a>
          <?php else: ?><p class="muted small">No supporting document was attached.</p><?php endif; ?>
        </div>
        <?php if ($sel['prop_status'] === 'pending'): ?>
          <form class="panel-section" method="post">
            <?= csrf_field() ?><input type="hidden" name="proposal_id" value="<?= (int) $sel['proposal_id'] ?>">
            <div class="field"><label class="label-mono">Approved amount (₱)</label><input class="input mono" name="approved_amount" value="<?= e($sel['proposed_amount']) ?>" inputmode="decimal"><div class="hint">May be reduced below the proposed amount.</div></div>
            <div class="field"><label class="label-mono" for="proposal-remarks">Remarks</label><textarea class="input" id="proposal-remarks" name="remarks" rows="4" placeholder="Required when a proposal is returned."></textarea></div>
            <button class="btn btn-primary btn-block" name="action" value="approve">Approve proposal</button>
            <button class="btn btn-danger btn-block mt" name="action" value="return">Return for revision</button>
          </form>
        <?php elseif ($sel['remarks']): ?>
          <div class="note-box mt"><div class="label-mono" style="margin-bottom:4px">Remarks</div><?= e($sel['remarks']) ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
