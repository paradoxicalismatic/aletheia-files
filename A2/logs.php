<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: member_login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$viewer = $stmt->fetch();

if (!$viewer || $viewer['role'] !== 'owner') {
    header("Location: index.php");
    exit;
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filter_type = $_GET['type'] ?? 'all';   // all | log | ban | kick | message | pm | code | link
$filter_user = trim($_GET['user'] ?? '');
$filter_from = $_GET['from'] ?? '';
$filter_to   = $_GET['to']   ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 75;

// ── Build unified event stream ────────────────────────────────────────────────
// Each source produces: [time, type, actor, target, detail, extra]

$all_events = [];

// 1. user_logs
if ($filter_type === 'all' || $filter_type === 'log') {
    $q = "SELECT l.created_at as time, 'log' as type,
                 l.username as actor, '' as target,
                 l.action as detail,
                 CONCAT('uid:', l.user_id, ' | role:', COALESCE(u.role,'?')) as extra
          FROM user_logs l
          LEFT JOIN users u ON u.id = l.user_id";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "l.username LIKE ?"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "l.created_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "l.created_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));
}

// 2. banned_users
if ($filter_type === 'all' || $filter_type === 'ban') {
    $q = "SELECT b.banned_at as time, 'ban' as type,
                 COALESCE(a.username,'system') as actor,
                 COALESCE(u.username,'#'||b.user_id) as target,
                 'user banned' as detail,
                 CONCAT('banned_id:', b.id, ' | by_id:', COALESCE(b.banned_by,'?')) as extra
          FROM banned_users b
          LEFT JOIN users u ON u.id = b.user_id
          LEFT JOIN users a ON a.id = b.banned_by";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "(u.username LIKE ? OR a.username LIKE ?)"; $p[] = "%$filter_user%"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "b.banned_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "b.banned_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));
}

// 3. kicks
if ($filter_type === 'all' || $filter_type === 'kick') {
    $q = "SELECT k.created_at as time, 'kick' as type,
                 COALESCE(a.username,'#'||k.kicked_by) as actor,
                 COALESCE(u.username,'#'||k.user_id) as target,
                 COALESCE(k.reason, 'no reason given') as detail,
                 CONCAT('kick_id:', k.id, ' | expires:', COALESCE(k.expires_at,'never')) as extra
          FROM kicks k
          LEFT JOIN users u ON u.id = k.user_id
          LEFT JOIN users a ON a.id = k.kicked_by";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "(u.username LIKE ? OR a.username LIKE ?)"; $p[] = "%$filter_user%"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "k.created_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "k.created_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));
}

// 4. messages
if ($filter_type === 'all' || $filter_type === 'message') {
    $q = "SELECT m.created_at as time, 'message' as type,
                 COALESCE(u.username,'#'||m.user_id) as actor,
                 '' as target,
                 SUBSTR(m.content, 1, 120) as detail,
                 CONCAT('msg_id:', m.id, CASE WHEN m.reply_to_message_id IS NOT NULL THEN ' | reply_to:'||m.reply_to_message_id ELSE '' END) as extra
          FROM messages m
          LEFT JOIN users u ON u.id = m.user_id";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "u.username LIKE ?"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "m.created_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "m.created_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $q .= ' ORDER BY m.created_at DESC LIMIT 500';
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));
}

// 5. private_messages
if ($filter_type === 'all' || $filter_type === 'pm') {
    $q = "SELECT pm.created_at as time, 'pm' as type,
                 COALESCE(s.username,'#'||pm.sender_id) as actor,
                 COALESCE(r.username,'#'||pm.receiver_id) as target,
                 SUBSTR(pm.content, 1, 120) as detail,
                 CONCAT('pm_id:', pm.id, ' | read:', pm.is_read) as extra
          FROM private_messages pm
          LEFT JOIN users s ON s.id = pm.sender_id
          LEFT JOIN users r ON r.id = pm.receiver_id";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "(s.username LIKE ? OR r.username LIKE ?)"; $p[] = "%$filter_user%"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "pm.created_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "pm.created_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $q .= ' ORDER BY pm.created_at DESC LIMIT 500';
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));
}

// 6. member_codes — created
if ($filter_type === 'all' || $filter_type === 'code') {
    $q = "SELECT mc.created_at as time, 'code_created' as type,
                 COALESCE(u.username,'#'||mc.created_by) as actor,
                 '' as target,
                 CONCAT('code: ', mc.code) as detail,
                 CONCAT('code_id:', mc.id, ' | used:', mc.is_used) as extra
          FROM member_codes mc
          LEFT JOIN users u ON u.id = mc.created_by";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "u.username LIKE ?"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "mc.created_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "mc.created_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));

    // codes used
    $q2 = "SELECT mc.used_at as time, 'code_used' as type,
                  COALESCE(u.username,'#'||mc.used_by) as actor,
                  '' as target,
                  CONCAT('used code: ', mc.code) as detail,
                  CONCAT('code_id:', mc.id) as extra
           FROM member_codes mc
           LEFT JOIN users u ON u.id = mc.used_by
           WHERE mc.is_used = 1 AND mc.used_at IS NOT NULL";
    $w2 = []; $p2 = [];
    if ($filter_user) { $w2[] = "u.username LIKE ?"; $p2[] = "%$filter_user%"; }
    if ($filter_from) { $w2[] = "mc.used_at >= ?"; $p2[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w2[] = "mc.used_at <= ?"; $p2[] = $filter_to   . ' 23:59:59'; }
    if ($w2) $q2 .= ' AND ' . implode(' AND ', $w2);
    $s2 = $pdo->prepare($q2); $s2->execute($p2);
    $all_events = array_merge($all_events, $s2->fetchAll(PDO::FETCH_ASSOC));
}

// 7. links
if ($filter_type === 'all' || $filter_type === 'link') {
    $q = "SELECT l.created_at as time, 'link_added' as type,
                 COALESCE(l.added_by,'unknown') as actor,
                 '' as target,
                 CONCAT('[', COALESCE(c.name,'?'), '] ', l.title, ' — ', l.url) as detail,
                 CONCAT('link_id:', l.id) as extra
          FROM links l
          LEFT JOIN categories c ON c.id = l.category_id";
    $w = []; $p = [];
    if ($filter_user) { $w[] = "l.added_by LIKE ?"; $p[] = "%$filter_user%"; }
    if ($filter_from) { $w[] = "l.created_at >= ?"; $p[] = $filter_from . ' 00:00:00'; }
    if ($filter_to)   { $w[] = "l.created_at <= ?"; $p[] = $filter_to   . ' 23:59:59'; }
    if ($w) $q .= ' WHERE ' . implode(' AND ', $w);
    $s = $pdo->prepare($q); $s->execute($p);
    $all_events = array_merge($all_events, $s->fetchAll(PDO::FETCH_ASSOC));
}

// ── Sort all events by time desc, paginate ───────────────────────────────────
usort($all_events, fn($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

$total       = count($all_events);
$total_pages = max(1, ceil($total / $per_page));
$page        = min($page, $total_pages);
$events      = array_slice($all_events, ($page - 1) * $per_page, $per_page);

// ── Summary counts ───────────────────────────────────────────────────────────
$type_counts = [];
foreach ($all_events as $e) {
    $t = $e['type'];
    $type_counts[$t] = ($type_counts[$t] ?? 0) + 1;
}
arsort($type_counts);

// ── Helper ───────────────────────────────────────────────────────────────────
function timeAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return $diff . 's ago';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($ts));
}

function badge(string $type): array {
    return match($type) {
        'ban'          => ['red',    '⊘ ban'],
        'kick'         => ['orange', '↑ kick'],
        'log'          => ['blue',   '→ log'],
        'message'      => ['purple', '✦ msg'],
        'pm'           => ['teal',   '✉ pm'],
        'code_created' => ['gold',   '⬡ code+'],
        'code_used'    => ['gold',   '⬡ code✓'],
        'link_added'   => ['green',  '⊕ link'],
        default        => ['dim',    $type],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log: <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07050f;
            --surface: #0e0b1a;
            --surface2: #131020;
            --surface3: #191528;
            --border: rgba(139,92,246,0.14);
            --border2: rgba(139,92,246,0.28);
            --primary: #a78bfa;
            --primary-bright: #c4b5fd;
            --accent: #7c3aed;
            --text: #ddd9f0;
            --text-muted: #67637e;
            --text-dim: #38344e;
            --red:    #f87171;
            --orange: #fb923c;
            --green:  #4ade80;
            --blue:   #60a5fa;
            --teal:   #2dd4bf;
            --gold:   #fbbf24;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { height: 100%; }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100%;
            padding: 0;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0,0,0,0.025) 3px, rgba(0,0,0,0.025) 4px);
            pointer-events: none;
            z-index: 200;
        }

        /* ── Top bar ── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
        }

        .brand { color: var(--primary); letter-spacing: 0.12em; }
        .sep { width: 1px; height: 14px; background: var(--border2); }
        .page-title { color: var(--text-muted); }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.68rem;
        }

        .owner-badge {
            color: var(--gold);
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.2);
            border-radius: 5px;
            padding: 3px 9px;
        }

        .back-link {
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary); }

        /* ── Page wrapper ── */
        .wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 28px 60px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ── Summary chips ── */
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .summary-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.66rem;
            text-decoration: none;
            border: 1px solid var(--border);
            background: var(--surface2);
            color: var(--text-muted);
            transition: all 0.15s;
        }
        .summary-chip:hover, .summary-chip.active {
            border-color: var(--border2);
            color: var(--primary-bright);
            background: rgba(124,58,237,0.12);
        }
        .summary-chip .cnt {
            background: rgba(139,92,246,0.15);
            border-radius: 4px;
            padding: 1px 6px;
            color: var(--primary);
        }

        /* ── Filter bar ── */
        .filter-bar {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
        }

        .filter-bar form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }

        .ff { display: flex; flex-direction: column; gap: 4px; }

        .fl {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.54rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-dim);
        }

        .filter-bar input,
        .filter-bar select {
            background: rgba(139,92,246,0.05);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--text);
            padding: 6px 10px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            outline: none;
            height: 30px;
            transition: border-color 0.2s;
        }
        .filter-bar input:focus,
        .filter-bar select:focus { border-color: var(--border2); }
        .filter-bar select option { background: #131020; }

        .fbtn {
            height: 30px;
            padding: 0 14px;
            border-radius: 7px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent), #6d28d9);
            color: #fff;
            transition: opacity 0.2s;
        }
        .fbtn:hover { opacity: 0.85; }

        .freset {
            height: 30px;
            padding: 0 12px;
            border-radius: 7px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-dim);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: color 0.2s, border-color 0.2s;
        }
        .freset:hover { color: var(--primary); border-color: var(--border2); }

        /* ── Meta line ── */
        .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.63rem;
            color: var(--text-dim);
        }
        .meta span { color: var(--text-muted); }

        /* ── Table ── */
        .tbl-wrap {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        table { width: 100%; border-collapse: collapse; }

        thead { background: var(--surface3); border-bottom: 1px solid var(--border); }

        th {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.55rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--text-dim);
            padding: 9px 14px;
            text-align: left;
            font-weight: 400;
        }

        tbody tr {
            border-bottom: 1px solid rgba(139,92,246,0.04);
            transition: background 0.12s;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: rgba(139,92,246,0.035); }

        td {
            padding: 8px 14px;
            font-size: 0.77rem;
            vertical-align: top;
        }

        .t-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.62rem;
            color: var(--text-dim);
            white-space: nowrap;
        }

        .t-actor { font-weight: 600; color: var(--text); white-space: nowrap; }

        .arrow { color: var(--text-dim); margin: 0 4px; font-size: 0.7rem; }

        .t-target { color: var(--text-muted); font-size: 0.75rem; }

        .t-detail {
            color: var(--text-muted);
            font-size: 0.73rem;
            line-height: 1.45;
            max-width: 380px;
            word-break: break-word;
        }

        .t-extra {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.58rem;
            color: var(--text-dim);
            white-space: nowrap;
        }

        /* Action badge */
        .badge {
            display: inline-flex;
            align-items: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.62rem;
            padding: 3px 8px;
            border-radius: 5px;
            white-space: nowrap;
        }

        .badge.red    { background: rgba(248,113,113,0.1);  border: 1px solid rgba(248,113,113,0.22); color: var(--red); }
        .badge.orange { background: rgba(251,146,60,0.1);   border: 1px solid rgba(251,146,60,0.22);  color: var(--orange); }
        .badge.green  { background: rgba(74,222,128,0.1);   border: 1px solid rgba(74,222,128,0.22);  color: var(--green); }
        .badge.blue   { background: rgba(96,165,250,0.1);   border: 1px solid rgba(96,165,250,0.22);  color: var(--blue); }
        .badge.teal   { background: rgba(45,212,191,0.1);   border: 1px solid rgba(45,212,191,0.22);  color: var(--teal); }
        .badge.purple { background: rgba(167,139,250,0.1);  border: 1px solid rgba(167,139,250,0.22); color: var(--primary); }
        .badge.gold   { background: rgba(251,191,36,0.08);  border: 1px solid rgba(251,191,36,0.22);  color: var(--gold); }
        .badge.dim    { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); color: var(--text-dim); }

        /* Empty */
        .empty {
            padding: 52px;
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
            color: var(--text-dim);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pb {
            height: 28px;
            min-width: 28px;
            padding: 0 9px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.67rem;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.12s;
        }
        .pb:hover { border-color: var(--border2); color: var(--primary); }
        .pb.active { background: rgba(124,58,237,0.2); border-color: rgba(139,92,246,0.45); color: var(--primary-bright); }
        .pb.off { opacity: 0.25; pointer-events: none; }

        /* ── Relative time pulse ── */
        .t-time { cursor: default; }

        /* ── Expandable detail ── */
        .t-detail {
            cursor: pointer;
            max-width: 380px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: all 0.2s;
            user-select: none;
        }
        .t-detail.expanded {
            white-space: normal;
            text-overflow: unset;
            background: rgba(139,92,246,0.06);
            border-radius: 4px;
            padding: 4px 6px;
        }

        /* ── Row fade-in ── */
        @keyframes rowIn {
            from { opacity: 0; transform: translateY(4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        tbody tr {
            animation: rowIn 0.18s ease both;
        }
        tbody tr:nth-child(1)  { animation-delay: 0.00s; }
        tbody tr:nth-child(2)  { animation-delay: 0.02s; }
        tbody tr:nth-child(3)  { animation-delay: 0.04s; }
        tbody tr:nth-child(4)  { animation-delay: 0.06s; }
        tbody tr:nth-child(5)  { animation-delay: 0.08s; }
        tbody tr:nth-child(6)  { animation-delay: 0.10s; }
        tbody tr:nth-child(7)  { animation-delay: 0.12s; }
        tbody tr:nth-child(8)  { animation-delay: 0.14s; }
        tbody tr:nth-child(9)  { animation-delay: 0.16s; }
        tbody tr:nth-child(10) { animation-delay: 0.18s; }

        /* ── Auto-refresh toggle ── */
        .refresh-toggle {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.63rem;
            color: var(--text-dim);
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: transparent;
            transition: all 0.15s;
            user-select: none;
        }
        .refresh-toggle:hover { color: var(--primary); border-color: var(--border2); }
        .refresh-toggle.on { color: var(--green); border-color: rgba(74,222,128,0.3); background: rgba(74,222,128,0.05); }
        .refresh-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .refresh-toggle.on .refresh-dot { animation: blink 1s infinite; }
        @keyframes blink { 0%,100% { opacity:1; } 50% { opacity:0.2; } }

        /* ── Actor chip ── */
        .t-actor {
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.72rem;
        }
    </style>
</head>
<body>

<!-- Top bar -->
<header class="topbar">
    <div class="topbar-left">
        <span class="brand"><?= SITE_NAME ?></span>
        <div class="sep"></div>
        <span class="page-title">// full audit log</span>
    </div>
    <div class="topbar-right">
        <span class="owner-badge">⬡ owner · <?= htmlspecialchars($viewer['username']) ?></span>
        <a href="index.php" class="back-link">← back to chat</a>
    </div>
</header>

<div class="wrap">

    <!-- Summary chips -->
    <div class="summary">
        <?php
        $all_qs = http_build_query(array_merge($_GET, ['type' => 'all', 'page' => 1]));
        $total_all = array_sum($type_counts);
        ?>
        <a href="?<?= $all_qs ?>" class="summary-chip <?= $filter_type === 'all' ? 'active' : '' ?>">
            all events <span class="cnt"><?= number_format($total_all) ?></span>
        </a>
        <?php
        $type_labels = [
            'ban'          => '⊘ bans',
            'kick'         => '↑ kicks',
            'log'          => '→ user logs',
            'message'      => '✦ messages',
            'pm'           => '✉ private messages',
            'code_created' => '⬡ codes created',
            'code_used'    => '⬡ codes used',
            'link_added'   => '⊕ links added',
        ];
        foreach ($type_labels as $tkey => $tlabel):
            $qs = http_build_query(array_merge($_GET, ['type' => $tkey, 'page' => 1]));
            $cnt = $type_counts[$tkey] ?? 0;
        ?>
        <a href="?<?= $qs ?>" class="summary-chip <?= $filter_type === $tkey ? 'active' : '' ?>">
            <?= $tlabel ?> <span class="cnt"><?= number_format($cnt) ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
        <form method="GET">
            <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
            <div class="ff">
                <div class="fl">Username</div>
                <input type="text" name="user" placeholder="search user…" value="<?= htmlspecialchars($filter_user) ?>" style="width:150px">
            </div>
            <div class="ff">
                <div class="fl">From</div>
                <input type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>">
            </div>
            <div class="ff">
                <div class="fl">To</div>
                <input type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>">
            </div>
            <button type="submit" class="fbtn">Apply</button>
            <a href="logs.php" class="freset">Reset</a>
        </form>
    </div>

    <!-- Meta -->
    <div class="meta">
        <div>showing <span><?= number_format(count($events)) ?></span> of <span><?= number_format($total) ?></span> events · page <span><?= $page ?></span> of <span><?= $total_pages ?></span></div>
        <div style="display:flex;align-items:center;gap:10px;"><span><?= gmdate('Y-m-d H:i') ?> UTC</span><span class="refresh-toggle" id="refreshToggle"><span class="refresh-dot"></span><span class="refresh-label">auto-refresh off</span></span></div>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
        <?php if (empty($events)): ?>
            <div class="empty"><div style="font-size:1.8rem;margin-bottom:10px;opacity:0.3">⬡</div>// no events match your filters</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Actor</th>
                    <th>Target</th>
                    <th>Detail</th>
                    <th>Meta</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $e):
                [$color, $label] = badge($e['type']);
            ?>
                <tr>
                    <td class="t-time" title="<?= htmlspecialchars(substr($e['time'] ?? '', 0, 19)) ?>"><?= htmlspecialchars(timeAgo($e['time'] ?? '1970-01-01')) ?></td>
                    <td><span class="badge <?= $color ?>"><?= $label ?></span></td>
                    <td class="t-actor"><?= htmlspecialchars($e['actor'] ?? '—') ?></td>
                    <td class="t-target"><?= htmlspecialchars($e['target'] ?: '—') ?></td>
                    <td class="t-detail" onclick="this.classList.toggle('expanded')"><?= htmlspecialchars($e['detail'] ?? '') ?></td>
                    <td class="t-extra"><?= htmlspecialchars($e['extra'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1):
        $gp = fn($p) => '?' . http_build_query(array_merge($_GET, ['page' => $p]));
    ?>
    <div class="pagination">
        <a href="<?= $gp(1) ?>"              class="pb <?= $page <= 1 ? 'off' : '' ?>">««</a>
        <a href="<?= $gp($page - 1) ?>"      class="pb <?= $page <= 1 ? 'off' : '' ?>">‹</a>
        <?php for ($i = max(1,$page-3); $i <= min($total_pages,$page+3); $i++): ?>
            <a href="<?= $gp($i) ?>" class="pb <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $gp($page + 1) ?>"      class="pb <?= $page >= $total_pages ? 'off' : '' ?>">›</a>
        <a href="<?= $gp($total_pages) ?>"    class="pb <?= $page >= $total_pages ? 'off' : '' ?>">»»</a>
    </div>
    <?php endif; ?>

</div>

<script>
// Auto-refresh
let refreshTimer = null;
const toggle = document.getElementById('refreshToggle');
if (toggle) {
    toggle.addEventListener('click', () => {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
            toggle.classList.remove('on');
            toggle.querySelector('.refresh-label').textContent = 'auto-refresh off';
        } else {
            toggle.classList.add('on');
            toggle.querySelector('.refresh-label').textContent = 'auto-refresh on';
            refreshTimer = setInterval(() => location.reload(), 15000);
        }
    });
}
</script>
</body>
</html>
