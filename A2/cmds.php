<?php
/**
 * cmds.php - BBCode Message Parser
 * Include this file in config.php: require_once 'cmds.php';
 * Then use parse_message($message) in display.php when rendering messages
 */
function parse_message($message) {
    // Escape HTML first to prevent XSS
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // [notice]text[/notice] - Black Market styled notice box
    $message = preg_replace(
        '/\[notice\](.*?)\[\/notice\]/is',
        '<div class="bbcode-notice">
            <div class="bbcode-notice-side">!</div>
            <div class="bbcode-notice-body">$1</div>
        </div>',
        $message
    );
    
    // [bold]text[/bold] - Bold text
    $message = preg_replace(
        '/\[bold\](.*?)\[\/bold\]/is',
        '<strong>$1</strong>',
        $message
    );
    
    // [italic]text[/italic] or [i]text[/i] - Italic/Cursive text
    $message = preg_replace(
        '/\[italic\](.*?)\[\/italic\]/is',
        '<em>$1</em>',
        $message
    );
    $message = preg_replace(
        '/\[i\](.*?)\[\/i\]/is',
        '<em>$1</em>',
        $message
    );
    
    // [underline]text[/underline] or [u]text[/u] - Underlined text
    $message = preg_replace(
        '/\[underline\](.*?)\[\/underline\]/is',
        '<u>$1</u>',
        $message
    );
    $message = preg_replace(
        '/\[u\](.*?)\[\/u\]/is',
        '<u>$1</u>',
        $message
    );
    
    // [code]text[/code] - Scrollable code block with copy button
    static $code_counter = 0;
    $message = preg_replace_callback(
        '/\[code\](.*?)\[\/code\]/is',
        function($matches) use (&$code_counter) {
            $code_counter++;
            $code = trim($matches[1]);
            $code_id = 'code-' . $code_counter;
            
            return '<div class="bbcode-code-wrapper">' .
                   '<div class="bbcode-code-header">' .
                   '<label for="copy-' . $code_id . '" class="bbcode-copy-label" title="Click to copy code">📋</label>' .
                   '<input type="checkbox" id="copy-' . $code_id . '" class="copy-checkbox">' .
                   '<textarea class="copy-textarea" readonly>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</textarea>' .
                   '</div>' .
                   '<div class="bbcode-code"><pre><code>' . $code . '</code></pre></div>' .
                   '</div>';
        },
        $message
    );
    
    // Convert regular line breaks to <br>
    $message = nl2br($message);
    
    return $message;
}
?>
<style>
/* Black Market Notice Design */
.bbcode-notice {
    display: flex;
    width: 100%;
    background: #000000;
    border: 1px solid #ff0055;
    margin: 1.2rem 0;
    overflow: hidden;
    box-sizing: border-box;
}

.bbcode-notice-side {
    background: #ff0055;
    color: #000000;
    width: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 900;
    flex-shrink: 0;
}

.bbcode-notice-body {
    padding: 0.8rem 1.2rem;
    color: #ff0055;
    font-size: 0.95rem;
    line-height: 1.5;
    font-family: 'Segoe UI', Tahoma, sans-serif;
    flex-grow: 1;
}

/* BBCode Code Block Container */
.bbcode-code-wrapper {
    margin: 0.75rem 0;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #45475a;
}

.bbcode-code-header {
    background: #1a1a1a;
    padding: 0.5rem 1rem;
    display: flex;
    justify-content: flex-end;
    border-bottom: 1px solid #45475a;
    position: relative;
}

.bbcode-copy-label {
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
    user-select: none;
}

.bbcode-copy-label:hover {
    background-color: #313244;
}

.copy-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.copy-textarea {
    position: absolute;
    left: -9999px;
    opacity: 0;
}

.copy-checkbox:checked ~ .copy-textarea {
    position: fixed;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    opacity: 1;
    z-index: 10000;
    width: 80%;
    max-width: 800px;
    height: 60vh;
    background: #000000;
    color: #a6e3a1;
    border: 2px solid #89b4fa;
    padding: 1rem;
    font-family: 'Monaco', 'Courier New', 'Consolas', monospace;
    font-size: 0.9rem;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
}

.copy-checkbox:checked ~ .copy-textarea::before {
    content: 'Select All (Ctrl+A/Cmd+A) and Copy (Ctrl+C/Cmd+C) - Click anywhere to close';
    display: block;
    position: absolute;
    top: -2.5rem;
    left: 0;
    right: 0;
    background: #89b4fa;
    color: #11111b;
    padding: 0.5rem 1rem;
    font-weight: bold;
    text-align: center;
    border-radius: 6px 6px 0 0;
}

/* BBCode Code Block - Black Background with Scroll */
.bbcode-code {
    background: #000000;
    color: #a6e3a1;
    padding: 0;
    margin: 0;
    font-family: 'Monaco', 'Courier New', 'Consolas', monospace;
    font-size: 0.9rem;
    max-height: 400px;
    overflow: auto;
    display: block;
}

.bbcode-code pre {
    margin: 0;
    padding: 1rem;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.bbcode-code code {
    display: block;
    font-family: inherit;
    color: inherit;
    background: none;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* Custom scrollbar for code blocks */
.bbcode-code::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.bbcode-code::-webkit-scrollbar-track {
    background: #1a1a1a;
}

.bbcode-code::-webkit-scrollbar-thumb {
    background: #45475a;
    border-radius: 5px;
}

.bbcode-code::-webkit-scrollbar-thumb:hover {
    background: #585b70;
}

/* Firefox scrollbar */
.bbcode-code {
    scrollbar-width: thin;
    scrollbar-color: #45475a #1a1a1a;
}

/* Text formatting */
strong {
    font-weight: 700;
}

em {
    font-style: italic;
}

u {
    text-decoration: underline;
}
</style>