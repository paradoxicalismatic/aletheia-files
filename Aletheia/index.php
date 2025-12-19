<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];
generate_csrf_token();

// Handle lockdown toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_lockdown'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Double-check role for security
        if (ROLES[$current_user_role] >= ROLES['admin']) {
            $new_status = LOCKDOWN_ENABLED ? '0' : '1';
            $stmt = $pdo->prepare("UPDATE site_settings SET value = ? WHERE key = 'lockdown_status'");
            $stmt->execute([$new_status]);
        }
    }
    header("Location: index.php"); // Redirect to prevent form resubmission
    exit;
}

// Handle settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        try {
            // Sanitize and validate chat color
            $chat_color = $_POST['chat_color'] ?? '#89b4fa';
            if (!preg_match('/^#[a-f0-9]{6}$/i', $chat_color)) {
                $chat_color = '#89b4fa'; // Default to a safe value if invalid
            }

            // Sanitize and validate refresh interval
            $refresh_interval = (int)($_POST['refresh_interval'] ?? 5);
            $valid_intervals = [0, 1, 3, 5, 10, 30];
            if (!in_array($refresh_interval, $valid_intervals)) {
                $refresh_interval = 5; // Default to a safe value if invalid
            }

            // Sanitize and validate theme based on user role
            $theme = $_POST['theme'] ?? 'default';
            $all_themes = ['default', 'light', 'dark', 'visibility', 'neon', 'ocean', 'forest', 'volcano', 'chaos', 'rainbow', 'rotten', 'metal'];
            $guest_themes = ['default', 'light', 'dark'];
            $available_themes = (ROLES[$current_user_role] >= ROLES['member']) ? $all_themes : $guest_themes;
            if (!in_array($theme, $available_themes)) {
                $theme = 'default'; // Default if invalid or not allowed for user's role
            }

            // Sanitize and validate bio
            $bio = trim($_POST['bio'] ?? '');
            if (mb_strlen($bio, 'UTF-8') > 255) {
                $bio = mb_substr($bio, 0, 255, 'UTF-8'); // Truncate if too long
            }

            // Update the user's settings in the database
            $stmt = $pdo->prepare("UPDATE users SET chat_color = ?, refresh_interval = ?, theme = ?, bio = ? WHERE id = ?");
            $stmt->execute([$chat_color, $refresh_interval, $theme, $bio, $current_user_id]);

            $_SESSION['feedback_message'] = 'Settings updated successfully!';

        } catch (PDOException $e) {
            $_SESSION['feedback_message'] = 'Error updating settings.';
        }
    }
    // Redirect to prevent form resubmission and to apply changes immediately
    header("Location: index.php");
    exit;
}


// Handle kick user request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kick_user'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $user_to_kick_id = $_POST['kick_user'];

        // Fetch the role of the user to be kicked for permission checks
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_to_kick_id]);
        $user_to_kick_role = $stmt->fetchColumn();

        $can_kick = false;
        if (ROLES[$current_user_role] >= ROLES['moderator'] && $current_user_id != $user_to_kick_id) {
            if ($current_user_role === 'owner' || ROLES[$current_user_role] > ROLES[$user_to_kick_role]) {
                $can_kick = true;
            }
        }

        if ($can_kick) {
            try {
                $pdo->beginTransaction();

                // Add the user to the kicks table for 5 minutes
                $kick_stmt = $pdo->prepare("INSERT INTO kicks (user_id, kicked_by, expires_at) VALUES (?, ?, datetime('now', '+5 minutes'))");
                $kick_stmt->execute([$user_to_kick_id, $current_user_id]);
                
                // Set their last activity to the past so they appear offline immediately
                $offline_stmt = $pdo->prepare("UPDATE users SET last_activity = datetime('now', '-5 minutes') WHERE id = ?");
                $offline_stmt->execute([$user_to_kick_id]);

                $pdo->commit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                // Optionally handle or log the error
            }
        }
    }
    // Redirect to prevent form resubmission
    header("Location: index.php");
    exit;
}
// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message_id = $_POST['delete_message'];
        
        // Fetch message author's ID and role
        $stmt = $pdo->prepare("SELECT user_id, role FROM messages JOIN users ON users.id = messages.user_id WHERE messages.id = ?");
        $stmt->execute([$message_id]);
        $message_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($message_data) {
            $author_id = $message_data['user_id'];
            $author_role = $message_data['role'];

            $is_own_message = $current_user_id == $author_id;
            $can_self_delete = $is_own_message && (ROLES[$current_user_role] >= ROLES['moderator']);
            $can_staff_delete = !$is_own_message && (ROLES[$current_user_role] > ROLES[$author_role]) && (ROLES[$current_user_role] >= ROLES['moderator']);

            if ($can_self_delete || $can_staff_delete) {
                $del_stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                $del_stmt->execute([$message_id]);
            }
        }
    }
    header("Location: index.php");
    exit;
}

// Fetch the user's current theme to style the main page
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default'; // Fallback
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="dropdown.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <style>
        .header-side:first-child .dropdown-content {
            left: 0;
            right: auto;
        }
        
    </style>
</head>
<body>
    <input type="checkbox" id="users-toggle" style="display: none;">
    <input type="checkbox" id="settings-toggle" style="display: none;">

    <div class="online-users-panel">
        <iframe src="online_users.php" frameborder="0"></iframe>
    </div>

    <div class="main-container">
        <div class="chat-container">
            <header>
                <div class="header-side">
                    <details class="dropdown">
                        <summary class="button dropdown-button" title="Users">👥</summary>
                        <div class="dropdown-content">
                            <label for="users-toggle">🟢 Online Users</label>
                            <a href="all_users.php" target="_parent">✉️ Private Messages</a>
                        </div>
                    </details>
                    <a href="contact.php" class="button contact-button" title="Contact Us">✉️</a>
                </div>
                <div class="header-center">
                    <h1><?= SITE_NAME ?></h1>
                </div>
                <div class="header-side">
                    
<?php if (ROLES[$_SESSION['user_role']] >= ROLES['moderator']): ?>
                    <details class="dropdown">
                        <summary class="button dropdown-button" title="Staff Menu">🛡️</summary>
                        <div class="dropdown-content">
                            <a href="contact_submissions.php" title="Submissions">📥 Submissions</a>
                            <a href="roles.php" title="Manage Roles">👑 Manage Roles</a>
                            <?php if (ROLES[$_SESSION['user_role']] >= ROLES['admin']): ?>
                                <a href="link_vault.php" title="Link Vault">🔗 Link Vault</a>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <button 
                                        type="submit" 
                                        name="toggle_lockdown" 
                                        title="Toggle Site Lockdown"
                                        style="background: none; border: none; width: 100%; text-align: left; cursor: pointer;"
                                    >
                                        <?= LOCKDOWN_ENABLED ? '🔑 Unlock Site' : '🔒 Lock Site' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>

                    <a href="http://rotchatbadnytkkvlk47otadiyvq3oamhv6y6nvsm74aq5z65mkwumad.onion/chat.php" target="_blank" class="button rot-chat-button" title="ROT-CHAT">ROT-CHAT</a>
                    <a href="logout.php" class="button logout-button" title="Logout">🚪</a>
                    
                    <details class="dropdown">
                        <summary class="button dropdown-button" title="Settings & Info">≡</summary>
                        <div class="dropdown-content">
                            <label for="settings-toggle">⚙️ Open/Close Settings</label>
                            <a href="uptime.php">📈 Server Uptime</a>
                            <a href="news.php">📰 News & Updates</a>
                            <a href="rules.php">📜 Site Rules</a>
                            <a href="changelog.php">📋 Changelog</a>
                            <a href="link_display.php">🔗 Link List</a>
                        </div>
                    </details>

                </div>
            </header>
            <iframe src="display.php" name="display_frame" class="messages-frame" frameborder="0"></iframe>
            <iframe src="form.php" name="form_frame" class="form-frame" frameborder="0"></iframe>
        </div>
    </div>
 
    <div class="settings-panel">
        <iframe src="settings.php" frameborder="0"></iframe>
    </div>
</body>
</html>
