<?php
/**
 * Administrator: user accounts, roles and SK Chairman designation.
 * The Administrator does NOT verify KK registrations — that is the
 * barangay chairman's job (barangay/verify.php).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$barangays = q_all('SELECT * FROM barangays ORDER BY barangay_name');
$brgyIds = array_map('intval', array_column($barangays, 'barangay_id'));

function valid_account_input(array $brgyIds, ?int $editingId): array
{
    $role = post_int('role_id');
    $brgy = post_int('barangay_id');
    $email = post('email');
    $errors = [];
    if (!in_array($role, [ROLE_ADMIN, ROLE_FEDERATION, ROLE_BARANGAY, ROLE_MEMBER], true)) {
        $errors[] = 'Choose a role.';
    }
    if (in_array($role, [ROLE_BARANGAY, ROLE_MEMBER], true) && !in_array($brgy, $brgyIds, true)) {
        $errors[] = 'Barangay officials and KK members must belong to a barangay.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (q_val('SELECT 1 FROM users WHERE email = ? AND user_id <> ?', [$email, $editingId ?? 0])) {
        $errors[] = 'That email is already used by another account.';
    }
    return [$errors, $role, in_array($role, [ROLE_BARANGAY, ROLE_MEMBER], true) ? $brgy : null, $email];
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'create') {
        [$errors, $role, $brgy, $email] = valid_account_input($brgyIds, null);
        $username = post('username');
        $password = (string) ($_POST['password'] ?? '');
        if ($role === ROLE_MEMBER) {
            $errors[] = 'KK members register themselves and are verified by their barangay chairman.';
        }
        if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $username)) {
            $errors[] = 'Username must be 3–50 letters, numbers, dots, dashes or underscores.';
        } elseif (q_val('SELECT 1 FROM users WHERE username = ?', [$username])) {
            $errors[] = 'That username is taken.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'The initial password must have at least 8 characters.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            q('INSERT INTO users (role_id, barangay_id, username, email, password_hash, verification_status, date_verified, is_active) VALUES (?, ?, ?, ?, ?, "verified", NOW(), 1)', [
                $role, $brgy, $username, $email, password_hash($password, PASSWORD_DEFAULT),
            ]);
            audit('ACCOUNT_CREATED', 'Created ' . ROLE_LABELS[$role] . ' account "' . $username . '"' . ($brgy ? ' for barangay #' . $brgy : ''));
            flash('success', 'Account "' . $username . '" created. Give the user their initial password privately and ask them to change it.');
        }
        redirect('admin/users.php');
    }

    if ($action === 'update') {
        $id = post_int('user_id');
        $target = q_one('SELECT * FROM users WHERE user_id = ?', [$id]);
        [$errors, $role, $brgy, $email] = valid_account_input($brgyIds, $id);
        $active = post('is_active') === '1' ? 1 : 0;
        if (!$target) {
            $errors[] = 'Account not found.';
        } elseif ($id === uid() && ($active === 0 || $role !== ROLE_ADMIN)) {
            $errors[] = 'You cannot deactivate your own account or remove your own administrator role.';
        } elseif ((int) $target['role_id'] === ROLE_MEMBER && $role !== ROLE_MEMBER) {
            $errors[] = 'A KK member account cannot be converted into an official account.';
        }
        $newPass = (string) ($_POST['new_password'] ?? '');
        if ($newPass !== '' && strlen($newPass) < 8) {
            $errors[] = 'A reset password must have at least 8 characters.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            q('UPDATE users SET role_id = ?, barangay_id = ?, email = ?, is_active = ? WHERE user_id = ?', [$role, $brgy, $email, $active, $id]);
            if ($newPass !== '') {
                q('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $id]);
            }
            // A chairman who moves barangay, changes role or is deactivated loses the designation.
            q('UPDATE barangays SET chairman_id = NULL WHERE chairman_id = ? AND (barangay_id <> ? OR ? <> ? OR ? = 0)', [$id, $brgy ?? 0, $role, ROLE_BARANGAY, $active]);
            $pdo->commit();
            audit('ACCOUNT_UPDATED', 'Updated account "' . $target['username'] . '"' . ($active ? '' : ' (deactivated)') . ($newPass !== '' ? ' and reset its password' : ''));
            flash('success', 'Account "' . $target['username'] . '" updated.');
        }
        redirect('admin/users.php' . (get('tab') ? '?tab=' . urlencode(get('tab')) : ''));
    }

    if ($action === 'assign_chairman') {
        $brgy = post_int('barangay_id');
        $chair = post_int('chairman_id');
        $ok = in_array($brgy, $brgyIds, true) && ($chair === 0 || q_val('SELECT 1 FROM users WHERE user_id = ? AND role_id = ? AND barangay_id = ? AND is_active = 1', [$chair, ROLE_BARANGAY, $brgy]));
        if (!$ok) {
            flash('error', 'Choose an active SK Barangay Official account from that barangay.');
        } else {
            q('UPDATE barangays SET chairman_id = ? WHERE barangay_id = ?', [$chair ?: null, $brgy]);
            $name = q_val('SELECT barangay_name FROM barangays WHERE barangay_id = ?', [$brgy]);
            $uname = $chair ? q_val('SELECT username FROM users WHERE user_id = ?', [$chair]) : 'none';
            audit('CHAIRMAN_ASSIGNED', $name . ' · SK Chairman set to ' . $uname);
            if ($chair) {
                notify($chair, 'You are now the SK Chairman of ' . $name, 'You can now verify KK member registrations for your barangay.', 'barangay/verify.php');
            }
            flash('success', 'SK Chairman for ' . $name . ' updated.');
        }
        redirect('admin/users.php?tab=roles');
    }
}

$tab = get('tab') === 'roles' ? 'roles' : 'accounts';
$roleFilter = get_int('role');
$search = get('q');
$where = ['1=1'];
$params = [];
if ($roleFilter) {
    $where[] = 'u.role_id = ?';
    $params[] = $roleFilter;
} else {
    $where[] = 'u.role_id <> ' . ROLE_MEMBER;
}
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR u.email LIKE ? OR p.full_name LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$accounts = q_all('SELECT u.*, b.barangay_name, p.full_name, (SELECT 1 FROM barangays bb WHERE bb.chairman_id = u.user_id LIMIT 1) AS is_chair
                     FROM users u LEFT JOIN barangays b ON b.barangay_id = u.barangay_id LEFT JOIN youth_profiles p ON p.user_id = u.user_id
                    WHERE ' . implode(' AND ', $where) . ' ORDER BY u.role_id, b.barangay_name, u.username LIMIT 500', $params);
$roleCounts = [];
foreach (q_all('SELECT role_id, COUNT(*) c FROM users GROUP BY role_id') as $r) {
    $roleCounts[(int) $r['role_id']] = (int) $r['c'];
}
$assignments = q_all('SELECT b.*, u.username, u.email, u.is_active FROM barangays b LEFT JOIN users u ON u.user_id = b.chairman_id ORDER BY b.barangay_name');
$officialsByBrgy = [];
foreach (q_all('SELECT user_id, username, barangay_id FROM users WHERE role_id = ? AND is_active = 1 ORDER BY username', [ROLE_BARANGAY]) as $o) {
    $officialsByBrgy[(int) $o['barangay_id']][] = $o;
}
$assignedCount = count(array_filter($assignments, fn ($a) => $a['chairman_id']));

$title = 'User Management';
$nav = 'users';
$crumb = 'Administrator · Users & Roles';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">User Management</h1>
    <div class="page-sub">Official accounts, access roles, and SK Chairman assignments</div>
  </div>
  <button class="btn btn-primary" data-open="dlg-create">+ Add Account</button>
</div>

<div class="scope-banner">KK member registration approvals are handled by each barangay's SK Chairman — not by the Administrator. This screen manages system accounts only.</div>

<div class="tabs">
  <a class="tab<?= $tab === 'accounts' ? ' active' : '' ?>" href="?tab=accounts">User Accounts</a>
  <a class="tab<?= $tab === 'roles' ? ' active' : '' ?>" href="?tab=roles">Roles &amp; Chairman Assignments</a>
</div>

<?php if ($tab === 'accounts'): ?>
  <form class="card filters mb" method="get">
    <input type="hidden" name="tab" value="accounts">
    <input class="input" name="q" value="<?= e($search) ?>" placeholder="Search username, email or name…" style="flex:1;max-width:280px">
    <select class="input" name="role" data-autosubmit>
      <option value="0">All official roles</option>
      <?php foreach (ROLE_LABELS as $rid => $rl): ?><option value="<?= $rid ?>"<?= $roleFilter === $rid ? ' selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
  </form>
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Barangay</th><th>Status</th><th>Last Sign-in</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $a): ?>
        <tr>
          <td style="font-weight:500"><?= e($a['full_name'] ?: $a['username']) ?><?php if ($a['is_chair']): ?> <?= pill('approved', 'Chairman') ?><?php endif; ?><div class="sub"><?= e($a['username']) ?></div></td>
          <td class="sub"><?= e($a['email']) ?></td>
          <td><?= e(ROLE_LABELS[(int) $a['role_id']]) ?></td>
          <td class="muted"><?= e($a['barangay_name'] ?: 'Federation') ?></td>
          <td><?= (int) $a['is_active'] ? pill('completed', 'Active') : pill('inactive', 'Inactive') ?><?= $a['verification_status'] !== 'verified' ? ' ' . pill($a['verification_status']) : '' ?></td>
          <td class="sub"><?= e(fmt_datetime($a['last_login'])) ?></td>
          <td class="right"><button class="btn btn-secondary btn-sm" data-open="dlg-edit" data-fill='<?= e(json_encode(['user_id' => $a['user_id'], 'email' => $a['email'], 'role_id' => $a['role_id'], 'barangay_id' => $a['barangay_id'] ?? '', 'is_active' => $a['is_active'], 'uname' => $a['username']])) ?>'>Edit</button></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$accounts): ?><tr><td colspan="7" class="empty">No accounts match.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="grid grid-3 mb">
    <?php foreach ([[ROLE_ADMIN, 'System setup, accounts, settings, and logs'], [ROLE_FEDERATION, 'Federation-wide monitoring, budget allocation and approval'], [ROLE_BARANGAY, 'Barangay-scoped programs, inventory, budget and KK verification']] as [$rid, $desc]): ?>
      <div class="card card-pad">
        <div class="section-title" style="font-size:13px"><?= e(ROLE_LABELS[$rid]) ?></div>
        <div class="muted small" style="min-height:34px"><?= e($desc) ?></div>
        <div class="record-id" style="margin-top:10px"><?= $roleCounts[$rid] ?? 0 ?> accounts</div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card table-wrap">
    <div class="card-head">
      <div><div class="section-title" style="font-size:14px">SK Chairman Assignments</div><div class="section-sub">One designated SK Chairman account per barangay. Only the chairman verifies KK registrations.</div></div>
      <?= pill($assignedCount === count($assignments) ? 'approved' : 'pending', $assignedCount . ' of ' . count($assignments) . ' assigned') ?>
    </div>
    <table class="table">
      <thead><tr><th>Barangay</th><th>Assigned Chairman</th><th>Email</th><th>Status</th><th>Change assignment</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $as): ?>
        <tr>
          <td style="font-weight:500"><?= e($as['barangay_name']) ?></td>
          <td class="<?= $as['chairman_id'] ? '' : 'red' ?>"><?= e($as['username'] ?: 'Not assigned') ?></td>
          <td class="sub"><?= e($as['email'] ?: '—') ?></td>
          <td><?= !$as['chairman_id'] ? pill('pending', 'Unassigned') : ((int) $as['is_active'] ? pill('approved', 'Assigned') : pill('inactive', 'Inactive')) ?></td>
          <td>
            <?php $opts = $officialsByBrgy[(int) $as['barangay_id']] ?? []; ?>
            <?php if (!$opts): ?><span class="muted small">Create an SK Barangay Official account for this barangay first.</span>
            <?php else: ?>
            <form method="post" class="row">
              <?= csrf_field() ?><input type="hidden" name="action" value="assign_chairman"><input type="hidden" name="barangay_id" value="<?= (int) $as['barangay_id'] ?>">
              <select class="input" name="chairman_id" style="width:auto;padding:5px 8px;font-size:12px">
                <option value="0">— None —</option>
                <?php foreach ($opts as $o): ?><option value="<?= (int) $o['user_id'] ?>"<?= (int) $as['chairman_id'] === (int) $o['user_id'] ? ' selected' : '' ?>><?= e($o['username']) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-secondary btn-sm" type="submit"><?= $as['chairman_id'] ? 'Change' : 'Assign' ?></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
$brgyOptions = '';
foreach ($barangays as $b) {
    $brgyOptions .= '<option value="' . (int) $b['barangay_id'] . '">' . e($b['barangay_name']) . '</option>';
}
?>
<dialog class="modal" id="dlg-create">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="modal-head"><div><div class="modal-title">Add Official Account</div><div class="muted small">KK members register themselves.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="form-grid">
      <div class="field"><label>Username</label><input class="input" name="username" required pattern="[A-Za-z0-9._\-]{3,50}"></div>
      <div class="field"><label>Email</label><input class="input" type="email" name="email" required></div>
      <div class="field"><label>Role</label><select class="input" name="role_id" required>
        <option value="<?= ROLE_BARANGAY ?>">SK Barangay Official</option><option value="<?= ROLE_FEDERATION ?>">SK Federation Official</option><option value="<?= ROLE_ADMIN ?>">Administrator</option></select></div>
      <div class="field"><label>Barangay</label><select class="input" name="barangay_id"><option value="0">— Federation (none) —</option><?= $brgyOptions ?></select><div class="hint">Required for SK Barangay Officials.</div></div>
      <div class="field full"><label>Initial password</label><input class="input" type="text" name="password" minlength="8" required autocomplete="off"><div class="hint">Share it privately. The user should change it after first sign-in.</div></div>
    </div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create account</button></div>
  </form>
</dialog>

<dialog class="modal" id="dlg-edit">
  <form method="post" action="?tab=<?= e($tab) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id">
    <div class="modal-head"><div><div class="modal-title">Edit Account</div><div class="mono small muted" data-text="uname"></div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="form-grid">
      <div class="field full"><label>Email</label><input class="input" type="email" name="email" required></div>
      <div class="field"><label>Role</label><select class="input" name="role_id">
        <?php foreach (ROLE_LABELS as $rid => $rl): ?><option value="<?= $rid ?>"><?= e($rl) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Barangay</label><select class="input" name="barangay_id"><option value="0">— Federation (none) —</option><?= $brgyOptions ?></select></div>
      <div class="field"><label>Status</label><select class="input" name="is_active"><option value="1">Active</option><option value="0">Inactive (deactivated)</option></select></div>
      <div class="field"><label>Reset password (optional)</label><input class="input" type="text" name="new_password" minlength="8" autocomplete="off" placeholder="Leave blank to keep"></div>
    </div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php';
