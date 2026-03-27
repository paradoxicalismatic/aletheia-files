<?php
require_once 'common.php';
$error = '';

// --- ACCESS CONTROL ---
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403);
    die("Access Denied.");
}

// Action: Add Link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title = trim((string)($_POST['title'] ?? ''));
    $url   = trim((string)($_POST['url'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim((string)($_POST['description'] ?? ''));
    $added_by = $_SESSION['username'] ?? 'Admin';
    
    try {
        if ($category_id <= 0 || $title === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Please fill in all required fields with a valid URL.');
        }

        $stmt = $sqlite_pdo->prepare('INSERT INTO links(title, url, category_id, description, added_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$title, $url, $category_id, $description, $added_by]);
        
        header('Location: link_vault.php');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$categories = $sqlite_pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Link - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if (!empty($current_theme) && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
            display: flex;
            flex-direction: column;
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            letter-spacing: 0.5px;
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
            font-size: 0.95rem;
            transition: border-color 0.2s;
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
        }

        .btn-primary {
            background: var(--primary);
            color: var(--background);
            border: none;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
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
            background: rgba(255, 0, 0, 0.1);
            color: #ff8080;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid #ff4444;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<header>
    <h1><span>➕</span> ADD NEW LINK</h1>
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
                <input type="text" id="title" name="title" placeholder="e.g. Interesting site" required autofocus>
            </div>

            <div class="form-group">
                <label for="url">URL</label>
                <input type="url" id="url" name="url" placeholder="https://example.com" required>
            </div>

            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="" disabled selected>Select a category...</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" placeholder="What is this link for?"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">SAVE TO VAULT</button>
        </form>
    </section>
</main>

</body>
</html>