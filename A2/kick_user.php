<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

session_start();

if (!isset($_SESSION['csrf_token'], $_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    http_response_code(403);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

$kick_user_id = $_POST['kick_user'] ?? null;
$reason = $_POST['reason'] ?? null; // <-- get the reason

if (!$kick_user_id) {
    http_response_code(400);
    exit;
}

// Optional: check permissions again
if (!isset(ROLES[$current_user_role]) || ROLES[$current_user_role] < ROLES['moderator']) {
    http_response_code(403);
    exit;
}

// Insert into kicks table
$kick_stmt = $pdo->prepare("INSERT INTO kicks (user_id, kicked_by, reason, expires_at) VALUES (?, ?, ?, datetime('now', '+5 minutes'))");
$kick_stmt->execute([$kick_user_id, $current_user_id, $reason]);

header("Location: online_users.php");
exit;
