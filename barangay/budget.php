<?php
/**
 * Barangay budget: proposal → federation approval → expenses.
 * The barangay prepares a proposal with one line per program, attaches
 * the supporting document (stored in the repository and linked by
 * budget_proposals.document_id) and submits it. A returned proposal is
 * revised and resubmitted. After approval the barangay records expenses;
 * utilised_amount is recomputed from them. Each program budget carries a
 * completion status and a note explaining the remaining amount.
 * Money arithmetic is done in SQL on DECIMAL columns, never in PHP floats.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_BARANGAY);
$b = my_barangay();

function own_proposal(int $id, int $b, bool $lock = false): ?array
{
    return q_one('SELECT * FROM budget_proposals WHERE proposal_id = ? AND barangay_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id, $b]);
}
function own_program_budget(int $id, int $b): ?array
{
    return q_one('SELECT pb.*, bp.prop_status, bp.approved_amount, bp.proposal_id FROM program_budgets pb JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id
                   WHERE pb.program_budget_id = ? AND bp.barangay_id = ?', [$id, $b]);
}

if (is_post()) {
    csrf_check();
    $action = post('action');
    $pdo = db();

    if ($action === 'save_proposal') {
        $id = post_int('proposal_id');
        $titleIn = post('title');
        $alloc = post_int('allocation_id');
        $lines = [];
        foreach ((array) ($_POST['line'] ?? []) as $progId => $amt) {
            if (trim((string) $amt) === '') {
                continue;
            }
            $m = money_input($amt);
            if ($m === null || $m === '0.00') {
                flash('error', 'Each program amount must be a peso amount greater than zero, e.g. 15000 or 15000.50.');
                redirect('barangay/budget.php?edit=' . ($id ?: 'new'));
            }
            $lines[(int) $progId] = $m;
        }
        $ownPrograms = array_map('intval', array_column(q_all('SELECT program_id FROM programs WHERE barangay_id = ?', [$b]), 'program_id'));
        if ($titleIn === '' || !q_val('SELECT 1 FROM budget_allocations WHERE allocation_id = ?', [$alloc]) || !$lines || array_diff(array_keys($lines), $ownPrograms)) {
            flash('error', 'Enter a title, choose the allocation period, and give an amount for at least one of your programs.');
            redirect('barangay/budget.php?edit=' . ($id ?: 'new'));
        }
        $doc = null;
        if (!empty($_FILES['attachment']['name'])) {
            try {
                $doc = store_upload($_FILES['attachment'], 'proposals', DOC_TYPES);
            } catch (RuntimeException $ex) {
                flash('error', 'Attachment: ' . $ex->getMessage());
                redirect('barangay/budget.php?edit=' . ($id ?: 'new'));
            }
        }
        $pdo->beginTransaction();
        if ($id) {
            $p = own_proposal($id, $b, true);
            if (!$p || !in_array($p['prop_status'], ['draft', 'returned'], true)) {
                $pdo->rollBack();
                flash('error', 'Only draft or returned proposals can be edited.');
                redirect('barangay/budget.php');
            }
            q('UPDATE budget_proposals SET title = ?, allocation_id = ? WHERE proposal_id = ?', [$titleIn, $alloc, $id]);
            q('DELETE FROM program_budgets WHERE proposal_id = ?', [$id]);
        } else {
            q('INSERT INTO budget_proposals (allocation_id, barangay_id, submitted_by, proposal_code, title, proposed_amount, prop_status) VALUES (?, ?, ?, ?, ?, ?, "draft")',
                [$alloc, $b, uid(), 'TMP-' . bin2hex(random_bytes(6)), $titleIn, reset($lines)]);
            $id = (int) $pdo->lastInsertId();
            q('UPDATE budget_proposals SET proposal_code = ? WHERE proposal_id = ?', [sprintf('BPR-%s-%03d', date('Y'), $id), $id]);
        }
        foreach ($lines as $progId => $amt) {
            q('INSERT INTO program_budgets (proposal_id, program_id, allocated_amount, completion_status) VALUES (?, ?, ?, "ongoing")', [$id, $progId, $amt]);
        }
        q('UPDATE budget_proposals SET proposed_amount = (SELECT SUM(allocated_amount) FROM program_budgets WHERE proposal_id = ?) WHERE proposal_id = ?', [$id, $id]);
        if ($doc) {
            q('INSERT INTO documents (barangay_id, uploaded_by, title, category, file_path, file_size, is_public) VALUES (?, ?, ?, "proposal", ?, ?, 0)',
                [$b, uid(), 'Proposal attachment - ' . $titleIn, $doc['path'], $doc['size']]);
            q('UPDATE budget_proposals SET document_id = ? WHERE proposal_id = ?', [(int) $pdo->lastInsertId(), $id]);
        }
        $submit = post('submit') === '1';
        if ($submit) {
            q('UPDATE budget_proposals SET prop_status = "pending", date_submitted = NOW() WHERE proposal_id = ?', [$id]);
        }
        $pdo->commit();
        $code = q_val('SELECT proposal_code FROM budget_proposals WHERE proposal_id = ?', [$id]);
        audit($submit ? 'PROPOSAL_SUBMITTED' : 'PROPOSAL_SAVED', $code . ' ' . $titleIn);
        if ($submit) {
            notify_role(ROLE_FEDERATION, 'Budget proposal for review: ' . $code, current_user()['barangay_name'] . ' · ' . $titleIn, 'federation/budget.php');
        }
        flash('success', $code . ($submit ? ' submitted to the SK Federation.' : ' saved as a draft.'));
        redirect('barangay/budget.php');
    }

    if ($action === 'submit_proposal') {
        $pdo->beginTransaction();
        $p = own_proposal(post_int('proposal_id'), $b, true);
        if (!$p || !in_array($p['prop_status'], ['draft', 'returned'], true)) {
            $pdo->rollBack();
            flash('error', 'Only draft or returned proposals can be submitted.');
            redirect('barangay/budget.php');
        }
        q('UPDATE budget_proposals SET prop_status = "pending", date_submitted = NOW() WHERE proposal_id = ?', [$p['proposal_id']]);
        $pdo->commit();
        audit('PROPOSAL_SUBMITTED', $p['proposal_code']);
        notify_role(ROLE_FEDERATION, 'Budget proposal for review: ' . $p['proposal_code'], current_user()['barangay_name'] . ' · ' . $p['title'], 'federation/budget.php');
        flash('success', $p['proposal_code'] . ' submitted to the SK Federation.');
        redirect('barangay/budget.php');
    }

    if ($action === 'delete_draft') {
        $p = own_proposal(post_int('proposal_id'), $b);
        if ($p && $p['prop_status'] === 'draft') {
            q('DELETE FROM budget_proposals WHERE proposal_id = ?', [$p['proposal_id']]);
            audit('PROPOSAL_DELETED', $p['proposal_code']);
            flash('success', 'Draft deleted.');
        }
        redirect('barangay/budget.php');
    }

    if ($action === 'update_program_budget') {
        $pb = own_program_budget(post_int('program_budget_id'), $b) ?? require_barangay_access(null);
        $status = post('completion_status');
        $alloc = money_input(post('allocated_amount'));
        if (!in_array($status, ['complete', 'incomplete', 'ongoing'], true) || $alloc === null) {
            flash('error', 'Choose a completion status and enter a valid allocated amount.');
            redirect('barangay/budget.php?tab=programs&pb=' . $pb['program_budget_id']);
        }
        $pdo->beginTransaction();
        q('UPDATE program_budgets SET completion_status = ?, balance_note = ?, allocated_amount = ? WHERE program_budget_id = ?', [$status, post('balance_note') ?: null, $alloc, $pb['program_budget_id']]);
        // Allocation per program may not drop below what is spent, nor push the lines past the approved amount.
        $bad = q_one('SELECT (SELECT allocated_amount < utilised_amount FROM program_budgets WHERE program_budget_id = ?) AS below_spent,
                             (SELECT SUM(allocated_amount) FROM program_budgets WHERE proposal_id = ?) > COALESCE(?, 9999999999) AS over_cap', [$pb['program_budget_id'], $pb['proposal_id'], $pb['approved_amount']]);
        if ((int) $bad['below_spent'] || ($pb['prop_status'] === 'approved' && (int) $bad['over_cap'])) {
            $pdo->rollBack();
            flash('error', (int) $bad['below_spent'] ? 'The allocation cannot be lower than the amount already utilised.' : 'The program allocations would exceed the ' . peso($pb['approved_amount']) . ' approved by the federation.');
        } else {
            $pdo->commit();
            audit('PROGRAM_BUDGET_UPDATED', 'Program budget #' . $pb['program_budget_id'] . ' marked ' . $status);
            flash('success', 'Program budget updated.');
        }
        redirect('barangay/budget.php?tab=programs&pb=' . $pb['program_budget_id']);
    }

    if ($action === 'add_expense') {
        $pb = own_program_budget(post_int('program_budget_id'), $b) ?? require_barangay_access(null);
        $amt = money_input(post('amount'));
        $date = post('expense_date');
        $act = post_int('activity_id') ?: null;
        if ($pb['prop_status'] !== 'approved') {
            flash('error', 'Expenses can only be recorded against an approved budget.');
        } elseif ($amt === null || $amt === '0.00' || post('particulars') === '' || !strtotime($date)) {
            flash('error', 'Enter the particulars, a peso amount greater than zero and the expense date.');
        } elseif ($act && !q_val('SELECT 1 FROM activities WHERE activity_id = ? AND program_id = ?', [$act, $pb['program_id']])) {
            flash('error', 'Choose an activity of this program.');
        } else {
            $pdo->beginTransaction();
            q('SELECT program_budget_id FROM program_budgets WHERE program_budget_id = ? FOR UPDATE', [$pb['program_budget_id']]);
            $fits = (int) q_val('SELECT allocated_amount - utilised_amount >= CAST(? AS DECIMAL(12,2)) FROM program_budgets WHERE program_budget_id = ?', [$amt, $pb['program_budget_id']]);
            if (!$fits) {
                $pdo->rollBack();
                flash('error', 'This expense is larger than the remaining balance of the program budget.');
            } else {
                q('INSERT INTO budget_expenses (program_budget_id, activity_id, recorded_by, amount, particulars, expense_date) VALUES (?, ?, ?, ?, ?, ?)', [$pb['program_budget_id'], $act, uid(), $amt, mb_substr(post('particulars'), 0, 255), $date]);
                refresh_utilised((int) $pb['program_budget_id']);
                $pdo->commit();
                audit('EXPENSE_RECORDED', peso($amt) . ' on program budget #' . $pb['program_budget_id']);
                flash('success', 'Expense of ' . peso($amt) . ' recorded. Utilised amount updated.');
            }
        }
        redirect('barangay/budget.php?tab=programs&pb=' . $pb['program_budget_id']);
    }

    if ($action === 'delete_expense') {
        $exp = q_one('SELECT e.* FROM budget_expenses e JOIN program_budgets pb ON pb.program_budget_id = e.program_budget_id JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id WHERE e.expense_id = ? AND bp.barangay_id = ?', [post_int('expense_id'), $b]) ?? require_barangay_access(null);
        $pdo->beginTransaction();
        q('DELETE FROM budget_expenses WHERE expense_id = ?', [$exp['expense_id']]);
        refresh_utilised((int) $exp['program_budget_id']);
        $pdo->commit();
        audit('EXPENSE_DELETED', 'Expense #' . $exp['expense_id'] . ' (' . peso($exp['amount']) . ')');
        flash('success', 'Expense removed. Utilised amount updated.');
        redirect('barangay/budget.php?tab=expenses');
    }
}

$allocations = q_all('SELECT * FROM budget_allocations ORDER BY date_entered DESC');
$allocId = get_int('allocation') ?: (int) ($allocations[0]['allocation_id'] ?? 0);
$period = null;
foreach ($allocations as $a) {
    if ((int) $a['allocation_id'] === $allocId) {
        $period = $a;
    }
}
$proposals = q_all('SELECT bp.*, d.title AS doc_title, d.file_size, u.username AS reviewer,
                           (SELECT COUNT(*) FROM program_budgets pb WHERE pb.proposal_id = bp.proposal_id) AS line_count
                      FROM budget_proposals bp LEFT JOIN documents d ON d.document_id = bp.document_id LEFT JOIN users u ON u.user_id = bp.reviewed_by
                     WHERE bp.barangay_id = ? AND bp.allocation_id = ? ORDER BY bp.proposal_id DESC', [$b, $allocId]);
$summary = q_one('SELECT COALESCE(SUM(CASE WHEN prop_status IN ("pending","approved") THEN proposed_amount END), 0) proposed,
                         COALESCE(SUM(CASE WHEN prop_status = "approved" THEN approved_amount END), 0) approved,
                         (SELECT COALESCE(SUM(pb.utilised_amount), 0) FROM program_budgets pb JOIN budget_proposals x ON x.proposal_id = pb.proposal_id WHERE x.barangay_id = ? AND x.allocation_id = ? AND x.prop_status = "approved") utilised
                    FROM budget_proposals WHERE barangay_id = ? AND allocation_id = ?', [$b, $allocId, $b, $allocId]);
$programBudgets = q_all('SELECT pb.*, p.program_code, p.title, bp.proposal_code, bp.document_id, bp.approved_amount
                           FROM program_budgets pb JOIN programs p ON p.program_id = pb.program_id JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id
                          WHERE bp.barangay_id = ? AND bp.allocation_id = ? AND bp.prop_status = "approved" ORDER BY p.program_code', [$b, $allocId]);
$expenses = q_all('SELECT e.*, p.program_code, p.title AS program_title, a.title AS activity_title, u.username FROM budget_expenses e
                     JOIN program_budgets pb ON pb.program_budget_id = e.program_budget_id JOIN programs p ON p.program_id = pb.program_id
                     JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id LEFT JOIN activities a ON a.activity_id = e.activity_id JOIN users u ON u.user_id = e.recorded_by
                    WHERE bp.barangay_id = ? AND bp.allocation_id = ? ORDER BY e.expense_date DESC, e.expense_id DESC', [$b, $allocId]);
$tab = in_array(get('tab'), ['proposals', 'programs', 'expenses'], true) ? get('tab') : 'proposals';
$selPb = null;
foreach ($programBudgets as $pb) {
    if ((int) $pb['program_budget_id'] === get_int('pb') || (!get_int('pb') && !$selPb)) {
        $selPb = $pb;
    }
}
$edit = get('edit');
$editing = null;
$editLines = [];
if ($edit !== '') {
    if ($edit !== 'new') {
        $editing = own_proposal((int) $edit, $b) ?? require_barangay_access(null);
        foreach (q_all('SELECT program_id, allocated_amount FROM program_budgets WHERE proposal_id = ?', [$editing['proposal_id']]) as $l) {
            $editLines[(int) $l['program_id']] = $l['allocated_amount'];
        }
    }
    $myPrograms = q_all('SELECT program_id, program_code, title, centre FROM programs WHERE barangay_id = ? AND status <> "cancelled" ORDER BY program_code', [$b]);
}
$pendingCount = count(array_filter($proposals, fn ($p) => $p['prop_status'] === 'pending'));

$title = 'Budget';
$nav = 'budget';
$crumb = 'Resources · Budget';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Budget</h1><div class="page-sub"><?= e(current_user()['barangay_name']) ?> · <?= e($period['period'] ?? 'No allocation yet') ?> · Proposal and utilisation</div></div>
  <div class="page-actions">
    <?php if (count($allocations) > 1): ?><form method="get"><select class="input" name="allocation" data-autosubmit style="width:auto"><?php foreach ($allocations as $a): ?><option value="<?= (int) $a['allocation_id'] ?>"<?= (int) $a['allocation_id'] === $allocId ? ' selected' : '' ?>><?= e($a['period']) ?></option><?php endforeach; ?></select></form><?php endif; ?>
    <?php if ($pendingCount): ?><?= pill('pending', $pendingCount . ' proposal' . ($pendingCount > 1 ? 's' : '') . ' pending') ?><?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('barangay/budget-report.php?allocation=' . $allocId)) ?>">Budget reports</a>
    <?php if ($allocations): ?><a class="btn btn-primary" href="?edit=new&amp;allocation=<?= $allocId ?>">+ New proposal</a><?php endif; ?>
  </div>
</div>
<div class="scope-banner">Budget records are open to the SK officials and the SK Federation only. KK members have no access to them.</div>

<?php if (!$allocations): ?>
  <div class="card empty">The SK Federation has not entered a budget allocation yet. Proposals can be prepared once an allocation period exists.</div>
<?php elseif ($edit !== ''): ?>
  <form class="card card-pad" method="post" enctype="multipart/form-data" style="max-width:820px">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_proposal"><input type="hidden" name="proposal_id" value="<?= (int) ($editing['proposal_id'] ?? 0) ?>">
    <div class="spread mb"><div><div class="section-title"><?= $editing ? e($editing['proposal_code']) . ' · ' . ($editing['prop_status'] === 'returned' ? 'Revise and resubmit' : 'Edit draft') : 'New Budget Proposal' ?></div><div class="section-sub">One line per program. The proposed amount is the total of the lines.</div></div><a class="btn btn-secondary" href="?">Cancel</a></div>
    <?php if ($editing && $editing['prop_status'] === 'returned'): ?><div class="alert alert-error"><strong>Returned by the federation:</strong> <?= e($editing['remarks']) ?></div><?php endif; ?>
    <div class="form-grid">
      <div class="field"><label>Proposal title <span class="req">*</span></label><input class="input" name="title" required maxlength="255" value="<?= e($editing['title'] ?? '') ?>" placeholder="e.g. Annual Budget Proposal — CY <?= date('Y') ?>"></div>
      <div class="field"><label>Allocation period</label><select class="input" name="allocation_id"><?php foreach ($allocations as $a): ?><option value="<?= (int) $a['allocation_id'] ?>"<?= (int) $a['allocation_id'] === (int) ($editing['allocation_id'] ?? $allocId) ? ' selected' : '' ?>><?= e($a['period']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="card table-wrap mb">
      <table class="table"><thead><tr><th>Program</th><th>Centre</th><th class="num" style="width:180px">Proposed amount (₱)</th></tr></thead><tbody>
      <?php foreach ($myPrograms as $mp): ?>
        <tr><td style="font-weight:500"><?= e($mp['title']) ?><div class="sub"><?= e($mp['program_code']) ?></div></td><td class="muted small"><?= e($mp['centre']) ?></td>
          <td><input class="input mono" style="text-align:right" name="line[<?= (int) $mp['program_id'] ?>]" inputmode="decimal" placeholder="0.00" value="<?= e($editLines[(int) $mp['program_id']] ?? '') ?>" data-line></td></tr>
      <?php endforeach; ?>
      <?php if (!$myPrograms): ?><tr><td colspan="3" class="empty">Create programs first in <a href="<?= e(url('barangay/programs.php')) ?>">Programs</a>.</td></tr><?php endif; ?>
      </tbody><tfoot><tr><td colspan="2">Total proposed</td><td class="num" data-total>₱0.00</td></tr></tfoot></table>
    </div>
    <div class="field"><label>Supporting document <?= $editing && $editing['document_id'] ? '(a file is attached — upload to replace)' : '' ?></label><input class="input" type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
      <div class="hint">Proposal letter, itemized breakdown or council resolution. It is stored in the document repository and linked to this proposal.</div></div>
    <div class="modal-actions" style="justify-content:flex-start">
      <button class="btn btn-secondary" name="submit" value="0">Save as draft</button>
      <button class="btn btn-primary" name="submit" value="1"><?= $editing && $editing['prop_status'] === 'returned' ? 'Resubmit to federation' : 'Submit to federation' ?></button>
    </div>
  </form>
  <?php $inline_script = 'var t=document.querySelector("[data-total]");function s(){var v=0;document.querySelectorAll("[data-line]").forEach(function(i){var n=parseFloat(i.value.replace(/[,₱\s]/g,""));if(!isNaN(n))v+=Math.round(n*100)});t.textContent="₱"+(v/100).toLocaleString("en-PH",{minimumFractionDigits:2,maximumFractionDigits:2});}document.querySelectorAll("[data-line]").forEach(function(i){i.addEventListener("input",s)});s();'; ?>
<?php else: $bal = (float) $summary['approved'] - (float) $summary['utilised']; ?>
  <div class="grid grid-4 mb">
    <div class="card stat" style="border-top:3px solid var(--blue)"><div class="stat-label">Proposed</div><div class="stat-value"><?= e(peso0($summary['proposed'])) ?></div></div>
    <div class="card stat" style="border-top:3px solid var(--green)"><div class="stat-label">Approved by Federation</div><div class="stat-value"><?= e(peso0($summary['approved'])) ?></div></div>
    <div class="card stat" style="border-top:3px solid var(--sun)"><div class="stat-label">Utilised</div><div class="stat-value"><?= e(peso0($summary['utilised'])) ?></div><div class="stat-sub"><?= pct($summary['utilised'], $summary['approved'], 1) ?>% of approved</div></div>
    <div class="card stat" style="border-top:3px solid var(--indigo)"><div class="stat-label">Balance</div><div class="stat-value"><?= e(peso0($bal)) ?></div></div>
  </div>
  <div class="tabs">
    <a class="tab<?= $tab === 'proposals' ? ' active' : '' ?>" href="?tab=proposals&amp;allocation=<?= $allocId ?>">Proposals</a>
    <a class="tab<?= $tab === 'programs' ? ' active' : '' ?>" href="?tab=programs&amp;allocation=<?= $allocId ?>">Program Budgets</a>
    <a class="tab<?= $tab === 'expenses' ? ' active' : '' ?>" href="?tab=expenses&amp;allocation=<?= $allocId ?>">Expenses</a>
  </div>

  <?php if ($tab === 'proposals'): ?>
    <?php foreach ($proposals as $p): $st = $p['prop_status']; ?>
      <div class="row-card <?= ['pending' => '', 'approved' => 'green', 'returned' => 'red', 'draft' => 'amber'][$st] ?>">
        <div>
          <div class="row" style="gap:8px"><span class="record-id"><?= e($p['proposal_code']) ?></span><?= pill($st) ?></div>
          <div class="row-card-title"><?= e($p['title']) ?></div>
          <div class="row-card-detail">Proposed <?= e(peso($p['proposed_amount'])) ?> · Covers <?= (int) $p['line_count'] ?> program<?= (int) $p['line_count'] === 1 ? '' : 's' ?><?= $st === 'approved' ? ' · Approved ' . e(peso($p['approved_amount'])) . ' · Deducted from the federation allocation' : '' ?><?= $st === 'pending' ? ' · Submitted to SK Federation' : '' ?></div>
          <div class="row-card-detail"><?= $p['remarks'] ? 'Remarks: ' . e($p['remarks']) : ($p['document_id'] ? 'Attachment: <a href="' . e(url('file.php?id=' . $p['document_id'])) . '" target="_blank" rel="noopener">' . e($p['doc_title']) . '</a> (' . e(file_size_label((int) $p['file_size'])) . ')' : 'No attachment') ?></div>
        </div>
        <div class="row-card-side">
          <span class="row-card-time"><?= $st === 'draft' ? 'Draft' : ($st === 'pending' ? 'Submitted ' . e(fmt_date($p['date_submitted'])) : ucfirst($st) . ' ' . e(fmt_date($p['date_reviewed']))) ?></span>
          <?php if ($st === 'draft'): ?>
            <div class="row" style="gap:6px"><a class="btn btn-secondary btn-sm" href="?edit=<?= (int) $p['proposal_id'] ?>">Edit</a>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="submit_proposal"><input type="hidden" name="proposal_id" value="<?= (int) $p['proposal_id'] ?>"><button class="btn btn-primary btn-sm">Submit</button></form>
              <form method="post" data-confirm="Delete this draft?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_draft"><input type="hidden" name="proposal_id" value="<?= (int) $p['proposal_id'] ?>"><button class="btn btn-danger btn-sm">Delete</button></form></div>
          <?php elseif ($st === 'returned'): ?><a class="btn btn-primary btn-sm" href="?edit=<?= (int) $p['proposal_id'] ?>">Revise and resubmit</a>
          <?php elseif ($st === 'approved'): ?><a class="btn btn-secondary btn-sm" href="?tab=programs&amp;allocation=<?= $allocId ?>">View program budgets</a><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$proposals): ?><div class="card empty">No proposals for <?= e($period['period']) ?> yet. Prepare one with “+ New proposal”.</div><?php endif; ?>

  <?php elseif ($tab === 'programs'):
      foreach (q_all('SELECT bp.proposal_code, bp.approved_amount, SUM(pb.allocated_amount) lines_total FROM budget_proposals bp JOIN program_budgets pb ON pb.proposal_id = bp.proposal_id
                       WHERE bp.barangay_id = ? AND bp.allocation_id = ? AND bp.prop_status = "approved" GROUP BY bp.proposal_id HAVING lines_total > bp.approved_amount', [$b, $allocId]) as $ov): ?>
      <div class="alert alert-warning"><?= e($ov['proposal_code']) ?> was approved at <?= e(peso($ov['approved_amount'])) ?>, but its program lines total <?= e(peso($ov['lines_total'])) ?>. Reduce the allocated amounts so they fit the approved budget.</div>
    <?php endforeach; ?>
    <div class="split">
      <div class="card table-wrap">
        <div class="card-head"><div class="section-title">Program Budgets — Completion Status</div></div>
        <table class="table">
          <thead><tr><th>Program</th><th class="num">Allocated</th><th class="num">Utilised</th><th class="num">Balance</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($programBudgets as $pb): ?>
            <tr class="<?= $selPb && $selPb['program_budget_id'] === $pb['program_budget_id'] ? 'selected' : '' ?>">
              <td><a class="row-link" href="?tab=programs&amp;allocation=<?= $allocId ?>&amp;pb=<?= (int) $pb['program_budget_id'] ?>"><strong><?= e($pb['title']) ?></strong><div class="sub"><?= e($pb['program_code']) ?></div></a></td>
              <td class="num"><?= e(peso($pb['allocated_amount'])) ?></td><td class="num"><?= e(peso($pb['utilised_amount'])) ?></td><td class="num"><?= e(peso((float) $pb['allocated_amount'] - (float) $pb['utilised_amount'])) ?></td>
              <td><?= pill($pb['completion_status']) ?><?php if ($pb['balance_note']): ?><div class="muted" style="font-size:10.5px;max-width:200px;margin-top:3px"><?= e($pb['balance_note']) ?></div><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$programBudgets): ?><tr><td colspan="5" class="empty">No approved program budgets yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <aside class="card panel">
        <?php if ($selPb): $al = (float) $selPb['allocated_amount']; $ut = (float) $selPb['utilised_amount'];
            $doc = $selPb['document_id'] ? q_one('SELECT * FROM documents WHERE document_id = ?', [$selPb['document_id']]) : null;
            $pbActs = q_all('SELECT activity_id, title FROM activities WHERE program_id = ? ORDER BY activity_date', [$selPb['program_id']]); ?>
          <div class="panel-title"><?= e($selPb['title']) ?></div>
          <div class="panel-sub"><?= e($selPb['program_code']) ?> · <?= e($selPb['proposal_code']) ?></div>
          <div class="panel-section">
            <div class="kv"><span>Allocated</span><span><?= e(peso($al)) ?></span></div>
            <div class="kv"><span>Utilised</span><span class="blue"><?= e(peso($ut)) ?></span></div>
            <div class="kv"><span>Balance</span><span><?= e(peso($al - $ut)) ?></span></div>
            <div style="margin-top:8px"><?= bar($al ? $ut / $al * 100 : 0, $al && $ut >= $al ? 'green' : 'blue') ?></div>
            <div class="mono small muted" style="margin-top:4px"><?= pct($ut, $al) ?>% utilised</div>
          </div>
          <form class="panel-section" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="update_program_budget"><input type="hidden" name="program_budget_id" value="<?= (int) $selPb['program_budget_id'] ?>">
            <div class="label-mono">Completion Status</div>
            <select class="input mb" name="completion_status"><?php foreach (['ongoing', 'incomplete', 'complete'] as $s): ?><option value="<?= $s ?>"<?= $selPb['completion_status'] === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
            <div class="label-mono">Note on Remaining Amount</div>
            <textarea class="input mb" name="balance_note" rows="3" placeholder="Explain the remaining amount, e.g. held for supplies not yet delivered."><?= e($selPb['balance_note']) ?></textarea>
            <div class="label-mono">Allocated amount (₱)</div>
            <input class="input mono mb" name="allocated_amount" value="<?= e($selPb['allocated_amount']) ?>" inputmode="decimal">
            <button class="btn btn-secondary btn-block">Save status &amp; note</button>
          </form>
          <?php if ($doc): ?><div class="panel-section"><div class="label-mono">Supporting Document</div><a class="note-box" style="display:flex;justify-content:space-between;text-decoration:none" href="<?= e(url('file.php?id=' . $doc['document_id'])) ?>" target="_blank" rel="noopener"><span><?= e($doc['title']) ?></span><span class="mono small muted"><?= e(file_size_label((int) $doc['file_size'])) ?></span></a></div><?php endif; ?>
          <button class="btn btn-primary btn-block mt" data-open="dlg-expense">Record expense</button>
          <dialog class="modal" id="dlg-expense">
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="add_expense"><input type="hidden" name="program_budget_id" value="<?= (int) $selPb['program_budget_id'] ?>">
              <div class="modal-head"><div><div class="modal-title">Record Expense</div><div class="muted small"><?= e($selPb['title']) ?> · balance <?= e(peso($al - $ut)) ?></div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
              <div class="field"><label>Particulars</label><input class="input" name="particulars" required maxlength="255" placeholder="e.g. Meals and snacks"></div>
              <div class="form-grid">
                <div class="field"><label>Amount (₱)</label><input class="input mono" name="amount" required inputmode="decimal" placeholder="0.00"></div>
                <div class="field"><label>Expense date</label><input class="input" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="field full"><label>Activity (optional)</label><select class="input" name="activity_id"><option value="0">— Program-level expense —</option><?php foreach ($pbActs as $pa): ?><option value="<?= (int) $pa['activity_id'] ?>"><?= e($pa['title']) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Record expense</button></div>
            </form>
          </dialog>
        <?php else: ?><div class="empty">Program budgets appear here once the federation approves a proposal.</div><?php endif; ?>
      </aside>
    </div>

  <?php else: ?>
    <div class="card table-wrap">
      <div class="card-head"><div><div class="section-title">Expense Records</div><div class="section-sub">Recorded expenses update the program utilisation automatically.</div></div><span class="mono small">Total <?= e(peso($summary['utilised'])) ?></span></div>
      <table class="table">
        <thead><tr><th>Date</th><th>Program</th><th>Particulars</th><th>Activity</th><th class="num">Amount</th><th>Recorded by</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($expenses as $x): ?>
          <tr><td class="sub nowrap"><?= e(fmt_date($x['expense_date'])) ?></td><td><?= e($x['program_title']) ?><div class="sub"><?= e($x['program_code']) ?></div></td><td><?= e($x['particulars']) ?></td><td class="muted small"><?= e($x['activity_title'] ?: '—') ?></td>
            <td class="num"><?= e(peso($x['amount'])) ?></td><td class="sub"><?= e($x['username']) ?></td>
            <td class="right"><form method="post" data-confirm="Delete this expense?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="expense_id" value="<?= (int) $x['expense_id'] ?>"><button class="link-btn small red">Delete</button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?><tr><td colspan="7" class="empty">No expenses recorded yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
