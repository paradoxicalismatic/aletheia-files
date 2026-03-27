k<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if (LOCKDOWN_ENABLED) {
    $error = 'The site is currently in lockdown mode. New registrations are temporarily disabled.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha_input = strtolower($_POST['captcha'] ?? '');
    $captcha_correct = strtolower($_SESSION['captcha_text'] ?? 'a_different_string');

    if (empty($captcha_input) || $captcha_input !== $captcha_correct) {
        $error = "Incorrect CAPTCHA text entered.";
    } else {
        $username = clean_input($_POST['username']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $member_code = trim($_POST['member_code'] ?? '');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $error = "Username can only contain letters (a-z) and numbers (0-9).";
        } elseif (strlen($username) < 3) {
            $error = "Username too short (min 3 chars).";
        } elseif ($stmt->fetch()) {
            $error = "Username already taken.";
        } elseif ($password !== $confirm) {
            $error = "Passwords don't match.";
        } elseif (empty($member_code)) {
            $error = "A member code is required to register.";
        } else {
            $code_stmt = $pdo->prepare("SELECT id FROM member_codes WHERE code = ? AND is_used = 0");
            $code_stmt->execute([$member_code]);
            $valid_code = $code_stmt->fetch();

            if (!$valid_code) {
                $error = "Invalid or already used member code.";
            } else {
                $pdo->beginTransaction();
                try {
                    $hashed = password_hash($password . PEPPER, PASSWORD_BCRYPT);
                    $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'member')");
                    $insert_stmt->execute([$username, $hashed]);
                    $new_user_id = $pdo->lastInsertId();

                    $update_code_stmt = $pdo->prepare("UPDATE member_codes SET is_used = 1, used_by = ?, used_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $update_code_stmt->execute([$new_user_id, $valid_code['id']]);

                    $pdo->commit();
                    $_SESSION['success'] = "Registration successful! Please login.";
                    header("Location: member_login.php");
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Database error during registration.";
                }
            }
        }
    }
}

if (function_exists('set_captcha_text')) {
    set_captcha_text();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register: <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0d0b14;
            --surface: #161222;
            --surface2: #1e1830;
            --border: rgba(139, 92, 246, 0.18);
            --primary: #a78bfa;
            --primary-bright: #c4b5fd;
            --accent: #7c3aed;
            --text: #e2dff0;
            --text-muted: #7c7a8e;
            --text-dim: #4a4760;
            --danger: #f87171;
            --success: #4ade80;
            --gold: #fbbf24;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { height: 100%; }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 15% 50%, rgba(124, 58, 237, 0.12) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 85% 20%, rgba(167, 139, 250, 0.07) 0%, transparent 60%),
                radial-gradient(ellipse 30% 40% at 70% 80%, rgba(109, 40, 217, 0.08) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            width: 100%;
            max-width: 780px;
            background: var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(139, 92, 246, 0.12),
                0 32px 80px rgba(0, 0, 0, 0.6),
                0 0 120px rgba(124, 58, 237, 0.08);
            animation: fadeIn 0.5s ease both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .left, .right { padding: 32px 30px; }

        .left {
            background: var(--surface);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .right {
            background: var(--surface2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .eyebrow {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.6rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--primary);
            opacity: 0.75;
            margin-bottom: 6px;
        }

        h1 {
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--primary-bright);
            text-shadow: 0 0 40px rgba(167, 139, 250, 0.35);
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 0.74rem;
            color: var(--text-muted);
            font-weight: 300;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, var(--border), transparent);
            margin-bottom: 16px;
        }

        .message {
            padding: 9px 13px;
            border-radius: 8px;
            font-size: 0.74rem;
            font-family: 'JetBrains Mono', monospace;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .error-message {
            background: rgba(248, 113, 113, 0.07);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: var(--danger);
        }

        .lockdown-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            color: var(--danger);
            background: rgba(248, 113, 113, 0.08);
            border: 1px solid rgba(248, 113, 113, 0.25);
            border-radius: 6px;
            padding: 5px 10px;
            margin-bottom: 12px;
        }

        form { display: flex; flex-direction: column; gap: 10px; }

        .field { display: flex; flex-direction: column; }

        .field-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.57rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .required-badge {
            font-size: 0.54rem;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.1em;
            color: var(--gold);
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 4px;
            padding: 1px 5px;
        }

        input[type="text"],
        input[type="password"] {
            background: rgba(139, 92, 246, 0.06);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 8px 12px;
            font-family: 'Sora', sans-serif;
            font-size: 0.85rem;
            transition: border-color 0.2s, background 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: rgba(139, 92, 246, 0.5);
            background: rgba(139, 92, 246, 0.1);
        }

        input[name="member_code"] {
            border-color: rgba(251, 191, 36, 0.25);
            background: rgba(251, 191, 36, 0.04);
        }

        input[name="member_code"]:focus {
            border-color: rgba(251, 191, 36, 0.55);
            background: rgba(251, 191, 36, 0.07);
        }

        input[name="member_code"]::placeholder {
            color: rgba(251, 191, 36, 0.3);
        }

        input:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #161222 inset !important;
            -webkit-text-fill-color: #e2dff0 !important;
        }

        .captcha-wrap { display: flex; flex-direction: column; gap: 7px; }

        .captcha-wrap img {
            border-radius: 8px;
            border: 1px solid var(--border);
            width: 100%;
            height: auto;
        }

        .captcha-wrap input {
            text-align: center;
            letter-spacing: 3px;
            font-family: 'JetBrains Mono', monospace;
        }

        button[type="submit"] {
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: 0.88rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent) 0%, #6d28d9 100%);
            color: #fff;
            box-shadow: 0 4px 20px rgba(124, 58, 237, 0.35);
            transition: all 0.2s ease;
        }

        button[type="submit"]:hover:not(:disabled) {
            box-shadow: 0 6px 28px rgba(124, 58, 237, 0.55);
            transform: translateY(-1px);
        }

        button[type="submit"]:disabled {
            background: rgba(139, 92, 246, 0.15);
            color: var(--text-dim);
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-arrow {
            font-size: 0.8rem;
            opacity: 0.6;
            transition: transform 0.2s ease;
        }

        button[type="submit"]:hover:not(:disabled) .btn-arrow {
            transform: translateX(4px);
            opacity: 1;
        }

        .footer-links {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .footer-links a {
            font-size: 0.72rem;
            color: var(--text-dim);
            text-decoration: none;
            font-family: 'JetBrains Mono', monospace;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-links a:hover { color: var(--primary); }

        /* Right panel */
        .right h2 {
            font-size: 0.62rem;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 12px;
        }

        .code-explainer {
            padding: 12px 14px;
            border-radius: 10px;
            background: rgba(251, 191, 36, 0.05);
            border: 1px solid rgba(251, 191, 36, 0.2);
            margin-bottom: 12px;
        }

        .code-explainer-title {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gold);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .code-explainer p {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.55;
            font-weight: 300;
        }

        .info-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-item {
            display: flex;
            gap: 11px;
            align-items: flex-start;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(139, 92, 246, 0.1);
            transition: border-color 0.2s, background 0.2s;
        }

        .info-item:hover {
            background: rgba(139, 92, 246, 0.06);
            border-color: rgba(139, 92, 246, 0.25);
        }

        .info-icon {
            font-size: 0.85rem;
            padding-top: 1px;
            flex-shrink: 0;
        }

        .info-text strong {
            display: block;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
        }

        .info-text span {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 300;
            line-height: 1.4;
        }

        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            font-size: 0.68rem;
            color: var(--text-dim);
            font-family: 'JetBrains Mono', monospace;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 8px #4ade80;
            animation: pulse 2.5s ease infinite;
            flex-shrink: 0;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        @media (max-width: 600px) {
            .container { grid-template-columns: 1fr; }
            .right { display: none; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="left">
        <div class="eyebrow">// create account</div>
        <h1>Register</h1>
        <p class="subtitle">Member codes are required. No code, no account.</p>

        <div class="divider"></div>

        <?php if ($error): ?>
            <div class="message error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (LOCKDOWN_ENABLED): ?>
            <div class="lockdown-badge">⚠ lockdown active — registrations suspended</div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="field">
                <div class="field-label">Username</div>
                <input type="text" name="username" placeholder="choose a username" autocomplete="off" required <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
            </div>

            <div class="field">
                <div class="field-label">Password</div>
                <input type="password" name="password" placeholder="••••••••" required <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
            </div>

            <div class="field">
                <div class="field-label">Confirm Password</div>
                <input type="password" name="confirm_password" placeholder="••••••••" required <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
            </div>

            <div class="field">
                <div class="field-label">
                    Member Code
                    <span class="required-badge">required</span>
                </div>
                <input type="text" name="member_code" placeholder="enter your invite code" autocomplete="off" required <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
            </div>

            <?php if (function_exists('set_captcha_text')): ?>
            <div class="field">
                <div class="field-label">Verification</div>
                <div class="captcha-wrap">
                    <img src="captcha_image.php" alt="CAPTCHA">
                    <input type="text" name="captcha" placeholder="enter text above" autocomplete="off" required <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
                Create Account
                <span class="btn-arrow">→</span>
            </button>
        </form>

        <div class="footer-links">
            <a href="member_login.php">
                <span>Already have an account?</span>
                <span>→</span>
            </a>
            <a href="frontpage.php">
                <span>← Back</span>
            </a>
        </div>
    </div>

    <div class="right">
        <div>
            <h2>Membership</h2>

            <div class="code-explainer">
                <div class="code-explainer-title">🔑 Invite-only registration</div>
                <p>You must have a valid member code to create an account. Codes are single-use and issued by staff. Without one, use guest access instead.</p>
            </div>

            <div class="info-block">
                <div class="info-item">
                    <div class="info-icon">👤</div>
                    <div class="info-text">
                        <strong>Persistent identity</strong>
                        <span>Your username is yours. Guests can't claim it.</span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">⚡</div>
                    <div class="info-text">
                        <strong>Member perks</strong>
                        <span>Access to more themes, and a permanent account.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="status">
            <span class="status-dot"></span>
            Aletheia up :D
        </div>
    </div>

</div>
</body>
</html>
