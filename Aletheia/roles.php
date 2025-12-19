<?php
require_once 'config.php';

// --- ACCESS CONTROL ---
// This block checks if the user is logged in and has the required 'admin' role or higher.
// It relies on the ROLES array being defined in config.php (e.g., ['user' => 1, 'moderator' => 2, 'admin' => 3]).
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['moderator']) {
    http_response_code(403); // Set HTTP status to "Forbidden"
    
    // Display a clear, well-styled access denied message and stop the script.
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - The Onion Parlour</title>
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

// (Feedback and POST handling logic remains the same)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $user_to_change_id = $_POST['user_id'];
        $new_role = $_POST['new_role'];
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_to_change_id]);
        $target_user_role = $stmt->fetchColumn();
        $can_manage = (ROLES[$current_user_role] > ROLES[$target_user_role]) && ($target_user_role !== 'owner');
        if ($current_user_role === 'admin' && $target_user_role === 'admin') $can_manage = false;
        if ($user_to_change_id == $current_user_id) $can_manage = false;
        $can_assign = false;
        if ($current_user_role === 'owner' && $new_role !== 'owner') $can_assign = true;
        if ($current_user_role === 'admin' && ROLES[$new_role] < ROLES['admin']) $can_assign = true;
        if ($current_user_role === 'moderator' && ROLES[$new_role] < ROLES['moderator']) $can_assign = true;
        if (!$can_manage) {
            $feedback = "You do not have permission to manage this user.";
        } elseif (!array_key_exists($new_role, ROLES)) {
            $feedback = "Invalid role specified.";
        } elseif (!$can_assign) {
            $feedback = "You do not have permission to assign this role.";
        } else {
            $update_stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $update_stmt->execute([$new_role, $user_to_change_id]);
            $feedback = "User's role has been updated successfully.";
            $feedback_type = 'success';
        }
    } else {
        $feedback = "Invalid security token. Please try again.";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if (ROLES[$current_user_role] >= ROLES['moderator']) {
            $new_code = 'MEMBER-' . strtoupper(bin2hex(random_bytes(8)));
            $stmt = $pdo->prepare("INSERT INTO member_codes (code, created_by) VALUES (?, ?)");
            $stmt->execute([$new_code, $current_user_id]);
            $feedback = "New member code generated: <strong>" . htmlspecialchars($new_code) . "</strong>";
            $feedback_type = 'success';
        } else {
            $feedback = "You do not have permission to generate codes.";
        }
    } else {
        $feedback = "Invalid security token. Please try again.";
    }
}

// Get search term from URL
$search_term = trim($_GET['search'] ?? '');

// Fetch users, filtering by search term and sorting alphabetically
$sql = "SELECT id, username, role, message_count FROM users WHERE username LIKE :search ORDER BY username ASC";
$users_stmt = $pdo->prepare($sql);
$users_stmt->execute([':search' => '%' . $search_term . '%']);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch unused member codes
$codes_stmt = $pdo->query("SELECT code, created_at FROM member_codes WHERE is_used = 0 ORDER BY created_at DESC");
$unused_codes = $codes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current user's theme for styling
$theme_stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$theme_stmt->execute([$current_user_id]);
$current_theme = $theme_stmt->fetchColumn();

generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Role Management - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--background); 
            color: var(--text); 
            padding: 2rem;
            overflow-y: auto; 
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; }
        h1, h2 { color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem; margin-bottom: 2rem; }
        .feedback { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 500; border: 1px solid; }
        .feedback.success { background-color: rgba(166, 227, 161, 0.1); color: #a6e3a1; border-color: #a6e3a1; }
        .feedback.error { background-color: rgba(243, 139, 168, 0.1); color: #f38ba8; border-color: #f38ba8; }
        .search-form { margin-bottom: 2rem; display: flex; gap: 1rem; }
        .search-form input { flex-grow: 1; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid #363a4f; background-color: var(--surface); color: var(--text); font-size: 1rem; }
        .search-form input:focus { outline: none; border-color: var(--primary); }
        .search-form button { padding: 0.75rem 1.5rem; border-radius: 8px; border: 1px solid var(--primary); background-color: var(--primary); color: var(--background); font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s ease; }
        .search-form button:hover { background-color: transparent; color: var(--primary); }
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
        .user-card { background-color: var(--surface); border: 1px solid #363a4f; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #363a4f; padding-bottom: 1rem; }
        .card-header h3 { margin: 0; font-size: 1.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-body { flex-grow: 1; }
        .card-body p { margin: 0; color: var(--subtle-text); }
        .card-body strong { color: var(--text); font-size: 1.2rem; margin-left: 0.5rem; }
        .role-badge { display: inline-block; padding: 0.3rem 0.7rem; border-radius: 6px; font-weight: bold; font-size: 0.9rem; text-transform: capitalize; }
        .role-guest { background-color: #6c7086; color: #1e1e2e; }
        .role-member { background-color: #89b4fa; color: #1e1e2e; }
        .role-senior_member { background-color: #a6e3a1; color: #1e1e2e; }
        .role-moderator { background-color: #f9e2af; color: #1e1e2e; }
        .role-admin { background-color: #f38ba8; color: #1e1e2e; }
        .role-owner { background-color: #cba6f7; color: #1e1e2e; }
        .action-form { display: flex; gap: 0.5rem; align-items: center; margin-top: 1rem; }
        .action-form select, .action-form button { padding: 0.75rem; border-radius: 8px; border: 1px solid #363a4f; background-color: var(--background); color: var(--text); font-size: 1rem; }
        .action-form select { flex-grow: 1; }
        .action-form button { cursor: pointer; font-weight: bold; background-color: var(--primary); border-color: var(--primary); color: var(--background); transition: all 0.2s ease; }
        .action-form button:hover { background-color: transparent; color: var(--primary); }
        .management-section { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; background-color: var(--surface); padding: 2rem; border-radius: 12px; border: 1px solid #363a4f; }
        .code-list { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; }
        .code-list li { background-color: var(--background); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.5rem; font-family: monospace; border-left: 3px solid var(--primary); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>Role Management</h1>
            <?php if (ROLES[$current_user_role] >= ROLES['admin']): ?>
                <a href="manage_users.php" class="button">Manage Users</a>
                <a href="banned.php" class="button">View Ban List</a>
            <?php endif; ?>
        </div>
        
        <?php if ($feedback): ?><div class="feedback <?= $feedback_type ?>"><?= $feedback ?></div><?php endif; ?>
        
        <form method="GET" action="roles.php" class="search-form">
            <input type="text" name="search" placeholder="Search by username..." value="<?= htmlspecialchars($search_term) ?>" autocomplete="off">
            <button type="submit">Search</button>
        </form>

        <div class="user-grid">
            <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="card-header">
                        <h3><?= htmlspecialchars($user['username']) ?></h3>
                        <span class="role-badge role-<?= str_replace(' ', '_', $user['role']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?></span>
                    </div>
                    <div class="card-body">
                        <p>Message Count: <strong><?= htmlspecialchars($user['message_count']) ?></strong></p>
                    </div>
                    <div class="card-footer">
                        <?php
                        $target_role = $user['role'];
                        $can_manage_role = (ROLES[$current_user_role] > ROLES[$target_role]) && ($target_role !== 'owner');
                        if ($current_user_role === 'admin' && $target_role === 'admin') $can_manage_role = false;
                        if ($user['id'] == $current_user_id) $can_manage_role = false;
                        ?>
                        <?php if ($can_manage_role): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <select name="new_role">
                                    <?php foreach (ROLES as $role_name => $role_level):
                                        $can_assign = false;
                                        if ($current_user_role === 'owner' && $role_name !== 'owner') $can_assign = true;
                                        if ($current_user_role === 'admin' && ROLES[$role_name] < ROLES['admin']) $can_assign = true;
                                        if ($current_user_role === 'moderator' && ROLES[$role_name] < ROLES['moderator']) $can_assign = true;
                                        if (!$can_assign) continue;
                                    ?>
                                        <option value="<?= $role_name ?>" <?= $user['role'] === $role_name ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $role_name)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="change_role">Set</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (ROLES[$current_user_role] >= ROLES['moderator']): ?>
            <section class="management-section">
                <div>
                    <h2>Generate Member Codes</h2>
                    <form method="POST" class="action-form" style="flex-direction: column; align-items: stretch;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" name="generate_code">Generate New Code</button>
                    </form>
                </div>
                <div>
                    <h2>Unused Codes</h2>
                    <ul class="code-list">
                        <?php if (empty($unused_codes)): ?>
                            <li>No unused codes available.</li>
                        <?php else: ?>
                            <?php foreach ($unused_codes as $code): ?>
                                <li><?= htmlspecialchars($code['code']) ?></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>
        <?php endif; ?>

        <p style="margin-top: 3rem; text-align: center;"><a href="index.php" class="button">&larr; Back to Chat</a></p>
    </div>
</body>
</html>