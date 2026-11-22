<?php
/**
 * Programs & Activities.
 * Barangay official: creates and encodes programs and their activities
 * (date, time, venue). Creating an activity generates its QR token and its
 * default evaluation form. Federation: monitors every barangay, read-only.
 */
$canEdit = role() === ROLE_BARANGAY;
$scope = $canEdit ? my_barangay() : (get_int('barangay') ?: null);
$base = $canEdit ? 'barangay/programs.php' : 'federation/programs.php';
const PROGRAM_STATUSES = ['planned', 'ongoing', 'completed', 'evaluated', 'cancelled'];

if ($canEdit && is_post()) {
    csrf_check();
    $action = post('action');
    $b = my_barangay();

    if ($action === 'save_program') {
        $id = post_int('program_id');
        $titleIn = post('title');
        $centre = post('centre');
        $status = post('status') ?: 'planned';
        if ($titleIn === '' || !in_array($centre, CENTRES, true) || !in_array($status, PROGRAM_STATUSES, true)) {
            flash('error', 'Enter a title and choose a centre of participation and status.');
            redirect($base . ($id ? '?id=' . $id : ''));
        }
        if ($id) {
            $st = q('UPDATE programs SET title = ?, centre = ?, description = ?, status = ? WHERE program_id = ? AND barangay_id = ?', [$titleIn, $centre, post('description') ?: null, $status, $id, $b]);
            if (!$st->rowCount() && !q_val('SELECT 1 FROM programs WHERE program_id = ? AND barangay_id = ?', [$id, $b])) {
                require_barangay_access(null);
            }
            audit('PROGRAM_UPDATED', 'Updated program #' . $id);
            flash('success', 'Program updated.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            q('INSERT INTO programs (barangay_id, created_by, program_code, title, centre, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [$b, uid(), 'TMP-' . bin2hex(random_bytes(6)), $titleIn, $centre, post('description') ?: null, $status]);
            $id = (int) $pdo->lastInsertId();
            // The code is derived from the ID, so it is unique without a separate counter.
            q('UPDATE programs SET program_code = ? WHERE program_id = ?', [sprintf('PRG-%03d', $id), $id]);
            $pdo->commit();
            audit('PROGRAM_CREATED', 'Created program ' . sprintf('PRG-%03d', $id) . ' ' . $titleIn);
            flash('success', 'Program ' . sprintf('PRG-%03d', $id) . ' created. Add its activities next.');
        }
        redirect($base . '?id=' . $id);
    }

    if ($action === 'delete_program') {
        $id = post_int('program_id');
        $p = q_one('SELECT * FROM programs WHERE program_id = ? AND barangay_id = ?', [$id, $b]) ?? require_barangay_access(null);
        $used = q_val('SELECT (SELECT COUNT(*) FROM program_budgets WHERE program_id = ?) + (SELECT COUNT(*) FROM attendance at JOIN activities a ON a.activity_id = at.activity_id WHERE a.program_id = ?)', [$id, $id]);
        if ($used) {
            flash('error', 'This program has budget lines or recorded attendance. Mark it cancelled instead of deleting it.');
            redirect($base . '?id=' . $id);
        }
        q('DELETE FROM programs WHERE program_id = ? AND barangay_id = ?', [$id, $b]);
        audit('PROGRAM_DELETED', 'Deleted program ' . $p['program_code']);
        flash('success', 'Program ' . $p['program_code'] . ' deleted.');
        redirect($base);
    }

    if ($action === 'save_activity') {
        $pid = post_int('program_id');
        $aid = post_int('activity_id');
        $prog = q_one('SELECT * FROM programs WHERE program_id = ? AND barangay_id = ?', [$pid, $b]) ?? require_barangay_access(null);
        $t = post('title');
        $date = post('activity_date');
        $start = post('start_time') ?: null;
        $end = post('end_time') ?: null;
        $target = max(0, post_int('target_count'));
        if ($t === '' || !strtotime($date) || ($start && $end && $end <= $start)) {
            flash('error', 'Enter the activity title and date. The end time must be after the start time.');
            redirect($base . '?id=' . $pid);
        }
        // Venue clash check within the barangay on the same date and overlapping time.
        $clash = q_val('SELECT a.title FROM activities a JOIN programs p ON p.program_id = a.program_id
                         WHERE p.barangay_id = ? AND a.activity_date = ? AND a.venue = ? AND a.activity_id <> ?
                           AND (? IS NULL OR a.end_time IS NULL OR a.start_time < ?) AND (? IS NULL OR a.start_time IS NULL OR a.end_time > ?) LIMIT 1',
            [$b, $date, post('venue'), $aid, $end, $end, $start, $start]);
        if ($aid) {
            $ok = q_val('SELECT 1 FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE a.activity_id = ? AND p.barangay_id = ?', [$aid, $b]) ?: require_barangay_access(null);
            q('UPDATE activities SET program_id = ?, title = ?, activity_date = ?, start_time = ?, end_time = ?, venue = ?, target_count = ?, is_open = ? WHERE activity_id = ?',
                [$pid, $t, $date, $start, $end, post('venue') ?: null, $target, post('is_open') === '1' ? 1 : 0, $aid]);
            audit('ACTIVITY_UPDATED', 'Updated activity #' . $aid);
            flash('success', 'Activity updated.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            q('INSERT INTO activities (program_id, title, activity_date, start_time, end_time, venue, qr_token, is_open, target_count) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
                [$pid, $t, $date, $start, $end, post('venue') ?: null, new_qr_token(), $target]);
            $aid = (int) $pdo->lastInsertId();
            create_default_form($aid, $t);
            $pdo->commit();
            audit('ACTIVITY_CREATED', 'Created activity #' . $aid . ' under ' . $prog['program_code']);
            notify_role(ROLE_MEMBER, 'New activity: ' . $t, fmt_date($date) . ($start ? ' · ' . fmt_time($start) : '') . (post('venue') ? ' · ' . post('venue') : ''), 'member/calendar.php', $b);
            flash('success', 'Activity created with its attendance QR code and evaluation form.');
        }
        if ($clash) {
            flash('warning', 'Venue conflict: "' . $clash . '" is scheduled at the same venue and time on that date.');
        }
        redirect($base . '?id=' . $pid);
    }

    if ($action === 'delete_activity') {
        $aid = post_int('activity_id');
        $a = q_one('SELECT a.*, p.program_id FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE a.activity_id = ? AND p.barangay_id = ?', [$aid, $b]) ?? require_barangay_access(null);
        if (q_val('SELECT COUNT(*) FROM attendance WHERE activity_id = ?', [$aid])) {
            flash('error', 'This activity already has attendance records and cannot be deleted. Close it instead.');
        } else {
            q('DELETE FROM activities WHERE activity_id = ?', [$aid]);
            audit('ACTIVITY_DELETED', 'Deleted activity #' . $aid);
            flash('success', 'Activity deleted.');
        }
        redirect($base . '?id=' . $a['program_id']);
    }
}

$programs = programs_overview($scope);
if (get('export') === 'csv') {
    send_csv('programs-' . date('Ymd') . '.csv', ['Code', 'Program', 'Barangay', 'Centre', 'Status', 'First activity', 'Activities', 'Allocated', 'Utilised', 'Budget status', 'Target', 'Reached'],
        array_map(fn ($p) => [$p['program_code'], $p['title'], $p['barangay_name'], $p['centre'], $p['status'], $p['first_date'], $p['activity_count'], $p['allocated_amount'], $p['utilised_amount'], $p['completion_status'], $p['target'], $p['reached']], $programs));
}
$selId = get_int('id') ?: (int) ($programs[0]['program_id'] ?? 0);
$sel = null;
foreach ($programs as $p) {
    if ((int) $p['program_id'] === $selId) {
        $sel = $p;
    }
}
if (get_int('id') && !$sel && $canEdit) {
    require_barangay_access(null); // someone typed another barangay's program ID
}
$activities = $sel ? activities_overview(null, 'a.program_id = ?', [$sel['program_id']]) : [];
$barangays = $canEdit ? [] : q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');
$myPrograms = $canEdit ? q_all('SELECT program_id, program_code, title FROM programs WHERE barangay_id = ? ORDER BY program_code', [my_barangay()]) : [];

$title = 'Programs & Activities';
$nav = 'programs';
$crumb = ($canEdit ? 'Programs' : 'Monitoring') . ' · Programs & Activities';
require APP_ROOT . '/includes/header.php';
$link = fn (array $over) => '?' . http_build_query(array_merge($_GET, $over));
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Programs &amp; Activities</h1>
    <div class="page-sub">CBYDP centres of participation · <?= $canEdit ? e(current_user()['barangay_name']) : 'All barangays' ?> · <?= count($programs) ?> programs</div>
  </div>
  <div class="page-actions">
    <?php if (!$canEdit): ?>
      <form method="get"><select class="input" name="barangay" data-autosubmit style="width:auto"><option value="0">All barangays</option>
        <?php foreach ($barangays as $b): ?><option value="<?= (int) $b['barangay_id'] ?>"<?= $scope === (int) $b['barangay_id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option><?php endforeach; ?></select></form>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e($link(['export' => 'csv'])) ?>">↓ Export</a>
    <?php if ($canEdit): ?><button class="btn btn-primary" data-open="dlg-program" data-fill='{"program_id":"0","title":"","centre":"","description":"","status":"planned"}'>+ New Program</button><?php endif; ?>
  </div>
</div>
<?php if (!$canEdit): ?><div class="scope-banner">Programs and activities are encoded by each barangay. The federation reviews and consolidates them here.</div><?php endif; ?>

<div class="split">
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Program Name</th><?php if (!$canEdit): ?><th>Barangay</th><?php endif; ?><th>Centre</th><th>Schedule</th><th class="num">Budget</th><th>Budget Status</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($programs as $p): ?>
        <tr class="<?= $sel && (int) $p['program_id'] === (int) $sel['program_id'] ? 'selected' : '' ?>">
          <td style="font-weight:500;max-width:220px"><a class="row-link" href="<?= e($link(['id' => $p['program_id']])) ?>"><?= e($p['title']) ?><div class="sub"><?= e($p['program_code']) ?></div></a></td>
          <?php if (!$canEdit): ?><td class="muted"><?= e($p['barangay_name']) ?></td><?php endif; ?>
          <td class="muted small"><?= e($p['centre']) ?></td>
          <td class="sub nowrap"><?= $p['first_date'] ? e(fmt_date($p['first_date'])) . ($p['last_date'] !== $p['first_date'] ? '–' . e(fmt_date($p['last_date'], 'M j')) : '') : 'No activity yet' ?></td>
          <td class="num"><?= $p['allocated_amount'] !== null ? e(peso0($p['allocated_amount'])) : '—' ?></td>
          <td><?= $p['completion_status'] ? pill($p['completion_status']) : '<span class="muted small">No budget</span>' ?></td>
          <td><?= pill($p['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$programs): ?><tr><td colspan="7" class="empty">No programs yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <aside class="card panel">
    <?php if (!$sel): ?><div class="empty">Select a program to see its details.</div><?php else:
        $alloc = (float) ($sel['allocated_amount'] ?? 0); $util = (float) ($sel['utilised_amount'] ?? 0); ?>
      <?= pill($sel['status']) ?>
      <div class="panel-title" style="margin-top:8px"><?= e($sel['title']) ?></div>
      <div class="panel-sub"><?= e($sel['program_code']) ?> · <?= e($sel['barangay_name']) ?></div>
      <?php if ($sel['description']): ?><p class="muted small" style="margin-top:8px"><?= nl2br(e($sel['description'])) ?></p><?php endif; ?>

      <div class="panel-section" style="background:var(--surface-accent);padding:10px 12px;border-radius:7px">
        <div class="label-mono" style="margin-bottom:6px">Plan Alignment</div>
        <div class="row" style="gap:6px"><?= pill('completed', 'CBYDP') ?><?= pill('planned', $sel['centre']) ?></div>
      </div>

      <div class="panel-section">
        <div class="label-mono">Budget</div>
        <?php if ($sel['program_budget_id']): ?>
          <div class="kv"><span>Allocated</span><span><?= e(peso($alloc)) ?></span></div>
          <div class="kv"><span>Utilised</span><span class="blue"><?= e(peso($util)) ?></span></div>
          <div class="kv"><span>Balance</span><span><?= e(peso($alloc - $util)) ?></span></div>
          <div style="margin-top:8px"><?= bar($alloc ? $util / $alloc * 100 : 0) ?><div class="mono small muted" style="margin-top:4px"><?= pct($util, $alloc) ?>% utilised<?= $sel['prop_status'] !== 'approved' ? ' · proposal ' . e($sel['prop_status']) : '' ?></div></div>
          <div class="kv plain" style="margin-top:8px"><span>Completion status</span><span><?= pill($sel['completion_status']) ?></span></div>
          <?php if ($sel['balance_note']): ?><div class="note-box" style="margin-top:8px"><div class="label-mono" style="margin-bottom:4px">Note on remaining amount</div><?= e($sel['balance_note']) ?></div><?php endif; ?>
        <?php else: ?><p class="muted small">No budget line yet. Budget lines are added through a budget proposal.</p><?php endif; ?>
      </div>

      <div class="panel-section">
        <div class="label-mono">Target vs Reached</div>
        <div class="grid grid-2" style="gap:10px">
          <div class="card" style="padding:10px;text-align:center"><div class="stat-value" style="font-size:20px"><?= (int) $sel['target'] ?></div><div class="small muted">Target</div></div>
          <div class="card" style="padding:10px;text-align:center"><div class="stat-value" style="font-size:20px;color:<?= $sel['reached'] >= $sel['target'] && $sel['target'] > 0 ? 'var(--blue)' : '#C99400' ?>"><?= (int) $sel['reached'] ?></div><div class="small muted">Reached</div></div>
        </div>
        <?php if ($sel['target'] > 0): ?><div style="margin-top:8px"><?= bar($sel['reached'] / $sel['target'] * 100, 'amber') ?><div class="mono small muted" style="margin-top:3px"><?= pct($sel['reached'], $sel['target']) ?>% of target reached</div></div><?php endif; ?>
      </div>

      <div class="panel-section">
        <div class="spread"><div class="label-mono" style="margin:0">Activities (<?= count($activities) ?>)</div>
          <?php if ($canEdit): ?><button class="btn btn-ghost btn-sm" data-open="dlg-activity" data-fill='<?= e(json_encode(['activity_id' => 0, 'program_id' => $sel['program_id'], 'title' => '', 'activity_date' => '', 'start_time' => '', 'end_time' => '', 'venue' => '', 'target_count' => '', 'is_open' => '1'])) ?>'>+ Add activity</button><?php endif; ?></div>
        <?php foreach ($activities as $a): ?>
          <div style="border-top:1px solid var(--border-default);padding:9px 0;margin-top:8px">
            <div class="spread"><strong style="font-size:12.5px"><?= e($a['title']) ?></strong><?= pill((int) $a['is_open'] ? 'open' : 'closed', (int) $a['is_open'] ? 'Open' : 'Closed') ?></div>
            <div class="mono small muted"><?= e(fmt_date($a['activity_date'])) ?> · <?= e(fmt_time($a['start_time'])) ?><?= $a['end_time'] ? '–' . e(fmt_time($a['end_time'])) : '' ?></div>
            <div class="small muted"><?= e($a['venue'] ?: 'Venue to be announced') ?> · <?= (int) $a['attendance_count'] ?>/<?= (int) $a['target_count'] ?> attended · <?= (int) $a['response_count'] ?> evaluations</div>
            <?php if ($canEdit): ?>
            <div class="row" style="gap:6px;margin-top:6px">
              <a class="btn btn-secondary btn-sm" href="<?= e(url('barangay/qr.php?activity=' . $a['activity_id'])) ?>">QR &amp; attendance</a>
              <a class="btn btn-secondary btn-sm" href="<?= e(url('barangay/evaluation-form.php?activity=' . $a['activity_id'])) ?>">Evaluation form</a>
              <button class="btn btn-secondary btn-sm" data-open="dlg-activity" data-fill='<?= e(json_encode(['activity_id' => $a['activity_id'], 'program_id' => $sel['program_id'], 'title' => $a['title'], 'activity_date' => $a['activity_date'], 'start_time' => substr((string) $a['start_time'], 0, 5), 'end_time' => substr((string) $a['end_time'], 0, 5), 'venue' => $a['venue'] ?? '', 'target_count' => $a['target_count'], 'is_open' => $a['is_open']])) ?>'>Edit</button>
              <?php if (!(int) $a['attendance_count']): ?><form method="post" data-confirm="Delete this activity and its evaluation form?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="<?= (int) $a['activity_id'] ?>"><button class="btn btn-danger btn-sm">Delete</button></form><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($canEdit): ?>
        <div class="row mt">
          <button class="btn btn-primary" data-open="dlg-program" data-fill='<?= e(json_encode(['program_id' => $sel['program_id'], 'title' => $sel['title'], 'centre' => $sel['centre'], 'description' => $sel['description'] ?? '', 'status' => $sel['status']])) ?>'>Edit program</button>
          <form method="post" data-confirm="Delete program <?= e($sel['program_code']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_program"><input type="hidden" name="program_id" value="<?= (int) $sel['program_id'] ?>"><button class="btn btn-danger">Delete</button></form>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </aside>
</div>

<?php if ($canEdit): ?>
<dialog class="modal" id="dlg-program">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_program"><input type="hidden" name="program_id">
    <div class="modal-head"><div class="modal-title">Program</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Program title <span class="req">*</span></label><input class="input" name="title" required maxlength="255"></div>
    <div class="form-grid">
      <div class="field"><label>Centre of participation <span class="req">*</span></label><select class="input" name="centre" required><option value="">Select…</option><?php foreach (CENTRES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Status</label><select class="input" name="status"><?php foreach (PROGRAM_STATUSES as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label>Description</label><textarea class="input" name="description" rows="3"></textarea></div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Save program</button></div>
  </form>
</dialog>
<dialog class="modal" id="dlg-activity">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_activity"><input type="hidden" name="activity_id">
    <div class="modal-head"><div><div class="modal-title">Activity</div><div class="muted small">A unique QR code and evaluation form are created with each new activity.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Program</label><select class="input" name="program_id"><?php foreach ($myPrograms as $mp): ?><option value="<?= (int) $mp['program_id'] ?>"><?= e($mp['program_code'] . ' · ' . $mp['title']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Activity title <span class="req">*</span></label><input class="input" name="title" required maxlength="255"></div>
    <div class="form-grid">
      <div class="field"><label>Date <span class="req">*</span></label><input class="input" type="date" name="activity_date" required></div>
      <div class="field"><label>Target participants</label><input class="input" type="number" min="0" name="target_count"></div>
      <div class="field"><label>Start time</label><input class="input" type="time" name="start_time"></div>
      <div class="field"><label>End time</label><input class="input" type="time" name="end_time"></div>
      <div class="field full"><label>Venue</label><input class="input" name="venue" maxlength="255" placeholder="e.g. Barangay Hall"></div>
      <div class="field"><label>Attendance scanning</label><select class="input" name="is_open"><option value="1">Open — members can scan</option><option value="0">Closed</option></select></div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Save activity</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php';
