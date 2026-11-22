<?php
/**
 * Small helpers shared by every page: escaping, formatting, CSRF, flash
 * messages, redirects, settings, audit logging and notifications.
 */

require_once __DIR__ . '/../config/database.php';

const ROLE_ADMIN = 1;
const ROLE_FEDERATION = 2;
const ROLE_BARANGAY = 3;
const ROLE_MEMBER = 4;

const ROLE_LABELS = [
    ROLE_ADMIN      => 'Administrator',
    ROLE_FEDERATION => 'SK Federation Official',
    ROLE_BARANGAY   => 'SK Barangay Official',
    ROLE_MEMBER     => 'KK Member',
];

// The eight centres of participation of the CBYDP.
const CENTRES = [
    'Health', 'Education', 'Economic Empowerment', 'Social Inclusion',
    'Environment', 'Sports & Recreation', 'Active Citizenship', 'Peace & Security',
];

const CENTRE_COLOURS = [
    'Health' => '#1E7D4F', 'Education' => '#1B5AC4', 'Economic Empowerment' => '#C99400',
    'Social Inclusion' => '#B0407A', 'Environment' => '#5E9E2F', 'Sports & Recreation' => '#7048C4',
    'Active Citizenship' => '#10214B', 'Peace & Security' => '#5A6478',
];

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return url('assets/' . $path) . '?v=' . $v;
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) || str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

/** Peso amount for display. Values come from DECIMAL columns as strings. */
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** Compact peso (no centavos when whole). */
function peso0($amount): string
{
    $f = (float) $amount;
    return '₱' . (fmod($f, 1.0) == 0.0 ? number_format($f) : number_format($f, 2));
}

/**
 * Validate a money string from a form and return it normalised for a
 * DECIMAL(12,2) column, or null. Never converts through float.
 */
function money_input($raw): ?string
{
    $s = str_replace([',', '₱', ' '], '', trim((string) $raw));
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
        return null;
    }
    [$whole, $frac] = array_pad(explode('.', $s, 2), 2, '');
    return ltrim($whole, '0') === '' ? '0.' . str_pad($frac, 2, '0') : ltrim($whole, '0') . '.' . str_pad($frac, 2, '0');
}

function pct($part, $whole, int $dp = 0): string
{
    $w = (float) $whole;
    return $w > 0 ? number_format(((float) $part / $w) * 100, $dp) : number_format(0, $dp);
}

function fmt_date(?string $d, string $format = 'M j, Y'): string
{
    return $d ? date($format, strtotime($d)) : '—';
}

function fmt_datetime(?string $d): string
{
    return $d ? date('M j, Y g:i A', strtotime($d)) : '—';
}

function fmt_time(?string $t): string
{
    return $t ? date('g:i A', strtotime($t)) : '—';
}

function age(?string $birthdate): ?int
{
    if (!$birthdate) {
        return null;
    }
    return (new DateTime($birthdate))->diff(new DateTime('today'))->y;
}

/** KK ID shown to members, e.g. KK-2026-00001. Derived from the profile, not stored. */
function kk_id(int $profileId, ?string $dateEncoded = null): string
{
    return sprintf('KK-%s-%05d', $dateEncoded ? date('Y', strtotime($dateEncoded)) : date('Y'), $profileId);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? end($parts) : '';
    return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: '?';
}

/** Status pill. Maps every status the schema allows to a colour. */
function pill(?string $status, ?string $label = null): string
{
    $s = strtolower((string) $status);
    $map = [
        'pending' => 'amber', 'draft' => 'gray', 'ongoing' => 'amber', 'incomplete' => 'amber', 'low stock' => 'amber', 'on loan' => 'amber',
        'approved' => 'green', 'verified' => 'green', 'complete' => 'green', 'completed' => 'blue', 'available' => 'blue', 'returned item' => 'green',
        'evaluated' => 'navy', 'submitted' => 'green', 'eligible' => 'green', 'open' => 'green', 'replied' => 'green', 'present' => 'green',
        'returned' => 'red', 'rejected' => 'red', 'declined' => 'red', 'out of stock' => 'red', 'cancelled' => 'red', 'not eligible' => 'red', 'closed' => 'gray', 'inactive' => 'red',
        'planned' => 'outline',
    ];
    $colour = $map[$s] ?? 'gray';
    return '<span class="pill pill-' . $colour . '">' . e($label ?? $status) . '</span>';
}

/* ---------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired or the form was submitted twice. Go back, refresh the page and try again.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key): int
{
    return (int) ($_GET[$key] ?? 0);
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

/* --------------------------------------------------------------- flash */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function render_flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $message]) {
        $out .= '<div class="alert alert-' . e($type) . '" role="status">' . e($message) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ------------------------------------------------------------ settings */

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT setting_key, setting_value FROM settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}

/* ------------------------------------------------ audit + notifications */

/**
 * Write an audit log row. Called from every write path. Never pass a
 * password or other secret in $details.
 */
function audit(string $action, string $details = '', ?int $userId = null): void
{
    // Pass 0 to record an event with no user attached (e.g. anonymous feedback).
    $uid = $userId === 0 ? null : ($userId ?? ($_SESSION['user_id'] ?? null));
    q('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)', [
        $uid, mb_substr($action, 0, 100), mb_substr($details, 0, 500), $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function notify(int $userId, string $title, string $body = '', string $link = ''): void
{
    q('INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)', [
        $userId, mb_substr($title, 0, 255), mb_substr($body, 0, 500), $link === '' ? null : $link,
    ]);
}

/** Notify every active user with a role, optionally limited to one barangay. */
function notify_role(int $roleId, string $title, string $body = '', string $link = '', ?int $barangayId = null): void
{
    $sql = 'SELECT user_id FROM users WHERE role_id = ? AND is_active = 1 AND verification_status = "verified"';
    $params = [$roleId];
    if ($barangayId !== null) {
        $sql .= ' AND barangay_id = ?';
        $params[] = $barangayId;
    }
    foreach (q_all($sql, $params) as $r) {
        notify((int) $r['user_id'], $title, $body, $link);
    }
}

/* -------------------------------------------------------------- export */

/** Stream rows as a CSV download (opens in Excel). */
function send_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₱ and ñ correctly
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
    exit;
}
