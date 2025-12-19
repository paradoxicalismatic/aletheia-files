<?php
require_once 'config.php';

// Default role to guest in case the session is incomplete
$user_role = 'guest';

// Check if a user is actually logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get the user's role *before* we destroy the session
    $user_role = $_SESSION['user_role'] ?? 'guest';

// In logout.php

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // --- ADD THIS LINE FOR LOGGING ---
    log_user_action($_SESSION['user_id'], $_SESSION['username'], 'logout', 'User initiated logout.');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: member_login.php");
exit;

    try {
        // Set the user's last activity to 5 minutes ago to make them appear offline immediately
        $stmt = $pdo->prepare("UPDATE users SET last_activity = datetime('now', '-5 minutes') WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // If the database fails, we still proceed with logout
        error_log("Failed to update last_activity on logout: " . $e->getMessage());
    }
}

// Unset all of the session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Determine the correct login page based on the user's former role
if (ROLES[$user_role] >= ROLES['member']) {
    // Redirect members, admins, etc., to the member login page
    header("Location: member_login.php?logged_out=1");
} else {
    // Redirect guests and prisoners to the guest login page
    header("Location: guest_login.php?logged_out=1");
}
exit;