<?php
/** Notifications for the signed-in user. Opened from the top-bar button, never the sidebar. */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if (is_post()) {
    csrf_check();
    if (post('action') === 'read_all') {
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [uid()]);
        redirect('notifications.php');
    }
    if (post('action') === 'open') {
        $n = q_one('SELECT * FROM notifications WHERE notification_id = ? AND user_id = ?', [post_int('id'), uid()]);
        if ($n) {
            q('UPDATE notifications SET is_read = 1 WHERE notification_id = ?', [$n['notification_id']]);
            // Links are stored as app-relative paths written by the system itself.
            if ($n['link'] && preg_match('#^[a-z]+/[a-z\-]+\.php(\?[\w=&\-]*)?$#', $n['link'])) {
                redirect($n['link']);
            }
        }
        redirect('notifications.php');
    }
}

$items = q_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY date_created DESC LIMIT 200', [uid()]);
$unread = count(array_filter($items, fn ($n) => !(int) $n['is_read']));
$groups = ['Today' => [], 'This week' => [], 'Earlier' => []];
foreach ($items as $n) {
    $t = strtotime($n['date_created']);
    $groups[$t >= strtotime('today') ? 'Today' : ($t >= strtotime('-7 days') ? 'This week' : 'Earlier')][] = $n;
}

$title = 'Notifications';
$nav = 'notifications';
require __DIR__ . '/includes/header.php';
?>
<div style="max-width:760px">
  <div class="page-head">
    <div>
      <h1 class="page-title">Notifications</h1>
      <div class="page-sub"><?= e(ROLE_LABELS[role()]) ?><?= my_barangay() ? ' · ' . e(current_user()['barangay_name']) : '' ?> · <?= $unread ?> unread</div>
    </div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-secondary" <?= $unread ? '' : 'disabled' ?>>Mark all as read</button></form>
  </div>

  <?php if (!$items): ?><div class="card empty">You have no notifications yet.</div><?php endif; ?>
  <?php foreach ($groups as $label => $list): if (!$list) continue; ?>
    <div class="label-mono" style="margin-top:18px"><?= e($label) ?></div>
    <div class="card">
      <?php foreach ($list as $i => $n): ?>
        <form method="post" style="<?= $i ? 'border-top:1px solid var(--border-default)' : '' ?>">
          <?= csrf_field() ?><input type="hidden" name="action" value="open"><input type="hidden" name="id" value="<?= (int) $n['notification_id'] ?>">
          <button type="submit" style="all:unset;display:flex;gap:12px;padding:14px 18px;cursor:pointer;width:100%;box-sizing:border-box;<?= (int) $n['is_read'] ? '' : 'background:var(--surface-muted)' ?>">
            <span style="width:7px;height:7px;border-radius:50%;margin-top:6px;flex-shrink:0;background:<?= (int) $n['is_read'] ? 'var(--border-default)' : 'var(--sun)' ?>"></span>
            <span style="flex:1;min-width:0">
              <span style="display:block;font-size:13px;font-weight:<?= (int) $n['is_read'] ? 500 : 700 ?>"><?= e($n['title']) ?></span>
              <span style="display:block;font-size:12px" class="muted"><?= e($n['body']) ?></span>
            </span>
            <span class="mono small muted nowrap"><?= e(date(strtotime($n['date_created']) >= strtotime('today') ? 'g:i A' : 'M j, g:i A', strtotime($n['date_created']))) ?></span>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
