<?php

const BRAINOVERFLOW_SESSION_IDLE_TIMEOUT = 7200;
const BRAINOVERFLOW_USERNAME_MIN_LENGTH = 3;
const BRAINOVERFLOW_USERNAME_MAX_LENGTH = 30;

function brainoverflow_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function brainoverflow_login_user(array $user): void
{
    brainoverflow_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['email'] = (string) $user['email'];
    $_SESSION['role'] = (string) ($user['role'] ?? 'user');
    $_SESSION['last_activity'] = time();
}

function brainoverflow_is_logged_in(): bool
{
    brainoverflow_start_session();

    $hasValidIdentity = isset($_SESSION['user_id'], $_SESSION['username'])
        && is_int($_SESSION['user_id'])
        && $_SESSION['user_id'] > 0
        && is_string($_SESSION['username'])
        && $_SESSION['username'] !== '';

    if (!$hasValidIdentity) {
        return false;
    }

    $lastActivity = $_SESSION['last_activity'] ?? 0;

    if (!is_int($lastActivity) || time() - $lastActivity > BRAINOVERFLOW_SESSION_IDLE_TIMEOUT) {
        brainoverflow_logout_user();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

function brainoverflow_current_username(): string
{
    return brainoverflow_is_logged_in() ? $_SESSION['username'] : '';
}

function brainoverflow_csrf_token(): string
{
    brainoverflow_start_session();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function brainoverflow_verify_csrf_token(mixed $token): bool
{
    brainoverflow_start_session();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function brainoverflow_validate_username(string $username): array
{
    $errors = [];
    $length = strlen($username);

    if ($length < BRAINOVERFLOW_USERNAME_MIN_LENGTH || $length > BRAINOVERFLOW_USERNAME_MAX_LENGTH) {
        $errors[] = 'Username must be between 3 and 30 characters.';
    }

    if ($username !== '' && preg_match('/\A[A-Za-z0-9_]+\z/', $username) !== 1) {
        $errors[] = 'Username may contain only letters, numbers, and underscores.';
    }

    return $errors;
}

function brainoverflow_validate_password(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (preg_match('/[A-Z]/', $password) !== 1) {
        $errors[] = 'Password must include at least one uppercase letter.';
    }

    if (preg_match('/[a-z]/', $password) !== 1) {
        $errors[] = 'Password must include at least one lowercase letter.';
    }

    if (preg_match('/[0-9]/', $password) !== 1) {
        $errors[] = 'Password must include at least one number.';
    }

    if (preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
        $errors[] = 'Password must include at least one special character.';
    }

    return $errors;
}

function brainoverflow_logout_user(): void
{
    brainoverflow_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
