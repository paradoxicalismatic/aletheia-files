<?php
require_once 'common.php';
$error = '';
$id = (int)($_GET['id'] ?? 0);

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403);
    exit('Access Denied');
}

// Fetch existing data
$stmt = $sqlite_pdo->prepare('SELECT * FROM links WHERE id = ?');
$stmt->execute([$id]);
$link = $stmt->fetch();

if (!$link) {
    redirect('link_vault.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim((string)($_POST['title'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    try {
        if ($category_id <= 0) throw new RuntimeException('Select a category.');
        if ($title === '') throw new RuntimeException('Title required.');
        
        $stmt = $sqlite_pdo->prepare('UPDATE links SET title = ?, url = ?, category_id = ?, description = ? WHERE id = ?');
        $stmt->execute([$title, $url, $category_id, $description, $id]);
        redirect('link_vault.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$categories = $sqlite_pdo->query('SELECT id, name FROM categories ORDER BY name COLLATE NOCASE')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Link</title>
    <style>
        :root { --base: #11111b; --surface: #181825; --background: #1e1e2e; --overlay0: #313244; --text: #cdd6f4; --primary: #89b4fa; }
        body{ font-family:system-ui,sans-serif; background:var(--surface); color:var(--text); margin:0; }
        main{ max-width:500px; margin:3rem auto; padding:0 1rem; }
        .card{ background:var(--background); border:1px solid var(--overlay0); border-radius:10px; padding:1.5rem; }
        label{ display:block; margin:1rem 0 .3rem; color:#7f849c; font-size:.9rem; }
        input, select, textarea { width:100%; padding:.7rem; border-radius:8px; border:1px solid var(--overlay0); background:var(--base); color:var(--text); font-family:inherit; }
        .btn{ display:block; width:100%; margin-top:1.5rem; padding:.8rem; background:var(--primary); color:#11111b; border:none; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; text-align:center; }
    </style>
</head>
<body>
<main>
    <section class="card">
        <h2>Edit Link</h2>
        <?php if ($error): ?><p style="color:#f38ba8"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <label>Title</label>
            <input type="text" name="title" value="<?= h($link['title']) ?>" required>
            <label>URL</label>
            <input type="url" name="url" value="<?= h($link['url']) ?>" required>
            <label>Category</label>
            <select name="category_id">
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $link['category_id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Description</label>
            <textarea name="description"><?= h($link['description'] ?? '') ?></textarea>
            <button class="btn" type="submit">Update Link</button>
            <a href="link_vault.php" style="display:block; text-align:center; margin-top:1rem; color:#7f849c;">Cancel</a>
        </form>
    </section>
</main>
</body>
</html>