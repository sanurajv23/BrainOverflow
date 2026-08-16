<?php
session_start();

require __DIR__ . '/includes/google_oauth.php';

function redirect_with_oauth_error(string $message): void
{
    $_SESSION['oauth_error'] = $message;
    header('Location: register.php');
    exit;
}

if (isset($_GET['error'])) {
    redirect_with_oauth_error('Google sign-up was cancelled or could not be completed.');
}

if (!isset($_GET['code'], $_GET['state']) || !is_string($_GET['code']) || !is_string($_GET['state'])) {
    redirect_with_oauth_error('Google sign-up returned an invalid response.');
}

if (!isset($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
    redirect_with_oauth_error('Google sign-up could not be verified. Please try again.');
}

if (!brainoverflow_google_is_configured()) {
    redirect_with_oauth_error('Google sign-up is not configured yet. Please add Google OAuth credentials and try again.');
}

$config = brainoverflow_google_config();

try {
    $tokenResponse = brainoverflow_google_request('https://oauth2.googleapis.com/token', [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query([
                'code' => $_GET['code'],
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'grant_type' => 'authorization_code',
            ]),
            'ignore_errors' => true,
        ],
    ]);

    if (!isset($tokenResponse['access_token'])) {
        throw new RuntimeException('Google token response did not include an access token.');
    }

    $profile = brainoverflow_google_request('https://openidconnect.googleapis.com/v1/userinfo', [
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $tokenResponse['access_token'] . "\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ],
    ]);

    if (($profile['email_verified'] ?? false) !== true || empty($profile['email'])) {
        redirect_with_oauth_error('Google did not return a verified email address.');
    }

    require __DIR__ . '/config/database.php';

    $email = strtolower(trim((string) $profile['email']));
    $name = trim((string) ($profile['name'] ?? ''));

    $checkEmail = $pdo->prepare('SELECT id, username, email, role FROM users WHERE email = :email LIMIT 1');
    $checkEmail->execute(['email' => $email]);
    $user = $checkEmail->fetch();

    if (!$user) {
        $username = brainoverflow_google_unique_username($pdo, $email, $name);
        $insertUser = $pdo->prepare(
            'INSERT INTO users (username, email, password, role)
             VALUES (:username, :email, :password, :role)'
        );

        $insertUser->execute([
            'username' => $username,
            'email' => $email,
            'password' => null,
            'role' => 'user',
        ]);

        $user = [
            'id' => $pdo->lastInsertId(),
            'username' => $username,
            'email' => $email,
            'role' => 'user',
        ];
    }

    unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce']);
    brainoverflow_google_login_user($user);

    header('Location: index.php');
    exit;
} catch (Throwable $error) {
    error_log('BrainOverflow Google OAuth error: ' . $error->getMessage());
    redirect_with_oauth_error('Google sign-up could not be completed. Please try again later.');
}
