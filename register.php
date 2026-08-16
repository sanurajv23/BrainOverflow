<?php
$errors = [];
$successMessage = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $errors[] = 'Please fill in all fields.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (empty($errors)) {
        try {
            $submittedUsername = $username;
            require __DIR__ . '/config/database.php';
            $username = $submittedUsername;

            $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $checkEmail->execute(['email' => $email]);

            if ($checkEmail->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }

            error_log('BrainOverflow registration username POST value: ' . ($_POST['username'] ?? ''));

            $checkUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $checkUsername->execute(['username' => $username]);
            $existingUsername = $checkUsername->fetch();

            error_log('BrainOverflow username duplicate fetch returned row: ' . ($existingUsername ? 'true' : 'false'));

            if ($existingUsername) {
                $errors[] = 'This username is already taken.';
            }

            if (empty($errors)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertUser = $pdo->prepare(
                    'INSERT INTO users (username, email, password, role)
                     VALUES (:username, :email, :password, :role)'
                );

                $insertUser->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'user',
                ]);

                $successMessage = 'Registration successful. You can log in once the login feature is available.';
                $username = '';
                $email = '';
            }
        } catch (Throwable $error) {
            error_log('BrainOverflow registration error: ' . $error->getMessage());
            $errors[] = 'Registration could not be completed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create a BrainOverflow account.">
    <title>Register - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-page {
            min-height: calc(100vh - 64px);
            display: flex;
            align-items: center;
            padding: 56px 0;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(59, 130, 246, 0.08) 0%, transparent 45%),
                radial-gradient(ellipse at 80% 70%, rgba(6, 182, 212, 0.05) 0%, transparent 45%),
                var(--color-bg);
        }

        .auth-wrap {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }

        .auth-panel {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            padding: 32px;
        }

        .auth-header {
            margin-bottom: 24px;
        }

        .auth-header h1 {
            color: #fff;
            font-size: 1.8rem;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .auth-header p {
            color: var(--color-text-light);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            color: var(--color-text);
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            color: #fff;
            background: var(--color-bg-elevated);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: var(--radius-sm);
            font: inherit;
            outline: none;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
        }

        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
        }

        .auth-submit {
            width: 100%;
            justify-content: center;
            margin-top: 8px;
        }

        .auth-message {
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            margin-bottom: 18px;
        }

        .auth-message.error {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.22);
        }

        .auth-message.success {
            color: #bbf7d0;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.22);
        }

        .auth-message ul {
            list-style: disc;
            padding-left: 18px;
        }

        .auth-footer {
            margin-top: 20px;
            text-align: center;
            color: var(--color-text-light);
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: var(--color-primary-light);
            font-weight: 600;
        }

        .auth-footer a:hover {
            color: #fff;
        }

        @media (max-width: 480px) {
            .auth-page {
                padding: 32px 0;
            }

            .auth-panel {
                padding: 24px;
            }

            .auth-header h1 {
                font-size: 1.55rem;
            }
        }
    </style>
</head>
<body>
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
                </svg>
                <span class="logo-text"><span class="logo-brain">Brain</span><span class="logo-overflow">Overflow</span></span>
            </a>

            <nav class="main-nav" id="main-nav">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#">Explore</a></li>
                    <li><a href="#">Write</a></li>
                    <li><a href="#">About</a></li>
                    <li class="nav-auth-mobile"><a href="login.php" class="btn btn-login">Login</a></li>
                    <li class="nav-auth-mobile"><a href="register.php" class="btn btn-register">Register</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <a href="login.php" class="btn btn-login">Login</a>
                <a href="register.php" class="btn btn-register">Register</a>
            </div>

            <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" onclick="document.getElementById('main-nav').classList.toggle('open')">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>

    <main class="auth-page">
        <div class="container">
            <div class="auth-wrap">
                <section class="auth-panel">
                    <div class="auth-header">
                        <h1>Create your account</h1>
                        <p>Join BrainOverflow and get ready to share your ideas.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="auth-message error">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage !== ''): ?>
                        <div class="auth-message success">
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register.php" novalidate>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input class="form-control" type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input class="form-control" type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-hero auth-submit">Register</button>
                    </form>

                    <p class="auth-footer">
                        Already have an account? <a href="login.php">Login</a>
                    </p>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
