<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Fetch the current user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn() ?? 'default';
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Initial check for unread private messages
$unread_stmt = $pdo->prepare("SELECT COUNT(id) FROM private_messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->execute([$current_user_id]);
$has_unread_pms = $unread_stmt->fetchColumn() > 0;

// Fetch users active within the last minute
$online_users = $pdo->query("
    SELECT id, username, chat_color, role
    FROM users
    WHERE last_activity >= datetime('now', '-1 minute')
    ORDER BY username ASC
")->fetchAll(PDO::FETCH_ASSOC);

$online_count = count($online_users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Users</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <meta http-equiv="refresh" content="10">
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body { background-color: var(--background); color: var(--text); padding: 1rem; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .online-users-container { display: flex; flex-direction: column; height: 100%; }
        .header-section { border-bottom: 1px solid #363a4f; margin-bottom: 1rem; padding-bottom: 1rem; }
        h2 { margin: 0 0 0.5rem 0; font-size: 1.2rem; color: var(--primary); }
        .online-count { color: var(--subtle-text); font-size: 0.9rem; margin: 0; }
        .all-users-button { display: block; text-decoration: none; background-color: var(--surface); color: var(--primary); padding: 0.75rem; text-align: center; border-radius: 8px; font-weight: bold; margin-bottom: 1rem; border: 1px solid #363a4f; transition: all 0.2s ease; }
        .all-users-button:hover { background-color: var(--primary); color: var(--background); border-color: var(--primary); }
        .user-list-wrapper { flex-grow: 1; overflow-y: auto; }
        .online-users-list { list-style: none; padding: 0; margin: 0; }
        .online-users-list li { padding: 0.5rem 0.2rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; border-radius: 4px; transition: background-color 0.2s ease; }
        .online-users-list li:hover { background-color: rgba(255, 255, 255, 0.05); }
        .user-info { display: flex; align-items: center; gap: 0.5rem; flex-grow: 1; }
        .role-icon { font-size: 1rem; }
        .kick-btn {
            background: none; border: none; color: var(--error); cursor: pointer;
            font-size: 1.2rem; padding: 0 0.5rem; opacity: 0;
            transition: opacity 0.2s;
        }
        .online-users-list li:hover .kick-btn { opacity: 0.7; }
        .online-users-list li .kick-btn:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="online-users-container">
        <div class="header-section">
            <h2>Online</h2>
            <p class="online-count"><?= $online_count ?> user<?= $online_count !== 1 ? 's' : '' ?> online</p>
        </div>

        <a href="all_users.php" target="_parent" class="all-users-button">
            All Users <?= $has_unread_pms ? '📨' : '' ?>
        </a>

        <div class="user-list-wrapper">
            <ul class="online-users-list">
                <?php foreach ($online_users as $user): ?>
                    <li>
                        <div class="user-info">
                            <span class="role-icon" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?>">
                                <?= ROLE_ICONS[$user['role']] ?? '?' ?>
                            </span>
                            <a href="view_profile.php?user=<?= urlencode($user['username']) ?>" target="_parent" style="color: <?= htmlspecialchars($user['chat_color']) ?>; text-decoration: none;">
                                <?= htmlspecialchars($user['username']) ?>
                            </a>
                        </div>
                        <?php
                        $can_kick = false;
                        if (ROLES[$current_user_role] >= ROLES['moderator'] && $user['id'] != $current_user_id) {
                            if ($current_user_role === 'owner') { $can_kick = true; }
                            elseif (ROLES[$current_user_role] > ROLES[$user['role']]) { $can_kick = true; }
                        }
                        ?>
                        <?php if ($can_kick): ?>
                            <form method="POST" action="index.php" target="_parent" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="kick_user" value="<?= $user['id'] ?>">
                                <button type="submit" class="kick-btn" title="Kick user">&times;</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>