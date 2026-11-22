<?php
/**
 * Activity calendar. Barangay: its own activities. Federation: all
 * barangays in one view, so conflicts and gaps are visible. Venue
 * conflicts (same venue, same date, overlapping time) are flagged.
 */
$isBarangay = role() === ROLE_BARANGAY;
$scope = $isBarangay ? my_barangay() : (get_int('barangay') ?: null);

$month = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') : date('Y-m');
$first = new DateTime($month . '-01');
$daysIn = (int) $first->format('t');
$startDow = (int) $first->format('w');
$acts = activities_overview($scope, 'a.activity_date BETWEEN ? AND ?', [$first->format('Y-m-01'), $first->format('Y-m-t')]);

$byDay = [];
foreach ($acts as $a) {
    $byDay[(int) date('j', strtotime($a['activity_date']))][] = $a;
}
$conflictIds = [];
foreach ($byDay as $list) {
    foreach ($list as $i => $x) {
        foreach (array_slice($list, $i + 1) as $y) {
            $sameVenue = $x['venue'] && strcasecmp(trim($x['venue']), trim($y['venue'] ?? '')) === 0;
            $overlap = !$x['start_time'] || !$y['start_time'] || !$x['end_time'] || !$y['end_time'] || ($x['start_time'] < $y['end_time'] && $y['start_time'] < $x['end_time']);
            if ($sameVenue && $overlap) {
                $conflictIds[$x['activity_id']] = $conflictIds[$y['activity_id']] = true;
            }
        }
    }
}
$today = date('Y-m') === $month ? (int) date('j') : 0;
$sel = get_int('day') ?: ($today ?: (array_key_first($byDay) ?? 0));
$selActs = $byDay[$sel] ?? [];
$prev = (clone $first)->modify('-1 month')->format('Y-m');
$next = (clone $first)->modify('+1 month')->format('Y-m');
$barangays = $isBarangay ? [] : q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');
$qs = fn (array $o) => '?' . http_build_query(array_merge(['m' => $month, 'barangay' => $scope ?: null], $o));

$title = 'Activity Calendar';
$nav = 'calendar';
$crumb = ($isBarangay ? 'Programs' : 'Monitoring') . ' · Calendar';
require APP_ROOT . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Activity Calendar</h1>
    <div class="page-sub"><?= e($first->format('F Y')) ?> · <?= $isBarangay ? e(current_user()['barangay_name']) : ($scope ? 'One barangay' : 'All barangays') ?> · <?= count($acts) ?> activities</div>
  </div>
  <div class="page-actions">
    <?php if (!$isBarangay): ?>
      <form method="get"><input type="hidden" name="m" value="<?= e($month) ?>"><select class="input" name="barangay" data-autosubmit style="width:auto"><option value="0">All barangays</option>
        <?php foreach ($barangays as $b): ?><option value="<?= (int) $b['barangay_id'] ?>"<?= $scope === (int) $b['barangay_id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option><?php endforeach; ?></select></form>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e($qs(['m' => $prev, 'day' => null])) ?>">← <?= e(date('M', strtotime($prev . '-01'))) ?></a>
    <a class="btn btn-secondary" href="<?= e($qs(['m' => date('Y-m'), 'day' => null])) ?>">Today</a>
    <a class="btn btn-secondary" href="<?= e($qs(['m' => $next, 'day' => null])) ?>"><?= e(date('M', strtotime($next . '-01'))) ?> →</a>
    <?php if ($isBarangay): ?><a class="btn btn-primary" href="<?= e(url('barangay/programs.php')) ?>">+ Schedule activity</a><?php endif; ?>
  </div>
</div>

<div class="split">
  <div>
    <div class="card mb" style="overflow:hidden">
      <div class="calendar">
        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?><div class="cal-dow"><?= $d ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $startDow; $i++): ?><div class="cal-day blank"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $daysIn; $d++):
            $list = $byDay[$d] ?? [];
            $conf = (bool) array_filter($list, fn ($a) => isset($conflictIds[$a['activity_id']])); ?>
          <a class="cal-day<?= $d === $sel ? ' selected' : '' ?><?= $d === $today ? ' today' : '' ?><?= $conf ? ' conflict' : '' ?>" href="<?= e($qs(['day' => $d])) ?>">
            <span class="cal-num"><?= $d ?></span><?= $conf ? ' <span class="red small">⚠</span>' : '' ?>
            <?php foreach (array_slice($list, 0, 2) as $a): $c = CENTRE_COLOURS[$a['centre']] ?? '#5A6478'; ?>
              <div class="cal-event" style="border-color:<?= $c ?>;color:<?= $c ?>;background:<?= $c ?>1A"><?= e($a['title']) ?></div>
            <?php endforeach; ?>
            <?php if (count($list) > 2): ?><div class="mono" style="font-size:9px;color:var(--text-secondary)">+<?= count($list) - 2 ?> more</div><?php endif; ?>
          </a>
        <?php endfor; ?>
        <?php for ($i = ($startDow + $daysIn) % 7; $i && $i < 7; $i++): ?><div class="cal-day blank"></div><?php endfor; ?>
      </div>
    </div>
    <div class="card card-pad">
      <div class="label-mono">Centre of Participation</div>
      <div class="legend"><?php foreach (CENTRE_COLOURS as $c => $col): ?><span><i style="background:<?= $col ?>"></i><?= e($c) ?></span><?php endforeach; ?></div>
    </div>
  </div>

  <aside class="card panel">
    <?php if (!$sel): ?><div class="empty">Select a date to see activities.</div><?php else: ?>
      <div class="panel-sub"><?= e($first->format('F') . ' ' . $sel . ', ' . $first->format('Y')) ?></div>
      <div class="panel-title" style="margin:4px 0 14px"><?= count($selActs) ?> <?= count($selActs) === 1 ? 'Activity' : 'Activities' ?></div>
      <?php if (array_filter($selActs, fn ($a) => isset($conflictIds[$a['activity_id']]))): ?>
        <div class="alert alert-error"><strong>Venue conflict</strong> — two activities share the same venue at the same time on this date.</div>
      <?php endif; ?>
      <?php foreach ($selActs as $a): $c = CENTRE_COLOURS[$a['centre']] ?? '#5A6478'; ?>
        <div class="card" style="padding:12px 14px;margin-bottom:10px;border-left:3px solid <?= $c ?>">
          <div style="font-weight:600;font-size:13px;margin-bottom:4px"><?= e($a['title']) ?></div>
          <div class="small muted">🕐 <?= e(fmt_time($a['start_time'])) ?><?= $a['end_time'] ? '–' . e(fmt_time($a['end_time'])) : '' ?></div>
          <div class="small muted">📍 <?= e($a['venue'] ?: 'Venue to be announced') ?></div>
          <div class="small muted" style="margin-bottom:6px">🏘️ <?= e($a['barangay_name']) ?> · <?= e($a['program_code']) ?></div>
          <div class="row" style="gap:6px"><?= pill('planned', $a['centre']) ?><?= isset($conflictIds[$a['activity_id']]) ? pill('rejected', 'Conflict') : '' ?>
            <?php if ($isBarangay): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('barangay/qr.php?activity=' . $a['activity_id'])) ?>">QR</a><?php endif; ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$selActs): ?><p class="muted small">Nothing scheduled on this date.</p><?php endif; ?>
    <?php endif; ?>
  </aside>
</div>
<?php require APP_ROOT . '/includes/footer.php';
