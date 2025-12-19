<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = $_GET['with'] ?? null;

if (!$other_user_id || !is_numeric($other_user_id) || $other_user_id == $current_user_id) {
    header("Location: all_users.php");
    exit;
}

// Fetch user data for the header
$stmt = $pdo->prepare("SELECT username, theme, chat_color FROM users WHERE id = ?");
$stmt->execute([$other_user_id]);
$other_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$other_user) {
    header("Location: all_users.php");
    exit;
}

// Fetch current user's theme to style the main page
$stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_theme = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Private Message with <?= htmlspecialchars($other_user['username']) ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body {
            background-color: var(--background);
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center; /* Center the main container */
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .pm-container {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 800px;
            height: 100%;
            background-color: var(--surface);
            border-left: 1px solid #363a4f;
            border-right: 1px solid #363a4f;
        }
        .pm-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #363a4f;
            flex-shrink: 0;
        }
        .pm-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--primary);
        }
        .back-link {
            color: var(--subtle-text);
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        .back-link:hover {
            color: var(--primary);
        }
        .messages-frame {
            flex-grow: 1;
            border: none;
        }
        .form-frame {
            flex-shrink: 0;
            height: 95px;
            border: none;
            border-top: 1px solid #363a4f;
        }
    </style>
</head>
<body>
    <div class="pm-container">
        <header class="pm-header">
            <a href="all_users.php" class="back-link" target="_parent">&larr; All Users</a>
            <h1>Chat with <span style="color: <?= htmlspecialchars($other_user['chat_color']) ?>;"><?= htmlspecialchars($other_user['username']) ?></span></h1>
        </header>

        <iframe src="pm_messages.php?with=<?= $other_user_id ?>" name="pm_messages_frame" class="messages-frame"></iframe>
        <iframe src="pm_form.php?with=<?= $other_user_id ?>" name="pm_form_frame" class="form-frame"></iframe>
    </div>
</body>
</html>