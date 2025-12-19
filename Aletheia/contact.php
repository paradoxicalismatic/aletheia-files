<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$feedback_message = '';
$feedback_type = '';
$subject_val = '';
$message_val = '';

// Define character limits
define('SUBJECT_MAX_LENGTH', 100);
define('MESSAGE_MAX_LENGTH', 2000);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $user_id = $_SESSION['user_id'];
    $subject = trim($_POST['subject'] ?? 'No Subject');
    $message = trim($_POST['message']);

    // Preserve user input on failed submission
    $subject_val = $subject;
    $message_val = $message;

    // Fetch username
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $username = $stmt->fetchColumn();

    // Validation
    if (empty($subject) || empty($message)) {
        $feedback_message = "Subject and message cannot be empty.";
        $feedback_type = 'error';
    } elseif (strlen($subject) > SUBJECT_MAX_LENGTH) {
        $feedback_message = "Subject is too long. Maximum " . SUBJECT_MAX_LENGTH . " characters allowed.";
        $feedback_type = 'error';
    } elseif (strlen($message) > MESSAGE_MAX_LENGTH) {
        $feedback_message = "Message is too long. Maximum " . MESSAGE_MAX_LENGTH . " characters allowed.";
        $feedback_type = 'error';
    } else {
        $submission_content = "----------------------------------------\n";
        $submission_content .= "Date: " . date("Y-m-d H:i:s") . "\n";
        $submission_content .= "From: " . htmlspecialchars($username) . " (ID: " . $user_id . ")\n";
        $submission_content .= "Subject: " . htmlspecialchars($subject) . "\n";
        $submission_content .= "Message:\n" . htmlspecialchars($message) . "\n";
        $submission_content .= "----------------------------------------\n\n";

        // Save to file
        if (file_put_contents('contact_submissions.txt', $submission_content, FILE_APPEND | LOCK_EX)) {
            $feedback_message = "Your message has been sent successfully! Thank you.";
            $feedback_type = 'success';
            // Clear fields on success
            $subject_val = '';
            $message_val = '';
        } else {
            $feedback_message = "There was an error sending your message. Please try again later.";
            $feedback_type = 'error';
        }
    }
}


// Fetch the current user's theme for styling
try {
    $stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_theme = $stmt->fetchColumn();
} catch (PDOException $e) {
    $current_theme = 'default';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: var(--background);
            color: var(--text);
        }
        .container {
            text-align: center;
            padding: 40px;
            background-color: var(--surface);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            width: 90%;
            border: 1px solid #363a4f;
        }
        h1 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .intro-text {
            color: var(--subtle-text);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            text-align: left;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }
        .form-group input,
        .form-group textarea {
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #363a4f;
            background-color: var(--background);
            color: var(--text);
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .button-container {
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .feedback {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        .feedback.success {
            background-color: #a6e3a1;
            color: #1e1e2e;
        }
        .feedback.error {
            background-color: #f38ba8;
            color: #1e1e2e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Contact Us</h1>
        <p class="intro-text">Have a question, suggestion, or just want to report an issue? Drop us a message below.</p>

        <?php if ($feedback_message): ?>
            <div class="feedback <?= $feedback_type ?>"><?= htmlspecialchars($feedback_message) ?></div>
        <?php endif; ?>

        <form action="contact.php" method="POST" class="contact-form">
            <div class="form-group">
                <label for="subject">Subject (Max <?= SUBJECT_MAX_LENGTH ?> characters)</label>
                <input type="text" id="subject" name="subject" required maxlength="<?= SUBJECT_MAX_LENGTH ?>" value="<?= htmlspecialchars($subject_val) ?>">
            </div>
            <div class="form-group">
                <label for="message">Message (Max <?= MESSAGE_MAX_LENGTH ?> characters)</label>
                <textarea id="message" name="message" required maxlength="<?= MESSAGE_MAX_LENGTH ?>"><?= htmlspecialchars($message_val) ?></textarea>
            </div>
            <button type="submit" class="button">Send Message</button>
        </form>

        <div class="button-container">
            <a href="index.php" class="button">&larr; Back to chat</a>
        </div>
    </div>
</body>
</html>
