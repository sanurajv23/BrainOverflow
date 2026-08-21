<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request.');
}

brainoverflow_logout_user();

header('Location: index.php');
exit;
