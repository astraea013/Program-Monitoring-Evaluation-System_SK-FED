<?php
/**
 * Application configuration.
 *
 * Machine-specific values (database password, a fixed BASE_URL) belong in
 * config/local.php, which is git-ignored. Copy the block at the bottom of
 * this file into it if you need to override anything.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'SK Federation Monitoring & Evaluation System');

if (is_file(__DIR__ . '/local.php')) {
    require __DIR__ . '/local.php';
}

// Database (XAMPP defaults).
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'sk_monitoring');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// Base URL path of the app, e.g. "/sk-capstone" under XAMPP htdocs.
// Worked out from the document root so the folder can be renamed freely.
if (!defined('BASE_URL')) {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT);
    $base = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
    define('BASE_URL', rtrim(str_replace('\\', '/', $base), '/'));
}

// Uploads live under uploads/, which ships an .htaccess denying all direct
// access. Files are only ever served through file.php after an access check.
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

date_default_timezone_set('Asia/Manila');

// Sessions: HTTP-only cookie, no ID in URLs, strict mode.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('SKMES');
    session_start();
}

/* Example config/local.php
<?php
define('DB_PASS', 'your-password');
define('BASE_URL', '/sk-capstone');
*/
