<?php
/**
 * Reports & Analytics, generated from stored records only (no AI
 * interpretation). Barangay: its own records, and it submits reports to the
 * federation. Federation: consolidated across barangays, and it sees what
 * the barangays submitted.
 */
$isBarangay = role() === ROLE_BARANGAY;
$scope = $isBarangay ? my_barangay() : null;
$year = get_int('year') ?: (int) date('Y');
const REPORT_TYPES = ['Accomplishment Report', 'Activity Evaluation Summary', 'Attendance Report', 'Budget Utilisation Report', 'Youth Profile Update'];

if ($isBarangay && is_post()) {
    csrf_check();
    $type = post('report_type');
    $period = post('period');
    if (!in_array($type, REPORT_TYPES, true) || $period === '') {
        flash('error', 'Choose the report type and the period it covers.');
    } else {
        $file = null;
        if (!empty($_FILES['file']['name'])) {
            try {
                $file = store_upload($_FILES['file'], 'documents', DOC_TYPES)['path'];
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
                redirect('barangay/reports.php');
            }
        }
        q('INSERT INTO reports (generated_by, barangay_id, report_type, period, file_path) VALUES (?, ?, ?, ?, ?)', [uid(), my_barangay(), $type, mb_substr($period, 0, 50), $file]);
        audit('REPORT_SUBMITTED', $type . ' · ' . $period);
        notify_role(ROLE_FEDERATION, current_user()['barangay_name'] . ' submitted a report', $type . ' · ' . $period, 'federation/reports.php');
        flash('success', $type . ' for ' . $period . ' submitted to the SK Federation.');
    }
    redirect('barangay/reports.php');
}

$yearSql = 'YEAR(a.activity_date) = ?';
$acts = activities_overview($scope, $yearSql, [$year]);
$held = array_values(array_filter($acts, fn ($a) => $a['activity_date'] <= date('Y-m-d')));
$members = (int) q_val('SELECT COUNT(*) FROM users WHERE role_id = ? AND verification_status = "verified" AND is_active = 1' . ($scope ? ' AND barangay_id = ?' : ''), $scope ? [ROLE_MEMBER, $scope] : [ROLE_MEMBER]);
$activePrograms = (int) q_val("SELECT COUNT(*) FROM programs WHERE status IN ('planned','ongoing')" . ($scope ? ' AND barangay_id = ?' : ''), $scope ? [$scope] : []);
$avg = average_rating($scope);

// Attendance rate: scanned + manual attendance against each activity's target.
$attendance = [];
if ($isBarangay) {
    foreach ($held as $a) {
        $attendance[] = ['label' => $a['title'], 'rate' => $a['target_count'] ? min(100, round($a['attendance_count'] / $a['target_count'] * 100)) : 0, 'detail' => $a['attendance_count'] . '/' . $a['target_count']];
    }
} else {
    $by = [];
    foreach ($held as $a) {
        $by[$a['barangay_name']]['att'] = ($by[$a['barangay_name']]['att'] ?? 0) + $a['attendance_count'];
        $by[$a['barangay_name']]['tgt'] = ($by[$a['barangay_name']]['tgt'] ?? 0) + $a['target_count'];
    }
    foreach ($by as $name => $v) {
        $attendance[] = ['label' => $name, 'rate' => $v['tgt'] ? min(100, round($v['att'] / $v['tgt'] * 100)) : 0, 'detail' => $v['att'] . '/' . $v['tgt']];
    }
}
usort($attendance, fn ($x, $y) => $y['rate'] <=> $x['rate']);

$trend = [];
for ($qtr = 1; $qtr <= 4; $qtr++) {
    $in = array_filter($held, fn ($a) => (int) ceil(date('n', strtotime($a['activity_date'])) / 3) === $qtr);
    $att = array_sum(array_column($in, 'attendance_count'));
    $tgt = array_sum(array_column($in, 'target_count'));
    $trend[] = ['q' => 'Q' . $qtr, 'value' => $tgt ? (int) round(min(100, $att / $tgt * 100)) : null, 'att' => $att];
}

$evalBy = section_averages($scope, $isBarangay ? 'activity' : 'centre');
$centres = centre_completion($scope);
$submissions = q_all('SELECT r.*, b.barangay_name, u.username FROM reports r LEFT JOIN barangays b ON b.barangay_id = r.barangay_id JOIN users u ON u.user_id = r.generated_by'
    . ($isBarangay ? ' WHERE r.barangay_id = ?' : ' WHERE r.barangay_id IS NOT NULL') . ' ORDER BY r.date_generated DESC LIMIT 12', $isBarangay ? [$scope] : []);
$status = $isBarangay ? [] : q_all("
    SELECT b.barangay_name,
           (SELECT COUNT(*) FROM users u WHERE u.barangay_id = b.barangay_id AND u.role_id = ? AND u.verification_status = 'verified') AS members,
           (SELECT COUNT(*) FROM programs p WHERE p.barangay_id = b.barangay_id) AS programs,
           (SELECT COUNT(*) FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id AND YEAR(a.activity_date) = ?) AS acts,
           (SELECT COUNT(*) FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id AND YEAR(a.activity_date) = ? AND a.activity_date <= CURDATE()) AS done,
           (SELECT COUNT(*) FROM attendance at JOIN activities a ON a.activity_id = at.activity_id JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id AND YEAR(a.activity_date) = ?) AS att,
           (SELECT COUNT(*) FROM evaluation_responses r JOIN evaluation_forms f ON f.form_id = r.form_id JOIN activities a ON a.activity_id = f.activity_id JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id AND YEAR(a.activity_date) = ?) AS evals
      FROM barangays b ORDER BY b.barangay_name", [ROLE_MEMBER, $year, $year, $year, $year]);

if (get('export') === 'csv') {
    $who = $isBarangay ? current_user()['barangay_name'] : 'SK Federation (all barangays)';
    q('INSERT INTO reports (generated_by, barangay_id, report_type, period) VALUES (?, ?, ?, ?)', [uid(), $scope, 'Reports & Analytics export', 'CY ' . $year]);
    audit('REPORT_EXPORTED', 'Reports & Analytics CY ' . $year);
    $rows = [['Scope', $who, '', ''], ['Period', 'CY ' . $year, '', ''], ['Registered KK members', $members, '', ''], ['Active programs', $activePrograms, '', ''], ['Activities held / scheduled', count($held) . '/' . count($acts), '', ''], ['Average evaluation (1-5)', $avg ? number_format($avg, 2) : '', '', ''], ['', '', '', '']];
    $rows[] = [$isBarangay ? 'Activity' : 'Barangay', 'Attendance rate %', 'Attended/target', ''];
    foreach ($attendance as $r) {
        $rows[] = [$r['label'], $r['rate'], $r['detail'], ''];
    }
    $rows[] = ['', '', '', ''];
    $rows[] = [$isBarangay ? 'Activity' : 'Centre', 'Program Quality', 'Implementer Quality', 'Perceived Benefits'];
    foreach ($evalBy as $g => $v) {
        $rows[] = [$g, number_format($v['Program Quality'] ?? 0, 2), number_format($v['Implementer Quality'] ?? 0, 2), number_format($v['Perceived Benefits'] ?? 0, 2)];
    }
    $rows[] = ['', '', '', ''];
    $rows[] = ['Centre', 'Completion %', 'Programs', ''];
    foreach ($centres as $c) {
        $rows[] = [$c['label'], $c['value'], $c['total'], ''];
    }
    send_csv('reports-' . $year . '.csv', ['Item', 'Value', '', ''], $rows);
}

$title = 'Reports & Analytics';
$nav = 'reports';
$crumb = ($isBarangay ? 'Content & Reports' : 'Budget & Reports') . ' · Reports';
require APP_ROOT . '/includes/header.php';
$validTrend = array_values(array_filter($trend, fn ($t) => $t['value'] !== null));
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Reports &amp; Analytics</h1>
    <div class="page-sub"><?= $isBarangay ? e('SK ' . current_user()['barangay_name']) . ' · Barangay records only' : 'SK Federation ' . e(setting('municipality', 'Nasipit')) . ' · All barangays' ?> · CY <?= $year ?></div>
  </div>
  <div class="page-actions">
    <form method="get"><select class="input" name="year" data-autosubmit style="width:auto"><?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--): ?><option<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select></form>
    <button class="btn btn-secondary" data-print>↓ Print / Save as PDF</button>
    <a class="btn btn-secondary" href="?year=<?= $year ?>&amp;export=csv">↓ Spreadsheet</a>
    <?php if ($isBarangay): ?><a class="btn btn-secondary" href="<?= e(url('barangay/budget-report.php')) ?>">Budget report</a><button class="btn btn-primary" data-open="dlg-submit">+ Submit Report</button><?php endif; ?>
  </div>
</div>
<?php if ($isBarangay): ?><div class="scope-banner">These reports include only programs, attendance, and KK member evaluations from <?= e(current_user()['barangay_name']) ?>.</div><?php endif; ?>

<div class="grid grid-4 mb">
  <div class="card stat"><div class="stat-label">Registered KK Members</div><div class="stat-value blue"><?= number_format($members) ?></div><div class="stat-sub"><?= $isBarangay ? 'verified accounts' : 'across all barangays' ?></div></div>
  <div class="card stat"><div class="stat-label">Active Programs</div><div class="stat-value" style="color:#C99400"><?= $activePrograms ?></div><div class="stat-sub">planned or ongoing</div></div>
  <div class="card stat"><div class="stat-label">Activities Completed</div><div class="stat-value"><?= count($held) ?>/<?= count($acts) ?></div><div class="stat-sub">CY <?= $year ?> implementation</div></div>
  <div class="card stat"><div class="stat-label">Average Evaluation</div><div class="stat-value blue"><?= $avg ? number_format($avg, 1) : '—' ?><small>/5</small></div><div class="stat-sub">all rated questions</div></div>
</div>

<div class="grid grid-2 mb">
  <div class="card card-pad">
    <div class="section-title"><?= $isBarangay ? 'Attendance Rate by Activity' : 'Attendance Rate by Barangay' ?></div>
    <div class="section-sub mb">Recorded attendance against the target of each activity held</div>
    <?php foreach ($attendance as $r): ?>
      <div class="hbar-row"><span class="muted" title="<?= e($r['label']) ?>" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($r['label']) ?></span><?= bar((float) $r['rate'], $r['rate'] < 50 ? 'red' : 'blue') ?><span class="num"><?= (int) $r['rate'] ?>%</span></div>
    <?php endforeach; ?>
    <?php if (!$attendance): ?><div class="empty">No activities held in <?= $year ?> yet.</div><?php endif; ?>
  </div>
  <div class="card card-pad">
    <div class="section-title">Participation Trend by Quarter</div>
    <div class="section-sub mb">Attendance as a share of targets, per quarter</div>
    <?php if (count($validTrend) >= 1): $w = 360; $h = 90; $pts = [];
        foreach ($trend as $i => $t) { if ($t['value'] === null) continue; $pts[] = [40 + $i / 3 * ($w - 70), 10 + ($h - 10) - ($t['value'] / 100) * ($h - 10), $t]; } ?>
      <svg viewBox="0 0 <?= $w ?> <?= $h + 30 ?>" style="width:100%" role="img" aria-label="Participation trend by quarter">
        <line x1="30" y1="<?= $h ?>" x2="<?= $w - 20 ?>" y2="<?= $h ?>" stroke="var(--border-default)"/>
        <?php if (count($pts) > 1): ?><polyline fill="none" stroke="#1B5AC4" stroke-width="2" points="<?= e(implode(' ', array_map(fn ($p) => round($p[0], 1) . ',' . round($p[1], 1), $pts))) ?>"/><?php endif; ?>
        <?php foreach ($pts as [$x, $y, $t]): ?><circle cx="<?= round($x, 1) ?>" cy="<?= round($y, 1) ?>" r="4" fill="#1B5AC4"/><text x="<?= round($x, 1) ?>" y="<?= round($y - 8, 1) ?>" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="9" fill="var(--text-primary)"><?= $t['value'] ?>%</text><?php endforeach; ?>
        <?php foreach ($trend as $i => $t): ?><text x="<?= round(40 + $i / 3 * ($w - 70), 1) ?>" y="<?= $h + 16 ?>" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="9" fill="var(--text-secondary)"><?= $t['q'] ?><?= $t['value'] === null ? ' —' : '' ?></text><?php endforeach; ?>
      </svg>
    <?php else: ?><div class="empty">No attendance recorded yet.</div><?php endif; ?>
  </div>
</div>

<div class="card table-wrap mb">
  <div class="card-head"><div class="section-title">Average Evaluation Scores <?= $isBarangay ? 'by Activity' : 'by Centre' ?></div><span class="mono small muted">Scale: 1 (lowest) to 5 (highest)</span></div>
  <table class="table">
    <thead><tr><th><?= $isBarangay ? 'Barangay Activity' : 'Centre of Participation' ?></th><th class="num">Program Quality</th><th class="num">Implementer Quality</th><th class="num">Perceived Benefits</th><th class="num">Overall</th><th class="num">Responses</th></tr></thead>
    <tbody>
    <?php foreach ($evalBy as $g => $v): $secs = array_filter([$v['Program Quality'] ?? null, $v['Implementer Quality'] ?? null, $v['Perceived Benefits'] ?? null], fn ($x) => $x !== null); ?>
      <tr><td style="font-weight:500"><?= e($g) ?></td>
        <?php foreach (['Program Quality', 'Implementer Quality', 'Perceived Benefits'] as $s): ?><td class="num"><?= isset($v[$s]) ? number_format($v[$s], 1) : '—' ?></td><?php endforeach; ?>
        <td class="num"><strong class="blue"><?= $secs ? number_format(array_sum($secs) / count($secs), 1) : '—' ?></strong></td><td class="num"><?= (int) $v['_responses'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$evalBy): ?><tr><td colspan="6" class="empty">No evaluations submitted yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="grid grid-2 mb">
  <div class="card card-pad">
    <div class="section-title">CBYDP Completion by Centre</div>
    <div class="section-sub mb">Programs completed or evaluated, per centre of participation</div>
    <?php foreach ($centres as $c): ?>
      <div class="hbar-row"><span class="muted"><?= e($c['label']) ?></span><?= bar((float) $c['value'], 'amber') ?><span class="num"><?= $c['total'] ? $c['value'] . '%' : '—' ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="card table-wrap">
    <div class="card-head"><div><div class="section-title"><?= $isBarangay ? 'Reports Submitted to Federation' : 'Recent Barangay Submissions' ?></div><div class="section-sub"><?= $isBarangay ? 'Submission record for ' . e(current_user()['barangay_name']) : 'Reports received from SK Barangay Officials' ?></div></div></div>
    <table class="table">
      <thead><tr><?php if (!$isBarangay): ?><th>Barangay</th><?php endif; ?><th>Report</th><th>Period</th><th>Received</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($submissions as $s): ?>
        <tr><?php if (!$isBarangay): ?><td style="font-weight:500"><?= e($s['barangay_name']) ?></td><?php endif; ?><td><?= e($s['report_type']) ?></td><td class="sub"><?= e($s['period']) ?></td><td class="sub nowrap"><?= e(fmt_date($s['date_generated'])) ?></td>
          <td><?= strtotime($s['date_generated']) > strtotime('-3 days') ? pill('pending', 'New') : pill('completed', 'Received') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$submissions): ?><tr><td colspan="5" class="empty">No reports submitted yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!$isBarangay): ?>
<div class="card table-wrap">
  <div class="card-head"><div><div class="section-title">Barangay Program Status</div><div class="section-sub">Comparative implementation and evaluation status, CY <?= $year ?></div></div></div>
  <table class="table">
    <thead><tr><th>Barangay</th><th class="num">KK Members</th><th class="num">Programs</th><th class="num">Activities Completed</th><th class="num">Evaluation Rate</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($status as $s): $rate = $s['att'] ? (int) round($s['evals'] / $s['att'] * 100) : null; ?>
      <tr><td style="font-weight:500"><?= e($s['barangay_name']) ?></td><td class="num"><?= (int) $s['members'] ?></td><td class="num"><?= (int) $s['programs'] ?></td><td class="num"><?= (int) $s['done'] ?>/<?= (int) $s['acts'] ?></td>
        <td class="num<?= $rate !== null && $rate < 50 ? ' red' : '' ?>"><?= $rate === null ? '—' : $rate . '%' ?></td>
        <td><?= !$s['acts'] ? pill('planned', 'No activity') : ((int) $s['done'] === (int) $s['acts'] ? pill('completed') : pill('ongoing')) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($isBarangay): ?>
<dialog class="modal" id="dlg-submit">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="modal-head"><div><div class="modal-title">Submit Report to Federation</div><div class="muted small">Records the submission so the federation sees it under Recent Barangay Submissions.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Report type</label><select class="input" name="report_type"><?php foreach (REPORT_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Period covered</label><input class="input" name="period" required maxlength="50" placeholder="e.g. Q3 2026 or October 2026"></div>
    <div class="field"><label>Attach file (optional)</label><input class="input" type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx"></div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Submit</button></div>
  </form>
</dialog>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php';
