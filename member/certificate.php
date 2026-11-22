<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once APP_ROOT . '/includes/certificate-view.php';
require_role(ROLE_MEMBER);

$cert = cert_by_id(get_int('id'));
if (!$cert || (int) $cert['profile_id'] !== my_profile_id()) {
    http_response_code(404);
    flash('error', 'Certificate not found.');
    redirect('member/certificates.php');
}
$title = 'E-Certificate ' . $cert['certificate_no'];
$nav = 'certificates';
$crumb = 'My Activities · E-Certificate';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
  <div><h1 class="page-title">E-Certificate of Cooperation</h1><div class="page-sub"><?= e($cert['certificate_no']) ?></div></div>
  <div class="page-actions"><a class="btn btn-secondary" href="<?= e(url('member/certificates.php')) ?>">← My certificates</a><button class="btn btn-primary" data-print>↓ Download / Print</button></div>
</div>
<?php render_certificate($cert); ?>
<p class="muted small no-print" style="text-align:center;margin-top:14px">Choose "Save as PDF" in the print dialog to keep a copy on your phone.</p>
<?php require __DIR__ . '/../includes/footer.php';
