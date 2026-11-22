<?php
/**
 * Youth Profiling (KK masterlist).
 * Barangay official: maintains profiles of own barangay only.
 * Federation: read-only across barangays.
 * Included by barangay/youth.php and federation/youth.php after require_role().
 */
$canEdit = role() === ROLE_BARANGAY;
$scope = $canEdit ? my_barangay() : (get_int('barangay') ?: null);
$base = $canEdit ? 'barangay/youth.php' : 'federation/youth.php';
const YP_EDUCATION = ['Elementary Level', 'Elementary Graduate', 'High School Level', 'High School Graduate', 'Senior High School Level', 'Senior High School Graduate', 'College Level', 'College Graduate', 'Vocational/Technical'];

if ($canEdit && is_post()) {
    csrf_check();
    $id = post_int('profile_id');
    if ($id) {
        // Ownership is checked in the WHERE clause, not only in the navigation.
        $existing = q_one('SELECT * FROM youth_profiles WHERE profile_id = ? AND barangay_id = ?', [$id, my_barangay()]);
        if (!$existing) {
            require_barangay_access(null);
        }
    }
    if (post('action') === 'delete' && $id) {
        $used = q_val('SELECT (SELECT COUNT(*) FROM attendance WHERE profile_id = ?) + (SELECT COUNT(*) FROM evaluation_responses WHERE profile_id = ?) + (SELECT COUNT(*) FROM equipment_requests WHERE requested_by = ?) + (SELECT COUNT(*) FROM feedback WHERE profile_id = ?)', [$id, $id, $id, $id]);
        if ($existing['user_id'] || $used) {
            flash('error', 'This profile has an account or recorded attendance, evaluations or requests, so it cannot be deleted.');
        } else {
            q('DELETE FROM youth_profiles WHERE profile_id = ? AND barangay_id = ?', [$id, my_barangay()]);
            audit('YOUTH_PROFILE_DELETED', 'Deleted youth profile #' . $id);
            flash('success', 'Profile deleted.');
        }
        redirect($base);
    }

    $name = post('full_name');
    $birth = post('birthdate');
    $a = $birth !== '' && strtotime($birth) ? age($birth) : null;
    $mobile = post('contact_no');
    if ($name === '' || $a === null) {
        flash('error', 'Full name and a valid date of birth are required.');
    } elseif ($a < 15 || $a > 30) {
        flash('error', 'Katipunan ng Kabataan members must be 15 to 30 years old.');
    } elseif ($mobile !== '' && !preg_match('/^09\d{9}$/', $mobile)) {
        flash('error', 'Use the format 09XXXXXXXXX for the mobile number.');
    } else {
        $vals = [
            $name, $birth, in_array(post('sex'), ['Male', 'Female'], true) ? post('sex') : null, $mobile ?: null, post('address') ?: null,
            post('civil_status') ?: null, post('education') ?: null, post('work_status') ?: null, post('youth_org') ?: null,
            in_array(post('school_status'), ['In-School Youth', 'Out-of-School Youth'], true) ? post('school_status') : null,
            post('is_pwd') === '' ? null : (post('is_pwd') === '1' ? 1 : 0), post('attended_assembly') === '' ? null : (post('attended_assembly') === '1' ? 1 : 0),
        ];
        if ($id) {
            q('UPDATE youth_profiles SET full_name = ?, birthdate = ?, sex = ?, contact_no = ?, address = ?, civil_status = ?, education = ?, work_status = ?, youth_org = ?, school_status = ?, is_pwd = ?, attended_assembly = ?
                WHERE profile_id = ? AND barangay_id = ?', array_merge($vals, [$id, my_barangay()]));
            audit('YOUTH_PROFILE_UPDATED', 'Updated youth profile #' . $id);
            flash('success', 'Profile of ' . $name . ' updated.');
        } else {
            q('INSERT INTO youth_profiles (full_name, birthdate, sex, contact_no, address, civil_status, education, work_status, youth_org, school_status, is_pwd, attended_assembly, barangay_id)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($vals, [my_barangay()]));
            audit('YOUTH_PROFILE_CREATED', 'Encoded youth profile #' . db()->lastInsertId());
            flash('success', $name . ' added to the KK masterlist.');
        }
    }
    redirect($base);
}

$search = get('q');
$bracket = get('age');
$school = get('school');
$where = ['(u.user_id IS NULL OR u.verification_status = "verified")'];
$params = [];
if ($scope !== null) {
    $where[] = 'yp.barangay_id = ?';
    $params[] = $scope;
}
if ($search !== '') {
    if (preg_match('/^KK-\d{4}-(\d+)$/i', $search, $m)) {
        $where[] = 'yp.profile_id = ?';
        $params[] = (int) $m[1];
    } else {
        $where[] = 'yp.full_name LIKE ?';
        $params[] = '%' . $search . '%';
    }
}
$ranges = ['15-17' => [15, 17], '18-24' => [18, 24], '25-30' => [25, 30]];
if (isset($ranges[$bracket])) {
    $where[] = 'TIMESTAMPDIFF(YEAR, yp.birthdate, CURDATE()) BETWEEN ? AND ?';
    array_push($params, ...$ranges[$bracket]);
}
if (in_array($school, ['In-School Youth', 'Out-of-School Youth'], true)) {
    $where[] = 'yp.school_status = ?';
    $params[] = $school;
}
$rows = q_all('SELECT yp.*, b.barangay_name, u.email, u.date_verified,
                      (SELECT COUNT(*) FROM attendance a WHERE a.profile_id = yp.profile_id) AS attended
                 FROM youth_profiles yp JOIN barangays b ON b.barangay_id = yp.barangay_id LEFT JOIN users u ON u.user_id = yp.user_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY yp.full_name', $params);

if (get('export') === 'csv') {
    audit('YOUTH_EXPORTED', count($rows) . ' youth profiles exported');
    send_csv('kk-masterlist-' . date('Ymd') . '.csv',
        ['KK ID', 'Full name', 'Birthdate', 'Age', 'Sex', 'Barangay', 'Civil status', 'Education', 'Work status', 'School status', 'Youth organization', 'PWD', 'Attended SK Assembly', 'Mobile', 'Activities attended'],
        array_map(fn ($r) => [kk_id((int) $r['profile_id'], $r['date_encoded']), $r['full_name'], $r['birthdate'], age($r['birthdate']), $r['sex'], $r['barangay_name'], $r['civil_status'], $r['education'], $r['work_status'], $r['school_status'], $r['youth_org'], $r['is_pwd'] === null ? '' : ((int) $r['is_pwd'] ? 'Yes' : 'No'), $r['attended_assembly'] === null ? '' : ((int) $r['attended_assembly'] ? 'Yes' : 'No'), $r['contact_no'], $r['attended']], $rows));
}

$male = count(array_filter($rows, fn ($r) => $r['sex'] === 'Male'));
$female = count(array_filter($rows, fn ($r) => $r['sex'] === 'Female'));
$avgAge = $rows ? (int) round(array_sum(array_map(fn ($r) => age($r['birthdate']), $rows)) / count($rows)) : 0;
$view = get_int('view') ? (array_values(array_filter($rows, fn ($r) => (int) $r['profile_id'] === get_int('view')))[0] ?? null) : null;
$barangays = $canEdit ? [] : q_all('SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name');

$title = 'Youth Profiling';
$nav = 'youth';
$crumb = ($canEdit ? 'Members' : 'Monitoring') . ' · Youth Profiling';
require APP_ROOT . '/includes/header.php';
$yn = fn ($v) => $v === null ? '—' : ((int) $v ? 'Yes' : 'No');
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Youth Profiling</h1>
    <div class="page-sub">KK Masterlist — <?= count($rows) ?> members shown<?= $canEdit ? ' · ' . e(current_user()['barangay_name']) : '' ?></div>
  </div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">↓ Export to Spreadsheet</a>
    <?php if ($canEdit): ?><button class="btn btn-primary" data-open="dlg-profile" data-fill='{"profile_id":"0"}'>+ Encode Youth</button><?php endif; ?>
  </div>
</div>

<?php if (!$canEdit): ?><div class="scope-banner">Read-only. Youth profiles are encoded and updated by each barangay's SK officials; the federation views them for monitoring.</div><?php endif; ?>

<div class="grid grid-4 mb">
  <div class="card stat"><div class="stat-label">Total Members</div><div class="stat-value"><?= count($rows) ?></div></div>
  <div class="card stat"><div class="stat-label">Male</div><div class="stat-value blue"><?= $male ?></div></div>
  <div class="card stat"><div class="stat-label">Female</div><div class="stat-value blue"><?= $female ?></div></div>
  <div class="card stat"><div class="stat-label">Average Age</div><div class="stat-value"><?= $avgAge ?></div></div>
</div>

<form class="card filters mb" method="get">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Search by name or KK ID…" style="flex:1 1 200px;max-width:280px">
  <?php if (!$canEdit): ?>
    <select class="input" name="barangay" data-autosubmit><option value="0">All barangays</option>
      <?php foreach ($barangays as $b): ?><option value="<?= (int) $b['barangay_id'] ?>"<?= $scope === (int) $b['barangay_id'] ? ' selected' : '' ?>><?= e($b['barangay_name']) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <select class="input" name="age" data-autosubmit><option value="">All ages</option><?php foreach (array_keys($ranges) as $r): ?><option<?= $bracket === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
  <select class="input" name="school" data-autosubmit><option value="">All school statuses</option><?php foreach (['In-School Youth', 'Out-of-School Youth'] as $s): ?><option<?= $school === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
  <button class="btn btn-secondary">Search</button>
</form>

<div class="card table-wrap">
  <table class="table" style="min-width:960px">
    <thead><tr><th>KK ID</th><th>Full Name</th><th>Age</th><th>Barangay</th><th>Sex</th><th>Education</th><th>School Status</th><th>Organization</th><th>Account</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="record-id nowrap"><?= e(kk_id((int) $r['profile_id'], $r['date_encoded'])) ?></td>
        <td style="font-weight:600"><?= e($r['full_name']) ?></td>
        <td class="mono muted"><?= age($r['birthdate']) ?></td>
        <td class="muted"><?= e($r['barangay_name']) ?></td>
        <td class="muted"><?= e($r['sex'] ?: '—') ?></td>
        <td class="muted small"><?= e($r['education'] ?: '—') ?></td>
        <td><?= $r['school_status'] ? pill('completed', $r['school_status']) : '—' ?></td>
        <td class="muted small"><?= e($r['youth_org'] ?: 'None') ?></td>
        <td><?= $r['user_id'] ? pill('verified', 'Registered') : pill('draft', 'Encoded') ?></td>
        <td class="right nowrap"><a class="btn btn-secondary btn-sm" href="?<?= e(http_build_query(array_merge($_GET, ['view' => $r['profile_id']]))) ?>">View profile</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" class="empty">No members match your search.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($view): $closeUrl = '?' . http_build_query(array_diff_key($_GET, ['view' => 1])); ?>
<dialog class="modal wide" data-autoopen id="dlg-view">
  <div class="modal-head" style="border-bottom:1px solid var(--border-default);padding-bottom:16px">
    <div class="row"><span class="avatar"><?= e(initials($view['full_name'])) ?></span>
      <div><div class="modal-title"><?= e($view['full_name']) ?></div><div class="row" style="gap:7px;margin-top:3px"><span class="record-id"><?= e(kk_id((int) $view['profile_id'], $view['date_encoded'])) ?></span><?= $view['user_id'] ? pill('approved', 'Approved member') : pill('draft', 'Encoded by barangay') ?></div></div></div>
    <a class="modal-close" href="<?= e($closeUrl) ?>" aria-label="Close" style="text-decoration:none;display:flex;align-items:center;justify-content:center">×</a>
  </div>
  <div class="detail-section-title">Personal Information</div>
  <div class="detail-grid">
    <div><div class="field-label">Full name</div><div class="field-value"><?= e($view['full_name']) ?></div></div>
    <div><div class="field-label">Date of birth</div><div class="field-value"><?= e(fmt_date($view['birthdate'])) ?> (<?= age($view['birthdate']) ?> years old)</div></div>
    <div><div class="field-label">Sex assigned at birth</div><div class="field-value"><?= e($view['sex'] ?: '—') ?></div></div>
    <div><div class="field-label">Barangay</div><div class="field-value"><?= e($view['barangay_name']) ?></div></div>
    <div><div class="field-label">Civil status</div><div class="field-value"><?= e($view['civil_status'] ?: '—') ?></div></div>
    <div><div class="field-label">Highest educational attainment</div><div class="field-value"><?= e($view['education'] ?: '—') ?></div></div>
    <div><div class="field-label">Work status</div><div class="field-value"><?= e($view['work_status'] ?: '—') ?></div></div>
    <div><div class="field-label">Address</div><div class="field-value"><?= e($view['address'] ?: '—') ?></div></div>
  </div>
  <div class="detail-section-title">Youth Profile</div>
  <div class="detail-grid">
    <div><div class="field-label">Youth organization</div><div class="field-value"><?= e($view['youth_org'] ?: 'No organization') ?></div></div>
    <div><div class="field-label">Current school status</div><div class="field-value"><?= e($view['school_status'] ?: '—') ?></div></div>
    <div><div class="field-label">Person with disability (PWD)</div><div class="field-value"><?= $yn($view['is_pwd']) ?></div></div>
    <div><div class="field-label">Attended an SK Assembly</div><div class="field-value"><?= $yn($view['attended_assembly']) ?></div></div>
  </div>
  <div class="detail-section-title">Contact &amp; Participation</div>
  <div class="detail-grid">
    <div><div class="field-label">Email address</div><div class="field-value"><?= e($view['email'] ?: '—') ?></div></div>
    <div><div class="field-label">Mobile number</div><div class="field-value"><?= e($view['contact_no'] ?: '—') ?></div></div>
    <div><div class="field-label">Activities attended</div><div class="field-value"><?= (int) $view['attended'] ?></div></div>
    <div><div class="field-label"><?= $view['user_id'] ? 'Verified on' : 'Encoded on' ?></div><div class="field-value"><?= e(fmt_date($view['user_id'] ? $view['date_verified'] : $view['date_encoded'])) ?></div></div>
  </div>
  <?php if ($view['user_id']): ?><p class="note-box mt">Identity and selfie images were deleted after verification in accordance with the registration privacy notice.</p><?php endif; ?>
  <div class="modal-actions">
    <?php if ($canEdit): ?>
      <?php if (!$view['user_id'] && !(int) $view['attended']): ?>
      <form method="post" data-confirm="Delete this youth profile? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="profile_id" value="<?= (int) $view['profile_id'] ?>"><button class="btn btn-danger">Delete</button></form>
      <?php endif; ?>
      <button class="btn btn-secondary" data-open="dlg-profile" data-fill='<?= e(json_encode(['profile_id' => $view['profile_id'], 'full_name' => $view['full_name'], 'birthdate' => $view['birthdate'], 'sex' => $view['sex'] ?? '', 'contact_no' => $view['contact_no'] ?? '', 'address' => $view['address'] ?? '', 'civil_status' => $view['civil_status'] ?? '', 'education' => $view['education'] ?? '', 'work_status' => $view['work_status'] ?? '', 'youth_org' => $view['youth_org'] ?? '', 'school_status' => $view['school_status'] ?? '', 'is_pwd' => $view['is_pwd'] ?? '', 'attended_assembly' => $view['attended_assembly'] ?? ''])) ?>'>Edit profile</button>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e($closeUrl) ?>">Done</a>
  </div>
</dialog>
<?php endif; ?>

<?php if ($canEdit): ?>
<dialog class="modal wide" id="dlg-profile">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="profile_id">
    <div class="modal-head"><div><div class="modal-title">Youth Profile</div><div class="muted small">For youth who attend without a registered account, or to correct a member's record.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="form-grid">
      <div class="field full"><label>Full name <span class="req">*</span></label><input class="input" name="full_name" required maxlength="255"></div>
      <div class="field"><label>Date of birth <span class="req">*</span></label><input class="input" type="date" name="birthdate" required></div>
      <div class="field"><label>Sex</label><select class="input" name="sex"><option value="">—</option><option>Male</option><option>Female</option></select></div>
      <div class="field"><label>Mobile number</label><input class="input" name="contact_no" placeholder="09XXXXXXXXX"></div>
      <div class="field"><label>Civil status</label><select class="input" name="civil_status"><option value="">—</option><option>Single</option><option>Married</option><option>Widowed</option></select></div>
      <div class="field full"><label>Address</label><input class="input" name="address" maxlength="255"></div>
      <div class="field"><label>Highest educational attainment</label><select class="input" name="education"><option value="">—</option><?php foreach (YP_EDUCATION as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Work status</label><input class="input" name="work_status" maxlength="100" placeholder="Student, Employed, Unemployed…"></div>
      <div class="field"><label>School status</label><select class="input" name="school_status"><option value="">—</option><option>In-School Youth</option><option>Out-of-School Youth</option></select></div>
      <div class="field"><label>Youth organization</label><input class="input" name="youth_org" maxlength="255"></div>
      <div class="field"><label>PWD</label><select class="input" name="is_pwd"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
      <div class="field"><label>Attended an SK Assembly</label><select class="input" name="attended_assembly"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
    </div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save profile</button></div>
  </form>
</dialog>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php';
