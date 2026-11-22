<?php
/**
 * Activity evaluation. Open only to members recorded as attending. On
 * submission the response, its answers and the e-certificate are written
 * in one transaction — there is no approval step and no pending state.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$profileId = my_profile_id();
$aid = get_int('activity');

$act = q_one('SELECT a.*, f.form_id, f.form_title, f.is_open AS form_open, b.barangay_name
                FROM activities a JOIN evaluation_forms f ON f.activity_id = a.activity_id
                JOIN programs p ON p.program_id = a.program_id JOIN barangays b ON b.barangay_id = p.barangay_id
               WHERE a.activity_id = ?', [$aid]);
$attended = $act ? q_val('SELECT 1 FROM attendance WHERE activity_id = ? AND profile_id = ?', [$aid, $profileId]) : null;
if (!$act || !$attended) {
    flash('error', 'You can only evaluate activities where your attendance was recorded.');
    redirect('member/evaluations.php');
}
$existing = q_val('SELECT response_id FROM evaluation_responses WHERE form_id = ? AND profile_id = ?', [$act['form_id'], $profileId]);
if ($existing && (int) ($_SESSION['just_evaluated'] ?? 0) !== (int) $existing) {
    redirect('member/evaluations.php?view=' . $existing);
}
$questions = q_all('SELECT * FROM evaluation_questions WHERE form_id = ? ORDER BY sort_order, question_id', [$act['form_id']]);
$sections = [];
foreach ($questions as $qq) {
    $sections[$qq['section']][] = $qq;
}
$errors = [];

if (is_post() && !$existing) {
    csrf_check();
    if (!(int) $act['form_open']) {
        flash('error', 'This evaluation form has been closed by the SK officials.');
        redirect('member/evaluations.php');
    }
    $answers = [];
    foreach ($questions as $qq) {
        $v = $_POST['q'][$qq['question_id']] ?? '';
        if ($qq['question_type'] === 'rating') {
            $n = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            if ($n === false) {
                $errors[] = $qq['question_id'];
            }
            $answers[] = [$qq['question_id'], $n === false ? null : $n, null];
        } else {
            $answers[] = [$qq['question_id'], null, mb_substr(trim((string) $v), 0, 2000) ?: null];
        }
    }
    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            q('INSERT INTO evaluation_responses (form_id, profile_id) VALUES (?, ?)', [$act['form_id'], $profileId]);
            $rid = (int) $pdo->lastInsertId();
            foreach ($answers as [$qid, $rating, $text]) {
                q('INSERT INTO evaluation_answers (response_id, question_id, rating_value, answer_text) VALUES (?, ?, ?, ?)', [$rid, $qid, $rating, $text]);
            }
            q('INSERT INTO certificates (response_id, profile_id, activity_id, certificate_no) VALUES (?, ?, ?, ?)', [$rid, $profileId, $aid, 'TMP-' . bin2hex(random_bytes(8))]);
            $cid = (int) $pdo->lastInsertId();
            $certNo = sprintf('SKCRT-%s-%05d', date('Y'), $cid);
            q('UPDATE certificates SET certificate_no = ? WHERE certificate_id = ?', [$certNo, $cid]);
            $pdo->commit();
            audit('EVALUATION_SUBMITTED', 'Evaluation of activity #' . $aid . '; certificate ' . $certNo . ' issued');
            notify(uid(), 'Your e-certificate is ready', 'Certificate ' . $certNo . ' for ' . $act['title'] . ' is available to download.', 'member/certificates.php');
            $_SESSION['just_evaluated'] = $rid;
            redirect('member/evaluate.php?activity=' . $aid);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((int) ($ex->errorInfo[1] ?? 0) === 1062) {
                flash('info', 'You have already submitted this evaluation.');
                redirect('member/evaluations.php');
            }
            throw $ex;
        }
    }
}

$thanks = isset($_SESSION['just_evaluated']) && (int) $_SESSION['just_evaluated'] === (int) $existing;
unset($_SESSION['just_evaluated']);

$title = 'Evaluate Activity';
$nav = 'evaluations';
$crumb = 'My Activities · Evaluation';
require __DIR__ . '/../includes/header.php';
?>
<div style="max-width:520px;margin:0 auto">
<?php if ($thanks):
    $avgs = q_all('SELECT q.section, AVG(ea.rating_value) v FROM evaluation_answers ea JOIN evaluation_questions q ON q.question_id = ea.question_id WHERE ea.response_id = ? AND ea.rating_value IS NOT NULL GROUP BY q.section ORDER BY MIN(q.sort_order)', [$existing]);
    $cert = q_one('SELECT * FROM certificates WHERE response_id = ?', [$existing]); ?>
  <div style="text-align:center;padding:20px 0">
    <div style="font-size:40px">⭐</div>
    <h1 style="font-size:20px;margin:6px 0">Thank you, <?= e(explode(' ', display_name())[0]) ?>!</h1>
    <p class="muted">Your evaluation has been submitted and your e-certificate <span class="mono"><?= e($cert['certificate_no']) ?></span> was issued. Here's how you rated this activity.</p>
  </div>
  <div class="card card-pad mb">
    <?php foreach ($avgs as $s): ?><div class="kv"><span><?= e($s['section']) ?></span><span><strong style="color:#C99400"><?= number_format((float) $s['v'], 1) ?></strong>/5</span></div><?php endforeach; ?>
  </div>
  <a class="btn btn-primary btn-block" style="padding:12px" href="<?= e(url('member/certificate.php?id=' . $cert['certificate_id'])) ?>">View my e-certificate →</a>
  <a class="btn btn-secondary btn-block mt" href="<?= e(url('member/index.php')) ?>">Back to portal</a>
<?php else: ?>
  <div class="card mb" style="background:var(--indigo);color:#fff;padding:16px 18px;border:0">
    <div class="mono small" style="color:rgba(255,255,255,.6)" data-step-label>Step 1 of <?= count($sections) ?></div>
    <div style="font-family:var(--font-head);font-size:16px;font-weight:700"><?= e($act['title']) ?></div>
    <div class="small" style="color:rgba(255,255,255,.7)"><?= e(fmt_date($act['activity_date'])) ?> · <?= e($act['barangay_name']) ?></div>
  </div>
  <?php if (!(int) $act['form_open']): ?><div class="alert alert-warning">This evaluation form is closed.</div><?php endif; ?>
  <?php if ($errors): ?><div class="alert alert-error">Rate every statement before submitting.</div><?php endif; ?>
  <form method="post" id="eval-form">
    <?= csrf_field() ?>
    <?php $si = 0; foreach ($sections as $name => $qs): $si++; ?>
      <section data-step="<?= $si ?>">
        <div class="section-title" style="font-size:17px;margin-bottom:4px"><?= e($name) ?></div>
        <?php if ($qs[0]['question_type'] === 'rating'): ?><p class="muted small mb">Rate each statement from 1 (strongly disagree) to 5 (strongly agree).</p><?php endif; ?>
        <?php foreach ($qs as $qq): $qid = (int) $qq['question_id']; $old = $_POST['q'][$qid] ?? ''; ?>
          <div class="card card-pad mb"<?= in_array($qid, $errors, true) ? ' style="border-color:var(--red)"' : '' ?>>
            <div style="font-size:13px;font-weight:500;margin-bottom:10px" id="ql-<?= $qid ?>"><?= e($qq['question_text']) ?></div>
            <?php if ($qq['question_type'] === 'rating'): ?>
              <div class="rating" role="radiogroup" aria-labelledby="ql-<?= $qid ?>">
                <?php for ($v = 1; $v <= 5; $v++): ?><input type="radio" id="q<?= $qid ?>-<?= $v ?>" name="q[<?= $qid ?>]" value="<?= $v ?>" required<?= (string) $old === (string) $v ? ' checked' : '' ?>><label for="q<?= $qid ?>-<?= $v ?>" title="<?= e(RATING_LABELS[$v]) ?>"><?= $v ?></label><?php endfor; ?>
              </div>
              <div class="rating-legend"><span>Strongly disagree</span><span>Strongly agree</span></div>
            <?php else: ?>
              <textarea class="input" name="q[<?= $qid ?>]" rows="4" maxlength="2000" placeholder="Write anything you want the SK to know — what went well, what could be improved, or ideas for the next activity."><?= e($old) ?></textarea>
              <div class="muted small" style="margin-top:4px">This is optional.</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($si < count($sections)): ?>
          <button type="button" class="btn btn-primary btn-block" data-next style="padding:12px">Next →</button>
        <?php else: ?>
          <button type="submit" class="btn btn-primary btn-block" style="padding:12px" <?= (int) $act['form_open'] ? '' : 'disabled' ?>>Submit evaluation →</button>
        <?php endif; ?>
        <?php if ($si > 1): ?><button type="button" class="btn btn-secondary btn-block mt" data-prev>← Back</button><?php endif; ?>
      </section>
    <?php endforeach; ?>
  </form>
  <script>
  (function(){
    var f=document.getElementById('eval-form'), steps=f.querySelectorAll('[data-step]'), label=document.querySelector('[data-step-label]'), cur=1;
    function show(n){cur=n;steps.forEach(function(s){s.hidden=+s.getAttribute('data-step')!==n});label.textContent='Step '+n+' of '+steps.length;window.scrollTo(0,0);}
    f.querySelectorAll('[data-next]').forEach(function(b){b.addEventListener('click',function(){
      var sec=steps[cur-1], groups={}, ok=true;
      sec.querySelectorAll('input[type=radio]').forEach(function(r){groups[r.name]=groups[r.name]||sec.querySelector('input[name="'+r.name+'"]:checked');});
      Object.keys(groups).forEach(function(k){if(!groups[k])ok=false;});
      if(!ok){alert('Rate all the statements to continue.');return;} show(cur+1);
    });});
    f.querySelectorAll('[data-prev]').forEach(function(b){b.addEventListener('click',function(){show(cur-1);});});
    show(1);
  })();
  </script>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
