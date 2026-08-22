<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (!brainoverflow_is_logged_in()) {
    header('Location: login.php');
    exit;
}

const BRAINOVERFLOW_POST_TITLE_MAX_LENGTH = 255;
const BRAINOVERFLOW_POST_CONTENT_MAX_LENGTH = 50000;
const BRAINOVERFLOW_POST_CONTENT_MAX_BYTES = 65535;
const BRAINOVERFLOW_POST_CATEGORIES = [
    'Programming',
    'Web Development',
    'AI',
    'Database',
    'Technology',
    'Other',
];

$rawPostId = $_GET['id'] ?? null;
$postId = is_string($rawPostId) && preg_match('/\A[1-9][0-9]*\z/', $rawPostId) === 1
    ? filter_var($rawPostId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$post = null;
$errors = [];
$pageState = null;

if ($postId === false) {
    http_response_code(404);
    $pageState = 'not-found';
} else {
    try {
        require __DIR__ . '/config/database.php';

        $postQuery = $pdo->prepare(
            'SELECT id, user_id, title, content, category
             FROM blogpost
             WHERE id = :id
             LIMIT 1'
        );
        $postQuery->execute(['id' => $postId]);
        $post = $postQuery->fetch() ?: null;

        if ($post === null) {
            http_response_code(404);
            $pageState = 'not-found';
        } elseif ((int) $post['user_id'] !== $_SESSION['user_id']) {
            http_response_code(403);
            $pageState = 'forbidden';
        }
    } catch (Throwable $error) {
        error_log('BrainOverflow edit post load error: ' . $error->getMessage());
        http_response_code(500);
        $pageState = 'unavailable';
    }
}

$title = $post !== null ? (string) $post['title'] : '';
$content = $post !== null ? (string) $post['content'] : '';
$category = $post !== null && $post['category'] !== null ? (string) $post['category'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pageState === null) {
    $title = trim(is_string($_POST['title'] ?? null) ? $_POST['title'] : '');
    $content = trim(is_string($_POST['content'] ?? null) ? $_POST['content'] : '');
    $category = trim(is_string($_POST['category'] ?? null) ? $_POST['category'] : '');

    if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    } else {
        $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        $contentLength = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);

        if ($title === '') {
            $errors[] = 'Title is required.';
        } elseif ($titleLength > BRAINOVERFLOW_POST_TITLE_MAX_LENGTH) {
            $errors[] = 'Title must be 255 characters or fewer.';
        }

        if ($content === '') {
            $errors[] = 'Content is required.';
        } elseif ($contentLength > BRAINOVERFLOW_POST_CONTENT_MAX_LENGTH
            || strlen($content) > BRAINOVERFLOW_POST_CONTENT_MAX_BYTES) {
            $errors[] = 'Content must be 50,000 characters or fewer.';
        }

        if ($category !== '' && !in_array($category, BRAINOVERFLOW_POST_CATEGORIES, true)) {
            $errors[] = 'Please select a valid category.';
        }
    }

    if (empty($errors)) {
        try {
            $updatePost = $pdo->prepare(
                'UPDATE blogpost
                 SET title = :title, content = :content, category = :category
                 WHERE id = :id AND user_id = :user_id'
            );
            $updatePost->execute([
                'title' => $title,
                'content' => $content,
                'category' => $category !== '' ? $category : null,
                'id' => $postId,
                'user_id' => $_SESSION['user_id'],
            ]);

            header('Location: post.php?id=' . rawurlencode((string) $postId) . '&updated=1');
            exit;
        } catch (Throwable $error) {
            error_log('BrainOverflow edit post update error: ' . $error->getMessage());
            $errors[] = 'Your post could not be updated. Please try again later.';
        }
    }
}

$stateContent = [
    'not-found' => ['Post not found', 'The post may have been removed or the link may be incorrect.'],
    'forbidden' => ['Edit not allowed', 'You can only edit posts that you created.'],
    'unavailable' => ['Post temporarily unavailable', 'Please try again later.'],
];
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
    <meta name="description" content="Edit a BrainOverflow blog post.">
    <title>Edit Post - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { min-height: 100vh; background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg); }
        .edit-shell { min-height: 100vh; padding: 34px 0; }
        .edit-topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 30px; }
        .edit-back { color: var(--color-text); font-weight: 600; }
        .edit-back:hover { color: var(--color-primary-dark); }
        .edit-card, .edit-state {
            max-width: 860px;
            margin: 0 auto;
            padding: clamp(24px, 5vw, 48px);
            background: var(--color-surface);
            border: 1px solid rgba(249, 115, 22, 0.16);
            border-radius: 18px;
            box-shadow: var(--shadow-card);
        }
        .edit-heading { margin-bottom: 28px; }
        .edit-heading h1 { margin-bottom: 8px; font-size: clamp(1.8rem, 4vw, 2.5rem); }
        .edit-heading p, .field-hint, .edit-state p { color: var(--color-text-light); }
        .form-field { margin-bottom: 22px; }
        .form-field label { display: block; margin-bottom: 8px; font-weight: 650; }
        .form-field input, .form-field textarea, .form-field select {
            width: 100%; padding: 13px 15px; color: var(--color-text); background: var(--input);
            border: 1px solid var(--border); border-radius: var(--radius-md); font: inherit;
        }
        .form-field textarea { min-height: 330px; resize: vertical; }
        .form-field input:focus, .form-field textarea:focus, .form-field select:focus { border-color: var(--accent); outline: 3px solid var(--accent-light); }
        .field-hint { display: block; margin-top: 6px; font-size: 0.84rem; }
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; }
        .notice-error { margin-bottom: 22px; padding: 13px 16px; color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; border-radius: var(--radius-md); }
        :root[data-theme="dark"] .notice-error { color: #fecaca; background: #7f1d1d; border-color: #991b1b; }
        .edit-state { text-align: center; }
        .edit-state h1 { margin-bottom: 10px; }
        .edit-state p { margin-bottom: 24px; }
    </style>
</head>
<body>
    <div class="edit-shell">
        <div class="container">
            <div class="edit-topbar">
                <a class="edit-back" href="<?php echo $postId !== false ? 'post.php?id=' . rawurlencode((string) $postId) : 'index.php'; ?>">&larr; Back</a>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">&#9790;</button>
            </div>

            <?php if ($pageState !== null): ?>
                <main class="edit-state">
                    <h1><?php echo htmlspecialchars($stateContent[$pageState][0], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p><?php echo htmlspecialchars($stateContent[$pageState][1], ENT_QUOTES, 'UTF-8'); ?></p>
                    <a class="btn btn-hero" href="index.php">Return home</a>
                </main>
            <?php else: ?>
                <main class="edit-card">
                    <div class="edit-heading">
                        <h1>Edit blog post</h1>
                        <p>Update your post and save the changes when you are ready.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="notice-error" role="alert">
                            <ul>
                                <?php foreach ($errors as $errorMessage): ?>
                                    <li><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit-post.php?id=<?php echo rawurlencode((string) $postId); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-field">
                            <label for="title">Title</label>
                            <input id="title" name="title" type="text" maxlength="255" required value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="field-hint">Up to 255 characters.</span>
                        </div>

                        <div class="form-field">
                            <label for="content">Content</label>
                            <textarea id="content" name="content" maxlength="50000" required><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <span class="field-hint">Up to 50,000 characters.</span>
                        </div>

                        <div class="form-field">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">No category</option>
                                <?php foreach (BRAINOVERFLOW_POST_CATEGORIES as $categoryOption): ?>
                                    <option value="<?php echo htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $category === $categoryOption ? ' selected' : ''; ?>><?php echo htmlspecialchars($categoryOption, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint">Optional.</span>
                        </div>

                        <div class="form-actions">
                            <a class="btn btn-outline" href="post.php?id=<?php echo rawurlencode((string) $postId); ?>">Cancel</a>
                            <button class="btn btn-hero" type="submit">Save changes</button>
                        </div>
                    </form>
                </main>
            <?php endif; ?>
        </div>
    </div>

    <div class="cursor-glow" id="cursorGlow"></div>
    <script src="js/main.js"></script>
</body>
</html>
