<?php
require_once 'config.php';

// 1. Security Check: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: guest_login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];

// 2. Handle the POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kick_user'])) {
    // Verify CSRF Token
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $user_to_kick_id = $_POST['kick_user'];

        // Fetch the role of the target for permission hierarchy check
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_to_kick_id]);
        $user_to_kick_role = $stmt->fetchColumn();

        $can_kick = false;
        // Check if current user is Staff and not kicking themselves
        if (ROLES[$current_user_role] >= ROLES['moderator'] && $current_user_id != $user_to_kick_id) {
            // Owners can kick anyone; others check hierarchy
            if ($current_user_role === 'owner' || ROLES[$current_user_role] > ROLES[$user_to_kick_role]) {
                $can_kick = true;
            }
        }

        if ($can_kick) {
            try {
                $pdo->beginTransaction();

                // Add to kicks table for 5 minutes
                $kick_stmt = $pdo->prepare("INSERT INTO kicks (user_id, kicked_by, expires_at) VALUES (?, ?, datetime('now', '+5 minutes'))");
                $kick_stmt->execute([$user_to_kick_id, $current_user_id]);
                
                // Force user to appear offline
                $offline_stmt = $pdo->prepare("UPDATE users SET last_activity = datetime('now', '-5 minutes') WHERE id = ?");
                $offline_stmt->execute([$user_to_kick_id]);

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
            }
        }
    }
}

/**
 * 3. BREAK OUT OF IFRAME
 * Since this is called from an iframe, a standard header("Location: index.php") 
 * would only refresh the small sidebar. 
 * We use a meta refresh or a simple link to force the parent to reload.
 */
echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=index.php" target="_parent"></head>
      <body style="background:#000;color:#fff;font-family:monospace;">
      <script>window.parent.location.href = "index.php";</script>
      <noscript><a href="index.php" target="_parent">Kick successful. Click here to return.</a></noscript>
      </body></html>';
exit;