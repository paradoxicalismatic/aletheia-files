<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch the current user's theme for styling the container
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Get search term to pass to the iframe
$search_term = $_GET['search'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Users</title>
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
            flex-direction: column;
            overflow: hidden;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 1.5rem;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            flex-grow: 1; /* Allows container to fill space */
        }
        h1 {
            color: var(--primary);
            margin: 0 0 1rem 0;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
        }
        .search-form {
            margin-bottom: 1rem;
        }
        .search-form input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 6px;
            border: 1px solid #363a4f;
            background-color: var(--background);
            color: var(--text);
            box-sizing: border-box;
            font-size: 1rem;
        }
        .search-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .users-display-frame {
            flex-grow: 1; /* Allows iframe to fill space */
            border: 1px solid #363a4f;
            border-radius: 8px;
            background-color: var(--surface);
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link" target="_parent">&larr; Back to Chat</a>
    <h1>All Users</h1>
    
    <form method="GET" action="all_users.php" class="search-form">
        <input type="text" name="search" placeholder="Search and press Enter..." value="<?= htmlspecialchars($search_term) ?>" autocomplete="off">
    </form>

    <iframe 
        src="all_users_display.php?search=<?= htmlspecialchars($search_term) ?>" 
        class="users-display-frame"
        frameborder="0">
    </iframe>
</div>
</body>
</html>
