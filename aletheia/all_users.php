<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch the current user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Fetch unread message counts for the current user
$unread_stmt = $pdo->prepare("SELECT sender_id, COUNT(id) as unread_count FROM private_messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id");
$unread_stmt->execute([$current_user_id]);
$unread_messages = $unread_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle search and fetch all users, including roles
$search_term = $_GET['search'] ?? '';
$sql = "
    SELECT
        id,
        username,
        chat_color,
        role,
        (CASE WHEN last_activity >= datetime('now', '-5 minutes') THEN 1 ELSE 0 END) as is_online
    FROM users
    WHERE id != :user_id AND username LIKE :search
    ORDER BY is_online DESC, username ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':user_id' => $current_user_id,
    ':search' => '%' . $search_term . '%'
]);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate online and offline users
$online_users = array_filter($all_users, fn($u) => $u['is_online']);
$offline_users = array_filter($all_users, fn($u) => !$u['is_online']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Users - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <meta http-equiv="refresh" content="30">
    <style>
        
body {
            background: var(--background);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            height: 100vh; /* Changed */
            padding: 2rem;
            overflow: hidden; /* Added */
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            height: 100%; /* Added */
            display: flex; /* Added */
            flex-direction: column; /* Added */
        }

        /* Added new scroll class */
        .scroll-box {
            flex-grow: 1;
            overflow-y: auto;
            padding-right: 10px;
        }

        /* Optional: Styled scrollbar */
        .scroll-box::-webkit-scrollbar { width: 6px; }
        .scroll-box::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        h1 {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            padding: 0.625rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            transition: all 0.2s;
        }

        .back-link:hover {
            border-color: var(--primary);
            transform: translateX(-3px);
        }

        .search-form {
            margin-bottom: 2.5rem;
        }

        .search-form input {
            width: 100%;
            max-width: 500px;
            padding: 0.875rem 1.25rem;
            border-radius: 8px;
            border: 2px solid var(--border);
            background-color: var(--surface);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.2s;
        }

        .search-form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(137, 180, 250, 0.1);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-count {
            font-size: 0.875rem;
            color: var(--subtle-text);
            font-weight: 400;
        }

        .users-section {
            margin-bottom: 3rem;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .user-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
            transform: scaleX(0);
            transition: transform 0.25s;
        }

        .user-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .user-card:hover::before {
            transform: scaleX(1);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .role-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 1.125rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--subtle-text);
            text-transform: capitalize;
            margin-top: 0.125rem;
        }

        .user-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pill.online {
            background: rgba(166, 227, 161, 0.2);
            color: #a6e3a1;
        }

        .status-pill.offline {
            background: rgba(243, 139, 168, 0.2);
            color: #f38ba8;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-pill.online .status-dot {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .pm-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--background);
            border: 1px solid var(--border);
            color: var(--subtle-text);
            text-decoration: none;
            font-size: 1.25rem;
            transition: all 0.2s;
        }

        .pm-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--surface);
            transform: scale(1.1);
        }

        .pm-link.has-new {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--surface);
            animation: bounce 1.5s infinite;
            box-shadow: 0 0 12px rgba(137, 180, 250, 0.5);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0) scale(1); }
            40% { transform: translateY(-4px) scale(1.05); }
            60% { transform: translateY(-2px) scale(1.02); }
        }

        .no-users {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--subtle-text);
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: 8px;
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 2rem 0;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .users-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <h1>Private Messages</h1>
            <a href="index.php" class="back-link" target="_parent">
                <span>←</span>
                <span>Back to Chat</span>
            </a>
        </div>

        <div class="scroll-box"> <form method="GET" action="all_users.php" class="search-form">
            <input 
                type="text" 
                name="search" 
                placeholder="Search for users..." 
                value="<?= htmlspecialchars($search_term) ?>" 
                autocomplete="off"
            >
        </form>

        <?php if (empty($all_users)): ?>
            <div class="no-users">
                No users found<?= $search_term ? ' matching your search' : '' ?>.
            </div>
        <?php else: ?>
            
            <?php if (!empty($online_users)): ?>
            <div class="users-section">
                <h2 class="section-title">
                    <span>🟢 Online</span>
                    <span class="user-count">(<?= count($online_users) ?>)</span>
                </h2>
                <div class="users-grid">
                    <?php foreach ($online_users as $user): ?>
                        <div class="user-card">
                            <div class="user-header">
                                <span class="role-icon" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?>">
                                    <?= ROLE_ICONS[$user['role']] ?? '👤' ?>
                                </span>
                                <div class="user-info">
                                    <div class="user-name" style="color: <?= htmlspecialchars($user['chat_color']) ?>;">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </div>
                                    <div class="user-role">
                                        <?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="user-footer">
                                <div class="status-pill online">
                                    <span class="status-dot"></span>
                                    <span>Online</span>
                                </div>
                                <a 
                                
                                    href="private_message.php?with=<?= $user['id'] ?>" 
                                    target="_parent" 
                                    class="pm-link <?= isset($unread_messages[$user['id']]) ? 'has-new' : '' ?>"
                                    title="<?= isset($unread_messages[$user['id']]) ? 'New messages!' : 'Send message' ?>"
                                >
                                    <?= isset($unread_messages[$user['id']]) ? '📨' : '✉️' ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($offline_users)): ?>
            <?php if (!empty($online_users)): ?>
                <div class="divider"></div>
            <?php endif; ?>
            
            <div class="users-section">
                <h2 class="section-title">
                    <span>⚫ Offline</span>
                    <span class="user-count">(<?= count($offline_users) ?>)</span>
                </h2>
                <div class="users-grid">
                    <?php foreach ($offline_users as $user): ?>
                        <div class="user-card">
                            <div class="user-header">
                                
                                <span class="role-icon" title="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?>">
                                    <?= ROLE_ICONS[$user['role']] ?? '👤' ?>
                                </span>
                                <div class="user-info">
                                    <div class="user-name" style="color: <?= htmlspecialchars($user['chat_color']) ?>;">
                                        <?= htmlspecialchars($user['username']) ?>
                                    </div>
                                    <div class="user-role">
                                        <?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="user-footer">
                                <div class="status-pill offline">
                                    <span class="status-dot"></span>
                                    <span>Offline</span>
                                </div>
                                <a 
                                    href="private_message.php?with=<?= $user['id'] ?>" 
                                    target="_parent" 
                                    class="pm-link <?= isset($unread_messages[$user['id']]) ? 'has-new' : '' ?>"
                                    title="<?= isset($unread_messages[$user['id']]) ? 'New messages!' : 'Send message' ?>"
                                >
                                    <?= isset($unread_messages[$user['id']]) ? '📨' : '✉️' ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
        </div> </div>
</body>