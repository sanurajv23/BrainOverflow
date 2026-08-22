<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

const BRAINOVERFLOW_POST_CATEGORIES = [
    'Programming',
    'Web Development',
    'AI',
    'Database',
    'Technology',
    'Other',
];
const BRAINOVERFLOW_POSTS_PER_PAGE = 10;
const BRAINOVERFLOW_SEARCH_MAX_LENGTH = 100;

$isLoggedIn = brainoverflow_is_logged_in();
$currentUsername = brainoverflow_current_username();
$blogPosts = [];
$postsLoadFailed = false;
$searchTerm = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$searchTermLength = function_exists('mb_strlen') ? mb_strlen($searchTerm, 'UTF-8') : strlen($searchTerm);

if ($searchTermLength > BRAINOVERFLOW_SEARCH_MAX_LENGTH) {
    $searchTerm = function_exists('mb_substr')
        ? mb_substr($searchTerm, 0, BRAINOVERFLOW_SEARCH_MAX_LENGTH, 'UTF-8')
        : substr($searchTerm, 0, BRAINOVERFLOW_SEARCH_MAX_LENGTH);
}

$hasSearch = $searchTerm !== '';
$requestedCategory = is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';
$selectedCategory = in_array($requestedCategory, BRAINOVERFLOW_POST_CATEGORIES, true) ? $requestedCategory : '';
$hasCategoryFilter = $selectedCategory !== '';
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

    $fromSql =
        ' FROM blogpost
          INNER JOIN users ON users.id = blogpost.user_id';

    $whereClauses = [];
    $queryParameters = [];

    if ($hasSearch) {
        $whereClauses[] =
            "(blogpost.title LIKE :title_term ESCAPE '!'
              OR blogpost.content LIKE :content_term ESCAPE '!')";
        $escapedSearchTerm = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $searchTerm);
        $likeSearchTerm = '%' . $escapedSearchTerm . '%';
        $queryParameters['title_term'] = $likeSearchTerm;
        $queryParameters['content_term'] = $likeSearchTerm;
    }

    if ($hasCategoryFilter) {
        $whereClauses[] = 'blogpost.category = :category';
        $queryParameters['category'] = $selectedCategory;
    }

    $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

    $countQuery = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
    $countQuery->execute($queryParameters);
    $totalPosts = (int) $countQuery->fetchColumn();
    $totalPages = max(1, (int) ceil($totalPosts / BRAINOVERFLOW_POSTS_PER_PAGE));
    $currentPage = min($requestedPage, $totalPages);
    $offset = ($currentPage - 1) * BRAINOVERFLOW_POSTS_PER_PAGE;

    $postsSql =
        'SELECT blogpost.id, blogpost.title, blogpost.content, blogpost.category, blogpost.created_at,
                users.username AS author_username'
        . $fromSql
        . $whereSql
        . ' ORDER BY blogpost.created_at DESC, blogpost.id DESC
            LIMIT :limit OFFSET :offset';
    $postsQuery = $pdo->prepare($postsSql);

    foreach ($queryParameters as $parameterName => $parameterValue) {
        $postsQuery->bindValue(':' . $parameterName, $parameterValue, PDO::PARAM_STR);
    }

    $postsQuery->bindValue(':limit', BRAINOVERFLOW_POSTS_PER_PAGE, PDO::PARAM_INT);
    $postsQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
    $postsQuery->execute();

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

function brainoverflow_explore_page_url(int $page, string $searchTerm, string $category): string
{
    $parameters = [];

    if ($searchTerm !== '') {
        $parameters['q'] = $searchTerm;
    }

    if ($category !== '') {
        $parameters['category'] = $category;
    }

    $parameters['page'] = $page;

    return 'explore.php?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
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
        .explore-search { display: flex; gap: 6px; width: min(820px, 100%); margin: 30px auto 0; padding: 7px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-full); box-shadow: var(--shadow-sm); }
        .explore-search:focus-within { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--accent-light); }
        .explore-search input { flex: 1; min-width: 0; padding: 10px 16px; color: var(--color-text); background: transparent; border: 0; outline: 0; font: inherit; }
        .explore-search select { min-width: 180px; padding: 10px 14px; color: var(--color-text); background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-full); outline: 0; font: inherit; }
        .explore-search .btn { flex: 0 0 auto; }
        .search-summary { color: var(--color-text-light); font-size: 0.95rem; }
        .pagination { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; margin-top: 34px; }
        .pagination-link { display: inline-flex; min-width: 40px; min-height: 40px; align-items: center; justify-content: center; padding: 8px 13px; color: var(--color-text); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-weight: 650; }
        .pagination-link:hover { color: var(--color-primary-dark); border-color: var(--color-border-hover); }
        .pagination-link.active { color: #fff; background: var(--color-primary); border-color: var(--color-primary); }
        .pagination-link.disabled { color: var(--color-text-muted); opacity: 0.65; cursor: not-allowed; }
        @media (max-width: 640px) { .explore-search { flex-direction: column; border-radius: var(--radius-md); } .explore-search select { width: 100%; border-radius: var(--radius-md); } .explore-search .btn { width: 100%; } }
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
                    <?php if ($isLoggedIn): ?>
                    <li><a href="my-posts.php">My Posts</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <?php endif; ?>
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
                    <select name="category" aria-label="Filter by category">
                        <option value="">All Categories</option>
                        <?php foreach (BRAINOVERFLOW_POST_CATEGORIES as $categoryOption): ?>
                            <option value="<?php echo htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedCategory === $categoryOption ? ' selected' : ''; ?>><?php echo htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-hero" type="submit">Apply</button>
                </form>
            </div>
        </section>

        <section class="featured-posts explore-posts">
            <div class="container">
                <div class="section-header">
                    <h2><span class="section-bar"></span><?php echo ($hasSearch || $hasCategoryFilter) ? 'Filtered Posts' : 'All Posts'; ?></h2>
                    <?php if (($hasSearch || $hasCategoryFilter) && !$postsLoadFailed): ?>
                    <span class="search-summary"><?php echo $totalPosts; ?> result<?php echo $totalPosts === 1 ? '' : 's'; ?></span>
                    <?php endif; ?>
                </div>

                <div class="blog-grid">
                    <?php if (empty($blogPosts)): ?>
                    <div class="posts-empty">
                        <h3><?php echo $postsLoadFailed ? 'Posts are temporarily unavailable' : (($hasSearch || $hasCategoryFilter) ? 'No results found' : 'No posts yet'); ?></h3>
                        <p>
                            <?php if ($postsLoadFailed): ?>
                                Please try again later.
                            <?php elseif ($hasSearch && $hasCategoryFilter): ?>
                                No posts matched your search in <?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>.
                            <?php elseif ($hasSearch): ?>
                                No posts matched “<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>”. Try a different search.
                            <?php elseif ($hasCategoryFilter): ?>
                                No posts were found in <?php echo htmlspecialchars($selectedCategory, ENT_QUOTES, 'UTF-8'); ?>.
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
                            <?php if ($post['category'] !== null && trim((string) $post['category']) !== ''): ?>
                                <span class="card-category cat-php"><?php echo htmlspecialchars($post['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
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

                <?php if (!$postsLoadFailed && $totalPages > 1): ?>
                <nav class="pagination" aria-label="Explore posts pages">
                    <?php if ($currentPage > 1): ?>
                        <a class="pagination-link" href="<?php echo htmlspecialchars(brainoverflow_explore_page_url($currentPage - 1, $searchTerm, $selectedCategory), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                    <?php else: ?>
                        <span class="pagination-link disabled" aria-disabled="true">Previous</span>
                    <?php endif; ?>

                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a class="pagination-link<?php echo $pageNumber === $currentPage ? ' active' : ''; ?>" href="<?php echo htmlspecialchars(brainoverflow_explore_page_url($pageNumber, $searchTerm, $selectedCategory), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $pageNumber === $currentPage ? ' aria-current="page"' : ''; ?>><?php echo $pageNumber; ?></a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a class="pagination-link" href="<?php echo htmlspecialchars(brainoverflow_explore_page_url($currentPage + 1, $searchTerm, $selectedCategory), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
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
