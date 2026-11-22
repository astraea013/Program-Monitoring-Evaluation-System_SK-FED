<?php
/** Administrator: read-only audit log viewer with category filter and CSV export. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_ADMIN);

// Categories group the action codes written by audit().
const LOG_CATEGORIES = [
    'auth'     => ['LOGIN', 'LOGOUT', 'PASSWORD_CHANGED'],
    'account'  => ['ACCOUNT_CREATED', 'ACCOUNT_UPDATED', 'REGISTER', 'REGISTRATION_VERIFIED', 'REGISTRATION_REJECTED', 'PROFILE_UPDATED'],
    'role'     => ['CHAIRMAN_ASSIGNED'],
    'security' => ['LOGIN_FAILED', 'ACCESS_DENIED', 'VERIFICATION_PHOTO_VIEWED'],
    'system'   => ['SETTINGS_UPDATED', 'BARANGAY_CREATED', 'BARANGAY_UPDATED'],
];

function log_category(string $action): string
{
    foreach (LOG_CATEGORIES as $cat => $actions) {
        if (in_array($action, $actions, true)) {
            return strtoupper($cat);
        }
    }
    return 'RECORD';
}

$filter = get('cat');
$from = get('from');
$to = get('to');
$where = ['1=1'];
$params = [];
if ($filter === 'record') {
    $all = array_merge(...array_values(LOG_CATEGORIES));
    $where[] = 'l.action NOT IN (' . implode(',', array_fill(0, count($all), '?')) . ')';
    $params = array_merge($params, $all);
} elseif (isset(LOG_CATEGORIES[$filter])) {
    $where[] = 'l.action IN (' . implode(',', array_fill(0, count(LOG_CATEGORIES[$filter]), '?')) . ')';
    $params = array_merge($params, LOG_CATEGORIES[$filter]);
}
if ($from !== '' && strtotime($from)) {
    $where[] = 'l.timestamp >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '' && strtotime($to)) {
    $where[] = 'l.timestamp <= ?';
    $params[] = $to . ' 23:59:59';
}
$sql = 'SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON u.user_id = l.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY l.timestamp DESC, l.log_id DESC';

if (get('export') === 'csv') {
    $rows = array_map(fn ($r) => [$r['timestamp'], $r['username'] ?: 'System / unknown', log_category($r['action']), $r['action'], $r['details'], $r['ip_address']], q_all($sql, $params));
    audit('LOGS_EXPORTED', count($rows) . ' log rows exported');
    send_csv('system-logs-' . date('Ymd') . '.csv', ['Timestamp', 'User', 'Category', 'Action', 'Details', 'IP address'], $rows);
}
$logs = q_all($sql . ' LIMIT 300', $params);

$title = 'System Logs';
$nav = 'logs';
$crumb = 'Administrator · System Logs';
require __DIR__ . '/../includes/header.php';
$qs = fn (array $over) => '?' . http_build_query(array_merge(['cat' => $filter, 'from' => $from, 'to' => $to], $over));
?>
<div class="page-head">
  <div>
    <h1 class="page-title">System Logs</h1>
    <div class="page-sub">Read-only record of access, maintenance, and security events</div>
  </div>
  <a class="btn btn-secondary" href="<?= e($qs(['export' => 'csv'])) ?>">Export logs</a>
</div>

<div class="scope-banner">System logs are read-only and retained according to the configured audit policy (<?= e(setting('audit_retention', '24 months')) ?>). Passwords are never written to the log.</div>

<div class="spread mb" style="flex-wrap:wrap">
  <div class="row">
    <?php foreach (['' => 'All events', 'account' => 'Account', 'role' => 'Role', 'auth' => 'Auth', 'security' => 'Security', 'system' => 'System', 'record' => 'Records'] as $k => $l): ?>
      <a class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($qs(['cat' => $k])) ?>"><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
  <form class="row" method="get">
    <input type="hidden" name="cat" value="<?= e($filter) ?>">
    <input class="input" type="date" name="from" value="<?= e($from) ?>" style="width:auto;padding:5px 8px" aria-label="From date">
    <input class="input" type="date" name="to" value="<?= e($to) ?>" style="width:auto;padding:5px 8px" aria-label="To date">
    <button class="btn btn-secondary btn-sm">Apply</button>
  </form>
</div>
<div class="mono small muted mb"><?= count($logs) ?> events shown<?= count($logs) === 300 ? ' (latest 300 — export for the full list)' : '' ?></div>

<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>Timestamp</th><th>User / Source</th><th>Category</th><th>Event</th><th>IP</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): $cat = log_category($l['action']); ?>
      <tr>
        <td class="sub nowrap"><?= e($l['timestamp']) ?></td>
        <td><?= e($l['username'] ?: 'Unknown / system') ?></td>
        <td><?= pill('completed', $cat) ?></td>
        <td class="muted"><span class="mono small"><?= e($l['action']) ?></span><br><?= e($l['details']) ?></td>
        <td class="sub"><?= e($l['ip_address']) ?></td>
        <td><?= in_array($l['action'], ['LOGIN_FAILED', 'ACCESS_DENIED'], true) ? pill('rejected', 'Review') : pill('approved', 'Success') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="6" class="empty">No log events match.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php';
