<?php
require_once 'config.php';

// --- ACCESS CONTROL ---
// This block checks if the user is logged in and has the required 'admin' role or higher.
// It relies on the ROLES array being defined in config.php (e.g., ['user' => 1, 'moderator' => 2, 'admin' => 3]).
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['admin']) {
    http_response_code(403); // Set HTTP status to "Forbidden"
    
    // Display a clear, well-styled access denied message and stop the script.
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>(403 Forbidden) Access Denied - The Onion Parlour</title>
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
                background-color: #11111b; /* Catppuccin Mocha Base */
                color: #cdd6f4; /* Catppuccin Mocha Text */
                display: flex;
                justify-content: center;
                align-items: center;
                text-align: center;
                position: relative;
            }
            .container {
                background-color: #1e1e2e; /* Catppuccin Mocha Mantle */
                padding: 2.5rem 3rem;
                border-radius: 12px;
                border: 1px solid #313244; /* Catppuccin Mocha Overlay0 */
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            }
            h1 {
                font-size: 2.5rem;
                margin: 0 0 0.5rem 0;
                color: #f38ba8; /* Catppuccin Mocha Red */
            }
            p {
                margin: 0 0 1.5rem 0;
                font-size: 1.1rem;
                color: #bac2de; /* Catppuccin Mocha Subtext1 */
            }
            .btn {
                display: inline-block;
                background-color: #89b4fa; /* Catppuccin Mocha Blue */
                color: #11111b;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                transition: background-color 0.2s ease-in-out, transform 0.2s ease;
            }
            .btn:hover {
                background-color: #a6e3a1; /* Catppuccin Mocha Green */
                transform: translateY(-2px); /* This is a CSS transition, not JavaScript */
            }
            footer {
                position: absolute;
                bottom: 1.5rem;
                width: 100%;
                text-align: center;
                color: #7f849c; /* Catppuccin Mocha Subtext0 */
                font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>(403 Forbidden)</h1>
            <p>You do not have the necessary permissions to view this page.</p>
            <a href="index.php" class="btn">Return to Chat</a>
        </div>
        <footer>
            The Onion Parlour
        </footer>
    </body>
    </html>
    HTML;
    exit; // Halt script execution immediately.
}
// --- END ACCESS CONTROL ---

$current_user_id = $_SESSION['user_id'];

// --- Handle Unban POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $user_id_to_unban = $_POST['user_id_to_unban'];
        
        $pdo->beginTransaction();
        // Set user role back to guest
        $pdo->prepare("UPDATE users SET role = 'guest' WHERE id = ?")->execute([$user_id_to_unban]);
        // Remove from the banned_users table
        $pdo->prepare("DELETE FROM banned_users WHERE user_id = ?")->execute([$user_id_to_unban]);
        $pdo->commit();

        // Redirect to prevent form resubmission
        header("Location: banned.php");
        exit;
    }
}

// Fetch banned users, including their ID for the unban action
$stmt = $pdo->query("
    SELECT u.id as banned_user_id, u.username as banned_user, a.username as admin_user, b.banned_at
    FROM banned_users b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN users a ON b.banned_by = a.id
    ORDER BY b.banned_at DESC
");
$banned_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$theme_stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$theme_stmt->execute([$current_user_id]);
$current_theme = $theme_stmt->fetchColumn();

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Banned Users - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: var(--background); color: var(--text); padding: 2rem; overflow-y: auto; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem; margin-bottom: 2rem; }
        .ban-table { width: 100%; border-collapse: collapse; background-color: var(--surface); border-radius: 8px; overflow: hidden; }
        .ban-table th, .ban-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--background); }
        .ban-table th { font-size: 1.1rem; }
        .ban-table tr:last-child td { border-bottom: none; }
        .ban-table tr:hover { background-color: #363a4f; }
        .no-bans { text-align: center; padding: 2rem; color: var(--subtle-text); background-color: var(--surface); border-radius: 8px; }
        .btn-unban { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; background-color: #a6e3a1; color: #1e1e2e; }
        .btn-unban:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Banned Users List</h1>
        <?php if (empty($banned_list)): ?>
            <p class="no-bans">No users are currently banned.</p>
        <?php else: ?>
            <table class="ban-table">
                <thead>
                    <tr>
                        <th>Banned User</th>
                        <th>Banned By</th>
                        <th>Date of Ban</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banned_list as $ban): ?>
                        <tr>
                            <td><?= htmlspecialchars($ban['banned_user']) ?></td>
                            <td><?= htmlspecialchars($ban['admin_user'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars(date('F j, Y, g:i a', strtotime($ban['banned_at']))) ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="user_id_to_unban" value="<?= $ban['banned_user_id'] ?>">
                                    <button type="submit" name="unban_user" class="btn-unban">Unban</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p style="margin-top: 3rem; text-align: center;">
            <a href="manage_users.php" class="button">&larr; Back to User Management</a>
        </p>
    </div>
</body>
</html>