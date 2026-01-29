<?php
// require_once 'config.php' is needed to establish the $pdo connection
require_once 'config.php'; 

// --- Database Table Creation for User Logs ---
// This table tracks user actions like login, logout, kicks, bans, etc.

try {
    // We use CREATE TABLE IF NOT EXISTS to prevent errors if the script is run multiple times.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            action TEXT NOT NULL,          -- e.g., 'login', 'logout', 'kick', 'ban'
            details TEXT,                  -- Optional: Specific context for the action (e.g., reason for ban)
            ip_address TEXT,               -- IP address at the time of the action (useful for moderation)
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            -- FOREIGN KEY: Ensures that if a user is deleted from the 'users' table, 
            -- all their related logs are automatically deleted as well (ON DELETE CASCADE).
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Optional: Echo a message to confirm success when run directly in the browser
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Table Setup</title>
        <style>body{background-color:#1a1b26;color:#9ece6a;font-family:monospace;padding:20px;}</style>
    </head>
    <body>
        <p>✅ User Logs table created successfully or already exists in chat.db.</p>
        <p><a href="index.php" style="color:#7dcfff;">&larr; Return to Chat</a></p>
    </body>
    </html>
    HTML;

} catch (PDOException $e) {
    // If the table creation fails, display the error
    die("Database error during logs table creation: " . $e->getMessage());
}