<?php
/** KK member homepage. Note what is absent: there is no budget information anywhere for members. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$b = my_barangay();
$pid = my_profile_id();

$next = q_one('SELECT a.*, p.centre FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = ? AND a.activity_date >= CURDATE() ORDER BY a.activity_date, a.start_time LIMIT 1', [$b]);
$upcoming = activities_overview($b, 'a.activity_date >= CURDATE()');
$announcements = q_all('SELECT x.*, u.role_id FROM announcements x JOIN users u ON u.user_id = x.posted_by WHERE x.barangay_id = ? OR x.barangay_id IS NULL ORDER BY x.date_posted DESC LIMIT 4', [$b]);
$accomplishments = q_all('SELECT ac.*, p.title, b.barangay_name FROM accomplishments ac JOIN programs p ON p.program_id = ac.program_id JOIN barangays b ON b.barangay_id = p.barangay_id ORDER BY ac.date_posted DESC LIMIT 3');
$pendingEvals = q_all('SELECT a.activity_id, a.title FROM attendance at JOIN activities a ON a.activity_id = at.activity_id JOIN evaluation_forms f ON f.activity_id = a.activity_id
                        WHERE at.profile_id = ? AND f.is_open = 1 AND NOT EXISTS (SELECT 1 FROM evaluation_responses r WHERE r.form_id = f.form_id AND r.profile_id = at.profile_id)', [$pid]);
$year = date('Y');
$stats = [
    ['Activities completed this year', (int) q_val('SELECT COUNT(*) FROM activities WHERE YEAR(activity_date) = ? AND activity_date <= CURDATE()', [$year])],
    ['KK members who joined at least one activity', (int) q_val('SELECT COUNT(DISTINCT at.profile_id) FROM attendance at JOIN activities a ON a.activity_id = at.activity_id WHERE YEAR(a.activity_date) = ?', [$year])],
    ['Average evaluation score', ($avg = average_rating(null)) ? number_format($avg, 1) . ' / 5' : '—'],
    ['E-certificates issued', (int) q_val('SELECT COUNT(*) FROM certificates WHERE YEAR(date_issued) = ?', [$year])],
];
$myFeedback = q_all('SELECT * FROM feedback WHERE profile_id = ? ORDER BY date_sent DESC LIMIT 2', [$pid]);

$title = 'Home';
$nav = 'home';
$crumb = 'KK Member · Home';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-lg">
  <h1 class="page-title" style="font-size:22px">Hello, <?= e(explode(' ', display_name())[0]) ?>! 👋</h1>
  <div class="muted">Here's what's happening in the SK of <?= e(current_user()['barangay_name']) ?>.</div>
</div>

<?php foreach ($pendingEvals as $pe): ?>
  <div class="alert alert-warning">You attended <strong><?= e($pe['title']) ?></strong> but have not evaluated it yet. <a href="<?= e(url('member/evaluate.php?activity=' . $pe['activity_id'])) ?>">Evaluate now</a> to receive your e-certificate.</div>
<?php endforeach; ?>

<?php if ($next): $days = (int) (new DateTime('today'))->diff(new DateTime($next['activity_date']))->days; ?>
<div class="card mb" style="background:var(--indigo);color:#fff;border:0;padding:18px 20px;display:flex;justify-content:space-between;align-items:center;gap:16px">
  <div>
    <div class="mono small" style="color:var(--sun);text-transform:uppercase;letter-spacing:.08em">Next activity</div>
    <div style="font-family:var(--font-head);font-size:18px;font-weight:700;margin:3px 0"><?= e($next['title']) ?></div>
    <div class="small" style="color:rgba(255,255,255,.75)">📅 <?= e(fmt_date($next['activity_date'], 'F j, Y')) ?> · <?= e(fmt_time($next['start_time'])) ?> &nbsp; 📍 <?= e($next['venue'] ?: 'Venue to be announced') ?></div>
  </div>
  <div style="text-align:center"><div class="mono" style="font-size:30px;color:var(--sun);line-height:1"><?= $days ?></div><div class="small" style="color:rgba(255,255,255,.7)"><?= $days === 0 ? 'today' : ($days === 1 ? 'day away' : 'days away') ?></div></div>
</div>
<?php endif; ?>

<div class="grid grid-2 mb">
  <div class="card card-pad">
    <div class="spread mb"><div class="section-title">Open Activities</div><a class="small" href="<?= e(url('member/calendar.php')) ?>">Calendar →</a></div>
    <?php foreach (array_slice($upcoming, 0, 4) as $i => $a): ?>
      <div style="<?= $i ? 'border-top:1px solid var(--border-default);' : '' ?>padding:10px 0">
        <div style="font-weight:600"><?= e($a['title']) ?></div>
        <div class="small muted">📅 <?= e(fmt_date($a['activity_date'])) ?> · <?= e($a['venue'] ?: 'Venue TBA') ?></div>
        <div class="row" style="gap:8px;margin-top:4px"><?= pill('planned', $a['centre']) ?><span class="mono small muted"><?= (int) $a['attendance_count'] ?>/<?= (int) $a['target_count'] ?> attended</span></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$upcoming): ?><p class="muted small">No upcoming activities in your barangay yet.</p><?php endif; ?>
  </div>
  <div class="card card-pad">
    <div class="spread mb"><div class="section-title">Announcements</div><a class="small" href="<?= e(url('member/announcements.php')) ?>">All →</a></div>
    <?php foreach ($announcements as $i => $a): ?>
      <div style="<?= $i ? 'border-top:1px solid var(--border-default);' : '' ?>padding:10px 0">
        <div style="font-weight:600"><?= e($a['title']) ?></div>
        <div class="mono small muted"><?= e(fmt_date($a['date_posted'])) ?> · <?= (int) $a['role_id'] === ROLE_FEDERATION ? 'SK Federation' : 'SK Barangay' ?></div>
        <div class="small" style="margin-top:3px"><?= e(mb_strimwidth($a['body'], 0, 160, '…')) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$announcements): ?><p class="muted small">No announcements yet.</p><?php endif; ?>
  </div>
</div>

<div class="card card-pad mb">
  <div class="section-title mb">Federation Accomplishments — <?= e($year) ?></div>
  <div class="grid grid-4">
    <?php foreach ($stats as [$label, $value]): ?><div><div class="stat-value" style="color:var(--blue)"><?= e((string) $value) ?></div><div class="small muted"><?= e($label) ?></div></div><?php endforeach; ?>
  </div>
  <?php foreach ($accomplishments as $ac): ?>
    <div style="border-top:1px solid var(--border-default);padding:10px 0;margin-top:10px"><strong><?= e($ac['title']) ?></strong> <span class="mono small muted">· <?= e($ac['barangay_name']) ?></span><div class="small muted"><?= e(mb_strimwidth($ac['narrative'], 0, 220, '…')) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="card card-pad">
  <div class="spread mb"><div class="section-title">Recommendations</div><a class="btn btn-primary btn-sm" href="<?= e(url('member/feedback.php')) ?>">+ Submit feedback</a></div>
  <?php foreach ($myFeedback as $f): ?>
    <div style="border-top:1px solid var(--border-default);padding:10px 0">
      <div class="small"><?= e($f['message']) ?></div>
      <?= $f['reply'] ? '<div class="note-box" style="margin-top:6px"><strong>SK Official:</strong> ' . e($f['reply']) . '</div>' : pill('pending', 'Awaiting reply') ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$myFeedback): ?><p class="muted small">Share your suggestions with your SK officials. You can send them anonymously.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
