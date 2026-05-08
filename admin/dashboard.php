<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("Location: ../login.php");
    exit;
}
$page_title = "Admin Dashboard";
include '../includes/header.php'; 

// Fetch Stats
require_once '../api/db.php';
$db = get_db();
$user_count = $db->querySingle("SELECT COUNT(*) FROM users");
?>

<style>
    /* Admin specific styles override */
    .admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }
    .admin-card {
        background: rgba(0, 0, 0, 0.6);
        border: 1px solid var(--primary-color);
        padding: 1.5rem;
        border-radius: 8px;
    }
    .admin-card h3 {
        margin-top: 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
        color: var(--secondary-color);
    }
    .console-output {
        background: #000;
        color: #0f0;
        font-family: monospace;
        padding: 1rem;
        height: 200px;
        overflow-y: auto;
        border: 1px solid #333;
        margin-top: 1rem;
        white-space: pre-wrap;
    }
    .action-btn {
        background: rgba(255, 68, 68, 0.2);
        border: 1px solid #ff4444;
        color: #ff4444;
        padding: 0.25rem 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .action-btn:hover {
        background: #ff4444;
        color: white;
    }
    .success-btn {
        background: rgba(0, 200, 81, 0.2);
        border: 1px solid #00C851;
        color: #00C851;
    }
    .success-btn:hover {
        background: #00C851;
        color: white;
    }
</style>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Admin Dashboard</h1>
            <div style="font-size:0.9rem; opacity:0.7;">Logged in as <?php echo htmlspecialchars($_SESSION['user']['username']); ?> (ID: <?php echo $_SESSION['user']['account_id']; ?>)</div>
        </div>
        <div style="display:flex; flex-wrap: wrap; gap: 10px;">
            <a href="mods.php" class="dl-btn" style="text-decoration:none; display:flex; align-items:center; background: var(--pso-blue); color: #000;">Manage Mods</a>
            <a href="mission_manager.php" class="dl-btn success-btn" style="text-decoration:none; display:flex; align-items:center;">Manage Missions</a>
            <a href="telemetry.php" class="dl-btn" style="text-decoration:none; display:flex; align-items:center; border-color:#00ffcc; color:#00ffcc;">Telemetry</a>
            <button onclick="window.open('manual_window.php', 'AdminManual', 'width=600,height=800');" class="dl-btn">Admin Manual</button>
        </div>
    </div>

    <div class="admin-grid">
        <!-- Statistics -->
        <div class="admin-card">
            <h3>Account Stats</h3>
            <div style="font-size: 2rem; font-weight: bold; color: var(--secondary-color); text-align: center;">
                <?php echo $user_count; ?>
            </div>
            <p style="text-align: center; margin-top: 0.5rem; opacity: 0.8;">Registered Accounts</p>
        </div>

        <div class="admin-card">
            <h3>Online Stat</h3>
            <div style="font-size: 2rem; font-weight: bold; color: #00C851; text-align: center;" id="client-count-admin">
                --
            </div>
            <p style="text-align: center; margin-top: 0.5rem; opacity: 0.8;">Players Online</p>
        </div>

        <!-- Server Control -->
        <div class="admin-card">
            <h3>Server Broadcast</h3>
            <p>Send a message to all connected players.</p>
            <form id="announce-form" onsubmit="sendAnnouncement(event)">
                <div class="form-group">
                    <input type="text" id="announce-msg" placeholder="Message to server..." required style="width: 100%; padding: 8px;">
                </div>
                <button type="submit" class="dl-btn success-btn" style="width:100%">Broadcast</button>
            </form>
        </div>

        <!-- Reward Reset -->
        <div class="admin-card">
            <h3>Reset Reward Claim</h3>
            <p>Refund a claimed milestone for a player.</p>
            <form id="reset-claim-form" onsubmit="resetClaim(event)">
                <div style="display:flex; flex-wrap: wrap; gap:10px; margin-bottom: 5px;">
                    <input type="text" id="rc-cname" list="claimed-chars-list" placeholder="Search Character Name..." required style="flex-grow:1; padding: 8px;">
                    <input type="number" id="rc-count" placeholder="# to Revert" value="1" min="1" required style="width: 100px; padding: 8px;">
                </div>
                <div style="display:flex; gap:10px; margin-bottom: 10px;">
                    <input type="number" id="rc-aid" placeholder="Account ID (Auto-filled)" required readonly style="width: 100%; padding: 8px; background: rgba(255,255,255,0.05); color: #888;">
                </div>
                <datalist id="claimed-chars-list"></datalist>
                <button type="submit" class="dl-btn" style="border-color: #ff8800; background: rgba(255, 136, 0, 0.2); color: #ffaa44; width: 100%;">Reset Claim</button>
            </form>
            <div id="rc-out" style="margin-top: 10px; font-size: 0.9em;"></div>
        </div>

        <!-- Terminal -->
        <div class="admin-card" style="grid-column: span 2;">
            <h3>
                Console Command
                <button onclick="window.open('console_window.php', 'Console', 'width=800,height=600');" 
                        class="dl-btn" 
                        style="float:right; font-size: 0.7rem; padding: 2px 8px; margin-top: -2px;">
                    Popout
                </button>
            </h3>
            <p>Execute raw shell commands (Use with caution).</p>
            <form id="console-form" onsubmit="runConsole(event)">
                <div style="display:flex; flex-wrap: wrap; gap:10px;">
                    <input type="text" id="console-cmd" placeholder="Command (e.g. reload, kick <id>)" style="flex-grow:1; padding: 8px;">
                    <button type="submit" class="dl-btn">Run</button>
                </div>
            </form>
            <div id="console-out" class="console-output">Ready...</div>
        </div>

        <!-- Player Management -->
        <div class="admin-card" style="grid-column: span 3;">
            <h3>Online Players</h3>
            <div style="overflow-x: auto;">
                <table style="width:100%; min-width: 600px; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid #333;">
                            <th>Name</th>
                            <th>Level</th>
                            <th>Class</th>
                            <th>Section ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="admin-player-list">
                        <tr><td colspan="5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <button onclick="refreshPlayerList()" class="dl-btn" style="margin-top:1rem; font-size:0.8rem;">Refresh List</button>
        </div>

        <!-- All Accounts -->
        <div class="admin-card" style="grid-column: span 3;">
            <h3>All Registered Accounts</h3>
            <div style="overflow-x: auto; max-height: 400px;">
                <table style="width:100%; min-width: 600px; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid #333; position: sticky; top: 0; background: rgba(0,0,0,0.9);">
                            <th>Account ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Discord ID</th>
                            <th>Created At</th>
                            <th>Flags</th>
                            <th>Last Char</th>
                        </tr>
                    </thead>
                    <tbody id="admin-accounts-list">
                        <tr><td colspan="7">Loading accounts...</td></tr>
                    </tbody>
                </table>
            </div>
            <button onclick="refreshAccountsList()" class="dl-btn" style="margin-top:1rem; font-size:0.8rem;">Refresh List</button>
        </div>
    </div>
</main>

<script>
async function sendAnnouncement(e) {
    e.preventDefault();
    const msg = document.getElementById('announce-msg').value;
    await execCommand(`announce ${msg}`);
    document.getElementById('announce-msg').value = '';
    logConsole(`Broadcasted: ${msg}`);
}

let characterMap = {};

async function initClaimReset() {
    try {
        const res = await fetch('/api/admin_get_claimed_characters.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.success && data.characters) {
            const datalist = document.getElementById('claimed-chars-list');
            data.characters.forEach(char => {
                const opt = document.createElement('option');
                opt.value = char.character_name;
                datalist.appendChild(opt);
                
                // Store mapping of char name -> account ID for auto-fill
                characterMap[char.character_name] = char.account_id;
            });
            
            // Auto fill Account ID when a character is selected
            document.getElementById('rc-cname').addEventListener('input', function(e) {
                const aidInput = document.getElementById('rc-aid');
                if (characterMap[this.value]) {
                    aidInput.value = characterMap[this.value];
                } else {
                    aidInput.value = '';
                }
            });
        }
    } catch (e) {
        console.error("Failed to load autocomplete claims", e);
    }
}

async function resetClaim(e) {
    e.preventDefault();
    const aid = document.getElementById('rc-aid').value;
    const cname = document.getElementById('rc-cname').value;
    const count = document.getElementById('rc-count').value;
    const out = document.getElementById('rc-out');
    
    out.style.color = 'var(--text-color)';
    out.textContent = 'Resetting...';
    
    try {
        const res = await fetch('/api/admin_reset_claim.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.getCSRFToken()},
            body: JSON.stringify({account_id: aid, character_name: cname, count: count})
        });
        const data = await res.json();
        
        if (data.success) {
            out.style.color = '#00C851';
            out.textContent = data.message;
            document.getElementById('reset-claim-form').reset();
            // Re-fetch autocomplete just in case they cleared out a character entirely
            document.getElementById('claimed-chars-list').innerHTML = '';
            initClaimReset();
        } else {
            out.style.color = '#ff4444';
            out.textContent = data.error || 'Failed to reset claim.';
        }
    } catch (err) {
        out.style.color = '#ff4444';
        out.textContent = 'Connection error.';
    }
}

async function runConsole(e) {
    e.preventDefault();
    const cmd = document.getElementById('console-cmd').value;
    if(!cmd) return;
    await execCommand(cmd);
    document.getElementById('console-cmd').value = '';
}

async function execCommand(cmd) {
    const out = document.getElementById('console-out');
    out.textContent += `\n> ${cmd}...`;
    out.scrollTop = out.scrollHeight;

    try {
        const res = await fetch('/api/admin_exec.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.getCSRFToken()},
            body: JSON.stringify({command: cmd})
        });
        const data = await res.json();
        
        if (data.result) {
            out.textContent += `\n${data.result}`;
        } else if (data.error) {
            out.textContent += `\nError: ${data.error}`;
        }
    } catch (e) {
        out.textContent += `\nConnection Failed`;
    }
    out.scrollTop = out.scrollHeight;
}

function logConsole(text) {
    const out = document.getElementById('console-out');
    out.textContent += `\n[Info] ${text}`;
    out.scrollTop = out.scrollHeight;
}

async function refreshPlayerList() {
    try {
        const res = await fetch('/api/summary.php', { credentials: 'same-origin' });
        const data = await res.json();
        const tbody = document.getElementById('admin-player-list');
        tbody.innerHTML = '';

        if (!data.Clients || data.Clients.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5">No players online.</td></tr>';
            return;
        }

        data.Clients.forEach(c => {
            if (!c.Name) return; 
            // We need AccountID to kick! 
            // The /y/summary from newserv returns ClientID as 'ID', which kick uses.
            
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            row.innerHTML = `
                <td style="padding:0.5rem;">${c.Name}</td>
                <td style="padding:0.5rem;">${c.Level}</td>
                <td style="padding:0.5rem;">${c.Class}</td>
                <td style="padding:0.5rem;">${c.SectionID}</td>
                <td style="padding:0.5rem;">
                    <button class="action-btn" onclick="kickUser('${c.ID}', '${c.Name}')">Kick</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (e) {
        console.error(e);
    }
}

async function kickUser(id, name) {
    if (!id) {
        alert("Cannot determine User ID for " + name);
        return;
    }
    if(!confirm(`Kick ${name} (ID: ${id})?`)) return;
    await execCommand(`kick ${id}`);
}

async function refreshAccountsList() {
    try {
        const res = await fetch('/api/admin_get_accounts.php', { credentials: 'same-origin' });
        const data = await res.json();
        const tbody = document.getElementById('admin-accounts-list');
        tbody.innerHTML = '';

        if (!data.success || !data.accounts || data.accounts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7">No accounts found.</td></tr>';
            return;
        }

        data.accounts.forEach(a => {
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
            
            let flagsStr = a.Flags ? '0x' + a.Flags.toString(16).toUpperCase() : 'None';
            let name = a.WebUsername || (a.BBLicenses && a.BBLicenses[0] ? a.BBLicenses[0].UserName : 'Unknown');
            let email = a.WebEmail || '-';
            let discordId = a.WebDiscordID || '-';
            let created = a.WebCreatedAt ? new Date(a.WebCreatedAt).toLocaleString() : '-';
            let lastChar = a.LastPlayerName || '-';

            row.innerHTML = `
                <td style="padding:0.5rem; font-family:monospace;">${a.AccountID || '-'}</td>
                <td style="padding:0.5rem;">${name}</td>
                <td style="padding:0.5rem;">${email}</td>
                <td style="padding:0.5rem; font-family:monospace;">${discordId}</td>
                <td style="padding:0.5rem;">${created}</td>
                <td style="padding:0.5rem; font-family:monospace;">${flagsStr}</td>
                <td style="padding:0.5rem;">${lastChar}</td>
            `;
            tbody.appendChild(row);
        });
    } catch (e) {
        console.error(e);
        const tbody = document.getElementById('admin-accounts-list');
        if (tbody) tbody.innerHTML = '<tr><td colspan="6">Error loading accounts.</td></tr>';
    }
}

// Init
refreshPlayerList();
refreshAccountsList();
initClaimReset();
</script>

<?php include '../includes/footer.php'; ?>
