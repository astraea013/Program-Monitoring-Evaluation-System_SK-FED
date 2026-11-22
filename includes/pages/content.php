<?php
/**
 * KK Member Content: announcements, documents published to the member
 * portal, accomplishments, and replies to member feedback.
 * Federation may target all barangays or one. Barangay is limited to its own.
 * Anonymous feedback never shows the member's identity here.
 */
$isBarangay = role() === ROLE_BARANGAY;
$base = $isBarangay ? 'barangay/content.php' : 'federation/content.php';
$myB = my_barangay();
$barangays = q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');
$brgyIds = array_map('intval', array_column($barangays, 'barangay_id'));

if (is_post()) {
    csrf_check();
    $action = post('action');
    $tab = post('tab') ?: 'announcements';

    if ($action === 'announce') {
        $t = post('title');
        $body = post('body');
        $target = $isBarangay ? $myB : (post_int('barangay_id') ?: null);
        if ($t === '' || $body === '' || ($target !== null && !in_array($target, $brgyIds, true))) {
            flash('error', 'Enter a title and a message.');
        } else {
            q('INSERT INTO announcements (posted_by, barangay_id, title, body) VALUES (?, ?, ?, ?)', [uid(), $target, $t, $body]);
            audit('ANNOUNCEMENT_POSTED', '"' . $t . '" to ' . ($target ? 'barangay #' . $target : 'all barangays'));
            notify_role(ROLE_MEMBER, 'New announcement: ' . $t, mb_substr($body, 0, 140), 'member/announcements.php', $target);
            flash('success', 'Announcement published to the KK member portal.');
        }
    }
    if ($action === 'delete_announcement') {
        $id = post_int('id');
        $st = q('DELETE FROM announcements WHERE announcement_id = ? AND ' . ($isBarangay ? 'barangay_id = ?' : 'posted_by IN (SELECT user_id FROM users WHERE role_id = ' . ROLE_FEDERATION . ')'), $isBarangay ? [$id, $myB] : [$id]);
        $st->rowCount() ? audit('ANNOUNCEMENT_DELETED', 'Announcement #' . $id) : require_barangay_access(null);
        flash('success', 'Announcement removed.');
    }
    if ($action === 'upload_public') {
        $tab = 'documents';
        $t = post('title');
        $cat = post('category');
        if ($t === '' || !isset(DOC_CATEGORIES[$cat]) || $cat === 'proposal') {
            flash('error', 'Enter a title and choose a document type.');
        } else {
            try {
                $f = store_upload($_FILES['file'] ?? null, 'documents', DOC_TYPES);
                $target = $isBarangay ? $myB : (post_int('barangay_id') ?: null);
                q('INSERT INTO documents (barangay_id, uploaded_by, title, category, file_path, file_size, is_public) VALUES (?, ?, ?, ?, ?, ?, 1)', [$target, uid(), $t, $cat, $f['path'], $f['size']]);
                audit('DOCUMENT_UPLOADED', 'Published "' . $t . '" to the member portal');
                flash('success', '"' . $t . '" published to the member portal.');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
            }
        }
    }
    if ($action === 'unpublish') {
        $tab = 'documents';
        $id = post_int('id');
        $st = q('UPDATE documents SET is_public = 0 WHERE document_id = ? AND ' . ($isBarangay ? 'barangay_id = ?' : 'barangay_id IS NULL'), $isBarangay ? [$id, $myB] : [$id]);
        $st->rowCount() ? audit('DOCUMENT_UNPUBLISHED', 'Document #' . $id) : require_barangay_access(null);
        flash('success', 'Removed from the member portal. The file stays in the repository.');
    }
    if ($action === 'accomplishment') {
        $tab = 'accomplishments';
        $pid = post_int('program_id');
        $text = post('narrative');
        $ok = $isBarangay ? q_val('SELECT 1 FROM programs WHERE program_id = ? AND barangay_id = ?', [$pid, $myB]) : q_val('SELECT 1 FROM programs WHERE program_id = ?', [$pid]);
        if (!$ok || $text === '') {
            flash('error', 'Choose a program and write the accomplishment.');
        } else {
            q('INSERT INTO accomplishments (program_id, narrative) VALUES (?, ?)', [$pid, $text]);
            audit('ACCOMPLISHMENT_POSTED', 'Accomplishment for program #' . $pid);
            flash('success', 'Accomplishment published.');
        }
    }
    if ($action === 'delete_accomplishment') {
        $tab = 'accomplishments';
        $id = post_int('id');
        $st = $isBarangay
            ? q('DELETE ac FROM accomplishments ac JOIN programs p ON p.program_id = ac.program_id WHERE ac.accomplishment_id = ? AND p.barangay_id = ?', [$id, $myB])
            : q('DELETE FROM accomplishments WHERE accomplishment_id = ?', [$id]);
        $st->rowCount() ? audit('ACCOMPLISHMENT_DELETED', '#' . $id) : require_barangay_access(null);
        flash('success', 'Accomplishment removed.');
    }
    if ($action === 'reply') {
        $tab = 'feedback';
        $id = post_int('id');
        $reply = post('reply');
        $fb = $isBarangay
            ? q_one('SELECT f.*, yp.user_id FROM feedback f JOIN youth_profiles yp ON yp.profile_id = f.profile_id WHERE f.feedback_id = ? AND yp.barangay_id = ?', [$id, $myB])
            : q_one('SELECT f.*, yp.user_id FROM feedback f JOIN youth_profiles yp ON yp.profile_id = f.profile_id WHERE f.feedback_id = ?', [$id]);
        if (!$fb) {
            require_barangay_access(null);
        }
        if ($reply === '') {
            flash('error', 'Write a reply first.');
        } else {
            q('UPDATE feedback SET reply = ?, replied_by = ?, date_replied = NOW() WHERE feedback_id = ?', [$reply, uid(), $id]);
            audit('FEEDBACK_REPLIED', 'Feedback #' . $id);
            if ($fb['user_id']) {
                notify((int) $fb['user_id'], 'The SK replied to your feedback', mb_substr($reply, 0, 140), 'member/feedback.php');
            }
            flash('success', 'Reply sent.');
        }
    }
    redirect($base . '?tab=' . urlencode($tab));
}

$tab = in_array(get('tab'), ['announcements', 'documents', 'accomplishments', 'feedback'], true) ? get('tab') : 'announcements';
$scopeSql = $isBarangay ? '(x.barangay_id = ? OR x.barangay_id IS NULL)' : '1=1';
$scopeP = $isBarangay ? [$myB] : [];
$announcements = q_all("SELECT x.*, u.username, u.role_id, b.barangay_name FROM announcements x JOIN users u ON u.user_id = x.posted_by LEFT JOIN barangays b ON b.barangay_id = x.barangay_id WHERE $scopeSql ORDER BY x.date_posted DESC", $scopeP);
$portalDocs = q_all("SELECT x.*, u.username, b.barangay_name FROM documents x JOIN users u ON u.user_id = x.uploaded_by LEFT JOIN barangays b ON b.barangay_id = x.barangay_id WHERE x.is_public = 1 AND $scopeSql ORDER BY x.date_uploaded DESC", $scopeP);
$accomplishments = q_all('SELECT ac.*, p.program_code, p.title, b.barangay_name FROM accomplishments ac JOIN programs p ON p.program_id = ac.program_id JOIN barangays b ON b.barangay_id = p.barangay_id' . ($isBarangay ? ' WHERE p.barangay_id = ?' : '') . ' ORDER BY ac.date_posted DESC', $isBarangay ? [$myB] : []);
$feedback = q_all('SELECT f.*, yp.full_name, yp.profile_id, yp.date_encoded, b.barangay_name, u.username AS replier
                     FROM feedback f JOIN youth_profiles yp ON yp.profile_id = f.profile_id JOIN barangays b ON b.barangay_id = yp.barangay_id LEFT JOIN users u ON u.user_id = f.replied_by'
                  . ($isBarangay ? ' WHERE yp.barangay_id = ?' : '') . ' ORDER BY (f.reply IS NULL) DESC, f.date_sent DESC', $isBarangay ? [$myB] : []);
$awaiting = count(array_filter($feedback, fn ($f) => $f['reply'] === null));
$programs = q_all('SELECT p.program_id, p.program_code, p.title, b.barangay_name FROM programs p JOIN barangays b ON b.barangay_id = p.barangay_id' . ($isBarangay ? ' WHERE p.barangay_id = ?' : '') . ' ORDER BY p.program_code', $isBarangay ? [$myB] : []);

$title = 'KK Member Content';
$nav = 'content';
$crumb = 'Content · KK Member Content';
require APP_ROOT . '/includes/header.php';
$roleName = ROLE_LABELS[role()];
$audience = function () use ($isBarangay, $barangays) {
    if ($isBarangay) {
        return '<input class="input" value="' . e(current_user()['barangay_name']) . '" readonly>';
    }
    $o = '<select class="input" name="barangay_id"><option value="0">All Barangays</option>';
    foreach ($barangays as $b) {
        $o .= '<option value="' . (int) $b['barangay_id'] . '">' . e($b['barangay_name']) . '</option>';
    }
    return $o . '</select>';
};
?>
<div class="page-head">
  <div>
    <h1 class="page-title">KK Member Content</h1>
    <div class="page-sub"><?= e($roleName) ?> · Publish member content and respond to feedback</div>
  </div>
</div>
<div class="scope-banner"><?= $isBarangay ? 'Uploads and feedback are scoped to ' . e(current_user()['barangay_name']) . '.' : 'Federation uploads may be published to all barangays or targeted to one barangay.' ?></div>

<div class="tabs">
  <a class="tab<?= $tab === 'announcements' ? ' active' : '' ?>" href="?tab=announcements">Announcements</a>
  <a class="tab<?= $tab === 'documents' ? ' active' : '' ?>" href="?tab=documents">Reports &amp; Documents</a>
  <a class="tab<?= $tab === 'accomplishments' ? ' active' : '' ?>" href="?tab=accomplishments">Accomplishments</a>
  <a class="tab<?= $tab === 'feedback' ? ' active' : '' ?>" href="?tab=feedback">Member Feedback (<?= $awaiting ?>)</a>
</div>

<?php if ($tab === 'announcements'): ?>
<div class="grid settings-layout form-left">
  <form class="card card-pad" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="announce">
    <div class="section-title">Publish Announcement</div><p class="muted small mb">The announcement appears in the targeted KK member portal.</p>
    <div class="field"><label class="label-mono">Audience</label><?= $audience() ?></div>
    <div class="field"><label class="label-mono">Title</label><input class="input" name="title" required maxlength="255" placeholder="Announcement title"></div>
    <div class="field"><label class="label-mono">Message</label><textarea class="input" name="body" rows="5" required placeholder="Write the announcement for KK members"></textarea></div>
    <button class="btn btn-primary btn-block">Publish announcement</button>
  </form>
  <div class="card card-pad">
    <div class="section-title mb">Published Announcements</div>
    <?php foreach ($announcements as $i => $a): $mine = $isBarangay ? (int) $a['barangay_id'] === $myB : (int) $a['role_id'] === ROLE_FEDERATION; ?>
      <div style="<?= $i ? 'border-top:1px solid var(--border-default);' : '' ?>padding:12px 0">
        <div class="spread"><strong><?= e($a['title']) ?></strong><?= pill('completed', $a['barangay_name'] ?: 'All Barangays') ?></div>
        <p class="muted" style="font-size:12px;margin:4px 0"><?= nl2br(e($a['body'])) ?></p>
        <div class="spread"><span class="mono small muted"><?= e($a['username']) ?> · <?= e(fmt_datetime($a['date_posted'])) ?></span>
          <?php if ($mine): ?><form method="post" data-confirm="Remove this announcement?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="id" value="<?= (int) $a['announcement_id'] ?>"><button class="link-btn small red">Remove</button></form><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$announcements): ?><div class="empty">No announcements have been published.</div><?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'documents'): ?>
<div class="grid settings-layout form-left">
  <form class="card card-pad" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload_public">
    <div class="section-title">Upload for KK Members</div><p class="muted small mb">Publish an accomplishment report or official document to the member portal.</p>
    <div class="field"><label class="label-mono">Audience</label><?= $audience() ?></div>
    <div class="field"><label class="label-mono">Document title</label><input class="input" name="title" required maxlength="255"></div>
    <div class="field"><label class="label-mono">Document type</label><select class="input" name="category"><?php foreach (DOC_CATEGORIES as $k => $l): if ($k === 'proposal') continue; ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="label-mono">File</label><input class="input" type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"></div>
    <button class="btn btn-primary btn-block">Upload to member portal</button>
  </form>
  <div class="card card-pad">
    <div class="section-title mb">Member Portal Uploads</div>
    <?php foreach ($portalDocs as $i => $d): $mine = $isBarangay ? (int) $d['barangay_id'] === $myB : $d['barangay_id'] === null; ?>
      <div class="spread" style="<?= $i ? 'border-top:1px solid var(--border-default);' : '' ?>padding:11px 0">
        <div><a href="<?= e(url('file.php?id=' . $d['document_id'])) ?>" target="_blank" rel="noopener" style="font-weight:600;color:inherit"><?= e($d['title']) ?></a>
          <div class="mono small muted"><?= e(DOC_CATEGORIES[$d['category']] ?? $d['category']) ?> · <?= e($d['username']) ?> · <?= e(fmt_date($d['date_uploaded'])) ?></div></div>
        <div class="row" style="gap:6px"><?= pill('planned', $d['barangay_name'] ?: 'All Barangays') ?>
          <?php if ($mine): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unpublish"><input type="hidden" name="id" value="<?= (int) $d['document_id'] ?>"><button class="link-btn small">Unpublish</button></form><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$portalDocs): ?><div class="empty">No reports or documents have been published.</div><?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'accomplishments'): ?>
<div class="grid settings-layout form-left">
  <form class="card card-pad" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="accomplishment">
    <div class="section-title">Publish Accomplishment</div><p class="muted small mb">Accomplishments appear on the KK member homepage.</p>
    <div class="field"><label class="label-mono">Program</label><select class="input" name="program_id" required><option value="">Select…</option><?php foreach ($programs as $p): ?><option value="<?= (int) $p['program_id'] ?>"><?= e($p['program_code'] . ' · ' . $p['title'] . ($isBarangay ? '' : ' (' . $p['barangay_name'] . ')')) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="label-mono">What was accomplished</label><textarea class="input" name="narrative" rows="5" required></textarea></div>
    <button class="btn btn-primary btn-block">Publish accomplishment</button>
  </form>
  <div class="card card-pad">
    <div class="section-title mb">Published Accomplishments</div>
    <?php foreach ($accomplishments as $i => $a): ?>
      <div style="<?= $i ? 'border-top:1px solid var(--border-default);' : '' ?>padding:11px 0">
        <div class="spread"><strong><?= e($a['title']) ?></strong><span class="record-id"><?= e($a['program_code']) ?></span></div>
        <p class="muted" style="font-size:12px;margin:4px 0"><?= nl2br(e($a['narrative'])) ?></p>
        <div class="spread"><span class="mono small muted"><?= e($a['barangay_name']) ?> · <?= e(fmt_date($a['date_posted'])) ?></span>
          <form method="post" data-confirm="Remove this accomplishment?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_accomplishment"><input type="hidden" name="id" value="<?= (int) $a['accomplishment_id'] ?>"><button class="link-btn small red">Remove</button></form></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$accomplishments): ?><div class="empty">No accomplishments have been published.</div><?php endif; ?>
  </div>
</div>

<?php else: ?>
  <?php foreach ($feedback as $f): ?>
    <div class="row-card <?= $f['reply'] ? 'green' : 'amber' ?>">
      <div style="flex:1;min-width:0">
        <div class="row" style="gap:8px"><strong><?= (int) $f['is_anonymous'] ? 'Anonymous KK Member' : e($f['full_name']) ?></strong><?= $f['reply'] ? pill('replied', 'Replied') : pill('pending', 'Awaiting reply') ?><span class="mono small muted"><?= e($f['barangay_name']) ?></span></div>
        <p style="font-size:12.5px;margin:6px 0"><?= nl2br(e($f['message'])) ?></p>
        <?php if ($f['reply']): ?><div class="note-box"><strong><?= e($f['replier'] ?: 'SK Official') ?>:</strong> <?= nl2br(e($f['reply'])) ?></div><?php endif; ?>
      </div>
      <div class="row-card-side">
        <span class="row-card-time"><?= e(fmt_datetime($f['date_sent'])) ?></span>
        <button class="btn <?= $f['reply'] ? 'btn-secondary' : 'btn-primary' ?> btn-sm" data-open="dlg-reply" data-fill='<?= e(json_encode(['id' => $f['feedback_id'], 'reply' => $f['reply'] ?? '', 'msg' => $f['message']])) ?>'><?= $f['reply'] ? 'Edit reply' : 'Reply' ?></button>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$feedback): ?><div class="card empty">No feedback has been submitted yet.</div><?php endif; ?>
  <dialog class="modal" id="dlg-reply">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id">
      <div class="modal-head"><div class="modal-title">Reply to KK Member</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
      <div class="note-box mb" data-text="msg"></div>
      <textarea class="input" name="reply" rows="5" required placeholder="Write an official response"></textarea>
      <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Send reply</button></div>
    </form>
  </dialog>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php';
