<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$rows = q_all('SELECT x.*, u.role_id, b.barangay_name FROM announcements x JOIN users u ON u.user_id = x.posted_by LEFT JOIN barangays b ON b.barangay_id = x.barangay_id
                WHERE x.barangay_id = ? OR x.barangay_id IS NULL ORDER BY x.date_posted DESC', [my_barangay()]);
$title = 'Announcements';
$nav = 'announcements';
$crumb = 'Information · Announcements';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Announcements</h1><div class="page-sub">From the SK Federation and SK <?= e(current_user()['barangay_name']) ?></div></div></div>
<?php foreach ($rows as $a): ?>
  <div class="card card-pad mb">
    <div class="spread"><div class="section-title"><?= e($a['title']) ?></div><?= pill((int) $a['role_id'] === ROLE_FEDERATION ? 'completed' : 'planned', (int) $a['role_id'] === ROLE_FEDERATION ? 'SK Federation' : 'SK ' . ($a['barangay_name'] ?? 'Barangay')) ?></div>
    <div class="mono small muted mb"><?= e(fmt_datetime($a['date_posted'])) ?></div>
    <div style="font-size:13px"><?= nl2br(e($a['body'])) ?></div>
  </div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card empty">No announcements yet.</div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
