<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

function brainoverflow_like_error(int $status, string $title, string $message): never
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
    brainoverflow_like_error(405, 'Method not allowed', 'Likes can only be changed using a POST request.');
}

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    brainoverflow_like_error(403, 'Request rejected', 'Your session expired. Please return to the post and try again.');
}

$rawPostId = $_POST['id'] ?? null;
$postId = is_string($rawPostId) && preg_match('/\A[1-9][0-9]*\z/', $rawPostId) === 1
    ? filter_var($rawPostId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($postId === false) {
    brainoverflow_like_error(404, 'Post not found', 'The post may have been removed or the request may be incorrect.');
}

try {
    require __DIR__ . '/config/database.php';
    $pdo->beginTransaction();

    $postQuery = $pdo->prepare('SELECT id FROM blogpost WHERE id = :id LIMIT 1');
    $postQuery->execute(['id' => $postId]);

    if (!$postQuery->fetch()) {
        $pdo->rollBack();
        brainoverflow_like_error(404, 'Post not found', 'The post may have already been removed.');
    }

    $likeQuery = $pdo->prepare(
        'SELECT id
         FROM post_likes
         WHERE post_id = :post_id AND user_id = :user_id
         LIMIT 1
         FOR UPDATE'
    );
    $likeQuery->execute([
        'post_id' => $postId,
        'user_id' => $_SESSION['user_id'],
    ]);
    $like = $likeQuery->fetch();

    if ($like) {
        $removeLike = $pdo->prepare(
            'DELETE FROM post_likes
             WHERE post_id = :post_id AND user_id = :user_id'
        );
        $removeLike->execute([
            'post_id' => $postId,
            'user_id' => $_SESSION['user_id'],
        ]);
    } else {
        $addLike = $pdo->prepare(
            'INSERT INTO post_likes (post_id, user_id)
             VALUES (:post_id, :user_id)'
        );
        $addLike->execute([
            'post_id' => $postId,
            'user_id' => $_SESSION['user_id'],
        ]);
    }

    $pdo->commit();
    header('Location: post.php?id=' . rawurlencode((string) $postId));
    exit;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('BrainOverflow toggle like error: ' . $error->getMessage());
    brainoverflow_like_error(500, 'Like could not be updated', 'Please try again later.');
}
