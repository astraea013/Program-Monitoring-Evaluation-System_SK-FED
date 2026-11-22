<?php
/**
 * Page shell: <head>, sidebar and top bar. Set before including:
 *   $title  page title
 *   $nav    id of the active sidebar item
 *   $crumb  breadcrumb text (defaults to $title)
 */
$__u = current_user();
$__unread = $__u ? (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$__u['user_id']]) : 0;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Dashboard') ?> · SK Federation M&amp;E</title>
<link rel="icon" href="<?= e(asset('img/sk-federation-logo.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<script>try{if(localStorage.getItem('skmes-theme')==='dark')document.documentElement.classList.add('dark-mode')}catch(e){}</script>
</head>
<body class="app">
<?php if ($__u): require __DIR__ . '/sidebar.php'; endif; ?>
<div class="main">
<?php if ($__u): ?>
  <header class="topbar">
    <button type="button" class="nav-toggle" data-nav-toggle aria-controls="nav-rail" aria-label="Open menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="breadcrumb"><?= e($crumb ?? $title ?? '') ?></div>
    <div class="topbar-actions">
      <a class="notification-button<?= ($nav ?? '') === 'notifications' ? ' active' : '' ?>" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications, <?= $__unread ?> unread" title="Notifications">
        <?= nav_icon('bell') ?>
        <?php if ($__unread > 0): ?><span class="notification-badge"><?= $__unread > 99 ? '99+' : $__unread ?></span><?php endif; ?>
      </a>
      <div class="profile-menu-wrap">
        <button type="button" class="profile-button" data-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-label="Open account menu">
          <span class="profile-avatar"><?= e(initials(display_name())) ?></span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="profile-dropdown" role="menu" hidden>
          <div class="profile-dropdown-header">
            <div class="profile-dropdown-name"><?= e(display_name()) ?></div>
            <div class="profile-dropdown-role"><?= e(ROLE_LABELS[(int) $__u['role_id']]) ?></div>
          </div>
          <a class="profile-menu-item" role="menuitem" href="<?= e(url('account.php')) ?>">Profile</a>
          <a class="profile-menu-item" role="menuitem" href="<?= e(url('account.php#password')) ?>">Change Password</a>
          <button type="button" class="profile-menu-item" role="menuitem" data-theme-toggle>
            <span style="flex:1">Dark mode</span><span class="theme-toggle" aria-hidden="true"><span></span></span>
          </button>
          <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="profile-menu-item danger" role="menuitem">Sign out</button>
          </form>
        </div>
      </div>
    </div>
  </header>
<?php endif; ?>
  <main class="page<?= !empty($wide) ? ' page-wide' : '' ?>" id="content">
    <?= render_flashes() ?>
