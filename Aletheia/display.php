<?php
require_once 'config.php';
// NOTE: Assuming config.php defines $pdo, $_SESSION, format_message_content(), 
// ROLES, ROLE_ICONS, PRISONER_MESSAGES, etc.

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// --- RANK DEFINITIONS ---
// Ranks are defined from highest message requirement to lowest.
// YOU CAN CUSTOMIZE THE 'size' VALUE (in pixels) FOR EACH RANK HERE.
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

// This function determines the correct rank for a user based on their message count.
function get_user_rank($message_count, $ranks) {
    foreach ($ranks as $rank) {
        if ($message_count >= $rank['messages']) {
            return $rank;
        }
    }
    // Default to the lowest rank if something goes wrong.
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

$user_refresh_interval = $user_settings['refresh_interval'] ?? 5;
$current_theme = $user_settings['theme'] ?? 'default';

// The query now fetches the user's message_count to determine their rank.
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
        <meta http-equiv="refresh" content="<?= htmlspecialchars($user_refresh_interval) ?>">
    <?php endif; ?>

<style>
    body {
        background-color: var(--surface);
        color: var(--text);
        font-family: 'Inter', sans-serif;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        overflow-y: auto;
    }
    .message-wrapper {
        display: flex;
        flex-direction: column;
        border-bottom: 1px solid #363a4f;
        padding-bottom: 0.3rem;
    }
    
    /* MODIFICATION 1: Revert alignment to baseline to vertically center time/actions */
    .message {
        display: flex;
        align-items: baseline; 
        gap: 0.75rem;
    }
    .time {
        color: var(--subtle-text);
        flex-shrink: 0; 
    }
    
    /* Ensures text wrapping beneath the username/icons */
    .message-content-wrapper {
        flex-grow: 1; 
    }
    .content {
        word-break: break-word;
    }
    .user {
        flex-shrink: 0;
        display: inline-block; 
    }
    .message-actions {
        display: flex;
        align-items: center;
        opacity: 0.7;
        transition: opacity 0.2s ease-in-out;
    }
    .message-wrapper:hover .message-actions {
        opacity: 1;
    }
    .reply-btn, .delete-btn {
        background: none; border: none; cursor: pointer;
        font-size: 1.1rem; padding: 0 0.5rem;
        transition: color 0.2s;
    }
    .reply-btn { color: var(--primary); }
    .reply-btn:hover { color: var(--secondary); }
    .delete-btn { color: var(--error); }
    .delete-btn:hover { color: #f38ba8; }
    
    /* --- STYLES FOR RANK AND ROLE ICONS --- */
    .rank-icon, .role-icon {
        display: inline-block; 
        margin-right: 0.15rem; 
        vertical-align: middle;
        color: #ffffff; 
    }
    
    /* KEPT: Position the role icon 10px higher */
    .role-icon {
        position: relative;
        top: -1px; 
    }
    /* --------------------------------------------- */

    .reply-quote {
        background-color: var(--background);
        border-left: 3px solid var(--primary);
        padding: 0.25rem 0.75rem;
        margin-left: 3.5rem;
        margin-top: 0.2rem;
        border-radius: 4px;
        font-size: 0.9em;
        opacity: 0.8;
    }
    .reply-quote-user { font-weight: bold; }
</style>
</head>
<body>
    <div id="message-container">
    <?php while ($message = $messages_stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <div class="message-wrapper">
            <div class="message" data-message-id="<?= $message['message_id'] ?>">
                <span class="time"><?= htmlspecialchars(date('H:i', strtotime($message['created_at']))) ?></span>
                
                <div class="message-content-wrapper">
                    <span class="user" style="color: <?= htmlspecialchars($message['chat_color']) ?>;">
                        
                        <?php
                            // Determine the user's current rank based on their message count.
                            $user_rank = get_user_rank($message['message_count'], $ranks);
                            // Create the hover text (title attribute) for the rank symbol.
                            $rank_title = htmlspecialchars($user_rank['name'] . ' (' . number_format($message['message_count']) . ' messages)');
                        ?>

                        <span class="rank-icon" title="<?= $rank_title ?>" style="font-size: <?= $user_rank['size'] ?>;">
                            <?= $user_rank['symbol'] ?>
                        </span>

                        <span class="role-icon" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $message['author_role']))) ?>">
                            <?= ROLE_ICONS[$message['author_role']] ?? '?' ?>
                        </span>

                        <a href="view_profile.php?user=<?= urlencode($message['username']) ?>" target="_parent" style="color: inherit; text-decoration: none;">
                            <?= htmlspecialchars($message['username']) ?>
                        </a>:</span><span class="content"><?= format_message_content(($message['author_role'] === 'prisoner') ? PRISONER_MESSAGES[array_rand(PRISONER_MESSAGES)] : $message['content']) ?></span>
                </div>

                <div class="message-actions">
                    <a href="form.php?reply_to=<?= $message['message_id'] ?>" target="form_frame" class="reply-btn" title="Reply">↪</a>
                    <?php
                    // Logic to determine if the user can delete the message
                    $can_delete = false;
                    // Assuming ROLES array and a 'moderator' key are defined in config.php
                    if (ROLES[$current_user_role] >= ROLES['moderator']) {
                        if (ROLES[$current_user_role] > ROLES[$message['author_role']] || $message['user_id'] == $current_user_id) {
                            $can_delete = true;
                        }
                    }
                    ?>
                    <?php if ($can_delete): ?>
                        <form method="POST" action="index.php" target="_parent" style="display: inline;">
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