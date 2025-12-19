<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit;
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = $_GET['with'] ?? null;

if (!$other_user_id || !is_numeric($other_user_id)) {
    http_response_code(400); exit;
}

// Mark messages from this user as read upon opening the conversation
$update_stmt = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
$update_stmt->execute([$other_user_id, $current_user_id]);

// Fetch current user's settings for theme and refresh
$stmt = $pdo->prepare("SELECT theme, refresh_interval FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
$current_theme = $user_settings['theme'] ?? 'default';
$refresh_interval = $user_settings['refresh_interval'] ?? 15;


// Fetch the conversation, newest messages first
$stmt = $pdo->prepare("
    SELECT pm.*, sender.username as sender_username, sender.chat_color as sender_color
    FROM private_messages pm
    JOIN users sender ON pm.sender_id = sender.id
    WHERE (pm.sender_id = :user1 AND pm.receiver_id = :user2)
       OR (pm.sender_id = :user2 AND pm.receiver_id = :user1)
    ORDER BY pm.created_at DESC
");
$stmt->execute([':user1' => $current_user_id, ':user2' => $other_user_id]);
$conversation = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PM Display</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <?php if ($refresh_interval > 0): ?>
        <meta http-equiv="refresh" content="<?= htmlspecialchars($refresh_interval) ?>">
    <?php endif; ?>
    <style>
        body {
            background-color: var(--background);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            padding: 1.5rem;
            margin: 0;
            overflow-y: auto; /* Enable scrolling */
        }
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .message-wrapper {
            display: flex;
            flex-direction: column;
            max-width: 75%;
        }
        .message {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            word-wrap: break-word;
            line-height: 1.5;
        }
        .message-sent {
            align-self: flex-end;
        }
        .message-sent .message {
            background-color: var(--primary);
            color: var(--background);
            border-bottom-right-radius: 2px;
        }
        .message-received {
            align-self: flex-start;
        }
        .message-received .message {
            background-color: var(--surface);
            border-bottom-left-radius: 2px;
        }
        .message-meta {
            font-size: 0.8rem;
            color: var(--subtle-text);
            margin-top: 0.25rem;
            padding: 0 0.2rem;
        }
        .message-sent .message-meta {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="messages-list">
        <?php if (empty($conversation)): ?>
            <p style="text-align: center; color: var(--subtle-text);">No messages yet. Start the conversation!</p>
        <?php else: ?>
            <?php foreach ($conversation as $msg): ?>
                <div class="message-wrapper <?= $msg['sender_id'] == $current_user_id ? 'message-sent' : 'message-received' ?>">
                    <div class="message">
                        <?= nl2br(clean_input($msg['content'])) ?>
                    </div>
                    <div class="message-meta">
                        <span style="color: <?= htmlspecialchars($msg['sender_color']) ?>; font-weight: bold;"><?= htmlspecialchars($msg['sender_username']) ?></span>
                        at <?= date('H:i', strtotime($msg['created_at'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>