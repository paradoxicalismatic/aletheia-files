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

// Handle sending a new PM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['private_message'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // CSRF token mismatch
    } else {
        $message_content = trim($_POST['private_message']);
        if (!empty($message_content)) {
            $stmt = $pdo->prepare("INSERT INTO private_messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
            $stmt->execute([$current_user_id, $other_user_id, $message_content]);
            // Redirect to the same page to prevent form resubmission and clear the input
            header("Location: pm_form.php?with=" . $other_user_id);
            exit;
        }
    }
}

// Fetch current user's theme for styling
$stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_theme = $stmt->fetchColumn();

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PM Form</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body {
            background-color: var(--surface);
            margin: 0;
            padding: 0;
        }
        .pm-form-container {
            padding: 1.5rem;
        }
        .pm-form {
            display: flex;
            gap: 1rem;
        }
        .pm-form input[type="text"] {
            flex-grow: 1;
            padding: 1rem;
            border: 1px solid #363a4f;
            border-radius: 8px;
            background-color: var(--background);
            color: var(--text);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }
        .pm-form input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
        }
        .pm-form button {
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>
    <div class="pm-form-container">
        <form method="POST" action="pm_form.php?with=<?= $other_user_id ?>" class="pm-form" target="_self">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="text" name="private_message" placeholder="Type a message..." autocomplete="off" autofocus required>
            <button type="submit" class="button">Send</button>
        </form>
    </div>
</body>
</html>