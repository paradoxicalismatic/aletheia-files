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
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
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
$current_user_role = $_SESSION['user_role'];
$feedback = '';
$feedback_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    $user_id_to_action = $_POST['user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id_to_action]);
    $target_user_role = $stmt->fetchColumn();

    if ($target_user_role) {
        if (isset($_POST['delete_user'])) {
            // NEW PERMISSION: Only the Owner can delete users.
            $can_delete = ($current_user_role === 'owner' && $user_id_to_action != $current_user_id);

            if ($can_delete) {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE member_codes SET created_by = NULL WHERE created_by = ?")->execute([$user_id_to_action]);
                    $pdo->prepare("UPDATE member_codes SET used_by = NULL WHERE used_by = ?")->execute([$user_id_to_action]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id_to_action]);
                    $pdo->commit();
                    $feedback = "User deleted successfully.";
                    $feedback_type = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $feedback = "Database error during deletion: " . $e->getMessage();
                }
            } else {
                $feedback = "You do not have permission to delete users.";
            }

        } elseif (isset($_POST['ban_user'])) {
            $can_ban = false;
            if ($current_user_role === 'owner' && $user_id_to_action != $current_user_id) {
                $can_ban = true;
            } elseif (ROLES[$current_user_role] >= ROLES['moderator'] && ROLES[$target_user_role] < ROLES['moderator']) {
                $can_ban = true;
            }

            if ($can_ban) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET role = 'prisoner' WHERE id = ?")->execute([$user_id_to_action]);
                $pdo->prepare("INSERT OR IGNORE INTO banned_users (user_id, banned_by) VALUES (?, ?)")->execute([$user_id_to_action, $current_user_id]);
                $pdo->commit();
                $feedback = "User has been banned.";
                $feedback_type = 'success';
            } else {
                $feedback = "You do not have permission to ban this user.";
            }
        }
    }
}






$search_term = trim($_GET['search'] ?? '');
// MODIFIED QUERY: Excludes users with the 'prisoner' role from the list.
$sql = "SELECT id, username, role, message_count FROM users 
        WHERE id != :current_user_id AND username LIKE :search AND role != 'prisoner'
        ORDER BY username ASC";
$users_stmt = $pdo->prepare($sql);
$users_stmt->execute([':current_user_id' => $current_user_id, ':search' => '%' . $search_term . '%']);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$theme_stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$theme_stmt->execute([$current_user_id]);
$current_theme = $theme_stmt->fetchColumn();

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: var(--background); color: var(--text); padding: 2rem; overflow-y: auto; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        h1 { color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem; margin-bottom: 2rem; }
        .feedback { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; border: 1px solid; }
        .feedback.success { background-color: rgba(166, 227, 161, 0.1); color: #a6e3a1; border-color: #a6e3a1; }
        .feedback.error { background-color: rgba(243, 139, 168, 0.1); color: #f38ba8; border-color: #f38ba8; }
        .search-form { margin-bottom: 2rem; display: flex; gap: 1rem; }
        .search-form input { flex-grow: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #363a4f; background-color: var(--surface); color: var(--text); font-size: 1rem; }
        .search-form input:focus { outline: none; border-color: var(--primary); }
        .search-form button { padding: 0.75rem 1.5rem; border-radius: 8px; border: 1px solid var(--primary); background-color: var(--primary); color: var(--background); font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease; }
        .search-form button:hover { background-color: transparent; color: var(--primary); }
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        .user-card { background-color: var(--surface); border: 1px solid #363a4f; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
        .card-header h3 { margin: 0; font-size: 1.4rem; }
        .card-body p { margin: 0; color: var(--subtle-text); }
        .card-body strong { color: var(--text); }
        .card-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid #363a4f; display: flex; gap: 1rem; justify-content: flex-end; }
        .action-btn { padding: 0.5rem 1rem; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; }
        .action-btn:hover { opacity: 0.8; }
        .btn-delete { background-color: var(--error); color: var(--background); }
        .btn-ban { background-color: #f9e2af; color: #1e1e2e; }
        .role-badge { display: inline-block; padding: 0.3rem 0.7rem; border-radius: 6px; font-weight: bold; font-size: 0.9rem; text-transform: capitalize; }
        .role-guest, .role-member, .role-senior_member { background-color: #89b4fa; color: #1e1e2e; }
        .role-moderator { background-color: #f9e2af; color: #1e1e2e; }
        .role-admin { background-color: #f38ba8; color: #1e1e2e; }
        .role-owner { background-color: #cba6f7, color: #1e1e2e; }
        .role-prisoner { background-color: #494d64; color: #cdd6f4; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>User Management</h1>
            <a href="banned.php" class="button">View Ban List</a>
            <a href="roles.php" class="button">Manage Roles</a>
        </div>
        <?php if ($feedback): ?><div class="feedback <?= $feedback_type ?>"><?= $feedback ?></div><?php endif; ?>
        <form method="GET" action="manage_users.php" class="search-form">
            <input type="text" name="search" placeholder="Search for a user..." value="<?= htmlspecialchars($search_term) ?>" autocomplete="off">
            <button type="submit">Search</button>
        </form>
        <div class="user-grid">
            <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="card-header"><h3><?= htmlspecialchars($user['username']) ?></h3></div>
                    <div class="card-body">
                        <p>Role: <span class="role-badge role-<?= str_replace(' ', '_', $user['role']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?></span></p>
                        <p>Message Count: <strong><?= htmlspecialchars($user['message_count']) ?></strong></p>
                    </div>
                    <div class="card-footer">
                        <?php
                        // Ban permissions
                        $can_ban_user = false;
                        if ($current_user_role === 'owner' && $user['id'] != $current_user_id) {
                            $can_ban_user = true;
                        } elseif (ROLES[$current_user_role] >= ROLES['moderator'] && ROLES[$user['role']] < ROLES['moderator']) {
                            $can_ban_user = true;
                        }
                        // Delete permissions
                        $can_delete_user = ($current_user_role === 'owner' && $user['id'] != $current_user_id);
                        ?>
                        <?php if ($can_ban_user): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to ban this user?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" name="ban_user" class="action-btn btn-ban">Ban</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($can_delete_user): ?>
                            <form method="POST" onsubmit="return confirm('WARNING: This is permanent. Are you absolutely sure?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" name="delete_user" class="action-btn btn-delete">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 3rem; text-align: center;"><a href="index.php" class="button">&larr; Back to Chat</a></p>
    </div>
</body>
</html>

