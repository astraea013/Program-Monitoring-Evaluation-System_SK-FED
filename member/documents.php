<?php
/** Reports & documents published to the member portal by the barangay or the federation. Never budget files. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$section = get('s') === 'docs' ? 'docs' : 'reports';
$rows = q_all('SELECT d.*, u.role_id, b.barangay_name FROM documents d JOIN users u ON u.user_id = d.uploaded_by LEFT JOIN barangays b ON b.barangay_id = d.barangay_id
                WHERE d.is_public = 1 AND d.category <> "proposal" AND (d.barangay_id = ? OR d.barangay_id IS NULL) AND ' . ($section === 'reports' ? 'd.category = "report"' : 'd.category <> "report"') . '
                ORDER BY d.date_uploaded DESC', [my_barangay()]);
$accomplishments = $section === 'reports' ? q_all('SELECT ac.*, p.title, p.program_code FROM accomplishments ac JOIN programs p ON p.program_id = ac.program_id WHERE p.barangay_id = ? ORDER BY ac.date_posted DESC', [my_barangay()]) : [];

$title = 'Reports & Documents';
$nav = 'documents';
$crumb = 'Information · Reports & Documents';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Reports &amp; Documents</h1><div class="page-sub">Brgy. <?= e(current_user()['barangay_name']) ?> · Official records</div></div></div>
<div class="tabs"><a class="tab<?= $section === 'reports' ? ' active' : '' ?>" href="?s=reports">Accomplishment Reports</a><a class="tab<?= $section === 'docs' ? ' active' : '' ?>" href="?s=docs">Documents</a></div>
<?php foreach ($rows as $d): ?>
  <div class="card mb" style="display:flex;gap:14px;align-items:center;padding:14px 16px">
    <div style="font-size:22px"><?= $section === 'reports' ? '📊' : '📄' ?></div>
    <div style="flex:1;min-width:0"><div style="font-weight:600"><?= e($d['title']) ?></div>
      <div class="row" style="gap:6px;margin-top:3px"><span class="mono small muted"><?= e(fmt_date($d['date_uploaded'])) ?></span><?= pill('completed', DOC_CATEGORIES[$d['category']] ?? $d['category']) ?></div>
      <div class="small muted">Uploaded by: <?= (int) $d['role_id'] === ROLE_FEDERATION ? 'SK Federation' : 'SK ' . e($d['barangay_name']) ?></div></div>
    <a class="btn btn-secondary btn-sm" href="<?= e(url('file.php?id=' . $d['document_id'])) ?>" target="_blank" rel="noopener">View</a>
  </div>
<?php endforeach; ?>
<?php foreach ($accomplishments as $a): ?>
  <div class="card card-pad mb" style="border-left:3px solid var(--sun)"><div class="spread"><strong><?= e($a['title']) ?></strong><span class="record-id"><?= e($a['program_code']) ?></span></div><div class="small muted"><?= e(fmt_date($a['date_posted'])) ?></div><p style="margin-top:6px;font-size:12.5px"><?= nl2br(e($a['narrative'])) ?></p></div>
<?php endforeach; ?>
<?php if (!$rows && !$accomplishments): ?><div class="card empty">Nothing has been published here yet.</div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
