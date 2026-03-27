<?php
require_once 'common.php';
$error = '';

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403);
    die("Access Denied.");
}

// Fetch the current user's theme
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn() ?: 'default';
} catch (PDOException $e) {
    $current_theme = 'default';
}

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
            header('Location: manage_categories.php');
            exit;
        } catch (PDOException $e) {
            $error = 'That category name already exists.';
        }
    }
}

if ($action === 'delete_category') {
    csrf_verify();
    $catId = (int)($_POST['category_id'] ?? 0);
    if ($catId > 0) {
        $deleteLinks = isset($_POST['delete_links']) && $_POST['delete_links'] === '1';
        if ($deleteLinks) {
            $stmt = $sqlite_pdo->prepare('DELETE FROM links WHERE category_id = ?');
            $stmt->execute([$catId]);
        }
        $stmt = $sqlite_pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$catId]);
        header('Location: manage_categories.php');
        exit;
    }
}

// --- Data for rendering (FIXED: Removed NOCASE for MariaDB) ---
$categories = $sqlite_pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        header h1 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--primary);
            letter-spacing: 1px;
        }

        main {
            max-width: 700px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        h2 {
            margin: 0 0 1.2rem 0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
        }

        .row {
            display: flex;
            gap: 10px;
        }

        input[type="text"] {
            flex: 1;
            padding: 0.75rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-family: inherit;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
            border: 1px solid var(--border);
            background: var(--background);
            color: var(--text);
        }

        .btn-primary {
            background: var(--primary);
            color: var(--background);
            border: none;
        }

        .btn:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .btn-danger {
            color: var(--error);
            border-color: var(--error);
        }

        .btn-danger:hover {
            background: var(--error);
            color: white;
        }

        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .category-item:last-child {
            border-bottom: none;
        }

        .delete-controls {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .checkbox-label {
            font-size: 0.75rem;
            color: var(--subtle-text);
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .error-msg {
            color: var(--error);
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<header>
    <h1>📁 MANAGE CATEGORIES</h1>
    <a href="link_vault.php" class="btn">← BACK TO VAULT</a>
</header>

<main>
    <section class="card">
        <h2>Add New Category</h2>
        <?php if ($error): ?>
            <div class="error-msg"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="add_category">
            <div class="row">
                <input type="text" name="name" placeholder="e.g. Documentation" required autofocus>
                <button type="submit" class="btn btn-primary">ADD</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Existing Categories</h2>
<div style="height: 150px; width: 600px; overflow: auto;">
  


        
        <?php if (empty($categories)): ?>
            <p style="text-align: center; opacity: 0.5; padding: 1rem;">No categories yet.</p>
        <?php else: ?>
            
            <ul class="category-list">
                <?php foreach ($categories as $c): ?>
                    <li class="category-item">
                        <span style="font-weight: 500;"><?= h($c['name']) ?></span>
                        <form method="POST" onsubmit="return confirm('Delete this category?');" class="delete-controls">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                            <input type="hidden" name="action" value="delete_category">
                            <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="delete_links" value="1"> 
                                cascade delete links
                            </label>
                            
                            <button type="submit" class="btn btn-danger">DELETE</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
</div>
    </section>
</main>

</body>
</html>