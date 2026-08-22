<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

$rawPostId = $_GET['id'] ?? null;
$postId = is_string($rawPostId) && preg_match('/\A[1-9][0-9]*\z/', $rawPostId) === 1
    ? filter_var($rawPostId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$post = null;
$postLoadFailed = false;

if ($postId !== false) {
    try {
        require __DIR__ . '/config/database.php';

        $postQuery = $pdo->prepare(
            'SELECT blogpost.id, blogpost.user_id, blogpost.title, blogpost.content, blogpost.created_at,
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

if (!$postLoadFailed && $post === null) {
    http_response_code(404);
}

$pageTitle = $post !== null ? $post['title'] . ' - BrainOverflow' : 'Post Not Found - BrainOverflow';
$canEdit = $post !== null
    && brainoverflow_is_logged_in()
    && (int) $post['user_id'] === $_SESSION['user_id'];
$postUpdated = $post !== null && isset($_GET['updated']) && $_GET['updated'] === '1';
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
        .post-actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--color-border); }
        .delete-form { margin: 0; }
        .btn-delete { color: #b91c1c; background: transparent; border: 1px solid #ef4444; }
        .btn-delete:hover { color: #fff; background: #dc2626; }
        .post-notice { max-width: 860px; margin: 0 auto 18px; padding: 13px 16px; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: var(--radius-md); }
        :root[data-theme="dark"] .post-notice { color: #bbf7d0; background: #14532d; border-color: #166534; }
        .post-state { text-align: center; }
        .post-state h1 { margin-bottom: 10px; }
        .post-state p { margin-bottom: 24px; color: var(--color-text-light); }
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
                    <article class="post-article">
                        <span class="card-category cat-php">Community</span>
                        <h1><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="post-meta">
                            <span>By <span class="post-author"><?php echo htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8'); ?></span></span>
                            <time datetime="<?php echo htmlspecialchars(date('c', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                            </time>
                        </div>
                        <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'), false); ?></div>
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
