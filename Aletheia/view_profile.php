<?php
// view_profile.php

require_once 'config.php';

// Must be logged in to view profiles
if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

// Get the username from the URL
$username_to_view = $_GET['user'] ?? null;

if (!$username_to_view) {
    // Redirect to the main page if no user is specified
    header("Location: index.php");
    exit;
}

// Fetch the current logged-in user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default'; // Fallback theme
}

// Fetch the profile data for the requested user
try {
    $stmt = $pdo->prepare("
        SELECT username, role, message_count, bio, created_at, last_activity, chat_color
        FROM users
        WHERE username = ?
    ");
    $stmt->execute([$username_to_view]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profile_user = null; // Handle database errors
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile: <?= htmlspecialchars((string)($username_to_view ?? '')) ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body {
            background-color: var(--background);
            font-family: 'Inter', sans-serif;
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            width: 100%;
            box-sizing: border-box;
            background-color: var(--surface);
            border-radius: 8px;
            border: 1px solid #363a4f;
            color: var(--text);
        }
        h1 {
            color: var(--primary);
            margin: 0 0 1.5rem 0;
            text-align: center;
            border-bottom: 1px solid #363a4f;
            padding-bottom: 1rem;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 1rem;
        }
        .profile-grid > dt {
            font-weight: bold;
            color: var(--subtle-text);
        }
        .profile-grid > dd {
            margin: 0;
        }
        .user-bio {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #363a4f;
        }
        .user-bio h3 {
            margin: 0 0 0.5rem 0;
            color: var(--primary);
        }
        .user-bio blockquote {
            margin: 0;
            padding: 1rem;
            background-color: var(--background);
            border-left: 4px solid var(--primary);
            border-radius: 4px;
            font-style: italic;
            white-space: pre-wrap; /* Respects newlines */
        }
        .no-bio {
             color: var(--subtle-text);
        }
        .error-message {
            text-align: center;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link" target="_parent">&larr; Back to Chat</a>

    <?php if ($profile_user): ?>
        <h1 style="color: <?= htmlspecialchars($profile_user['chat_color']) ?>;">
            <?= htmlspecialchars($profile_user['username']) ?>'s Profile
        </h1>

        <dl class="profile-grid">
            <dt>Role</dt>
            <dd><?= ROLE_ICONS[$profile_user['role']] ?? '?' ?> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $profile_user['role']))) ?></dd>

            <dt>Message Count</dt>
            <dd><?= htmlspecialchars((string)$profile_user['message_count']) ?></dd>

            <dt>Join Date</dt>
            <dd><?= htmlspecialchars(date('F j, Y', strtotime($profile_user['created_at']))) ?></dd>

            <dt>Last Seen</dt>
            <dd><?= htmlspecialchars(date('F j, Y, g:i a', strtotime($profile_user['last_activity']))) ?></dd>
        </dl>

        <div class="user-bio">
            <h3>Bio</h3>
            <?php
            // Check if the bio is set and not just whitespace
            $bio_text = trim($profile_user['bio'] ?? '');
            if (!empty($bio_text)):
            ?>
                <blockquote><?= htmlspecialchars($bio_text) ?></blockquote>
            <?php else: ?>
                <p class="no-bio">This user hasn't written a bio yet.</p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="error-message">
            <h1>User Not Found</h1>
            <p>The user "<?= htmlspecialchars((string)($username_to_view ?? '')) ?>" does not exist.</p>
        </div>
    <?php endif; ?>

</div>
</body>
</html>