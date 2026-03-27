<?php
declare(strict_types=1);

require_once 'config.php';

// --- User Settings and Theme ---
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_row = $stmt->fetch(PDO::FETCH_ASSOC); // Changed variable name for clarity
        $user_settings = $user_row ?: [];
    } catch (PDOException $e) {
        $user_settings = []; 
    }
} else {
    $user_settings = [];
}
$current_theme = $user_settings['theme'] ?? 'default';

// --- Username Sync ---
if (isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_row) {
            $_SESSION['username'] = $user_row['username'];
        }
    } catch (PDOException $e) {
        // Fail silently
    }
}

// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!function_exists('csrf_verify')) {
    function csrf_verify(): void {
        if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>Invalid CSRF token.</p>');
        }
    }
}

// --- Database for Links (MariaDB) ---
try {
    // Fixed syntax: Attributes must be inside the try block, catch must be separate.
    $sqlite_pdo = new PDO('mysql:host=localhost;dbname=links;charset=utf8mb4', 'chatuser', 'eKy.z4M_');
    $sqlite_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlite_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Helpers ---
if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never {
        header('Location: ' . $path);
        exit;
    }
}