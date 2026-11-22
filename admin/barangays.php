<?php
/** Administrator: barangay directory and system-wide settings. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

const RETENTION = ['12 months', '24 months', '36 months', 'Indefinite'];

if (is_post()) {
    csrf_check();
    $action = post('action');
    $tab = 'barangays';

    if ($action === 'save_barangay') {
        $id = post_int('barangay_id');
        $name = post('barangay_name');
        $muni = post('municipality') ?: 'Nasipit';
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Enter the barangay name (up to 100 characters).');
        } elseif (q_val('SELECT 1 FROM barangays WHERE barangay_name = ? AND municipality = ? AND barangay_id <> ?', [$name, $muni, $id])) {
            flash('error', 'That barangay is already in the directory.');
        } elseif ($id) {
            q('UPDATE barangays SET barangay_name = ?, municipality = ? WHERE barangay_id = ?', [$name, $muni, $id]);
            audit('BARANGAY_UPDATED', 'Barangay #' . $id . ' renamed to ' . $name);
            flash('success', 'Barangay record updated.');
        } else {
            q('INSERT INTO barangays (barangay_name, municipality) VALUES (?, ?)', [$name, $muni]);
            audit('BARANGAY_CREATED', 'Added barangay ' . $name);
            flash('success', $name . ' added to the directory.');
        }
    }

    if ($action === 'settings') {
        $tab = 'settings';
        $name = post('system_name');
        $muni = post('municipality');
        $ret = post('audit_retention');
        $min = post_int('eligibility_min_activities');
        if ($name === '' || $muni === '' || !in_array($ret, RETENTION, true) || $min < 0 || $min > 100) {
            flash('error', 'Check the settings: every field is required and the eligibility minimum must be 0–100.');
        } else {
            set_setting('system_name', $name);
            set_setting('municipality', $muni);
            set_setting('audit_retention', $ret);
            set_setting('eligibility_min_activities', (string) $min);
            audit('SETTINGS_UPDATED', "Retention $ret, eligibility minimum $min activities");
            flash('success', 'System settings saved.');
        }
    }

    if ($action === 'maintenance') {
        $tab = 'settings';
        $on = setting('maintenance_mode') === '1' ? '0' : '1';
        set_setting('maintenance_mode', $on);
        audit('SETTINGS_UPDATED', 'Maintenance mode ' . ($on === '1' ? 'enabled' : 'disabled'));
        flash($on === '1' ? 'warning' : 'success', $on === '1' ? 'Maintenance mode is on. Only administrators can sign in.' : 'Maintenance mode is off.');
    }
    redirect('admin/barangays.php?tab=' . $tab);
}

$tab = get('tab') === 'settings' ? 'settings' : 'barangays';
$rows = q_all('SELECT b.*, u.username AS chairman, u.is_active AS chair_active,
                      (SELECT COUNT(*) FROM users x WHERE x.barangay_id = b.barangay_id AND x.role_id = ? AND x.is_active = 1) AS officials,
                      (SELECT COUNT(*) FROM youth_profiles y WHERE y.barangay_id = b.barangay_id) AS youth
                 FROM barangays b LEFT JOIN users u ON u.user_id = b.chairman_id ORDER BY b.barangay_name', [ROLE_BARANGAY]);
$activeAccounts = count(array_filter($rows, fn ($r) => (int) $r['officials'] > 0));
$needChair = count(array_filter($rows, fn ($r) => !$r['chairman_id']));

$title = 'Barangays & System Settings';
$nav = 'barangays';
$crumb = 'Administrator · Barangays & Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Barangays &amp; System Settings</h1>
    <div class="page-sub">Maintain the official directory and system-wide configuration</div>
  </div>
  <?php if ($tab === 'barangays'): ?><button class="btn btn-primary" data-open="dlg-brgy" data-fill='{"barangay_id":"0","barangay_name":"","municipality":"Nasipit"}'>+ Add Barangay</button><?php endif; ?>
</div>

<div class="tabs">
  <a class="tab<?= $tab === 'barangays' ? ' active' : '' ?>" href="?tab=barangays">SK Barangays</a>
  <a class="tab<?= $tab === 'settings' ? ' active' : '' ?>" href="?tab=settings">System Settings</a>
</div>

<?php if ($tab === 'barangays'): ?>
  <div class="grid grid-3 mb">
    <div class="card stat"><div class="stat-label">Registered Barangays</div><div class="stat-value"><?= count($rows) ?></div></div>
    <div class="card stat"><div class="stat-label">With Active Official Accounts</div><div class="stat-value"><?= $activeAccounts ?></div></div>
    <div class="card stat"><div class="stat-label">Chairman Assignment Needed</div><div class="stat-value<?= $needChair ? ' red' : '' ?>"><?= $needChair ?></div></div>
  </div>
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>SK Barangay</th><th>Municipality</th><th>Assigned Chairman</th><th class="num">Officials</th><th class="num">Youth Profiles</th><th>Account Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td style="font-weight:500"><?= e($r['barangay_name']) ?></td>
          <td class="muted"><?= e($r['municipality']) ?></td>
          <td class="<?= $r['chairman'] ? 'muted' : 'red' ?>"><?= e($r['chairman'] ?: 'Not assigned') ?></td>
          <td class="num"><?= (int) $r['officials'] ?></td>
          <td class="num"><?= (int) $r['youth'] ?></td>
          <td><?= !$r['chairman_id'] ? pill('pending') : ((int) $r['chair_active'] ? pill('completed', 'Active') : pill('inactive')) ?></td>
          <td class="right"><button class="btn btn-secondary btn-sm" data-open="dlg-brgy" data-fill='<?= e(json_encode(['barangay_id' => $r['barangay_id'], 'barangay_name' => $r['barangay_name'], 'municipality' => $r['municipality']])) ?>'>Edit</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small mt">To assign or change a barangay's SK Chairman, go to <a href="<?= e(url('admin/users.php?tab=roles')) ?>">Users &amp; Roles → Chairman Assignments</a>.</p>

  <dialog class="modal" id="dlg-brgy">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_barangay"><input type="hidden" name="barangay_id">
      <div class="modal-head"><div class="modal-title">Barangay Record</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
      <div class="field"><label>Barangay name</label><input class="input" name="barangay_name" required maxlength="100"></div>
      <div class="field"><label>Municipality</label><input class="input" name="municipality" required maxlength="100"></div>
      <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
  </dialog>
<?php else: ?>
  <div class="grid settings-layout">
    <form class="card card-pad" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <div class="section-title mb">General Configuration</div>
      <div class="field"><label>System name</label><input class="input" name="system_name" value="<?= e(setting('system_name', APP_NAME)) ?>" required maxlength="255"></div>
      <div class="field"><label>Municipality</label><input class="input" name="municipality" value="<?= e(setting('municipality', 'Nasipit, Agusan del Norte')) ?>" required maxlength="255"></div>
      <div class="field"><label>Audit log retention</label><select class="input" name="audit_retention"><?php foreach (RETENTION as $r): ?><option<?= setting('audit_retention') === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Eligibility: minimum QR-scanned activities per year</label><input class="input" type="number" min="0" max="100" name="eligibility_min_activities" value="<?= e(setting('eligibility_min_activities', '4')) ?>" required>
        <div class="hint">Used by Participation &amp; Eligibility. The system reports standing only; SK officials decide what it means.</div></div>
      <button class="btn btn-primary" type="submit">Save settings</button>
    </form>
    <form class="card card-pad" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="maintenance">
      <?php $mm = setting('maintenance_mode') === '1'; ?>
      <div class="section-title">Maintenance Mode</div>
      <p class="muted small" style="margin:6px 0 16px">Temporarily prevent non-administrator sign-ins while system maintenance is being performed. Signed-in users are signed out on their next page load.</p>
      <button class="btn <?= $mm ? 'btn-danger' : 'btn-secondary' ?> btn-block" type="submit"><?= $mm ? 'Disable maintenance mode' : 'Enable maintenance mode' ?></button>
      <div class="mono small mt <?= $mm ? 'red' : 'muted' ?>">Status: <?= $mm ? 'MAINTENANCE ACTIVE' : 'NORMAL OPERATION' ?></div>
    </form>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
