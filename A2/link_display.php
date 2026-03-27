<?php

declare(strict_types=1);

// Includes config.php, initializes theme ($current_theme) and CSRF ($csrf_token)
// and establishes $sqlite_pdo for the links database.
require_once 'common.php';

// =======================================================
// 1. ACCESS CONTROL AND SETUP
// =======================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

// =======================================================
// 2. DATA FETCHING
// =======================================================
try {
    // Fetch all categories for the forms and display (needed for the display section)
    $categories = $sqlite_pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all links, joined with category name
    $links_stmt = $sqlite_pdo->query("
        SELECT l.title, l.url, c.name AS category_name
        FROM links l
        JOIN categories c ON l.category_id = c.id
        ORDER BY c.name, l.title
    ");
    $links_by_category = [];
    while ($link = $links_stmt->fetch(PDO::FETCH_ASSOC)) {
        // Group links by their category name
        $links_by_category[$link['category_name']][] = $link;
    }
} catch (PDOException $e) {
    // This handles the case where the links.db or tables don't exist yet
    $error_message = 'Warning: Database issue while fetching links. ' . htmlspecialchars($e->getMessage());
    $categories = [];
    $links_by_category = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Useful Links</title>
    
    <link rel="stylesheet" href="styles.css">
    <?php if (isset($current_theme) && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
        
        /* THEME FIX: Removed the hardcoded :root block that was overriding theme variables. */
        
        body {
            font-family: 'Inter', sans-serif;
            /* THEME FIX: Use theme variables */
            background: var(--background); 
            color: var(--text);
            padding: 2rem 1rem;
            margin: 0;
            overflow-y: auto;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
            padding: 2.5rem 3rem;
            /* THEME FIX: Use theme variables */
            background-color: var(--surface);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            /* THEME FIX: Use theme variables */
            border: 1px solid var(--border, #363a4f);
            border-radius: 16px;
        }
        
        h1 {
            /* THEME FIX: Use theme variables */
            color: var(--primary);
            text-align: center;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        
        /* SCROLL THING 1: Links Section is now internally scrollable */
        .links-section {
            margin-top: 2rem;
            padding-bottom: 1.5rem;
            max-height: 500px; /* Max height before scroll */
            overflow-y: auto;  /* Add scrollbar when content exceeds max-height */
            padding-right: 15px; /* Add space for scrollbar to avoid clipping */
        }
        
        .links-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .link-category h2 {
            font-size: 1.5rem;
            /* THEME FIX: Use theme variables */
            color: var(--surface); /* Assuming category text is surface or inverted text */
            background-color: var(--primary);
            padding: 12px 18px;
            margin: 0 0 12px 0;
            border-radius: 8px 8px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .link-item {
            display: block;
            /* THEME FIX: Use theme variables */
            background-color: var(--surface);
            border: 1px solid var(--border, #363a4f);
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 10px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s ease;
        }
        
        .link-item:hover {
            /* THEME FIX: Use theme variables or a derived color */
            background-color: var(--hover-surface, #363a4f); 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .link-item .title {
            font-weight: 600;
            /* THEME FIX: Use theme variables */
            color: var(--primary);
            font-size: 1.1rem;
            display: block;
            margin-bottom: 5px;
        }
        
        .link-item .url {
            display: block;
            font-size: 0.85rem;
            /* THEME FIX: Use theme variables */
            color: var(--subtle-text, #8a8da0);
            word-break: break-all;
        }
        
        .form-section {
            margin-top: 3rem;
            padding-top: 2rem;
            /* THEME FIX: Use theme variables */
            border-top: 1px dashed var(--border, #363a4f);
        }
        
        .form-section h2 {
            /* THEME FIX: Use theme variables */
            color: var(--text);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.8rem;
        }
        
        .form-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        @media (max-width: 700px) {
            .form-container {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1.5rem;
            }
        }
        
        /* SCROLL THING 2: Form boxes are now internally scrollable */
        .form-box {
            /* THEME FIX: Use theme variables */
            background-color: var(--background);
            padding: 1.5rem;
            border-radius: 12px;
            /* THEME FIX: Use theme variables */
            border: 1px solid var(--border, #363a4f);
            /* SCROLL ADDED BACK HERE */
            max-height: 150px;
            overflow-y: auto;
        }
        
        .form-box h3 {
            /* THEME FIX: Use theme variables */
            color: var(--primary);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .info-message {
            /* THEME FIX: Use theme variables */
            color: var(--subtle-text, #8a8da0);
            text-align: center;
            font-style: italic;
            line-height: 1.5;
        }
        
        .button {
            display: inline-block;
            padding: 12px 24px;
            /* THEME FIX: Use theme variables */
            background-color: var(--primary);
            color: var(--surface); /* Assuming button text is surface or inverted text */
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            transition: background-color 0.2s, transform 0.2s;
        }
        
        .button:hover {
            /* THEME FIX: Use a slightly darker primary */
            background-color: var(--primary-darker, #5a4acd);
            transform: translateY(-2px);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 600;
            text-align: center;
        }
        
        .error-message {
            /* THEME FIX: Use theme variables for error states */
            background-color: var(--error-bg, rgba(255, 102, 102, 0.15));
            border: 1px solid var(--error-border, #ff6666);
            color: var(--error-text, #ff6666);
        }
        
        .empty-state {
            text-align: center;
            /* THEME FIX: Use theme variables */
            color: var(--subtle-text, #8a8da0);
            padding: 40px 20px;
            font-style: italic;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Links</h1>
        <p style="text-align: center; color: var(--subtle-text); margin-bottom: 0;">A collection of categorized links</p>

        <?php if (isset($error_message)): ?>
            <div class="message error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="links-section">
            <div class="links-list">
                <?php if (empty($links_by_category)): ?>
                    <div class="empty-state">
                        <p>No links have been added yet.</p>
                        <p>Use the forms below to add your first link and category.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($links_by_category as $category_name => $links): ?>
                        <div class="link-category">
                            <h2><?= htmlspecialchars($category_name) ?></h2>
                            <div>
                                <?php foreach ($links as $link): ?>
                                    <a href="<?= htmlspecialchars($link['url']) ?>" class="link-item" target="_blank" rel="noopener noreferrer">
                                        <span class="title"><?= htmlspecialchars($link['title']) ?></span>
                                        <span class="url"><?= htmlspecialchars($link['url']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section">
            <h2>Link Management</h2>
            <div class="form-container">
                <div class="form-box">
                    <h3>Add New Link</h3>
                    <p class="info-message">Link submission is disabled on this viewing page.<br>Go to the admin panel to add new links.</p>
                </div>

                <div class="form-box">
                    <h3>Add New Category</h3>
                    <p class="info-message">Category submission is disabled on this viewing page.<br>Go to the admin panel to add new categories.</p>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 3rem; text-align: center;">
            <a href="index.php" class="button">&larr; Back to Chat</a>
        </div>
    </div>
</body>
</html>