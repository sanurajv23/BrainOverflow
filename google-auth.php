<?php
session_start();

require __DIR__ . '/includes/google_oauth.php';

if (!brainoverflow_google_is_configured()) {
    $_SESSION['oauth_error'] = 'Google sign-up is not configured yet. Please add Google OAuth credentials and try again.';
    header('Location: register.php');
    exit;
}

$config = brainoverflow_google_config();
$state = bin2hex(random_bytes(32));
$nonce = bin2hex(random_bytes(32));

$_SESSION['google_oauth_state'] = $state;
$_SESSION['google_oauth_nonce'] = $nonce;

$query = http_build_query([
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'nonce' => $nonce,
    'access_type' => 'online',
    'prompt' => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
exit;
