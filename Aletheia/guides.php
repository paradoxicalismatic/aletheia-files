<?php

require_once 'config.php';

// --- ACCESS CONTROL ---
// This block checks if the user is logged in and has the required 'admin' role or higher.
// It relies on the ROLES array being defined in config.php (e.g., ['user' => 1, 'moderator' => 2, 'admin' => 3]).
if (!isset($_SESSION['user_role']) || !isset(ROLES[$_SESSION['user_role']]) || ROLES[$_SESSION['user_role']] < ROLES['owner']) {
    http_response_code(403); // Set HTTP status to "Forbidden"
    
    // Display a clear, well-styled access denied message and stop the script.
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>(403 Forbidden) Access Denied - The Onion Parlour</title>
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
                background-color: #11111b; /* Catppuccin Mocha Base */
                color: #cdd6f4; /* Catppuccin Mocha Text */
                display: flex;
                justify-content: center;
                align-items: center;
                text-align: center;
                position: relative;
            }
            .container {
                background-color: #1e1e2e; /* Catppuccin Mocha Mantle */
                padding: 2.5rem 3rem;
                border-radius: 12px;
                border: 1px solid #313244; /* Catppuccin Mocha Overlay0 */
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            }
            h1 {
                font-size: 2.5rem;
                margin: 0 0 0.5rem 0;
                color: #f38ba8; /* Catppuccin Mocha Red */
            }
            p {
                margin: 0 0 1.5rem 0;
                font-size: 1.1rem;
                color: #bac2de; /* Catppuccin Mocha Subtext1 */
            }
            .btn {
                display: inline-block;
                background-color: #89b4fa; /* Catppuccin Mocha Blue */
                color: #11111b;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                transition: background-color 0.2s ease-in-out, transform 0.2s ease;
            }
            .btn:hover {
                background-color: #a6e3a1; /* Catppuccin Mocha Green */
                transform: translateY(-2px); /* This is a CSS transition, not JavaScript */
            }
            footer {
                position: absolute;
                bottom: 1.5rem;
                width: 100%;
                text-align: center;
                color: #7f849c; /* Catppuccin Mocha Subtext0 */
                font-size: 0.9rem;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>(403 Forbidden)</h1>
            <p>You do not have the necessary permissions to view this page.</p>
            <a href="index.php" class="btn">Return to Chat</a>
        </div>
        <footer>
            The Onion Parlour
        </footer>
    </body>
    </html>
    HTML;
    exit; // Halt script execution immediately.
}
// --- END ACCESS CONTROL ---


// PHP file: combined_guide_tabs.php
// This file closes the PHP tag immediately and outputs the HTML content directly
// as an interactive, tabbed interface using only HTML/CSS.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Combined Linux Mint Cinnamon Guide - Tabbed</title>
<style>
  /* Base Synthwave/Neon Theme */
  :root {
    --bg: #0a001a;
    --panel: #140032;
    --accent1: #8a3ffc;   /* vivid indigo */
    --accent2: #d45fff;   /* bright magenta */
    --neon: #6f5fff;      /* soft indigo neon */
    --glass: rgba(255, 255, 255, 0.05);
    --mono: "Roboto Mono", monospace;
  }
  html, body {
    height: 100%;
    margin: 0;
    background:
      radial-gradient(1200px 600px at 10% 10%, rgba(111,95,255,0.07), transparent 8%),
      radial-gradient(900px 400px at 90% 90%, rgba(212,95,255,0.05), transparent 12%),
      var(--bg);
    color: var(--neon);
    font-family: var(--mono);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }
  .wrap {
    max-width: 1100px;
    margin: 5vh auto;
    padding: 28px;
    background: linear-gradient(180deg, var(--panel), #0c0030);
    border-radius: 14px;
    box-shadow: 0 10px 40px rgba(20,0,50,0.7);
    border: 1px solid var(--glass);
  }
  header {
    margin-bottom: 24px;
    border-bottom: 1px solid var(--accent1);
    padding-bottom: 15px;
  }
  h1 {
    font-size: 28px;
    margin: 0;
    color: var(--neon);
    user-select: none;
    text-align: center;
    text-shadow: 0 0 10px rgba(111,95,255,0.5);
  }
  h2 {
    color: var(--accent2);
    font-size: 1.3em;
    margin-top: 1.5em;
    border-bottom: 1px dashed rgba(212, 95, 255, 0.2);
    padding-bottom: 5px;
  }
  
  /* --- Tabbed Interface CSS --- */
  .tab-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }
  
  /* Hide radio buttons */
  .tab-container input[type="radio"] {
    display: none;
  }
  
  /* Style for tab labels (the actual tabs) */
  .tab-container label {
    padding: 10px 18px;
    cursor: pointer;
    background: var(--panel);
    border: 1px solid var(--glass);
    border-radius: 8px 8px 0 0;
    color: var(--muted);
    transition: all 0.3s ease;
    font-weight: 700;
    user-select: none;
    flex-grow: 1;
    text-align: center;
    min-width: 150px; /* Ensures better wrapping on small screens */
  }
  
  /* Hover and Checked (Active) state for tabs */
  .tab-container label:hover {
    color: var(--neon);
    border-color: var(--neon);
  }
  
  /* When a radio button is checked, apply styles to its corresponding label */
  .tab-container input[type="radio"]:checked + label {
    background: linear-gradient(180deg, var(--accent1), var(--accent2));
    color: var(--panel);
    border-color: var(--accent2);
    box-shadow: 0 0 15px var(--accent1);
    transform: translateY(-2px);
  }
  
  /* Style for content panels */
  .tab-content {
    display: none; /* Hide all content panels by default */
    width: 100%;
    padding: 25px;
    border-radius: 0 10px 10px 10px;
    background: #19053d; /* Slightly different panel color for contrast */
    border: 1px solid var(--accent1);
    box-shadow: inset 0 0 20px rgba(138, 63, 252, 0.15);
    margin-top: -1px; /* Overlap with the tab bar */
  }
  
  /* When a radio button is checked, use the general sibling selector (~) 
     to show the content panel associated with the checked tab */
  #tab-1:checked ~ .content-1,
  #tab-2:checked ~ .content-2,
  #tab-3:checked ~ .content-3,
  #tab-4:checked ~ .content-4,
  #tab-5:checked ~ .content-5,
  #tab-6:checked ~ .content-6 {
    display: block; /* Show the content panel */
  }
  
  /* Content-specific styling */
  ol.steps {
    list-style: none;
    counter-reset: step-counter;
    padding: 0;
  }
  ol.steps li {
    counter-increment: step-counter;
    margin: 16px 0;
    padding: 16px;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(111,95,255,0.04), transparent);
    border: 1px solid var(--glass);
    line-height: 1.5;
    position: relative;
  }
  ol.steps li::before {
    content: counter(step-counter);
    position: absolute;
    top: -10px;
    left: -10px;
    background: var(--accent2);
    color: var(--panel);
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 14px;
    font-weight: 900;
    box-shadow: 0 2px 5px rgba(212, 95, 255, 0.5);
  }
  code.cmd, pre.raw {
    display: block;
    margin-top: 8px;
    padding: 10px 12px;
    background: #0d002e;
    border-radius: 8px;
    color: #00f6ff; /* Neon Cyan */
    font-family: inherit;
    font-size: 13px;
    overflow-x: auto;
    user-select: text;
    border: 1px solid rgba(0, 246, 255, 0.1);
  }
  p.note {
    margin-top: 12px;
    padding: 12px;
    border-left: 4px solid var(--accent2);
    color: #e6ccff;
    background: rgba(212, 95, 255, 0.07);
    font-size: 13px;
  }
  a {
    color: var(--neon);
    text-decoration: none;
    transition: color 0.3s;
  }
  a:hover {
    color: var(--accent2);
    text-decoration: underline;
  }
  
</style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>💾 Combined Linux Mint Cinnamon Setup Guide (Tabbed View)</h1>
  </header>

  <div class="tab-container">
    <input type="radio" name="tabs" id="tab-1" checked />
    <label for="tab-1">.run to Launcher</label>

    <input type="radio" name="tabs" id="tab-2" />
    <label for="tab-2">AppImage & Pin</label>

    <input type="radio" name="tabs" id="tab-3" />
    <label for="tab-3">Tor Service Setup</label>
    
    <input type="radio" name="tabs" id="tab-4" />
    <label for="tab-4">XAMPP/Tor Quick Ref</label>

    <input type="radio" name="tabs" id="tab-5" />
    <label for="tab-5">Webapp Manager</label>

    <input type="radio" name="tabs" id="tab-6" />
    <label for="tab-6">PGP Isolation Guide</label>


    <div class="tab-content content-1">
      <h2>1. From .run File to Pinned Launcher</h2>
      <ol class="steps">
        <li>Download the <code>.run</code> installer file.</li>
        <li>
          Make the <code>.run</code> file executable:
          <code class="cmd">chmod +x /path/to/file.run</code>
        </li>
        <li>
          Run the <code>.run</code> installer:
          <code class="cmd">sudo /path/to/file.run</code>
        </li>
        <li>After installation, find the main executable (e.g. <code>/opt/lampp/manager-linux-x64.run</code>).</li>
        <li>
          Create a desktop entry file <code>~/.local/share/applications/yourapp.desktop</code> with this content:
          <pre class="raw">
[Desktop Entry]
Name=Your App Name
Exec=sudo /opt/lampp/manager-linux-x64.run
Icon=/opt/lampp/htdocs/favicon.ico
Type=Application
Terminal=false
StartupNotify=true
          </pre>
        </li>
        <li>
          Make the desktop entry trusted (mark as executable):
          <code class="cmd">chmod +x ~/.local/share/applications/yourapp.desktop</code>
        </li>
        <li>Log out and log back in (or refresh your desktop environment) to register the new launcher.</li>
        <li>Open your menu, find your app, right-click its icon, and choose “Add to panel” or “Pin to panel.”</li>
      </ol>
    </div>

    <div class="tab-content content-2">
      <h2>2. Make AppImage Executable & Pin To Panel</h2>
      <ol class="steps">
        <li>
          Give your AppImage executable permission:
          <code class="cmd">chmod +x ~/Downloads/YourAppImageFile.AppImage</code>
        </li>
        <li>
          Run it once:
          <code class="cmd">~/Downloads/YourAppImageFile.AppImage &amp;</code>
        </li>
        <li>
          Create a desktop launcher file:
          <code class="cmd">nano ~/.local/share/applications/yourapp.desktop</code>
        </li>
        <li>
          Paste this inside the file:
          <pre class="raw">
[Desktop Entry]
Name=Your App
Exec=/home/noah/Downloads/YourAppImageFile.AppImage
Type=Application
Icon=/home/noah/.icons/yourapp.png
Terminal=false
MimeType=x-scheme-handler/yourapp;
          </pre>
        </li>
        <li>Save and exit nano (<code>Ctrl+O</code>, <code>Enter</code>, <code>Ctrl+X</code>).</li>
        <li>
          Refresh desktop database:
          <code class="cmd">update-desktop-database ~/.local/share/applications</code>
        </li>
        <li>Find "Your App" in your menu and pin it to the panel.</li>
        <li>
          <p class="note">Oh yeah, and **PLEASE save the icons in:**<br /><code>/home/noah/.icons</code></p>
        </li>
      </ol>
    </div>

    <div class="tab-content content-3">
      <h2>3. Setup Tor Hidden Service on Linux Mint Cinnamon</h2>
      <ol class="steps">
        <li>
          <strong>Install Tor:</strong><br />
          Open terminal and run:
          <code class="cmd">sudo apt update<br>sudo apt install tor</code>
        </li>
        <li>
          <strong>Configure Tor Hidden Service:</strong><br />
          Edit the Tor config file:
          <code class="cmd">sudo nano /etc/tor/torrc</code>
          Add these lines at the end:
          <pre class="raw">
HiddenServiceDir /var/lib/tor/hidden_service/
HiddenServicePort 80 127.0.0.1:8080
          </pre>
          <p> - <code>HiddenServiceDir</code> is where Tor stores your onion keys and hostname.</p>
          <p> - <code>HiddenServicePort</code> maps Tor port 80 to your local web server on port 8080.</p>
        </li>
        <li>
          <strong>Restart Tor to apply changes:</strong><br />
          <code class="cmd">sudo systemctl restart tor</code>
        </li>
        <li>
          <strong>Find your .onion address:</strong><br />
          After Tor restarts, check your onion address:
          <code class="cmd">sudo cat /var/lib/tor/hidden_service/hostname</code>
        </li>
        <li>
          <strong>Set up your web server:</strong><br />
          For example, run a simple server on port 8080 serving your HTML files.<br />
          Example using Python:
          <code class="cmd">cd /path/to/your/site<br>python3 -m http.server 8080</code>
        </li>
        <li>
          <strong>Access your site:</strong><br />
          Use the .onion address from step 4 in the Tor Browser.<br />
          Your site is now reachable only via Tor.
        </li>
      </ol>
    </div>

    <div class="tab-content content-4">
      <h2>4. XAMPP + Tor Hidden Service Quick Reference</h2>

      <h2>XAMPP File Paths & Redirect:</h2>
      <p><strong>Where to put your website files:</strong></p>
      <code class="cmd">/opt/lampp/htdocs/hidden_service/</code>
      <p>Keep your HTML, CSS, images, and other site files inside that folder.</p>

      <p><strong>To redirect visitors from root (/) to your subfolder:</strong></p>
      <p>Create or edit this file: <code>/opt/lampp/htdocs/.htaccess</code></p>
      <p>With the following content:</p>
      <pre class="raw">RedirectMatch ^/$ /hidden_service/</pre>

      <h2>XAMPP Commands:</h2>
      <p><strong>Open the XAMPP Manager Window:</strong></p>
      <code class="cmd">sudo /opt/lampp/manager-linux-x64.run</code>

      <p><strong>Start XAMPP services (Apache, MySQL, etc):</strong></p>
      <code class="cmd">sudo /opt/lampp/lampp start</code>

      <p><strong>Restart XAMPP services:</strong></p>
      <code class="cmd">sudo /opt/lampp/lampp restart</code>

      <p><strong>Stop XAMPP services:</strong></p>
      <code class="cmd">sudo /opt/lampp/lampp stop</code>

      <p><strong>Check Apache is running:</strong></p>
      <code class="cmd">sudo /opt/lampp/lampp status</code>

      <h2>Tor Commands & Config:</h2>
      <p><strong>Tor Hidden Service config snippet in <code>/etc/tor/torrc</code>:</strong></p>
      <pre class="raw">
HiddenServiceDir /var/lib/tor/hidden_service/
HiddenServicePort 80 127.0.0.1:80
      </pre>

      <p><strong>Check Tor Hidden Service hostname (your .onion address):</strong></p>
      <code class="cmd">sudo cat /var/lib/tor/hidden_service/hostname</code>

      <p><strong>Restart Tor service:</strong></p>
      <code class="cmd">sudo systemctl restart tor</code>

      <h2>Testing:</h2>
      <p><strong>Test locally in browser:</strong></p>
      <p><code>http://localhost/hidden_service/</code> - to view your site</p>
    </div>
    
    <div class="tab-content content-5">
      <h2>5. SoundCloud — Webapp Manager Quick Reminder</h2>
      <ol class="steps" start="1">
        <li>
          <strong>Install Webapp Manager (once):</strong>
          <code class="cmd">sudo apt update &amp;&amp; sudo apt install webapp-manager</code>
        </li>
        <li>
          <strong>Open the manager:</strong>
          <code class="cmd">webapp-manager</code>
          <p class="small">If GUI doesn't show in menu: run the command directly.</p>
        </li>
        <li>
          <strong>Create new app:</strong>
          <p>• Name: *SoundCloud*</p>
          <p>• URL: *https://soundcloud.com*</p>
          <p>• Icon: choose or drop <code>/home/noah/.icons/soundcloud.png</code></p>
          <p>• Browser: isolated profile (recommended)</p>
          <p class="note">Click Save. A .desktop entry is created in your local applications menu.</p>
        </li>
        <li>
          <strong>Pin to panel:</strong>
          <p>Open your menu → find "SoundCloud" → right-click → Add to panel (or drag icon).</p>
        </li>
        <li>
          <strong>Extras — quick terminal commands:</strong>
          <pre class="raw"># open created desktop file
nano ~/.local/share/applications/webapp-soundcloud.desktop

# force-refresh menu icons
update-desktop-database ~/.local/share/applications
          </pre>
        </li>
        <li>
          <strong>Icons folder (you asked):</strong>
          <p>Save icons here:</p>
          <code class="cmd">/home/noah/.icons/</code>
          <p class="small">Use 256×256 PNG or SVG for best results.</p>
        </li>
        <li>
          <strong>Remove the webapp:</strong>
          <code class="cmd">webapp-manager → select SoundCloud → Remove</code>
          <p class="small">Or delete its desktop entry under <code>~/.local/share/applications/</code></p>
        </li>
      </ol>
      <p class="note">Tip: if webapp opens in the wrong browser profile, recreate it and select **Isolated profile** so cookies and storage stay separate.</p>
    </div>

    <div class="tab-content content-6">
      <h2>6. PGP Decryption Isolation Guide (Security Best Practice)</h2>
      <ol class="steps">
        <li>
          Install VirtualBox:<br />
          <code class="cmd">sudo apt install virtualbox</code>
        </li>
        <li>
          Download Linux Mint Cinnamon ISO:<br />
          <a href="https://linuxmint.com/download.php" target="_blank" rel="noopener noreferrer">https://linuxmint.com/download.php</a>
        </li>
        <li>
          Create a new VM in VirtualBox.<br />
          Set Linux Mint ISO as boot disk.
        </li>
        <li>
          Export your keys from main system:<br />
          - Open Kleopatra.<br />
          - Right-click your key, choose “Export Secret Keys” and “Export” for public key.<br />
          - Save files and transfer to VM.<br /><br />
          Import keys in VM Kleopatra:<br />
          <code class="cmd">File &gt; Import Certificates</code>
        </li>
        <li>
          Use VirtualBox shared folders or USB passthrough to transfer encrypted files.
        </li>
        <li>
          Decrypt files inside VM using **Kleopatra**.
        </li>
        <li>
          Do not open decrypted files immediately.
        </li>
        <li>
          Scan decrypted files with antivirus:<br />
          Install **ClamAV**:<br />
          <code class="cmd">sudo apt install clamav</code>
          Run scan:<br />
          <code class="cmd">clamscan -r /path/to/decrypted/files</code>
          Optional SHA256 checksum:<br />
          <code class="cmd">sha256sum /path/to/decrypted/file</code>
          Compare hash if known.
        </li>
        <li>
          If suspicious, delete VM or restore snapshot.
        </li>
      </ol>
    </div>
  </div>
</div>
</body>
</html>