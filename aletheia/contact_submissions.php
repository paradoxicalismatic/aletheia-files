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

$submissions_file = 'contact_submissions.txt';
$parsed_submissions = [];

if (file_exists($submissions_file) && is_readable($submissions_file)) {
    $content = file_get_contents($submissions_file);
    $submissions = explode("----------------------------------------", $content);
    $submissions = array_reverse(array_filter(array_map('trim', $submissions)));

    foreach ($submissions as $submission) {
        $data = [
            'date' => 'N/A',
            'from' => 'N/A',
            'subject' => 'No Subject',
            'message' => 'No message content.'
        ];
        if (preg_match('/Date: (.*?)\n/s', $submission, $matches)) {
            $data['date'] = $matches[1];
        }
        if (preg_match('/From: (.*?)\n/s', $submission, $matches)) {
            $data['from'] = $matches[1];
        }
        if (preg_match('/Subject: (.*?)\n/s', $submission, $matches)) {
            $data['subject'] = $matches[1];
        }
        if (preg_match('/Message:\n(.*?)$/s', $submission, $matches)) {
            $data['message'] = trim($matches[1]);
        }
        $parsed_submissions[] = $data;
    }
} else {
    file_put_contents($submissions_file, '');
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
    <title>Submissions Inbox - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars(str_replace(' ', '-', $current_theme)) ?>.css">
    <?php endif; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 3rem 1rem;
            background: linear-gradient(135deg, var(--background) 0%, #181825 100%);
            color: var(--text);
        }
        .container {
            padding: 2.5rem;
            background-color: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 950px;
            width: 100%;
            border: 1px solid #363a4f;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .header {
            text-align: center;
            margin-bottom: 2.5rem;
            flex-shrink: 0;
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            letter-spacing: -1px;
        }
        .header p {
            color: var(--subtle-text);
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }

        .submission-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            overflow-y: auto;
            padding-right: 1rem;
            margin-right: -1rem;
            flex-grow: 1;
        }
        .submission-card {
            background-color: var(--background);
            border: 1px solid #363a4f;
            border-left: 5px solid var(--primary);
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .submission-card[open] summary {
             border-bottom: 1px solid #363a4f;
        }
        .submission-card[open] {
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border-color: var(--primary);
        }
        .card-header {
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            cursor: pointer;
            list-style: none;
        }
        .card-header::-webkit-details-marker {
            display: none;
        }
        .card-subject {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text);
            margin: 0;
            /* Ensures long subject lines wrap correctly */
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        .card-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: var(--subtle-text);
            flex-shrink: 0;
        }
        .card-meta svg {
            width: 16px;
            height: 16px;
            stroke: var(--subtle-text);
            margin-right: 0.25rem;
        }
        .card-body {
            padding: 1.5rem;
            line-height: 1.7;
            color: var(--text);
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .card-body p {
            margin: 0;
        }
        .no-submissions {
            text-align: center;
            color: var(--subtle-text);
            padding: 4rem 2rem;
            background-color: var(--background);
            border-radius: 12px;
            border: 2px dashed #363a4f;
        }
        .button-container {
            margin-top: 2.5rem;
            text-align: center;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Submissions Inbox</h1>
            <p>Messages and reports from users.</p>
        </div>
        
        <div class="submission-list">
            <?php if (empty($parsed_submissions)): ?>
                <div class="no-submissions">
                    <h2>The inbox is empty!</h2>
                </div>
            <?php else: ?>
                <?php foreach ($parsed_submissions as $sub): ?>
                    <details class="submission-card">
                        <summary class="card-header">
                            <h2 class="card-subject"><?= htmlspecialchars($sub['subject']) ?></h2>
                            <div class="card-meta">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <strong><?= htmlspecialchars($sub['from']) ?></strong>
                            </div>
                        </summary>
                        <div class="card-body">
                            <p><?= nl2br(htmlspecialchars($sub['message'])) ?></p>
                            <div class="card-meta" style="margin-top: 1.5rem; justify-content: flex-end;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                <span><?= htmlspecialchars($sub['date']) ?></span>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="button-container">
            <a href="index.php" class="button">&larr; Back to Chat</a>
        </div>
    </div>
</body>
</html>
