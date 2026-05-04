<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    echo "Access Denied";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Manual - psobb.io</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700&family=Rajdhani:wght@300;500;700&display=swap">
    <style>
        body { 
            background: #050505; 
            color: #ccc; 
            padding: 2rem;
            font-family: 'Rajdhani', sans-serif;
            line-height: 1.6;
        }
        h1, h2, h3 {
            color: var(--primary-color);
            font-family: 'Orbitron', sans-serif;
            margin-top: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 0.5rem;
        }
        h1 { font-size: 2rem; border-bottom: 2px solid var(--primary-color); margin-top: 0;}
        h2 { font-size: 1.5rem; }
        h3 { font-size: 1.2rem; color: #fff; border-bottom: none; margin-bottom: 0.5rem;}
        
        ul { list-style-type: none; padding-left: 0; }
        li { margin-bottom: 1rem; padding-left: 1rem; border-left: 2px solid #333; transition: border-left-color 0.2s; }
        li:hover { border-left-color: var(--primary-color); }
        
        code { 
            background: #111; 
            color: #0f0; 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-family: monospace; 
            font-size: 0.9em;
            border: 1px solid #222;
        }
        .command-block {
            background: rgba(255,255,255,0.03);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #222;
        }
        strong { color: #fff; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #333; }
        th { color: var(--primary-color); }
        tr:hover td { background: rgba(255,255,255,0.05); }

        #search-container {
            position: sticky;
            top: 0;
            background: #050505;
            padding: 1rem 0;
            border-bottom: 1px solid #333;
            margin-bottom: 1rem;
            z-index: 100;
        }
        #search-input {
            width: 100%;
            padding: 12px;
            font-size: 1.1rem;
            background: #111;
            border: 1px solid #444;
            color: #fff;
            border-radius: 4px;
            font-family: 'Rajdhani', sans-serif;
        }
        #search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(0,255,0,0.2);
        }
        .highlight {
            background: rgba(255, 255, 0, 0.2);
            color: #fff;
        }
    </style>
</head>
<body>

<div id="search-container">
    <input type="text" id="search-input" placeholder="Search thousands of commands, IDs, items..." autofocus>
</div>

<h1>Newserv Admin Manual</h1>

<h2>Console Commands</h2>

<h3>User & Account Management</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>lookup &lt;USER&gt;</code></strong><br>Find account by name or client ID.<br><em>Example:</em> <code>lookup Sonic</code></li>
        <li><strong><code>kick &lt;USER&gt;</code></strong><br>Disconnect a user immediately.<br><em>Example:</em> <code>kick Sonic</code></li>
        <li><strong><code>add-account [params]</code></strong><br>Create a new account. Params: <code>id</code>, <code>flags</code>, <code>user-flags</code>, <code>temporary</code>.<br><em>Example:</em> <code>add-account flags=ADMINISTRATOR</code></li>
        <li><strong><code>update-account &lt;ID&gt; [params]</code></strong><br>Modify account (ban, flags).<br><em>Example:</em> <code>update-account 12345678 ban-duration=1w</code></li>
        <li><strong><code>delete-account &lt;ID&gt;</code></strong><br>Permanently delete an account.</li>
        <li><strong><code>add-license &lt;ID&gt; &lt;TYPE&gt; ...</code></strong><br>Add access key (license) to account.</li>
        <li><strong><code>list-accounts</code></strong><br>List all registered accounts.</li>
        <li><strong><code>on &lt;USER&gt; cc $edit secid &lt;ID&gt;</code></strong><br>Change another player's Section ID while they are online.<br><em>Example:</em> <code>on Sonic cc $edit secid Redria</code></li>
    </ul>
</div>

<h3>Server Control</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>announce &lt;MSG&gt;</code></strong><br>Scroll message to all players.</li>
        <li><strong><code>announce-mail &lt;MSG&gt;</code></strong><br>Send Simple Mail to all online players.</li>
        <li><strong><code>reload &lt;ITEM&gt;</code></strong><br>Reload config. Items: <code>quests</code>, <code>drop-tables</code>, <code>config</code>, <code>level-tables</code>, <code>all</code>.</li>
        <li><strong><code>info-board &lt;TEXT&gt;</code></strong><br>Set info board text for current session.</li>
        <li><strong><code>exit</code></strong><br>Shutdown the server process.</li>
        <li><strong><code>on &lt;USER&gt; cc &lt;COMMAND&gt;</code></strong><br>Run chat command as another user.<br><em>Example:</em> <code>on Sonic cc $warp 00:11</code></li>
    </ul>
</div>

<h3>Tournaments (Ep3)</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>create-tournament &lt;NAME&gt; &lt;MAP&gt; &lt;RULES&gt;</code></strong><br>Create new tournament.</li>
        <li><strong><code>start-tournament &lt;NAME&gt;</code></strong><br>Start tournament matches.</li>
        <li><strong><code>describe-tournament &lt;NAME&gt;</code></strong><br>Show tournament status.</li>
        <li><strong><code>list-tournaments</code></strong><br>List active tournaments.</li>
        <li><strong><code>delete-tournament &lt;NAME&gt;</code></strong><br>Delete a tournament.</li>
    </ul>
</div>

<h2>Chat Commands (In-Game)</h2>

<h3>General & Info</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>$help</code></strong>: List available commands.</li>
        <li><strong><code>$li</code> / <code>$lobbyinfo</code></strong>: Show lobby leader/player info.</li>
        <li><strong><code>$si</code> / <code>$server_info</code></strong>: Show server uptime/version.</li>
        <li><strong><code>$debug</code></strong>: Toggle coordinates/debug info.</li>
        <li><strong><code>$bank</code></strong>: Toggle Common/Character Bank.</li>
        <li><strong><code>$matcount</code></strong>: Show material usage details.</li>
        <li><strong><code>$password &lt;PASS&gt;</code></strong>: Set game password.</li>
    </ul>
</div>

<h3>Moderation</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>$kick &lt;USER&gt;</code></strong>: Kick user.</li>
        <li><strong><code>$ban &lt;USER&gt; &lt;TIME&gt;</code></strong>: Ban user (e.g. <code>$ban Sonic 1w</code>).</li>
        <li><strong><code>$silence &lt;USER&gt;</code></strong>: Global silence for user.</li>
        <li><strong><code>$ann &lt;MSG&gt;</code></strong>: Server announcement.</li>
        <li><strong><code>$ann? &lt;MSG&gt;</code></strong>: Anonymous announcement.</li>
        <li><strong><code>$ann! &lt;MSG&gt;</code></strong>: Simple Mail announcement.</li>
        <li><strong><code>$ann?! &lt;MSG&gt;</code></strong>: Anonymous Simple Mail announcement.</li>
        <li><strong><code>$announcerares</code></strong>: Toggle rare drop announcements.</li>
    </ul>
</div>

<h3>Character & Game State</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>$warp &lt;AREA&gt;</code> / <code>$warpme</code></strong>: Warp self to area.</li>
        <li><strong><code>$warpall &lt;AREA&gt;</code></strong>: Warp everyone to area.</li>
        <li><strong><code>$edit &lt;SUB&gt; &lt;VAL&gt;</code></strong>: Edit stats.<br><em>Subs:</em> <code>atp</code>, <code>mst</code>, <code>evp</code>, <code>hp</code>, <code>dfp</code>, <code>ata</code>, <code>lck</code>, <code>meseta</code>, <code>exp</code>, <code>level</code>, <code>secid</code>, <code>namecolor</code>.</li>
        <li><strong><code>$secid &lt;ID&gt;</code></strong>: Override drop ID.</li>
        <li><strong><code>$item &lt;HEX&gt;</code> / <code>$i</code></strong>: Spawn item.</li>
        <li><strong><code>$dropmode &lt;MODE&gt;</code></strong>: Set drop mode (`client`, `server-shared`, `server-private`).</li>
        <li><strong><code>$infhp</code> / <code>$inftp</code></strong>: Infinite HP/TP.</li>
        <li><strong><code>$maxlevel</code> / <code>$minlevel</code></strong>: Set Level 200 / Level 1.</li>
        <li><strong><code>$killcount &lt;VAL&gt;</code></strong>: Set sealed item kill count.</li>
        <li><strong><code>$save</code> / <code>$loadchar</code></strong>: Force save / reload character.</li>
        <li><strong><code>$switchchar</code></strong>: Switch character without disconnect.</li>
    </ul>
</div>

<h3>Quests & Events</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>$quest &lt;ID&gt;</code></strong>: Start quest.</li>
        <li><strong><code>$event &lt;EVENT&gt;</code></strong>: Set lobby event.</li>
        <li><strong><code>$allevent &lt;EVENT&gt;</code></strong>: Set global event.</li>
        <li><strong><code>$qcall</code> / <code>$qcheck</code> / <code>$qclear</code></strong>: Quest flag management.</li>
        <li><strong><code>$qset</code> / <code>$qsync</code> / <code>$qsyncall</code></strong>: Quest sync.</li>
    </ul>
</div>

<h3>Episode 3 (Card Battle)</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>$spectate</code> / <code>$spec&gt;</code></strong>: Spectator mode.</li>
        <li><strong><code>$song &lt;ID&gt;</code> / <code>$sound</code></strong>: Play audio.</li>
        <li><strong><code>$stat</code></strong>: Battle stats.</li>
        <li><strong><code>$surrender</code></strong>: Surrender.</li>
        <li><strong><code>$unset</code></strong>: Unset card.</li>
        <li><strong><code>$dicerange &lt;MIN&gt; &lt;MAX&gt;</code></strong>: Set dice range.</li>
    </ul>
</div>

<h3>Technical / Debug</h3>
<div class="command-block">
    <ul class="searchable">
        <li><strong><code>$arrow</code></strong>: Debug arrows.</li>
        <li><strong><code>$what</code> / <code>$where</code></strong>: Location info.</li>
        <li><strong><code>$whatobj</code> / <code>$whatene</code></strong>: Identify target object/enemy.</li>
        <li><strong><code>$readmem</code> / <code>$writemem</code></strong>: Memory access.</li>
        <li><strong><code>$sc</code> / <code>$ss</code></strong>: Send raw packet.</li>
        <li><strong><code>$replay-log</code></strong>: Replay log.</li>
    </ul>
</div>

<h2>Reference Lists</h2>

<h3>Section IDs</h3>
<div class="command-block">
    <table class="searchable-table">
        <tr><th>ID</th><th>Name</th><th>Abbrev</th></tr>
        <tr><td>0</td><td>Viridia</td><td>Vir</td></tr>
        <tr><td>1</td><td>Greennill</td><td>Grn</td></tr>
        <tr><td>2</td><td>Skyly</td><td>Sky</td></tr>
        <tr><td>3</td><td>Bluefull</td><td>Blu</td></tr>
        <tr><td>4</td><td>Purplenum</td><td>Prp</td></tr>
        <tr><td>5</td><td>Pinkal</td><td>Pnk</td></tr>
        <tr><td>6</td><td>Redria</td><td>Red</td></tr>
        <tr><td>7</td><td>Oran</td><td>Orn</td></tr>
        <tr><td>8</td><td>Yellowboze</td><td>Ylw</td></tr>
        <tr><td>9</td><td>Whitill</td><td>Wht</td></tr>
    </table>
</div>

<h3>Lobby Events</h3>
<div class="command-block">
    <p class="searchable-text"><code>xmas</code>, <code>val</code>, <code>easter</code>, <code>hallo</code>, <code>sonic</code>, <code>newyear</code>, <code>summer</code>, <code>white</code>, <code>wedding</code>, <code>fall</code>, <code>s-spring</code>, <code>s-summer</code>, <code>spring</code></p>
</div>

<h3>Common Warp IDs (EP:AREA)</h3>
<div class="command-block">
    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
        <ul class="searchable">
            <li><strong>Episode 1</strong></li>
            <li>00: Pioneer 2</li>
            <li>01: Forest 1</li>
            <li>02: Forest 2</li>
            <li>03: Cave 1</li>
            <li>06: Mine 1</li>
            <li>08: Ruins 1</li>
            <li>0B: Dragon</li>
            <li>0C: De Rol Le</li>
            <li>0D: Vol Opt</li>
            <li>0E: Dark Falz</li>
            <li>0F: Lobby</li>
        </ul>
        <ul class="searchable">
            <li><strong>Episode 2</strong></li>
            <li>00: Lab</li>
            <li>01: Temple Alpha</li>
            <li>03: Spaceship Alpha</li>
            <li>05: CCA</li>
            <li>0A: Seabed Upper</li>
            <li>0C: Gal Gryphon</li>
            <li>0D: Olga Flow</li>
            <li>0E: Barba Ray</li>
            <li>11: Tower</li>
        </ul>
        <ul class="searchable">
            <li><strong>Episode 4</strong></li>
            <li>00: Pioneer 2</li>
            <li>01: Crater East</li>
            <li>06: Desert 1</li>
            <li>09: Saint Milion</li>
        </ul>
    </div>
</div>

<script>
document.getElementById('search-input').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    
    // Filter Lists
    document.querySelectorAll('.searchable li').forEach(li => {
        const text = li.textContent.toLowerCase();
        li.style.display = text.includes(term) ? '' : 'none';
    });

    // Filter Tables
    document.querySelectorAll('.searchable-table tr').forEach((tr, index) => {
        if (index === 0) return; // Skip header
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(term) ? '' : 'none';
    });
    
    // Hide empty blocks (optional polish)
    document.querySelectorAll('.command-block').forEach(block => {
        let hasVisible = false;
        // Check lists
        if (block.querySelectorAll('li:not([style*="display: none"])').length > 0) hasVisible = true;
        // Check tables
        if (block.querySelectorAll('tr:not([style*="display: none"])').length > 1) hasVisible = true; // count > 1 for header
        // Check plain text
        if (block.querySelector('.searchable-text')) {
             if (block.querySelector('.searchable-text').textContent.toLowerCase().includes(term)) hasVisible = true;
        }
    });
});
</script>

</body>
</html>
