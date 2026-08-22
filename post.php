<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

$rawPostId = $_GET['id'] ?? null;
$postId = is_string($rawPostId) && preg_match('/\A[1-9][0-9]*\z/', $rawPostId) === 1
    ? filter_var($rawPostId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$post = null;
$postLoadFailed = false;
$comments = [];
$commentsLoadFailed = false;
$commentContent = '';
$commentErrors = [];
$likeCount = 0;
$hasLiked = false;

if ($postId !== false) {
    try {
        require __DIR__ . '/config/database.php';

        $postQuery = $pdo->prepare(
            'SELECT blogpost.id, blogpost.user_id, blogpost.title, blogpost.content, blogpost.category, blogpost.created_at,
                    users.username AS author_username
             FROM blogpost
             INNER JOIN users ON users.id = blogpost.user_id
             WHERE blogpost.id = :id
             LIMIT 1'
        );
        $postQuery->execute(['id' => $postId]);
        $post = $postQuery->fetch() ?: null;
    } catch (Throwable $error) {
        error_log('BrainOverflow post page error: ' . $error->getMessage());
        $postLoadFailed = true;
        http_response_code(500);
    }
}

if ($post !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!brainoverflow_is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    $commentContent = isset($_POST['content']) && is_string($_POST['content'])
        ? trim($_POST['content'])
        : '';

    if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $commentErrors[] = 'Your session token is invalid or has expired. Please try again.';
    }

    if ($commentContent === '') {
        $commentErrors[] = 'Comment content is required.';
    } elseif (function_exists('mb_strlen') ? mb_strlen($commentContent, 'UTF-8') > 5000 : strlen($commentContent) > 5000) {
        $commentErrors[] = 'Comment content must be 5000 characters or fewer.';
    }

    if (empty($commentErrors)) {
        try {
            $insertComment = $pdo->prepare(
                'INSERT INTO comments (post_id, user_id, content)
                 VALUES (:post_id, :user_id, :content)'
            );
            $insertComment->execute([
                'post_id' => (int) $post['id'],
                'user_id' => (int) $_SESSION['user_id'],
                'content' => $commentContent,
            ]);

            header('Location: post.php?id=' . rawurlencode((string) $post['id']) . '&comment=added');
            exit;
        } catch (Throwable $error) {
            error_log('BrainOverflow comment creation error: ' . $error->getMessage());
            $commentErrors[] = 'Your comment could not be added. Please try again later.';
        }
    }
}

if ($post !== null) {
    try {
        $likeCountQuery = $pdo->prepare(
            'SELECT COUNT(*)
             FROM post_likes
             WHERE post_id = :post_id'
        );
        $likeCountQuery->execute(['post_id' => $post['id']]);
        $likeCount = (int) $likeCountQuery->fetchColumn();

        if (brainoverflow_is_logged_in()) {
            $currentUserLikeQuery = $pdo->prepare(
                'SELECT 1
                 FROM post_likes
                 WHERE post_id = :post_id AND user_id = :user_id
                 LIMIT 1'
            );
            $currentUserLikeQuery->execute([
                'post_id' => $post['id'],
                'user_id' => $_SESSION['user_id'],
            ]);
            $hasLiked = (bool) $currentUserLikeQuery->fetchColumn();
        }
    } catch (Throwable $error) {
        error_log('BrainOverflow post likes error: ' . $error->getMessage());
    }
}

if ($post !== null) {
    try {
        $commentsQuery = $pdo->prepare(
            'SELECT comments.id, comments.user_id, comments.content, comments.created_at,
                    users.username AS author_username
             FROM comments
             INNER JOIN users ON users.id = comments.user_id
             WHERE comments.post_id = :post_id
             ORDER BY comments.created_at DESC, comments.id DESC
             LIMIT 20'
        );
        $commentsQuery->execute(['post_id' => $post['id']]);
        $comments = $commentsQuery->fetchAll();
    } catch (Throwable $error) {
        error_log('BrainOverflow post comments error: ' . $error->getMessage());
        $commentsLoadFailed = true;
    }
}

if (!$postLoadFailed && $post === null) {
    http_response_code(404);
}

$pageTitle = $post !== null ? $post['title'] . ' - BrainOverflow' : 'Post Not Found - BrainOverflow';
$canEdit = $post !== null
    && brainoverflow_is_logged_in()
    && (int) $post['user_id'] === $_SESSION['user_id'];
$postUpdated = $post !== null && isset($_GET['updated']) && $_GET['updated'] === '1';
$commentAdded = $post !== null && isset($_GET['comment']) && $_GET['comment'] === 'added';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function () {
            try {
                document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Read a blog post on BrainOverflow.">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg);
        }

        .post-shell { min-height: 100vh; padding: 34px 0; }
        .post-topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 30px; }
        .post-back { color: var(--color-text); font-weight: 600; }
        .post-back:hover { color: var(--color-primary-dark); }
        .post-article, .post-state {
            max-width: 860px;
            margin: 0 auto;
            padding: clamp(26px, 6vw, 58px);
            background: var(--color-surface);
            border: 1px solid rgba(249, 115, 22, 0.16);
            border-radius: 18px;
            box-shadow: var(--shadow-card);
        }
        .post-article h1 { margin-bottom: 18px; font-size: clamp(2rem, 5vw, 3.25rem); overflow-wrap: anywhere; }
        .post-meta { display: flex; flex-wrap: wrap; gap: 8px 18px; padding-bottom: 24px; color: var(--color-text-light); border-bottom: 1px solid var(--color-border); }
        .post-author { color: var(--color-primary-dark); font-weight: 650; }
        .post-content { margin-top: 30px; font-size: 1.05rem; line-height: 1.85; overflow-wrap: anywhere; }
        .post-likes { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--color-border); }
        .post-like-count { color: var(--color-text-light); font-weight: 650; }
        .post-like-form { margin: 0; }
        .post-like-button { padding: 8px 14px; color: var(--color-primary-dark); font: inherit; font-weight: 700; background: transparent; border: 1px solid var(--color-primary); border-radius: var(--radius-md); cursor: pointer; }
        .post-like-button:hover, .post-like-button.is-liked { color: #fff; background: var(--color-primary); }
        .post-like-login { color: var(--color-text-light); }
        .post-actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--color-border); }
        .delete-form { margin: 0; }
        .btn-delete { color: #b91c1c; background: transparent; border: 1px solid #ef4444; }
        .btn-delete:hover { color: #fff; background: #dc2626; }
        .post-notice { max-width: 860px; margin: 0 auto 18px; padding: 13px 16px; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: var(--radius-md); }
        :root[data-theme="dark"] .post-notice { color: #bbf7d0; background: #14532d; border-color: #166534; }
        .post-state { text-align: center; }
        .post-state h1 { margin-bottom: 10px; }
        .post-state p { margin-bottom: 24px; color: var(--color-text-light); }
        .comments-section { max-width: 860px; margin: 28px auto 0; padding: clamp(24px, 5vw, 40px); background: var(--color-surface); border: 1px solid rgba(249, 115, 22, 0.16); border-radius: 18px; box-shadow: var(--shadow-card); }
        .comments-section h2 { margin-bottom: 22px; }
        .comment-form { margin-bottom: 28px; padding-bottom: 28px; border-bottom: 1px solid var(--color-border); }
        .comment-form label { display: block; margin-bottom: 9px; font-weight: 700; }
        .comment-form textarea { width: 100%; min-height: 130px; resize: vertical; }
        .comment-form .btn { margin-top: 12px; }
        .comment-login { margin-bottom: 24px; padding: 16px; color: var(--color-text-light); background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); }
        .comment-errors { margin-bottom: 14px; padding: 14px 16px; color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; border-radius: var(--radius-md); }
        .comment-errors ul { margin: 0; padding-left: 20px; }
        :root[data-theme="dark"] .comment-errors { color: #fecaca; background: #7f1d1d; border-color: #991b1b; }
        .comment { padding: 18px 0; border-top: 1px solid var(--color-border); }
        .comment:first-of-type { padding-top: 0; border-top: 0; }
        .comment-meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; margin-bottom: 8px; }
        .comment-author { color: var(--color-primary-dark); font-weight: 700; overflow-wrap: anywhere; }
        .comment-date { color: var(--color-text-light); font-size: 0.88rem; }
        .comment-content { line-height: 1.7; overflow-wrap: anywhere; }
        .comment-delete-form { display: flex; justify-content: flex-end; margin: 12px 0 0; }
        .comment-delete-button { padding: 7px 12px; color: #b91c1c; font: inherit; font-size: 0.85rem; font-weight: 650; background: transparent; border: 1px solid #ef4444; border-radius: var(--radius-md); cursor: pointer; }
        .comment-delete-button:hover { color: #fff; background: #dc2626; }
        .comments-empty { padding: 24px; text-align: center; color: var(--color-text-light); background: var(--color-bg); border: 1px dashed var(--color-border); border-radius: var(--radius-md); }
    </style>
</head>
<body>
    <div class="post-shell">
        <div class="container">
            <div class="post-topbar">
                <a class="post-back" href="index.php">&larr; Back to home</a>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">&#9790;</button>
            </div>

            <?php if ($post !== null): ?>
                <main>
                    <?php if ($postUpdated): ?>
                        <div class="post-notice" role="status">Your post was updated successfully.</div>
                    <?php endif; ?>
                    <?php if ($commentAdded): ?>
                        <div class="post-notice" role="status">Your comment was added successfully.</div>
                    <?php endif; ?>
                    <article class="post-article">
                        <?php if ($post['category'] !== null && trim((string) $post['category']) !== ''): ?>
                            <span class="card-category cat-php"><?php echo htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <h1><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="post-meta">
                            <span>By <span class="post-author"><?php echo htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                            <time datetime="<?php echo htmlspecialchars(date('c', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                            </time>
                        </div>
                        <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'), false); ?></div>
                        <div class="post-likes">
                            <span class="post-like-count"><?php echo htmlspecialchars((string) $likeCount, ENT_QUOTES, 'UTF-8'); ?> <?php echo $likeCount === 1 ? 'like' : 'likes'; ?></span>
                            <?php if (brainoverflow_is_logged_in()): ?>
                                <form class="post-like-form" method="POST" action="toggle-like.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="post-like-button<?php echo $hasLiked ? ' is-liked' : ''; ?>" type="submit"><?php echo $hasLiked ? 'Unlike' : 'Like'; ?></button>
                                </form>
                            <?php else: ?>
                                <span class="post-like-login"><a href="login.php">Log in</a> to like this post.</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($canEdit): ?>
                            <div class="post-actions">
                                <form class="delete-form" method="POST" action="delete-post.php" onsubmit="return window.confirm('Delete this post permanently? This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="btn btn-delete" type="submit">Delete post</button>
                                </form>
                                <a class="btn btn-hero" href="edit-post.php?id=<?php echo rawurlencode((string) $post['id']); ?>">Edit post</a>
                            </div>
                        <?php endif; ?>
                    </article>

                    <section class="comments-section" aria-labelledby="comments-title">
                        <h2 id="comments-title">Comments</h2>
                        <?php if (brainoverflow_is_logged_in()): ?>
                            <form class="comment-form" method="POST" action="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>">
                                <?php if (!empty($commentErrors)): ?>
                                    <div class="comment-errors" role="alert">
                                        <ul>
                                            <?php foreach ($commentErrors as $commentError): ?>
                                                <li><?php echo htmlspecialchars($commentError, ENT_QUOTES, 'UTF-8'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                <label for="comment-content">Add a comment</label>
                                <textarea id="comment-content" name="content" maxlength="5000" required><?php echo htmlspecialchars($commentContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <button class="btn btn-hero" type="submit">Post comment</button>
                            </form>
                        <?php else: ?>
                            <div class="comment-login"><a href="login.php">Log in</a> to add a comment.</div>
                        <?php endif; ?>
                        <?php if ($commentsLoadFailed): ?>
                            <div class="comments-empty">Comments are temporarily unavailable.</div>
                        <?php elseif (empty($comments)): ?>
                            <div class="comments-empty">No comments yet.</div>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <article class="comment">
                                    <div class="comment-meta">
                                        <span class="comment-author"><?php echo htmlspecialchars($comment['author_username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <time class="comment-date" datetime="<?php echo htmlspecialchars(date('c', strtotime($comment['created_at'])), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($comment['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </time>
                                    </div>
                                    <div class="comment-content"><?php echo nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8'), false); ?></div>
                                    <?php if (brainoverflow_is_logged_in() && (int) $comment['user_id'] === $_SESSION['user_id']): ?>
                                        <form class="comment-delete-form" method="POST" action="delete-comment.php" onsubmit="return window.confirm('Delete this comment permanently?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $comment['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button class="comment-delete-button" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                </main>
            <?php else: ?>
                <main class="post-state">
                    <h1><?php echo $postLoadFailed ? 'Post temporarily unavailable' : 'Post not found'; ?></h1>
                    <p><?php echo $postLoadFailed ? 'Please try again later.' : 'The post may have been removed or the link may be incorrect.'; ?></p>
                    <a class="btn btn-hero" href="index.php">Return home</a>
                </main>
            <?php endif; ?>
        </div>
    </div>

    <div class="cursor-glow" id="cursorGlow"></div>
    <script src="js/main.js"></script>
</body>
</html>
