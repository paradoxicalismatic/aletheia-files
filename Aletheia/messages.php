<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$error_message = '';

// Process a new message if one was posted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'CSRF token validation failed.';
    } else {
        $message = trim($_POST['message']);
        $last_message_time = $_SESSION['last_message_time'] ?? 0;
        $current_time = time();
        $cooldown_period = 5;

        if (($current_time - $last_message_time) < $cooldown_period) {
            $error_message = "Please wait " . ($cooldown_period - ($current_time - $last_message_time)) . " seconds.";
        } elseif (empty($message)) {
            $error_message = "Message cannot be empty.";
        } elseif (mb_strlen($message, 'UTF-8') > 500) {
            $error_message = "Message too long. Maximum 500 characters allowed.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO messages (user_id, content) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $message]);
            $_SESSION['last_message_time'] = $current_time;
        }
    }
}

// This script will now output the same content as display.php
// so that when a message is sent, the display frame is updated instantly.
require 'display.php';