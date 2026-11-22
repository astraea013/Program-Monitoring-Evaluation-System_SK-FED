<?php
/** KK member calendar: the scheduled activities of the member's barangay. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$month = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') : date('Y-m');
$first = new DateTime($month . '-01');
$events = activities_overview(my_barangay(), 'a.activity_date BETWEEN ? AND ?', [$first->format('Y-m-01'), $first->format('Y-m-t')]);
$mine = array_flip(array_column(q_all('SELECT activity_id FROM attendance WHERE profile_id = ?', [my_profile_id()]), 'activity_id'));

$title = 'My Calendar';
$nav = 'calendar';
$crumb = 'My Activities · Calendar';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">My Calendar</h1><div class="page-sub">Events for Brgy. <?= e(current_user()['barangay_name']) ?> · <?= e($first->format('F Y')) ?></div></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="?m=<?= e((clone $first)->modify('-1 month')->format('Y-m')) ?>">← Prev</a>
    <a class="btn btn-secondary" href="?m=<?= date('Y-m') ?>">This month</a>
    <a class="btn btn-secondary" href="?m=<?= e((clone $first)->modify('+1 month')->format('Y-m')) ?>">Next →</a>
  </div>
</div>
<?php foreach ($events as $ev): $c = CENTRE_COLOURS[$ev['centre']] ?? '#5A6478'; ?>
  <div class="card mb" style="display:flex;gap:16px;padding:14px 16px;border-left:3px solid <?= $c ?>">
    <div style="text-align:center;min-width:48px"><div class="mono" style="font-size:22px;line-height:1"><?= e(date('j', strtotime($ev['activity_date']))) ?></div><div class="mono small muted"><?= e(date('M', strtotime($ev['activity_date']))) ?></div></div>
    <div style="flex:1">
      <div style="font-weight:600;font-size:14px"><?= e($ev['title']) ?></div>
      <div class="small muted">⏰ <?= e(fmt_time($ev['start_time'])) ?><?= $ev['end_time'] ? '–' . e(fmt_time($ev['end_time'])) : '' ?> · 📍 <?= e($ev['venue'] ?: 'Venue to be announced') ?></div>
      <div class="row" style="gap:6px;margin-top:6px"><span class="pill" style="background:<?= $c ?>1A;color:<?= $c ?>"><?= e($ev['centre']) ?></span><?= isset($mine[$ev['activity_id']]) ? pill('present', 'Attended') : '' ?></div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$events): ?><div class="card empty">No events scheduled for your barangay this month.</div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
