<?php
/** Issued e-certificates for the barangay's activities. Auto-issued; no approval needed. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once APP_ROOT . '/includes/certificate-view.php';
require_role(ROLE_BARANGAY);
$b = my_barangay();

if ($id = get_int('view')) {
    $cert = cert_by_id($id);
    if (!$cert || (int) $cert['program_barangay'] !== $b) {
        require_barangay_access(null);
    }
    $title = 'E-Certificate ' . $cert['certificate_no'];
    $nav = 'participation';
    $crumb = 'Programs · Certificates';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="page-head no-print"><div><h1 class="page-title">Certificate Preview</h1></div><div class="page-actions"><a class="btn btn-secondary" href="certificates.php">← Back</a><button class="btn btn-primary" data-print>Print</button></div></div>';
    render_certificate($cert);
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$search = get('q');
$params = [$b];
$extra = '';
if ($search !== '') {
    $extra = ' AND (yp.full_name LIKE ? OR c.certificate_no LIKE ? OR a.title LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$certs = q_all('SELECT c.*, yp.full_name, yp.date_encoded, a.title, a.activity_date FROM certificates c
                  JOIN youth_profiles yp ON yp.profile_id = c.profile_id JOIN activities a ON a.activity_id = c.activity_id
                  JOIN programs p ON p.program_id = a.program_id WHERE p.barangay_id = ?' . $extra . ' ORDER BY c.date_issued DESC', $params);
if (get('export') === 'csv') {
    send_csv('certificates-' . date('Ymd') . '.csv', ['Certificate no', 'Member', 'KK ID', 'Activity', 'Activity date', 'Issued'],
        array_map(fn ($c) => [$c['certificate_no'], $c['full_name'], kk_id((int) $c['profile_id'], $c['date_encoded']), $c['title'], $c['activity_date'], $c['date_issued']], $certs));
}

$title = 'Issued Certificates';
$nav = 'participation';
$crumb = 'Programs · Issued Certificates';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Issued Certificates</h1><div class="page-sub">E-Certificates of Cooperation — auto-issued on evaluation submission · <?= count($certs) ?> issued</div></div>
  <div class="page-actions"><form method="get" class="row"><input class="input" name="q" value="<?= e($search) ?>" placeholder="Search name, code or activity" style="width:220px"><button class="btn btn-secondary">Search</button></form><a class="btn btn-secondary" href="?export=csv">↓ Export</a></div>
</div>
<div class="scope-banner">Certificates are issued automatically once a member submits their activity evaluation. No approval is needed, and there is no pending state.</div>
<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>Member Name</th><th>KK ID</th><th>Activity</th><th>Date Issued</th><th>Verification Code</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($certs as $c): ?>
      <tr><td style="font-weight:500"><?= e($c['full_name']) ?></td><td class="record-id"><?= e(kk_id((int) $c['profile_id'], $c['date_encoded'])) ?></td><td class="muted"><?= e($c['title']) ?></td>
        <td class="sub"><?= e(fmt_date($c['date_issued'])) ?></td><td class="mono"><?= e($c['certificate_no']) ?></td>
        <td class="right"><a class="btn btn-secondary btn-sm" href="?view=<?= (int) $c['certificate_id'] ?>">Preview</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$certs): ?><tr><td colspan="6" class="empty">No certificates issued yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php';
