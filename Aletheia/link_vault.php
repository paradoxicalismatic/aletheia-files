<?php
require_once 'common.php';
// --- ACCESS CONTROL ---
// This block checks if the user is logged in and has the required 'admin' role or higher.
// It relies on the ROLES array being defined in config.php (e.g., ['user' => 1, 'moderator' => 2, 'admin' => 3]).
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403); // Set HTTP status to "Forbidden"
    
    // Display a clear, well-styled access denied message and stop the script.
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>(403 Forbidden) Access Denied - The Onion Parlour</title>
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
                background-color: #11111b; /* Catppuccin Mocha Base */
                color: #cdd6f4; /* Catppuccin Mocha Text */
                display: flex;
                justify-content: center;
                align-items: center;
                text-align: center;
                position: relative;
            }
            .container {
                background-color: #1e1e2e; /* Catppuccin Mocha Mantle */
                padding: 2.5rem 3rem;
                border-radius: 12px;
                border: 1px solid #313244; /* Catppuccin Mocha Overlay0 */
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            }
            h1 {
                font-size: 2.5rem;
                margin: 0 0 0.5rem 0;
                color: #f38ba8; /* Catppuccin Mocha Red */
            }
            p {
                margin: 0 0 1.5rem 0;
                font-size: 1.1rem;
                color: #bac2de; /* Catppuccin Mocha Subtext1 */
            }
            .btn {
                display: inline-block;
                background-color: #89b4fa; /* Catppuccin Mocha Blue */
                color: #11111b;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                transition: background-color 0.2s ease-in-out, transform 0.2s ease;
            }
            .btn:hover {
                background-color: #a6e3a1; /* Catppuccin Mocha Green */
                transform: translateY(-2px); /* This is a CSS transition, not JavaScript */
            }
            .btn:active {
                transform: translateY(0);
            }
            footer {
                position: absolute;
                bottom: 1.5rem;
                width: 100%;
                text-align: center;
                color: #7f849c; /* Catppuccin Mocha Subtext0 */
                font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>(403 Forbidden)</h1>
            <p>You do not have the necessary permissions to view this page.</p>
            <a href="index.php" class="btn">Return to Chat</a>
        </div>
        <footer>
            The Onion Parlour
        </footer>
    </body>
    </html>
    HTML;
    exit; // Halt script execution immediately.
}
// --- END ACCESS CONTROL ---


// --- User Settings and Theme ---
// Fetch the current user's settings to apply their chosen theme
// ... (the rest of your code continues from here)
// --- Action: Delete Link ---
if (($_POST['action'] ?? '') === 'delete_link') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $sqlite_pdo->prepare('DELETE FROM links WHERE id = ?');
        $stmt->execute([$id]);
    }
    redirect('link_vault.php');
}

// --- Data for rendering ---
$categories = $sqlite_pdo->query('SELECT id, name FROM categories ORDER BY name COLLATE NOCASE')->fetchAll();
$selectedCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

if ($selectedCat > 0) {
    $stmt = $sqlite_pdo->prepare('
        SELECT l.id, l.title, l.url, l.created_at, c.name AS category
        FROM links l LEFT JOIN categories c ON c.id = l.category_id
        WHERE l.category_id = :cid
        ORDER BY l.created_at DESC');
    $stmt->execute([':cid' => $selectedCat]);
} else {
    $stmt = $sqlite_pdo->query('
        SELECT l.id, l.title, l.url, l.created_at, c.name AS category
        FROM links l LEFT JOIN categories c ON c.id = l.category_id
        ORDER BY l.created_at DESC');
}
$links = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Link Vault</title>

<?php if ($current_theme && $current_theme !== 'default'): ?>
    <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
<?php endif; ?>
<style>
    /* Default Dark Theme (Catppuccin Mocha Palette) */
    :root {
        --base: #11111b;      /* Very darkest background (Body Base) */
        --surface: #181825;   /* Main background of the page */
        --background: #1e1e2e;/* Card/Container background (Mantle) */
        --overlay0: #313244;  /* Dark Borders/Separators */
        --overlay1: #45475a;  /* Lighter Borders/Pill outlines */
        --text: #cdd6f4;      /* Primary Text */
        --subtext1: #bac2de;  /* Secondary/Meta Text */
        --subtext0: #7f849c;  /* Faded Text */
        --primary: #89b4fa;   /* Link/Accent Color (Blue) */
        --error: #f38ba8;     /* Danger/Delete Color (Red) */
    }

    /* Shared styles */
    *{ box-sizing:border-box; }
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Noto Sans","Helvetica Neue",Arial,"Apple Color Emoji","Segoe UI Emoji"; background:var(--surface); color:var(--text); }
    header{ padding:2rem 1rem; border-bottom:1px solid var(--overlay0); text-align:center; }
    header h1{ margin:.2rem 0; font-weight:600; }
    .top-nav{ display:flex; justify-content:center; gap:.75rem; margin-top:1rem; }
    main{ max-width:1200px; margin:2rem auto; padding:0 1rem; }
    .card{ background:var(--background); border:1px solid var(--overlay0); border-radius:10px; padding:1.5rem; }
    h2{ margin:0 0 .75rem 0; font-size:1.1rem; color:var(--primary); letter-spacing:.3px; }
    .small{ font-size:.9rem; }
    footer{ text-align:center; padding:2rem; color:var(--subtext1); }

    /* Tactile button styles */
    .btn {
        display: inline-block;
        /* CHANGED: Darker background and border */
        background: var(--mantle);
        color: var(--text);
        border: 1px solid var(--overlay0);
        border-bottom: 3px solid var(--overlay0);
        border-radius: 8px;
        padding: .55rem .8rem;
        text-decoration: none;
        cursor: pointer;
        font-size: 1em;
        font-weight: 500;
        position: relative;
        top: 0;
        transition: all 0.1s ease-in-out;
    }
    .btn:hover {
        /* CHANGED: Even darker hover state */
        background: var(--base);
        top: -2px;
    }
    .btn:active {
        top: 1px;
        border-bottom-width: 1px;
        background: var(--mantle);
    }
    .danger {
        background: transparent;
        color: var(--error);
        border-color: var(--error);
    }
    .danger:hover { top: -2px; background: var(--error); color: var(--background); }
    .danger:active { top: 1px; background: var(--error) !important; color: var(--background) !important; }

    /* Decorated grid and card styles */
    .grid{ display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; }
    .link{
        /* CHANGED: Background is now a much darker shade */
        background-color: var(--base);
        border: 1px solid var(--overlay0);
        border-radius:10px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        min-height: 150px;
    }
    .link:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.12); }
    .link-title { font-weight: 600; margin-top: 0.5rem; margin-bottom: 0.5rem; }
    .link-title a{ color:var(--text); text-decoration:none; word-break: break-word; }
    .link-title a:hover{ text-decoration:underline; color: var(--primary); }
    .meta{ display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--overlay0); margin-top: auto; color:var(--subtext0); font-size:.85rem; }
    .category-pill { display: inline-block; padding: .2rem .6rem; background-color: var(--surface); border: 1px solid var(--overlay1); border-radius: 999px; font-size: .8rem; font-weight: 500; color: var(--subtext1); }
    
    .filters{ display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1rem; }
    .filters a{ text-decoration:none; }
    .pill{
        display:inline-block;
        padding:.2rem .5rem;
        border:1px solid var(--overlay1);
        border-radius:999px;
        color:var(--subtext1);
        font-size:.8rem;
        background: var(--mantle);
        transition: background 0.2s ease;
    }
    .pill:hover { background: var(--surface); color: var(--text); }
    .empty{ color:var(--subtext0); padding:.5rem 0; }
</style>
</head>
<body>

<header>
    <h1>Link Vault</h1>
    <div class="top-nav">
        <a href="index.php" class="btn">Back to Chat</a>
        <a href="add_link.php" class="btn">Add New Link</a>
        <a href="manage_categories.php" class="btn">Manage Categories</a>
    </div>
</header>

<main>
    <section class="card">
        <h2>Your Links</h2>
        <div class="filters small">
            <a class="pill" href="link_vault.php">All</a>
            <?php foreach ($categories as $c): ?>
                <a class="pill" href="link_vault.php?cat=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (count($links) === 0): ?>
            <p class="empty">No links saved yet.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($links as $l): ?>
                    <div class="link">
                        <div>
                            <div class="category-pill"><?= h($l['category'] ?? 'uncategorized') ?></div>
                            <div class="link-title">
                                <a href="<?= h($l['url']) ?>" target="_blank" rel="noopener noreferrer">🔗 <?= h($l['title']) ?></a>
                            </div>
                        </div>
                        <div class="meta">
                            <span><?= h(date('M d, Y', strtotime($l['created_at']))) ?></span>
                            <form method="post" action="link_vault.php" style="margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                <input type="hidden" name="action" value="delete_link">
                                <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                <button class="btn small danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer style="text-align:center; padding:2rem; color:var(--subtext1);">
    <span class="small">The Onion Parlour</span>
</footer>

</body>
</html>