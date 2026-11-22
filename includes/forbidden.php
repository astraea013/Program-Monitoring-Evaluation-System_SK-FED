<?php
// Shown by require_role() and require_barangay_access(). Output only.
$u = current_user();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Access denied · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="center-screen">
  <div class="card narrow-card">
    <div class="label-mono">Error 403</div>
    <h1 class="page-title">You do not have access to this page</h1>
    <p class="muted">This page belongs to another role or another barangay. Access is checked on the server, so changing the address will not open it.</p>
    <a class="btn btn-primary" href="<?= e(url($u ? home_url((int) $u['role_id']) : 'index.php')) ?>">Back to my dashboard</a>
  </div>
</body>
</html>
