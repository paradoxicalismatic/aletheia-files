<?php
require_once 'config.php';

// Ensure user is logged in to see this page
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Please log in to access this page.";
    exit;
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
    <title>Server Uptime</title>
    <link rel="stylesheet" href="styles.css">
    <?php if ($current_theme && $current_theme !== 'default'): ?>
        <link rel="stylesheet" href="data/themes/<?= htmlspecialchars($current_theme) ?>.css">
    <?php endif; ?>
    <meta http-equiv="refresh" content="1">
    <style>
        /* Styles adapted to use theme variables */
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
            max-width: 500px;
            width: 90%;
            border: 1px solid #363a4f;
        }
        h1 {
            color: var(--primary);
            margin-bottom: 8px;
        }
        p {
            font-size: 1.2rem;
            color: var(--text);
            margin-bottom: 24px;
        }
        .donation-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #363a4f;
        }
        .donation-text {
            font-size: 0.95rem;
            color: var(--subtle-text);
            margin-bottom: 15px;
        }
        .address-container {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--background);
            border: 1px solid #363a4f;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 25px;
            word-break: break-all; /* Ensures long address doesn't overflow */
        }
        .address {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9rem;
            color: var(--text);
            margin: 0;
            margin-right: 10px;
        }
        /* Using the existing .button style for the copy button */
        .copy-button {
            background-color: var(--background);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .copy-button:hover {
            background-color: var(--primary);
            color: var(--background);
        }
        .button-container {
            display: flex;
            justify-content: center;
            gap: 15px; /* Adds space between buttons */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Server Uptime</h1>
        <p>
            <?php
            // --- PHP UPTIME LOGIC ---
            $uptime_string = "Uptime information is unavailable.";
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                try {
                    if (class_exists('COM')) {
                        $wmi = new COM('winmgmts:{impersonationLevel=impersonate}!\\\\.\\root\\cimv2');
                        $os_info = $wmi->ExecQuery("SELECT LastBootUpTime FROM Win32_OperatingSystem");
                        $boot_time_str = "";
                        foreach ($os_info as $os) { $boot_time_str = $os->LastBootUpTime; break; }
                        $boot_time = new DateTime(substr($boot_time_str, 0, 4) . '-' . substr($boot_time_str, 4, 2) . '-' . substr($boot_time_str, 6, 2) . ' ' . substr($boot_time_str, 8, 2) . ':' . substr($boot_time_str, 10, 2) . ':' . substr($boot_time_str, 12, 2));
                        $interval = (new DateTime())->diff($boot_time);
                        $uptime_string = $interval->format('%a days, %h hours, %i minutes, %s seconds');
                    } else { $uptime_string = "COM extension is not enabled."; }
                } catch (Exception $e) { $uptime_string = "Error calculating uptime."; }
            } else {
                if (is_readable('/proc/uptime')) {
                    $uptime_seconds = (int)explode(' ', file_get_contents('/proc/uptime'))[0];
                    $days = floor($uptime_seconds / 86400);
                    $hours = floor(($uptime_seconds % 86400) / 3600);
                    $minutes = floor(($uptime_seconds % 3600) / 60);
                    $seconds = $uptime_seconds % 60;
                    $uptime_string = "$days days, $hours hours, $minutes minutes, $seconds seconds";
                } else { $uptime_string = "Cannot read uptime on this OS."; }
            }
            echo "The server has been up for: <br><strong>" . htmlspecialchars($uptime_string) . "</strong>";
            ?>
        </p>

        <div class="donation-section">
            <p class="donation-text">This server is powered by electricity and the occasional human sacrifice. Since the second option is getting complicated, we're relying on donations.</p>
            <div class="address-container">
                <span id="wallet-address" class="address">Currently not accepting donations.</span>
                <button class="copy-button" onclick="copyAddress()87">Copy</button>
            </div>
        </div>

        <div class="button-container">
            <a href="index.php" class="button">Back</a>
        </div>
    </div>

    <script>
        function copyAddress() {
            const address = document.getElementById('wallet-address').innerText;
            const textarea = document.createElement('textarea');
            textarea.value = address;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            const copyBtn = document.querySelector('.copy-button');
            copyBtn.innerText = 'Copied!';
            setTimeout(() => {
                copyBtn.innerText = 'Copy';
            }, 2000);
        }
    </script>
</body>
</html>