<?php
/**
 * KK registration verification. Only the designated SK Chairman of the
 * applicant's barangay may approve or reject — not the Administrator.
 * Viewing an ID or selfie photo is written to the audit log, and both
 * photos are deleted as soon as the chairman acts.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_BARANGAY);
$chair = is_chairman();
$b = my_barangay();

function pending_applicant(int $userId, int $barangayId): ?array
{
    return q_one('SELECT u.*, p.* FROM users u JOIN youth_profiles p ON p.user_id = u.user_id
                   WHERE u.user_id = ? AND u.barangay_id = ? AND u.role_id = ? AND u.verification_status = "pending"', [$userId, $barangayId, ROLE_MEMBER]);
}

// Stream a verification photo. Chairman only, own barangay only, always logged.
if (get('photo') !== '') {
    $app = $chair ? pending_applicant(get_int('user'), $b) : null;
    $path = $app ? (get('photo') === 'selfie' ? $app['selfie_path'] : $app['id_document_path']) : null;
    if (!$path || !is_file(UPLOAD_DIR . '/' . $path)) {
        http_response_code(404);
        exit('Not found.');
    }
    audit('VERIFICATION_PHOTO_VIEWED', 'Viewed ' . (get('photo') === 'selfie' ? 'selfie' : 'ID photo') . ' of ' . kk_id((int) $app['profile_id'], $app['date_encoded']));
    header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->file(UPLOAD_DIR . '/' . $path) ?: 'application/octet-stream'));
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    readfile(UPLOAD_DIR . '/' . $path);
    exit;
}

if (is_post()) {
    csrf_check();
    if (!$chair) {
        flash('error', 'Only the designated SK Chairman of your barangay can verify registrations.');
        redirect('barangay/verify.php');
    }
    $app = pending_applicant(post_int('user_id'), $b);
    if (!$app) {
        flash('error', 'That registration is no longer pending in your barangay.');
        redirect('barangay/verify.php');
    }
    $kk = kk_id((int) $app['profile_id'], $app['date_encoded']);
    $decision = post('decision');
    $reason = post('reason');
    if ($decision === 'reject' && $reason === '') {
        flash('error', 'Give a reason for the rejection so the applicant knows what to fix.');
        redirect('barangay/verify.php?id=' . $app['user_id']);
    }
    if (!in_array($decision, ['approve', 'reject'], true)) {
        redirect('barangay/verify.php');
    }
    $pdo = db();
    $pdo->beginTransaction();
    if ($decision === 'approve') {
        q('UPDATE users SET verification_status = "verified", verified_by = ?, date_verified = NOW(), rejection_reason = NULL WHERE user_id = ?', [uid(), $app['user_id']]);
        notify((int) $app['user_id'], 'Your KK registration was approved', 'Welcome! You can now sign in with your email or KK ID ' . $kk . '.', 'member/index.php');
        audit('REGISTRATION_VERIFIED', 'Approved ' . $kk . ' (' . $app['full_name'] . ')');
    } else {
        q('UPDATE users SET verification_status = "rejected", verified_by = ?, date_verified = NOW(), rejection_reason = ? WHERE user_id = ?', [uid(), mb_substr($reason, 0, 255), $app['user_id']]);
        audit('REGISTRATION_REJECTED', 'Rejected ' . $kk . ': ' . mb_substr($reason, 0, 200));
    }
    q('UPDATE youth_profiles SET id_document_path = NULL, selfie_path = NULL WHERE profile_id = ?', [$app['profile_id']]);
    $pdo->commit();
    // Delete the identity photos only after the decision is saved.
    delete_upload($app['id_document_path']);
    delete_upload($app['selfie_path']);
    flash('success', 'Registration of ' . $app['full_name'] . ' ' . ($decision === 'approve' ? 'approved. The account is now active.' : 'rejected.') . ' Verification photos were deleted.');
    redirect('barangay/verify.php');
}

$pending = q_all('SELECT u.user_id, u.email, u.date_created, p.* FROM users u JOIN youth_profiles p ON p.user_id = u.user_id
                   WHERE u.barangay_id = ? AND u.role_id = ? AND u.verification_status = "pending" ORDER BY u.date_created', [$b, ROLE_MEMBER]);
$recent = q_all('SELECT u.user_id, u.verification_status, u.date_verified, u.rejection_reason, p.full_name, p.profile_id, p.date_encoded, v.username AS verifier
                  FROM users u JOIN youth_profiles p ON p.user_id = u.user_id LEFT JOIN users v ON v.user_id = u.verified_by
                 WHERE u.barangay_id = ? AND u.role_id = ? AND u.verification_status <> "pending" AND u.date_verified IS NOT NULL ORDER BY u.date_verified DESC LIMIT 8', [$b, ROLE_MEMBER]);
$selId = get_int('id') ?: (int) ($pending[0]['user_id'] ?? 0);
$sel = null;
foreach ($pending as $p) {
    if ((int) $p['user_id'] === $selId) {
        $sel = $p;
    }
}
$showPhotos = $sel && $chair && get('show') === '1';

$title = 'Verify Members';
$nav = 'verify';
$crumb = 'Members · Verify Members';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Verification Queue</h1>
    <div class="page-sub">Brgy. <?= e(current_user()['barangay_name']) ?> — <?= count($pending) ?> pending</div>
  </div>
</div>

<div class="scope-banner">
  <?php if ($chair): ?>You are the SK Barangay Chairman for <strong>&nbsp;<?= e(current_user()['barangay_name']) ?>&nbsp;</strong>. You can only see registrations from your own barangay. Every time you view a verification photo, it is written to the audit log.
  <?php else: ?>Only the designated SK Chairman of <?= e(current_user()['barangay_name']) ?> can approve or reject registrations. You can see the queue, but not the photos or the decision buttons.<?php endif; ?>
</div>

<div class="grid side-layout side-wide">
  <div class="card" style="overflow:hidden">
    <?php foreach ($pending as $p): ?>
      <a href="?id=<?= (int) $p['user_id'] ?>" style="display:block;padding:13px 16px;border-bottom:1px solid var(--border-default);text-decoration:none;color:inherit;<?= $sel && (int) $sel['user_id'] === (int) $p['user_id'] ? 'background:var(--surface-accent)' : '' ?>">
        <div style="font-weight:600"><?= e($p['full_name']) ?></div>
        <div class="record-id"><?= e(kk_id((int) $p['profile_id'], $p['date_encoded'])) ?></div>
        <div class="small muted"><?= e(fmt_datetime($p['date_created'])) ?></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$pending): ?><div class="empty">No registrations are waiting for verification.</div><?php endif; ?>
    <?php if ($recent): ?>
      <div class="label-mono" style="padding:14px 16px 4px">Recent decisions</div>
      <?php foreach ($recent as $r): ?>
        <div style="padding:8px 16px;border-top:1px solid var(--border-default)">
          <div class="spread"><span style="font-size:12px;font-weight:500"><?= e($r['full_name']) ?></span><?= pill($r['verification_status']) ?></div>
          <div class="mono small muted"><?= e(fmt_date($r['date_verified'])) ?> · <?= e($r['verifier']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div>
    <?php if ($sel): $age = age($sel['birthdate']); ?>
      <div class="grid grid-2 mb">
        <div class="card card-pad">
          <div class="label-mono">Applicant Details</div>
          <div class="kv plain"><span>Full Name</span><span><?= e($sel['full_name']) ?></span></div>
          <div class="kv"><span>KK ID (assigned)</span><span><?= e(kk_id((int) $sel['profile_id'], $sel['date_encoded'])) ?></span></div>
          <div class="kv plain"><span>Date of Birth</span><span><?= e(fmt_date($sel['birthdate'])) ?> (<?= $age ?> yrs)</span></div>
          <div class="kv plain"><span>Sex</span><span><?= e($sel['sex']) ?></span></div>
          <div class="kv plain"><span>Barangay</span><span><?= e(current_user()['barangay_name']) ?></span></div>
          <div class="kv plain"><span>Education</span><span><?= e($sel['education'] ?: '—') ?></span></div>
          <div class="kv plain"><span>School status</span><span><?= e($sel['school_status'] ?: '—') ?></span></div>
          <div class="kv plain"><span>Youth organization</span><span><?= e($sel['youth_org'] ?: 'None') ?></span></div>
          <div class="kv plain"><span>PWD / SK Assembly</span><span><?= (int) $sel['is_pwd'] ? 'PWD' : 'Not PWD' ?> · <?= (int) $sel['attended_assembly'] ? 'Attended' : 'Not yet' ?></span></div>
          <div class="kv"><span>Mobile</span><span><?= e($sel['contact_no']) ?></span></div>
          <div class="kv"><span>Email</span><span><?= e($sel['email']) ?></span></div>
          <div class="kv plain"><span>Applied on</span><span><?= e(fmt_datetime($sel['date_created'])) ?></span></div>
          <?php if ($age < 15 || $age > 30): ?><div class="alert alert-error mt">Applicant is <?= $age ?> years old, outside the 15–30 KK age range.</div><?php endif; ?>
        </div>
        <div class="card card-pad">
          <div class="label-mono" style="margin-bottom:4px">Verification Photos</div>
          <p class="muted small">Opening these photos is logged in the audit trail. View only when necessary to verify identity and residency.</p>
          <?php if (!$chair): ?>
            <div class="empty">Only the designated SK Chairman can view the photos.</div>
          <?php elseif (!$sel['id_document_path'] && !$sel['selfie_path']): ?>
            <div class="empty">No photos on file.</div>
          <?php elseif (!$showPhotos): ?>
            <div class="upload-box" style="padding:26px">
              <div style="font-size:24px">🔒</div>
              <div style="font-weight:600;margin:4px 0">Photos are hidden</div>
              <div class="muted small mb">View the <?= e($sel['id_document_type']) ?> and selfie. This will be logged.</div>
              <a class="btn btn-secondary" href="?id=<?= (int) $sel['user_id'] ?>&amp;show=1">View photos (logged)</a>
            </div>
          <?php else: ?>
            <div class="grid grid-2" style="gap:10px">
              <?php foreach (['id' => $sel['id_document_type'], 'selfie' => 'Selfie'] as $k => $label): $src = url('barangay/verify.php?photo=' . $k . '&user=' . $sel['user_id']); $isPdf = $k === 'id' && str_ends_with((string) $sel['id_document_path'], '.pdf'); ?>
                <figure style="margin:0;text-align:center">
                  <?php if ($isPdf): ?><a class="btn btn-secondary" href="<?= e($src) ?>" target="_blank" rel="noopener">Open PDF</a>
                  <?php else: ?><a href="<?= e($src) ?>" target="_blank" rel="noopener"><img src="<?= e($src) ?>" alt="<?= e($label) ?>" style="width:100%;max-height:220px;object-fit:contain;border:1px solid var(--border-default);border-radius:8px;background:var(--surface-muted)"></a><?php endif; ?>
                  <figcaption class="small muted"><?= e($label) ?></figcaption>
                </figure>
              <?php endforeach; ?>
            </div>
            <div class="alert alert-warning mt" style="margin-bottom:0">⚠ Photo view logged at <?= e(date('g:i:s A')) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($chair): ?>
      <div class="card card-pad">
        <div class="section-title mb">Decision</div>
        <p class="muted small">Confirm the applicant's residency in <?= e(current_user()['barangay_name']) ?> and that they are 15 to 30 years old. Approving activates the account; either decision deletes the photos.</p>
        <div class="row">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $sel['user_id'] ?>"><input type="hidden" name="decision" value="approve"><button class="btn btn-primary">✓ Approve registration</button></form>
          <button class="btn btn-danger" data-open="dlg-reject">Reject with reason</button>
        </div>
      </div>
      <dialog class="modal" id="dlg-reject">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $sel['user_id'] ?>"><input type="hidden" name="decision" value="reject">
          <div class="modal-head"><div class="modal-title">Reject registration</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
          <div class="field"><label>Reason for rejection <span class="req">*</span></label><textarea class="input" name="reason" required maxlength="255" placeholder="Be specific — e.g. 'ID photo is unclear, face not visible' or 'Not a resident of this barangay.'"></textarea></div>
          <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-danger">Confirm rejection</button></div>
        </form>
      </dialog>
      <?php endif; ?>
    <?php else: ?>
      <div class="card empty">Select a registration from the queue.</div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php';
