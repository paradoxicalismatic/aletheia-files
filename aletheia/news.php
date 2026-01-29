<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

// Fetch the current user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Updates - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 3rem 1rem;
            background-color: var(--background);
            color: var(--text);
        }
        .container {
            padding: 2.5rem;
            background-color: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 900px;
            width: 100%;
            border: 1px solid #363a4f;
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .article {
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #363a4f;
        }
        .article:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .article h2 {
            color: var(--primary);
            margin-top: 0;
        }
        .article-meta {
            font-size: 0.9rem;
            color: var(--subtle-text);
            margin-bottom: 1rem;
        }
        .article-content p {
            line-height: 1.7;
            margin-bottom: 1rem;
        }
        .button-container {
            margin-top: 2.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>News & Updates</h1>

        <div class="article">
            <h2>Test!</h2>
            <p class="article-meta">Posted on August 10, 2025</p>
            <div class="article-content">
                <p>This is a test announcement.</p>
            </div>
        </div>


        <div class="button-container">
            <a href="index.php" class="button">&larr; Back to Chat</a>
        </div>
    </div>
</body>
</html>