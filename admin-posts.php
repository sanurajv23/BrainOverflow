<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['role']) || !is_string($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    $forbidden = true;
} else {
    $forbidden = false;
}

$currentUsername = brainoverflow_current_username();
$posts = [];
$pageError = null;
$postDeleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';

if (!$forbidden) {
    try {
        require __DIR__ . '/config/database.php';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                $pageError = 'Your session token is invalid or has expired. Please try again.';
            } else {
                $rawPostId = $_POST['id'] ?? null;
                $postId = is_string($rawPostId) && preg_match('/\A[1-9][0-9]*\z/', $rawPostId) === 1
                    ? filter_var($rawPostId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;

                if ($postId === false) {
                    http_response_code(404);
                    $pageError = 'The requested post could not be found.';
                } else {
                    $deletePost = $pdo->prepare('DELETE FROM blogpost WHERE id = :id');
                    $deletePost->execute(['id' => $postId]);

                    if ($deletePost->rowCount() !== 1) {
                        http_response_code(404);
                        $pageError = 'The post may have already been removed.';
                    } else {
                        header('Location: admin-posts.php?deleted=1');
                        exit;
                    }
                }
            }
        }

        $postsQuery = $pdo->prepare(
            'SELECT blogpost.id, blogpost.title, blogpost.category, blogpost.created_at,
                    users.username AS author_username
             FROM blogpost
             INNER JOIN users ON users.id = blogpost.user_id
             ORDER BY blogpost.created_at DESC, blogpost.id DESC'
        );
        $postsQuery->execute();
        $posts = $postsQuery->fetchAll();
    } catch (Throwable $error) {
        error_log('BrainOverflow admin posts error: ' . $error->getMessage());
        http_response_code(500);
        $pageError = 'Posts are temporarily unavailable. Please try again later.';
    }
}
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
    <meta name="description" content="Manage BrainOverflow posts.">
    <title>Manage Posts - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { min-height: 100vh; background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg); }
        .admin-posts-main { padding: 64px 0; }
        .admin-posts-panel, .admin-posts-state { padding: clamp(24px, 5vw, 44px); background: var(--color-surface); border: 1px solid rgba(249, 115, 22, 0.16); border-radius: 18px; box-shadow: var(--shadow-card); }
        .admin-posts-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; padding-bottom: 22px; border-bottom: 1px solid var(--color-border); }
        .admin-posts-heading h1 { margin-bottom: 6px; }
        .admin-posts-heading p, .admin-posts-state p { color: var(--color-text-light); }
        .admin-posts-table-wrap { overflow-x: auto; }
        .admin-posts-table { width: 100%; border-collapse: collapse; }
        .admin-posts-table th, .admin-posts-table td { padding: 15px 12px; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--color-border); }
        .admin-posts-table th { color: var(--color-text-light); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .admin-post-title { min-width: 220px; font-weight: 700; overflow-wrap: anywhere; }
        .admin-post-category { display: inline-block; padding: 5px 9px; color: var(--color-primary-dark); background: var(--accent-light); border-radius: 999px; font-size: 0.8rem; font-weight: 700; white-space: nowrap; }
        .admin-post-muted { color: var(--color-text-light); }
        .admin-post-actions { display: flex; align-items: center; gap: 8px; }
        .admin-post-actions form { margin: 0; }
        .admin-post-actions .btn { padding: 7px 11px; font-size: 0.82rem; white-space: nowrap; }
        .btn-delete { color: #b91c1c; background: transparent; border: 1px solid #ef4444; }
        .btn-delete:hover { color: #fff; background: #dc2626; }
        .admin-posts-notice, .admin-posts-error { margin-bottom: 20px; padding: 13px 16px; border-radius: var(--radius-md); }
        .admin-posts-notice { color: #166534; background: #dcfce7; border: 1px solid #86efac; }
        .admin-posts-error { color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; }
        :root[data-theme="dark"] .admin-posts-notice { color: #bbf7d0; background: #14532d; border-color: #166534; }
        :root[data-theme="dark"] .admin-posts-error { color: #fecaca; background: #7f1d1d; border-color: #991b1b; }
        .admin-posts-state { max-width: 760px; margin: 0 auto; text-align: center; }
        .admin-posts-state h1 { margin-bottom: 10px; }
        .admin-posts-state p { margin-bottom: 24px; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <svg class="logo-svg" width="36" height="36" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                    <circle cx="18" cy="10" r="2.2" fill="#F97316"/><circle cx="11" cy="15" r="2" fill="#FB923C"/>
                    <circle cx="25" cy="15" r="2" fill="#FDBA74"/><circle cx="9" cy="22" r="1.8" fill="#FB923C"/>
                    <circle cx="18" cy="19" r="2.2" fill="#F97316"/><circle cx="27" cy="22" r="1.8" fill="#FB923C"/>
                    <line x1="18" y1="10" x2="11" y2="15" stroke="#F97316" stroke-width="0.8" opacity="0.5"/>
                    <line x1="18" y1="10" x2="25" y2="15" stroke="#FDBA74" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="18" y2="19" stroke="#FB923C" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="18" y2="19" stroke="#FDBA74" stroke-width="0.8" opacity="0.5"/>
                </svg>
                <span class="logo-text"><span class="logo-brain">Brain</span><span class="logo-overflow">Overflow</span></span>
            </a>

            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="explore.php">Explore</a></li>
                    <li><a href="my-posts.php">My Posts</a></li>
                    <?php if (!$forbidden): ?>
                        <li><a href="admin.php">Admin</a></li>
                        <li><a href="admin-posts.php" class="active">Manage Posts</a></li>
                    <?php endif; ?>
                    <li class="nav-auth-mobile"><span class="btn btn-login">Hi, <?php echo htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <li class="nav-auth-mobile">
                        <form class="logout-form" method="POST" action="logout.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-register">Logout</button>
                        </form>
                    </li>
                </ul>
            </nav>

            <div class="header-actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">&#9790;</button>
                <span class="user-chip">Hi, <?php echo htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                <form class="logout-form" method="POST" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-register">Logout</button>
                </form>
            </div>

            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" onclick="document.getElementById('main-nav').classList.toggle('open')">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <main class="admin-posts-main">
        <div class="container">
            <?php if ($forbidden): ?>
                <section class="admin-posts-state">
                    <h1>Access forbidden</h1>
                    <p>You do not have permission to manage posts.</p>
                    <a class="btn btn-hero" href="index.php">Return home</a>
                </section>
            <?php else: ?>
                <?php if ($postDeleted): ?><div class="admin-posts-notice" role="status">The post was deleted successfully.</div><?php endif; ?>
                <?php if ($pageError !== null): ?><div class="admin-posts-error" role="alert"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <section class="admin-posts-panel" aria-labelledby="admin-posts-title">
                    <div class="admin-posts-heading">
                        <div><h1 id="admin-posts-title">Manage Posts</h1><p>Review and moderate all BrainOverflow posts.</p></div>
                        <a class="btn btn-outline" href="admin.php">Dashboard</a>
                    </div>

                    <?php if (empty($posts)): ?>
                        <div class="admin-posts-state"><h2>No posts found</h2><p>There are currently no blog posts to manage.</p></div>
                    <?php else: ?>
                        <div class="admin-posts-table-wrap">
                            <table class="admin-posts-table">
                                <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Created</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($posts as $post): ?>
                                        <tr>
                                            <td class="admin-post-title"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php if ($post['category'] !== null && trim((string) $post['category']) !== ''): ?>
                                                    <span class="admin-post-category"><?php echo htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php else: ?>
                                                    <span class="admin-post-muted">Uncategorized</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><time datetime="<?php echo htmlspecialchars(date('c', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?></time></td>
                                            <td>
                                                <div class="admin-post-actions">
                                                    <a class="btn btn-outline" href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>">View</a>
                                                    <form method="POST" action="admin-posts.php" onsubmit="return window.confirm('Delete this post permanently? This action cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button class="btn btn-delete" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand"><span>Brain<span class="text-blue">Overflow</span></span></div>
            <span class="footer-text">&copy; <?php echo date('Y'); ?> BrainOverflow. All rights reserved.</span>
            <div class="footer-links"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Contact</a></div>
        </div>
    </footer>

    <div class="cursor-glow" id="cursorGlow"></div>
    <script src="js/main.js"></script>
</body>
</html>
