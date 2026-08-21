<?php

require_once __DIR__ . '/auth.php';

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

function brainoverflow_google_safe_error(array $response, int $httpStatus, array $sensitiveValues = []): string
{
    $errorCode = isset($response['error']) && is_string($response['error'])
        ? preg_replace('/[^a-zA-Z0-9_.-]/', '', $response['error'])
        : 'unknown_error';
    $description = isset($response['error_description']) && is_string($response['error_description'])
        ? preg_replace('/[\x00-\x1F\x7F]+/', ' ', $response['error_description'])
        : '';

    foreach ($sensitiveValues as $sensitiveValue) {
        if (is_string($sensitiveValue) && $sensitiveValue !== '') {
            $description = str_replace($sensitiveValue, '[redacted]', $description);
        }
    }

    $description = trim(preg_replace('/\s+/', ' ', $description));
    $description = substr($description, 0, 300);
    $statusText = $httpStatus > 0 ? 'HTTP ' . $httpStatus : 'HTTP status unavailable';

    return 'Google OAuth request failed (' . $statusText . ', ' . ($errorCode ?: 'unknown_error') . ')'
        . ($description !== '' ? ': ' . $description : '.');
}

function brainoverflow_google_request(string $url, array $options = [], array $sensitiveValues = []): array
{
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    $httpStatus = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
            $httpStatus = (int) $matches[1];
        }
    }

    if ($response === false) {
        throw new RuntimeException(
            'Google OAuth request failed ('
            . ($httpStatus > 0 ? 'HTTP ' . $httpStatus : 'no HTTP response')
            . ').'
        );
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Google OAuth returned an invalid JSON response ('
            . ($httpStatus > 0 ? 'HTTP ' . $httpStatus : 'HTTP status unavailable')
            . ').'
        );
    }

    if ($httpStatus >= 400 || isset($decoded['error'])) {
        throw new RuntimeException(brainoverflow_google_safe_error($decoded, $httpStatus, $sensitiveValues));
    }

    return $decoded;
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
