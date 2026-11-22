<?php
/**
 * Authentication and role-based access control.
 *
 * Every page in admin/, federation/, barangay/ and member/ calls
 * require_role() before it produces any output.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Attempt a login with a username, email address or KK ID.
 * Returns null on success or an error message for the login form.
 */
function login(string $identifier, string $password): ?string
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return 'Enter your email, username or KK ID, and your password.';
    }

    if (preg_match('/^KK-\d{4}-(\d{1,})$/i', $identifier, $m)) {
        $user = q_one('SELECT u.* FROM users u JOIN youth_profiles p ON p.user_id = u.user_id WHERE p.profile_id = ?', [(int) $m[1]]);
    } else {
        $user = q_one('SELECT * FROM users WHERE username = ? OR email = ?', [$identifier, $identifier]);
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        audit('LOGIN_FAILED', 'Failed sign-in for identifier "' . mb_substr($identifier, 0, 60) . '"', $user ? (int) $user['user_id'] : null);
        return 'Incorrect email/KK ID or password. Please try again.';
    }
    if ($user['verification_status'] === 'pending') {
        return 'Your registration is still waiting for verification by the SK Chairman of your barangay. You can sign in once it is approved.';
    }
    if ($user['verification_status'] === 'rejected') {
        return 'Your registration was not approved' . ($user['rejection_reason'] ? ': ' . $user['rejection_reason'] : '.') . ' Contact your barangay SK office.';
    }
    if (!(int) $user['is_active']) {
        return 'This account has been deactivated. Contact the system administrator.';
    }
    if ((int) $user['role_id'] !== ROLE_ADMIN && setting('maintenance_mode') === '1') {
        return 'The system is under maintenance. Please try again later.';
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    q('UPDATE users SET last_login = NOW() WHERE user_id = ?', [$user['user_id']]);
    audit('LOGIN', 'Signed in', (int) $user['user_id']);
    return null;
}

function logout(): void
{
    if (!empty($_SESSION['user_id'])) {
        audit('LOGOUT', 'Signed out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * The signed-in user, re-read from the database on every request so a
 * deactivation takes effect immediately. Includes barangay and profile.
 */
function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($user !== false && !$refresh) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $row = q_one(
            'SELECT u.*, b.barangay_name, p.profile_id, p.full_name, p.date_encoded
               FROM users u
               LEFT JOIN barangays b ON b.barangay_id = u.barangay_id
               LEFT JOIN youth_profiles p ON p.user_id = u.user_id
              WHERE u.user_id = ?',
            [$_SESSION['user_id']]
        );
        if ($row && (int) $row['is_active'] === 1 && $row['verification_status'] === 'verified'
            && ((int) $row['role_id'] === ROLE_ADMIN || setting('maintenance_mode') !== '1')) {
            $user = $row;
        } else {
            $_SESSION = [];
        }
    }
    return $user;
}

function uid(): int
{
    return (int) (current_user()['user_id'] ?? 0);
}

function role(): int
{
    return (int) (current_user()['role_id'] ?? 0);
}

/** Barangay of the signed-in official or member. Null for admin and federation. */
function my_barangay(): ?int
{
    $b = current_user()['barangay_id'] ?? null;
    return $b === null ? null : (int) $b;
}

/** Display name: the youth profile name for members, otherwise the username. */
function display_name(?array $u = null): string
{
    $u = $u ?? current_user();
    return $u['full_name'] ?: $u['username'];
}

function home_url(int $role): string
{
    return [
        ROLE_ADMIN => 'admin/index.php',
        ROLE_FEDERATION => 'federation/index.php',
        ROLE_BARANGAY => 'barangay/index.php',
        ROLE_MEMBER => 'member/index.php',
    ][$role] ?? 'index.php';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please sign in to continue.');
        redirect('index.php');
    }
    return $user;
}

/** Allow only the listed roles. Anyone else gets a 403 page, not a redirect loop. */
function require_role(int ...$roles): array
{
    $user = require_login();
    if (!in_array((int) $user['role_id'], $roles, true)) {
        audit('ACCESS_DENIED', 'Blocked from ' . mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 200));
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        exit;
    }
    return $user;
}

/** Stop unless the record belongs to the official's own barangay. */
function require_barangay_access(?int $barangayId): void
{
    if ($barangayId === null || $barangayId !== my_barangay()) {
        audit('ACCESS_DENIED', 'Cross-barangay access attempt on ' . mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 200));
        http_response_code(403);
        require __DIR__ . '/forbidden.php';
        exit;
    }
}

/** For KK members: the youth profile ID that attendance and evaluations hang off. */
function my_profile_id(): int
{
    return (int) (current_user()['profile_id'] ?? 0);
}

/** Is the signed-in official the designated SK Chairman of their barangay? */
function is_chairman(): bool
{
    $b = my_barangay();
    return $b !== null && (int) q_val('SELECT chairman_id FROM barangays WHERE barangay_id = ?', [$b]) === uid();
}
