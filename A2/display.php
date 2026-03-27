<?php
require_once 'config.php';
require_once 'cmds.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// --- RANK DEFINITIONS ---
$ranks = [
    ['symbol' => '♆', 'name' => 'Rank 8', 'messages' => 10000, 'size' => '16px'],
    ['symbol' => '✪', 'name' => 'Rank 7', 'messages' => 7500, 'size' => '15px'],
    ['symbol' => '✯', 'name' => 'Rank 6', 'messages' => 5000, 'size' => '15px'],
    ['symbol' => '★', 'name' => 'Rank 5', 'messages' => 2500, 'size' => '14px'],
    ['symbol' => '✦', 'name' => 'Rank 4', 'messages' => 1000, 'size' => '14px'],
    ['symbol' => '⬢', 'name' => 'Rank 3', 'messages' => 500, 'size' => '13px'],
    ['symbol' => '◆', 'name' => 'Rank 2', 'messages' => 100, 'size' => '10px'],
    ['symbol' => '●', 'name' => 'Rank 1', 'messages' => 0, 'size' => '20px']
];

function get_user_rank($message_count, $ranks) {
    foreach ($ranks as $rank) {
        if ($message_count >= $rank['messages']) {
            return $rank;
        }
    }
    return $ranks[count($ranks) - 1];
}

// Fetch the current user's settings
try {
    $stmt = $pdo->prepare("SELECT refresh_interval, theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user_settings = [];
}

$user_refresh_interval = isset($user_settings['refresh_interval']) ? (int)$user_settings['refresh_interval'] : 5;
$current_theme = $user_settings['theme'] ?? 'default';

// Fetch messages
$messages_stmt = $pdo->query("
    SELECT
        m.id as message_id, m.content, m.created_at, m.reply_to_message_id,
        u.id as user_id, u.username, u.chat_color, u.role as author_role, u.message_count,
        replied_msg.content AS replied_content,
        replied_user.username AS replied_username
    FROM messages m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN messages replied_msg ON m.reply_to_message_id = replied_msg.id
    LEFT JOIN users replied_user ON replied_msg.user_id = replied_user.id
    ORDER BY m.id DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat Display</title>
    <link rel="stylesheet" href="styles.css">
    
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    
    <?php if ($user_refresh_interval > 0): ?>
        <meta http-equiv="refresh" content="<?= $user_refresh_interval ?>">
    <?php endif; ?>

<style>
    /* Global Reset for Pixel-Perfection */
    * { box-sizing: border-box; }

    body {
        background-color: var(--surface);
        color: var(--text);
        font-family: 'Inter', sans-serif;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        overflow-y: auto;
        margin: 0;
    }

    .message-wrapper {
        display: flex;
        flex-direction: column;
        border-bottom: 1px solid #363a4f;
        padding: 6px 0;
    }
    
    .message {
        display: flex;
        align-items: center; /* Vertical alignment fix */
        gap: 12px;
        width: 100%;
    }

    /* Fixed width for Time so it never shifts the text */
    .time {
        color: var(--subtle-text);
        flex-shrink: 0;
        width: 42px; 
        font-size: 0.85rem;
        font-variant-numeric: tabular-nums;
    }
    
    .message-content-wrapper {
        display: flex;
        align-items: center;
        flex-grow: 1; 
        min-width: 0;
    }

    /* Fixed Gutter for Icons so Username always starts at the same X coordinate */
    .user-meta {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px; 
        flex-shrink: 0;
        margin-right: 8px;
    }

    .rank-icon, .role-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        color: #ffffff;
        text-align: center;
    }

    .user {
        flex-shrink: 0;
        font-weight: bold;
        white-space: nowrap;
        margin-right: 6px;
    }

    .content {
        word-break: break-word;
        color: #efefef;
    }

    .message-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        opacity: 0.3;
        transition: opacity 0.2s ease-in-out;
        flex-shrink: 0;
    }

    .message-wrapper:hover .message-actions {
        opacity: 1;
    }

    .reply-btn, .delete-btn {
        background: none; border: none; cursor: pointer;
        font-size: 1.1rem; padding: 0 4px;
        transition: color 0.2s;
        text-decoration: none;
    }

    .reply-btn { color: var(--primary); }
    .reply-btn:hover { color: var(--secondary); }
    .delete-btn { color: var(--error); font-weight: bold; }
    .delete-btn:hover { color: #f38ba8; }

    .reply-quote {
        background-color: rgba(0,0,0,0.2);
        border-left: 3px solid var(--primary);
        padding: 4px 12px;
        margin-left: 110px; /* Aligns quote perfectly with message start */
        margin-top: 4px;
        border-radius: 4px;
        font-size: 0.85em;
        opacity: 0.7;
    }

    .reply-quote-user { font-weight: bold; margin-right: 4px; }
    
    #delete-iframe { display: none; }
</style>
</head>
<body>
    <iframe name="delete-iframe" id="delete-iframe"></iframe>
    
    <div id="message-container">
    <?php while ($message = $messages_stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="message-wrapper">
            <div class="message">
                <span class="time"><?= htmlspecialchars(date('H:i', strtotime($message['created_at']))) ?></span>
                
                <div class="message-content-wrapper">
                    <div class="user-meta">
                        <?php
                            $user_rank = get_user_rank($message['message_count'], $ranks);
                            $rank_title = htmlspecialchars($user_rank['name'] . ' (' . number_format($message['message_count']) . ' messages)');
                        ?>
                        <span class="rank-icon" title="<?= $rank_title ?>" style="font-size: <?= $user_rank['size'] ?>;">
                            <?= $user_rank['symbol'] ?>
                        </span>

                        <span class="role-icon" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $message['author_role']))) ?>">
                            <?= ROLE_ICONS[$message['author_role']] ?? '?' ?>
                        </span>
                    </div>

                    <span class="user" style="color: <?= htmlspecialchars($message['chat_color']) ?>;">
                        <a href="view_profile.php?user=<?= urlencode($message['username']) ?>" target="_parent" style="color: inherit; text-decoration: none;">
                            <?= htmlspecialchars($message['username']) ?>
                        </a>:</span><span class="content"><?= parse_message(($message['author_role'] === 'prisoner') ? PRISONER_MESSAGES[array_rand(PRISONER_MESSAGES)] : $message['content']) ?></span>
                </div>

                <div class="message-actions">
                    <a href="form.php?reply_to=<?= $message['message_id'] ?>" target="form_frame" class="reply-btn" title="Reply">↪</a>
                    <?php
                    $can_delete = false;
                    if (ROLES[$current_user_role] >= ROLES['moderator']) {
                        if (ROLES[$current_user_role] > ROLES[$message['author_role']] || $message['user_id'] == $current_user_id) {
                            $can_delete = true;
                        }
                    }
                    ?>
                    <?php if ($can_delete): ?>
                        <form method="POST" action="index.php" target="delete-iframe" style="display: inline; margin:0; padding:0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="delete_message" value="<?= $message['message_id'] ?>">
                            <button type="submit" class="delete-btn" title="Delete message">×</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message['reply_to_message_id'] && $message['replied_content']): ?>
                <div class="reply-quote">
                    <span class="reply-quote-user"><?= htmlspecialchars($message['replied_username']) ?>:</span>
                    <span><?= htmlspecialchars(mb_strimwidth($message['replied_content'], 0, 70, "...")) ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
    </div>
</body>
</html>