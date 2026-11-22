<?php
/**
 * KK member registration. The account stays pending until the designated
 * SK Chairman of the selected barangay verifies it (barangay/verify.php).
 * Step 1 personal + youth profile, step 2 contact, password and ID,
 * step 3 selfie. All steps are one form; JavaScript only pages through it.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(home_url(role()));
}

const ORGS = [
    'None' => 'No organization',
    'Kabataan Kontra Droga at Terorismo (KKDAT)' => 'Kabataan Kontra Droga at Terorismo (KKDAT)',
    'Propelling Our Inherited Nation Through our Youth Inc. (POINTY)' => 'Propelling Our Inherited Nation Through our Youth Inc. (POINTY)',
    'Others' => 'Others — specify',
];
const EDUCATION = ['Elementary Level', 'Elementary Graduate', 'High School Level', 'High School Graduate', 'Senior High School Level', 'Senior High School Graduate', 'College Level', 'College Graduate', 'Vocational/Technical'];
const ID_DOCS = ['School ID', 'PhilSys National ID', 'PSA Birth Certificate'];

$barangays = q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');
$f = array_fill_keys(['first_name', 'middle_name', 'last_name', 'birthdate', 'sex', 'barangay_id', 'civil_status', 'education', 'youth_org', 'youth_org_other', 'school_status', 'is_pwd', 'attended_assembly', 'email', 'mobile', 'id_type'], '');
$errors = [];
$done = $_SESSION['registered'] ?? null;
unset($_SESSION['registered']);

if (is_post()) {
    csrf_check();
    foreach ($f as $k => $_) {
        $f[$k] = post($k);
    }
    $consent = !empty($_POST['consent']);
    $password = (string) ($_POST['password'] ?? '');

    foreach (['first_name', 'last_name', 'birthdate', 'sex', 'barangay_id', 'youth_org', 'school_status', 'is_pwd', 'attended_assembly', 'email', 'mobile', 'id_type'] as $req) {
        if ($f[$req] === '') {
            $errors[$req] = 'Required';
        }
    }
    $age = $f['birthdate'] !== '' && strtotime($f['birthdate']) ? age($f['birthdate']) : null;
    if ($f['birthdate'] !== '' && ($age === null || $age < 15 || $age > 30)) {
        $errors['birthdate'] = 'You must be 15–30 years old to register.';
    }
    if (!in_array($f['sex'], ['Male', 'Female'], true) && $f['sex'] !== '') {
        $errors['sex'] = 'Choose Male or Female.';
    }
    $barangayIds = array_map('intval', array_column($barangays, 'barangay_id'));
    if ($f['barangay_id'] !== '' && !in_array((int) $f['barangay_id'], $barangayIds, true)) {
        $errors['barangay_id'] = 'Choose a barangay from the list.';
    }
    if ($f['youth_org'] !== '' && !isset(ORGS[$f['youth_org']])) {
        $errors['youth_org'] = 'Choose an answer from the list.';
    }
    if ($f['youth_org'] === 'Others' && $f['youth_org_other'] === '') {
        $errors['youth_org_other'] = 'Please specify your organization.';
    }
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ($f['email'] !== '' && q_val('SELECT 1 FROM users WHERE email = ?', [$f['email']])) {
        $errors['email'] = 'This email is already registered. Sign in instead.';
    }
    if ($f['mobile'] !== '' && !preg_match('/^09\d{9}$/', $f['mobile'])) {
        $errors['mobile'] = 'Use the format 09XXXXXXXXX.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
        $errors['password_confirm'] = 'The passwords do not match.';
    }
    if ($f['id_type'] !== '' && !in_array($f['id_type'], ID_DOCS, true)) {
        $errors['id_type'] = 'Choose an ID type from the list.';
    }
    if (!$consent) {
        $errors['consent'] = 'You must agree to continue.';
    }

    $stored = [];
    if (!$errors) {
        try {
            $stored['id'] = store_upload($_FILES['id_file'] ?? null, 'verification', ID_TYPES);
        } catch (RuntimeException $ex) {
            $errors['id_file'] = $ex->getMessage();
        }
        try {
            $stored['selfie'] = store_upload($_FILES['selfie'] ?? null, 'verification', PHOTO_TYPES);
        } catch (RuntimeException $ex) {
            $errors['selfie'] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            q('INSERT INTO users (role_id, barangay_id, username, email, password_hash, verification_status, is_active) VALUES (?, ?, ?, ?, ?, "pending", 1)', [
                ROLE_MEMBER, (int) $f['barangay_id'], 'pending-' . bin2hex(random_bytes(6)), $f['email'], password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $fullName = trim(preg_replace('/\s+/', ' ', $f['first_name'] . ' ' . $f['middle_name'] . ' ' . $f['last_name']));
            q('INSERT INTO youth_profiles (user_id, barangay_id, full_name, birthdate, sex, contact_no, civil_status, education, youth_org, school_status, is_pwd, attended_assembly, id_document_type, id_document_path, selfie_path, consent_date)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
                $userId, (int) $f['barangay_id'], $fullName, $f['birthdate'], $f['sex'], $f['mobile'],
                $f['civil_status'] ?: null, $f['education'] ?: null,
                $f['youth_org'] === 'Others' ? $f['youth_org_other'] : ($f['youth_org'] === 'None' ? null : $f['youth_org']),
                $f['school_status'], $f['is_pwd'] === 'Yes' ? 1 : 0, $f['attended_assembly'] === 'Yes' ? 1 : 0,
                $f['id_type'], $stored['id']['path'], $stored['selfie']['path'],
            ]);
            $profileId = (int) $pdo->lastInsertId();
            $kk = kk_id($profileId);
            q('UPDATE users SET username = ? WHERE user_id = ?', [strtolower($kk), $userId]);
            audit('REGISTER', 'KK registration submitted for barangay #' . (int) $f['barangay_id'] . ' (' . $kk . ')', $userId);

            $chair = q_val('SELECT chairman_id FROM barangays WHERE barangay_id = ?', [(int) $f['barangay_id']]);
            $title = 'New KK registration pending review';
            $body = $fullName . ' (' . $kk . ') registered and is waiting for verification.';
            if ($chair) {
                notify((int) $chair, $title, $body, 'barangay/verify.php');
            } else {
                notify_role(ROLE_BARANGAY, $title, $body, 'barangay/verify.php', (int) $f['barangay_id']);
            }
            $pdo->commit();
            $_SESSION['registered'] = $kk;
            redirect('register.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            delete_upload($stored['id']['path'] ?? null);
            delete_upload($stored['selfie']['path'] ?? null);
            error_log('Registration failed: ' . $ex->getMessage());
            $errors['form'] = 'Your registration could not be saved. Please try again.';
        }
    } else {
        delete_upload($stored['id']['path'] ?? null);
        delete_upload($stored['selfie']['path'] ?? null);
    }
}

// The step holding the first error opens first after a failed submit.
$step2Fields = ['email', 'mobile', 'password', 'password_confirm', 'id_type', 'id_file', 'consent'];
$firstErrorStep = !$errors ? 1 : (array_intersect(array_keys($errors), array_merge(['first_name', 'last_name', 'birthdate', 'sex', 'barangay_id', 'youth_org', 'youth_org_other', 'school_status', 'is_pwd', 'attended_assembly'])) ? 1 : (array_intersect(array_keys($errors), $step2Fields) ? 2 : 3));

function fe(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<div class="error-text">' . e($errors[$k]) . '</div>' : '';
}
function sel(string $value, string $current): string
{
    return $value === $current ? ' selected' : '';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KK Member Registration · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="mobile-frame">
<?php if ($done): ?>
  <div class="mobile-body" style="padding-top:40px;text-align:center">
    <div class="big-check">✓</div>
    <h1 style="font-size:20px;margin-bottom:6px">Registration submitted!</h1>
    <p class="muted">Your registration is now being reviewed by the SK Barangay Chairman of your barangay. You can sign in once it is approved.</p>
    <div class="card card-pad mb mt">
      <div class="label-mono">Your KK ID Number</div>
      <div class="kk-id"><?= e($done) ?></div>
      <div style="margin-top:8px"><?= pill('pending', 'Verification pending') ?></div>
      <p class="muted small" style="margin-top:10px">Save this ID number. You can use it or your email to sign in.</p>
    </div>
    <div class="card card-pad mb" style="text-align:left">
      <div class="section-title" style="font-size:13px;margin-bottom:6px">About your verification photos</div>
      <p class="muted small">Your ID photo and selfie can only be viewed by the SK Barangay Chairman of your barangay. Every time they are opened, it is recorded in the audit log. Once the chairman acts on your registration, both photos are permanently deleted.</p>
    </div>
    <a class="btn btn-primary btn-block" href="<?= e(url('index.php')) ?>" style="padding:12px">Back to login →</a>
  </div>
<?php else: ?>
  <div class="mobile-head">
    <a href="<?= e(url('index.php')) ?>" data-back aria-label="Back">←</a>
    <div><strong>KK Member Registration</strong><small data-step-label>Step 1 of 3</small></div>
  </div>
  <div class="steps" aria-hidden="true"><span class="on" data-bar="1"></span><span data-bar="2"></span><span data-bar="3"></span></div>

  <form class="mobile-body" method="post" enctype="multipart/form-data" novalidate id="reg-form" data-first-step="<?= $firstErrorStep ?>">
    <?= csrf_field() ?>
    <?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php elseif ($errors): ?><div class="alert alert-error">Please correct the highlighted fields.</div><?php endif; ?>

    <section data-step="1">
      <div class="card card-pad mb">
        <div class="section-title mb" style="font-size:14px">Personal Information</div>
        <div class="field"><label>First Name <span class="req">*</span></label><input class="input<?= isset($errors['first_name']) ? ' error' : '' ?>" name="first_name" value="<?= e($f['first_name']) ?>" required><?= fe($errors, 'first_name') ?></div>
        <div class="field"><label>Middle Name (optional)</label><input class="input" name="middle_name" value="<?= e($f['middle_name']) ?>"></div>
        <div class="field"><label>Last Name <span class="req">*</span></label><input class="input<?= isset($errors['last_name']) ? ' error' : '' ?>" name="last_name" value="<?= e($f['last_name']) ?>" required><?= fe($errors, 'last_name') ?></div>
        <div class="field"><label>Date of Birth <span class="req">*</span></label><input class="input<?= isset($errors['birthdate']) ? ' error' : '' ?>" type="date" name="birthdate" value="<?= e($f['birthdate']) ?>" required data-age><div class="hint mono" data-age-out></div><?= fe($errors, 'birthdate') ?></div>
        <div class="field"><label>Sex assigned at birth <span class="req">*</span></label>
          <select class="input<?= isset($errors['sex']) ? ' error' : '' ?>" name="sex" required><option value="">Select…</option><option<?= sel('Male', $f['sex']) ?>>Male</option><option<?= sel('Female', $f['sex']) ?>>Female</option></select><?= fe($errors, 'sex') ?></div>
        <div class="field"><label>Barangay <span class="req">*</span></label>
          <select class="input<?= isset($errors['barangay_id']) ? ' error' : '' ?>" name="barangay_id" required><option value="">Select barangay…</option>
            <?php foreach ($barangays as $b): ?><option value="<?= (int) $b['barangay_id'] ?>"<?= sel((string) $b['barangay_id'], $f['barangay_id']) ?>><?= e($b['barangay_name']) ?></option><?php endforeach; ?>
          </select><div class="hint">The SK Chairman of this barangay will verify your registration.</div><?= fe($errors, 'barangay_id') ?></div>
        <div class="field"><label>Civil Status</label>
          <select class="input" name="civil_status"><option value="">Select…</option><?php foreach (['Single', 'Married', 'Widowed'] as $o): ?><option<?= sel($o, $f['civil_status']) ?>><?= $o ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Highest Educational Attainment</label>
          <select class="input" name="education"><option value="">Select…</option><?php foreach (EDUCATION as $o): ?><option<?= sel($o, $f['education']) ?>><?= e($o) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="card card-pad mb">
        <div class="section-title" style="font-size:14px">Youth Profile</div>
        <p class="muted small mb">Tell us about your organization, education status, and community participation.</p>
        <div class="field"><label>Do you belong to a youth organization? <span class="req">*</span></label>
          <select class="input<?= isset($errors['youth_org']) ? ' error' : '' ?>" name="youth_org" required data-org><option value="">Select an answer…</option>
            <?php foreach (ORGS as $v => $l): ?><option value="<?= e($v) ?>"<?= sel($v, $f['youth_org']) ?>><?= e($l) ?></option><?php endforeach; ?></select><?= fe($errors, 'youth_org') ?></div>
        <div class="field" data-org-other<?= $f['youth_org'] === 'Others' ? '' : ' hidden' ?>><label>Specify your organization <span class="req">*</span></label><input class="input<?= isset($errors['youth_org_other']) ? ' error' : '' ?>" name="youth_org_other" value="<?= e($f['youth_org_other']) ?>" placeholder="Enter the organization name"><?= fe($errors, 'youth_org_other') ?></div>
        <div class="field"><label>Current school status <span class="req">*</span></label>
          <select class="input<?= isset($errors['school_status']) ? ' error' : '' ?>" name="school_status" required><option value="">Select an answer…</option><?php foreach (['In-School Youth', 'Out-of-School Youth'] as $o): ?><option<?= sel($o, $f['school_status']) ?>><?= $o ?></option><?php endforeach; ?></select><?= fe($errors, 'school_status') ?></div>
        <div class="field"><label>Are you a person with disability (PWD)? <span class="req">*</span></label>
          <select class="input<?= isset($errors['is_pwd']) ? ' error' : '' ?>" name="is_pwd" required><option value="">Select an answer…</option><option<?= sel('Yes', $f['is_pwd']) ?>>Yes</option><option<?= sel('No', $f['is_pwd']) ?>>No</option></select><?= fe($errors, 'is_pwd') ?></div>
        <div class="field"><label>Have you attended an SK Assembly? <span class="req">*</span></label>
          <select class="input<?= isset($errors['attended_assembly']) ? ' error' : '' ?>" name="attended_assembly" required><option value="">Select an answer…</option><option<?= sel('Yes', $f['attended_assembly']) ?>>Yes</option><option<?= sel('No', $f['attended_assembly']) ?>>No</option></select><?= fe($errors, 'attended_assembly') ?></div>
      </div>
      <button type="button" class="btn btn-primary btn-block" data-next style="padding:12px;font-size:14px">Continue →</button>
    </section>

    <section data-step="2">
      <div class="card card-pad mb">
        <div class="section-title mb" style="font-size:14px">Contact Information</div>
        <div class="field"><label>Email Address <span class="req">*</span></label><input class="input<?= isset($errors['email']) ? ' error' : '' ?>" type="email" name="email" value="<?= e($f['email']) ?>" required autocomplete="email"><?= fe($errors, 'email') ?></div>
        <div class="field"><label>Mobile Number <span class="req">*</span></label><input class="input<?= isset($errors['mobile']) ? ' error' : '' ?>" type="tel" name="mobile" value="<?= e($f['mobile']) ?>" placeholder="09XXXXXXXXX" required><?= fe($errors, 'mobile') ?></div>
        <div class="field"><label>Password <span class="req">*</span></label><input class="input<?= isset($errors['password']) ? ' error' : '' ?>" type="password" name="password" autocomplete="new-password" required minlength="8"><div class="hint">At least 8 characters.</div><?= fe($errors, 'password') ?></div>
        <div class="field"><label>Confirm Password <span class="req">*</span></label><input class="input<?= isset($errors['password_confirm']) ? ' error' : '' ?>" type="password" name="password_confirm" autocomplete="new-password" required><?= fe($errors, 'password_confirm') ?></div>
      </div>
      <div class="card card-pad mb">
        <div class="section-title" style="font-size:14px">Identity Verification</div>
        <p class="muted small mb">Upload a photo of one of the following: School ID, National ID (PhilSys), or PSA Birth Certificate.</p>
        <div class="field"><label>ID type <span class="req">*</span></label>
          <select class="input<?= isset($errors['id_type']) ? ' error' : '' ?>" name="id_type" required><option value="">Select…</option><?php foreach (ID_DOCS as $o): ?><option<?= sel($o, $f['id_type']) ?>><?= e($o) ?></option><?php endforeach; ?></select><?= fe($errors, 'id_type') ?></div>
        <label class="upload-box"<?= isset($errors['id_file']) ? ' style="border-color:var(--red)"' : '' ?>>
          <span class="muted">Tap to upload or take a photo of your ID</span><br><span class="mono small muted">JPG, PNG or PDF · max 5 MB</span>
          <input type="file" name="id_file" accept="image/jpeg,image/png,application/pdf" capture="environment" required>
        </label>
        <?= fe($errors, 'id_file') ?>
      </div>
      <div class="card card-pad mb" style="background:var(--surface-canvas)">
        <div class="section-title" style="font-size:13px;margin-bottom:8px">Data Privacy Consent</div>
        <p class="muted small">Under Republic Act No. 10173 (Data Privacy Act of 2012), your personal information, ID photo, and selfie will be collected and stored securely by the SK Federation. Your photos will be deleted once the SK Chairman of your barangay acts on your registration. Every time someone views your photos, it is recorded in the audit log.</p>
        <label class="check"><input type="checkbox" name="consent" value="1" required<?= !empty($_POST['consent']) ? ' checked' : '' ?>><span>I have read and agree to how my personal data and identity documents will be used.</span></label>
        <?= fe($errors, 'consent') ?>
      </div>
      <button type="button" class="btn btn-primary btn-block" data-next style="padding:12px;font-size:14px">Proceed to Selfie Verification →</button>
    </section>

    <section data-step="3">
      <div class="card card-pad mb" style="text-align:center">
        <div class="section-title" style="font-size:15px;margin-bottom:12px">Take your selfie</div>
        <div class="selfie-preview" data-selfie-preview>Position your face inside the oval</div>
        <ol class="muted small" style="text-align:left;max-width:280px;margin:12px auto">
          <li>Make sure your full face is visible</li>
          <li>Find a bright spot — avoid shadows</li>
          <li>Remove glasses or hats if you can</li>
        </ol>
        <label class="btn btn-secondary">Open camera
          <input type="file" name="selfie" accept="image/jpeg,image/png" capture="user" required style="display:none" data-selfie>
        </label>
        <?= fe($errors, 'selfie') ?>
        <p class="muted small" style="margin-top:14px">Your ID photo and selfie are only used to verify your identity. Once the SK barangay acts on your registration, both images are permanently deleted from our servers.</p>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="padding:12px;font-size:14px">Submit registration →</button>
    </section>
  </form>
<?php endif; ?>
</div>
<script>
(function () {
  var form = document.getElementById('reg-form');
  if (!form) return;
  var steps = form.querySelectorAll('[data-step]'), label = document.querySelector('[data-step-label]'), back = document.querySelector('[data-back]');
  var current = parseInt(form.getAttribute('data-first-step'), 10) || 1;
  function show(n) {
    current = n;
    steps.forEach(function (s) { s.hidden = parseInt(s.getAttribute('data-step'), 10) !== n; });
    document.querySelectorAll('[data-bar]').forEach(function (b) { b.classList.toggle('on', parseInt(b.getAttribute('data-bar'), 10) <= n); });
    label.textContent = 'Step ' + n + ' of 3';
    window.scrollTo(0, 0);
  }
  function valid(section) {
    var ok = true;
    section.querySelectorAll('[required]').forEach(function (el) {
      if (el.closest('[hidden]')) return;
      var bad = el.type === 'checkbox' ? !el.checked : (el.type === 'file' ? !el.files.length : !el.value.trim());
      el.classList.toggle('error', bad);
      if (bad) ok = false;
    });
    return ok;
  }
  form.querySelectorAll('[data-next]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (valid(steps[current - 1])) show(current + 1);
      else alert('Please complete the required fields.');
    });
  });
  back.addEventListener('click', function (e) { if (current > 1) { e.preventDefault(); show(current - 1); } });
  var dob = form.querySelector('[data-age]'), out = form.querySelector('[data-age-out]');
  function age() {
    if (!dob.value) { out.textContent = ''; return; }
    var d = new Date(dob.value), t = new Date(), a = t.getFullYear() - d.getFullYear();
    if (t.getMonth() < d.getMonth() || (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())) a--;
    var ok = a >= 15 && a <= 30;
    out.textContent = 'Age: ' + a + ' years old ' + (ok ? '✓' : '— must be 15–30 to register');
    out.style.color = ok ? 'var(--blue)' : 'var(--red)';
  }
  dob.addEventListener('change', age); age();
  var org = form.querySelector('[data-org]'), other = form.querySelector('[data-org-other]');
  org.addEventListener('change', function () { other.hidden = org.value !== 'Others'; });
  var selfie = form.querySelector('[data-selfie]'), prev = form.querySelector('[data-selfie-preview]');
  selfie.addEventListener('change', function () {
    if (!selfie.files.length) return;
    var img = new Image(); img.alt = 'Selfie preview'; img.src = URL.createObjectURL(selfie.files[0]);
    prev.innerHTML = ''; prev.appendChild(img);
  });
  form.addEventListener('submit', function (e) { if (!valid(steps[2])) { e.preventDefault(); alert('Take your selfie before submitting.'); } });
  show(current);
})();
</script>
</body>
</html>
