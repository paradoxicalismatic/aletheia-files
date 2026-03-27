<?php
// view_profile.php

require_once 'config.php';

// Must be logged in to view profiles
if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

// Get the username from the URL
$username_to_view = $_GET['user'] ?? null;

if (!$username_to_view) {
    // Redirect to the main page if no user is specified
    header("Location: index.php");
    exit;
}

// Fetch the current logged-in user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default'; // Fallback theme
}

// Fetch the profile data for the requested user
try {
    $stmt = $pdo->prepare("
        SELECT id, username, role, message_count, bio, created_at, last_activity, chat_color
        FROM users
        WHERE username = ?
    ");
    $stmt->execute([$username_to_view]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $profile_user = null; // Handle database errors
}

// Calculate additional stats if user exists
$total_characters = 0;
$total_words = 0;
$first_message_date = null;
$latest_message_date = null;

if ($profile_user) {
    try {
        // Get message statistics
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(LENGTH(message)), 0) as total_chars,
                MIN(timestamp) as first_msg,
                MAX(timestamp) as last_msg
            FROM messages
            WHERE user_id = ?
        ");
        $stmt->execute([$profile_user['id']]);
        $msg_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_characters = $msg_stats['total_chars'] ?? 0;
        $first_message_date = $msg_stats['first_msg'];
        $latest_message_date = $msg_stats['last_msg'];
        
        // Estimate word count (average 5 characters per word)
        $total_words = $total_characters > 0 ? round($total_characters / 5) : 0;
        
    } catch (PDOException $e) {
        // Silently fail, stats will remain at defaults
    }
}

// Helper function to format large numbers
function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}

// Helper function to calculate time since
function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Calculate days active
$days_active = 0;
if ($profile_user && $profile_user['created_at']) {
    $created = new DateTime($profile_user['created_at']);
    $now = new DateTime();
    $days_active = $created->diff($now)->days;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile: <?= htmlspecialchars((string)($username_to_view ?? '')) ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>

        
        body {


            
            background-color: var(--surface);
            color: var(--text);
            font-family: 'Monaco', 'Courier New', 'Consolas', monospace;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
        }
        
        .container {
            
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
            background-color: var(--background);
            border-radius: 6px;
            border: 2px solid var(--border);
            overflow: hidden;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4);
        }
        
        /* Terminal Header */
        .terminal-header {

            
            background: var(--surface);
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .terminal-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 9px;
            font-weight: bold;
            line-height: 1;
        }

        .dot-red { 
            background: #ff5f56; 
            color: transparent;
        }

        .terminal-header:hover .dot-red {
            color: rgba(0, 0, 0, 0.5);
        }

        .dot-red:hover {
            background: #ff7b72;
            color: rgba(0, 0, 0, 0.8) !important;
        }
        .dot-yellow { background: #ffbd2e; }
        .dot-green { background: #27c93f; }
        
        .terminal-title {
            margin-left: 15px;
            color: var(--subtle-text);
            font-size: 0.85rem;
        }
        
        .back-link {
            margin-left: auto;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: var(--secondary);
        }
        
        /* Terminal Body */
        .terminal-body {

                max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            line-height: 1.6;
        }
        
        .terminal-line {
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .prompt {
            color: var(--primary);
            font-weight: bold;
        }
        
        .command {
            color: var(--secondary);
        }
        
        .output {
            color: var(--text);
            margin-left: 20px;
            margin-top: 5px;
        }
        
        .output-header {
            color: var(--success);
            font-weight: bold;
        }
        
        .output-error {
            color: var(--error);
            font-weight: bold;
        }
        
        /* User Info Block */
        .user-info-block {
            margin: 15px 0 15px 20px;
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
        }
        
        .info-line {
            margin-bottom: 8px;
        }
        
        .info-key {
            color: var(--accent);
            font-weight: bold;
        }
        
        .info-value {
            color: var(--secondary);
            margin-left: 10px;
        }
        
        .role-icon {
            display: inline-block;
            margin-right: 5px;
        }
        
        /* Stats Grid */
        .stat-output {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0 15px 20px;
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        
        .stat-item {
            padding: 10px;
            background: var(--background);
            border-radius: 4px;
            border-left: 3px solid var(--primary);
        }
        
        .stat-term {
            color: var(--accent);
            font-weight: bold;
            display: block;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        
        .stat-term-value {
            color: var(--secondary);
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        /* Section Divider */
        .section-divider {
            border-top: 1px dashed var(--border);
            margin: 25px 0;
        }
        
        /* Bio Output */
        .bio-output {
            margin-left: 20px;
            padding: 15px 20px;
            background: var(--surface);
            border-left: 3px solid var(--primary);
            color: var(--text);
            border-radius: 4px;
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        .no-bio {
            color: var(--subtle-text);
            font-style: italic;
        }
        
        .comment {
            color: var(--subtle-text);
        }
        
        /* Error State */
        .error-container {
            padding: 40px;
            text-align: center;
        }
        
        .error-ascii {
            font-size: 0.85rem;
            color: var(--error);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        /* Responsive */
        @media (max-width: 600px) {
            .stat-output {
                grid-template-columns: 1fr;
            }
            
            .terminal-body {
                padding: 20px 15px;
            }
            
            .output {
                margin-left: 10px;
            }
            
            .user-info-block,
            .stat-output,
            .bio-output {
                margin-left: 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
<div class="terminal-header">
    <a href="index.php" class="terminal-dot dot-red">
    &times;
</a>
    <span class="terminal-dot dot-yellow"></span>
    <span class="terminal-dot dot-green"></span>
    
    <span class="terminal-title">public-user-profile.sh</span>

</div>
    
    <div class="terminal-body">
        <?php if ($profile_user): ?>
            <div class="terminal-line">
                <span class="prompt">$</span> <span class="command">./fetch_user_profile.sh --user=<?= htmlspecialchars($profile_user['username']) ?></span>
            </div>
            
            <div class="output">
                <div class="output-header">✓ Profile loaded successfully</div>
            </div>
            
            <div class="terminal-line" style="margin-top: 20px;">
                <span class="prompt">$</span> <span class="command">cat user_info.json</span>
            </div>
            
            <div class="user-info-block">
                <div class="info-line">
                    <span class="info-key">"username"</span>:<span class="info-value">"<?= htmlspecialchars($profile_user['username']) ?>"</span>
                </div>
                <div class="info-line">
                    <span class="info-key">"role"</span>:<span class="info-value">"<?= ROLE_ICONS[$profile_user['role']] ?? '?' ?> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $profile_user['role']))) ?>"</span>
                </div>
                <div class="info-line">
                    <span class="info-key">"chat_color"</span>:<span class="info-value" style="color: <?= htmlspecialchars($profile_user['chat_color']) ?>;">"<?= htmlspecialchars($profile_user['chat_color']) ?>"</span>
                </div>
                <div class="info-line">
                    <span class="info-key">"joined"</span>:<span class="info-value">"<?= htmlspecialchars(date('F j, Y', strtotime($profile_user['created_at']))) ?> (<?= timeAgo($profile_user['created_at']) ?>)"</span>
                </div>
                <div class="info-line">
                    <span class="info-key">"last_seen"</span>:<span class="info-value">"<?= htmlspecialchars(date('M j, Y g:i a', strtotime($profile_user['last_activity']))) ?> (<?= timeAgo($profile_user['last_activity']) ?>)"</span>
                </div>
                <?php if ($first_message_date): ?>
                <div class="info-line">
                    <span class="info-key">"first_message"</span>:<span class="info-value">"<?= htmlspecialchars(date('F j, Y', strtotime($first_message_date))) ?>"</span>
                </div>
                <?php endif; ?>
                <?php if ($latest_message_date): ?>
                <div class="info-line">
                    <span class="info-key">"latest_message"</span>:<span class="info-value">"<?= htmlspecialchars(date('F j, Y', strtotime($latest_message_date))) ?>"</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section-divider"></div>
            
            <div class="terminal-line">
                <span class="prompt">$</span> <span class="command">./get_stats.sh</span>
            </div>
            
            <div class="stat-output">
                <div class="stat-item">
                    <span class="stat-term">MESSAGES</span>
                    <span class="stat-term-value"><?= formatNumber($profile_user['message_count']) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-term">WORDS(coming soon)</span>
                    <span class="stat-term-value"><?= formatNumber($total_words) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-term">DAYS_ACTIVE</span>
                    <span class="stat-term-value"><?= $days_active ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-term">CHARACTERS(coming soon)</span>
                    <span class="stat-term-value"><?= formatNumber($total_characters) ?></span>
                </div>
            </div>
            
            <div class="section-divider"></div>
            
            <div class="terminal-line">
                <span class="prompt">$</span> <span class="command">cat bio.txt</span>
            </div>
            
            <?php
            $bio_text = trim($profile_user['bio'] ?? '');
            if (!empty($bio_text)):
            ?>
                <div class="bio-output"><?= htmlspecialchars($bio_text) ?></div>
            <?php else: ?>
                <div class="bio-output no-bio">cat: bio.txt: No such file or directory</div>
            <?php endif; ?>
            
            <div class="terminal-line" style="margin-top: 20px;">
                <span class="prompt">$</span> <span class="comment"># End of profile</span>
            </div>
            
        <?php else: ?>
            <div class="terminal-line">
                <span class="prompt">$</span> <span class="command">./fetch_user_profile.sh --user=<?= htmlspecialchars((string)($username_to_view ?? '')) ?></span>
            </div>
            
            <div class="output">
                <div class="output-error">✗ Error: User not found</div>
            </div>
            
            <div class="error-ascii">
     _____                     
    | ____|_ __ _ __ ___  _ __ 
    |  _| | '__| '__/ _ \| '__|
    | |___| |  | | | (_) | |   
    |_____|_|  |_|  \___/|_|   
            </div>
            
            <div class="output" style="margin-left: 0; text-align: center;">
                <p style="color: var(--subtle-text);">The user "<?= htmlspecialchars((string)($username_to_view ?? '')) ?>" does not exist in the database.</p>
            </div>
            
            <div class="terminal-line" style="margin-top: 20px;">
                <span class="prompt">$</span> <span class="comment"># Use the back button to return to chat</span>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>