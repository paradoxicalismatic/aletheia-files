<?php
require_once 'config.php';

// Ensure user is logged in to see settings
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Please log in to access settings.";
    exit;
}

// Fetch the user's current settings to display in the form
try {
    $stmt = $pdo->prepare("SELECT chat_color, refresh_interval, theme, role, bio FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_color = $user_settings['chat_color'] ?? '#89b4fa';
    $current_interval = $user_settings['refresh_interval'] ?? 5;
    $current_theme = $user_settings['theme'] ?? 'default';
    $current_role = $user_settings['role'] ?? 'guest';
    $current_bio = $user_settings['bio'] ?? '';
} catch (PDOException $e) {
    $current_color = '#89b4fa';
    $current_interval = 5;
    $current_theme = 'default';
    $current_role = 'guest';
    $current_bio = '';
    $_SESSION['feedback_message'] = 'Database may need to be updated.';
}

$all_themes = ['default', 'light', 'dark', 'visibility', 'neon', 'ocean', 'forest', 'volcano', 'chaos', 'rainbow', 'rotten', 'metal'];
$guest_themes = ['default', 'light', 'dark'];

// Determine current role based on ROLES constant defined in config.php
$current_role_value = ROLES[$current_role] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            background-color: var(--background);
            color: var(--text);
            display: flex;
            flex-direction: column;
            font-family: 'Inter', sans-serif;
        }

        .header {
            flex-shrink: 0;
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        h1 {
            color: var(--primary);
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .intro-text {
            font-size: 0.85rem;
            color: var(--subtle-text);
        }

        .feedback {
            margin: 0.75rem;
            padding: 0.75rem;
            border-radius: 6px;
            background-color: var(--primary-bg-subtle, rgba(140, 120, 233, 0.2));
            color: var(--primary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .scrollable-form-area {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .scrollable-form-area::-webkit-scrollbar {
            display: none;
        }

        .settings-form {
            max-width: 100%;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            padding-bottom: 2rem;
        }

        .setting-wrapper {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: stretch;
        }

        .setting-wrapper label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text);
        }

        .input-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        input[type="color"] {
            width: 50px;
            height: 35px;
            border: 2px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            padding: 2px;
            background: var(--surface);
        }

        select {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background-color: var(--surface);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            cursor: pointer;
            min-width: 0;
        }

        select:focus {
            outline: none;
            border-color: var(--primary);
        }

        textarea {
            width: 100%;
            min-height: 70px;
            padding: 0.65rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background-color: var(--surface);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .button {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: var(--surface);
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .button:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Settings</h1>
        <p class="intro-text">Customize your chat experience</p>
    </div>

    <?php if (isset($_SESSION['feedback_message'])): ?>
        <p class="feedback"><?= htmlspecialchars($_SESSION['feedback_message']) ?></p>
        <?php unset($_SESSION['feedback_message']); ?>
    <?php endif; ?>

    <div class="scrollable-form-area">
        <form method="POST" action="index.php" target="_parent" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="update_settings" value="1">

            <div class="setting-wrapper">
                <label for="chat_color">Your Color</label>
                <div class="input-row">
                    <input type="color" id="chat_color" name="chat_color" value="<?= htmlspecialchars($current_color) ?>">
                    <span style="font-size: 0.85rem; color: var(--subtle-text);"><?= htmlspecialchars($current_color) ?></span>
                </div>
            </div>

            <div class="setting-wrapper">
                <label for="refresh_interval">Refresh Speed</label>
                <select id="refresh_interval" name="refresh_interval">
                    <option value="1" <?= $current_interval == 1 ? 'selected' : '' ?>>1 Second</option>
                    <option value="3" <?= $current_interval == 3 ? 'selected' : '' ?>>3 Seconds</option>
                    <option value="5" <?= $current_interval == 5 ? 'selected' : '' ?>>5 Seconds (Default)</option>
                    <option value="10" <?= $current_interval == 10 ? 'selected' : '' ?>>10 Seconds</option>
                    <option value="30" <?= $current_interval == 30 ? 'selected' : '' ?>>30 Seconds</option>
                    <option value="0" <?= $current_interval == 0 ? 'selected' : '' ?>>Manual Only</option>
                </select>
            </div>
            
            <div class="setting-wrapper">
                <label for="theme">Theme</label>
                <select id="theme" name="theme">
                    <?php
                    $available_themes = ($current_role_value >= ROLES['member']) ? $all_themes : $guest_themes;
                    foreach ($all_themes as $theme) {
                        if (in_array($theme, $available_themes)) {
                            $selected = ($current_theme == $theme) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($theme) . "\" $selected>" . ucfirst(str_replace('_', ' ', $theme)) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="setting-wrapper">
                <label for="bio">Your Bio <span style="color: var(--subtle-text); font-size: 0.8rem;">(max 255 chars)</span></label>
                <textarea id="bio" name="bio" maxlength="255" placeholder="Tell others about yourself..."><?= htmlspecialchars($current_bio) ?></textarea>
            </div>
            
            <button type="submit" class="button">Save Settings</button>
        </form>
    </div>
</body>
</html>