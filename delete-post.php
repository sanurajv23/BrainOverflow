<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

function brainoverflow_delete_error(int $status, string $title, string $message): never
{
    http_response_code($status);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $safeTitle . ' - BrainOverflow</title><link rel="stylesheet" href="css/style.css">';
    echo '</head><body><main class="container" style="max-width:760px;padding-top:80px;text-align:center">';
    echo '<h1>' . $safeTitle . '</h1><p style="margin:14px 0 24px;color:var(--color-text-light)">' . $safeMessage . '</p>';
    echo '<a class="btn btn-hero" href="index.php">Return home</a></main><script src="js/main.js"></script></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    brainoverflow_delete_error(405, 'Method not allowed', 'Posts can only be deleted from the confirmation form.');
}

if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    brainoverflow_delete_error(403, 'Request rejected', 'Your session expired. Please return to the post and try again.');
}

$rawPostId = $_POST['id'] ?? null;
$postId = is_string($rawPostId) && preg_match('/\A[1-9][0-9]*\z/', $rawPostId) === 1
    ? filter_var($rawPostId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($postId === false) {
    brainoverflow_delete_error(404, 'Post not found', 'The post may have been removed or the request may be incorrect.');
}

try {
    require __DIR__ . '/config/database.php';

    $ownerQuery = $pdo->prepare('SELECT user_id FROM blogpost WHERE id = :id LIMIT 1');
    $ownerQuery->execute(['id' => $postId]);
    $post = $ownerQuery->fetch();

    if (!$post) {
        brainoverflow_delete_error(404, 'Post not found', 'The post may have already been removed.');
    }

    if ((int) $post['user_id'] !== $_SESSION['user_id']) {
        brainoverflow_delete_error(403, 'Delete not allowed', 'You can only delete posts that you created.');
    }

    $deletePost = $pdo->prepare(
        'DELETE FROM blogpost
         WHERE id = :id AND user_id = :user_id'
    );
    $deletePost->execute([
        'id' => $postId,
        'user_id' => $_SESSION['user_id'],
    ]);

    if ($deletePost->rowCount() !== 1) {
        brainoverflow_delete_error(404, 'Post not found', 'The post may have already been removed.');
    }

    header('Location: index.php?deleted=1');
    exit;
} catch (Throwable $error) {
    error_log('BrainOverflow delete post error: ' . $error->getMessage());
    brainoverflow_delete_error(500, 'Post could not be deleted', 'Please try again later.');
}
