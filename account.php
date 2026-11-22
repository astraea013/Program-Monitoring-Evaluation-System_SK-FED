<?php
/** Profile and password change, for every role. */
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'password') {
        $row = q_one('SELECT password_hash FROM users WHERE user_id = ?', [uid()]);
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), $row['password_hash'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Use at least 8 characters for the new password.');
        } elseif ($new !== (string) ($_POST['confirm'] ?? '')) {
            flash('error', 'The new passwords do not match.');
        } else {
            q('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($new, PASSWORD_DEFAULT), uid()]);
            audit('PASSWORD_CHANGED', 'Changed own password');
            flash('success', 'Your password was updated.');
        }
        redirect('account.php#password');
    }
    if ($action === 'contact' && role() === ROLE_MEMBER) {
        // Members maintain their own contact details; the rest of the profile is kept by the barangay.
        $mobile = post('contact_no');
        $address = post('address');
        if ($mobile !== '' && !preg_match('/^09\d{9}$/', $mobile)) {
            flash('error', 'Use the format 09XXXXXXXXX for the mobile number.');
        } else {
            q('UPDATE youth_profiles SET contact_no = ?, address = ? WHERE user_id = ?', [$mobile ?: null, $address ?: null, uid()]);
            audit('PROFILE_UPDATED', 'Updated own contact details');
            flash('success', 'Your contact details were saved.');
        }
        redirect('account.php');
    }
}

$profile = role() === ROLE_MEMBER ? q_one('SELECT * FROM youth_profiles WHERE user_id = ?', [uid()]) : null;
$title = 'Profile';
$nav = 'account';
$crumb = 'Account · Profile';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Profile</h1>
    <div class="page-sub">Your account information</div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card card-pad">
    <div class="row mb" style="background:var(--surface-accent);padding:14px;border-radius:8px">
      <span class="avatar"><?= e(initials(display_name())) ?></span>
      <div>
        <div class="section-title"><?= e(display_name()) ?></div>
        <div class="muted small"><?= e(ROLE_LABELS[role()]) ?></div>
      </div>
    </div>
    <div class="kv"><span>Account ID</span><span><?= e($profile ? kk_id((int) $profile['profile_id'], $profile['date_encoded']) : strtoupper($user['username'])) ?></span></div>
    <div class="kv"><span>Username</span><span><?= e($user['username']) ?></span></div>
    <div class="kv"><span>Email</span><span><?= e($user['email']) ?></span></div>
    <div class="kv"><span>Assigned area</span><span><?= e($user['barangay_name'] ?: 'Nasipit, Agusan del Norte') ?></span></div>
    <div class="kv"><span>Last sign-in</span><span><?= e(fmt_datetime($user['last_login'])) ?></span></div>

    <?php if ($profile): ?>
      <div class="detail-section-title">Personal Information</div>
      <div class="detail-grid">
        <div><div class="field-label">Full name</div><div class="field-value"><?= e($profile['full_name']) ?></div></div>
        <div><div class="field-label">Date of birth</div><div class="field-value"><?= e(fmt_date($profile['birthdate'])) ?> (<?= age($profile['birthdate']) ?> yrs)</div></div>
        <div><div class="field-label">Sex assigned at birth</div><div class="field-value"><?= e($profile['sex'] ?: '—') ?></div></div>
        <div><div class="field-label">Civil status</div><div class="field-value"><?= e($profile['civil_status'] ?: '—') ?></div></div>
        <div><div class="field-label">Education</div><div class="field-value"><?= e($profile['education'] ?: '—') ?></div></div>
        <div><div class="field-label">Barangay</div><div class="field-value"><?= e($user['barangay_name']) ?></div></div>
      </div>
      <div class="detail-section-title">Youth Profile</div>
      <div class="detail-grid">
        <div><div class="field-label">Youth organization</div><div class="field-value"><?= e($profile['youth_org'] ?: 'No organization') ?></div></div>
        <div><div class="field-label">School status</div><div class="field-value"><?= e($profile['school_status'] ?: '—') ?></div></div>
        <div><div class="field-label">PWD</div><div class="field-value"><?= $profile['is_pwd'] === null ? '—' : ((int) $profile['is_pwd'] ? 'Yes' : 'No') ?></div></div>
        <div><div class="field-label">Attended an SK Assembly</div><div class="field-value"><?= $profile['attended_assembly'] === null ? '—' : ((int) $profile['attended_assembly'] ? 'Yes' : 'No') ?></div></div>
      </div>
      <div class="detail-section-title">Registration &amp; Identity Verification</div>
      <div class="detail-grid">
        <div><div class="field-label">Identity document</div><div class="field-value"><?= e($profile['id_document_type'] ? $profile['id_document_type'] . ' · Verified' : '—') ?></div></div>
        <div><div class="field-label">Data privacy consent</div><div class="field-value"><?= $profile['consent_date'] ? 'Granted' : '—' ?></div></div>
        <div><div class="field-label">Verified on</div><div class="field-value"><?= e(fmt_date($user['date_verified'])) ?></div></div>
        <div><div class="field-label">Registered on</div><div class="field-value"><?= e(fmt_date($profile['date_encoded'])) ?></div></div>
      </div>
      <p class="note-box mt">Identity and selfie images were deleted after verification in accordance with the registration privacy notice. To correct your personal details, contact your barangay SK office.</p>
    <?php endif; ?>
  </div>

  <div class="stack">
    <?php if ($profile): ?>
    <form class="card card-pad" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="contact">
      <div class="section-title mb">Contact Details</div>
      <div class="field"><label>Mobile number</label><input class="input" name="contact_no" value="<?= e($profile['contact_no']) ?>" placeholder="09XXXXXXXXX"></div>
      <div class="field"><label>Address</label><input class="input" name="address" value="<?= e($profile['address']) ?>" placeholder="Purok / street"></div>
      <button class="btn btn-primary" type="submit">Save contact details</button>
    </form>
    <?php endif; ?>
    <form class="card card-pad" method="post" id="password">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="section-title">Change Password</div>
      <p class="muted small mb">Use a strong password you do not use elsewhere.</p>
      <div class="field"><label>Current password</label><input class="input" type="password" name="current" autocomplete="current-password" required></div>
      <div class="field"><label>New password</label><input class="input" type="password" name="new" autocomplete="new-password" minlength="8" required></div>
      <div class="field"><label>Confirm new password</label><input class="input" type="password" name="confirm" autocomplete="new-password" required></div>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
