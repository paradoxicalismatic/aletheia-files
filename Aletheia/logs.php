<?php
require_once 'config.php';

// --- ACCESS CONTROL ---
// Only allow users with Moderator role or higher (ROLES['moderator'] = 4)
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['moderator']) {
    http_response_code(403);
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>(403 Forbidden) Access Denied</title>
        <style>body{background-color:#111;color:#eee;font-family:sans-serif;padding:20px;}h1{color:#f33;}</style>
    </head>
    <body>
        <h1>(403) Access Denied</h1>
        <p>You do not have permission to view the User Logs.</p>
    </body>
    </html>
    HTML;
    exit;
}

// --- FETCH LOGS ---
try {
    $stmt = $pdo->query("
        SELECT 
            id, 
            user_id, 
            username, 
            action, 
            created_at 
        FROM user_logs 
        ORDER BY created_at DESC 
        LIMIT 500
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error while fetching logs: " . $e->getMessage());
}

$current_role = $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Logs - The Onion Parlour</title>
    <style>
        /* Simple dark theme styling for readability */
        :root {
            --bg-color: #1a1b26;
            --surface-color: #24283b;
            --text-color: #c0caf5;
            --header-color: #7dcfff;
            --border-color: #414868;
            --success-color: #9ece6a;
            --error-color: #f7768e;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        
        h1 {
            color: var(--header-color);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--surface-color);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        th, td {
            border: 1px solid var(--border-color);
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: var(--border-color);
            color: var(--header-color);
            text-transform: uppercase;
            font-size: 0.9em;
        }

        tr:nth-child(even) {
            background-color: #292e42; /* Slightly darker row for contrast */
        }
        
        .action-login { color: var(--success-color); font-weight: bold; }
        .action-logout { color: var(--error-color); font-weight: bold; }
        /* Add more classes for other actions if needed */

        .note {
            margin-top: 20px;
            padding: 10px;
            border: 1px dashed var(--border-color);
            color: var(--muted);
            font-size: 0.9em;
        }

        a.button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: var(--header-color);
            color: var(--bg-color);
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <h1>User Activity Logs</h1>
    <p>Viewing latest 500 entries (Required role: <?= strtoupper($current_role) ?>).</p>
    
    <?php if (empty($logs)): ?>
        <p>No activity logs found in the database.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time (Local)</th>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    // Use a class to style common actions
                    $action_class = match ($log['action']) {
                        'login' => 'action-login',
                        'logout' => 'action-logout',
                        default => '',
                    };
                ?>
                <tr>
                    <td><?= htmlspecialchars($log['id']) ?></td>
                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><?= htmlspecialchars($log['user_id']) ?></td>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td class="<?= $action_class ?>"><?= htmlspecialchars(ucfirst($log['action'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <p class="note">Timestamps are converted to your system's **local time** for easy reading.</p>
    <a href="index.php" class="button">&larr; Back to Chat</a>

</body>
</html>