<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aletheia</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0d0b14;
    --surface: #161222;
    --surface2: #1e1830;
    --border: rgba(139, 92, 246, 0.18);
    --primary: #a78bfa;
    --primary-bright: #c4b5fd;
    --primary-glow: rgba(167, 139, 250, 0.25);
    --accent: #7c3aed;
    --text: #e2dff0;
    --text-muted: #7c7a8e;
    --text-dim: #4a4760;
    --danger: #f87171;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Sora', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 24px;
    overflow-x: hidden;
  }

  /* Atmospheric background */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse 60% 50% at 20% 50%, rgba(124, 58, 237, 0.12) 0%, transparent 70%),
      radial-gradient(ellipse 40% 60% at 80% 20%, rgba(167, 139, 250, 0.07) 0%, transparent 60%),
      radial-gradient(ellipse 30% 40% at 70% 80%, rgba(109, 40, 217, 0.08) 0%, transparent 60%);
    pointer-events: none;
    z-index: 0;
  }

  /* Subtle noise texture */
  body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");
    opacity: 0.4;
    pointer-events: none;
    z-index: 0;
  }

  .container {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px;
    max-width: 860px;
    width: 100%;
    background: var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow:
      0 0 0 1px rgba(139, 92, 246, 0.15),
      0 32px 80px rgba(0, 0, 0, 0.6),
      0 0 120px rgba(124, 58, 237, 0.08);
    animation: fadeIn 0.6s ease both;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .left, .right {
    background: var(--surface);
    padding: 44px 40px;
  }

  .left {
    border-radius: 20px 0 0 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .right {
    border-radius: 0 20px 20px 0;
    background: var(--surface2);
  }

  /* Logo / Brand */
  .brand {
    margin-bottom: 32px;
  }

  .brand-eyebrow {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.65rem;
    letter-spacing: 0.2em;
    color: var(--primary);
    text-transform: uppercase;
    margin-bottom: 10px;
    opacity: 0.8;
  }

  .brand h1 {
    font-size: 2.6rem;
    font-weight: 700;
    letter-spacing: -0.04em;
    color: var(--primary-bright);
    line-height: 1;
    text-shadow: 0 0 40px rgba(167, 139, 250, 0.4);
  }

  .brand p {
    margin-top: 14px;
    font-size: 0.88rem;
    color: var(--text-muted);
    line-height: 1.65;
    font-weight: 300;
    max-width: 260px;
  }

  /* Divider */
  .divider {
    height: 1px;
    background: linear-gradient(90deg, var(--border) 0%, transparent 100%);
    margin: 28px 0;
  }

  /* Buttons */
  .buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 18px;
    border-radius: 10px;
    font-family: 'Sora', sans-serif;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    letter-spacing: 0.01em;
  }

  .btn-arrow {
    font-size: 0.8rem;
    opacity: 0.5;
    transition: transform 0.2s ease, opacity 0.2s ease;
  }

  .btn:hover .btn-arrow {
    transform: translateX(4px);
    opacity: 1;
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, #6d28d9 100%);
    color: #fff;
    box-shadow: 0 4px 20px rgba(124, 58, 237, 0.35);
  }
  .btn-primary:hover {
    box-shadow: 0 6px 28px rgba(124, 58, 237, 0.55);
    transform: translateY(-1px);
  }

  .btn-ghost {
    background: rgba(139, 92, 246, 0.07);
    color: var(--text);
    border: 1px solid var(--border);
  }
  .btn-ghost:hover {
    background: rgba(139, 92, 246, 0.14);
    border-color: rgba(139, 92, 246, 0.35);
    color: var(--primary-bright);
  }

  /* Status indicator */
  .status {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 28px;
    font-size: 0.75rem;
    color: var(--text-dim);
    font-family: 'JetBrains Mono', monospace;
  }

  .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 8px #4ade80;
    animation: pulse 2.5s ease infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
  }

  /* Right side — Rules */
  .right h2 {
    font-size: 0.7rem;
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--text-dim);
    margin-bottom: 22px;
  }

  .rule-box {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 14px 16px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(139, 92, 246, 0.1);
    margin-bottom: 10px;
    transition: border-color 0.2s, background 0.2s;
  }

  .rule-box:hover {
    background: rgba(139, 92, 246, 0.06);
    border-color: rgba(139, 92, 246, 0.25);
  }

  .rule-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.7rem;
    color: var(--primary);
    opacity: 0.6;
    padding-top: 2px;
    flex-shrink: 0;
  }

  .rule-text {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.55;
    font-weight: 300;
  }

  .rule-text strong {
    color: var(--text);
    font-weight: 600;
  }

  .rule-box.danger {
    border-color: rgba(248, 113, 113, 0.2);
    background: rgba(248, 113, 113, 0.04);
  }

  .rule-box.danger:hover {
    border-color: rgba(248, 113, 113, 0.4);
    background: rgba(248, 113, 113, 0.07);
  }

  .rule-box.danger .rule-num {
    color: var(--danger);
    opacity: 0.7;
  }

  /* Footer note */
  .footer-note {
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid var(--border);
    font-size: 0.72rem;
    color: var(--text-dim);
    font-family: 'JetBrains Mono', monospace;
    line-height: 1.6;
  }

  @media (max-width: 640px) {
    .container {
      grid-template-columns: 1fr;
      gap: 1px;
    }
    .left { border-radius: 20px 20px 0 0; }
    .right { border-radius: 0 0 20px 20px; }
    .brand h1 { font-size: 2rem; }
  }
</style>
</head>
<body>
<div class="container">

  <div class="left">
    <div class="brand">
      <div class="brand-eyebrow">  Place to chat and stuff</div>
      <h1>Aletheia</h1>
      <p>A privacy-focused chat community. Free speech (but read rules)</p>
    </div>

    <div class="divider"></div>

    <div class="buttons">
      <a href="register.php" class="btn btn-primary">
        Create Account
        <span class="btn-arrow">→</span>
      </a>
      <a href="member_login.php" class="btn btn-ghost">
        Member Login
        <span class="btn-arrow">→</span>
      </a>
      <a href="guest_login.php" class="btn btn-ghost">
        Guest Access
        <span class="btn-arrow">→</span>
      </a>
    </div>

    <div class="status">
      <span class="status-dot"></span>
      Aletheia up ;)
    </div>
  </div>


  <div class="right">
    <h2>Community Rules</h2>

    <div class="rule-box">
      <span class="rule-num">01</span>
      <div class="rule-text"><strong>No scamming.</strong> Fraud, phishing, or linking to scam sites isn't.</div>
    </div>

    <div class="rule-box">
      <span class="rule-num">02</span>
      <div class="rule-text"><strong>No spamming.</strong> Repeated messages or mass promotion isn't allowed.</div>
    </div>

    <div class="rule-box danger">
      <span class="rule-num">03</span>
      <div class="rule-text"><strong>No CSAM.</strong> Zero tolerance.</div>
    </div>

    <div class="rule-box">
      <span class="rule-num">04</span>
      <div class="rule-text"><strong>Everything else goes.</strong> Say what you think, argue passionately, be weird, just don't ruin it for others.</div>
    </div>

    <div class="footer-note">
Place where u can talk and be secret and stuff
    <br>
PM's are not yet encrypted!!
    </div>
  </div>

</div>
</body>
</html>
