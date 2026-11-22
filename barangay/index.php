<?php
/** SK Barangay Official dashboard — one barangay only. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_BARANGAY);
$b = my_barangay();
$name = current_user()['barangay_name'];

$members = (int) q_val('SELECT COUNT(*) FROM users WHERE barangay_id = ? AND role_id = ? AND verification_status = "verified" AND is_active = 1', [$b, ROLE_MEMBER]);
$newMembers = (int) q_val('SELECT COUNT(*) FROM users WHERE barangay_id = ? AND role_id = ? AND verification_status = "verified" AND date_verified >= DATE_FORMAT(CURDATE(), "%Y-%m-01")', [$b, ROLE_MEMBER]);
$activePrograms = (int) q_val("SELECT COUNT(*) FROM programs WHERE barangay_id = ? AND status IN ('planned','ongoing')", [$b]);
$next = q_one('SELECT a.title, a.activity_date FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = ? AND a.activity_date >= CURDATE() ORDER BY a.activity_date, a.start_time LIMIT 1', [$b]);
$avg = average_rating($b);
$centres = centre_completion($b);
$recent = activities_overview($b, 'a.activity_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)');
$recent = array_slice(array_reverse($recent), 0, 6);

$todo = [
    [(int) q_val('SELECT COUNT(*) FROM users WHERE barangay_id = ? AND role_id = ? AND verification_status = "pending"', [$b, ROLE_MEMBER]), 'KK registrations waiting for verification', 'barangay/verify.php'],
    [(int) q_val('SELECT COUNT(*) FROM equipment_requests r JOIN inventory_items i ON i.item_id = r.item_id WHERE i.barangay_id = ? AND r.req_status = "pending"', [$b]), 'Equipment requests to review', 'barangay/inventory.php'],
    [(int) q_val('SELECT COUNT(*) FROM budget_proposals WHERE barangay_id = ? AND prop_status = "returned"', [$b]), 'Budget proposals returned for revision', 'barangay/budget.php'],
    [(int) q_val('SELECT COUNT(*) FROM budget_proposals WHERE barangay_id = ? AND prop_status = "pending"', [$b]), 'Budget proposals awaiting federation review', 'barangay/budget.php'],
    [(int) q_val('SELECT COUNT(*) FROM feedback f JOIN youth_profiles yp ON yp.profile_id = f.profile_id WHERE yp.barangay_id = ? AND f.reply IS NULL', [$b]), 'Member feedback without a reply', 'barangay/content.php?tab=feedback'],
    [(int) q_val('SELECT COUNT(*) FROM inventory_items WHERE barangay_id = ? AND stock_status <> "available"', [$b]), 'Inventory items low or out of stock', 'barangay/inventory.php?tab=inventory'],
];
$budget = q_one('SELECT COALESCE(SUM(bp.approved_amount), 0) approved, (SELECT COALESCE(SUM(pb.utilised_amount), 0) FROM program_budgets pb JOIN budget_proposals x ON x.proposal_id = pb.proposal_id WHERE x.barangay_id = ? AND x.prop_status = "approved" AND x.allocation_id = ?) utilised
                   FROM budget_proposals bp WHERE bp.barangay_id = ? AND bp.prop_status = "approved" AND bp.allocation_id = ?', [$b, $alloc = (int) (current_allocation()['allocation_id'] ?? 0), $b, $alloc]);

$title = 'Dashboard';
$nav = 'dashboard';
$crumb = 'SK Barangay · Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Dashboard — Brgy. <?= e($name) ?></h1><div class="page-sub">SK Barangay <?= e($name) ?> · CY <?= date('Y') ?><?= is_chairman() ? ' · You are the designated SK Chairman' : '' ?></div></div>
</div>

<div class="grid grid-4 mb-lg">
  <div class="card stat"><div class="stat-label">KK Members (Brgy.)</div><div class="stat-value blue"><?= $members ?></div><div class="stat-sub">+<?= $newMembers ?> this month</div></div>
  <div class="card stat"><div class="stat-label">Active Programs</div><div class="stat-value" style="color:#C99400"><?= $activePrograms ?></div><div class="stat-sub">this barangay only</div></div>
  <div class="card stat"><div class="stat-label">Next Activity</div><div class="stat-value"><?php if ($next): $d = (int) (new DateTime('today'))->diff(new DateTime($next['activity_date']))->days; ?><?= $d === 0 ? 'Today' : $d . ' day' . ($d === 1 ? '' : 's') ?><?php else: ?>—<?php endif; ?></div><div class="stat-sub"><?= e($next['title'] ?? 'none scheduled') ?></div></div>
  <div class="card stat"><div class="stat-label">Avg Evaluation Score</div><div class="stat-value blue"><?= $avg ? number_format($avg, 1) : '—' ?><small>/5</small></div><div class="stat-sub">all programs</div></div>
</div>

<div class="grid grid-2 mb">
  <div class="card card-pad">
    <div class="section-title">Brgy. <?= e($name) ?> — CBYDP Progress</div>
    <div class="section-sub mb">Completion rate per centre of participation</div>
    <div class="row" style="align-items:center;gap:16px;flex-wrap:nowrap">
      <div style="flex:0 0 220px;max-width:50%"><?= sun_chart($centres) ?></div>
      <div class="legend-list" style="flex:1"><?php foreach ($centres as $c): ?><div class="spread"><span><?= e($c['label']) ?></span><span><?= $c['total'] ? $c['value'] . '%' : '—' ?></span></div><?php endforeach; ?></div>
    </div>
  </div>
  <div class="card" style="overflow:hidden">
    <div class="card-head"><div><div class="section-title">Pending Actions</div><div class="section-sub">Items in your barangay that need an SK official</div></div></div>
    <?php foreach ($todo as [$n, $label, $href]): ?>
      <a href="<?= e(url($href)) ?>" class="spread" style="padding:11px 18px;border-bottom:1px solid var(--border-row);text-decoration:none;color:inherit">
        <span style="font-size:12.5px"><?= e($label) ?></span><?= $n ? pill('pending', (string) $n) : pill('completed', '0') ?>
      </a>
    <?php endforeach; ?>
    <div class="card-foot">
      <div class="spread small"><span class="muted">Budget utilised (current period)</span><span class="mono"><?= e(peso0($budget['utilised'])) ?> / <?= e(peso0($budget['approved'])) ?></span></div>
      <div style="margin-top:6px"><?= bar((float) pct($budget['utilised'], $budget['approved'], 1)) ?></div>
    </div>
  </div>
</div>

<div class="card card-pad">
  <div class="spread mb"><div class="section-title">Recent Activities — <?= e($name) ?></div><a class="small" href="<?= e(url('barangay/calendar.php')) ?>">Calendar →</a></div>
  <?php foreach ($recent as $i => $a): $past = $a['activity_date'] < date('Y-m-d'); ?>
    <div class="spread" style="padding:10px 0;<?= $i ? 'border-top:1px solid var(--border-default)' : '' ?>">
      <div><div style="font-weight:500"><?= e($a['title']) ?></div><div class="mono small muted"><?= e($a['centre']) ?> · <?= e(fmt_date($a['activity_date'])) ?></div></div>
      <div class="row" style="gap:10px"><?php if ($past || (int) $a['attendance_count']): ?><span class="mono small muted"><?= (int) $a['attendance_count'] ?>/<?= (int) $a['target_count'] ?></span><?php endif; ?>
        <?= $past ? ((int) $a['response_count'] ? pill('evaluated') : pill('completed')) : pill('planned') ?>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('barangay/qr.php?activity=' . $a['activity_id'])) ?>">QR</a></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$recent): ?><p class="muted small">No activities in the last 60 days or upcoming. <a href="<?= e(url('barangay/programs.php')) ?>">Schedule one</a>.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
