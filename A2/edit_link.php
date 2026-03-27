<?php
require_once 'common.php';
$error = '';
$id = (int)($_GET['id'] ?? 0);

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403);
    die("Access Denied.");
}

// Fetch existing data
$stmt = $sqlite_pdo->prepare('SELECT * FROM links WHERE id = ?');
$stmt->execute([$id]);
$link = $stmt->fetch();

if (!$link) {
    header('Location: link_vault.php');
    exit;
}

// Fetch user theme
try {
    $stmt_theme = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt_theme->execute([$_SESSION['user_id']]);
    $current_theme = $stmt_theme->fetchColumn() ?: 'default';
} catch (PDOException $e) {
    $current_theme = 'default';
}

// --- Action: Update Link ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim((string)($_POST['title'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    try {
        if ($category_id <= 0) throw new RuntimeException('Please select a valid category.');
        if ($title === '') throw new RuntimeException('Link title is required.');
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new RuntimeException('A valid URL is required.');
        
        $stmt_upd = $sqlite_pdo->prepare('UPDATE links SET title = ?, url = ?, category_id = ?, description = ? WHERE id = ?');
        $stmt_upd->execute([$title, $url, $category_id, $description, $id]);
        
        header('Location: link_vault.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
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
    <title>Edit Link - <?= SITE_NAME ?></title>
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
            display: flex;
            flex-direction: column;
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
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 3rem 1rem;
        }

        .card {
            width: 100%;
            max-width: 550px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--primary);
        }

        input, select, textarea {
            width: 100%;
            padding: 0.8rem;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        textarea {
            height: 120px;
            resize: vertical;
        }

        .btn {
            cursor: pointer;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--background);
            border: none;
            width: 100%;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .error-banner {
            background: rgba(243, 139, 168, 0.1);
            color: var(--error);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--error);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<header>
    <h1>✏️ EDIT LINK</h1>
    <a href="link_vault.php" class="btn btn-outline">← CANCEL</a>
</header>

<main>
    <section class="card">
        <?php if ($error): ?>
            <div class="error-banner">⚠️ <?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?= h($link['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="url">URL</label>
                <input type="url" id="url" name="url" value="<?= h($link['url']) ?>" required>
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $link['category_id'] ? 'selected' : '' ?>>
                            <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description"><?= h($link['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">UPDATE LINK</button>
        </form>
    </section>
</main>

</body>
</html>