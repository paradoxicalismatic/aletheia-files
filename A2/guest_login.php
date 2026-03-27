<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_GET['kicked'])) {
    $error = 'You have been kicked from the chat.';
}

if (LOCKDOWN_ENABLED) {
    $error = 'The site is currently in lockdown mode. Guest access is temporarily disabled.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $captcha_input   = strtolower($_POST['captcha'] ?? '');
    $captcha_correct = strtolower($_SESSION['captcha_text'] ?? 'a_different_string');

    if (empty($captcha_input) || $captcha_input !== $captcha_correct) {
        $error = "Incorrect CAPTCHA text entered.";
    } else {
        $username = clean_input($_POST['username'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $error = "Username may only contain letters and numbers.";
        } elseif (strlen($username) < 3) {
            $error = "Username must be at least 3 characters.";
        } else {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $existing = $stmt->fetch();

            if ($existing && $existing['password'] !== null && $existing['password'] !== '') {
                $error = "That username belongs to an existing account.";
            } else {
                $pdo->beginTransaction();
                try {
                    if ($existing && ($existing['password'] === null || $existing['password'] === '')) {
                        $user_id = $existing['id'];
                        $log_action = 'guest_login';
                    } else {
                        $insert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, '', 'guest')");
                        $insert->execute([$username]);
                        $user_id = $pdo->lastInsertId();
                        $log_action = 'guest_register';
                    }

                    $ban_stmt = $pdo->prepare("SELECT user_id FROM banned_users WHERE user_id = ?");
                    $ban_stmt->execute([$user_id]);
                    if ($ban_stmt->fetch()) {
                        $pdo->rollBack();
                        $error = "This account has been permanently banned.";
                    } else {
                        $_SESSION['user_id'] = $user_id;

                        try {
                            $log_stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action) VALUES (?, ?, ?)");
                            $log_stmt->execute([$user_id, $username, $log_action]);
                        } catch (PDOException $e) {
                            error_log("Failed to log guest action: " . $e->getMessage());
                        }

                        $pdo->commit();
                        header("Location: index.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "A database error occurred. Please try again.";
                    error_log("Guest access error: " . $e->getMessage());
                }
            }
        }
    }
}

// Fetch top chatters by message count
$top_chatters = [];
try {
    $top_stmt = $pdo->query("
        SELECT u.username, COUNT(m.id) AS msg_count
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE u.role != 'guest'
        GROUP BY m.user_id
        ORDER BY msg_count DESC
        LIMIT 6
    ");
    $top_chatters = $top_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch top chatters: " . $e->getMessage());
}

set_captcha_text();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Access: <?= SITE_NAME ?></title>
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
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; overflow: hidden; }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% 50%, rgba(124, 58, 237, 0.12) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 80% 20%, rgba(167, 139, 250, 0.07) 0%, transparent 60%),
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

        .left, .right { padding: 36px 34px; }

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
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--primary-bright);
            text-shadow: 0 0 40px rgba(167, 139, 250, 0.35);
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 0.76rem;
            color: var(--text-muted);
            font-weight: 300;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .divider {
            height: 1px;
            background: linear-gradient(90deg, var(--border), transparent);
            margin-bottom: 18px;
        }

        .message {
            padding: 9px 13px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-family: 'JetBrains Mono', monospace;
            margin-bottom: 14px;
            line-height: 1.5;
        }
        .error-message {
            background: rgba(248, 113, 113, 0.07);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: var(--danger);
        }
        .success-message {
            background: rgba(74, 222, 128, 0.07);
            border: 1px solid rgba(74, 222, 128, 0.3);
            color: var(--success);
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
            margin-bottom: 14px;
        }

        form { display: flex; flex-direction: column; gap: 11px; }

        .field-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.58rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 5px;
        }

        .field { display: flex; flex-direction: column; }

        input[type="text"] {
            background: rgba(139, 92, 246, 0.06);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 9px 12px;
            font-family: 'Sora', sans-serif;
            font-size: 0.86rem;
            transition: border-color 0.2s, background 0.2s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: rgba(139, 92, 246, 0.5);
            background: rgba(139, 92, 246, 0.1);
        }

        input[type="text"]:disabled {
            opacity: 0.35;
            cursor: not-allowed;
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
            padding: 11px 16px;
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
            margin-top: 16px;
            padding-top: 14px;
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

        /* Right panel — leaderboard */
        .panel-header { margin-bottom: 14px; }
        .panel-header .eyebrow { margin-bottom: 4px; }

        .panel-title {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--primary-bright);
        }

        .leaderboard {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .chatter-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 13px;
            border-radius: 10px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(139, 92, 246, 0.08);
            transition: border-color 0.2s, background 0.2s;
            animation: slideIn 0.4s ease both;
        }

        .chatter-row:nth-child(1) { animation-delay: 0.05s; }
        .chatter-row:nth-child(2) { animation-delay: 0.10s; }
        .chatter-row:nth-child(3) { animation-delay: 0.15s; }
        .chatter-row:nth-child(4) { animation-delay: 0.20s; }
        .chatter-row:nth-child(5) { animation-delay: 0.25s; }
        .chatter-row:nth-child(6) { animation-delay: 0.30s; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(8px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .chatter-row:hover {
            background: rgba(139, 92, 246, 0.06);
            border-color: rgba(139, 92, 246, 0.2);
        }

        .chatter-row:nth-child(1) .rank { color: #fbbf24; }
        .chatter-row:nth-child(2) .rank { color: #94a3b8; }
        .chatter-row:nth-child(3) .rank { color: #c07040; }

        .rank {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            color: var(--text-dim);
            width: 16px;
            flex-shrink: 0;
            text-align: center;
        }

        .chatter-name {
            flex: 1;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .msg-bar-wrap {
            flex: 1;
            height: 3px;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 99px;
            overflow: hidden;
        }

        .msg-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--primary));
            border-radius: 99px;
        }

        .msg-count {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            color: var(--text-dim);
            flex-shrink: 0;
            min-width: 36px;
            text-align: right;
        }

        .no-chatters {
            font-size: 0.75rem;
            color: var(--text-dim);
            font-family: 'JetBrains Mono', monospace;
            padding: 12px 0;
        }

        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
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
    </style>
</head>
<body>
<div class="container">

    <!-- Left: guest form -->
    <div class="left">
        <div class="eyebrow">// guest access</div>
        <h1>Guest Access</h1>
        <p class="subtitle">Pick a username and solve the CAPTCHA — no account needed.</p>

        <div class="divider"></div>

        <?php if ($error): ?>
            <div class="message error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (LOCKDOWN_ENABLED): ?>
            <div class="lockdown-badge">⚠ lockdown active — guest access suspended</div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="field">
                <div class="field-label">Username</div>
                <input
                    type="text"
                    name="username"
                    placeholder="choose a username"
                    autocomplete="off"
                    required
                    <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>
                >
            </div>

            <div class="field">
                <div class="field-label">Verification</div>
                <div class="captcha-wrap">
                    <img src="captcha_image.php" alt="CAPTCHA">
                    <input
                        type="text"
                        name="captcha"
                        placeholder="enter text above"
                        autocomplete="off"
                        required
                        <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>
                    >
                </div>
            </div>

            <button type="submit" <?= LOCKDOWN_ENABLED ? 'disabled' : '' ?>>
                Enter Chat
                <span class="btn-arrow">→</span>
            </button>
        </form>

        <div class="footer-links">
            <a href="member_login.php">
                <span>Have a member account?</span>
                <span>→</span>
            </a>
            <a href="frontpage.php">
                <span>← Back</span>
            </a>
        </div>
    </div>

    <!-- Right: leaderboard -->
    <div class="right">
        <div>
            <div class="panel-header">
                <div class="eyebrow">// all time</div>
                <div class="panel-title">Active Chatters</div>
            </div>

            <?php if (empty($top_chatters)): ?>
                <div class="no-chatters">no messages yet.</div>
            <?php else: ?>
                <?php $max = max(array_column($top_chatters, 'msg_count')); ?>
                <div class="leaderboard">
                    <?php foreach ($top_chatters as $i => $chatter): ?>
                        <div class="chatter-row">
                            <span class="rank"><?= $i + 1 ?></span>
                            <span class="chatter-name"><?= htmlspecialchars($chatter['username']) ?></span>
                            <div class="msg-bar-wrap">
                                <div class="msg-bar" style="width: <?= round(($chatter['msg_count'] / $max) * 100) ?>%"></div>
                            </div>
                            <span class="msg-count"><?= number_format($chatter['msg_count']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="status">
            <span class="status-dot"></span>
            server online 
        </div>
    </div>

</div>
</body>
</html>
