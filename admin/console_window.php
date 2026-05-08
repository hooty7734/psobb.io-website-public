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
    <title>Server Console - psobb.io</title>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700&family=Rajdhani:wght@300;500;700&display=swap">
    <style>
        body { 
            background: #000; 
            color: #fff; 
            padding: 1rem;
            display: flex;
            flex-direction: column;
            height: 100vh;
            margin: 0;
            box-sizing: border-box;
        }
        .console-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .console-output {
            background: #111;
            color: #0f0;
            font-family: monospace;
            padding: 1rem;
            flex-grow: 1;
            overflow-y: auto;
            border: 1px solid #333;
            white-space: pre-wrap;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
            font-size: 14px;
        }
        .input-group {
            display: flex;
            gap: 10px;
        }
        input[type="text"] {
            flex-grow: 1;
            padding: 10px;
            background: #222;
            border: 1px solid #444;
            color: #fff;
            font-family: monospace;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        button {
            padding: 10px 20px;
            background: var(--primary-color);
            border: none;
            color: #000;
            font-weight: bold;
            cursor: pointer;
            text-transform: uppercase;
        }
        button:hover {
            background: #fff;
        }
    </style>
</head>
<body>

    <div class="console-container">
        <h2 style="margin:0; font-family: 'Orbitron', sans-serif; color: var(--primary-color);">SERVER CONSOLE</h2>
        
        <div id="console-out" class="console-output">Newserv Console Ready...</div>

        <form onsubmit="runConsole(event)">
            <div class="input-group">
                <input type="text" id="console-cmd" placeholder="Enter command..." autofocus autocomplete="off">
                <button type="submit">Send</button>
            </div>
        </form>
    </div>

<script>
// Command History Setup
const MAX_HISTORY = 50;
let commandHistory = JSON.parse(localStorage.getItem('console_history') || '[]');
let historyIndex = commandHistory.length;

async function runConsole(e) {
    e.preventDefault();
    const cmdInput = document.getElementById('console-cmd');
    const cmd = cmdInput.value;
    if(!cmd) return;
    
    // Save to history
    if (cmd.trim()) {
        if (commandHistory.length === 0 || commandHistory[commandHistory.length - 1] !== cmd) {
            commandHistory.push(cmd);
            if (commandHistory.length > MAX_HISTORY) {
                commandHistory.shift();
            }
            localStorage.setItem('console_history', JSON.stringify(commandHistory));
        }
        historyIndex = commandHistory.length;
    }

    await execCommand(cmd);
    cmdInput.value = '';
}

async function execCommand(cmd) {
    const out = document.getElementById('console-out');
    out.textContent += `\n> ${cmd}`;
    out.scrollTop = out.scrollHeight;

    try {
        const res = await fetch('../api/admin_exec.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''},
            body: JSON.stringify({command: cmd})
        });
        const data = await res.json();
        
        if (data.result) {
            out.textContent += `\n${data.result}\n`;
        } else if (data.error) {
            out.textContent += `\nError: ${data.error}\n`;
        }
    } catch (e) {
        out.textContent += `\nConnection Failed: ${e}\n`;
    }
    out.scrollTop = out.scrollHeight;
}

const cmdInput = document.getElementById('console-cmd');

cmdInput.addEventListener('keydown', function(e) {
    // History Navigation
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (historyIndex > 0) {
            historyIndex--;
            this.value = commandHistory[historyIndex];
        }
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (historyIndex < commandHistory.length - 1) {
            historyIndex++;
            this.value = commandHistory[historyIndex];
        } else {
            historyIndex = commandHistory.length;
            this.value = '';
        }
    } 
    // Tab Autocomplete
    else if (e.key === 'Tab') {
        e.preventDefault();
        const fullInput = this.value;
        const out = document.getElementById('console-out');
        
        // Determine Context
        let options = commands;
        let tokenToComplete = fullInput;
        let prefix = "";
        
        const parts = fullInput.split(' ');
        
        // Context: "on <Player>"
        if (parts[0] === 'on' && parts.length <= 2) {
            options = players;
            tokenToComplete = parts[1] || "";
            prefix = "on ";
        } 
        // Context: "on <Player> <Command>"
        else if (parts[0] === 'on' && parts.length === 3) {
            options = commands; // Re-use commands list for the 3rd argument
            tokenToComplete = parts[2] || "";
            prefix = `on ${parts[1]} `;
        }
        // Default: Command context (start of line)
        else if (parts.length === 1) {
            options = commands;
            tokenToComplete = parts[0];
            prefix = "";
        } else {
            // Unknown context, do nothing
            return;
        }

        const matches = options.filter(c => c.toLowerCase().startsWith(tokenToComplete.toLowerCase()));
        
        if (matches.length === 1) {
            // Apply completion
            // If it's a player name with spaces, wrap in quotes? 
            // Newserv usually handles spaces if they are the last arg, but safe to just insert.
            let completion = matches[0];
            if (completion.includes(' ') && parts[0] === 'on' && parts.length <= 2) {
                completion = `"${completion}"`;
            }
            this.value = prefix + completion + " ";
        } else if (matches.length > 1) {
            // Find common prefix
            let common = current;
            while (matches.every(c => c.startsWith(common + matches[0][common.length]))) {
                common += matches[0][common.length];
            }
            this.value = common;
            
            // Show possibilities
            const out = document.getElementById('console-out');
            out.textContent += `\n> ${current}`;
            out.textContent += `\nPossible completions: ${matches.join(', ')}`;
            out.scrollTop = out.scrollHeight;
        }
    }
});
</script>

</body>
</html>
