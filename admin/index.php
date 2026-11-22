<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

$officials = (int) q_val('SELECT COUNT(*) FROM users WHERE role_id <> ?', [ROLE_MEMBER]);
$activeOfficials = (int) q_val('SELECT COUNT(*) FROM users WHERE role_id <> ? AND is_active = 1', [ROLE_MEMBER]);
$barangays = (int) q_val('SELECT COUNT(*) FROM barangays');
$assigned = (int) q_val('SELECT COUNT(*) FROM barangays WHERE chairman_id IS NOT NULL');
$logsToday = (int) q_val('SELECT COUNT(*) FROM audit_logs WHERE timestamp >= CURDATE()');
$failedToday = (int) q_val("SELECT COUNT(*) FROM audit_logs WHERE timestamp >= CURDATE() AND action IN ('LOGIN_FAILED','ACCESS_DENIED')");
$recent = q_all("SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON u.user_id = l.user_id
                  WHERE l.action IN ('ACCOUNT_CREATED','ACCOUNT_UPDATED','CHAIRMAN_ASSIGNED','SETTINGS_UPDATED','BARANGAY_CREATED','BARANGAY_UPDATED')
                  ORDER BY l.timestamp DESC LIMIT 5");

$paths = [
    ['01', 'Users, Roles & Assignments', 'Control who can access the system and what each official is allowed to do.', ['Manage user accounts', 'Manage user roles', 'Assign an SK Chairman to each barangay'], 'admin/users.php', 'Manage users and roles'],
    ['02', 'Barangays & System Settings', 'Maintain the official barangay directory and system-wide configuration.', ['Manage the ' . $barangays . ' SK barangays', 'Update barangay records', 'Configure system settings'], 'admin/barangays.php', 'Manage setup'],
    ['03', 'System Logs', 'Review traceable records of access, account changes, and system activity.', ['View authentication events', 'Review administrator actions', 'Inspect security and system events'], 'admin/logs.php', 'View system logs'],
];

$title = 'Administrator Homepage';
$nav = 'dashboard';
$crumb = 'Administrator · Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1 class="page-title">Administrator Homepage</h1>
    <div class="page-sub">System setup and maintenance · <?= e(setting('municipality', 'Nasipit, Agusan del Norte')) ?></div>
  </div>
</div>

<div class="scope-banner">The Administrator maintains access, barangay setup, configuration, and logs. Program data and KK registration decisions remain with the authorized SK officials.</div>

<div class="grid grid-3 mb-lg">
  <?php foreach ($paths as [$n, $t, $d, $tasks, $href, $action]): ?>
    <div class="card card-pad" style="border-top:3px solid var(--blue);display:flex;flex-direction:column">
      <div class="record-id" style="font-size:11px;margin-bottom:10px">PATH <?= e($n) ?></div>
      <div class="section-title" style="font-size:17px;margin-bottom:8px"><?= e($t) ?></div>
      <p class="muted" style="font-size:12px"><?= e($d) ?></p>
      <ul style="padding-left:18px;margin:0 0 18px;font-size:11.5px"><?php foreach ($tasks as $task): ?><li><?= e($task) ?></li><?php endforeach; ?></ul>
      <a class="btn btn-primary btn-block" style="margin-top:auto" href="<?= e(url($href)) ?>"><?= e($action) ?> →</a>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid grid-4 mb">
  <div class="card stat"><div class="stat-label">Official Accounts</div><div class="stat-value"><?= $officials ?></div><div class="stat-sub"><?= $activeOfficials ?> active</div></div>
  <div class="card stat"><div class="stat-label">Barangays</div><div class="stat-value"><?= $barangays ?></div><div class="stat-sub">in <?= e(setting('municipality', 'Nasipit')) ?></div></div>
  <div class="card stat"><div class="stat-label">Chairmen Assigned</div><div class="stat-value"><?= $assigned ?>/<?= $barangays ?></div><div class="stat-sub"><?= $barangays - $assigned ?> assignment<?= $barangays - $assigned === 1 ? '' : 's' ?> needed</div></div>
  <div class="card stat"><div class="stat-label">Log Events Today</div><div class="stat-value"><?= $logsToday ?></div><div class="stat-sub"><?= $failedToday ?> require review</div></div>
</div>

<div class="card">
  <div class="card-head"><div class="section-title" style="font-size:14px">Recent Maintenance Activity</div><a class="small" href="<?= e(url('admin/logs.php')) ?>">View all →</a></div>
  <?php if (!$recent): ?><div class="empty">No maintenance activity recorded yet.</div><?php endif; ?>
  <table class="table"><tbody>
    <?php foreach ($recent as $r): ?>
      <tr><td style="font-weight:600"><?= e(ucwords(strtolower(str_replace('_', ' ', $r['action'])))) ?></td><td class="muted"><?= e($r['details']) ?></td><td class="sub nowrap right"><?= e(fmt_datetime($r['timestamp'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php require __DIR__ . '/../includes/footer.php';
