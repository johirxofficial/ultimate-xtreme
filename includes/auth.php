<?php
/**
 * Authentication + roles (admin / manager) and CSRF helpers.
 */

function auth_check(): bool
{
    app_session();

    if (empty($_SESSION['admin'])) {
        return false;
    }

    static $checked = false;
    static $valid   = false;

    if ($checked) {
        return $valid;
    }
    $checked = true;

    $id   = (int) ($_SESSION['admin_id'] ?? 0);
    $user = $id > 0 ? user_find($id) : null;

    if (!$user || (int) $user['status'] !== 1) {
        $_SESSION = [];
        $valid    = false;

        return false;
    }

    // Keep the session in sync with role/username changes.
    $_SESSION['admin_user'] = (string) $user['username'];
    $_SESSION['admin_role'] = (string) $user['role'];
    $valid = true;

    return true;
}

function auth_user(): string
{
    app_session();

    return (string) ($_SESSION['admin_user'] ?? '');
}

function auth_id(): int
{
    app_session();

    return (int) ($_SESSION['admin_id'] ?? 0);
}

function auth_role(): string
{
    app_session();

    return ($_SESSION['admin_role'] ?? '') === 'admin' ? 'admin' : 'manager';
}

function auth_is_admin(): bool
{
    return auth_check() && auth_role() === 'admin';
}

function auth_setup_needed(): bool
{
    return users_count() === 0;
}

function auth_attempt(string $username, string $password): bool
{
    $user = user_by_name($username);

    if (!$user || (int) $user['status'] !== 1 || !password_verify($password, (string) $user['password_hash'])) {
        usleep(400000);

        return false;
    }

    app_session();
    session_regenerate_id(true);

    $_SESSION['admin']      = true;
    $_SESSION['admin_id']   = (int) $user['id'];
    $_SESSION['admin_user'] = (string) $user['username'];
    $_SESSION['admin_role'] = (string) $user['role'];
    $_SESSION['login_at']   = time();

    user_touch_login((int) $user['id']);
    activity_log('login', 'Signed in as ' . $user['username']);

    return true;
}

function auth_setup(string $username, string $password): bool
{
    if (!auth_setup_needed()) {
        return false;
    }

    $id = user_save([
        'username'      => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role'          => 'admin',
        'status'        => 1,
    ]);

    if ($id <= 0) {
        return false;
    }

    if (auth_attempt($username, $password)) {
        activity_log('setup', 'Initial admin account created');

        return true;
    }

    user_delete($id);

    return false;
}

function auth_logout(): void
{
    app_session();

    if (!empty($_SESSION['admin'])) {
        activity_log('logout', 'Signed out');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
        unset($_COOKIE[session_name()]);
    }

    session_destroy();
}

function require_auth(): void
{
    if (!auth_check()) {
        redirect('admin/login.php');
    }
}

function require_admin(): void
{
    require_auth();

    if (!auth_is_admin()) {
        flash('error', 'That area is restricted to administrators.');
        redirect('admin/index.php');
    }
}

function csrf_token(): string
{
    app_session();

    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf'];
}

function csrf_valid(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }

    app_session();

    return !empty($_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], $token);
}
