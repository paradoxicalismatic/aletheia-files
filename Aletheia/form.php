<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit;
}

// Fetch user's theme to style the form
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Check if this is a reply to another message
$reply_to_id = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : null;
$reply_info = null;
if ($reply_to_id) {
    // Fetch the original message to show context
    $stmt = $pdo->prepare("SELECT m.content, u.username FROM messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
    $stmt->execute([$reply_to_id]);
    $reply_info = $stmt->fetch(PDO::FETCH_ASSOC);
    // If the message being replied to doesn't exist, cancel the reply
    if (!$reply_info) {
        $reply_to_id = null;
    }
}


$error_message = '';

// Process a new message if one was posted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'Security token mismatch. Please try again.';
    } else {
        $message = trim($_POST['message']);
        $last_message_time = $_SESSION['last_message_time'] ?? 0;
        $current_time = time();
        $cooldown_period = 5;

        // Get the reply ID from the form submission
        $reply_to_message_id = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : null;

        if (empty($message)) {
            $error_message = "Message cannot be empty.";
        } elseif (mb_strlen($message, 'UTF-8') > 500) {
            $error_message = "Message is too long (max 500 chars).";
        } elseif (($current_time - $last_message_time) < $cooldown_period) {
            $error_message = "Please wait " . ($cooldown_period - ($current_time - $last_message_time)) . " seconds.";
        } else {
            try {
                $pdo->beginTransaction();

                // The INSERT statement now includes the reply_to_message_id
                $stmt = $pdo->prepare("INSERT INTO messages (user_id, content, reply_to_message_id) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $message, $reply_to_message_id]);
                
                // ... (rest of the logic for message count and promotion is the same)
                $stmt = $pdo->prepare("UPDATE users SET message_count = message_count + 1 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $stmt = $pdo->prepare("SELECT role, message_count FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_data = $stmt->fetch();
                if ($user_data['role'] === 'member' && $user_data['message_count'] >= 1000) {
                    $promo_stmt = $pdo->prepare("UPDATE users SET role = 'senior_member' WHERE id = ?");
                    $promo_stmt->execute([$_SESSION['user_id']]);
                }
                $pdo->commit();
                
                $_SESSION['last_message_time'] = $current_time;
                
                header("Location: form.php");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Database error: could not send message.";
            }
        }
    }
}

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat Form</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevent scrollbars inside the iframe */
        }
        body {
            background-color: var(--surface);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .message-form-wrapper {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
            padding: 0.1rem; /* MODIFIED: Reduced padding */
            box-sizing: border-box;
            justify-content: center;
        }
.replying-to-notice {
            /* 1. Set position to relative */
            position: relative;
            /* 2. Push it up by 20px (opposite of margin-top)
                  This negates the space created by the padding/margin above it,
                  pulling it into the margin of the element above it */
            top: 20px;
            
            /* Remove margin-top if you added it previously */
            /* margin-top: 20px; <--- REMOVE THIS LINE */
            
            /* Keep existing minimal settings to reduce internal spacing */
            padding: 0.1rem 0.1rem; 
            margin-bottom: 0.1rem;
            background-color: var(--background);
            border-left: 3px solid var(--primary);
            border-radius: 4px;
            font-size: 0.85em;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            color: var(--primary);
        }
        .replying-to-notice strong {
             color: var(--text);
        }
        .replying-to-notice a {
            color: var(--subtle-text);
            text-decoration: none;
            font-weight: bold;
        }
        .message-form {
            display: flex;
            width: 100%;
        }

        
    </style>
</head>
<body>
<div class="message-form-wrapper">
    <?php if ($error_message): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

<?php if ($reply_info): 
        // Truncate content to 100 characters
        $display_content = mb_substr($reply_info['content'], 0, 100, 'UTF-8');
        // Check if the content was actually truncated
        $needs_ellipsis = (mb_strlen($reply_info['content'], 'UTF-8') > 100);
    ?>
        <div class="replying-to-notice">
            <span>Replying to 
                <strong><?= htmlspecialchars($reply_info['username']) ?></strong>: 
                <?= htmlspecialchars($display_content) ?>
                <?php if ($needs_ellipsis): ?>...<?php endif; ?>
            </span>
            <a href="form.php" target="_self">[Cancel]</a>
        </div>
    <?php endif; ?>
  
    <form method="POST" action="form.php" target="_self" class="message-form">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <?php if ($reply_to_id): ?>
            <input type="hidden" name="reply_to_message_id" value="<?= htmlspecialchars($reply_to_id) ?>">
        <?php endif; ?>
        <input type="text" name="message" placeholder="Type your message..." required maxlength="500" autocomplete="off" autofocus>
        <button type="submit">Send</button>
    </form>
</div>
</body>
</html>