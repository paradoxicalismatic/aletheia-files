<?php
require_once 'common.php';
$error = '';


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
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
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


// --- Actions ---
$action = $_POST['action'] ?? '';

if ($action === 'add_category') {
    csrf_verify();
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        $error = 'Category name required.';
    } else {
        try {
            $stmt = $sqlite_pdo->prepare('INSERT INTO categories(name) VALUES (?)');
            $stmt->execute([$name]);
            redirect('manage_categories.php');
        } catch (PDOException $e) {
            $error = 'That category name already exists.';
        }
    }
}

if ($action === 'delete_category') {
    csrf_verify();
    $catId = (int)($_POST['category_id'] ?? 0);
    if ($catId <= 0) {
        $error = 'Invalid category selected for deletion.';
    } else {
        $deleteLinks = isset($_POST['delete_links']) && $_POST['delete_links'] === '1';
        if ($deleteLinks) {
            $stmt = $sqlite_pdo->prepare('DELETE FROM links WHERE category_id = ?');
            $stmt->execute([$catId]);
        }
        $stmt = $sqlite_pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$catId]);
        redirect('manage_categories.php');
    }
}

// --- Data for rendering ---
$categories = $sqlite_pdo->query('SELECT id, name FROM categories ORDER BY name COLLATE NOCASE')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories - Link Vault</title>

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
        --mantle: #1e1e2e;    /* Used for buttons and inputs */
    }

    /* Shared styles for all pages */
    *{ box-sizing:border-box; }
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Noto Sans","Helvetica Neue",Arial,"Apple Color Emoji","Segoe UI Emoji"; background:var(--surface); color:var(--text); }
    header{ padding:2rem 1rem; border-bottom:1px solid var(--overlay0); text-align:center; }
    header h1{ margin:.2rem 0; font-weight:600; }
    .top-nav{ display:flex; justify-content:center; gap:.75rem; margin-top:1rem; }
    main{ max-width:600px; margin:2rem auto; padding:0 1rem; }
    .card{ background:var(--background); border:1px solid var(--overlay0); border-radius:10px; padding:1.5rem; margin-bottom: 1.25rem; }
    h2{ margin:0 0 1rem 0; font-size:1.1rem; color:var(--primary); letter-spacing:.3px; }
    form{ display:block; margin:0; }
    .row{ display:flex; gap:.5rem; }
    .row > *{ flex:1; }
    
    /* Button styles (using mantle/surface) */
    .btn{ 
        display:inline-block; 
        border:1px solid var(--overlay1); 
        background:var(--mantle); 
        color:var(--text); 
        padding:.55rem .8rem; 
        border-radius:8px; 
        text-decoration:none; 
        cursor:pointer; 
        font-size: 1em; 
        transition: background-color 0.2s ease;
    }
    .btn:hover{ background:var(--surface); }
    .danger{ border-color:var(--error); color:var(--error); }
    .danger:hover{ background:var(--error); color:var(--background); }
    
    .small{ font-size:.9rem; }
    .muted{ color:var(--subtext0); }
    
    /* Input styles (using surface) */
    input[type="text"]{ 
        width:100%; 
        padding:.6rem .7rem; 
        border:1px solid var(--overlay0); 
        border-radius:8px; 
        background:var(--surface); 
        color:var(--text); 
    }
    .list{ margin:0; padding:0; list-style:none; }
    .list li{ padding:.5rem .25rem; border-bottom:1px solid var(--overlay0); display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
    .list li:last-child{ border-bottom:none; }
    .empty{ color:var(--subtext0); padding:.5rem 0; }
    footer{ text-align:center; padding:2rem; color:var(--subtext1); }
</style>
</head>
<body>

<header>
    <h1>Manage Categories</h1>
    <div class="top-nav">
        <a href="link_vault.php" class="btn">← Back to Vault</a>
    </div>
</header>

<main>
    <section class="card">
        <h2>Add New Category</h2>
        <?php if (!empty($error)): ?>
            <p class="small" style="color:var(--error);"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post" action="manage_categories.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="add_category">
            <div class="row">
                <input type="text" id="name" name="name" placeholder="e.g. Research" required>
                <button class="btn" type="submit">Add</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Existing Categories</h2>
        <?php if (count($categories) === 0): ?>
            <p class="empty">No categories yet.</p>
        <?php else: ?>
            <ul class="list">
            <?php foreach ($categories as $c): ?>
                <li>
                    <span><?= h($c['name']) ?></span>
                    <form method="post" action="manage_categories.php" style="margin:0; text-align: right;">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                        <label class="small muted"><input type="checkbox" name="delete_links" value="1"> delete links</label>
                        <button class="btn danger small" type="submit" onclick="return confirm('Delete this category?');">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

</body>
</html>