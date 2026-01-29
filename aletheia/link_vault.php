<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'common.php';

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
            .btn:active { transform: translateY(0); }
            footer { position: absolute; bottom: 1.5rem; width: 100%; text-align: center; color: #7f849c; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>(403 Forbidden)</h1>
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

// Fetch the current user's theme
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

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

// UPDATED QUERY TO INCLUDE DESCRIPTION AND ADDED_BY
$sqlBase = 'SELECT l.*, c.name AS category FROM links l LEFT JOIN categories c ON c.id = l.category_id';
if ($selectedCat > 0) {
    $stmt = $sqlite_pdo->prepare($sqlBase . ' WHERE l.category_id = :cid ORDER BY l.created_at DESC');
    $stmt->execute([':cid' => $selectedCat]);
} else {
    $stmt = $sqlite_pdo->query($sqlBase . ' ORDER BY l.created_at DESC');
}
$links = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Link Vault - <?= SITE_NAME ?></title>
<link rel="stylesheet" href="styles.css">
<?php if ($current_theme && $current_theme !== 'default'): ?>
    <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
<?php endif; ?>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--background);
        color: var(--text);
        min-height: 100vh;
        overflow-y: auto;
    }
    
    header {
        padding: 1.5rem 1rem;
        border-bottom: 2px solid var(--border);
        text-align: center;
        background: var(--surface);
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    header h1 {
        margin: 0.2rem 0 0.75rem 0;
        font-weight: 600;
        color: var(--primary);
        font-size: 1.5rem;
    }
    
    .top-nav {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    main {
        max-width: 1200px;
        margin: 1.5rem auto;
        padding: 0 1rem 2rem 1rem;
    }
    
    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 1.5rem;
    }
    
    h2 {
        margin: 0 0 0.75rem 0;
        font-size: 1.25rem;
        color: var(--primary);
        letter-spacing: 0.3px;
    }
    
    .btn {
        display: inline-block;
        background: var(--surface);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.55rem 0.8rem;
        text-decoration: none;
        cursor: pointer;
        font-size: 1em;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    .btn:hover {
        background: var(--background);
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    
    .btn:active {
        transform: translateY(0);
    }
    
    .danger {
        color: var(--error);
        border-color: var(--error);
    }
    
    .danger:hover {
        background: var(--error);
        color: var(--surface);
    }
    
    .edit-btn {
        color: var(--primary);
        border-color: var(--primary);
    }
    
    .edit-btn:hover {
        background: var(--primary);
        color: var(--surface);
    }
    
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.25rem;
    }
    
    .link {
        background-color: var(--background);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 150px;
        transition: all 0.2s;
    }
    
    .link:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    
    .link-title {
        font-weight: 600;
        margin: 0.5rem 0;
    }
    
    .link-title a {
        color: var(--text);
        text-decoration: none;
        word-break: break-word;
    }
    
    .link-title a:hover {
        color: var(--primary);
    }
    
    .meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
        margin-top: auto;
        color: var(--subtle-text);
        font-size: 0.85rem;
    }
    
    .category-pill {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        background-color: var(--surface);
        border: 1px solid var(--border);
        border-radius: 999px;
        font-size: 0.8rem;
        color: var(--text);
    }
    
    .filters {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    
    .pill {
        display: inline-block;
        padding: 0.3rem 0.7rem;
        border: 1px solid var(--border);
        border-radius: 999px;
        color: var(--text);
        font-size: 0.8rem;
        background: var(--background);
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .pill:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--surface);
    }
    
    /* Tooltip styling for description */
    .info-toggle {
        display: inline-block;
        vertical-align: middle;
        position: relative;
    }
    
    .info-toggle summary {
        list-style: none;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0 5px;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .info-toggle summary:hover {
        opacity: 1;
        color: var(--primary);
    }
    
    .info-toggle summary::-webkit-details-marker {
        display: none;
    }
    
    .info-box {
        position: absolute;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        background: var(--surface);
        padding: 10px;
        border-radius: 8px;
        width: 220px;
        z-index: 10;
        font-size: 0.85rem;
        color: var(--text);
        border: 2px solid var(--primary);
        pointer-events: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
    }
    
    .info-toggle[open] .info-box {
        pointer-events: auto;
    }
    
    footer {
        text-align: center;
        padding: 2rem;
        color: var(--subtle-text);
    }
    
    .small {
        font-size: 0.75rem;
    }
    
    @media (max-width: 768px) {
        .grid {
            grid-template-columns: 1fr;
        }
        
        .top-nav {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
<header>
    <h1>🔗 Link Vault</h1>
    <div class="top-nav">
        <a href="index.php" class="btn">← Back to Chat</a>
        <a href="add_link.php" class="btn">➕ Add New Link</a>
        <a href="manage_categories.php" class="btn">📁 Manage Categories</a>
    </div>
</header>
<main>
    <section class="card">
        <h2>Your Links</h2>
        <div class="filters">
            <a class="pill" href="link_vault.php">All</a>
            <?php foreach ($categories as $c): ?>
                <a class="pill" href="link_vault.php?cat=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if (count($links) === 0): ?>
            <p style="color: var(--subtle-text); text-align: center; padding: 2rem;">No links saved yet.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($links as $l): ?>
                    <div class="link">
                        <div>
                            <div class="category-pill"><?= h($l['category'] ?? 'uncategorized') ?></div>
                            <div class="link-title"><a href="<?= h($l['url']) ?>" target="_blank">🔗 <?= h($l['title']) ?></a></div>
                        </div>
                        <div class="meta">
                            <span><?= h(date('M d, Y', strtotime($l['created_at']))) ?></span>
                            <div style="display:flex; gap:5px; align-items:center;">
                                <details class="info-toggle">
                                    <summary title="View Details">🛈</summary>
                                    <div class="info-box">
                                        <strong>Added by:</strong> <?= h($l['added_by'] ?? 'System') ?><br><br>
                                        <?= !empty($l['description']) ? h($l['description']) : '<em>No description.</em>' ?>
                                    </div>
                                </details>
                                <a href="edit_link.php?id=<?= (int)$l['id'] ?>" class="btn small edit-btn">Edit</a>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                    <input type="hidden" name="action" value="delete_link">
                                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                    <button class="btn small danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer><span class="small"><?= SITE_NAME ?></span></footer>
</body>
</html>