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
$users = [];
$pageError = null;
$userDeleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';

if (!$forbidden) {
    try {
        require __DIR__ . '/config/database.php';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                $pageError = 'Your session token is invalid or has expired. Please try again.';
            } else {
                $rawUserId = $_POST['id'] ?? null;
                $userId = is_string($rawUserId) && preg_match('/\A[1-9][0-9]*\z/', $rawUserId) === 1
                    ? filter_var($rawUserId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;

                if ($userId === false) {
                    http_response_code(404);
                    $pageError = 'The requested user could not be found.';
                } elseif ($userId === $_SESSION['user_id']) {
                    http_response_code(403);
                    $pageError = 'You cannot delete your own administrator account.';
                } else {
                    $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $deleteUser->execute(['id' => $userId]);

                    if ($deleteUser->rowCount() !== 1) {
                        http_response_code(404);
                        $pageError = 'The user may have already been removed.';
                    } else {
                        header('Location: admin-users.php?deleted=1');
                        exit;
                    }
                }
            }
        }

        $usersQuery = $pdo->prepare(
            'SELECT users.id, users.username, users.email, users.role,
                    COUNT(blogpost.id) AS post_count
             FROM users
             LEFT JOIN blogpost ON blogpost.user_id = users.id
             GROUP BY users.id, users.username, users.email, users.role
             ORDER BY users.id DESC'
        );
        $usersQuery->execute();
        $users = $usersQuery->fetchAll();
    } catch (Throwable $error) {
        error_log('BrainOverflow admin users error: ' . $error->getMessage());
        http_response_code(500);
        $pageError = 'Users are temporarily unavailable. Please try again later.';
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
    <meta name="description" content="Manage BrainOverflow users.">
    <title>Manage Users - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { min-height: 100vh; background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg); }
        .admin-users-main { padding: 64px 0; }
        .admin-users-panel, .admin-users-state { padding: clamp(24px, 5vw, 44px); background: var(--color-surface); border: 1px solid rgba(249, 115, 22, 0.16); border-radius: 18px; box-shadow: var(--shadow-card); }
        .admin-users-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; padding-bottom: 22px; border-bottom: 1px solid var(--color-border); }
        .admin-users-heading h1 { margin-bottom: 6px; }
        .admin-users-heading p, .admin-users-state p { color: var(--color-text-light); }
        .admin-users-table-wrap { overflow-x: auto; }
        .admin-users-table { width: 100%; border-collapse: collapse; }
        .admin-users-table th, .admin-users-table td { padding: 15px 12px; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--color-border); }
        .admin-users-table th { color: var(--color-text-light); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .admin-user-value { overflow-wrap: anywhere; }
        .admin-user-role { display: inline-block; padding: 5px 9px; color: var(--color-primary-dark); background: var(--accent-light); border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .admin-user-actions form { margin: 0; }
        .admin-user-actions .btn { padding: 7px 11px; font-size: 0.82rem; white-space: nowrap; }
        .admin-current-user { color: var(--color-text-light); font-size: 0.85rem; font-weight: 650; }
        .btn-delete { color: #b91c1c; background: transparent; border: 1px solid #ef4444; }
        .btn-delete:hover { color: #fff; background: #dc2626; }
        .admin-users-notice, .admin-users-error { margin-bottom: 20px; padding: 13px 16px; border-radius: var(--radius-md); }
        .admin-users-notice { color: #166534; background: #dcfce7; border: 1px solid #86efac; }
        .admin-users-error { color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; }
        :root[data-theme="dark"] .admin-users-notice { color: #bbf7d0; background: #14532d; border-color: #166534; }
        :root[data-theme="dark"] .admin-users-error { color: #fecaca; background: #7f1d1d; border-color: #991b1b; }
        .admin-users-state { max-width: 760px; margin: 0 auto; text-align: center; }
        .admin-users-state h1 { margin-bottom: 10px; }
        .admin-users-state p { margin-bottom: 24px; }
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
                        <li><a href="admin-posts.php">Manage Posts</a></li>
                        <li><a href="admin-users.php" class="active">Manage Users</a></li>
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

    <main class="admin-users-main">
        <div class="container">
            <?php if ($forbidden): ?>
                <section class="admin-users-state">
                    <h1>Access forbidden</h1>
                    <p>You do not have permission to manage users.</p>
                    <a class="btn btn-hero" href="index.php">Return home</a>
                </section>
            <?php else: ?>
                <?php if ($userDeleted): ?><div class="admin-users-notice" role="status">The user account was deleted successfully.</div><?php endif; ?>
                <?php if ($pageError !== null): ?><div class="admin-users-error" role="alert"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <section class="admin-users-panel" aria-labelledby="admin-users-title">
                    <div class="admin-users-heading">
                        <div><h1 id="admin-users-title">Manage Users</h1><p>Review BrainOverflow accounts and their contributions.</p></div>
                        <a class="btn btn-outline" href="admin.php">Dashboard</a>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="admin-users-state"><h2>No users found</h2><p>There are currently no user accounts to display.</p></div>
                    <?php else: ?>
                        <div class="admin-users-table-wrap">
                            <table class="admin-users-table">
                                <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Posts</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="admin-user-value"><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="admin-user-value"><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><span class="admin-user-role"><?php echo htmlspecialchars(ucfirst((string) $user['role']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><?php echo htmlspecialchars((string) $user['post_count'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="admin-user-actions">
                                                <?php if ((int) $user['id'] === $_SESSION['user_id']): ?>
                                                    <span class="admin-current-user">Current account</span>
                                                <?php else: ?>
                                                    <form method="POST" action="admin-users.php" onsubmit="return window.confirm('Delete this user permanently? Their posts, comments, and likes will also be removed.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button class="btn btn-delete" type="submit">Delete</button>
                                                    </form>
                                                <?php endif; ?>
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
