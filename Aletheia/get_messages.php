<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$messages = $pdo->query("
    SELECT m.*, u.username, u.role /* ADDED u.role */
    FROM messages m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.created_at DESC
")->fetchAll();

foreach ($messages as $msg): ?>
<div class="message">
    <span class="time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
    <span class="user"><?= clean_input($msg['username']) ?>:</span>
    <span class="content"><?= format_message_content($msg['content'], $msg['role']) ?></span> </div>
<?php endforeach; ?>