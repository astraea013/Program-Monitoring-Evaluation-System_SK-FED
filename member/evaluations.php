<?php
/** Own evaluations: submitted ratings and answers, and attended activities still open for evaluation. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$pid = my_profile_id();

$rows = q_all('SELECT a.activity_id, a.title, a.activity_date, f.form_id, f.is_open AS form_open, r.response_id, r.date_submitted,
                      (SELECT AVG(ea.rating_value) FROM evaluation_answers ea WHERE ea.response_id = r.response_id) AS score
                 FROM attendance at JOIN activities a ON a.activity_id = at.activity_id
                 LEFT JOIN evaluation_forms f ON f.activity_id = a.activity_id
                 LEFT JOIN evaluation_responses r ON r.form_id = f.form_id AND r.profile_id = at.profile_id
                WHERE at.profile_id = ? ORDER BY a.activity_date DESC', [$pid]);
$view = null;
if ($rid = get_int('view')) {
    $view = q_one('SELECT r.*, a.title, a.activity_date FROM evaluation_responses r JOIN evaluation_forms f ON f.form_id = r.form_id JOIN activities a ON a.activity_id = f.activity_id WHERE r.response_id = ? AND r.profile_id = ?', [$rid, $pid]);
    $answers = $view ? q_all('SELECT q.*, ea.rating_value, ea.answer_text FROM evaluation_answers ea JOIN evaluation_questions q ON q.question_id = ea.question_id WHERE ea.response_id = ? ORDER BY q.sort_order', [$rid]) : [];
}

$title = 'My Evaluations';
$nav = 'evaluations';
$crumb = 'My Activities · My Evaluations';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1 class="page-title">My Evaluations</h1><div class="page-sub">Evaluation status for activities you attended</div></div></div>
<div class="scope-banner">Your e-certificate is released only once you submit the evaluation for an activity.</div>
<?php foreach ($rows as $r): ?>
  <div class="row-card <?= $r['response_id'] ? 'green' : 'amber' ?>">
    <div><div class="row-card-title" style="margin-top:0"><?= e($r['title']) ?></div>
      <div class="row-card-detail"><?= e(fmt_date($r['activity_date'])) ?> · <?= $r['response_id'] ? 'Your rating: ' . number_format((float) $r['score'], 1) . ' / 5' : 'Not yet rated' ?></div></div>
    <div class="row-card-side">
      <?php if ($r['response_id']): ?><?= pill('submitted', 'Submitted') ?><a class="btn btn-secondary btn-sm" href="?view=<?= (int) $r['response_id'] ?>">Review my answers</a>
      <?php elseif ($r['form_id'] && (int) $r['form_open']): ?><a class="btn btn-primary btn-sm" href="<?= e(url('member/evaluate.php?activity=' . $r['activity_id'])) ?>">Complete evaluation</a>
      <?php else: ?><?= pill('closed', 'Form closed') ?><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="card empty">You have not attended an activity yet. Scan the activity QR code to record attendance and unlock its evaluation.</div><?php endif; ?>

<?php if ($view): ?>
<dialog class="modal wide" data-autoopen>
  <div class="modal-head"><div><div class="modal-title"><?= e($view['title']) ?></div><div class="mono small muted">Submitted <?= e(fmt_datetime($view['date_submitted'])) ?></div></div><a class="modal-close" href="evaluations.php" aria-label="Close" style="text-decoration:none;display:flex;align-items:center;justify-content:center">×</a></div>
  <?php $sec = null; foreach ($answers as $an): if ($an['section'] !== $sec): $sec = $an['section']; ?><div class="detail-section-title"><?= e($sec) ?></div><?php endif; ?>
    <div class="kv plain"><span style="max-width:75%"><?= e($an['question_text']) ?></span><span><?= $an['question_type'] === 'rating' ? '<strong class="mono" style="color:#C99400">' . (int) $an['rating_value'] . '</strong>/5 · ' . e(RATING_LABELS[(int) $an['rating_value']] ?? '') : e($an['answer_text'] ?: '—') ?></span></div>
  <?php endforeach; ?>
  <div class="modal-actions"><a class="btn btn-primary" href="evaluations.php">Done</a></div>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
