<?php
/** Own attendance history and standing for succeeding programs. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$pid = my_profile_id();
$year = (int) date('Y');
$rows = q_all('SELECT a.title, a.activity_date, a.venue, at.time_scanned, at.entry_method, b.barangay_name
                 FROM attendance at JOIN activities a ON a.activity_id = at.activity_id JOIN programs p ON p.program_id = a.program_id JOIN barangays b ON b.barangay_id = p.barangay_id
                WHERE at.profile_id = ? ORDER BY at.time_scanned DESC', [$pid]);
$missed = q_all('SELECT a.title, a.activity_date FROM activities a JOIN programs p ON p.program_id = a.program_id
                  WHERE p.barangay_id = ? AND YEAR(a.activity_date) = ? AND a.activity_date < CURDATE()
                    AND NOT EXISTS (SELECT 1 FROM attendance at WHERE at.activity_id = a.activity_id AND at.profile_id = ?) ORDER BY a.activity_date DESC', [my_barangay(), $year, $pid]);
$counted = count(array_filter($rows, fn ($r) => $r['entry_method'] === 'qr_scan' && (int) date('Y', strtotime($r['activity_date'])) === $year));
$min = (int) setting('eligibility_min_activities', '4');

$title = 'My Attendance';
$nav = 'attendance';
$crumb = 'My Activities · My Attendance';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">My Attendance</h1><div class="page-sub">Your verified activity attendance · <?= e(current_user()['barangay_name']) ?></div></div></div>
<div class="grid grid-3 mb">
  <div class="card stat"><div class="stat-label">Activities attended</div><div class="stat-value"><?= count($rows) ?></div><div class="stat-sub">all time</div></div>
  <div class="card stat"><div class="stat-label">Counted this year (QR)</div><div class="stat-value blue"><?= $counted ?><small> / <?= $min ?> needed</small></div></div>
  <div class="card stat"><div class="stat-label">Standing for succeeding programs</div><div class="stat-value" style="font-size:16px;margin-top:6px"><?= $counted >= $min ? pill('eligible', 'Eligible') : pill('not eligible', 'Not yet eligible') ?></div></div>
</div>
<div class="card table-wrap mb">
  <table class="table">
    <thead><tr><th>Activity</th><th>Date</th><th>Check-in</th><th>Method</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td style="font-weight:500"><?= e($r['title']) ?><div class="sub"><?= e($r['barangay_name']) ?> · <?= e($r['venue']) ?></div></td><td class="sub"><?= e(fmt_date($r['activity_date'])) ?></td><td class="sub"><?= e(date('g:i A', strtotime($r['time_scanned']))) ?></td>
        <td><?= $r['entry_method'] === 'manual' ? pill('draft', 'Encoded') : pill('completed', 'QR scan') ?></td><td><?= pill('present', 'Present') ?></td></tr>
    <?php endforeach; ?>
    <?php foreach ($missed as $m): ?>
      <tr><td class="muted"><?= e($m['title']) ?></td><td class="sub"><?= e(fmt_date($m['activity_date'])) ?></td><td class="sub">—</td><td></td><td><?= pill('rejected', 'Absent') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows && !$missed): ?><tr><td colspan="5" class="empty">No attendance yet. Scan the QR code at your next SK activity.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="muted small">Only QR-scanned attendance counts toward eligibility. The SK officials decide what your standing means for succeeding programs.</p>
<?php require __DIR__ . '/../includes/footer.php';
