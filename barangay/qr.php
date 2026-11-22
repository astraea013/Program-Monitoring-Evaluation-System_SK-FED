<?php
/**
 * QR Attendance. The QR code identifies the ACTIVITY, not the person.
 * The official displays or prints it; logged-in KK members scan it
 * (member/scan.php). Youth without an account are encoded manually here.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_BARANGAY);
$b = my_barangay();

function own_activity(int $id, int $b): ?array
{
    return q_one('SELECT a.*, p.title AS program_title, p.program_code, p.centre, f.form_id
                    FROM activities a JOIN programs p ON p.program_id = a.program_id LEFT JOIN evaluation_forms f ON f.activity_id = a.activity_id
                   WHERE a.activity_id = ? AND p.barangay_id = ?', [$id, $b]);
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'quick_activity') {
        // "Other / unscheduled activity" from the setup screen.
        $pid = post_int('program_id');
        $t = post('title');
        $date = post('activity_date') ?: date('Y-m-d');
        if (!q_val('SELECT 1 FROM programs WHERE program_id = ? AND barangay_id = ?', [$pid, $b]) || $t === '' || !strtotime($date)) {
            flash('error', 'Choose the program and enter the activity name and date.');
            redirect('barangay/qr.php');
        }
        $pdo = db();
        $pdo->beginTransaction();
        q('INSERT INTO activities (program_id, title, activity_date, start_time, venue, qr_token, is_open, target_count) VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
            [$pid, $t, $date, post('start_time') ?: null, post('venue') ?: null, new_qr_token(), max(0, post_int('target_count'))]);
        $aid = (int) $pdo->lastInsertId();
        create_default_form($aid, $t);
        $pdo->commit();
        audit('ACTIVITY_CREATED', 'Created unscheduled activity #' . $aid . ' from QR Attendance');
        redirect('barangay/qr.php?activity=' . $aid);
    }

    $a = own_activity(post_int('activity_id'), $b) ?? require_barangay_access(null);
    $aid = (int) $a['activity_id'];

    if ($action === 'toggle') {
        q('UPDATE activities SET is_open = 1 - is_open WHERE activity_id = ?', [$aid]);
        audit('ACTIVITY_UPDATED', 'Attendance scanning ' . ((int) $a['is_open'] ? 'closed' : 'opened') . ' for activity #' . $aid);
        flash('success', 'Attendance scanning is now ' . ((int) $a['is_open'] ? 'closed' : 'open') . '.');
    }
    if ($action === 'regenerate') {
        q('UPDATE activities SET qr_token = ? WHERE activity_id = ?', [new_qr_token(), $aid]);
        audit('QR_REGENERATED', 'New QR code for activity #' . $aid);
        flash('warning', 'A new QR code was generated. Previously printed codes for this activity no longer work.');
    }
    if ($action === 'manual') {
        $pid = post_int('profile_id');
        if (!q_val('SELECT 1 FROM youth_profiles WHERE profile_id = ? AND barangay_id = ?', [$pid, $b])) {
            flash('error', 'Choose a youth profile from your barangay. Encode walk-ins in Youth Profiling first.');
        } elseif (q_val('SELECT 1 FROM attendance WHERE activity_id = ? AND profile_id = ?', [$aid, $pid])) {
            flash('error', 'That member is already recorded for this activity.');
        } else {
            q('INSERT INTO attendance (activity_id, profile_id, entry_method) VALUES (?, ?, "manual")', [$aid, $pid]);
            audit('ATTENDANCE_MANUAL', 'Manual attendance for profile #' . $pid . ' at activity #' . $aid);
            flash('success', 'Attendance encoded manually.');
        }
    }
    if ($action === 'remove') {
        $attId = post_int('attendance_id');
        $row = q_one('SELECT * FROM attendance WHERE attendance_id = ? AND activity_id = ?', [$attId, $aid]);
        if ($row && !q_val('SELECT 1 FROM evaluation_responses WHERE form_id = ? AND profile_id = ?', [$a['form_id'], $row['profile_id']])) {
            q('DELETE FROM attendance WHERE attendance_id = ?', [$attId]);
            audit('ATTENDANCE_REMOVED', 'Removed attendance #' . $attId);
            flash('success', 'Attendance entry removed.');
        } else {
            flash('error', 'Entries with a submitted evaluation cannot be removed.');
        }
    }
    redirect('barangay/qr.php?activity=' . $aid . (post('tab') ? '&tab=' . urlencode(post('tab')) : ''));
}

$aid = get_int('activity');
$activity = $aid ? (own_activity($aid, $b) ?? require_barangay_access(null)) : null;

// Lightweight polling endpoint for the live counter.
if ($activity && get('count') === '1') {
    header('Content-Type: application/json');
    echo json_encode(['count' => (int) q_val('SELECT COUNT(*) FROM attendance WHERE activity_id = ?', [$aid])]);
    exit;
}

if ($activity) {
    $roster = q_all('SELECT at.*, yp.full_name, yp.date_encoded, yp.user_id, r.response_id
                       FROM attendance at JOIN youth_profiles yp ON yp.profile_id = at.profile_id
                       LEFT JOIN evaluation_responses r ON r.profile_id = at.profile_id AND r.form_id = ?
                      WHERE at.activity_id = ? ORDER BY at.time_scanned DESC', [$activity['form_id'], $aid]);
    $responses = $activity['form_id'] ? q_all('SELECT r.*, yp.full_name, yp.date_encoded,
                       AVG(CASE WHEN q.section = "Program Quality" THEN ea.rating_value END) pq,
                       AVG(CASE WHEN q.section = "Implementer Quality" THEN ea.rating_value END) iq,
                       AVG(CASE WHEN q.section = "Perceived Benefits" THEN ea.rating_value END) pb,
                       AVG(ea.rating_value) overall,
                       MAX(CASE WHEN q.question_type = "text" THEN ea.answer_text END) suggestion
                  FROM evaluation_responses r JOIN youth_profiles yp ON yp.profile_id = r.profile_id
                  JOIN evaluation_answers ea ON ea.response_id = r.response_id JOIN evaluation_questions q ON q.question_id = ea.question_id
                 WHERE r.form_id = ? GROUP BY r.response_id ORDER BY r.date_submitted DESC', [$activity['form_id']]) : [];
    $tab = get('tab') === 'evaluations' ? 'evaluations' : 'attendance';

    if (get('export') === 'csv') {
        if ($tab === 'evaluations') {
            send_csv('evaluations-activity-' . $aid . '.csv', ['KK ID', 'Member', 'Program Quality', 'Implementer Quality', 'Perceived Benefits', 'Overall', 'Suggestion', 'Submitted'],
                array_map(fn ($r) => [kk_id((int) $r['profile_id'], $r['date_encoded']), $r['full_name'], number_format((float) $r['pq'], 2), number_format((float) $r['iq'], 2), number_format((float) $r['pb'], 2), number_format((float) $r['overall'], 2), $r['suggestion'], $r['date_submitted']], $responses));
        }
        send_csv('attendance-activity-' . $aid . '.csv', ['#', 'KK ID', 'Member', 'Time in', 'Method', 'Evaluation'],
            array_map(fn ($r, $i) => [count($roster) - $i, kk_id((int) $r['profile_id'], $r['date_encoded']), $r['full_name'], $r['time_scanned'], $r['entry_method'] === 'manual' ? 'Manual' : 'QR scan', $r['response_id'] ? 'Submitted' : 'Pending'], $roster, array_keys($roster)));
    }
    $walkins = q_all('SELECT profile_id, full_name, date_encoded FROM youth_profiles yp WHERE barangay_id = ? AND profile_id NOT IN (SELECT profile_id FROM attendance WHERE activity_id = ?)
                       AND (user_id IS NULL OR user_id IN (SELECT user_id FROM users WHERE verification_status = "verified")) ORDER BY full_name', [$b, $aid]);
    $scanUrl = scan_url($activity['qr_token']);
} else {
    $choices = activities_overview($b, 'a.activity_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $programs = q_all('SELECT program_id, program_code, title FROM programs WHERE barangay_id = ? AND status <> "cancelled" ORDER BY program_code', [$b]);
}

$title = 'QR Attendance';
$nav = 'qr';
$crumb = 'Programs · QR Attendance';
// Vendored locally (assets/js/vendor) so the QR code still draws without internet.
$scripts = [asset('js/vendor/qrcode-generator-1.4.4.js')];
// Draws the QR as an SVG into #id; shows a fallback if the script did not load.
function qr_js(string $text, int $size): string
{
    return '(function(){var el=document.getElementById("qr");if(typeof qrcode!=="function"){el.textContent="QR library not loaded.";return;}'
        . 'var q=qrcode(0,"M");q.addData(' . json_encode($text) . ');q.make();el.innerHTML=q.createSvgTag(Math.max(2,Math.floor(' . $size . '/q.getModuleCount())),0);})();';
}

// Printable QR poster.
if ($activity && get('print') === '1'): ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>QR · <?= e($activity['title']) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"></head>
<body style="background:#fff;text-align:center;padding:40px;font-family:Archivo,Inter,sans-serif;color:#10214B">
  <img src="<?= e(asset('img/sk-federation-logo.png')) ?>" alt="" style="width:90px">
  <div class="label-mono" style="margin-top:8px">SK <?= e(current_user()['barangay_name']) ?> · Attendance</div>
  <h1 style="font-size:28px;margin:6px 0"><?= e($activity['title']) ?></h1>
  <div class="mono"><?= e(fmt_date($activity['activity_date'], 'F j, Y')) ?> · <?= e(fmt_time($activity['start_time'])) ?> · <?= e($activity['venue'] ?: '') ?></div>
  <div id="qr" style="display:inline-block;margin:28px auto;padding:16px;border:2px solid #10214B;border-radius:10px"></div>
  <p style="font-size:16px;max-width:460px;margin:0 auto">Sign in to your KK member account, then scan this code with your phone camera to record your attendance and open the evaluation form.</p>
  <p class="mono small muted" style="margin-top:20px"><?= e($activity['program_code']) ?> · Activity #<?= (int) $activity['activity_id'] ?></p>
  <button class="btn btn-primary no-print" style="margin-top:16px" onclick="window.print()">Print</button>
  <script src="<?= e(asset('js/vendor/qrcode-generator-1.4.4.js')) ?>"></script>
  <script><?= qr_js($scanUrl, 320) ?></script>
</body></html>
<?php exit; endif;

if ($activity) {
    $inline_script = qr_js($scanUrl, 220)
        . 'setInterval(function(){fetch(' . json_encode(url('barangay/qr.php?activity=' . $aid . '&count=1')) . ',{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(d){var el=document.getElementById("qr-count");if(d.count!=el.textContent){el.textContent=d.count;document.getElementById("qr-new").hidden=false;}}).catch(function(){})},10000);';
}
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$activity): ?>
<div style="max-width:640px">
  <div class="page-head"><div><h1 class="page-title">Generate QR Code</h1><div class="page-sub">SK <?= e(current_user()['barangay_name']) ?> · Select the activity to generate its attendance QR</div></div></div>
  <form class="card card-pad mb" method="get">
    <div class="label-mono">Your Scheduled Activities</div>
    <?php foreach ($choices as $i => $c): ?>
      <label class="option-card"><input type="radio" name="activity" value="<?= (int) $c['activity_id'] ?>"<?= $i === 0 ? ' checked' : '' ?>>
        <span><strong style="font-size:13px"><?= e($c['title']) ?></strong> <?= (int) $c['is_open'] ? '' : pill('closed', 'Closed') ?><br><span class="mono small muted"><?= e(fmt_date($c['activity_date'])) ?> · <?= e(fmt_time($c['start_time'])) ?> · <?= e($c['venue'] ?: 'No venue') ?> · <?= (int) $c['attendance_count'] ?> attended</span></span></label>
    <?php endforeach; ?>
    <?php if (!$choices): ?><p class="muted">No activities in the last 30 days or upcoming. Schedule one in <a href="<?= e(url('barangay/programs.php')) ?>">Programs</a>, or create an unscheduled activity below.</p><?php endif; ?>
    <?php if ($choices): ?><button class="btn btn-primary btn-block mt" style="padding:11px">Generate QR Code →</button><?php endif; ?>
  </form>
  <form class="card card-pad" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="quick_activity">
    <div class="section-title" style="font-size:14px">Other / unscheduled activity</div>
    <p class="muted small mb">Creates the activity under one of your programs, with its own QR code and evaluation form.</p>
    <div class="field"><label>Program</label><select class="input" name="program_id" required><?php foreach ($programs as $p): ?><option value="<?= (int) $p['program_id'] ?>"><?= e($p['program_code'] . ' · ' . $p['title']) ?></option><?php endforeach; ?></select></div>
    <div class="form-grid">
      <div class="field full"><input class="input" name="title" placeholder="Activity name" required></div>
      <div class="field full"><input class="input" name="venue" placeholder="Venue"></div>
      <div class="field"><input class="input" type="date" name="activity_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="field"><input class="input" type="time" name="start_time"></div>
      <div class="field"><input class="input" type="number" min="0" name="target_count" placeholder="Target participants"></div>
    </div>
    <button class="btn btn-secondary" <?= $programs ? '' : 'disabled' ?>>Create and generate QR</button>
  </form>
</div>
<?php else: ?>
<div class="split split-left">
  <div class="qr-stage">
    <div class="label-mono" style="color:rgba(255,255,255,.55)"><?= e($activity['program_code']) ?></div>
    <div style="font-family:var(--font-head);font-size:17px;font-weight:700"><?= e($activity['title']) ?></div>
    <div class="mono small" style="color:rgba(255,255,255,.65)"><?= e($activity['venue'] ?: 'No venue') ?> · <?= e(fmt_date($activity['activity_date'])) ?> · <?= e(fmt_time($activity['start_time'])) ?></div>
    <div class="qr-box" id="qr" aria-label="Attendance QR code"></div>
    <div class="label-mono" style="color:rgba(255,255,255,.55);margin-bottom:4px">Members scanned in</div>
    <div class="qr-count" id="qr-count"><?= count($roster) ?></div>
    <div id="qr-new" hidden class="small" style="margin-top:6px"><a href="" style="color:var(--sun)">New scans — refresh roster</a></div>
    <div class="mt"><?= (int) $activity['is_open'] ? pill('open', 'Scanning open') : pill('closed', 'Scanning closed') ?></div>
    <p class="small" style="color:rgba(255,255,255,.7);margin-top:12px">Members sign in, then scan this code with their phone. Scanning records attendance and opens the evaluation form automatically.</p>
    <div class="row" style="justify-content:center;margin-top:10px">
      <a class="btn btn-secondary btn-sm" href="?">← Change activity</a>
      <a class="btn btn-secondary btn-sm" href="?activity=<?= $aid ?>&amp;print=1" target="_blank" rel="noopener">Print QR</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="activity_id" value="<?= $aid ?>"><button class="btn btn-secondary btn-sm"><?= (int) $activity['is_open'] ? 'Close scanning' : 'Open scanning' ?></button></form>
      <form method="post" data-confirm="Generate a new QR code? Printed copies of the current code will stop working."><?= csrf_field() ?><input type="hidden" name="action" value="regenerate"><input type="hidden" name="activity_id" value="<?= $aid ?>"><button class="btn btn-secondary btn-sm">New code</button></form>
    </div>
    <details style="margin-top:12px;text-align:left"><summary class="small" style="cursor:pointer;color:rgba(255,255,255,.7)">Scan link</summary><div class="mono" style="font-size:9px;word-break:break-all;color:rgba(255,255,255,.7);margin-top:4px"><?= e($scanUrl) ?></div></details>
  </div>

  <div>
    <div class="page-head" style="margin-bottom:12px">
      <div><h1 class="page-title" style="font-size:17px"><?= $tab === 'attendance' ? 'Live Attendance Roster' : 'KK Member Evaluations' ?></h1><div class="page-sub">Brgy. <?= e(current_user()['barangay_name']) ?> · <?= e($activity['title']) ?></div></div>
      <div class="page-actions">
        <a class="btn btn-secondary" href="?activity=<?= $aid ?>&amp;tab=<?= $tab ?>&amp;export=csv">Export <?= $tab ?></a>
        <?php if ($tab === 'attendance'): ?><button class="btn btn-primary" data-open="dlg-manual">+ Encode walk-in</button><?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(url('barangay/evaluation-form.php?activity=' . $aid)) ?>">Evaluation form</a>
      </div>
    </div>
    <div class="tabs">
      <a class="tab<?= $tab === 'attendance' ? ' active' : '' ?>" href="?activity=<?= $aid ?>">Attendance (<?= count($roster) ?>)</a>
      <a class="tab<?= $tab === 'evaluations' ? ' active' : '' ?>" href="?activity=<?= $aid ?>&amp;tab=evaluations">Evaluations (<?= count($responses) ?>)</a>
    </div>
    <?php if ($tab === 'attendance'): ?>
      <div class="card table-wrap">
        <table class="table">
          <thead><tr><th>#</th><th>KK ID</th><th>Member Name</th><th>Time In</th><th>Method</th><th>Evaluation</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($roster as $i => $r): ?>
            <tr>
              <td class="mono muted"><?= count($roster) - $i ?></td>
              <td class="record-id"><?= e(kk_id((int) $r['profile_id'], $r['date_encoded'])) ?></td>
              <td style="font-weight:500"><?= e($r['full_name']) ?></td>
              <td class="sub"><?= e(date('M j, g:i A', strtotime($r['time_scanned']))) ?></td>
              <td><?= $r['entry_method'] === 'manual' ? pill('draft', 'Manual') : pill('completed', 'QR scan') ?></td>
              <td><?= $r['response_id'] ? pill('submitted', 'Submitted') : ($r['user_id'] ? pill('pending') : '<span class="muted small">No account</span>') ?></td>
              <td class="right"><?php if (!$r['response_id']): ?><form method="post" data-confirm="Remove this attendance entry?"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="activity_id" value="<?= $aid ?>"><input type="hidden" name="attendance_id" value="<?= (int) $r['attendance_id'] ?>"><button class="link-btn small red">Remove</button></form><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$roster): ?><tr><td colspan="7" class="empty">No one has scanned in yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php else:
        $avgAll = $responses ? array_sum(array_map(fn ($r) => (float) $r['overall'], $responses)) / count($responses) : 0; ?>
      <div class="grid grid-3 mb">
        <div class="card stat"><div class="stat-label">Response Rate</div><div class="stat-value"><?= count($responses) ?>/<?= count($roster) ?></div><div class="stat-sub">attendees submitted</div></div>
        <div class="card stat"><div class="stat-label">Average Rating</div><div class="stat-value blue"><?= $responses ? number_format($avgAll, 1) : '—' ?><small>/5</small></div><div class="stat-sub">across all dimensions</div></div>
        <div class="card stat"><div class="stat-label">Written Suggestions</div><div class="stat-value"><?= count(array_filter($responses, fn ($r) => trim((string) $r['suggestion']) !== '')) ?></div><div class="stat-sub">member comments</div></div>
      </div>
      <div class="card table-wrap">
        <table class="table">
          <thead><tr><th>KK Member</th><th class="num">Program</th><th class="num">Implementer</th><th class="num">Benefits</th><th class="num">Overall</th><th>Suggestion</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($responses as $r): ?>
            <tr>
              <td style="font-weight:500"><?= e($r['full_name']) ?><div class="sub"><?= e(kk_id((int) $r['profile_id'], $r['date_encoded'])) ?></div></td>
              <?php foreach (['pq', 'iq', 'pb'] as $k): ?><td class="num"><?= $r[$k] !== null ? number_format((float) $r[$k], 1) : '—' ?></td><?php endforeach; ?>
              <td class="num"><strong class="blue"><?= number_format((float) $r['overall'], 1) ?></strong><span class="muted">/5</span></td>
              <td><?= trim((string) $r['suggestion']) !== '' ? pill('pending', 'Provided') : pill('completed', 'None') ?></td>
              <td class="right"><button class="btn btn-secondary btn-sm" data-open="dlg-eval" data-fill='<?= e(json_encode(['ev_name' => $r['full_name'], 'ev_pq' => number_format((float) $r['pq'], 1) . '/5', 'ev_iq' => number_format((float) $r['iq'], 1) . '/5', 'ev_pb' => number_format((float) $r['pb'], 1) . '/5', 'ev_overall' => number_format((float) $r['overall'], 1), 'ev_sugg' => trim((string) $r['suggestion']) ?: 'No written suggestion was provided.', 'ev_date' => 'Submitted ' . fmt_datetime($r['date_submitted'])])) ?>'>View</button></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$responses): ?><tr><td colspan="7" class="empty">No evaluations submitted yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <dialog class="modal" id="dlg-eval">
        <div class="modal-head"><div><div class="modal-title">Member Evaluation</div><div class="small muted" data-text="ev_name"></div></div><div class="stat-value blue" data-text="ev_overall"></div></div>
        <div class="kv"><span>Program Quality</span><span data-text="ev_pq"></span></div>
        <div class="kv"><span>Implementer Quality</span><span data-text="ev_iq"></span></div>
        <div class="kv"><span>Perceived Benefits</span><span data-text="ev_pb"></span></div>
        <div class="note-box mt"><div class="label-mono" style="margin-bottom:4px">Member Suggestion</div><span data-text="ev_sugg"></span></div>
        <div class="mono small muted mt" data-text="ev_date"></div>
        <div class="modal-actions"><button class="btn btn-primary" data-close>Close</button></div>
      </dialog>
    <?php endif; ?>
  </div>
</div>

<dialog class="modal" id="dlg-manual">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="manual"><input type="hidden" name="activity_id" value="<?= $aid ?>">
    <div class="modal-head"><div><div class="modal-title">Encode walk-in attendance</div><div class="muted small">For youth who attended without scanning — for example, those with no registered account.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Youth profile</label><select class="input" name="profile_id" required><option value="">Select…</option><?php foreach ($walkins as $w): ?><option value="<?= (int) $w['profile_id'] ?>"><?= e($w['full_name'] . ' · ' . kk_id((int) $w['profile_id'], $w['date_encoded'])) ?></option><?php endforeach; ?></select>
      <div class="hint">Not listed? <a href="<?= e(url('barangay/youth.php')) ?>">Encode the youth profile first.</a> Manual entries are recorded but do not count toward eligibility.</div></div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Record attendance</button></div>
  </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
