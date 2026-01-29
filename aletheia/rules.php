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
    <title>Site Rules - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 3rem 1rem;
            background: linear-gradient(135deg, var(--background) 0%, #181825 100%);
            color: var(--text);
        }
        .container {
            padding: 2.5rem 3rem;
            background-color: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 900px;
            width: 100%;
            border: 1px solid #363a4f;
            max-height: 90vh; /* Set a max height */
            overflow-y: auto; /* Allow vertical scrolling */
        }
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 0.5rem 0;
            letter-spacing: -1px;
        }
        .header p {
            font-size: 1.1rem;
            color: var(--subtle-text);
            max-width: 600px;
            margin: auto;
        }
        .rules-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .rule-item {
            background-color: var(--background);
            border: 1px solid #363a4f;
            border-left: 4px solid var(--primary);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            transition: all 0.3s ease;
        }
        .rule-item:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: var(--primary);
        }
        .rule-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(140, 120, 233, 0.1);
            border-radius: 8px;
        }
        .rule-icon svg {
            width: 24px;
            height: 24px;
            stroke: var(--primary);
        }
        .rule-content {
            flex-grow: 1;
        }
        .rule-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 0.5rem 0;
        }
        .rule-description {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--subtle-text);
            margin: 0;
        }
        .button-container {
            margin-top: 3rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Site Rules</h1>
            <p>To ensure a safe and welcoming environment for everyone, please follow these rules.</p>
        </div>

        <div class="rules-list">
            <div class="rule-item">
                <div class="rule-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg></div>
                <div class="rule-content">
                    <h2 class="rule-title">No Child Pornography</h2>
                    <p class="rule-description">Don't send links that contain CP, or any other links with CP.</p>
                </div>
            </div>
            
            <div class="rule-item">
                <div class="rule-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
                <div class="rule-content">
                    <h2 class="rule-title">Be Respectful</h2>
                    <p class="rule-description">Do not harass, threaten, or personally attack other users.</p>
                </div>
            </div>

            <div class="rule-item">
                <div class="rule-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></div>
                <div class="rule-content">
                    <h2 class="rule-title">No Spamming</h2>
                    <p class="rule-description">Do not send repetitive messages, advertisements, or links. This includes overusing caps or emojis, which disrupts the chat for others.</p>
                </div>
            </div>


        </div>

        <div class="button-container">
            <a href="index.php" class="button">&larr; Back to Chat</a>
        </div>
    </div>
</body>
</html>