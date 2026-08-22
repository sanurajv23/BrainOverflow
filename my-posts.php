<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

$currentUsername = brainoverflow_current_username();
$blogPosts = [];
$postsLoadFailed = false;

try {
    require __DIR__ . '/config/database.php';

    $postsQuery = $pdo->prepare(
        'SELECT id, title, content, created_at
         FROM blogpost
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC'
    );
    $postsQuery->execute(['user_id' => $_SESSION['user_id']]);
    $blogPosts = $postsQuery->fetchAll();
} catch (Throwable $error) {
    error_log('BrainOverflow my posts error: ' . $error->getMessage());
    $postsLoadFailed = true;
}

function brainoverflow_my_posts_excerpt(string $content, int $maximumLength = 170): string
{
    $normalizedContent = preg_replace('/\s+/u', ' ', trim($content)) ?? trim($content);
    $contentLength = function_exists('mb_strlen') ? mb_strlen($normalizedContent, 'UTF-8') : strlen($normalizedContent);

    if ($contentLength <= $maximumLength) {
        return $normalizedContent;
    }

    $excerpt = function_exists('mb_substr')
        ? mb_substr($normalizedContent, 0, $maximumLength - 1, 'UTF-8')
        : substr($normalizedContent, 0, $maximumLength - 1);

    return rtrim($excerpt) . '…';
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
    <meta name="description" content="Manage your BrainOverflow blog posts.">
    <title>My Posts - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .my-posts-hero { padding: 64px 0 8px; text-align: center; }
        .my-posts-hero h1 { margin-bottom: 12px; font-size: clamp(2rem, 5vw, 3.25rem); }
        .my-posts-hero p { max-width: 620px; margin: 0 auto; color: var(--color-text-light); }
        .my-posts-list { padding-top: 38px; }
        .my-posts-list .blog-card h3 a:hover { color: var(--color-primary-dark); }
        .my-post-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--color-border); }
        .my-post-actions form { margin: 0; }
        .my-post-actions .btn { padding: 8px 14px; font-size: 0.86rem; }
        .btn-delete { color: #b91c1c; background: transparent; border: 1px solid #ef4444; }
        .btn-delete:hover { color: #fff; background: #dc2626; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <svg class="logo-svg" width="36" height="36" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                    <circle cx="18" cy="10" r="2.2" fill="#F97316"/>
                    <circle cx="11" cy="15" r="2" fill="#FB923C"/>
                    <circle cx="25" cy="15" r="2" fill="#FDBA74"/>
                    <circle cx="9" cy="22" r="1.8" fill="#FB923C"/>
                    <circle cx="18" cy="19" r="2.2" fill="#F97316"/>
                    <circle cx="27" cy="22" r="1.8" fill="#FB923C"/>
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
                    <li><a href="create-post.php">Write</a></li>
                    <li><a href="my-posts.php" class="active">My Posts</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="#">About</a></li>
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

    <main>
        <section class="my-posts-hero">
            <div class="container">
                <h1>My Posts</h1>
                <p>View and manage the posts you have shared with the BrainOverflow community.</p>
            </div>
        </section>

        <section class="featured-posts my-posts-list">
            <div class="container">
                <div class="section-header">
                    <h2><span class="section-bar"></span>Your Posts</h2>
                    <a class="btn btn-hero" href="create-post.php">Create post</a>
                </div>

                <div class="blog-grid">
                    <?php if (empty($blogPosts)): ?>
                    <div class="posts-empty">
                        <h3><?php echo $postsLoadFailed ? 'Posts are temporarily unavailable' : 'You have not written any posts yet'; ?></h3>
                        <p><?php echo $postsLoadFailed ? 'Please try again later.' : 'Create your first post and share it with the community.'; ?></p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($blogPosts as $post): ?>
                    <article class="blog-card">
                        <div class="card-thumb thumb-php"></div>
                        <div class="card-body">
                            <span class="card-category cat-php">Your post</span>
                            <h3><a href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                            <p class="card-excerpt"><?php echo htmlspecialchars(brainoverflow_my_posts_excerpt($post['content']), ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="card-meta">
                                <span class="post-date"><?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="my-post-actions">
                                <a class="btn btn-outline" href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>">View</a>
                                <a class="btn btn-hero" href="edit-post.php?id=<?php echo rawurlencode((string) $post['id']); ?>">Edit</a>
                                <form method="POST" action="delete-post.php" onsubmit="return window.confirm('Delete this post permanently? This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="btn btn-delete" type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
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
