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
            <h1>🚫 Access Denied</h1>
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



// --- Action: Add Link ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim((string)($_POST['title'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    try {
        if ($category_id <= 0) throw new RuntimeException('You must select a category.');
        if ($title === '') throw new RuntimeException('Link title required.');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Valid URL required (include http/https).');
        }

        $stmt = $sqlite_pdo->prepare('INSERT INTO links(title, url, category_id) VALUES (?, ?, ?)');
        $stmt->execute([$title, $url, $category_id]);
        redirect('link_vault.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
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
<title>Add Link - Link Vault</title>

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
    main{ max-width:500px; margin:2rem auto; padding:0 1rem; }
    .card{ background:var(--background); border:1px solid var(--overlay0); border-radius:10px; padding:1.5rem; }
    h2{ margin:0 0 1rem 0; font-size:1.1rem; color:var(--primary); letter-spacing:.3px; }
    form{ display:block; margin:0; }
    label{ display:block; margin:.4rem 0 .25rem; color:var(--subtext0); font-size:.95rem; }
    
    /* Input/Select styles */
    input[type="text"], input[type="url"], select{ 
        width:100%; 
        padding:.6rem .7rem; 
        border:1px solid var(--overlay0); 
        border-radius:8px; 
        background:var(--surface); 
        color:var(--text); 
    }
    
    /* Button styles */
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
    .muted{ color:var(--subtext0); }
    .small{ font-size:.9rem; }
    footer{ text-align:center; padding:2rem; color:var(--subtext1); }
</style>
</head>
<body>

<header>
    <h1>Add New Link</h1>
    <div class="top-nav">
        <a href="link_vault.php" class="btn">← Back to Vault</a>
    </div>
</header>

<main>
    <section class="card">
        <h2>Save a Link</h2>
        <?php if (!empty($error)): ?>
            <p class="small" style="color:var(--error);"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if (empty($categories)): ?>
            <p class="muted">You must <a href="manage_categories.php">create a category</a> before you can add a link.</p>
        <?php else: ?>
            <form method="post" action="add_link.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required>
                <label for="url">URL (include http/https)</label>
                <input type="url" id="url" name="url" required>
                <label for="category_id">Category</label>
                
                <select id="category_id" name="category_id" required>
                    <option value="" disabled selected>— Select a category —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:.7rem">
                    <button class="btn" type="submit">Save Link</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>

</body>
</html>