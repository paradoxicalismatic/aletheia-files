<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Check for any unread private messages for the current user
$unread_stmt = $pdo->prepare("SELECT COUNT(id) FROM private_messages WHERE receiver_id = ? AND is_read = 0");
$unread_stmt->execute([$current_user_id]);
$has_unread_pms = $unread_stmt->fetchColumn() > 0;

// Fetch users active in the last 1 minute for a more stable online list.
$online_users = $pdo->query("
    SELECT id, username, chat_color, role
    FROM users
    WHERE last_activity >= datetime('now', '-1 minute')
    ORDER BY username ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Combine all data into a single array
$data = [
    'online_users' => $online_users,
    'has_unread_pms' => $has_unread_pms
];

// Set the header to tell the browser we're sending JSON data
header('Content-Type: application/json');

// Encode the data into JSON format and send it.
echo json_encode($data);