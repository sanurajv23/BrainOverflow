<?php
// BrainOverflow - Home Page
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

$isLoggedIn = brainoverflow_is_logged_in();
$currentUsername = brainoverflow_current_username();
$postDeleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';

$blogPosts = [];
$postsLoadFailed = false;

try {
    require __DIR__ . '/config/database.php';

    $postsQuery = $pdo->query(
        'SELECT blogpost.id, blogpost.title, blogpost.content, blogpost.created_at,
                users.username AS author_username
         FROM blogpost
         INNER JOIN users ON users.id = blogpost.user_id
         ORDER BY blogpost.created_at DESC, blogpost.id DESC'
    );
    $blogPosts = $postsQuery->fetchAll();
} catch (Throwable $error) {
    error_log('BrainOverflow homepage posts error: ' . $error->getMessage());
    $postsLoadFailed = true;
}

$featuredPosts = array_slice($blogPosts, 0, 3);

function brainoverflow_post_excerpt(string $content, int $maximumLength = 170): string
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

function brainoverflow_author_initials(string $username): string
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
    <meta name="description" content="BrainOverflow — A modern blogging platform to share your knowledge, ideas, and insights with the world.">
    <title>BrainOverflow — Share Ideas. Inspire Minds.</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <!-- ===== Header / Navigation ===== -->
    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo">
                <svg class="logo-svg" width="36" height="36" viewBox="0 0 36 36" fill="none">
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
                    <line x1="9" y1="22" x2="13" y2="27" stroke="#FDBA74" stroke-width="0.8" opacity="0.4"/>
                    <line x1="18" y1="19" x2="13" y2="27" stroke="#F97316" stroke-width="0.8" opacity="0.4"/>
                    <line x1="18" y1="19" x2="23" y2="27" stroke="#FB923C" stroke-width="0.8" opacity="0.4"/>
                    <line x1="27" y1="22" x2="23" y2="27" stroke="#FB923C" stroke-width="0.8" opacity="0.4"/>
                    <line x1="9" y1="22" x2="18" y2="19" stroke="#FB923C" stroke-width="0.8" opacity="0.3"/>
                    <line x1="27" y1="22" x2="18" y2="19" stroke="#FB923C" stroke-width="0.8" opacity="0.3"/>
                </svg>
                <span class="logo-text"><span class="logo-brain">Brain</span><span class="logo-overflow">Overflow</span></span>
            </a>

            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="explore.php">Explore</a></li>
                    <li><a href="<?php echo $isLoggedIn ? 'create-post.php' : 'login.php'; ?>">Write</a></li>
                    <?php if ($isLoggedIn): ?>
                    <li><a href="my-posts.php">My Posts</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <?php endif; ?>
                    <li><a href="#">About</a></li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-auth-mobile"><a href="#" class="btn btn-login">Hi, <?php echo htmlspecialchars($currentUsername); ?></a></li>
                    <li class="nav-auth-mobile">
                        <form class="logout-form" method="POST" action="logout.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token()); ?>">
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
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">☾</button>
                <?php if ($isLoggedIn): ?>
                <span class="user-chip">Hi, <?php echo htmlspecialchars($currentUsername); ?></span>
                <form class="logout-form" method="POST" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token()); ?>">
                    <button type="submit" class="btn btn-register">Logout</button>
                </form>
                <?php else: ?>
                <a href="login.php" class="btn btn-login">Login</a>
                <a href="register.php" class="btn btn-register">Register</a>
                <?php endif; ?>
            </div>

            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" onclick="document.getElementById('main-nav').classList.toggle('open')">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <?php if ($postDeleted): ?>
    <div class="container">
        <div class="home-notice" role="status">Your post was deleted successfully.</div>
    </div>
    <?php endif; ?>

    <!-- ===== Hero Section ===== -->
    <section class="hero">
        <div class="container hero-inner">
            <div class="hero-content">
                <h1>Share Ideas.<br><span class="text-blue">Inspire</span> Minds.</h1>
                <p>BrainOverflow is a space for curious minds to learn, share knowledge, and grow together.</p>
                <div class="hero-buttons">
                    <a href="<?php echo $isLoggedIn ? 'create-post.php' : 'login.php'; ?>" class="btn btn-hero">
                        <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                        Start Writing
                    </a>
                    <a href="#featured" class="btn btn-outline">
                        <svg class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        Explore Blogs
                    </a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="laptop">
                    <div class="laptop-screen">
                        <div class="screen-dots">
                            <span class="dot dot-red"></span>
                            <span class="dot dot-blue"></span>
                            <span class="dot dot-green"></span>
                        </div>
                        <div class="screen-code">
                            <div class="code-line cl-purple" style="width: 45%;"></div>
                            <div class="code-line cl-blue" style="width: 70%;"></div>
                            <div class="code-line cl-cyan" style="width: 50%;"></div>
                            <div class="code-line cl-blue" style="width: 80%;"></div>
                            <div class="code-line cl-green" style="width: 35%;"></div>
                            <div class="code-line cl-purple" style="width: 60%;"></div>
                            <div class="code-line cl-cyan" style="width: 45%;"></div>
                            <div class="code-line cl-blue" style="width: 55%;"></div>
                        </div>
                    </div>
                    <div class="laptop-base"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== Featured Posts Section ===== -->
    <section class="featured-posts" id="featured">
        <div class="container">
            <div class="section-header">
                <h2><span class="section-bar"></span>Featured Posts</h2>
                <a href="#" class="view-all">View all &rarr;</a>
            </div>

            <div class="blog-grid">
                <?php if (empty($featuredPosts)): ?>
                <div class="posts-empty">
                    <h3><?php echo $postsLoadFailed ? 'Posts are temporarily unavailable' : 'No posts yet'; ?></h3>
                    <p><?php echo $postsLoadFailed ? 'Please try again later.' : 'Be the first to share something with the community.'; ?></p>
                </div>
                <?php else: ?>
                <?php foreach ($featuredPosts as $post): ?>
                <article class="blog-card">
                    <div class="card-thumb thumb-php"></div>
                    <div class="card-body">
                        <span class="card-category cat-php">Community</span>
                        <h3><a href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                        <p class="card-excerpt"><?php echo htmlspecialchars(brainoverflow_post_excerpt($post['content']), ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="card-meta">
                            <div class="meta-author">
                                <div class="author-avatar av-php"><?php echo htmlspecialchars(brainoverflow_author_initials($post['author_username']), ENT_QUOTES, 'UTF-8'); ?></div>
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

    <!-- ===== Latest Blogs Section ===== -->
    <section class="latest-blogs" id="latest">
        <div class="container">
            <div class="section-header">
                <h2>
                    <svg class="section-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    Latest Blogs
                </h2>
                <a href="#" class="view-all">View all &rarr;</a>
            </div>

            <div class="blog-list">
                <?php if (empty($blogPosts)): ?>
                <div class="posts-empty posts-empty-list">
                    <p><?php echo $postsLoadFailed ? 'Posts could not be loaded right now.' : 'New posts will appear here once they are published.'; ?></p>
                </div>
                <?php else: ?>
                <?php foreach ($blogPosts as $post): ?>
                <article class="blog-row">
                    <div class="row-thumb thumb-php">
                        <span>POST</span>
                    </div>
                    <div class="row-content">
                        <span class="card-category cat-php">Community</span>
                        <h3><a href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                        <p class="row-excerpt"><?php echo htmlspecialchars(brainoverflow_post_excerpt($post['content']), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="row-meta">
                        <div class="meta-author">
                            <div class="author-avatar av-php"><?php echo htmlspecialchars(brainoverflow_author_initials($post['author_username']), ENT_QUOTES, 'UTF-8'); ?></div>
                            <span class="author-name"><?php echo htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <span class="post-date"><?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <a href="post.php?id=<?php echo rawurlencode((string) $post['id']); ?>" class="row-arrow" aria-label="Read <?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>">&rarr;</a>
                </article>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ===== Footer ===== -->
    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <svg class="footer-logo-svg" width="22" height="22" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="10" r="2.2" fill="#F97316"/>
                    <circle cx="11" cy="15" r="2" fill="#FB923C"/>
                    <circle cx="25" cy="15" r="2" fill="#FDBA74"/>
                    <circle cx="18" cy="19" r="2.2" fill="#F97316"/>
                    <circle cx="13" cy="27" r="1.6" fill="#FDBA74"/>
                    <circle cx="23" cy="27" r="1.6" fill="#FB923C"/>
                    <line x1="18" y1="10" x2="11" y2="15" stroke="#F97316" stroke-width="0.8" opacity="0.5"/>
                    <line x1="18" y1="10" x2="25" y2="15" stroke="#FDBA74" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="18" y2="19" stroke="#FB923C" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="18" y2="19" stroke="#FDBA74" stroke-width="0.8" opacity="0.5"/>
                </svg>
                <span>Brain<span class="text-blue">Overflow</span></span>
            </div>
            <span class="footer-text">&copy; <?php echo date('Y'); ?> BrainOverflow. All rights reserved.</span>
            <div class="footer-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#">Contact</a>
            </div>
        </div>
    </footer>

    <!-- Mouse glow element -->
    <div class="cursor-glow" id="cursorGlow"></div>

    <script src="js/main.js"></script>
</body>
</html>
