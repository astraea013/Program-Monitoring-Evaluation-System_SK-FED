<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_url((int) $u['role_id']));
}

$error = null;
$identifier = '';
if (is_post()) {
    csrf_check();
    $identifier = post('identifier');
    $error = login($identifier, (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        $u = current_user(true);
        $next = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        // Only follow a same-app path, never an external URL.
        if ($next !== '' && str_starts_with($next, BASE_URL . '/') && !str_starts_with($next, '//')) {
            redirect($next);
        }
        redirect(home_url((int) $u['role_id']));
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(APP_NAME) ?></title>
<link rel="icon" href="<?= e(asset('img/sk-federation-logo.png')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="login-screen">
  <div class="login-brand">
    <img src="<?= e(asset('img/sk-federation-logo.png')) ?>" alt="SK Federation Nasipit logo">
    <strong>SK Federation</strong>
    <span>Monitoring &amp; Evaluation System</span>
  </div>

  <div class="card narrow-card" style="max-width:380px">
    <h2 style="font-size:18px;margin-bottom:18px">Sign in to your account</h2>
    <?= render_flashes() ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="identifier">Email or KK ID</label>
        <input class="input<?= $error ? ' error' : '' ?>" id="identifier" name="identifier" value="<?= e($identifier) ?>" placeholder="e.g. juan@email.com or KK-2026-00001" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div style="position:relative">
          <input class="input<?= $error ? ' error' : '' ?>" id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required style="padding-right:52px">
          <button type="button" class="link-btn" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);text-decoration:none;font-size:12px" onclick="var p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'Show':'Hide'">Show</button>
        </div>
      </div>
      <button class="btn btn-primary btn-block" type="submit" style="padding:10px">Sign in</button>
    </form>
    <div style="text-align:center;margin-top:16px;border-top:1px solid var(--border-default);padding-top:16px;font-size:12px">
      <span class="muted">Not registered yet?</span> <a href="<?= e(url('register.php')) ?>">Create an account</a>
    </div>
  </div>
</div>
</body>
</html>
