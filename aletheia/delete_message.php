<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message_id = $_POST['delete_message'];
        
        // Fetch message author's ID and role
        $stmt = $pdo->prepare("SELECT user_id, role FROM messages JOIN users ON users.id = messages.user_id WHERE messages.id = ?");
        $stmt->execute([$message_id]);
        $message_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($message_data) {
            $author_id = $message_data['user_id'];
            $author_role = $message_data['role'];

            $is_own_message = $current_user_id == $author_id;
            $can_self_delete = $is_own_message && (ROLES[$current_user_role] >= ROLES['moderator']);
            $can_staff_delete = !$is_own_message && (ROLES[$current_user_role] > ROLES[$author_role]) && (ROLES[$current_user_role] >= ROLES['moderator']);

            if ($can_self_delete || $can_staff_delete) {
                $del_stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                $del_stmt->execute([$message_id]);
            }
        }
    }
}

// Just reload the display iframe, not the whole page
header("Location: display.php");
exit;
?>