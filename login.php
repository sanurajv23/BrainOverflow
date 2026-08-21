<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

if (brainoverflow_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$loginIdentifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginIdentifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your login session expired. Please try again.';
    } elseif ($loginIdentifier === '' || $password === '') {
        $errors[] = 'Please enter your username or email and password.';
    }

    if (empty($errors)) {
        try {
            require __DIR__ . '/config/database.php';

            $findUser = $pdo->prepare(
                'SELECT id, username, email, password, role
                 FROM users
                 WHERE username = :username_identifier OR email = :email_identifier
                 LIMIT 1'
            );
            $findUser->execute([
                'username_identifier' => $loginIdentifier,
                'email_identifier' => $loginIdentifier,
            ]);
            $user = $findUser->fetch();

            if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid username/email or password.';
            } else {
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $updatePassword = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $updatePassword->execute([
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $user['id'],
                    ]);
                }

                brainoverflow_login_user($user);
                header('Location: index.php');
                exit;
            }
        } catch (Throwable $error) {
            error_log('BrainOverflow login error: ' . $error->getMessage());
            $errors[] = 'Login could not be completed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Log in to BrainOverflow.">
    <title>Login - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            min-height: 100vh;
            overflow-x: hidden;
            background:
                radial-gradient(ellipse at 18% 6%, rgba(59, 130, 246, 0.16) 0%, transparent 34%),
                radial-gradient(ellipse at 82% 18%, rgba(6, 182, 212, 0.08) 0%, transparent 32%),
                radial-gradient(ellipse at 50% 96%, rgba(96, 165, 250, 0.26) 0%, transparent 18%),
                var(--color-bg);
        }

        .login-shell {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 34px 0 28px;
        }

        .auth-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 26px;
        }

        .auth-back {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--color-text);
            font-size: 0.98rem;
            font-weight: 600;
            transition: color var(--transition-fast);
        }

        .auth-back svg {
            color: var(--color-primary-light);
        }

        .auth-back:hover {
            color: #fff;
        }

        .auth-page {
            flex: 1;
            display: flex;
            align-items: center;
        }

        .auth-wrap {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
        }

        .auth-panel {
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            min-height: 320px;
            overflow: hidden;
            background: rgba(7, 11, 20, 0.72);
            border: 1px solid rgba(96, 165, 250, 0.36);
            border-radius: 18px;
            box-shadow: 0 28px 90px rgba(0, 0, 0, 0.48), 0 0 44px rgba(59, 130, 246, 0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .auth-welcome,
        .auth-form-panel {
            position: relative;
            padding: 28px;
        }

        .auth-welcome {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background:
                radial-gradient(ellipse at 50% 28%, rgba(37, 99, 235, 0.32) 0%, transparent 42%),
                linear-gradient(135deg, rgba(8, 27, 69, 0.94), rgba(4, 12, 28, 0.96));
            border-right: 1px solid rgba(96, 165, 250, 0.28);
        }

        .auth-welcome::after {
            content: '';
            position: absolute;
            right: -18%;
            bottom: -18%;
            width: 56%;
            aspect-ratio: 1;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.08);
            pointer-events: none;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 390px;
        }

        .welcome-mark {
            width: 60px;
            height: 60px;
            margin: 0 auto 14px;
            color: var(--color-primary-light);
            filter: drop-shadow(0 0 22px rgba(59, 130, 246, 0.45));
        }

        .welcome-content h1 {
            color: #fff;
            font-size: 1.55rem;
            line-height: 1.08;
            margin-bottom: 8px;
            text-shadow: 0 0 22px rgba(255, 255, 255, 0.08);
        }

        .welcome-content p {
            color: #c7d2fe;
            font-size: 0.9rem;
            line-height: 1.65;
        }

        .code-divider {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 14px;
            margin: 14px 0;
            color: var(--color-primary);
            font-weight: 800;
        }

        .code-divider::before,
        .code-divider::after {
            content: '';
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.55));
        }

        .code-divider::after {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.55), transparent);
        }

        .welcome-login {
            margin-top: 6px;
        }

        .welcome-login p {
            margin-bottom: 14px;
            font-size: 1rem;
        }

        .auth-side-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 190px;
            padding: 13px 20px;
            color: #eaf6ff;
            background: rgba(14, 165, 233, 0.08);
            border: 1px solid rgba(14, 165, 233, 0.72);
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 800;
            box-shadow:
                0 0 18px rgba(14, 165, 233, 0.18),
                0 0 34px rgba(59, 130, 246, 0.10);
            overflow: hidden;
            transition: background var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast), color var(--transition-fast);
        }

        .auth-side-btn::before {
            content: none;
        }

        .auth-side-btn span {
            position: relative;
            z-index: 1;
        }

        .auth-side-btn:hover {
            color: #fff;
            background: rgba(14, 165, 233, 0.13);
            border-color: rgba(56, 189, 248, 1);
            box-shadow:
                0 0 22px rgba(14, 165, 233, 0.28),
                0 0 42px rgba(59, 130, 246, 0.14);
        }

        .auth-side-btn:active {
            background: rgba(14, 165, 233, 0.10);
            box-shadow:
                0 0 14px rgba(14, 165, 233, 0.20),
                0 0 28px rgba(59, 130, 246, 0.10);
        }

        .auth-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(5, 12, 26, 0.78);
        }

        .auth-form-inner {
            width: 100%;
            max-width: 500px;
        }

        .auth-header {
            margin-bottom: 18px;
            text-align: center;
        }

        .auth-header h1 {
            color: #fff;
            font-size: 1.5rem;
            line-height: 1.16;
            margin-bottom: 8px;
        }

        .auth-header p {
            color: #aebbd2;
            font-size: 1rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 10px;
        }

        .form-group label {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 22px;
            width: 22px;
            height: 22px;
            color: #82a0cb;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            min-height: 40px;
            padding: 10px 14px 10px 48px;
            color: #fff;
            background: rgba(8, 18, 36, 0.72);
            border: 1px solid rgba(96, 165, 250, 0.25);
            border-radius: 9px;
            font: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
        }

        .form-control::placeholder {
            color: #8ea1bd;
        }

        .form-control:focus {
            border-color: var(--color-primary);
            background: rgba(9, 22, 45, 0.9);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14), 0 0 24px rgba(59, 130, 246, 0.12);
        }

        .forgot-link {
            display: flex;
            justify-content: flex-end;
            margin: -2px 0 12px;
        }

        .forgot-link a {
            color: var(--color-primary-light);
            font-size: 0.88rem;
            font-weight: 700;
        }

        .forgot-link a:hover {
            color: #fff;
        }

        button.auth-submit {
            position: relative;
            width: 100%;
            justify-content: center;
            min-height: 40px;
            margin-top: 0;
            border-radius: 9px;
            border: 1px solid rgba(14, 165, 233, 0.72);
            color: #eaf6ff;
            background: rgba(14, 165, 233, 0.08);
            font-size: 1rem;
            font-weight: 800;
            text-shadow: none;
            box-shadow:
                0 0 18px rgba(14, 165, 233, 0.18),
                0 0 34px rgba(59, 130, 246, 0.10);
            transform: translateY(0);
            transition: background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        button.auth-submit::before {
            content: none;
        }

        button.auth-submit:hover {
            color: #fff;
            background: rgba(14, 165, 233, 0.13);
            border-color: rgba(56, 189, 248, 1);
            transform: translateY(0);
            box-shadow:
                0 0 22px rgba(14, 165, 233, 0.28),
                0 0 42px rgba(59, 130, 246, 0.14);
        }

        button.auth-submit:active {
            transform: translateY(0);
            background: rgba(14, 165, 233, 0.10);
            box-shadow:
                0 0 14px rgba(14, 165, 233, 0.20),
                0 0 28px rgba(59, 130, 246, 0.10);
        }

        .auth-divider {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 18px;
            margin: 14px 0 12px;
            color: #aebbd2;
            font-size: 0.95rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            height: 1px;
            background: rgba(148, 163, 184, 0.24);
        }

        .google-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: 100%;
            min-height: 36px;
            color: var(--color-text);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 9px;
            font: inherit;
            font-weight: 700;
            cursor: default;
        }

        .google-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            color: #fff;
            background: conic-gradient(from -45deg, #4285f4 0 25%, #34a853 0 50%, #fbbc05 0 75%, #ea4335 0 100%);
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1;
        }

        .auth-message {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .auth-message.error {
            color: #dc2626;
            background: rgba(220, 38, 38, 0.08);
            border: 1px solid rgba(220, 38, 38, 0.18);
        }

        .auth-message ul {
            display: grid;
            gap: 4px;
            padding-left: 18px;
            list-style: disc;
        }

        .auth-bottom {
            margin-top: 10px;
            text-align: center;
            color: var(--color-text-light);
            font-size: 0.9rem;
        }

        .auth-bottom a {
            color: var(--color-primary-light);
            font-weight: 600;
        }

        .auth-bottom a:hover {
            color: #fff;
        }

        .auth-footer {
            margin-top: 20px;
            text-align: center;
            color: #9fb0cc;
        }

        .auth-footer a {
            color: var(--color-primary);
        }

        @media (max-width: 900px) {
            .auth-topbar {
                margin-bottom: 34px;
            }

            .auth-panel {
                grid-template-columns: 1fr;
                max-width: 620px;
                margin: 0 auto;
            }

            .auth-welcome {
                min-height: 360px;
                border-right: none;
                border-bottom: 1px solid rgba(96, 165, 250, 0.28);
            }

            .auth-welcome,
            .auth-form-panel {
                padding: 42px;
            }
        }

        @media (max-width: 560px) {
            .login-shell {
                padding: 22px 0;
            }

            .auth-topbar {
                align-items: flex-start;
                gap: 14px;
                margin-bottom: 26px;
            }

            .auth-back {
                font-size: 0.9rem;
            }

            .logo {
                font-size: 1.15rem;
            }

            .logo-svg {
                width: 30px;
                height: 30px;
            }

            .auth-panel {
                border-radius: 14px;
            }

            .auth-welcome,
            .auth-form-panel {
                padding: 30px 22px;
            }

            .auth-welcome {
                min-height: 320px;
            }

            .welcome-mark {
                width: 86px;
                height: 86px;
                margin-bottom: 22px;
            }

            .welcome-content h1 {
                font-size: 2rem;
            }

            .auth-header h1 {
                font-size: 1.65rem;
            }

            .form-control {
                min-height: 56px;
                padding-left: 58px;
            }

            .input-icon {
                left: 19px;
            }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <header class="container auth-topbar">
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

            <a href="index.php" class="auth-back">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Back to Home
            </a>
        </header>

        <main class="auth-page">
            <div class="container">
                <div class="auth-wrap">
                    <section class="auth-panel">
                        <aside class="auth-welcome">
                            <div class="welcome-content">
                                <svg class="welcome-mark" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                                    <path d="M30 18C20 18 16 25 16 34V43C16 50 10 52 10 60C10 68 16 70 16 77V86C16 95 20 102 30 102" stroke="currentColor" stroke-width="10" stroke-linecap="round"/>
                                    <path d="M90 18C100 18 104 25 104 34V43C104 50 110 52 110 60C110 68 104 70 104 77V86C104 95 100 102 90 102" stroke="currentColor" stroke-width="10" stroke-linecap="round"/>
                                    <path d="M60 34V86" stroke="currentColor" stroke-width="3" stroke-linecap="round" opacity="0.75"/>
                                    <path d="M50 38C40 38 35 44 35 53C28 55 26 64 32 70C31 78 38 84 47 82C51 87 58 85 60 78" stroke="#93c5fd" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M70 38C80 38 85 44 85 53C92 55 94 64 88 70C89 78 82 84 73 82C69 87 62 85 60 78" stroke="#93c5fd" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <h1>Hello, Welcome!</h1>
                                <p>Don't have an account?</p>
                                <div class="code-divider">&lt;/&gt;</div>
                                <div class="welcome-login">
                                    <p>Join the BrainOverflow developer community.</p>
                                    <a href="register.php" class="auth-side-btn"><span>Create Account</span> <span aria-hidden="true">&rarr;</span></a>
                                </div>
                            </div>
                        </aside>

                        <div class="auth-form-panel">
                            <div class="auth-form-inner">
                                <div class="auth-header">
                                    <h1>Welcome Back</h1>
                                    <p>Sign in to continue to BrainOverflow.</p>
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

                                <form method="POST" action="login.php" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token()); ?>">
                                    <div class="form-group">
                                        <label for="login_identifier">Username or Email</label>
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M20 21C20 17.7 16.4 15 12 15C7.6 15 4 17.7 4 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M12 12C14.2 12 16 10.2 16 8C16 5.8 14.2 4 12 4C9.8 4 8 5.8 8 8C8 10.2 9.8 12 12 12Z" stroke="currentColor" stroke-width="1.8"/>
                                        </svg>
                                        <input class="form-control" type="text" id="login_identifier" name="login_identifier" autocomplete="username" placeholder="Username or Email" value="<?php echo htmlspecialchars($loginIdentifier); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M7 10V8C7 5.2 9.2 3 12 3C14.8 3 17 5.2 17 8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M6 10H18V20H6V10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M12 14V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" placeholder="Password">
                                    </div>

                                    <div class="forgot-link">
                                        <a href="#">Forgot Password?</a>
                                    </div>

                                    <button type="submit" class="btn auth-submit">Login <span aria-hidden="true">&rarr;</span></button>
                                </form>

                                <div class="auth-divider"><span>or</span></div>
                                <a class="google-placeholder" href="google-auth.php"><span class="google-mark" aria-hidden="true">G</span> Continue with Google</a>

                                <p class="auth-bottom">
                                    Don't have an account? <a href="register.php">Create Account</a>
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <footer class="auth-footer">
            <div class="container">
                &copy; <?php echo date('Y'); ?> <a href="index.php">BrainOverflow</a>. All rights reserved.
            </div>
        </footer>
    </div>

    <div class="cursor-glow" id="cursorGlow"></div>
    <script src="js/main.js"></script>
</body>
</html>
