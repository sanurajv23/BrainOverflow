<?php

const BRAINOVERFLOW_SESSION_IDLE_TIMEOUT = 7200;
const BRAINOVERFLOW_USERNAME_MIN_LENGTH = 3;
const BRAINOVERFLOW_USERNAME_MAX_LENGTH = 30;
const BRAINOVERFLOW_LOGIN_MAX_ATTEMPTS = 5;
const BRAINOVERFLOW_LOGIN_ATTEMPT_WINDOW = 900;
const BRAINOVERFLOW_LOGIN_LOCKOUT_DURATION = 900;
const BRAINOVERFLOW_LOGIN_MAX_DELAY_MICROSECONDS = 2000000;

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

    static $validatedSessions = [];
    $sessionValidationKey = session_id() . ':' . $_SESSION['user_id'];

    if (!isset($validatedSessions[$sessionValidationKey])) {
        try {
            require __DIR__ . '/../config/database.php';

            $currentUserQuery = $pdo->prepare(
                'SELECT id, username, email, role
                 FROM users
                 WHERE id = :id
                 LIMIT 1'
            );
            $currentUserQuery->execute(['id' => $_SESSION['user_id']]);
            $currentUser = $currentUserQuery->fetch();

            if (!$currentUser) {
                brainoverflow_logout_user();
                return false;
            }

            $_SESSION['user_id'] = (int) $currentUser['id'];
            $_SESSION['username'] = (string) $currentUser['username'];
            $_SESSION['email'] = (string) $currentUser['email'];
            $_SESSION['role'] = (string) ($currentUser['role'] ?? 'user');
            $validatedSessions[$sessionValidationKey] = true;
        } catch (Throwable $error) {
            error_log('BrainOverflow session revalidation error: ' . $error->getMessage());
            return false;
        }
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

function brainoverflow_login_client_ip(): string
{
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($remoteAddress) && filter_var($remoteAddress, FILTER_VALIDATE_IP)
        ? $remoteAddress
        : 'unknown';
}

function brainoverflow_login_rate_limit_path(string $scope, string $value): string
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brainoverflow-login-rate-limit';

    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Login rate-limit storage could not be created.');
    }

    return $directory . DIRECTORY_SEPARATOR . hash('sha256', $scope . ':' . $value) . '.json';
}

function brainoverflow_login_rate_limit_record(string $scope, string $value): array
{
    $path = brainoverflow_login_rate_limit_path($scope, $value);
    $handle = fopen($path, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Login rate-limit record could not be opened.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Login rate-limit record could not be locked.');
        }

        rewind($handle);
        $stored = stream_get_contents($handle);
        $record = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;

        if (!is_array($record)) {
            return ['attempts' => 0, 'first_attempt' => 0, 'locked_until' => 0];
        }

        return [
            'attempts' => max(0, (int) ($record['attempts'] ?? 0)),
            'first_attempt' => max(0, (int) ($record['first_attempt'] ?? 0)),
            'locked_until' => max(0, (int) ($record['locked_until'] ?? 0)),
        ];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function brainoverflow_login_rate_limit_keys(string $ipAddress, string $identifier): array
{
    return [
        ['scope' => 'ip', 'value' => $ipAddress],
        ['scope' => 'identifier', 'value' => strtolower(trim($identifier))],
    ];
}

function brainoverflow_login_rate_limit_status(string $ipAddress, string $identifier): array
{
    $now = time();
    $retryAfter = 0;

    try {
        foreach (brainoverflow_login_rate_limit_keys($ipAddress, $identifier) as $key) {
            $record = brainoverflow_login_rate_limit_record($key['scope'], $key['value']);
            $retryAfter = max($retryAfter, $record['locked_until'] - $now);
        }
    } catch (Throwable $error) {
        error_log('BrainOverflow login rate-limit read error: ' . $error->getMessage());
    }

    return ['limited' => $retryAfter > 0, 'retry_after' => max(0, $retryAfter)];
}

function brainoverflow_record_failed_login(string $ipAddress, string $identifier): int
{
    $now = time();
    $highestAttempts = 0;

    try {
        foreach (brainoverflow_login_rate_limit_keys($ipAddress, $identifier) as $key) {
            $path = brainoverflow_login_rate_limit_path($key['scope'], $key['value']);
            $handle = fopen($path, 'c+');

            if ($handle === false || !flock($handle, LOCK_EX)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }

                throw new RuntimeException('Login rate-limit record could not be updated.');
            }

            try {
                $stored = stream_get_contents($handle);
                $record = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;

                if (!is_array($record)) {
                    $record = ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];
                }

                $record['attempts'] = max(0, (int) ($record['attempts'] ?? 0));
                $record['first_attempt'] = max(0, (int) ($record['first_attempt'] ?? 0));
                $record['locked_until'] = max(0, (int) ($record['locked_until'] ?? 0));

                if ($record['first_attempt'] === 0 || $now - $record['first_attempt'] > BRAINOVERFLOW_LOGIN_ATTEMPT_WINDOW) {
                    $record = ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];
                }

                $record['attempts']++;
                $highestAttempts = max($highestAttempts, $record['attempts']);

                if ($record['attempts'] >= BRAINOVERFLOW_LOGIN_MAX_ATTEMPTS) {
                    $record['locked_until'] = $now + BRAINOVERFLOW_LOGIN_LOCKOUT_DURATION;
                }

                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode($record, JSON_THROW_ON_ERROR));
                fflush($handle);
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    } catch (Throwable $error) {
        error_log('BrainOverflow login rate-limit write error: ' . $error->getMessage());
        return 0;
    }

    return min(
        max(0, $highestAttempts - 1) * 250000,
        BRAINOVERFLOW_LOGIN_MAX_DELAY_MICROSECONDS
    );
}

function brainoverflow_reset_login_rate_limit(string $ipAddress, string $identifier): void
{
    try {
        foreach (brainoverflow_login_rate_limit_keys($ipAddress, $identifier) as $key) {
            $path = brainoverflow_login_rate_limit_path($key['scope'], $key['value']);

            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Login rate-limit record could not be removed.');
            }
        }
    } catch (Throwable $error) {
        error_log('BrainOverflow login rate-limit reset error: ' . $error->getMessage());
    }
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
