<?php
require_once 'config.php';

// Prevent the script from timing out and disable output buffering
set_time_limit(0);
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

if (!isset($_SESSION['user_id'])) {
    exit("Access Denied");
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

// Include your styles so the messages look correct inside the frame
echo "<!DOCTYPE html><html><head><link rel='stylesheet' href='styles.css'>";
// Optional: Auto-scroll to bottom trick for pure HTML
echo "<style>body { display: flex; flex-direction: column-reverse; }</style>";
echo "</head><body><div id='chat'>";

$last_id = 0;

// 1. Get the current maximum ID so we only stream NEW messages
$stmt = $pdo->query("SELECT id FROM messages ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch();
if ($row) $last_id = $row['id'];

// 2. The Infinite Loop
while (true) {
    // Check for new messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.role, u.chat_color 
        FROM messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id > ? 
        ORDER BY m.id ASC
    ");
    $stmt->execute([$last_id]);
    $new_messages = $stmt->fetchAll();

    foreach ($new_messages as $msg) {
        // We reuse your existing formatting logic from config.php
        echo "<div class='message' style='border-left: 3px solid {$msg['chat_color']}'>";
        echo "<span class='time'>" . date('H:i', strtotime($msg['created_at'])) . "</span> ";
        echo "<span class='user' style='color:{$msg['chat_color']}'>" . ROLE_ICONS[$msg['role']] . " " . htmlspecialchars($msg['username']) . ":</span> ";
        echo "<span class='content'>" . format_message_content($msg['content'], $msg['role']) . "</span>";
        echo "</div>";
        
        $last_id = $msg['id'];
    }

    // Push the data to the browser immediately
    echo str_pad('', 4096); // Force browsers to render the buffer
    flush();

    // Sleep for 1 second to save CPU, then check again
    sleep(1);

    // If the user closes the tab, kill the PHP process
    if (connection_aborted()) break;
}