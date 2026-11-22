<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$certs = q_all('SELECT c.*, a.title, a.activity_date FROM certificates c JOIN activities a ON a.activity_id = c.activity_id WHERE c.profile_id = ? ORDER BY c.date_issued DESC', [my_profile_id()]);

$title = 'My Certificates';
$nav = 'certificates';
$crumb = 'My Activities · My Certificates';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">My Certificates</h1><div class="page-sub">E-certificates earned after attendance and evaluation · <?= count($certs) ?> issued</div></div></div>
<div class="grid grid-3">
  <?php foreach ($certs as $c): ?>
    <div class="card card-pad" style="border-top:3px solid var(--sun)">
      <div class="record-id"><?= e($c['certificate_no']) ?></div>
      <div class="section-title" style="margin:6px 0 2px"><?= e($c['title']) ?></div>
      <div class="small muted mb">Activity on <?= e(fmt_date($c['activity_date'])) ?> · Issued <?= e(fmt_date($c['date_issued'])) ?></div>
      <a class="btn btn-secondary btn-block" href="<?= e(url('member/certificate.php?id=' . $c['certificate_id'])) ?>">View &amp; download</a>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$certs): ?><div class="card empty">No certificates yet. Attend an activity, scan its QR code and submit the evaluation to receive one.</div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
