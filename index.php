<?php
// BrainOverflow - Home Page
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

$isLoggedIn = brainoverflow_is_logged_in();
$currentUsername = brainoverflow_current_username();

// Static sample data for blog posts (no database connection)
$blogPosts = [
    [
        'title'    => 'Getting Started with PHP 8: New Features You Should Know',
        'excerpt'  => 'Explore the powerful new features in PHP 8, including named arguments, union types, and the match expression that make your code cleaner and more expressive.',
        'author'   => 'Sarah Chen',
        'initials' => 'SC',
        'date'     => 'August 12, 2026',
        'category' => 'PHP',
        'thumb_label' => 'php',
    ],
    [
        'title'    => 'Building RESTful APIs: A Practical Guide',
        'excerpt'  => 'Learn the fundamentals of designing and implementing RESTful APIs with proper authentication, versioning, and error handling strategies.',
        'author'   => 'James Rivera',
        'initials' => 'JR',
        'date'     => 'August 10, 2026',
        'category' => 'Backend',
        'thumb_label' => 'API',
    ],
    [
        'title'    => 'CSS Grid vs Flexbox: When to Use Which',
        'excerpt'  => 'A deep dive into the differences between CSS Grid and Flexbox, with practical examples to help you choose the right layout tool for every situation.',
        'author'   => 'Anika Patel',
        'initials' => 'AP',
        'date'     => 'August 8, 2026',
        'category' => 'CSS',
        'thumb_label' => 'CSS',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
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
                    <circle cx="18" cy="10" r="2.2" fill="#3b82f6"/>
                    <circle cx="11" cy="15" r="2" fill="#60a5fa"/>
                    <circle cx="25" cy="15" r="2" fill="#8b5cf6"/>
                    <circle cx="9" cy="22" r="1.8" fill="#06b6d4"/>
                    <circle cx="18" cy="19" r="2.2" fill="#3b82f6"/>
                    <circle cx="27" cy="22" r="1.8" fill="#06b6d4"/>
                    <circle cx="13" cy="27" r="1.6" fill="#8b5cf6"/>
                    <circle cx="23" cy="27" r="1.6" fill="#60a5fa"/>
                    <line x1="18" y1="10" x2="11" y2="15" stroke="#3b82f6" stroke-width="0.8" opacity="0.5"/>
                    <line x1="18" y1="10" x2="25" y2="15" stroke="#8b5cf6" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="18" y2="19" stroke="#60a5fa" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="18" y2="19" stroke="#8b5cf6" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="9" y2="22" stroke="#06b6d4" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="27" y2="22" stroke="#06b6d4" stroke-width="0.8" opacity="0.5"/>
                    <line x1="9" y1="22" x2="13" y2="27" stroke="#8b5cf6" stroke-width="0.8" opacity="0.4"/>
                    <line x1="18" y1="19" x2="13" y2="27" stroke="#3b82f6" stroke-width="0.8" opacity="0.4"/>
                    <line x1="18" y1="19" x2="23" y2="27" stroke="#60a5fa" stroke-width="0.8" opacity="0.4"/>
                    <line x1="27" y1="22" x2="23" y2="27" stroke="#06b6d4" stroke-width="0.8" opacity="0.4"/>
                    <line x1="9" y1="22" x2="18" y2="19" stroke="#06b6d4" stroke-width="0.8" opacity="0.3"/>
                    <line x1="27" y1="22" x2="18" y2="19" stroke="#06b6d4" stroke-width="0.8" opacity="0.3"/>
                </svg>
                <span class="logo-text"><span class="logo-brain">Brain</span><span class="logo-overflow">Overflow</span></span>
            </a>

            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="#">Explore</a></li>
                    <li><a href="#">Write</a></li>
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

    <!-- ===== Hero Section ===== -->
    <section class="hero">
        <div class="container hero-inner">
            <div class="hero-content">
                <h1>Share Ideas.<br><span class="text-blue">Inspire</span> Minds.</h1>
                <p>BrainOverflow is a space for curious minds to learn, share knowledge, and grow together.</p>
                <div class="hero-buttons">
                    <a href="<?php echo $isLoggedIn ? '#' : 'login.php'; ?>" class="btn btn-hero">
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
                            <span class="dot dot-yellow"></span>
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
                <?php foreach ($blogPosts as $post): ?>
                <article class="blog-card">
                    <div class="card-thumb thumb-<?php echo strtolower($post['category']); ?>"></div>
                    <div class="card-body">
                        <span class="card-category cat-<?php echo strtolower($post['category']); ?>"><?php echo htmlspecialchars($post['category']); ?></span>
                        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="card-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                        <div class="card-meta">
                            <div class="meta-author">
                                <div class="author-avatar av-<?php echo strtolower($post['category']); ?>"><?php echo htmlspecialchars($post['initials']); ?></div>
                                <span class="author-name"><?php echo htmlspecialchars($post['author']); ?></span>
                            </div>
                            <span class="post-date"><?php echo htmlspecialchars($post['date']); ?></span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
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
                <?php foreach ($blogPosts as $post): ?>
                <article class="blog-row">
                    <div class="row-thumb thumb-<?php echo strtolower($post['category']); ?>">
                        <span><?php echo htmlspecialchars($post['thumb_label']); ?></span>
                    </div>
                    <div class="row-content">
                        <span class="card-category cat-<?php echo strtolower($post['category']); ?>"><?php echo htmlspecialchars($post['category']); ?></span>
                        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="row-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                    </div>
                    <div class="row-meta">
                        <div class="meta-author">
                            <div class="author-avatar av-<?php echo strtolower($post['category']); ?>"><?php echo htmlspecialchars($post['initials']); ?></div>
                            <span class="author-name"><?php echo htmlspecialchars($post['author']); ?></span>
                        </div>
                        <span class="post-date"><?php echo htmlspecialchars($post['date']); ?></span>
                    </div>
                    <a href="#" class="row-arrow" aria-label="Read more">&rarr;</a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== Footer ===== -->
    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <svg class="footer-logo-svg" width="22" height="22" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="10" r="2.2" fill="#3b82f6"/>
                    <circle cx="11" cy="15" r="2" fill="#60a5fa"/>
                    <circle cx="25" cy="15" r="2" fill="#8b5cf6"/>
                    <circle cx="18" cy="19" r="2.2" fill="#3b82f6"/>
                    <circle cx="13" cy="27" r="1.6" fill="#8b5cf6"/>
                    <circle cx="23" cy="27" r="1.6" fill="#60a5fa"/>
                    <line x1="18" y1="10" x2="11" y2="15" stroke="#3b82f6" stroke-width="0.8" opacity="0.5"/>
                    <line x1="18" y1="10" x2="25" y2="15" stroke="#8b5cf6" stroke-width="0.8" opacity="0.5"/>
                    <line x1="11" y1="15" x2="18" y2="19" stroke="#60a5fa" stroke-width="0.8" opacity="0.5"/>
                    <line x1="25" y1="15" x2="18" y2="19" stroke="#8b5cf6" stroke-width="0.8" opacity="0.5"/>
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