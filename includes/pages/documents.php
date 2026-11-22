<?php
/**
 * Document repository: minutes, resolutions, letters, reports, and the
 * supporting documents attached to budget proposals.
 * Barangay: own barangay's files plus federation-level files.
 * Federation: federation files, and every barangay's when filtered.
 */
$isBarangay = role() === ROLE_BARANGAY;
$base = $isBarangay ? 'barangay/documents.php' : 'federation/documents.php';

if (is_post()) {
    csrf_check();
    if (post('action') === 'upload') {
        $titleIn = post('title');
        $cat = post('category');
        $prog = post_int('program_id') ?: null;
        if ($titleIn === '' || !isset(DOC_CATEGORIES[$cat])) {
            flash('error', 'Enter a document title and choose its type.');
            redirect($base);
        }
        if ($prog && $isBarangay && !q_val('SELECT 1 FROM programs WHERE program_id = ? AND barangay_id = ?', [$prog, my_barangay()])) {
            require_barangay_access(null);
        }
        try {
            $f = store_upload($_FILES['file'] ?? null, 'documents', DOC_TYPES);
            q('INSERT INTO documents (program_id, barangay_id, uploaded_by, title, category, file_path, file_size, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$prog, $isBarangay ? my_barangay() : null, uid(), $titleIn, $cat, $f['path'], $f['size'], ($cat !== 'proposal' && !empty($_POST['is_public'])) ? 1 : 0]);
            audit('DOCUMENT_UPLOADED', 'Uploaded "' . $titleIn . '" (' . $cat . ')');
            flash('success', '"' . $titleIn . '" uploaded.');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect($base . '?cat=' . urlencode($cat));
    }
    if (post('action') === 'delete') {
        $id = post_int('document_id');
        // Only the owning level can delete: a barangay its own files, the federation federation-level files.
        $doc = q_one('SELECT * FROM documents WHERE document_id = ? AND ' . ($isBarangay ? 'barangay_id = ?' : 'barangay_id IS NULL'), $isBarangay ? [$id, my_barangay()] : [$id]);
        if (!$doc) {
            require_barangay_access(null);
        }
        if (q_val('SELECT 1 FROM budget_proposals WHERE document_id = ? AND prop_status IN ("pending","approved")', [$id])) {
            flash('error', 'This file supports a pending or approved budget proposal and cannot be deleted.');
        } else {
            q('DELETE FROM documents WHERE document_id = ?', [$id]);
            delete_upload($doc['file_path']);
            audit('DOCUMENT_DELETED', 'Deleted "' . $doc['title'] . '"');
            flash('success', 'Document deleted.');
        }
        redirect($base);
    }
}

$cat = isset(DOC_CATEGORIES[get('cat')]) ? get('cat') : '';
$year = get_int('year');
$brgyFilter = $isBarangay ? null : get('barangay');
$where = [];
$params = [];
if ($isBarangay) {
    $where[] = '(d.barangay_id = ? OR d.barangay_id IS NULL)';
    $params[] = my_barangay();
} elseif ($brgyFilter === 'fed' || $brgyFilter === '') {
    $where[] = 'd.barangay_id IS NULL';
} elseif ($brgyFilter !== 'all') {
    $where[] = 'd.barangay_id = ?';
    $params[] = (int) $brgyFilter;
}
$counts = [];
foreach (q_all('SELECT d.category, COUNT(*) c FROM documents d' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' GROUP BY d.category', $params) as $c) {
    $counts[$c['category']] = (int) $c['c'];
}
if ($cat !== '') {
    $where[] = 'd.category = ?';
    $params[] = $cat;
}
if ($year) {
    $where[] = 'YEAR(d.date_uploaded) = ?';
    $params[] = $year;
}
$docs = q_all('SELECT d.*, u.username, b.barangay_name, p.program_code
                 FROM documents d JOIN users u ON u.user_id = d.uploaded_by LEFT JOIN barangays b ON b.barangay_id = d.barangay_id LEFT JOIN programs p ON p.program_id = d.program_id
               ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY d.date_uploaded DESC', $params);
$years = q_all('SELECT DISTINCT YEAR(date_uploaded) y FROM documents ORDER BY y DESC');
$programs = $isBarangay ? q_all('SELECT program_id, program_code, title FROM programs WHERE barangay_id = ? ORDER BY program_code', [my_barangay()]) : [];
$barangays = $isBarangay ? [] : q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');
$qs = fn (array $o) => '?' . http_build_query(array_filter(array_merge(['cat' => $cat, 'year' => $year ?: null, 'barangay' => $brgyFilter ?: null], $o), fn ($v) => $v !== null && $v !== ''));

$title = 'Document Repository';
$nav = 'documents';
$crumb = 'Content · Documents';
require APP_ROOT . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Document Repository</h1>
    <div class="page-sub"><?= e($cat ? DOC_CATEGORIES[$cat] : 'All document types') ?> · <?= count($docs) ?> files</div>
  </div>
  <div class="page-actions">
    <?php if (!$isBarangay): ?>
      <form method="get"><input type="hidden" name="cat" value="<?= e($cat) ?>"><select class="input" name="barangay" data-autosubmit style="width:auto">
        <option value="fed"<?= in_array($brgyFilter, ['', 'fed'], true) ? ' selected' : '' ?>>Federation files</option><option value="all"<?= $brgyFilter === 'all' ? ' selected' : '' ?>>All barangays + federation</option>
        <?php foreach ($barangays as $b): ?><option value="<?= (int) $b['barangay_id'] ?>"<?= $brgyFilter === (string) $b['barangay_id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option><?php endforeach; ?></select></form>
    <?php endif; ?>
    <button class="btn btn-primary" data-open="dlg-upload">+ Upload</button>
  </div>
</div>

<div class="grid side-layout">
  <div class="card" style="padding:12px 0">
    <div class="label-mono" style="padding:0 16px">Document Types</div>
    <a class="nav-like<?= $cat === '' ? ' on' : '' ?>" href="<?= e($qs(['cat' => null])) ?>"><span>All documents</span><span class="mono small"><?= array_sum($counts) ?></span></a>
    <?php foreach (DOC_CATEGORIES as $k => $label): ?>
      <a class="nav-like<?= $cat === $k ? ' on' : '' ?>" href="<?= e($qs(['cat' => $k])) ?>"><span><?= e($label) ?></span><span class="mono small"><?= $counts[$k] ?? 0 ?></span></a>
    <?php endforeach; ?>
    <div class="label-mono" style="padding:14px 16px 0">Year</div>
    <a class="nav-like<?= !$year ? ' on' : '' ?>" href="<?= e($qs(['year' => null])) ?>"><span>All years</span></a>
    <?php foreach ($years as $y): ?><a class="nav-like<?= $year === (int) $y['y'] ? ' on' : '' ?>" href="<?= e($qs(['year' => $y['y']])) ?>"><span><?= (int) $y['y'] ?></span></a><?php endforeach; ?>
  </div>

  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Document Name</th><th>Type</th><th>Owner</th><th>Uploaded By</th><th>Date</th><th>Portal</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): $own = $isBarangay ? (int) $d['barangay_id'] === my_barangay() : $d['barangay_id'] === null; ?>
        <tr>
          <td style="font-weight:500">📄 <?= e($d['title']) ?><div class="sub"><?= e(strtoupper(pathinfo($d['file_path'], PATHINFO_EXTENSION))) ?> · <?= e(file_size_label((int) $d['file_size'])) ?><?= $d['program_code'] ? ' · ' . e($d['program_code']) : '' ?></div></td>
          <td class="muted small"><?= e(DOC_CATEGORIES[$d['category']] ?? $d['category']) ?></td>
          <td class="muted small"><?= e($d['barangay_name'] ?: 'SK Federation') ?></td>
          <td class="muted small"><?= e($d['username']) ?></td>
          <td class="sub nowrap"><?= e(fmt_date($d['date_uploaded'])) ?></td>
          <td><?= (int) $d['is_public'] ? pill('approved', 'Published') : pill('draft', 'Internal') ?></td>
          <td class="right nowrap">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('file.php?id=' . $d['document_id'])) ?>" target="_blank" rel="noopener">View</a>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('file.php?id=' . $d['document_id'] . '&download=1')) ?>">↓</a>
            <?php if ($own): ?><form class="inline-form" method="post" data-confirm="Delete &quot;<?= e($d['title']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="document_id" value="<?= (int) $d['document_id'] ?>"><button class="btn btn-danger btn-sm" aria-label="Delete">✕</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$docs): ?><tr><td colspan="7" class="empty">No files in this folder yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<dialog class="modal" id="dlg-upload">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <div class="modal-head"><div><div class="modal-title">Upload Document</div><div class="muted small">PDF, DOC, DOCX, XLS, XLSX, JPG or PNG · max 5 MB</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Document title <span class="req">*</span></label><input class="input" name="title" required maxlength="255"></div>
    <div class="form-grid">
      <div class="field"><label>Type <span class="req">*</span></label><select class="input" name="category" required><?php foreach (DOC_CATEGORIES as $k => $l): ?><option value="<?= $k ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <?php if ($isBarangay): ?><div class="field"><label>Related program</label><select class="input" name="program_id"><option value="0">— None —</option><?php foreach ($programs as $p): ?><option value="<?= (int) $p['program_id'] ?>"><?= e($p['program_code'] . ' · ' . $p['title']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <label class="upload-box mb"><span class="muted">Choose a file</span><input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"></label>
    <label class="check"><input type="checkbox" name="is_public" value="1"><span>Publish to the KK member portal (<?= $isBarangay ? 'members of ' . e(current_user()['barangay_name']) : 'all barangays' ?>). Budget proposal files are never published.</span></label>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Upload</button></div>
  </form>
</dialog>
<?php require APP_ROOT . '/includes/footer.php';
