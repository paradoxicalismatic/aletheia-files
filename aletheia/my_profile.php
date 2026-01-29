<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Fetch the current user's theme
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}

// Calculate user stats and achievements
$stats_query = "
    SELECT 
        u.id,
        u.username,
        u.chat_color,
        u.role,
        u.created_at,
        COUNT(DISTINCT m.id) as message_count,
        COUNT(DISTINCT DATE(m.created_at)) as days_active,
        (SELECT COUNT(*) FROM private_messages WHERE sender_id = u.id) as pms_sent,
        JULIANDAY('now') - JULIANDAY(u.created_at) as days_member
    FROM users u
    LEFT JOIN messages m ON m.user_id = u.id
    WHERE u.id = ?
    GROUP BY u.id
";

$stmt = $pdo->prepare($stats_query);
$stmt->execute([$current_user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Achievement logic
$achievements = [];

// Message milestones
if ($user_stats['message_count'] >= 1000) $achievements[] = ['icon' => '💬', 'name' => 'Chatterbox Legend', 'desc' => '1000+ messages'];
elseif ($user_stats['message_count'] >= 500) $achievements[] = ['icon' => '💬', 'name' => 'Super Chatter', 'desc' => '500+ messages'];
elseif ($user_stats['message_count'] >= 100) $achievements[] = ['icon' => '💬', 'name' => 'Conversationalist', 'desc' => '100+ messages'];
elseif ($user_stats['message_count'] >= 10) $achievements[] = ['icon' => '💬', 'name' => 'First Words', 'desc' => '10+ messages'];

// Activity milestones
if ($user_stats['days_active'] >= 100) $achievements[] = ['icon' => '🔥', 'name' => 'Century Club', 'desc' => '100+ active days'];
elseif ($user_stats['days_active'] >= 30) $achievements[] = ['icon' => '🔥', 'name' => 'Monthly Regular', 'desc' => '30+ active days'];
elseif ($user_stats['days_active'] >= 7) $achievements[] = ['icon' => '🔥', 'name' => 'Weekly Visitor', 'desc' => '7+ active days'];

// Membership milestones
if ($user_stats['days_member'] >= 365) $achievements[] = ['icon' => '🎂', 'name' => 'Veteran', 'desc' => '1+ year member'];
elseif ($user_stats['days_member'] >= 180) $achievements[] = ['icon' => '🎂', 'name' => 'Regular', 'desc' => '6+ months member'];
elseif ($user_stats['days_member'] >= 30) $achievements[] = ['icon' => '🎂', 'name' => 'Established', 'desc' => '1+ month member'];

// Role achievements
if (ROLES[$user_stats['role']] >= ROLES['moderator']) {
    $achievements[] = ['icon' => '🛡️', 'name' => 'Staff Member', 'desc' => 'Part of the team'];
}

// PM achievements
if ($user_stats['pms_sent'] >= 100) $achievements[] = ['icon' => '📨', 'name' => 'Social Butterfly', 'desc' => '100+ PMs sent'];
elseif ($user_stats['pms_sent'] >= 25) $achievements[] = ['icon' => '📨', 'name' => 'Connector', 'desc' => '25+ PMs sent'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile & Stats - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--background);
            font-family: 'Monaco', 'Courier New', 'Consolas', monospace;
            color: var(--text);
            min-height: 100vh;
            padding: 2rem;
            overflow-y: scroll;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background);
            border-left: 1px solid var(--border);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--surface);
            border: 1px solid var(--border);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        /* Firefox scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--surface) var(--background);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        h1 {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: normal;
        }

        h1::before {
            content: '$ ';
            color: var(--primary);
        }

        .back-link {
            color: var(--primary);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
            transition: all 0.2s;
        }

        .back-link::before {
            content: '< ';
            color: var(--primary);
        }

        .back-link:hover {
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(137, 180, 250, 0.3);
        }

        .profile-card {
            background: var(--surface);
            border: 2px solid var(--border);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .profile-card::before {
            content: '╔═══════════════════════════════════════════════════════════════════╗';
            display: block;
            color: var(--border);
            font-size: 0.8rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .profile-card::after {
            content: '╚═══════════════════════════════════════════════════════════════════╝';
            display: block;
            color: var(--border);
            font-size: 0.8rem;
            margin-top: 1rem;
            overflow: hidden;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .role-badge {
            font-size: 3rem;
        }

        .username {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .username::before {
            content: 'USER: ';
            color: var(--subtle-text);
            font-size: 0.8rem;
        }

        .member-since {
            color: var(--subtle-text);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .member-since::before {
            content: '> ';
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 1.25rem;
            transition: all 0.2s;
            position: relative;
        }

        .stat-card::before {
            content: '┌─────────────────┐';
            display: block;
            color: var(--border);
            font-size: 0.7rem;
            margin-bottom: 0.5rem;
        }

        .stat-card::after {
            content: '└─────────────────┘';
            display: block;
            color: var(--border);
            font-size: 0.7rem;
            margin-top: 0.5rem;
        }

        .stat-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 12px rgba(137, 180, 250, 0.3);
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--subtle-text);
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: normal;
            margin-bottom: 1.5rem;
            color: var(--text);
            padding-left: 1rem;
            border-left: 3px solid var(--primary);
        }

        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .achievement {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
            position: relative;
        }

        .achievement::before {
            content: '>';
            position: absolute;
            left: 0.5rem;
            color: var(--primary);
            opacity: 0;
            transition: all 0.2s;
        }

        .achievement:hover {
            border-color: var(--primary);
            padding-left: 1.5rem;
            box-shadow: 0 0 12px rgba(137, 180, 250, 0.3);
        }

        .achievement:hover::before {
            opacity: 1;
        }

        .achievement-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .achievement-info {
            flex: 1;
        }

        .achievement-name {
            font-weight: bold;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .achievement-desc {
            color: var(--subtle-text);
            font-size: 0.75rem;
        }

        .progress-section {
            background: var(--surface);
            border: 2px solid var(--border);
            padding: 1.5rem;
        }

        .progress-item {
            margin-bottom: 1.25rem;
        }

        .progress-item:last-child {
            margin-bottom: 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .progress-label::before {
            content: '[';
            color: var(--primary);
        }

        .progress-label::after {
            content: ']';
            color: var(--primary);
        }

        .progress-value {
            color: var(--primary);
            font-weight: bold;
        }

        .progress-bar {
            height: 20px;
            background: var(--background);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            position: relative;
            transition: width 0.3s;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 2px,
                rgba(255, 255, 255, 0.1) 2px,
                rgba(255, 255, 255, 0.1) 4px
            );
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--subtle-text);
            border: 1px dashed var(--border);
            background: var(--surface);
        }

        .empty-state::before {
            content: '[ ! ] ';
            color: var(--primary);
        }

        /* Scan line effect */
        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(transparent, var(--primary), transparent);
            opacity: 0.2;
            animation: scan 4s linear infinite;
            pointer-events: none;
            z-index: 9999;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .achievements-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Profile</h1>
            <a href="index.php" class="back-link" target="_parent">← Back</a>
        </div>

        <div class="profile-card">
            <div class="profile-header">
                <span class="role-badge"><?= ROLE_ICONS[$user_stats['role']] ?? '👤' ?></span>
                <div>
                    <div class="username" style="color: <?= htmlspecialchars($user_stats['chat_color']) ?>;">
                        <?= htmlspecialchars($user_stats['username']) ?>
                    </div>
                    <div class="member-since">
                        Member since <?= date('F j, Y', strtotime($user_stats['created_at'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-value"><?= number_format($user_stats['message_count']) ?></div>
                <div class="stat-label">Messages Sent</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🔥</div>
                <div class="stat-value"><?= number_format($user_stats['days_active']) ?></div>
                <div class="stat-label">Active Days</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📨</div>
                <div class="stat-value"><?= number_format($user_stats['pms_sent']) ?></div>
                <div class="stat-label">PMs Sent</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= floor($user_stats['days_member']) ?></div>
                <div class="stat-label">Days as Member</div>
            </div>
        </div>

        <h2 class="section-title">🏆 Achievements (<?= count($achievements) ?>)</h2>
        
        <?php if (empty($achievements)): ?>
            <div class="empty-state">
                Keep chatting to unlock achievements!
            </div>
        <?php else: ?>
            <div class="achievements-grid">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="achievement">
                        <div class="achievement-icon"><?= $achievement['icon'] ?></div>
                        <div class="achievement-info">
                            <div class="achievement-name"><?= htmlspecialchars($achievement['name']) ?></div>
                            <div class="achievement-desc"><?= htmlspecialchars($achievement['desc']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">📊 Progress to Next Milestones</h2>
        
        <div class="progress-section">
            <?php
            // Next message milestone
            $next_msg_milestone = 10;
            if ($user_stats['message_count'] >= 10) $next_msg_milestone = 100;
            if ($user_stats['message_count'] >= 100) $next_msg_milestone = 500;
            if ($user_stats['message_count'] >= 500) $next_msg_milestone = 1000;
            if ($user_stats['message_count'] >= 1000) $next_msg_milestone = 2000;
            
            $msg_progress = min(100, ($user_stats['message_count'] / $next_msg_milestone) * 100);
            ?>
            <div class="progress-item">
                <div class="progress-header">
                    <span class="progress-label">💬 Messages to <?= number_format($next_msg_milestone) ?></span>
                    <span class="progress-value"><?= number_format($user_stats['message_count']) ?> / <?= number_format($next_msg_milestone) ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $msg_progress ?>%;"></div>
                </div>
            </div>

            <?php
            // Next activity milestone
            $next_days_milestone = 7;
            if ($user_stats['days_active'] >= 7) $next_days_milestone = 30;
            if ($user_stats['days_active'] >= 30) $next_days_milestone = 100;
            if ($user_stats['days_active'] >= 100) $next_days_milestone = 365;
            
            $days_progress = min(100, ($user_stats['days_active'] / $next_days_milestone) * 100);
            ?>
            <div class="progress-item">
                <div class="progress-header">
                    <span class="progress-label">🔥 Active Days to <?= $next_days_milestone ?></span>
                    <span class="progress-value"><?= $user_stats['days_active'] ?> / <?= $next_days_milestone ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $days_progress ?>%;"></div>
                </div>
            </div>

            <?php
            // Next PM milestone
            $next_pm_milestone = 25;
            if ($user_stats['pms_sent'] >= 25) $next_pm_milestone = 100;
            if ($user_stats['pms_sent'] >= 100) $next_pm_milestone = 250;
            
            $pm_progress = min(100, ($user_stats['pms_sent'] / $next_pm_milestone) * 100);
            ?>
            <div class="progress-item">
                <div class="progress-header">
                    <span class="progress-label">📨 PMs to <?= $next_pm_milestone ?></span>
                    <span class="progress-value"><?= $user_stats['pms_sent'] ?> / <?= $next_pm_milestone ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $pm_progress ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>