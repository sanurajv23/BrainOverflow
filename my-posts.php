<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

const BRAINOVERFLOW_MY_POSTS_PER_PAGE = 10;

$currentUsername = brainoverflow_current_username();
$blogPosts = [];
$postsLoadFailed = false;
$rawPage = $_GET['page'] ?? null;
$requestedPage = is_string($rawPage) && preg_match('/\A[1-9][0-9]*\z/', $rawPage) === 1
    ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : 1;
$requestedPage = $requestedPage !== false ? $requestedPage : 1;
$currentPage = $requestedPage;
$totalPosts = 0;
$totalPages = 1;

try {
    require __DIR__ . '/config/database.php';

    $countQuery = $pdo->prepare('SELECT COUNT(*) FROM blogpost WHERE user_id = :user_id');
    $countQuery->execute(['user_id' => $_SESSION['user_id']]);
    $totalPosts = (int) $countQuery->fetchColumn();
    $totalPages = max(1, (int) ceil($totalPosts / BRAINOVERFLOW_MY_POSTS_PER_PAGE));
    $currentPage = min($requestedPage, $totalPages);
    $offset = ($currentPage - 1) * BRAINOVERFLOW_MY_POSTS_PER_PAGE;

    $postsQuery = $pdo->prepare(
        'SELECT id, title, content, created_at
         FROM blogpost
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );
    $postsQuery->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $postsQuery->bindValue(':limit', BRAINOVERFLOW_MY_POSTS_PER_PAGE, PDO::PARAM_INT);
    $postsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
    $postsQuery->execute();
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

function brainoverflow_my_posts_page_url(int $page): string
{
    return 'my-posts.php?' . http_build_query(['page' => $page], '', '&', PHP_QUERY_RFC3986);
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
        .pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; margin-top: 34px; }
        .pagination-link { display: inline-flex; min-width: 40px; min-height: 40px; align-items: center; justify-content: center; padding: 8px 13px; color: var(--color-text); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-weight: 650; }
        .pagination-link:hover { color: var(--color-primary-dark); border-color: var(--color-border-hover); }
        .pagination-link.active { color: #fff; background: var(--color-primary); border-color: var(--color-primary); }
        .pagination-link.disabled { color: var(--color-text-muted); opacity: 0.65; cursor: not-allowed; }
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

                <?php if (!$postsLoadFailed && $totalPages > 1): ?>
                <nav class="pagination" aria-label="My posts pages">
                    <?php if ($currentPage > 1): ?>
                        <a class="pagination-link" href="<?php echo htmlspecialchars(brainoverflow_my_posts_page_url($currentPage - 1), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                    <?php else: ?>
                        <span class="pagination-link disabled" aria-disabled="true">Previous</span>
                    <?php endif; ?>

                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a class="pagination-link<?php echo $pageNumber === $currentPage ? ' active' : ''; ?>" href="<?php echo htmlspecialchars(brainoverflow_my_posts_page_url($pageNumber), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $pageNumber === $currentPage ? ' aria-current="page"' : ''; ?>><?php echo $pageNumber; ?></a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="pagination-link" href="<?php echo htmlspecialchars(brainoverflow_my_posts_page_url($currentPage + 1), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                    <?php else: ?>
                        <span class="pagination-link disabled" aria-disabled="true">Next</span>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
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
