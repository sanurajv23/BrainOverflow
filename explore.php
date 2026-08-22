<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

$isLoggedIn = brainoverflow_is_logged_in();
$currentUsername = brainoverflow_current_username();
$blogPosts = [];
$postsLoadFailed = false;
$searchTerm = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$hasSearch = $searchTerm !== '';

try {
    require __DIR__ . '/config/database.php';

    $postsSql =
        'SELECT blogpost.id, blogpost.title, blogpost.content, blogpost.created_at,
                users.username AS author_username
         FROM blogpost
         INNER JOIN users ON users.id = blogpost.user_id';

    if ($hasSearch) {
        $postsSql .=
            " WHERE (blogpost.title LIKE :title_term ESCAPE '!'
                     OR blogpost.content LIKE :content_term ESCAPE '!')";
    }

    $postsSql .= ' ORDER BY blogpost.created_at DESC, blogpost.id DESC';
    $postsQuery = $pdo->prepare($postsSql);

    if ($hasSearch) {
        $escapedSearchTerm = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchTerm);
        $likeSearchTerm = '%' . $escapedSearchTerm . '%';
        $postsQuery->execute([
            'title_term' => $likeSearchTerm,
            'content_term' => $likeSearchTerm,
        ]);
    } else {
        $postsQuery->execute();
    }

    $blogPosts = $postsQuery->fetchAll();
} catch (Throwable $error) {
    error_log('BrainOverflow explore posts error: ' . $error->getMessage());
    $postsLoadFailed = true;
}

function brainoverflow_explore_excerpt(string $content, int $maximumLength = 170): string
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

function brainoverflow_explore_initials(string $username): string
{
    $initials = function_exists('mb_substr') ? mb_substr($username, 0, 2, 'UTF-8') : substr($username, 0, 2);

    return function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);
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
    <meta name="description" content="Explore blog posts from the BrainOverflow community.">
    <title>Explore - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .explore-hero { padding: 64px 0 8px; text-align: center; }
        .explore-hero h1 { margin-bottom: 12px; font-size: clamp(2rem, 5vw, 3.25rem); }
        .explore-hero p { max-width: 620px; margin: 0 auto; color: var(--color-text-light); }
        .explore-posts { padding-top: 38px; }
        .explore-posts .blog-card h3 a:hover { color: var(--color-primary-dark); }
        .explore-search { display: flex; width: min(680px, 100%); margin: 30px auto 0; padding: 7px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-full); box-shadow: var(--shadow-sm); }
        .explore-search:focus-within { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--accent-light); }
        .explore-search input { flex: 1; min-width: 0; padding: 10px 16px; color: var(--color-text); background: transparent; border: 0; outline: 0; font: inherit; }
        .explore-search .btn { flex: 0 0 auto; }
        .search-summary { color: var(--color-text-light); font-size: 0.95rem; }
        @media (max-width: 520px) { .explore-search { border-radius: var(--radius-md); } .explore-search .btn { padding-inline: 16px; } }
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
                    <circle cx="13" cy="27" r="1.6" fill="#FDBA74"/>
                    <circle cx="23" cy="27" r="1.6" fill="#FB923C"/>
                    <line x1="18" y1="10" x2="11" y2="15" stroke="#F97316" stroke-width="0.8" opacity="0.5"/>
                    <line x1="18" y1="10" x2="25" y2="15" stroke="#FDBA74" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="18" y2="19" stroke="#FB923C" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="18" y2="19" stroke="#FDBA74" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="9" y2="22" stroke="#FB923C" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="27" y2="22" stroke="#FB923C" stroke-width="0.8" opacity="0.5"/>
                </svg>
                <span class="logo-text"><span class="logo-brain">Brain</span><span class="logo-overflow">Overflow</span></span>
            </a>

            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="explore.php" class="active">Explore</a></li>
                    <li><a href="<?php echo $isLoggedIn ? 'create-post.php' : 'login.php'; ?>">Write</a></li>
                    <li><a href="#">About</a></li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-auth-mobile"><span class="btn btn-login">Hi, <?php echo htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <li class="nav-auth-mobile">
                        <form class="logout-form" method="POST" action="logout.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-register">Logout</button>
                        </form>
                    </li>
                    <?php else: ?>
                    <li class="nav-auth-mobile"><a href="login.php" class="btn btn-login">Login</a></li>
                    <li class="nav-auth-mobile"><a href="register.php" class="btn btn-register">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">&#9790;</button>
                <?php if ($isLoggedIn): ?>
                <span class="user-chip">Hi, <?php echo htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                <form class="logout-form" method="POST" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-register">Logout</button>
                </form>
                <?php else: ?>
                <a href="login.php" class="btn btn-login">Login</a>
                <a href="register.php" class="btn btn-register">Register</a>
                <?php endif; ?>
            </div>

            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" onclick="document.getElementById('main-nav').classList.toggle('open')">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <main>
        <section class="explore-hero">
            <div class="container">
                <h1>Explore Blogs</h1>
                <p>Discover ideas, lessons, and stories shared by the BrainOverflow community.</p>
                <form class="explore-search" method="GET" action="explore.php" role="search">
                    <input type="search" name="q" aria-label="Search blog posts" placeholder="Search titles and content" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-hero" type="submit">Search</button>
                </form>
            </div>
        </section>

        <section class="featured-posts explore-posts">
            <div class="container">
                <div class="section-header">
                    <h2><span class="section-bar"></span><?php echo $hasSearch ? 'Search Results' : 'All Posts'; ?></h2>
                    <?php if ($hasSearch && !$postsLoadFailed): ?>
                    <span class="search-summary"><?php echo count($blogPosts); ?> result<?php echo count($blogPosts) === 1 ? '' : 's'; ?></span>
                    <?php endif; ?>
                </div>

                <div class="blog-grid">
                    <?php if (empty($blogPosts)): ?>
                    <div class="posts-empty">
                        <h3><?php echo $postsLoadFailed ? 'Posts are temporarily unavailable' : ($hasSearch ? 'No results found' : 'No posts yet'); ?></h3>
                        <p>
                            <?php if ($postsLoadFailed): ?>
                                Please try again later.
                            <?php elseif ($hasSearch): ?>
                                No posts matched “<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>”. Try a different search.
                            <?php else: ?>
                                Be the first to share something with the community.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($blogPosts as $post): ?>
                    <article class="blog-card">
                        <div class="card-thumb thumb-php"></div>
                        <div class="card-body">
                            <span class="card-category cat-php">Community</span>
                            <h3><a href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                            <p class="card-excerpt"><?php echo htmlspecialchars(brainoverflow_explore_excerpt($post['content']), ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="card-meta">
                                <div class="meta-author">
                                    <div class="author-avatar av-php"><?php echo htmlspecialchars(brainoverflow_explore_initials($post['author_username']), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <span class="author-name"><?php echo htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <span class="post-date"><?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
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
