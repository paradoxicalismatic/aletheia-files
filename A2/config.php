<?php
declare(strict_types=1);

// ========================
// SECURITY & DB CONFIG
// ========================
define('SITE_NAME', 'Aletheia');
define('DB_FILE', __DIR__ . '/chat.db');

// WARNING: CHANGE THIS PEPPER TO A LONG, RANDOM STRING FOR PRODUCTION!
// It is used in conjunction with user passwords.
define('PEPPER', 'ASDIYtB8DB67AI8NOHLDA72ùe$^dqm^µqsmµ^$^p-)çcijzadkbvjh!"é§!èç!"ôzwartemensen');

// Error reporting (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Secure sessions
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// ========================
// ROLE DEFINITIONS
// ========================
define('ROLES', [
    'prisoner' => 0,
    'guest' => 1,
    'member' => 2,
    'senior_member' => 3,
    'moderator' => 4,
    'admin' => 5,
    'owner' => 6
]);

define('ROLE_ICONS', [
    'prisoner' => '⛓️',
    'guest' => '👤',
    'member' => '⚡',
    'senior_member' => '🧑‍🎓',
    'moderator' => '💎',
    'admin' => '🛡️',
    'owner' => '🧛'
]);


// Messages for the 'prisoner' role
define('PRISONER_MESSAGES', [
    "I'm so pathetic",
    "I hope you are feeling great today!",
    "Please forgive me.",
    "I have learned my lesson.",
    "What's your favorite color?",
    "I wish I could be like you all.",
    "Have a wonderful day!",
    "I'm sorry for what I did.",
    "I'm trying to be a better person."
]);



// ========================
// DATABASE SETUP (SQLite)
// ========================
try {
    $pdo = new PDO("mysql:host=localhost;dbname=chat;charset=utf8mb4", "chatuser", "eKy.z4M_");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 💡 FIX: Increase the database lock timeout to 10 seconds (10000ms)
    // This addresses the "Database is locked" error by making processes wait.
    // Enable foreign key constraints
    // Create tables if they don't exist
// Initialize lockdown setting if it doesn't exist
    $pdo->exec("INSERT IGNORE INTO site_settings (`key`, value) VALUES ('lockdown_status', '0');");

    // Fetch lockdown status and define it globally
    $lockdown_stmt = $pdo->query("SELECT value FROM site_settings WHERE `key` = 'lockdown_status'");
    $lockdown_status = $lockdown_stmt->fetchColumn();
    define('LOCKDOWN_ENABLED', $lockdown_status === '1');


    // Add columns if they don't exist (legacy support)
    // Set 'Nonsensical' as Owner (Only runs if user exists)
    $owner_stmt = $pdo->prepare("UPDATE users SET role = 'owner' WHERE username = 'Nonsensical'");
    $owner_stmt->execute();


    // KICK/BAN CHECK (runs on every page load for logged-in users)
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        
        // Check for active kick
        $kick_check_stmt = $pdo->prepare("SELECT id FROM kicks WHERE user_id = ? AND expires_at > CURRENT_TIMESTAMP");
        $kick_check_stmt->execute([$user_id]);
        if ($kick_check_stmt->fetch()) {
            session_destroy();
            header("Location: guest_login.php?kicked=1");
            exit;
        }

        // Check for ban
        $ban_check_stmt = $pdo->prepare("SELECT id FROM banned_users WHERE user_id = ?");
        $ban_check_stmt->execute([$user_id]);
        if ($ban_check_stmt->fetch()) {
            session_destroy();
            header("Location: guest_login.php?banned=1");
            exit;
        }

        // Update user's last activity timestamp and fetch their current role
        // Wrap in a transaction to be safe from locks
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user_id]);
            $role_stmt = $pdo->prepare("SELECT role, chat_color, refresh_interval, theme FROM users WHERE id = ?");
            $role_stmt->execute([$user_id]);
            $user_data = $role_stmt->fetch(PDO::FETCH_ASSOC);
            $pdo->commit();
            
            if ($user_data) {
                $_SESSION['user_role'] = $user_data['role'];
                // Update session with other common settings for quick access
                $_SESSION['chat_color'] = $user_data['chat_color'];
                $_SESSION['refresh_interval'] = (int)$user_data['refresh_interval'];
                $_SESSION['theme'] = $user_data['theme'];
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Allow page to load but log the issue
            error_log("Failed to update activity/fetch role: " . $e->getMessage());
        }
    }

} catch (PDOException $e) {
    // ⚠️ CRITICAL: Fatal error if DB connection fails
    die("Database error: " . $e->getMessage());
}

// ========================
// HELPER FUNCTIONS
// ========================

/**
 * Logs a user action to the user_logs table.
 * @param string $action The action performed (e.g., 'login', 'logout', 'ban').
 * @param string|null $details Optional additional information (e.g., reason for ban).
 */
function log_user_action(string $action, ?string $details = null): void {
    global $pdo; 
    
    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

        try {
            // Use transaction for reliable log writing
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO user_logs (user_id, username, action, details, ip_address) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$_SESSION['user_id'], 
                $_SESSION['username'],
                $action,
                $details,
                $ip_address
            ]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Failed to write to user_logs: " . $e->getMessage());
        }
    }
}

/**
 * Sanitizes and cleans user input.
 * @param mixed $data The input data.
 * @return string The cleaned and sanitized string.
 */
function clean_input(mixed $data): string {
    if (!is_scalar($data)) {
        return '';
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Formats message content with BBCode and links.
 * @param string $text The raw message content.
 * @param string $user_role The role of the message sender for permission checks.
 * @return string The formatted HTML.
 */
function format_message_content(string $text, string $user_role = 'guest'): string {
    // Sanitize first
    $safe_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // --- NOTICE BBCODE LOGIC ---
    // Check if the user is a Moderator or higher
    global $ROLES;
    $can_use_notice = ($ROLES[$user_role] ?? 0) >= ($ROLES['moderator'] ?? 4);

    if ($can_use_notice) {
        // Replacement for authorized users: creates the red warning box
        $notice_html = '<div class="notice-box">⚠️ ' . SITE_NAME . ' WARNING: $1</div>';
        $safe_text = preg_replace('/\[notice\](.*?)\[\/notice\]/is', $notice_html, $safe_text);
    } else {
        // Replacement for unauthorized users: strips the tag but leaves the content
        $safe_text = preg_replace('/\[notice\](.*?)\[\/notice\]/is', '$1', $safe_text);
    }
    // --- END NOTICE BBCODE LOGIC ---
    
    // Formatting: Mentions, Bold, Italic, Strikethrough, Code
    $safe_text = preg_replace('/@(\w+)/', '<span class="mention">@$1</span>', $safe_text);
    $safe_text = preg_replace('/(?<!\w)\*(\S(.*?)\S?)\*(?!\w)/s', '<b>$1</b>', $safe_text);
    $safe_text = preg_replace('/(?<!\w)_(\S(.*?)\S?)_(?!\w)/s', '<i>$1</i>', $safe_text);
    $safe_text = preg_replace('/(?<!\w)~(\S(.*?)\S?)~(?!\w)/s', '<s>$1</s>', $safe_text);
    $safe_text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $safe_text);
    
    return nl2br($safe_text);
}

/**
 * Generates and returns a CSRF token.
 * @return string The CSRF token.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies the CSRF token from a POST request.
 * Exits with a 403 error on failure.
 */
function csrf_verify(): void {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Invalid CSRF token.</p>');
    }
}

/**
 * Fetches the role of a user by ID.
 * @param int $user_id The ID of the user.
 * @return string The user's role, or 'guest' if not found.
 */
function get_user_role(int $user_id): string {
    global $pdo;
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'guest';
}

/**
 * Sets a random CAPTCHA text in the session.
 */
function set_captcha_text(): void {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $text = '';
    for ($i = 0; $i < 5; $i++) {
        // Use a more secure random number generator if possible (PHP 7+)
        $text .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha_text'] = $text;
}
