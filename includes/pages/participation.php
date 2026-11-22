<?php
/**
 * Participation & Eligibility, computed from stored QR attendance.
 * The system reports standing; SK officials decide what it means.
 */
$isBarangay = role() === ROLE_BARANGAY;
$scope = $isBarangay ? my_barangay() : (get_int('barangay') ?: null);
$year = get_int('year') ?: (int) date('Y');
$min = (int) setting('eligibility_min_activities', '4');
$rows = participation_rows($scope, $year);
foreach ($rows as &$r) {
    $r['eligible'] = (int) $r['attended'] >= $min;
}
unset($r);
$filter = get('show');
$shown = array_values(array_filter($rows, fn ($r) => $filter === '' || ($filter === 'eligible') === $r['eligible']));
if (get('export') === 'csv') {
    send_csv("participation-$year.csv", ['KK ID', 'Name', 'Barangay', 'QR-scanned activities', 'Activities held', 'Rate %', 'Manual (not counted)', 'Last attended', 'Eligibility'],
        array_map(fn ($r) => [kk_id((int) $r['profile_id'], $r['date_encoded']), $r['full_name'], $r['barangay_name'], $r['attended'], $r['held'], pct($r['attended'], $r['held']), $r['manual_count'], $r['last_attended'], $r['eligible'] ? 'Eligible' : 'Not yet eligible'], $shown));
}
$eligible = count(array_filter($rows, fn ($r) => $r['eligible']));
$barangays = $isBarangay ? [] : q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');

$title = 'Participation & Eligibility';
$nav = 'participation';
$crumb = ($isBarangay ? 'Programs' : 'Monitoring') . ' · Participation';
require APP_ROOT . '/includes/header.php';
$qs = fn (array $o) => '?' . http_build_query(array_merge(['year' => $year, 'barangay' => $scope ?: null, 'show' => $filter ?: null], $o));
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Participation &amp; Eligibility</h1>
    <div class="page-sub">Based on scanned QR attendance only · Jan–Dec <?= $year ?></div>
  </div>
  <div class="page-actions">
    <form method="get" class="row">
      <?php if (!$isBarangay): ?><select class="input" name="barangay" data-autosubmit style="width:auto"><option value="0">All barangays</option>
        <?php foreach ($barangays as $b): ?><option value="<?= (int) $b['barangay_id'] ?>"<?= $scope === (int) $b['barangay_id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <select class="input" name="year" data-autosubmit style="width:auto"><?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 3; $y--): ?><option<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
    </form>
    <a class="btn btn-secondary" href="<?= e($qs(['export' => 'csv'])) ?>">↓ Export</a>
    <?php if ($isBarangay): ?><a class="btn btn-secondary" href="<?= e(url('barangay/certificates.php')) ?>">Issued certificates</a><?php endif; ?>
  </div>
</div>

<div class="split">
  <div>
    <div class="grid grid-3 mb">
      <a class="card stat" href="<?= e($qs(['show' => null])) ?>" style="text-decoration:none;color:inherit"><div class="stat-label">Total Members</div><div class="stat-value"><?= count($rows) ?></div></a>
      <a class="card stat" href="<?= e($qs(['show' => 'eligible'])) ?>" style="text-decoration:none;color:inherit"><div class="stat-label">Eligible</div><div class="stat-value blue"><?= $eligible ?></div></a>
      <a class="card stat" href="<?= e($qs(['show' => 'not'])) ?>" style="text-decoration:none;color:inherit"><div class="stat-label">Not Yet Eligible</div><div class="stat-value red"><?= count($rows) - $eligible ?></div></a>
    </div>
    <div class="card table-wrap">
      <table class="table">
        <thead><tr><th>KK ID</th><th>Member Name</th><?php if (!$isBarangay): ?><th>Barangay</th><?php endif; ?><th>Activities Attended</th><th>Rate</th><th>Last Attended</th><th>Eligibility</th></tr></thead>
        <tbody>
        <?php foreach ($shown as $r): $rate = (float) pct($r['attended'], $r['held']); ?>
          <tr>
            <td class="record-id nowrap"><?= e(kk_id((int) $r['profile_id'], $r['date_encoded'])) ?></td>
            <td style="font-weight:500"><?= e($r['full_name']) ?><?= $r['user_id'] ? '' : ' <span class="muted small">(no account)</span>' ?></td>
            <?php if (!$isBarangay): ?><td class="muted"><?= e($r['barangay_name']) ?></td><?php endif; ?>
            <td class="mono"><strong><?= (int) $r['attended'] ?></strong>/<?= (int) $r['held'] ?><?= (int) $r['manual_count'] ? ' <span class="muted small" title="Manual entries are not counted">+' . (int) $r['manual_count'] . ' manual</span>' : '' ?></td>
            <td style="min-width:120px"><div class="row" style="gap:8px;flex-wrap:nowrap"><div style="flex:1"><?= bar($rate, $r['eligible'] ? 'blue' : 'red') ?></div><span class="mono small"><?= (int) $rate ?>%</span></div></td>
            <td class="sub"><?= e(fmt_date($r['last_attended'])) ?></td>
            <td><?= pill($r['eligible'] ? 'eligible' : 'not eligible', $r['eligible'] ? 'Eligible' : 'Not Eligible') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$shown): ?><tr><td colspan="7" class="empty">No members to show.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <aside class="card panel">
    <div class="label-mono">Eligibility Rule</div>
    <div class="card" style="padding:12px 14px">
      <div class="small" style="font-weight:600;margin-bottom:6px">Current Settings</div>
      <div class="kv"><span>Minimum activities</span><span><?= $min ?> per year</span></div>
      <div class="kv"><span>Counting window</span><span>Jan–Dec <?= $year ?></span></div>
      <div class="kv"><span>Method</span><span>Scanned QR only</span></div>
    </div>
    <div class="note-box mt"><div class="label-mono" style="margin-bottom:4px">Important Note</div>This eligibility report is computed automatically from QR scan records. The SK officials — not this system — decide what the results mean and what action to take. The system does not approve or reject anyone.</div>
    <div class="scope-banner mt" style="margin-bottom:0">Only QR-scanned attendance counts toward eligibility. Manually encoded attendance is recorded but not counted.</div>
    <p class="muted small mt">The minimum is set by the Administrator under Barangays &amp; Settings.</p>
  </aside>
</div>
<?php require APP_ROOT . '/includes/footer.php';
