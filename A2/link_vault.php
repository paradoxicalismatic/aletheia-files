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
        <title>(403 Forbidden) Access Denied</title>
        <style>
            html, body { height: 100%; margin: 0; padding: 0; background-color: #11111b; color: #cdd6f4; display: flex; justify-content: center; align-items: center; font-family: sans-serif; }
            .container { background-color: #1e1e2e; padding: 2.5rem; border-radius: 12px; border: 1px solid #313244; text-align: center; }
            h1 { color: #f38ba8; margin-bottom: 1rem; }
            .btn { display: inline-block; background-color: #89b4fa; color: #11111b; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>(403 Forbidden)</h1>
            <p>Access Denied: Admin permissions required.</p>
            <a href="index.php" class="btn">Return to Chat</a>
        </div>
    </body>
    </html>
HTML;
    exit;
}

// Fetch the current user's theme (already handled in common.php, but re-asserting here for safety)
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn() ?: 'default';
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

// --- Data for rendering (FIXED: Removed NOCASE for MariaDB) ---
$categories = $sqlite_pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
$selectedCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

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
    body { font-family: 'Inter', sans-serif; background: var(--background); color: var(--text); min-height: 100vh; overflow-y: auto; }
    header { padding: 1.5rem 1rem; border-bottom: 2px solid var(--border); text-align: center; background: var(--surface); display: flex; flex-direction: column; align-items: center; }
    header h1 { margin: 0 0 1rem 0; color: var(--primary); font-size: 1.5rem; }
    .top-nav { display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap; }
    main { max-width: 1200px; margin: 1.5rem auto; padding: 0 1rem 2rem 1rem; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; }
    .btn { display: inline-block; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 0.8rem; text-decoration: none; cursor: pointer; font-size: 0.9rem; font-weight: 600; }
    .btn:hover { border-color: var(--primary); transform: translateY(-2px); transition: 0.2s; }
    .danger:hover { background: var(--error); color: white; border-color: var(--error); }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; margin-top: 1rem; }
    .link { background-color: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 1.2rem; display: flex; flex-direction: column; justify-content: space-between; min-height: 160px; }
    .link:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .category-pill { display: inline-block; padding: 0.2rem 0.6rem; background: var(--surface); border: 1px solid var(--border); border-radius: 999px; font-size: 0.75rem; margin-bottom: 0.5rem; }
    .meta { display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; border-top: 1px solid var(--border); margin-top: auto; font-size: 0.8rem; }
    .filters { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .pill { padding: 0.4rem 0.8rem; border: 1px solid var(--border); border-radius: 20px; text-decoration: none; color: var(--text); font-size: 0.8rem; }
    .pill:hover { background: var(--primary); color: var(--background); border-color: var(--primary); }
    .info-box { position: absolute; bottom: 125%; left: 50%; transform: translateX(-50%); background: var(--surface); padding: 12px; border-radius: 8px; width: 240px; z-index: 100; border: 2px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
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
        <div class="filters">
            <a class="pill" href="link_vault.php">All</a>
            <?php foreach ($categories as $c): ?>
                <a class="pill" href="link_vault.php?cat=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($links)): ?>
            <p style="text-align: center; padding: 3rem; opacity: 0.6;">No links found.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($links as $l): ?>
                    <div class="link">
                        <div>
                            <div class="category-pill"><?= h($l['category'] ?? 'Uncategorized') ?></div>
                            <div style="font-weight: bold; margin: 0.5rem 0;">
                                <a href="<?= h($l['url']) ?>" target="_blank" style="color:var(--primary); text-decoration:none;">🔗 <?= h($l['title']) ?></a>
                            </div>
                        </div>
                        <div class="meta">
                            <span><?= date('M d, Y', strtotime($l['created_at'])) ?></span>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <details style="position:relative;">
                                    <summary style="cursor:pointer; list-style:none; font-size:1.1rem;" title="Details">🛈</summary>
                                    <div class="info-box">
                                        <strong>Added by:</strong> <?= h($l['added_by'] ?? 'System') ?><br><br>
                                        <?= !empty($l['description']) ? h($l['description']) : '<em>No description.</em>' ?>
                                    </div>
                                </details>
                                <a href="edit_link.php?id=<?= (int)$l['id'] ?>" class="btn" style="padding: 0.2rem 0.5rem;">Edit</a>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this?');">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                    <input type="hidden" name="action" value="delete_link">
                                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                    <button type="submit" class="btn danger" style="padding: 0.2rem 0.5rem;">Del</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<footer><?= SITE_NAME ?></footer>
</body>
</html>