<?php

function brainoverflow_load_env(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $envPath = dirname(__DIR__) . '/.env';

    if (!is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function brainoverflow_google_config(): array
{
    brainoverflow_load_env();

    return [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: '',
    ];
}

function brainoverflow_google_is_configured(): bool
{
    $config = brainoverflow_google_config();

    return $config['client_id'] !== '' && $config['client_secret'] !== '' && $config['redirect_uri'] !== '';
}

function brainoverflow_google_request(string $url, array $options = []): array
{
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new RuntimeException('Google OAuth request failed.');
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Google OAuth returned an invalid response.');
    }

    return $decoded;
}

function brainoverflow_google_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'] ?? 'user';
}

function brainoverflow_google_unique_username(PDO $pdo, string $email, string $name = ''): string
{
    $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $name));

    if ($base === '') {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', strstr($email, '@', true) ?: 'user'));
    }

    $base = substr($base, 0, 24) ?: 'user';
    $candidate = $base;
    $suffix = 1;

    $checkUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');

    while (true) {
        $checkUsername->execute(['username' => $candidate]);

        if (!$checkUsername->fetch()) {
            return $candidate;
        }

        $candidate = substr($base, 0, 20) . $suffix;
        $suffix++;
    }
}
