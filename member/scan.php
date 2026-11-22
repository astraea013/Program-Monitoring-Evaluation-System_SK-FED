<?php
/**
 * Attendance by QR. The member must be signed in (require_role sends them
 * to the login page and back here afterwards). The scan validates the
 * token, checks the activity is open, records attendance, then sends the
 * member to that activity's evaluation form.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(ROLE_MEMBER);
$profileId = my_profile_id();

$token = get('t');
$result = null;
if ($token !== '') {
    $activity = preg_match('/^[A-Za-z0-9-]{8,64}$/', $token)
        ? q_one('SELECT a.*, p.title AS program_title, p.centre, b.barangay_name, f.form_id
                   FROM activities a JOIN programs p ON p.program_id = a.program_id JOIN barangays b ON b.barangay_id = p.barangay_id
                   LEFT JOIN evaluation_forms f ON f.activity_id = a.activity_id WHERE a.qr_token = ?', [$token])
        : null;
    if (!$activity) {
        $result = ['error', 'This QR code is not valid. It may have been replaced by a newer code — ask the SK officials for the current one.'];
    } elseif (!(int) $activity['is_open']) {
        $result = ['error', 'Attendance scanning for "' . $activity['title'] . '" is closed.'];
    } else {
        $existing = q_one('SELECT * FROM attendance WHERE activity_id = ? AND profile_id = ?', [$activity['activity_id'], $profileId]);
        if ($existing) {
            $result = ['already', $activity, $existing['time_scanned']];
        } else {
            try {
                q('INSERT INTO attendance (activity_id, profile_id, entry_method) VALUES (?, ?, "qr_scan")', [$activity['activity_id'], $profileId]);
                audit('ATTENDANCE_SCANNED', 'QR attendance at activity #' . $activity['activity_id']);
                $result = ['ok', $activity, date('Y-m-d H:i:s')];
            } catch (PDOException $ex) {
                // Two quick scans can race; the unique key keeps one row.
                $result = ['already', $activity, date('Y-m-d H:i:s')];
            }
        }
        $evaluated = $activity['form_id'] ? q_val('SELECT r.response_id FROM evaluation_responses r WHERE r.form_id = ? AND r.profile_id = ?', [$activity['form_id'], $profileId]) : null;
    }
}

$title = 'Scan QR Code';
$nav = 'scan';
$crumb = 'My Activities · Scan QR Code';
if (!$result) {
    $scripts = [asset('js/vendor/html5-qrcode-2.3.8.min.js')];
    $inline_script = <<<'JS'
(function(){
  var status = document.getElementById('scan-status');
  if (typeof Html5Qrcode === 'undefined') { status.textContent = 'The camera scanner could not load. Use your phone camera app, or paste the code link below.'; return; }
  var reader = new Html5Qrcode('qr-reader'), done = false;
  function go(text) {
    if (done) return;
    var m = String(text).match(/[?&]t=([A-Za-z0-9-]{8,64})/) || String(text).match(/^([A-Za-z0-9-]{8,64})$/);
    if (!m) { status.textContent = 'That QR code is not an SK activity code.'; return; }
    done = true; status.textContent = 'Code found. Recording attendance…';
    reader.stop().catch(function(){}).then(function(){ location.href = '?t=' + m[1]; });
  }
  document.getElementById('scan-start').addEventListener('click', function(){
    this.hidden = true; status.textContent = 'Point your camera at the activity QR code.';
    reader.start({ facingMode: 'environment' }, { fps: 10, qrbox: 230 }, go, function(){}).catch(function(err){
      status.textContent = 'Camera unavailable (' + err + '). Allow camera access, or use your phone camera app.';
      document.getElementById('scan-start').hidden = false;
    });
  });
  document.getElementById('scan-manual').addEventListener('submit', function(e){ e.preventDefault(); go(this.code.value.trim()); });
})();
JS;
}
require __DIR__ . '/../includes/header.php';
?>
<div style="max-width:460px;margin:0 auto">
<?php if (!$result): ?>
  <div class="page-head"><div><h1 class="page-title">Scan QR Code</h1><div class="page-sub">Record your attendance at an SK activity</div></div></div>
  <div class="card card-pad" style="text-align:center">
    <div id="qr-reader"></div>
    <p id="scan-status" class="muted small" style="margin:12px 0">Scanning records your attendance, opens the activity's evaluation form, and gives you an e-certificate once you submit it.</p>
    <button class="btn btn-primary btn-block" id="scan-start" style="padding:12px">Open camera</button>
  </div>
  <form class="card card-pad mt" id="scan-manual">
    <div class="label-mono">Camera not working?</div>
    <p class="muted small">Scan the poster with your phone's camera app — it opens this page directly. Or paste the scan link here.</p>
    <div class="row" style="flex-wrap:nowrap"><input class="input" name="code" placeholder="Paste scan link or code"><button class="btn btn-secondary">Go</button></div>
  </form>
<?php elseif ($result[0] === 'error'): ?>
  <div class="card card-pad" style="text-align:center;margin-top:30px">
    <div class="big-check" style="background:#FBE4E3;color:var(--red)">!</div>
    <h1 style="font-size:19px;margin-bottom:6px">Attendance not recorded</h1>
    <p class="muted"><?= e($result[1]) ?></p>
    <a class="btn btn-primary" href="<?= e(url('member/scan.php')) ?>">Scan again</a>
  </div>
<?php else: [$state, $a, $time] = $result; ?>
  <div style="text-align:center;padding:24px 0 16px">
    <div class="big-check">✓</div>
    <h1 style="font-size:20px;margin-bottom:4px"><?= $state === 'ok' ? 'Attendance recorded!' : 'You are already checked in' ?></h1>
    <p class="muted">Welcome, <?= e(explode(' ', display_name())[0]) ?>! See you in the activity.</p>
  </div>
  <div class="card card-pad mb">
    <div class="label-mono">Activity Details</div>
    <div class="kv plain"><span>Activity</span><span><?= e($a['title']) ?></span></div>
    <div class="kv plain"><span>Date</span><span><?= e(fmt_date($a['activity_date'], 'F j, Y')) ?></span></div>
    <div class="kv"><span>Time in</span><span><?= e(fmt_time(substr($time, 11))) ?></span></div>
    <div class="kv plain"><span>Venue</span><span><?= e($a['venue'] ?: '—') ?></span></div>
    <div class="kv plain"><span>Barangay</span><span><?= e($a['barangay_name']) ?></span></div>
  </div>
  <div class="card card-pad mb" style="text-align:center">
    <div class="label-mono">Your KK ID</div>
    <div class="kk-id" style="font-size:20px"><?= e(kk_id($profileId, current_user()['date_encoded'])) ?></div>
  </div>
  <?php if ($evaluated): ?>
    <a class="btn btn-primary btn-block" style="padding:12px" href="<?= e(url('member/certificates.php')) ?>">View my e-certificate →</a>
  <?php elseif ($a['form_id']): ?>
    <a class="btn btn-primary btn-block" style="padding:12px" href="<?= e(url('member/evaluate.php?activity=' . $a['activity_id'])) ?>">Rate this activity →</a>
    <p class="muted small" style="text-align:center;margin-top:8px">You'll get an e-certificate once you submit your rating.</p>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
