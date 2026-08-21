<?php
require_once __DIR__ . '/includes/auth.php';
brainoverflow_start_session();

$errors = [];
$successMessage = '';
$username = '';
$email = '';
$oauthError = $_SESSION['oauth_error'] ?? '';
unset($_SESSION['oauth_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!brainoverflow_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your registration session expired. Please try again.';
    } elseif ($username === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $errors[] = 'Please fill in all fields.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($username !== '') {
        $errors = array_merge($errors, brainoverflow_validate_username($username));
    }

    if ($password !== '') {
        $errors = array_merge($errors, brainoverflow_validate_password($password));
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (empty($errors)) {
        try {
            require __DIR__ . '/config/database.php';

            $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $checkEmail->execute(['email' => $email]);

            if ($checkEmail->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }

            $checkUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $checkUsername->execute(['username' => $username]);
            $existingUsername = $checkUsername->fetch();

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

                $successMessage = 'Registration successful. You can now log in.';
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
    <meta name="description" content="Create a BrainOverflow account.">
    <title>Register - BrainOverflow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            min-height: 100vh;
            overflow-x: hidden;
            background:
                radial-gradient(ellipse at 18% 6%, var(--accent-light) 0%, transparent 34%),
                radial-gradient(ellipse at 82% 18%, rgba(251, 146, 60, 0.05) 0%, transparent 32%),
                radial-gradient(ellipse at 50% 96%, rgba(253, 186, 116, 0.06) 0%, transparent 18%),
                var(--bg);
        }

        .register-shell {
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
            color: var(--color-primary-dark);
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
            background: var(--surface);
            border: 1px solid rgba(249, 115, 22, 0.16);
            border-radius: 18px;
            box-shadow:
                0 0 12px rgba(249, 115, 22, 0.08),
                0 4px 20px var(--shadow);
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
                radial-gradient(ellipse at 50% 28%, rgba(249, 115, 22, 0.10) 0%, transparent 44%),
                linear-gradient(135deg, var(--accent-light), var(--surface));
            border-right: 1px solid var(--border);
        }

        .auth-welcome::after {
            content: '';
            position: absolute;
            right: -18%;
            bottom: -18%;
            width: 56%;
            aspect-ratio: 1;
            border-radius: 50%;
            background: rgba(249, 115, 22, 0.06);
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
            color: var(--color-primary);
            filter: drop-shadow(0 6px 14px rgba(249, 115, 22, 0.10));
        }

        .welcome-content h1 {
            color: var(--text);
            font-size: 1.55rem;
            line-height: 1.16;
            font-weight: 650;
            margin-bottom: 8px;
            text-shadow: none;
        }

        .welcome-content p {
            color: var(--text-secondary);
            font-size: 0.86rem;
            font-weight: 400;
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
            background: linear-gradient(90deg, transparent, rgba(249, 115, 22, 0.28));
        }

        .code-divider::after {
            background: linear-gradient(90deg, rgba(249, 115, 22, 0.28), transparent);
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
            color: #ffffff;
            background: var(--color-primary);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 800;
            box-shadow:
                0 4px 12px rgba(249, 115, 22, 0.18);
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
            color: #ffffff;
            background: var(--color-primary-dark);
            border-color: var(--color-primary-dark);
            box-shadow:
                0 4px 12px rgba(249, 115, 22, 0.18);
        }

        .auth-side-btn:active {
            background: var(--color-primary-dark);
            box-shadow:
                0 4px 12px rgba(249, 115, 22, 0.18);
        }

        .auth-form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--surface);
        }

        .auth-form-inner {
            width: 100%;
            max-width: 500px;
        }

        .auth-header {
            margin-bottom: 14px;
            text-align: center;
        }

        .auth-header h1 {
            color: var(--text);
            font-size: 1.28rem;
            line-height: 1.16;
            font-weight: 650;
            margin-bottom: 8px;
        }

        .auth-header p {
            color: var(--text-secondary);
            font-size: 1rem;
            font-weight: 400;
        }

        .form-group {
            position: relative;
            margin-bottom: 9px;
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
            color: var(--color-primary);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            min-height: 35px;
            padding: 7px 14px 7px 48px;
            color: var(--color-text);
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: 11px;
            font: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color var(--transition-fast), box-shadow var(--transition-fast), background var(--transition-fast);
        }

        .form-control::placeholder {
            color: var(--muted);
        }

        .form-control:focus {
            border-color: var(--accent);
            background: var(--input);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.10);
        }

        .password-strength {
            max-height: 0;
            margin: 0;
            opacity: 0;
            overflow: hidden;
            transition: max-height 0.2s ease, margin 0.2s ease, opacity 0.2s ease;
        }

        .password-strength.is-visible {
            max-height: 150px;
            margin: 7px 0 8px;
            opacity: 1;
        }

        .strength-summary {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--color-text-light);
            font-size: 13px;
            line-height: 1.4;
        }

        .strength-title,
        .strength-label {
            font-size: 13px;
            font-weight: 700;
        }

        .strength-meter {
            flex: 1;
            height: 5px;
            overflow: hidden;
            border-radius: 999px;
            background: var(--border);
        }

        .strength-meter-fill {
            display: block;
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: #dc2626;
            transition: width 0.2s ease, background 0.2s ease;
        }

        .strength-requirements {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            column-gap: 14px;
            row-gap: 3px;
            margin: 6px 0 0;
            padding: 0;
            color: var(--muted);
            font-size: 11.5px;
            line-height: 1.35;
            list-style: none;
        }

        .strength-requirements li::before {
            content: 'x';
            display: inline-block;
            width: 14px;
            color: #dc2626;
            font-weight: 700;
        }

        .strength-requirements li.is-met {
            color: var(--color-text);
        }

        .strength-requirements li.is-met::before {
            content: '\2713';
            color: var(--accent);
        }

        button.auth-submit {
            position: relative;
            width: 100%;
            justify-content: center;
            min-height: 40px;
            margin-top: 10px;
            border-radius: 8px;
            border: none;
            color: #ffffff;
            background: var(--color-primary);
            font-size: 1rem;
            font-weight: 800;
            text-shadow: none;
            box-shadow:
                0 4px 12px rgba(249, 115, 22, 0.18);
            transform: translateY(0);
            transition: background 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }

        button.auth-submit::before {
            content: none;
        }

        button.auth-submit:hover {
            color: #ffffff;
            background: var(--color-primary-dark);
            border-color: var(--color-primary-dark);
            transform: translateY(0);
            box-shadow:
                0 4px 12px rgba(249, 115, 22, 0.18);
        }

        button.auth-submit:active {
            transform: translateY(0);
            background: var(--color-primary-dark);
            box-shadow:
                0 4px 12px rgba(249, 115, 22, 0.18);
        }

        .auth-message {
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            margin-bottom: 18px;
        }

        .auth-message.error {
            color: #dc2626;
            background: rgba(220, 38, 38, 0.08);
            border: 1px solid rgba(220, 38, 38, 0.18);
        }

        .auth-message.success {
            color: #ea580c;
            background: rgba(249, 115, 22, 0.08);
            border: 1px solid rgba(249, 115, 22, 0.18);
        }

        .auth-message ul {
            list-style: disc;
            padding-left: 18px;
        }

        .auth-divider {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 18px;
            margin: 12px 0 10px;
            color: var(--color-text-light);
            font-size: 0.95rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            height: 1px;
            background: var(--border);
        }

        .google-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            width: 100%;
            min-height: 36px;
            color: var(--color-text);
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: 8px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .google-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            color: #ffffff;
            background: var(--accent);
            font-size: 0.82rem;
            font-weight: 800;
            line-height: 1;
        }

        .auth-bottom {
            margin-top: 8px;
            text-align: center;
            color: var(--color-text-light);
            font-size: 0.9rem;
        }

        .auth-bottom a {
            color: var(--color-primary-light);
            font-weight: 600;
        }

        .auth-bottom a:hover {
            color: var(--color-primary-dark);
        }

        .auth-footer {
            margin-top: 20px;
            text-align: center;
            color: var(--color-text-muted);
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
                border-bottom: 1px solid var(--border);
            }

            .auth-welcome,
            .auth-form-panel {
                padding: 42px;
            }
        }

        @media (max-width: 560px) {
            .register-shell {
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
                font-size: 1.85rem;
            }

            .auth-header h1 {
                font-size: 1.5rem;
            }

            .form-control {
                min-height: 42px;
                padding: 9px 14px 9px 58px;
            }

            .input-icon {
                left: 19px;
            }

            .strength-requirements {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-shell">
        <header class="container auth-topbar">
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
                </svg>
                <span class="logo-text"><span class="logo-brain">Brain</span><span class="logo-overflow">Overflow</span></span>
            </a>

            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark theme" title="Switch to dark theme">☾</button>

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
                                    <path d="M50 38C40 38 35 44 35 53C28 55 26 64 32 70C31 78 38 84 47 82C51 87 58 85 60 78" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" opacity="0.58"/>
                                    <path d="M70 38C80 38 85 44 85 53C92 55 94 64 88 70C89 78 82 84 73 82C69 87 62 85 60 78" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" opacity="0.58"/>
                                </svg>
                                <h1>Hello, Welcome!</h1>
                                <p>Join BrainOverflow and become a part of a developer community.</p>
                                <div class="code-divider">&lt;/&gt;</div>
                                <div class="welcome-login">
                                    <p>Already have an account?</p>
                                    <a href="login.php" class="auth-side-btn"><span>Login</span> <span aria-hidden="true">&rarr;</span></a>
                                </div>
                            </div>
                        </aside>

                        <div class="auth-form-panel">
                            <div class="auth-form-inner">
                                <div class="auth-header">
                                    <h1>Create your account</h1>
                                    <p>Fill in the details below to get started.</p>
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

                                <?php if ($oauthError !== ''): ?>
                                    <div class="auth-message error">
                                        <?php echo htmlspecialchars($oauthError); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="register.php" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(brainoverflow_csrf_token()); ?>">
                                    <div class="form-group">
                                        <label for="username">Username</label>
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M20 21C20 17.7 16.4 15 12 15C7.6 15 4 17.7 4 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M12 12C14.2 12 16 10.2 16 8C16 5.8 14.2 4 12 4C9.8 4 8 5.8 8 8C8 10.2 9.8 12 12 12Z" stroke="currentColor" stroke-width="1.8"/>
                                        </svg>
                                        <input class="form-control" type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" autocomplete="username" placeholder="Username">
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email address</label>
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M4 6H20V18H4V6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M5 7L12 13L19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <input class="form-control" type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" autocomplete="email" placeholder="Email address">
                                    </div>

                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M7 10V8C7 5.2 9.2 3 12 3C14.8 3 17 5.2 17 8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M6 10H18V20H6V10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M12 14V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" placeholder="Password">
                                    </div>

                                    <div class="password-strength" id="passwordStrength" aria-live="polite">
                                        <div class="strength-summary">
                                            <span class="strength-title">Password strength:</span>
                                            <span class="strength-meter" aria-hidden="true"><span class="strength-meter-fill" id="strengthMeterFill"></span></span>
                                            <span class="strength-label" id="strengthLabel">Weak</span>
                                        </div>
                                        <ul class="strength-requirements">
                                            <li data-requirement="length">At least 8 characters</li>
                                            <li data-requirement="uppercase">Uppercase letter</li>
                                            <li data-requirement="lowercase">Lowercase letter</li>
                                            <li data-requirement="number">At least one number</li>
                                            <li data-requirement="special">At least one special character</li>
                                        </ul>
                                    </div>

                                    <div class="form-group">
                                        <label for="confirm_password">Confirm Password</label>
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M7 10V8C7 5.2 9.2 3 12 3C14.8 3 17 5.2 17 8V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="M6 10H18V20H6V10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M12 14V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Confirm Password">
                                    </div>

                                    <button type="submit" class="btn btn-hero auth-submit">Register <span aria-hidden="true">&rarr;</span></button>
                                </form>

                                <div class="auth-divider"><span>or</span></div>
                                <a class="google-placeholder" href="google-auth.php"><span class="google-mark" aria-hidden="true">G</span> Sign up with Google</a>

                                <p class="auth-bottom">
                                    Already have an account? <a href="login.php">Login</a>
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
    <script>
        (function () {
            var passwordInput = document.getElementById('password');
            var strengthPanel = document.getElementById('passwordStrength');
            var meterFill = document.getElementById('strengthMeterFill');
            var strengthLabel = document.getElementById('strengthLabel');
            var requirementItems = strengthPanel ? strengthPanel.querySelectorAll('[data-requirement]') : [];

            if (!passwordInput || !strengthPanel || !meterFill || !strengthLabel) {
                return;
            }

            function updatePasswordStrength() {
                var password = passwordInput.value;

                if (password === '') {
                    strengthPanel.classList.remove('is-visible');
                    meterFill.style.width = '0';
                    strengthLabel.textContent = 'Weak';
                    requirementItems.forEach(function (item) {
                        item.classList.remove('is-met');
                    });
                    return;
                }

                var checks = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };

                var score = Object.keys(checks).filter(function (key) {
                    return checks[key];
                }).length;

                requirementItems.forEach(function (item) {
                    item.classList.toggle('is-met', checks[item.dataset.requirement]);
                });

                strengthPanel.classList.add('is-visible');
                meterFill.style.width = (score * 20) + '%';

                if (score <= 2) {
                    strengthLabel.textContent = 'Weak';
                    meterFill.style.background = '#dc2626';
                } else if (score <= 4) {
                    strengthLabel.textContent = 'Medium';
                    meterFill.style.background = '#fb923c';
                } else {
                    strengthLabel.textContent = 'Strong';
                    meterFill.style.background = 'var(--accent)';
                }
            }

            passwordInput.addEventListener('input', updatePasswordStrength);
            updatePasswordStrength();
        }());
    </script>
</body>
</html>
