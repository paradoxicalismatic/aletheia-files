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
    <title>Settings</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        /* CRITICAL: Ensure HTML takes full available height */
        html {
            height: 100%;
        }

        body {
            background-color: var(--background);
            color: var(--text);
            padding: 1rem;
            text-align: center;
            /* Flex layout to manage header and scroll area */
            display: flex;
            flex-direction: column;
            min-height: 100%; /* Must use 100% here as html is 100% */
            margin: 0; /* Remove default body margin */
        }
        h1 { color: var(--primary); margin-bottom: 0.5rem; }
        .intro-text { margin-top: 0; margin-bottom: 1rem; }
        .feedback {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 8px;
            background-color: var(--primary-bg-subtle, rgba(140, 120, 233, 0.2));
            color: var(--primary);
            font-weight: 500;
        }

        /* --- Scrollable Container Styles --- */
        .scrollable-form-area {
            /* Use flex-grow to take remaining space */
            flex-grow: 1;
            /* Use calc() to determine height after header and padding */
            height: calc(100vh - 120px); /* Estimated height remaining after header and body padding */
            overflow-y: auto; /* Enables vertical scrolling */
            max-width: 350px; /* Reduced width */
            width: 100%;
            margin: 0 auto;
            padding-right: 15px; 
            box-sizing: border-box;
            -ms-overflow-style: none; /* IE and Edge */
            scrollbar-width: none; /* Firefox */
        }
        /* Hide scrollbar for Webkit browsers (Chrome, Safari) */
        .scrollable-form-area::-webkit-scrollbar {
            display: none;
        }
        /* --- End Scrollable Container Styles --- */

        .settings-form {
            display: inline-flex;
            flex-direction: column;
            align-items: stretch;
            gap: 1.5rem;
            margin-top: 0.5rem; 
            padding-right: 10px;
            box-sizing: border-box;
            
            /* ADDED: Extra space below the form content to allow the button to scroll fully into view */
            padding-bottom: 5rem; 
        }
        .setting-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between; 
            gap: 1rem;
        }
        .setting-wrapper label {
            font-size: 1.1rem;
            font-weight: 500;
            flex-shrink: 0;
            text-align: left;
        }
        input[type="color"] {
            width: 60px;
            height: 40px;
            border: 2px solid var(--text);
            border-radius: 8px;
            cursor: pointer;
            padding: 3px;
        }
        select {
            padding: 0.5rem;
            border: 2px solid var(--text);
            border-radius: 8px;
            background-color: var(--surface);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 1; 
            min-width: 100px;
        }
        textarea {
            width: 100%;
            min-height: 80px;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid #363a4f;
            background-color: var(--background);
            color: var(--text);
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            resize: vertical;
        }
        textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .settings-form .button {
            width: 100%;
        }
    </style>
</head>
<body>
    <h1>Settings</h1>
    <p class="intro-text">Customize your chat experience.</p>

    <!-- START SCROLLABLE AREA -->
    <div class="scrollable-form-area">

        <?php if (isset($_SESSION['feedback_message'])): ?>
            <p class="feedback"><?= htmlspecialchars($_SESSION['feedback_message']) ?></p>
            <?php unset($_SESSION['feedback_message']); ?>
        <?php endif; ?>

        <form method="POST" action="index.php" target="_parent" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="update_settings" value="1">

            <div class="setting-wrapper">
                <label for="chat_color">Your Color:</label>
                <input type="color" id="chat_color" name="chat_color" value="<?= htmlspecialchars($current_color) ?>">
            </div>

            <div class="setting-wrapper">
                <label for="refresh_interval">Refresh Speed:</label>
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
                <label for="theme">Theme:</label>
                <select id="theme" name="theme">
                    <?php
                    // Use $current_role_value to determine theme access
                    $available_themes = ($current_role_value >= ROLES['member']) ? $all_themes : $guest_themes;
                    foreach ($all_themes as $theme) {
                        if (in_array($theme, $available_themes)) {
                            $selected = ($current_theme == $theme) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($theme) . "\" $selected>" . ucfirst($theme) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="setting-wrapper" style="flex-direction: column; align-items: stretch;">
                <label for="bio">Your Bio (max 255 chars):</label>
                <textarea id="bio" name="bio" maxlength="255"><?= htmlspecialchars($current_bio) ?></textarea>
            </div>
            
            <button type="submit" class="button">Save Settings</button>
        </form>
    </div>
    <!-- END SCROLLABLE AREA -->
</body>
</html>