<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: frontpage.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];
generate_csrf_token();

// Handle lockdown toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_lockdown'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if (ROLES[$current_user_role] >= ROLES['admin']) {
            $new_status = LOCKDOWN_ENABLED ? '0' : '1';
            $stmt = $pdo->prepare("UPDATE site_settings SET value = ? WHERE key = 'lockdown_status'");
            $stmt->execute([$new_status]);
        }
    }
    header("Location: index.php");
    exit;
}

// Handle settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        try {
            $chat_color = $_POST['chat_color'] ?? '#89b4fa';
            if (!preg_match('/^#[a-f0-9]{6}$/i', $chat_color)) {
                $chat_color = '#89b4fa';
            }
            $refresh_interval = (int)($_POST['refresh_interval'] ?? 5);
            $valid_intervals = [0, 1, 3, 5, 10, 30];
            if (!in_array($refresh_interval, $valid_intervals)) {
                $refresh_interval = 5;
            }
            $theme = $_POST['theme'] ?? 'default';
            $all_themes = ['default', 'whitenblack', 'blacknwhite', 'dark', 'visibility', 'neon', 'ocean', 'forest', 'volcano', 'chaos', 'rainbow', 'rotten', 'cyberpunk'];
            $guest_themes = ['default', 'whitenblack', 'blacknwhite'];
            $available_themes = (ROLES[$current_user_role] >= ROLES['member']) ? $all_themes : $guest_themes;
            if (!in_array($theme, $available_themes)) {
                $theme = 'default';
            }
            $bio = trim($_POST['bio'] ?? '');
            if (mb_strlen($bio, 'UTF-8') > 255) {
                $bio = mb_substr($bio, 0, 255, 'UTF-8');
            }
            $stmt = $pdo->prepare("UPDATE users SET chat_color = ?, refresh_interval = ?, theme = ?, bio = ? WHERE id = ?");
            $stmt->execute([$chat_color, $refresh_interval, $theme, $bio, $current_user_id]);
            $_SESSION['feedback_message'] = 'Settings updated successfully!';
        } catch (PDOException $e) {
            $_SESSION['feedback_message'] = 'Error updating settings.';
        }
    }
    header("Location: index.php");
    exit;
}

// Handle panel state toggles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_panel'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $panel = $_POST['toggle_panel'];
        if (!in_array($panel, ['show_online_users', 'show_settings'])) {
            header("Location: index.php");
            exit;
        }
        $stmt = $pdo->prepare("SELECT $panel FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $current_state = $stmt->fetchColumn();
        $new_state = $current_state ? 0 : 1;
        $update = $pdo->prepare("UPDATE users SET $panel = ? WHERE id = ?");
        $update->execute([$new_state, $current_user_id]);
    }
    header("Location: index.php");
    exit;
}

// Fetch panel states
$stmt = $pdo->prepare("SELECT show_online_users, show_settings FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_states = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch current theme
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        /* =====================================================
           SOLID GRID SYSTEM — NO JS / NO TRANSPARENCY
           ===================================================== */
        header {
            background: var(--background);
            border-bottom: 2px solid var(--primary);
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown > summary {
            list-style: none;
            outline: none;
            cursor: pointer;
        }
        .dropdown > summary::-webkit-details-marker { display: none; }

        /* THE "CLICK OUTSIDE" OVERLAY (Pure CSS) */
        .dropdown[open] > summary::before {
            content: " ";
            display: block;
            position: fixed;
            top: 0; right: 0; bottom: 0; left: 0;
            z-index: 1500;
            background: transparent;
        }

        .dropdown-content {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 320px;
            background: var(--primary);
            border: 2px solid var(--primary);
            box-shadow: 6px 6px 0px var(--surface);
            z-index: 2000;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
        }

        .header-side:first-child .dropdown-content {
            left: 0;
            right: auto;
        }

        .dropdown-content a,
        .dropdown-content label,
        .dropdown-content button {
            all: unset;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
            background: var(--background);
            color: var(--text);
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            box-sizing: border-box;
            text-align: center;
        }

        .dropdown-content a:hover,
        .dropdown-content label:hover,
        .dropdown-content button:hover {
            background: var(--surface);
            color: var(--primary);
        }

        .dropdown-content i, .dropdown-content svg {
            width: 22px;
            height: 22px;
            margin-bottom: 8px;
            stroke-width: 2.5;
            color: var(--primary);
        }

        .grid-full {
            grid-column: span 2;
            flex-direction: row !important;
            gap: 12px;
            padding: 15px !important;
        }

        header .button {
            all: unset;
            padding: 8px 14px;
            border: 2px solid var(--primary);
            background: var(--background);
            color: var(--text);
            font-weight: 800;
            font-size: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        header .button:hover {
            background: var(--primary);
            color: var(--background);
        }

        .logout-btn { border-color: var(--error) !important; color: var(--error) !important; }
        .logout-btn:hover { background: var(--error) !important; color: var(--background) !important; }

        .lock-btn.danger { background: var(--error) !important; color: var(--background) !important; }
        .lock-btn.danger i { color: var(--background) !important; }

        .header-side { display: flex; gap: 8px; }
        .header-center h1 { font-size: 1.2rem; margin: 0; text-transform: uppercase; font-weight: 900; }
    </style>
</head>
<body>
    <input type="checkbox" id="users-toggle" <?= $user_states['show_online_users'] ? 'checked' : '' ?> style="display: none;">
    <input type="checkbox" id="settings-toggle" <?= $user_states['show_settings'] ? 'checked' : '' ?> style="display: none;">

    <div class="online-users-panel">
        <iframe src="online_users.php" frameborder="0"></iframe>
    </div>

    <div class="main-container">
        <div class="chat-container">
            <header>
                <div class="header-side">
                    <a href="contact.php" class="button"><i data-lucide="send"></i> CONTACT</a>
                </div>

                <div class="header-center">
                    <h1><?= SITE_NAME ?></h1>
                </div>

                <div class="header-side">
                    <?php if (ROLES[$_SESSION['user_role']] >= ROLES['moderator']): ?>
                    <details class="dropdown">
                        <summary class="button"><i data-lucide="shield"></i> STAFF</summary>
                        <div class="dropdown-content">
                            <a href="contact_submissions.php"><i data-lucide="inbox"></i> INBOX</a>
                            <a href="roles.php"><i data-lucide="crown"></i> ROLES</a>
                            <?php if (ROLES[$_SESSION['user_role']] >= ROLES['admin']): ?>
                                <a href="link_vault.php"><i data-lucide="database"></i> VAULT</a>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <button type="submit" name="toggle_lockdown" class="lock-btn <?= LOCKDOWN_ENABLED ? 'danger' : '' ?>">
                                        <i data-lucide="<?= LOCKDOWN_ENABLED ? 'unlock' : 'lock' ?>"></i>
                                        <?= LOCKDOWN_ENABLED ? 'UNLOCK' : 'LOCK' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>

                    <details class="dropdown">
                        <summary class="button"><i data-lucide="layout-grid"></i> MENU</summary>
                        <div class="dropdown-content">
                            <form method="POST" style="display:contents;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="toggle_panel" value="show_settings">
                                <label onclick="this.parentElement.submit()">
                                    <i data-lucide="settings-2"></i> SETTINGS
                                </label>
                            </form>
                            
                            <form method="POST" style="display:contents;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="toggle_panel" value="show_online_users">
                                <label onclick="this.parentElement.submit()">
                                    <i data-lucide="panel-left"></i> TOGGLE LEFT PANEL
                                </label>
                            </form>

                            <a href="pm_display.php"><i data-lucide="mail"></i> PMS</a>
                            <a href="my_profile.php"><i data-lucide="user-cog"></i> PROFILE</a>
                            <a href="rules.php"><i data-lucide="gavel"></i> RULES</a>
                            <a href="link_display.php"><i data-lucide="external-link"></i> LINKS</a>
                            <a href="uptime.php"><i data-lucide="pulse"></i> UPTIME</a>
                            
                            <a href="changelog.php"><i data-lucide="megaphone"></i> CHANGELOG</a>
                        </div>
                    </details>

                    <a href="logout.php" class="button logout-btn" title="Logout">
                        <i data-lucide="log-out"></i>
                    </a>
                </div>
            </header>

            <iframe src="display.php" name="display_frame" class="messages-frame" frameborder="0"></iframe>
            <iframe src="form.php" name="form_frame" class="form-frame" frameborder="0"></iframe>
        </div>
    </div>

    <div class="settings-panel">
        <iframe src="settings.php" frameborder="0"></iframe>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>