<?php
require_once 'config.php';



if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch the current user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 3rem 1rem;
            background-color: var(--background);
            color: var(--text);
            overflow-y: auto; /* ADDED: This enables vertical scrolling */
        }
        .container {
            padding: 2.5rem;
            background-color: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 900px;
            width: 100%;
            border: 1px solid #363a4f;
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .version-log {
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #363a4f;
        }
        .version-log:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        .version-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 1rem;
        }
        .version-header h2 {
            color: var(--primary);
            margin: 0;
        }
        .version-header .date {
            font-size: 0.9rem;
            color: var(--subtle-text);
        }
        .change-list {
            list-style: none;
            padding-left: 1rem;
        }
        .change-list li {
            position: relative;
            padding-left: 1.5rem;
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        .change-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 9px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .change-list .added::before { background-color: #a6e3a1; } /* Green */
        .change-list .fixed::before { background-color: #f9e2af; } /* Yellow */
        .change-list .improved::before { background-color: #89b4fa; } /* Blue */
        
        .button-container {
            margin-top: 2.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Changelog</h1>


<div class="version-log">
<div class="version-header">
<h2>Version 2.1</h2>
<span class="date">December 3, 2025</span>
</div>
<ul class="change-list">
<li class="fixed">I FINALLY fixed the "replying to" positioning to be visible.</li>
</ul>
</div>



<div class="version-log">
<div class="version-header">
<h2>Version 2</h2>
<span class="date">December 3, 2025</span>
</div>
<ul class="change-list">
<li class="added">The site's name changed from "The Onion Parlour" to "Aletheia"</li>
<li class="fixed">Fixed SOME theme's GUI.</li>
</ul>
</div>


<div class="version-log">
<div class="version-header">
<h2>Version 1.6.2</h2>
<span class="date">December 1, 2025</span>
</div>
<ul class="change-list">
<li class="fixed">Fixed pages that didn't load themes correctly</li>
<li class="added">Made a publicly accesible link list</li>
</ul>
</div>
        
<div class="version-log">
<div class="version-header">
<h2>Version 1.6</h2>
<span class="date">December 1, 2025</span>
</div>
<ul class="change-list">
<li class="improved">Made the rank symbols rougly the same size.</li>

</ul>
</div>

<div class="version-log">
<div class="version-header">
<h2>Version 1.5.2</h2>
<span class="date">August 20, 2025</span>
</div>
<ul class="change-list">
<li class="improved">Restricted acces to some files</li>

</ul>
</div>

<div class="version-log">
<div class="version-header">
<h2>Version 1.5.1</h2>
<span class="date">August 15, 2025</span>
</div>
<ul class="change-list">
<li class="fixed">Corrected a bug where markdown and @mention tags were not rendering as HTML.</li>

</ul>
</div>
<div class="version-log">
<div class="version-header">
<h2>Version 1.5.0</h2>
<span class="date">August 15, 2025</span>
</div>
<ul class="change-list">
<li class="added">Introduced a new message-based Rank System. Users now earn rank symbols (e.g., ●, ◆, ★) based on their total message count, visible next to their role icon.</li>
<li class="improved">The logout process now intelligently redirects users to either the member or guest login page based on their previous role.</li>
</ul>
</div>
<div class="version-log">
<div class="version-header">
<h2>Version 1.4.0</h2>
<span class="date">August 15, 2025</span>
</div>
<ul class="change-list">
<li class="added">Implemented a comprehensive ban system where banned users are logged to a separate database table and assigned the 'prisoner' role.</li>
<li class="fixed">Patched a critical Stored XSS vulnerability in the message rendering logic.</li>
<li class="fixed">Resolved a persistent and critical database error that previously prevented staff from deleting user accounts.</li>
</ul>
</div>

<div class="version-log">
    <div class="version-header">
        <h2>Version 1.3.3</h2>
        <span class="date">August 13, 2025</span>
    </div>
    <ul class="change-list">
        <li class="added">Added three new themes: Visibility, Rotten, and an enhanced Metal theme.</li>
        <li class="improved">The 'Metal' theme has been updated with a more distinct high-tech aesthetic, featuring new animations, an improved background, angular UI elements, and custom scrollbars.</li>
        <li class="fixed">Fixed a layout issue in the settings panel where form elements could be pushed off-screen when using themes with larger fonts (e.g., Visibility, Rotten).</li>
    </ul>
</div>
<div class="version-log">
    <div class="version-header">
        <h2>Version 1.3.2</h2>
        <span class="date">August 13, 2025</span>
    </div>
    <ul class="change-list">
        <li class="added">Added user profiles. You can now click a username to see their role, message count, and a custom bio which can be set in the settings.</li>
        <li class="added">Added message replies. You can now reply directly to messages, and the reply will appear threaded underneath.</li>
        <li class="changed">Changed the 'Online Users' button (👥) to a dropdown menu with quick links to the user list and private messages.</li>
        <li class="changed">Adjusted the message display to be more compact, with less space between each message and a visible line separator.</li>
    </ul>
</div>
<div class="version-log">
    <div class="version-header">
        <h2>Version 1.3.1</h2>
        <span class="date">August 13, 2025</span>
    </div>
    <ul class="change-list">
        <li class="improved">Improved online user list's refreshing.</li>
        <li class="added">I combined all the staff (🛡️) and settings/info (⚙️) buttons into two separate dropdown menus that open when clicked, to make the interface less cluttered.</li>
        <li class="added">Moderators and higher-ranking users can now delete their own messages.</li>
    </ul>
</div>

<div class="version-log">
            <div class="version-header">
                <h2>Version 1.3.0</h2>
                <span class="date">August 12, 2025</span>
            </div>
            <ul class="change-list">
                <li class="added">Added a server-side image CAPTCHA to login and registration pages to prevent automated bots.</li>
                <li class="added">Added strict alphanumeric-only username validation to improve security.</li>
                <li class="fixed">Fixed multiple logic bugs in the CAPTCHA system that caused it to fail after the first attempt.</li>
                <li class="improved">Reduced the width of both the Online Users and Settings side panels for a cleaner layout.</li>
                <li class="improved">Converted header buttons (Logout, Roles, Submissions, etc.) to emojis with hover tooltips for a more compact interface.</li>
            </ul>
        </div>

        <div class="version-log">
            <div class="version-header">
                <h2>Version 1.2.0</h2>
                <span class="date">August 12, 2025</span>
            </div>
            <ul class="change-list">
                <li class="added">Implemented a full Role System (Owner, Admin, Moderator, Member, Guest, Prisoner).</li>
                <li class="added">Added staff panels for role management and viewing contact submissions.</li>
                <li class="added">Staff can now kick users and delete messages based on role hierarchy.</li>
                <li class="added">Added a "Prisoner" role that scrambles user messages.</li>
                <li class="added">Added @mention highlighting in chat.</li>
                <li class="added">Added new themes: "Chaos" and "Better Visibility".</li>
                <li class="added">Added a simple CAPTCHA and alphanumeric-only validation to the registration page to fight bots.</li>
                <li class="improved">Role emojis now display next to usernames in all user lists.</li>
                <li class="improved">The "All Users" list is now scrollable.</li>
                <li class="fixed">Corrected various layout and spacing issues.</li>
            </ul>
        </div>

        <div class="version-log">
            <div class="version-header">
                <h2>Version 1.1.0</h2>
                <span class="date">August 12, 2025</span>
            </div>
            <ul class="change-list">
                <li class="added">Added mentions, Roles, Themes and more</li>
                <li class="fixed">Fixed small issues and bugs</li>
            </ul>
        </div>

        
        <div class="version-log">
            <div class="version-header">
                <h2>Version 1.0.0</h2>
                <span class="date">August 11, 2025</span>
            </div>
            <ul class="change-list">
                <li class="added">Added News, Changelog, and Rules pages.</li>
                <li class="fixed">Fixed a major styling issue causing pages to appear unstyled.</li>
                <li class="improved">Improved text wrapping on the contact submissions page.</li>
            </ul>
        </div>


        <div class="button-container">
            <a href="index.php" class="button">&larr; Back to Chat</a>
        </div>
    </div>

</body>

</html>