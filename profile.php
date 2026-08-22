<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

$currentUsername = brainoverflow_current_username();
$profile = null;
$profileLoadFailed = false;

try {
    require __DIR__ . '/config/database.php';

    $profileQuery = $pdo->prepare(
        'SELECT users.username, users.email, users.role,
                (SELECT COUNT(*) FROM blogpost WHERE blogpost.user_id = users.id) AS post_count
         FROM users
         WHERE users.id = :user_id
         LIMIT 1'
    );
    $profileQuery->execute(['user_id' => $_SESSION['user_id']]);
    $profile = $profileQuery->fetch() ?: null;

    if ($profile === null) {
        http_response_code(404);
    }
} catch (Throwable $error) {
    error_log('BrainOverflow profile error: ' . $error->getMessage());
    $profileLoadFailed = true;
    http_response_code(500);
}

function brainoverflow_profile_initials(string $username): string
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
    <meta name="description" content="View your BrainOverflow profile.">
    <title>Profile - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { min-height: 100vh; background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg); }
        .profile-main { padding: 64px 0; }
        .profile-card, .profile-state {
            max-width: 760px; margin: 0 auto; padding: clamp(26px, 6vw, 54px); background: var(--color-surface);
            border: 1px solid rgba(249, 115, 22, 0.16); border-radius: 18px; box-shadow: var(--shadow-card);
        }
        .profile-heading { display: flex; align-items: center; gap: 20px; margin-bottom: 34px; padding-bottom: 28px; border-bottom: 1px solid var(--color-border); }
        .profile-avatar { display: grid; flex: 0 0 72px; width: 72px; height: 72px; place-items: center; color: #fff; background: var(--color-primary); border-radius: 50%; font-size: 1.4rem; font-weight: 750; }
        .profile-heading h1 { margin-bottom: 5px; overflow-wrap: anywhere; }
        .profile-heading p { color: var(--color-text-light); }
        .profile-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .profile-detail { padding: 18px; background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-md); }
        .profile-detail dt { margin-bottom: 5px; color: var(--color-text-light); font-size: 0.82rem; font-weight: 650; text-transform: uppercase; letter-spacing: 0.04em; }
        .profile-detail dd { font-weight: 650; overflow-wrap: anywhere; }
        .profile-actions { display: flex; justify-content: flex-end; margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--color-border); }
        .profile-state { text-align: center; }
        .profile-state h1 { margin-bottom: 10px; }
        .profile-state p { margin-bottom: 24px; color: var(--color-text-light); }
        @media (max-width: 560px) { .profile-details { grid-template-columns: 1fr; } .profile-heading { align-items: flex-start; } }
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
                    <li><a href="my-posts.php">My Posts</a></li>
                    <li><a href="profile.php" class="active">Profile</a></li>
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

    <main class="profile-main">
        <div class="container">
            <?php if ($profile !== null): ?>
            <section class="profile-card" aria-labelledby="profile-title">
                <div class="profile-heading">
                    <div class="profile-avatar"><?php echo htmlspecialchars(brainoverflow_profile_initials($profile['username']), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div>
                        <h1 id="profile-title"><?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p>Your BrainOverflow account</p>
                    </div>
                </div>

                <dl class="profile-details">
                    <div class="profile-detail"><dt>Username</dt><dd><?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div class="profile-detail"><dt>Email</dt><dd><?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div class="profile-detail"><dt>Role</dt><dd><?php echo htmlspecialchars(ucfirst($profile['role']), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                    <div class="profile-detail"><dt>Posts created</dt><dd><?php echo htmlspecialchars((string) $profile['post_count'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                </dl>

                <div class="profile-actions"><a class="btn btn-hero" href="my-posts.php">View my posts</a></div>
            </section>
            <?php else: ?>
            <section class="profile-state">
                <h1><?php echo $profileLoadFailed ? 'Profile temporarily unavailable' : 'Profile not found'; ?></h1>
                <p><?php echo $profileLoadFailed ? 'Please try again later.' : 'Your account details could not be found.'; ?></p>
                <a class="btn btn-hero" href="index.php">Return home</a>
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
