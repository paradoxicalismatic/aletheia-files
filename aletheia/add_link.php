<?php
require_once 'common.php';
$error = '';

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403);
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>(403 Forbidden) Access Denied - The Onion Parlour</title>
        <style>
            html, body { height: 100%; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji"; background-color: #11111b; color: #cdd6f4; display: flex; justify-content: center; align-items: center; text-align: center; position: relative; }
            .container { background-color: #1e1e2e; padding: 2.5rem 3rem; border-radius: 12px; border: 1px solid #313244; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); }
            h1 { font-size: 2.5rem; margin: 0 0 0.5rem 0; color: #f38ba8; }
            p { margin: 0 0 1.5rem 0; font-size: 1.1rem; color: #bac2de; }
            .btn { display: inline-block; background-color: #89b4fa; color: #11111b; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background-color 0.2s ease-in-out, transform 0.2s ease; }
            .btn:hover { background-color: #a6e3a1; transform: translateY(-2px); }
            footer { position: absolute; bottom: 1.5rem; width: 100%; text-align: center; color: #7f849c; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🚫 Access Denied</h1>
            <p>You do not have the necessary permissions to view this page.</p>
            <a href="index.php" class="btn">Return to Chat</a>
        </div>
        <footer>The Onion Parlour</footer>
    </body>
    </html>
HTML;
    exit;
}
// --- END ACCESS CONTROL ---

// --- Action: Add Link ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim((string)($_POST['title'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    $added_by = $_SESSION['username'] ?? 'Admin';
    
    try {
        if ($category_id <= 0) throw new RuntimeException('You must select a category.');
        if ($title === '') throw new RuntimeException('Link title required.');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Valid URL required (include http/https).');
        }

        // UPDATED INSERT TO INCLUDE DESCRIPTION AND ADDED_BY
        $stmt = $sqlite_pdo->prepare('INSERT INTO links(title, url, category_id, description, added_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$title, $url, $category_id, $description, $added_by]);
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
    :root { --base: #11111b; --surface: #181825; --background: #1e1e2e; --overlay0: #313244; --overlay1: #45475a; --text: #cdd6f4; --subtext1: #bac2de; --subtext0: #7f849c; --primary: #89b4fa; --error: #f38ba8; --mantle: #1e1e2e; }
    *{ box-sizing:border-box; }
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Noto Sans","Helvetica Neue",Arial,"Apple Color Emoji","Segoe UI Emoji"; background:var(--surface); color:var(--text); }
    header{ padding:2rem 1rem; border-bottom:1px solid var(--overlay0); text-align:center; }
    header h1{ margin:.2rem 0; font-weight:600; }
    .top-nav{ display:flex; justify-content:center; gap:.75rem; margin-top:1rem; }
    main{ max-width:500px; margin:2rem auto; padding:0 1rem; }
    .card{ background:var(--background); border:1px solid var(--overlay0); border-radius:10px; padding:1.5rem; }
    h2{ margin:0 0 1rem 0; font-size:1.1rem; color:var(--primary); letter-spacing:.3px; }
    label{ display:block; margin:.4rem 0 .25rem; color:var(--subtext0); font-size:.95rem; }
    input[type="text"], input[type="url"], select, textarea { width:100%; padding:.6rem .7rem; border:1px solid var(--overlay0); border-radius:8px; background:var(--surface); color:var(--text); font-family:inherit; }
    textarea { height: 80px; resize: vertical; }
    .btn{ display:inline-block; border:1px solid var(--overlay1); background:var(--mantle); color:var(--text); padding:.55rem .8rem; border-radius:8px; text-decoration:none; cursor:pointer; font-size: 1em; transition: background-color 0.2s ease; }
    .btn:hover{ background:var(--surface); }
    .muted{ color:var(--subtext0); }
</style>
</head>
<body>
<header>
    <h1>Add New Link</h1>
    <div class="top-nav"><a href="link_vault.php" class="btn">← Back to Vault</a></div>
</header>
<main>
    <section class="card">
        <h2>Save a Link</h2>
        <?php if (!empty($error)): ?><p class="small" style="color:var(--error);"><?= h($error) ?></p><?php endif; ?>
        <?php if (empty($categories)): ?>
            <p class="muted">You must <a href="manage_categories.php">create a category</a> before you can add a link.</p>
        <?php else: ?>
            <form method="post">
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
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" placeholder="A brief note about this link..."></textarea>
                <div style="margin-top:.7rem">
                    <button class="btn" type="submit">Save Link</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>