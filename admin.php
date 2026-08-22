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
$counts = [
    'users' => 0,
    'posts' => 0,
    'comments' => 0,
    'likes' => 0,
];
$countsLoadFailed = false;

if (!$forbidden) {
    try {
        require __DIR__ . '/config/database.php';

        $usersCountQuery = $pdo->prepare('SELECT COUNT(*) FROM users');
        $usersCountQuery->execute();
        $counts['users'] = (int) $usersCountQuery->fetchColumn();

        $postsCountQuery = $pdo->prepare('SELECT COUNT(*) FROM blogpost');
        $postsCountQuery->execute();
        $counts['posts'] = (int) $postsCountQuery->fetchColumn();

        $commentsCountQuery = $pdo->prepare('SELECT COUNT(*) FROM comments');
        $commentsCountQuery->execute();
        $counts['comments'] = (int) $commentsCountQuery->fetchColumn();

        $likesCountQuery = $pdo->prepare('SELECT COUNT(*) FROM post_likes');
        $likesCountQuery->execute();
        $counts['likes'] = (int) $likesCountQuery->fetchColumn();
    } catch (Throwable $error) {
        error_log('BrainOverflow admin dashboard error: ' . $error->getMessage());
        $countsLoadFailed = true;
        http_response_code(500);
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
    <meta name="description" content="BrainOverflow administration dashboard.">
    <title>Admin Dashboard - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { min-height: 100vh; background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg); }
        .admin-main { padding: 64px 0; }
        .admin-panel, .admin-state { max-width: 920px; margin: 0 auto; padding: clamp(26px, 6vw, 54px); background: var(--color-surface); border: 1px solid rgba(249, 115, 22, 0.16); border-radius: 18px; box-shadow: var(--shadow-card); }
        .admin-heading { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--color-border); }
        .admin-heading h1 { margin-bottom: 8px; }
        .admin-heading p, .admin-state p { color: var(--color-text-light); }
        .admin-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .admin-stat { padding: 22px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); }
        .admin-stat dt { margin-bottom: 8px; color: var(--color-text-light); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .admin-stat dd { color: var(--color-primary-dark); font-size: 2rem; font-weight: 800; }
        .admin-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; padding-top: 24px; border-top: 1px solid var(--color-border); }
        .admin-state { text-align: center; }
        .admin-state h1 { margin-bottom: 10px; }
        .admin-state p { margin-bottom: 24px; }
        @media (max-width: 720px) { .admin-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 420px) { .admin-stats { grid-template-columns: 1fr; } }
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
                    <?php if (!$forbidden): ?><li><a href="admin.php" class="active">Admin</a></li><?php endif; ?>
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

    <main class="admin-main">
        <div class="container">
            <?php if ($forbidden): ?>
                <section class="admin-state">
                    <h1>Access forbidden</h1>
                    <p>You do not have permission to view the admin dashboard.</p>
                    <a class="btn btn-hero" href="index.php">Return home</a>
                </section>
            <?php elseif ($countsLoadFailed): ?>
                <section class="admin-state">
                    <h1>Dashboard temporarily unavailable</h1>
                    <p>The site totals could not be loaded. Please try again later.</p>
                    <a class="btn btn-hero" href="index.php">Return home</a>
                </section>
            <?php else: ?>
                <section class="admin-panel" aria-labelledby="admin-title">
                    <div class="admin-heading">
                        <h1 id="admin-title">Admin Dashboard</h1>
                        <p>Overview of the BrainOverflow community.</p>
                    </div>
                    <dl class="admin-stats">
                        <div class="admin-stat"><dt>Total users</dt><dd><?php echo htmlspecialchars((string) $counts['users'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div class="admin-stat"><dt>Total posts</dt><dd><?php echo htmlspecialchars((string) $counts['posts'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div class="admin-stat"><dt>Total comments</dt><dd><?php echo htmlspecialchars((string) $counts['comments'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        <div class="admin-stat"><dt>Total likes</dt><dd><?php echo htmlspecialchars((string) $counts['likes'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    </dl>
                    <div class="admin-actions">
                        <a class="btn btn-hero" href="explore.php">Explore posts</a>
                        <a class="btn btn-outline" href="my-posts.php">My Posts</a>
                    </div>
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
