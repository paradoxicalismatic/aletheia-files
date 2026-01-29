<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// Set headers for Server-Sent Events (SSE)
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Disable PHP output buffering
if (ob_get_level()) ob_end_clean();

// Keep track of last message ID
$lastMessageId = 0;

// Infinite loop to keep streaming
while (true) {
    try {
        // Fetch new messages since last check
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.role, u.chat_color
            FROM messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$lastMessageId]);
        $newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If we have new messages, send them
        if (!empty($newMessages)) {
            foreach ($newMessages as $msg) {
                // Update last message ID
                $lastMessageId = max($lastMessageId, $msg['id']);
                
                // Check if this is a reply
                $replyInfo = null;
                if ($msg['reply_to_message_id']) {
                    $replyStmt = $pdo->prepare("SELECT m.content, u.username FROM messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
                    $replyStmt->execute([$msg['reply_to_message_id']]);
                    $replyInfo = $replyStmt->fetch(PDO::FETCH_ASSOC);
                }
                
                // Check delete permissions
                $canDelete = false;
                $currentUserRole = $_SESSION['user_role'];
                if (isset(ROLES[$currentUserRole]) && ROLES[$currentUserRole] >= ROLES['moderator']) {
                    if ($msg['user_id'] == $_SESSION['user_id']) {
                        $canDelete = true;
                    } elseif (ROLES[$currentUserRole] > ROLES[$msg['role']]) {
                        $canDelete = true;
                    }
                }
                
                // Build the message HTML
                $html = '<div class="message" data-id="' . $msg['id'] . '">';
                
                if ($replyInfo) {
                    $replyContent = htmlspecialchars(mb_substr($replyInfo['content'], 0, 50, 'UTF-8'));
                    $ellipsis = mb_strlen($replyInfo['content'], 'UTF-8') > 50 ? '...' : '';
                    $html .= '<div class="reply-indicator">↪ Replying to <strong>' . 
                             htmlspecialchars($replyInfo['username']) . '</strong>: ' . 
                             $replyContent . $ellipsis . '</div>';
                }
                
                $html .= '<span class="time">' . date('H:i', strtotime($msg['created_at'])) . '</span>';
                $html .= '<span class="user" style="color: ' . htmlspecialchars($msg['chat_color']) . '">' . 
                         clean_input($msg['username']) . ':</span>';
                $html .= '<span class="content">' . format_message_content($msg['content'], $msg['role']) . '</span>';
                
                $html .= '<div class="message-actions">';
                $html .= '<a href="form.php?reply_to=' . $msg['id'] . '" target="form_frame">Reply</a>';
                
                if ($canDelete) {
                    $html .= '<form method="POST" action="index.php" target="_parent" style="display: inline; margin: 0;">';
                    $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
                    $html .= '<input type="hidden" name="delete_message" value="' . $msg['id'] . '">';
                    $html .= '<button type="submit" onclick="return confirm(\'Delete this message?\')">Delete</button>';
                    $html .= '</form>';
                }
                
                $html .= '</div></div>';
                
                // Send the message as SSE
                echo "data: " . json_encode(['html' => $html, 'id' => $msg['id']]) . "\n\n";
                flush();
            }
        } else {
            // Send heartbeat to keep connection alive
            echo ": heartbeat\n\n";
            flush();
        }
        
    } catch (PDOException $e) {
        error_log("Stream error: " . $e->getMessage());
    }
    
    // Sleep for 1 second before checking again
    sleep(1);
    
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }
}
?>