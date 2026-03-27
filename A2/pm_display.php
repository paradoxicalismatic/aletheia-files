<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch current user theme
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Unread counts per sender
$unread_stmt = $pdo->prepare("SELECT sender_id, COUNT(id) as unread_count FROM private_messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id");
$unread_stmt->execute([$current_user_id]);
$unread_messages = $unread_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Last message per conversation
$last_msg_stmt = $pdo->prepare("
    SELECT other_id, content, created_at FROM (
        SELECT
            CASE WHEN sender_id = :uid1 THEN receiver_id ELSE sender_id END as other_id,
            content,
            created_at,
            ROW_NUMBER() OVER (
                PARTITION BY CASE WHEN sender_id = :uid2 THEN receiver_id ELSE sender_id END
                ORDER BY created_at DESC
            ) as rn
        FROM private_messages
        WHERE sender_id = :uid3 OR receiver_id = :uid4
    ) t WHERE rn = 1
");
$last_msg_stmt->execute([':uid1' => $current_user_id, ':uid2' => $current_user_id, ':uid3' => $current_user_id, ':uid4' => $current_user_id]);
$last_messages = [];
foreach ($last_msg_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $last_messages[$row['other_id']] = $row;
}

// Search + fetch users
$search_term = $_GET['search'] ?? '';
$sql = "
    SELECT id, username, chat_color, role,
        (CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as is_online
    FROM users
    WHERE id != :user_id AND username LIKE :search
    ORDER BY is_online DESC, username ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $current_user_id, ':search' => '%' . $search_term . '%']);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sort: unread first, then by last message time, then online, then alpha
usort($all_users, function($a, $b) use ($unread_messages, $last_messages) {
    $a_unread = isset($unread_messages[$a['id']]) ? (int)$unread_messages[$a['id']] : 0;
    $b_unread = isset($unread_messages[$b['id']]) ? (int)$unread_messages[$b['id']] : 0;
    if ($a_unread !== $b_unread) return $b_unread - $a_unread;
    $a_time = isset($last_messages[$a['id']]) ? strtotime($last_messages[$a['id']]['created_at']) : 0;
    $b_time = isset($last_messages[$b['id']]) ? strtotime($last_messages[$b['id']]['created_at']) : 0;
    if ($a_time !== $b_time) return $b_time - $a_time;
    if ($a['is_online'] !== $b['is_online']) return $b['is_online'] - $a['is_online'];
    return strcmp($a['username'], $b['username']);
});

function timeShort(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'now';
    if ($diff < 3600)   return floor($diff/60) . 'm';
    if ($diff < 86400)  return floor($diff/3600) . 'h';
    if ($diff < 604800) return floor($diff/86400) . 'd';
    return date('M j', strtotime($ts));
}

function initials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    if (count($words) >= 2) return strtoupper(substr($words[0],0,1) . substr($words[1],0,1));
    return strtoupper(substr($name, 0, 2));
}

// Deterministic color from username
function avatarColor(string $name): string {
    $colors = ['#5c6bc0','#7e57c2','#26a69a','#42a5f5','#66bb6a','#ab47bc','#ef5350','#26c6da','#d4e157','#ffa726'];
    return $colors[abs(crc32($name)) % count($colors)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <meta http-equiv="refresh" content="30">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #111116;
            color: #e8e8ec;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* ── Top bar ── */
        .topbar {
            height: 56px;
            background: #19191f;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            flex-shrink: 0;
        }

        .topbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: #e8e8ec;
            letter-spacing: 0.01em;
        }

        .topbar-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #888;
            text-decoration: none;
            font-size: 0.82rem;
            transition: color 0.15s;
        }
        .topbar-back:hover { color: #e8e8ec; }

        /* ── Search ── */
        .search-wrap {
            padding: 12px 14px;
            background: #19191f;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }

        .search-wrap form {
            display: flex;
            align-items: center;
            background: #2a2a33;
            border-radius: 8px;
            padding: 0 12px;
            gap: 8px;
        }

        .search-icon { color: #555; font-size: 0.85rem; }

        .search-wrap input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: #e8e8ec;
            font-size: 0.85rem;
            padding: 9px 0;
            font-family: inherit;
        }

        .search-wrap input::placeholder { color: #555; }

        /* ── Conversation list ── */
        .conv-list {
            flex: 1;
            overflow-y: auto;
            padding: 4px 0;
        }

        .conv-list::-webkit-scrollbar { width: 4px; }
        .conv-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

        /* ── Section label ── */
        .section-label {
            padding: 10px 18px 4px;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #444;
        }

        /* ── Conversation row ── */
        .conv-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            transition: background 0.1s;
            position: relative;
            border-radius: 6px;
            margin: 1px 6px;
        }

        .conv-row:hover { background: rgba(255,255,255,0.04); }
        .conv-row.has-unread { background: rgba(255,255,255,0.025); }

        /* ── Avatar ── */
        .avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
            position: relative;
            letter-spacing: 0.04em;
        }

        .avatar-online::after {
            content: '';
            position: absolute;
            bottom: 1px;
            right: 1px;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background: #3cb371;
            border: 2px solid #111116;
        }

        /* ── Conv body ── */
        .conv-body {
            flex: 1;
            min-width: 0;
        }

        .conv-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 3px;
        }

        .conv-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: #e8e8ec;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 160px;
        }

        .conv-time {
            font-size: 0.68rem;
            color: #555;
            flex-shrink: 0;
            margin-left: 8px;
        }

        .conv-time.recent { color: var(--primary, #7c6af7); }

        .conv-preview {
            font-size: 0.78rem;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conv-row.has-unread .conv-preview { color: #aaa; font-weight: 500; }

        /* ── Unread badge ── */
        .unread-badge {
            background: var(--primary, #7c6af7);
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 2px 7px;
            min-width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        /* ── Role badge ── */
        .role-badge {
            font-size: 0.62rem;
            color: #555;
            margin-left: 5px;
        }

        /* ── Empty state ── */
        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            color: #444;
            text-align: center;
            gap: 10px;
        }

        .empty-icon { font-size: 2.2rem; opacity: 0.4; }
        .empty-text { font-size: 0.82rem; }

        /* ── Divider ── */
        .list-divider {
            height: 1px;
            background: rgba(255,255,255,0.04);
            margin: 6px 14px;
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <span class="topbar-title">Messages</span>
    <a href="index.php" class="topbar-back" target="_parent">← back to chat</a>
</div>

<!-- Search -->
<div class="search-wrap">
    <form method="GET" action="pm_display.php">
        <span class="search-icon">⌕</span>
        <input type="text" name="search" placeholder="Search conversations..." value="<?= htmlspecialchars($search_term) ?>" autocomplete="off">
    </form>
</div>

<!-- Conversation list -->
<div class="conv-list">
    <?php if (empty($all_users)): ?>
        <div class="empty">
            <div class="empty-icon">✉</div>
            <div class="empty-text"><?= $search_term ? 'No users match your search.' : 'No users found.' ?></div>
        </div>
    <?php else: ?>

        <?php
        // Separate: has convo history vs no history
        $with_history = array_filter($all_users, fn($u) => isset($last_messages[$u['id']]));
        $no_history   = array_filter($all_users, fn($u) => !isset($last_messages[$u['id']]));
        ?>

        <?php if (!empty($with_history)): ?>
        <?php if (!$search_term): ?>
        <div class="section-label">Recent</div>
        <?php endif; ?>

        <?php foreach ($with_history as $user):
            $uid     = $user['id'];
            $unread  = (int)($unread_messages[$uid] ?? 0);
            $last    = $last_messages[$uid] ?? null;
            $preview = $last ? mb_strimwidth(strip_tags($last['content']), 0, 55, '…') : '';
            $time    = $last ? timeShort($last['created_at']) : '';
            $recent  = $last && (time() - strtotime($last['created_at'])) < 3600;
            $color   = avatarColor($user['username']);
            $initials = initials($user['username']);
        ?>
        <a href="private_message.php?with=<?= $uid ?>" target="_parent" class="conv-row <?= $unread ? 'has-unread' : '' ?>">
            <div class="avatar <?= $user['is_online'] ? 'avatar-online' : '' ?>" style="background:<?= $color ?>22;color:<?= $color ?>;">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div class="conv-body">
                <div class="conv-top">
                    <div class="conv-name" style="color:<?= htmlspecialchars($user['chat_color'] ?: '#e8e8ec') ?>">
                        <?= htmlspecialchars($user['username']) ?>
                        <span class="role-badge"><?= ROLE_ICONS[$user['role']] ?? '' ?></span>
                    </div>
                    <span class="conv-time <?= $recent ? 'recent' : '' ?>"><?= $time ?></span>
                </div>
                <div class="conv-preview"><?= htmlspecialchars($preview) ?></div>
            </div>
            <?php if ($unread): ?>
            <div class="unread-badge"><?= $unread ?></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($no_history) && !empty($with_history)): ?>
        <div class="list-divider"></div>
        <?php endif; ?>

        <?php if (!empty($no_history)): ?>
        <?php if (!$search_term && !empty($with_history)): ?>
        <div class="section-label">All Users</div>
        <?php endif; ?>

        <?php foreach ($no_history as $user):
            $uid     = $user['id'];
            $unread  = (int)($unread_messages[$uid] ?? 0);
            $color   = avatarColor($user['username']);
            $initials = initials($user['username']);
        ?>
        <a href="private_message.php?with=<?= $uid ?>" target="_parent" class="conv-row <?= $unread ? 'has-unread' : '' ?>">
            <div class="avatar <?= $user['is_online'] ? 'avatar-online' : '' ?>" style="background:<?= $color ?>22;color:<?= $color ?>;">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div class="conv-body">
                <div class="conv-top">
                    <div class="conv-name" style="color:<?= htmlspecialchars($user['chat_color'] ?: '#e8e8ec') ?>">
                        <?= htmlspecialchars($user['username']) ?>
                        <span class="role-badge"><?= ROLE_ICONS[$user['role']] ?? '' ?></span>
                    </div>
                    <?php if ($user['is_online']): ?>
                    <span class="conv-time recent">online</span>
                    <?php endif; ?>
                </div>
                <div class="conv-preview" style="color:#444;font-style:italic;">no messages yet</div>
            </div>
            <?php if ($unread): ?>
            <div class="unread-badge"><?= $unread ?></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
