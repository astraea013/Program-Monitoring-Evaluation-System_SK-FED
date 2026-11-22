<?php
/**
 * Evaluation form builder for one activity. Questions can be changed only
 * while no member has submitted a response, so every response to a form
 * answers the same questions.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_BARANGAY);
$b = my_barangay();
$aid = get_int('activity') ?: post_int('activity_id');
$act = q_one('SELECT a.*, p.program_code, f.form_id, f.form_title, f.is_open AS form_open FROM activities a JOIN programs p ON p.program_id = a.program_id
                LEFT JOIN evaluation_forms f ON f.activity_id = a.activity_id WHERE a.activity_id = ? AND p.barangay_id = ?', [$aid, $b]) ?? require_barangay_access(null);
if (!$act['form_id']) {
    create_default_form($aid, $act['title']);
    redirect('barangay/evaluation-form.php?activity=' . $aid);
}
$formId = (int) $act['form_id'];
$responses = (int) q_val('SELECT COUNT(*) FROM evaluation_responses WHERE form_id = ?', [$formId]);
$locked = $responses > 0;

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'toggle') {
        q('UPDATE evaluation_forms SET is_open = 1 - is_open WHERE form_id = ?', [$formId]);
        audit('EVAL_FORM_UPDATED', 'Form #' . $formId . ' ' . ((int) $act['form_open'] ? 'closed' : 'opened'));
    } elseif ($locked) {
        flash('error', 'Members have already answered this form, so its questions can no longer be changed.');
    } elseif ($action === 'save') {
        $text = post('question_text');
        $type = post('question_type') === 'text' ? 'text' : 'rating';
        $section = post('section') ?: 'General';
        $qid = post_int('question_id');
        if ($text === '') {
            flash('error', 'Enter the question text.');
        } elseif ($qid) {
            q('UPDATE evaluation_questions SET question_text = ?, question_type = ?, section = ?, sort_order = ? WHERE question_id = ? AND form_id = ?', [$text, $type, mb_substr($section, 0, 100), post_int('sort_order'), $qid, $formId]);
            audit('EVAL_FORM_UPDATED', 'Edited question #' . $qid);
        } else {
            q('INSERT INTO evaluation_questions (form_id, question_text, question_type, section, sort_order) VALUES (?, ?, ?, ?, ?)', [$formId, $text, $type, mb_substr($section, 0, 100), post_int('sort_order') ?: 99]);
            audit('EVAL_FORM_UPDATED', 'Added question to form #' . $formId);
        }
    } elseif ($action === 'delete') {
        q('DELETE FROM evaluation_questions WHERE question_id = ? AND form_id = ?', [post_int('question_id'), $formId]);
        audit('EVAL_FORM_UPDATED', 'Removed question from form #' . $formId);
    } elseif ($action === 'reset') {
        q('DELETE FROM evaluation_forms WHERE form_id = ?', [$formId]);
        create_default_form($aid, $act['title']);
        audit('EVAL_FORM_UPDATED', 'Reset form for activity #' . $aid . ' to defaults');
    }
    redirect('barangay/evaluation-form.php?activity=' . $aid);
}

$questions = q_all('SELECT * FROM evaluation_questions WHERE form_id = ? ORDER BY sort_order, question_id', [$formId]);
$title = 'Evaluation Form';
$nav = 'programs';
$crumb = 'Programs · Evaluation Form';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title"><?= e($act['form_title']) ?></h1><div class="page-sub"><?= e($act['program_code']) ?> · <?= e(fmt_date($act['activity_date'])) ?> · <?= $responses ?> responses</div></div>
  <div class="page-actions">
    <a class="btn btn-secondary" href="<?= e(url('barangay/qr.php?activity=' . $aid . '&tab=evaluations')) ?>">View responses</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= $aid ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-secondary"><?= (int) $act['form_open'] ? 'Close form' : 'Open form' ?></button></form>
    <?php if (!$locked): ?><button class="btn btn-primary" data-open="dlg-q" data-fill='{"question_id":"0","question_text":"","question_type":"rating","section":"Program Quality","sort_order":"<?= count($questions) + 1 ?>"}'>+ Add question</button><?php endif; ?>
  </div>
</div>
<div class="scope-banner">Rating questions use a 1 (strongly disagree) to 5 (strongly agree) scale. The e-certificate is issued automatically when a member submits this form. <?= $locked ? 'Questions are locked because members have already responded.' : 'You can change the questions until the first member responds.' ?></div>

<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th>Section</th><th>Question</th><th>Type</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($questions as $qq): ?>
      <tr><td class="mono muted"><?= (int) $qq['sort_order'] ?></td><td class="muted small"><?= e($qq['section']) ?></td><td><?= e($qq['question_text']) ?></td><td><?= pill($qq['question_type'] === 'rating' ? 'completed' : 'draft', $qq['question_type'] === 'rating' ? 'Rating 1–5' : 'Text') ?></td>
        <td class="right nowrap"><?php if (!$locked): ?>
          <button class="btn btn-secondary btn-sm" data-open="dlg-q" data-fill='<?= e(json_encode(['question_id' => $qq['question_id'], 'question_text' => $qq['question_text'], 'question_type' => $qq['question_type'], 'section' => $qq['section'], 'sort_order' => $qq['sort_order']])) ?>'>Edit</button>
          <form class="inline-form" method="post" data-confirm="Remove this question?"><?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= $aid ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="question_id" value="<?= (int) $qq['question_id'] ?>"><button class="btn btn-danger btn-sm">✕</button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$locked): ?>
<form method="post" class="mt" data-confirm="Replace all questions with the default set?"><?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= $aid ?>"><input type="hidden" name="action" value="reset"><button class="link-btn small">Reset to the default questions</button></form>
<dialog class="modal" id="dlg-q">
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="activity_id" value="<?= $aid ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="question_id">
    <div class="modal-head"><div class="modal-title">Question</div><button type="button" class="modal-close" data-close aria-label="Close">×</button></div>
    <div class="field"><label>Question text</label><textarea class="input" name="question_text" rows="3" required></textarea></div>
    <div class="form-grid">
      <div class="field"><label>Section</label><input class="input" name="section" list="sections" maxlength="100"><datalist id="sections"><option>Program Quality</option><option>Implementer Quality</option><option>Perceived Benefits</option><option>Suggestions</option></datalist></div>
      <div class="field"><label>Type</label><select class="input" name="question_type"><option value="rating">Rating 1–5</option><option value="text">Text answer</option></select></div>
      <div class="field"><label>Order</label><input class="input" type="number" name="sort_order" min="1"></div>
    </div>
    <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary">Save question</button></div>
  </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
