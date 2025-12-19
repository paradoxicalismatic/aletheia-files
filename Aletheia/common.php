<?php
declare(strict_types=1);

// This ensures integration with the main app's session and configuration.
require_once 'config.php';

// --- User Settings and Theme ---
// Fetch the current user's settings to apply their chosen theme
if (isset($_SESSION['user_id'])) {
    try {
        // $pdo is from config.php, used for the main user database
        $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $user_settings = []; // Fail gracefully
    }
} else {
    $user_settings = []; // Not logged in
}
// This variable will be used in the <head> of other files
$current_theme = $user_settings['theme'] ?? 'default';


// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function csrf_verify(): void {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Invalid CSRF token.</p>';
        exit;
    }
}

// --- Storage (SQLite for Links) ---
try {
    // Use a different variable name ($sqlite_pdo) to avoid conflict with $pdo from config.php
    $sqlite_pdo = new PDO('sqlite:' . __DIR__ . '/links.db', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sqlite_pdo->exec('PRAGMA foreign_keys = ON;');

    $sqlite_pdo->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    $sqlite_pdo->exec('CREATE TABLE IF NOT EXISTS links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        url TEXT NOT NULL,
        category_id INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    )');
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Helpers ---
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}