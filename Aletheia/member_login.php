<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_GET['kicked'])) {
    $error = 'You have been kicked from the chat.';
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $captcha_input = strtolower($_POST['captcha'] ?? '');
    $captcha_correct = strtolower($_SESSION['captcha_text'] ?? 'a_different_string');

    if (empty($captcha_input) || $captcha_input !== $captcha_correct) {
        $error = "Incorrect CAPTCHA text entered.";
    } else {
        $username = clean_input($_POST['username']);
        $password = $_POST['password'];

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $error = "Invalid username or password";
        } else {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password . PEPPER, $user['password'])) {
                $ban_stmt = $pdo->prepare("SELECT user_id FROM banned_users WHERE user_id = ?");
                $ban_stmt->execute([$user['id']]);
                if ($ban_stmt->fetch()) {
                    $error = "This account has been permanently banned.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    header("Location: index.php");
                    exit;
                }
            } else {
                $error = "Invalid username or password";
            }
        }
    }
}

// INSERT THIS CODE AFTER SUCCESSFUL LOGIN AND AFTER $_SESSION['user_id'] IS SET
if (isset($_SESSION['user_id'])) {
    try {
        // Fetch username for the log (assuming $pdo is available from config.php)
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $username_for_log = $stmt->fetchColumn() ?? 'Unknown User'; 
        
        // Log the login action
        $log_stmt = $pdo->prepare("INSERT INTO user_logs (user_id, username, action) VALUES (?, ?, 'login')");
        $log_stmt->execute([$_SESSION['user_id'], $username_for_log]);
    } catch (PDOException $e) {
        // Log error, but don't prevent the user from logging in
        error_log("Failed to log login action: " . $e->getMessage());
    }
}
// END OF LOGIN LOGGING CODE

set_captcha_text();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background-color: #121212;
            --surface-color: #1e1e1e;
            --primary-color: #bb86fc;
            --primary-variant-color: #3700b3;
            --secondary-color: #03dac6;
            --text-color: #e0e0e0;
            --error-color: #cf6679;
            --success-color: #66bb6a;
            --border-color: #333;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(45deg, #121212, #1a0a24);
        }

        .auth-container {
            background-color: var(--surface-color);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-weight: 500;
            letter-spacing: 1px;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            text-align: center;
        }
        .error-message { background-color: rgba(207, 102, 121, 0.1); border: 1px solid var(--error-color); color: var(--error-color); }
        .success-message { background-color: rgba(102, 187, 106, 0.1); border: 1px solid var(--success-color); color: var(--success-color); }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        input[type="text"],
        input[type="password"] {
            background: transparent;
            border: none;
            border-bottom: 2px solid var(--border-color);
            color: var(--text-color);
            padding: 0.8rem 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-bottom-color: var(--primary-color);
        }
        
        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px var(--surface-color) inset !important;
            -webkit-text-fill-color: var(--text-color) !important;
        }

        .captcha-box {
            margin-top: 0.5rem;
            text-align: center;
        }
        .captcha-box img {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }
        .captcha-box input {
            text-align: center;
            letter-spacing: 2px;
        }

        button[type="submit"] {
            background-color: var(--primary-color);
            color: #000;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 1rem;
        }

        button[type="submit"]:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        p {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #aaa;
        }

        a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        a:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        .rules-text {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Member Login</h1>
        <?php if ($error): ?><div class="message error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success-message"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="POST" novalidate>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <div class="captcha-box">
                <img src="captcha_image.php" alt="CAPTCHA Image">
                <input type="text" name="captcha" placeholder="Enter CAPTCHA" required autocomplete="off">
            </div>

            <button type="submit">Login</button>
        </form>
        <p>Not a member? <a href="guest_login.php">Login as a guest</a></p>
        <p class="rules-text">Rules: no CP/porn/spam/gore</p>
    </div>
</body>
</html>