<?php
/** Comments and suggestions. If sent anonymously, officials never see who sent it. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$pid = my_profile_id();

if (is_post()) {
    csrf_check();
    $msg = post('message');
    if ($msg === '' || mb_strlen($msg) > 2000) {
        flash('error', 'Write your suggestion (up to 2,000 characters).');
    } else {
        $anon = !empty($_POST['is_anonymous']) ? 1 : 0;
        q('INSERT INTO feedback (profile_id, message, is_anonymous) VALUES (?, ?, ?)', [$pid, $msg, $anon]);
        // The audit entry for anonymous feedback deliberately omits the author.
        audit('FEEDBACK_SENT', $anon ? 'Anonymous feedback submitted' : 'Feedback submitted', $anon ? 0 : null);
        notify_role(ROLE_BARANGAY, 'New member feedback', mb_strimwidth($msg, 0, 120, '…'), 'barangay/content.php?tab=feedback', my_barangay());
        flash('success', 'Thank you! Your feedback was sent to the SK officials' . ($anon ? ' anonymously.' : '.'));
    }
    redirect('member/feedback.php');
}
$rows = q_all('SELECT * FROM feedback WHERE profile_id = ? ORDER BY date_sent DESC', [$pid]);

$title = 'Feedback';
$nav = 'feedback';
$crumb = 'Information · Feedback';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">Recommendations</h1><div class="page-sub">Comments and suggestions to the SK officials of <?= e(current_user()['barangay_name']) ?></div></div><button class="btn btn-primary" data-open="dlg-fb">+ Submit feedback</button></div>
<?php foreach ($rows as $f): ?>
  <div class="row-card <?= $f['reply'] ? 'green' : 'amber' ?>">
    <div style="flex:1">
      <div class="row" style="gap:8px"><?= (int) $f['is_anonymous'] ? pill('draft', 'Sent anonymously') : '' ?><?= $f['reply'] ? pill('replied', 'Replied') : pill('pending', 'Awaiting reply') ?></div>
      <p style="margin:6px 0;font-size:12.5px"><?= nl2br(e($f['message'])) ?></p>
      <?php if ($f['reply']): ?><div class="note-box"><strong>SK Official:</strong> <?= nl2br(e($f['reply'])) ?></div><?php endif; ?>
    </div>
    <div class="row-card-side"><span class="row-card-time"><?= e(fmt_date($f['date_sent'])) ?></span></div>
  </div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card empty">You have not sent any feedback yet.</div><?php endif; ?>

<dialog class="modal" id="dlg-fb">
  <form method="post">
    <?= csrf_field() ?>
    <div class="modal-head"><div><div class="modal-title">Submit Recommendation</div><div class="muted small">Share your suggestions with the SK Barangay officials.</div></div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <textarea class="input" name="message" rows="5" maxlength="2000" required placeholder="Write your suggestion or recommendation here…"></textarea>
    <label class="check mt"><input type="checkbox" name="is_anonymous" value="1"><span><strong>Submit anonymously</strong><br><span class="muted small">Your name will not be shown to SK officials.</span></span></label>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Submit</button></div>
  </form>
</dialog>
<?php require __DIR__ . '/../includes/footer.php';
