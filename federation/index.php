<?php
/** SK Federation dashboard — consolidated across every barangay, read from what the barangays encoded. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_FEDERATION);

$members = (int) q_val('SELECT COUNT(*) FROM users WHERE role_id = ? AND verification_status = "verified" AND is_active = 1', [ROLE_MEMBER]);
$newMembers = (int) q_val('SELECT COUNT(*) FROM users WHERE role_id = ? AND verification_status = "verified" AND date_verified >= DATE_FORMAT(CURDATE(), "%Y-%m-01")', [ROLE_MEMBER]);
$activePrograms = (int) q_val("SELECT COUNT(*) FROM programs WHERE status IN ('planned','ongoing')");
$brgyWithPrograms = (int) q_val('SELECT COUNT(DISTINCT barangay_id) FROM programs');
$upcoming = (int) q_val('SELECT COUNT(*) FROM activities WHERE activity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)');
$avg = average_rating(null);
$centres = centre_completion(null);
$alloc = current_allocation();
$pendingProposals = (int) q_val('SELECT COUNT(*) FROM budget_proposals WHERE prop_status = "pending"');

// Recent submissions: barangay reports and budget proposals, newest first.
$subs = q_all('(SELECT r.report_type AS what, r.period AS detail, b.barangay_name, r.date_generated AS at, "federation/reports.php" AS link
                  FROM reports r JOIN barangays b ON b.barangay_id = r.barangay_id)
               UNION ALL
               (SELECT CONCAT("Budget proposal ", bp.proposal_code), bp.title, b.barangay_name, bp.date_submitted, CONCAT("federation/budget.php?proposal=", bp.proposal_id)
                  FROM budget_proposals bp JOIN barangays b ON b.barangay_id = bp.barangay_id WHERE bp.date_submitted IS NOT NULL)
               ORDER BY at DESC LIMIT 6');
$status = q_all("SELECT b.barangay_name,
                        (SELECT COUNT(*) FROM users u WHERE u.barangay_id = b.barangay_id AND u.role_id = ? AND u.verification_status = 'verified') members,
                        (SELECT COUNT(*) FROM programs p WHERE p.barangay_id = b.barangay_id) programs,
                        (SELECT COUNT(*) FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id AND YEAR(a.activity_date) = YEAR(CURDATE())) acts,
                        (SELECT COUNT(*) FROM activities a JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id AND YEAR(a.activity_date) = YEAR(CURDATE()) AND a.activity_date <= CURDATE()) done,
                        (SELECT COUNT(*) FROM attendance at JOIN activities a ON a.activity_id = at.activity_id JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id) att,
                        (SELECT COUNT(*) FROM evaluation_responses r JOIN evaluation_forms f ON f.form_id = r.form_id JOIN activities a ON a.activity_id = f.activity_id JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = b.barangay_id) evals
                   FROM barangays b ORDER BY programs DESC, b.barangay_name", [ROLE_MEMBER]);

$title = 'Federation Dashboard';
$nav = 'dashboard';
$crumb = 'SK Federation · Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Federation Dashboard</h1><div class="page-sub">SK Federation <?= e(setting('municipality', 'Nasipit')) ?> · CY <?= date('Y') ?></div></div>
  <?php if ($pendingProposals): ?><a class="btn btn-primary" href="<?= e(url('federation/budget.php')) ?>"><?= $pendingProposals ?> budget proposal<?= $pendingProposals > 1 ? 's' : '' ?> to review →</a><?php endif; ?>
</div>

<div class="grid grid-4 mb-lg">
  <div class="card stat"><div class="stat-label">Registered KK Members</div><div class="stat-value blue"><?= number_format($members) ?></div><div class="stat-sub">+<?= $newMembers ?> this month</div></div>
  <div class="card stat"><div class="stat-label">Active Programs</div><div class="stat-value" style="color:#C99400"><?= $activePrograms ?></div><div class="stat-sub"><?= $brgyWithPrograms ?> barangays with programs</div></div>
  <div class="card stat"><div class="stat-label">Upcoming Activities</div><div class="stat-value"><?= $upcoming ?></div><div class="stat-sub">next 7 days</div></div>
  <div class="card stat"><div class="stat-label">Avg Evaluation Score</div><div class="stat-value blue"><?= $avg ? number_format($avg, 1) : '—' ?><small>/5</small></div><div class="stat-sub">across all centres</div></div>
</div>

<div class="grid grid-2 mb">
  <div class="card card-pad">
    <div class="spread"><div><div class="section-title">CBYDP Completion by Centre</div><div class="section-sub">Programs completed per centre of participation</div></div><?= pill('ongoing', 'CY ' . date('Y')) ?></div>
    <div class="row mt" style="align-items:center;gap:20px;flex-wrap:nowrap">
      <div style="flex:0 0 230px;max-width:50%"><?= sun_chart($centres) ?></div>
      <div class="legend-list" style="flex:1"><?php foreach ($centres as $c): ?><div class="spread"><span><i style="display:inline-block;width:8px;height:8px;background:var(--sun);border-radius:1px;margin-right:6px"></i><?= e($c['label']) ?></span><span><?= $c['total'] ? $c['value'] . '%' : '—' ?></span></div><?php endforeach; ?></div>
    </div>
  </div>
  <div class="card" style="overflow:hidden">
    <div class="card-head"><div class="section-title">Recent Submissions</div><a class="small mono" href="<?= e(url('federation/reports.php')) ?>">View all →</a></div>
    <?php foreach ($subs as $s): $new = strtotime($s['at']) > strtotime('-3 days'); ?>
      <a class="row" href="<?= e(url($s['link'])) ?>" style="padding:13px 18px;border-bottom:1px solid var(--border-row);text-decoration:none;color:inherit;flex-wrap:nowrap;align-items:flex-start">
        <span style="width:7px;height:7px;border-radius:50%;margin-top:6px;flex-shrink:0;background:<?= $new ? 'var(--sun)' : 'var(--border-default)' ?>"></span>
        <span style="flex:1;min-width:0"><span style="display:block;font-weight:<?= $new ? 600 : 400 ?>"><?= e($s['what']) ?></span><span class="mono small muted"><?= e($s['barangay_name']) ?> · <?= e($s['detail']) ?></span></span>
        <span class="mono small muted nowrap"><?= e(fmt_date($s['at'], 'M j')) ?></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$subs): ?><div class="empty">No submissions from barangays yet.</div><?php endif; ?>
    <?php if ($alloc): ?><div class="card-foot"><div class="spread small"><span class="muted"><?= e($alloc['period']) ?> allocation remaining</span><span class="mono"><?= e(peso0($alloc['remaining_amount'])) ?> of <?= e(peso0($alloc['total_amount'])) ?></span></div><div style="margin-top:6px"><?= bar(100 - (float) pct($alloc['remaining_amount'], $alloc['total_amount'], 1), 'green') ?></div></div><?php endif; ?>
  </div>
</div>

<div class="card table-wrap">
  <div class="card-head"><div class="section-title">Barangay Program Status</div></div>
  <table class="table">
    <thead><tr><th>Barangay</th><th class="num">KK Members</th><th class="num">Programs</th><th class="num">Activities Done</th><th class="num">Evaluation Rate</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($status as $s): $rate = $s['att'] ? (int) round($s['evals'] / $s['att'] * 100) : null; ?>
      <tr><td style="font-weight:500"><?= e($s['barangay_name']) ?></td><td class="num"><?= (int) $s['members'] ?></td><td class="num"><?= (int) $s['programs'] ?></td><td class="num"><?= (int) $s['done'] ?>/<?= (int) $s['acts'] ?></td>
        <td class="num<?= $rate !== null && $rate < 50 ? ' red' : '' ?>"><?= $rate === null ? '—' : $rate . '%' ?></td>
        <td><?= !$s['programs'] ? pill('planned', 'No programs') : (!$s['acts'] ? pill('planned') : ((int) $s['done'] === (int) $s['acts'] ? pill('completed') : pill('ongoing'))) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php';
