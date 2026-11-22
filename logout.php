<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Sign-out is a POST with a CSRF token so a link on another site cannot sign people out.
if (is_post()) {
    csrf_check();
    logout();
    session_start();
    flash('success', 'You have been signed out.');
}
redirect('index.php');
