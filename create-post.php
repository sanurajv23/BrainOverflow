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

$errors = [];
$title = '';
$content = '';
$category = '';
$postCreated = isset($_GET['created']) && $_GET['created'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            require __DIR__ . '/config/database.php';

            $insertPost = $pdo->prepare(
                'INSERT INTO blogpost (user_id, title, content, category)
                 VALUES (:user_id, :title, :content, :category)'
            );
            $insertPost->execute([
                'user_id' => $_SESSION['user_id'],
                'title' => $title,
                'content' => $content,
                'category' => $category !== '' ? $category : null,
            ]);

            header('Location: create-post.php?created=1');
            exit;
        } catch (Throwable $error) {
            error_log('BrainOverflow create post error: ' . $error->getMessage());
            $errors[] = 'Your post could not be published. Please try again later.';
        }
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
    <meta name="description" content="Create a blog post on BrainOverflow.">
    <title>Create Post - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%), var(--bg);
        }

        .create-shell { min-height: 100vh; padding: 34px 0; }
        .create-topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 30px; }
        .create-back { color: var(--color-text); font-weight: 600; }
        .create-back:hover { color: var(--color-primary-dark); }
        .create-card {
            max-width: 860px;
            margin: 0 auto;
            padding: clamp(24px, 5vw, 48px);
            background: var(--surface);
            border: 1px solid rgba(249, 115, 22, 0.16);
            border-radius: 18px;
            box-shadow: var(--shadow-card);
        }
        .create-heading { margin-bottom: 28px; }
        .create-heading h1 { margin-bottom: 8px; font-size: clamp(1.8rem, 4vw, 2.5rem); }
        .create-heading p, .field-hint { color: var(--color-text-light); }
        .form-field { margin-bottom: 22px; }
        .form-field label { display: block; margin-bottom: 8px; font-weight: 650; }
        .form-field input, .form-field textarea, .form-field select {
            width: 100%;
            padding: 13px 15px;
            color: var(--color-text);
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font: inherit;
        }
        .form-field textarea { min-height: 330px; resize: vertical; }
        .form-field input:focus, .form-field textarea:focus, .form-field select:focus {
            border-color: var(--accent);
            outline: 3px solid var(--accent-light);
        }
        .field-hint { display: block; margin-top: 6px; font-size: 0.84rem; }
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; }
        .notice { margin-bottom: 22px; padding: 13px 16px; border-radius: var(--radius-md); }
        .notice-success { color: #166534; background: #dcfce7; border: 1px solid #86efac; }
        .notice-error { color: #991b1b; background: #fee2e2; border: 1px solid #fca5a5; }
        :root[data-theme="dark"] .notice-success { color: #bbf7d0; background: #14532d; border-color: #166534; }
        :root[data-theme="dark"] .notice-error { color: #fecaca; background: #7f1d1d; border-color: #991b1b; }
    </style>
</head>
<body>
    <div class="create-shell">
        <div class="container">
            <div class="create-topbar">
                <a class="create-back" href="index.php">&larr; Back to home</a>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">&#9790;</button>
            </div>

            <main class="create-card">
                <div class="create-heading">
                    <h1>Create a blog post</h1>
                    <p>Share an idea, lesson, or story with the BrainOverflow community.</p>
                </div>

                <?php if ($postCreated): ?>
                    <div class="notice notice-success" role="status">Your post was published successfully.</div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="notice notice-error" role="alert">
                        <ul>
                            <?php foreach ($errors as $errorMessage): ?>
                                <li><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="create-post.php">
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
                        <a class="btn btn-outline" href="index.php">Cancel</a>
                        <button class="btn btn-hero" type="submit">Publish post</button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <div class="cursor-glow" id="cursorGlow"></div>
    <script src="js/main.js"></script>
</body>
</html>
