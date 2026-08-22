<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

function brainoverflow_comment_delete_error(int $status, string $title, string $message): never
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    brainoverflow_comment_delete_error(405, 'Method not allowed', 'Comments can only be deleted using a POST request.');
}

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    brainoverflow_comment_delete_error(403, 'Request rejected', 'Your session expired. Please return to the post and try again.');
}

$rawCommentId = $_POST['id'] ?? null;
$commentId = is_string($rawCommentId) && preg_match('/\A[1-9][0-9]*\z/', $rawCommentId) === 1
    ? filter_var($rawCommentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($commentId === false) {
    brainoverflow_comment_delete_error(404, 'Comment not found', 'The comment may have been removed or the request may be incorrect.');
}

try {
    require __DIR__ . '/config/database.php';

    $commentQuery = $pdo->prepare(
        'SELECT user_id, post_id
         FROM comments
         WHERE id = :id
         LIMIT 1'
    );
    $commentQuery->execute(['id' => $commentId]);
    $comment = $commentQuery->fetch();

    if (!$comment) {
        brainoverflow_comment_delete_error(404, 'Comment not found', 'The comment may have already been removed.');
    }

    if ((int) $comment['user_id'] !== $_SESSION['user_id']) {
        brainoverflow_comment_delete_error(403, 'Delete not allowed', 'You can only delete comments that you created.');
    }

    $deleteComment = $pdo->prepare(
        'DELETE FROM comments
         WHERE id = :id AND user_id = :user_id'
    );
    $deleteComment->execute([
        'id' => $commentId,
        'user_id' => $_SESSION['user_id'],
    ]);

    if ($deleteComment->rowCount() !== 1) {
        brainoverflow_comment_delete_error(404, 'Comment not found', 'The comment may have already been removed.');
    }

    header('Location: post.php?id=' . rawurlencode((string) $comment['post_id']) . '&comment_deleted=1');
    exit;
} catch (Throwable $error) {
    error_log('BrainOverflow delete comment error: ' . $error->getMessage());
    brainoverflow_comment_delete_error(500, 'Comment could not be deleted', 'Please try again later.');
}
