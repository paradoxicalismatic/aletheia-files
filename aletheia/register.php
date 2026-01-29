<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if (LOCKDOWN_ENABLED) {
    $error = 'The site is currently in lockdown mode. New registrations are temporarily disabled.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha_input = strtolower($_POST['captcha'] ?? '');
    $captcha_correct = strtolower($_SESSION['captcha_text'] ?? 'a_different_string');

    if (empty($captcha_input) || $captcha_input !== $captcha_correct) {
        $error = "Incorrect CAPTCHA text entered.";
    } else {
        $username = clean_input($_POST['username']);
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'];
        $member_code = trim($_POST['member_code'] ?? '');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            $error = "Username can only contain letters (a-z) and numbers (0-9).";
        } elseif (strlen($username) < 3) {
            $error = "Username too short (min 3 chars)";
        } elseif ($stmt->fetch()) {
            $error = "Username already taken";
        } elseif ($password !== $confirm) {
            $error = "Passwords don't match";
        } else {
            $role = 'guest';
            $code_id_to_mark = null;

            if (!empty($member_code)) {
                $code_stmt = $pdo->prepare("SELECT id FROM member_codes WHERE code = ? AND is_used = 0");
                $code_stmt->execute([$member_code]);
                $valid_code = $code_stmt->fetch();
                if ($valid_code) {
                    $role = 'member';
                    $code_id_to_mark = $valid_code['id'];
                } else {
                    $error = "Invalid or already used member code. Registering as Guest.";
                }
            }

            if (empty($error)) {
                $pdo->beginTransaction();
                try {
                    $hashed = password_hash($password . PEPPER, PASSWORD_BCRYPT);
                    $insert_stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $insert_stmt->execute([$username, $hashed, $role]);
                    $new_user_id = $pdo->lastInsertId();

                    if ($code_id_to_mark) {
                        $update_code_stmt = $pdo->prepare("UPDATE member_codes SET is_used = 1, used_by = ?, used_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $update_code_stmt->execute([$new_user_id, $code_id_to_mark]);
                    }
                    
                    $pdo->commit();
                    $_SESSION['success'] = "Registration successful! Please login.";
                    header("Location: guest_login.php");
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Database error during registration.";
                }
            }
        }
    }
}

if (function_exists('set_captcha_text')) {
    set_captcha_text();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= SITE_NAME ?></title>
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
            padding: 2rem 0; /* Allow scrolling on small screens */
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
        
        input:disabled {
            opacity: 0.4;
            cursor: not-allowed;
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

        button[type="submit"]:hover:not(:disabled) {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        button[type="submit"]:disabled {
            background-color: #444;
            color: #888;
            cursor: not-allowed;
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
        <h1>Create Account</h1>
        <?php if ($error): ?><div class="message error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" novalidate>
            <input type="text" name="username" placeholder="Username" required <?= (defined('LOCKDOWN_ENABLED') && LOCKDOWN_ENABLED) ? 'disabled' : '' ?>>
            <input type="password" name="password" placeholder="Password" required <?= (defined('LOCKDOWN_ENABLED') && LOCKDOWN_ENABLED) ? 'disabled' : '' ?>>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required <?= (defined('LOCKDOWN_ENABLED') && LOCKDOWN_ENABLED) ? 'disabled' : '' ?>>
            <input type="text" name="member_code" placeholder="Member Code (Optional)" <?= (defined('LOCKDOWN_ENABLED') && LOCKDOWN_ENABLED) ? 'disabled' : '' ?>>
            
            <?php if (function_exists('set_captcha_text')): ?>
            <div class="captcha-box">
                <img src="captcha_image.php" alt="CAPTCHA Image">
                <input type="text" name="captcha" placeholder="Enter CAPTCHA" required autocomplete="off" <?= (defined('LOCKDOWN_ENABLED') && LOCKDOWN_ENABLED) ? 'disabled' : '' ?>>
            </div>
            <?php endif; ?>

            <button type="submit" <?= (defined('LOCKDOWN_ENABLED') && LOCKDOWN_ENABLED) ? 'disabled' : '' ?>>Register</button>
        </form>
        <p>Already have an account? <a href="guest_login.php">Login here</a></p>
        <p class="rules-text">Rules: No CP/porn/spam/gore/other illegal activity.</p>
    </div>
</body>
</html>