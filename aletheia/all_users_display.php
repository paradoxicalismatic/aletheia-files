<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch the current user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Fetch unread message counts for the current user
$unread_stmt = $pdo->prepare("SELECT sender_id, COUNT(id) as unread_count FROM private_messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id");
$unread_stmt->execute([$current_user_id]);
$unread_messages = $unread_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle search and fetch all users, including roles
$search_term = $_GET['search'] ?? '';
$sql = "
    SELECT
        id,
        username,
        chat_color,
        role,
        (CASE WHEN last_activity >= datetime('now', '-5 minutes') THEN 1 ELSE 0 END) as is_online
    FROM users
    WHERE id != :user_id AND username LIKE :search
    ORDER BY is_online DESC, username ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':user_id' => $current_user_id,
    ':search' => '%' . $search_term . '%'
]);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Users Display</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <meta http-equiv="refresh" content="30">
    <style>
        body { background-color: var(--surface); font-family: 'Inter', sans-serif; margin: 0; height: 100%; overflow-y: auto; }
        .user-list { list-style: none; padding: 0; margin: 0; }
        .user-list-item { padding: 1rem 1.5rem; border-bottom: 1px solid var(--background); display: flex; justify-content: space-between; align-items: center; gap: 1rem; transition: background-color 0.2s ease; }
        .user-list-item:last-child { border-bottom: none; }
        .user-list-item:hover { background-color: #363a4f; }
        .user-info { font-weight: 500; font-size: 1.1rem; flex-grow: 1; display: flex; align-items: center; gap: 0.75rem; }
        .role-icon { font-size: 1.1rem; }
        .user-status-wrapper { display: flex; align-items: center; gap: 1rem; }
        .user-status { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; color: var(--subtle-text); }
        .status-indicator { width: 10px; height: 10px; border-radius: 50%; }
        .status-online { background-color: #a6e3a1; }
        .status-offline { background-color: var(--error); }
        .pm-link { text-decoration: none; font-size: 1.5rem; color: var(--subtle-text); transition: color 0.2s ease; }
        .pm-link:hover { color: var(--primary); }
        .pm-link.has-new { color: var(--primary); animation: bounce 1.5s infinite; }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% {transform: translateY(0);} 40% {transform: translateY(-5px);} 60% {transform: translateY(-2px);} }
    </style>
</head>
<body>
    <ul class="user-list">
        <?php if (empty($all_users)): ?>
            <li class="user-list-item">No users found.</li>
        <?php else: ?>
            <?php foreach ($all_users as $user): ?>
                <li class="user-list-item">
                    <div class="user-info" style="color: <?= htmlspecialchars($user['chat_color']) ?>;">
                        <span class="role-icon" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?>">
                            <?= ROLE_ICONS[$user['role']] ?? '?' ?>
                        </span>
                        <span><?= htmlspecialchars($user['username']) ?></span>
                    </div>
                    <div class="user-status-wrapper">
                        <div class="user-status">
                            <span class="status-indicator <?= $user['is_online'] ? 'status-online' : 'status-offline' ?>"></span>
                            <span><?= $user['is_online'] ? 'Online' : 'Offline' ?></span>
                        </div>
                        <a href="private_message.php?with=<?= $user['id'] ?>" target="_parent" class="pm-link <?= isset($unread_messages[$user['id']]) ? 'has-new' : '' ?>">
                            <?= isset($unread_messages[$user['id']]) ? '📨' : '✉️' ?>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</body>
</html>
