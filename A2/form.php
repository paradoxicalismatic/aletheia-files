<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit;
}

// Define character limits per role
$role_limits = [
    'owner' => PHP_INT_MAX, // No limit
    'admin' => 50000,
    'moderator' => 20000,
    'senior_member' => 5000,
    'member' => 5000,
    'guest' => 2000,
    'prisoner' => 500
];

$current_role = $_SESSION['user_role'];
$char_limit = $role_limits[$current_role] ?? 500;

// Fetch user's theme to style the form
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Check if user wants multiline mode
$multiline_mode = isset($_GET['multiline']) ? (bool)$_GET['multiline'] : false;

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

// --- BAD WORD FILTER ---
// ADD YOUR BAD WORDS HERE (one per line, lowercase):
$bad_words = [
    'badword1',
    'badword2',
    'badword3',
    // Add more bad words here
];

function contains_bad_words($message, $bad_words) {
    $message_lower = mb_strtolower($message, 'UTF-8');
    foreach ($bad_words as $bad_word) {
        if (stripos($message_lower, $bad_word) !== false) {
            return true;
        }
    }
    return false;
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
        
        // No cooldown for senior_member and above
        $bypass_cooldown = ROLES[$_SESSION['user_role']] >= ROLES['senior_member'];

        // Get the reply ID from the form submission
        $reply_to_message_id = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : null;

        if (empty($message)) {
            $error_message = "Message cannot be empty.";
        } elseif (mb_strlen($message, 'UTF-8') > $char_limit) {
            $error_message = "Message is too long (max " . number_format($char_limit) . " chars).";
        } elseif (ROLES[$_SESSION['user_role']] < ROLES['admin'] && contains_bad_words($message, $bad_words)) {
            $error_message = "Your message contains prohibited words.";
        } elseif (!$bypass_cooldown && ($current_time - $last_message_time) < $cooldown_period) {
            $error_message = "Please wait " . ($cooldown_period - ($current_time - $last_message_time)) . " seconds.";
        } else {
            try {
                $pdo->beginTransaction();

                // The INSERT statement now includes the reply_to_message_id
                $stmt = $pdo->prepare("INSERT INTO messages (user_id, content, reply_to_message_id) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $message, $reply_to_message_id]);
                
                // Update message count
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
                
                // Redirect to clear the form instantly
                $redirect_url = 'form.php';
                if ($multiline_mode) {
                    $redirect_url .= '?multiline=1';
                }
                header("Location: $redirect_url");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error_message = "Database error: could not send message.";
            }
        }
    }
}

generate_csrf_token();

// Build the base URL for form actions
$form_action = 'form.php';
if ($multiline_mode) {
    $form_action .= '?multiline=1';
}

// Build the multiline toggle URL
$toggle_url = 'form.php';
if ($reply_to_id) {
    $toggle_url .= '?reply_to=' . $reply_to_id;
    if (!$multiline_mode) {
        $toggle_url .= '&multiline=1';
    }
} else {
    if (!$multiline_mode) {
        $toggle_url .= '?multiline=1';
    }
}
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
            overflow: hidden;
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
            padding: 0.1rem;
            box-sizing: border-box;
            justify-content: center;
        }
        .error-message {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: var(--error);
            color: var(--surface);
            border-radius: 4px;
            flex-shrink: 0;
        }
        .replying-to-notice {
            position: relative;
            top: 20px;
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
        .replying-to-notice .reply-content {
            flex: 1;
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
            gap: 0.5rem;
        }
        .message-form input[type="text"],
        .message-form textarea {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background-color: var(--background);
            color: var(--text);
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
        }
        .message-form textarea {
            min-height: 80px;
            max-height: 200px;
        }
        .message-form button,
        .message-form a.button {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .message-form button[type="submit"] {
            background-color: var(--primary);
            color: var(--surface);
            padding: 0.75rem 1.5rem;
        }
        .message-form button[type="submit"]:hover {
            background-color: var(--secondary);
        }
        .toggle-multiline-btn {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            background-color: var(--background);
            color: var(--text);
            font-size: 1.2rem;
            line-height: 1;
        }
        .toggle-multiline-btn:hover {
            background-color: var(--surface);
            border-color: var(--primary);
        }
    </style>
</head>
<body onload="document.querySelector('input[name=message], textarea[name=message]').focus();">
<div class="message-form-wrapper">
    <?php if ($error_message): ?>
        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <?php if ($reply_info): 
        $display_content = mb_substr($reply_info['content'], 0, 100, 'UTF-8');
        $needs_ellipsis = (mb_strlen($reply_info['content'], 'UTF-8') > 100);
        $cancel_url = 'form.php';
        if ($multiline_mode) {
            $cancel_url .= '?multiline=1';
        }
    ?>
        <div class="replying-to-notice">
            <span class="reply-content">
                Replying to <strong><?= htmlspecialchars($reply_info['username']) ?></strong>: 
                <?= htmlspecialchars($display_content) ?><?php if ($needs_ellipsis): ?>...<?php endif; ?>
            </span>
            <a href="<?= htmlspecialchars($cancel_url) ?>" target="_self">[Cancel]</a>
        </div>
    <?php endif; ?>
  
    <form method="POST" action="<?= htmlspecialchars($form_action) ?>" target="_self" class="message-form" onsubmit="setTimeout(() => { this.querySelector('input[name=message], textarea[name=message]').value = ''; }, 0);">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <?php if ($reply_to_id): ?>
            <input type="hidden" name="reply_to_message_id" value="<?= htmlspecialchars($reply_to_id) ?>">
        <?php endif; ?>
        
        <?php if ($multiline_mode): ?>
            <textarea name="message" placeholder="Type your message..." required maxlength="<?= $char_limit ?>" autofocus></textarea>
        <?php else: ?>
            <input type="text" name="message" placeholder="Type your message..." required maxlength="<?= $char_limit ?>" autocomplete="off" autofocus>
        <?php endif; ?>
        
        <a href="<?= htmlspecialchars($toggle_url) ?>" target="_self" class="button toggle-multiline-btn" title="Toggle multi-line input">⇅</a>
        
        <button type="submit">Send</button>
    </form>
</div>
</body>
</html>