// psobb-website/js/main.js
/**
 * PSOBB Website Frontend Logic
 * 
 * Handles all client-side dynamic behavior including:
 * - Session Management (Login, Logout, Dashboard State)
 * - Server Telemetry Fetching (Player counts, Active Games)
 * - DOM manipulation and layout animation (Intersection Observers)
 * - CSRF Header Injection for secure API interaction
 */

document.addEventListener('DOMContentLoaded', () => {
    window.getCSRFToken = function() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    fetchServerStats();

    // Refresh stats every 30 seconds
    setInterval(fetchServerStats, 30000);

    // If we are on the stats page, load detailed stats
    if (document.getElementById('player-list')) {
        fetchDetailedStats();
        setInterval(fetchDetailedStats, 30000);
    }

    // Login Form Handler
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.onsubmit = handleLogin;
    }

    // Check if logged in (for login page)
    if (document.getElementById('dashboard')) {
        checkLoginStatus();
    }

    // Global login UI updates
    const userStr = sessionStorage.getItem('psobb_user');
    if (userStr) {
        const teamLink = document.getElementById('nav-team-link');
        if (teamLink) teamLink.style.display = ''; // Reset display to show it

        const loginBtn = document.querySelector('.login-nav-btn');
        if (loginBtn) loginBtn.textContent = 'Dashboard';

        // MAG Feeder link - admin only
        try {
            const userData = JSON.parse(userStr);
            if (userData && userData.isAdmin) {
                const magLink = document.getElementById('nav-magfeeder-link');
                if (magLink) magLink.style.display = '';
            }
        } catch (e) { }
    }

    // Initialize Star Stream
    // initStarStream();

    // Scroll Animation Observer
    const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px 0px -50px 0px"
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe news items and widgets
    document.querySelectorAll('.news-item, .sidebar-widget, .server-status-widget').forEach(el => {
        el.style.opacity = '0'; // Initial hidden state
        observer.observe(el);
    });
});

async function handleLogin(e) {
    e.preventDefault();
    const username = e.target.username.value.toLowerCase();
    const password = e.target.password.value;
    // Check if captcha input exists and is visible
    const captchaInput = document.getElementById('captcha');
    const captcha = (captchaInput && captchaInput.value) ? captchaInput.value : '';

    const errorEl = document.getElementById('login-error');
    const submitBtn = e.target.querySelector('button');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Logging in...';
    if (errorEl) errorEl.style.display = 'none';

    try {
        const response = await fetch('/api/login.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password, captcha })
        });

        const data = await response.json();

        if (response.ok) {
            // Login Success
            sessionStorage.setItem('psobb_user', JSON.stringify(data));
            showDashboard(data);
        } else {
            // Login Failed
            // Check for CAPTCHA requirement
            if (data.captcha_required) {
                const grp = document.getElementById('captcha-group');
                if (grp) {
                    grp.style.display = 'block';
                    // Reload captcha image to be safe
                    const img = document.getElementById('captcha-img');
                    if (img) img.src = 'api/captcha.php?' + Math.random();
                }
            }
            throw new Error(data.error || 'Login failed');
        }
    } catch (error) {
        if (errorEl) {
            errorEl.textContent = error.message;
            errorEl.style.display = 'block';
        } else {
            alert(error.message);
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Login';
    }
}

function checkLoginStatus() {
    const userStr = sessionStorage.getItem('psobb_user');
    if (userStr) {
        try {
            const user = JSON.parse(userStr);
            showDashboard(user);
        } catch (e) {
            sessionStorage.removeItem('psobb_user');
        }
    }
}

function showDashboard(user) {
    const loginContainer = document.querySelector('.login-container-form');
    const dashboard = document.getElementById('dashboard');

    if (loginContainer) loginContainer.style.display = 'none';
    if (dashboard) {
        dashboard.style.display = 'block';
        const mainBox = document.querySelector('.login-container');
        if (mainBox) mainBox.classList.add('dashboard-active');

        // Handle Discord Redirect Flags
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'session_expired') {
            alert("Security Error: Your PHP Session was lost during transit. Please log out and explicitly log back in to refresh your Secure Session token.");
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('discord_linked') === '1') {
            user.discord_id = 'linked';
            sessionStorage.setItem('psobb_user', JSON.stringify(user));
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('discord_unlinked') === '1') {
            user.discord_id = null;
            sessionStorage.setItem('psobb_user', JSON.stringify(user));
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Toggle Discord Button UI
        const btnLinkDiscord = document.getElementById('btn-link-discord');
        const discordLinkedInfo = document.getElementById('discord-linked-info');
        if (btnLinkDiscord && discordLinkedInfo) {
            if (user.discord_id) {
                btnLinkDiscord.style.display = 'none';
                discordLinkedInfo.style.display = 'block';
            } else {
                btnLinkDiscord.style.display = 'block';
                discordLinkedInfo.style.display = 'none';
            }
        }

        // Populate dashboard
        const lastPlayer = (user.BBLicenses && user.BBLicenses.length > 0) ? user.BBLicenses[0].UserName : (user.LastPlayerName || 'Hunter');

        document.getElementById('dash-username-header').textContent = lastPlayer;
        document.getElementById('dash-username').textContent = lastPlayer;
        document.getElementById('dash-account-id').textContent = user.AccountID;

        document.getElementById('dash-team').textContent = user.BBTeamID ? 'Team #' + user.BBTeamID : 'None';

        const adminBtn = document.getElementById('admin-panel-btn');
        const bountyBtn = document.getElementById('bounty-board-btn');
        
        if (user.isAdmin) {
            if (adminBtn) adminBtn.style.display = 'block';
        } else {
            if (adminBtn) adminBtn.style.display = 'none';
        }
        
        // Rewards Panel Button (all players)
        const rewardsBtn = document.getElementById('rewards-panel-btn');
        if (rewardsBtn) {
            rewardsBtn.style.display = 'block';
        }

        // Bounty Board Button (all players)
        if (bountyBtn) {
            bountyBtn.style.display = 'block';
        }

        // Section ID Change Logic
        loadActiveCharacterSectionId(user.AccountID);

        // Bank Swap Logic
        loadCharacterBankSwitcher(user.AccountID);

        // Load existing display name into the alias input
        loadDisplayName();
    }
}

async function loadDisplayName() {
    const input = document.getElementById('display-name-input');
    if (!input) return;
    try {
        const res = await fetch('/api/get_display_name.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.display_name) {
            input.value = data.display_name;
        }
    } catch (e) { /* silent */ }
}

window.saveDisplayName = async function () {
    const input = document.getElementById('display-name-input');
    const btn = document.getElementById('btn-save-alias');
    const msgEl = document.getElementById('alias-message');
    const name = input.value.trim();

    if (!name) {
        msgEl.textContent = 'Please enter a display name.';
        msgEl.style.color = '#ff4444';
        msgEl.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';
    msgEl.style.display = 'none';

    try {
        const response = await fetch('/api/set_display_name.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({ display_name: name })
        });
        const data = await response.json();

        if (response.ok && data.success) {
            msgEl.textContent = '✓ ' + data.message;
            msgEl.style.color = '#00C851';
            msgEl.style.display = 'block';
            btn.textContent = 'Saved!';
            setTimeout(() => { btn.disabled = false; btn.textContent = 'Save'; }, 2000);
        } else {
            msgEl.textContent = data.error || 'Failed to update.';
            msgEl.style.color = '#ff4444';
            msgEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Save';
        }
    } catch (e) {
        msgEl.textContent = 'Connection error.';
        msgEl.style.color = '#ff4444';
        msgEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Save';
    }
};

async function loadActiveCharacterSectionId(accountId) {
    const secIdContainer = document.getElementById('section-id-change-container');
    if (!secIdContainer) return;

    secIdContainer.innerHTML = '<p>Checking for online characters...</p>';

    try {
        const response = await fetch('/api/summary.php');
        const data = await response.json();

        // Allow picking Section ID even if offline as a fallback for the admin/user
        let activeCharacter = null;
        if (data.Clients) {
            activeCharacter = data.Clients.find(c => c.AccountID === accountId && c.Name);
        }

        let html = '';
        if (activeCharacter) {
            html += `<p>Character: <strong>${escapeHtml(activeCharacter.Name)}</strong> (Level ${activeCharacter.Level})</p>
                     <p>Current Section ID: <strong class="section-id id-${(activeCharacter.SectionID || 'none').toLowerCase()}">${activeCharacter.SectionID}</strong></p>`;
            if (activeCharacter.Level > 50) {
                html += `<p style="color: #ff4444; margin-top: 10px;">Only characters level 50 and below can change their Section ID.</p>`;
                secIdContainer.innerHTML = html;
                return;
            }
        } else {
            html += `<p style="color: #ffaa00; margin-bottom: 15px;">Warning: You must be logged into a character in-game to apply a Section ID change immediately. You can still pre-select one here.</p>`;
        }

        const secIdInfo = {
            'Viridia': 'Partisans, Shots',
            'Greenill': 'Daggers, Rifles',
            'Skyly': 'Swords, Sealed J-Sword',
            'Bluefull': 'Partisans, Spread',
            'Purplenum': 'Mechguns, Units',
            'Pinkal': 'Wands, Force Weapons',
            'Redria': 'Slicers, Armors, Balanced',
            'Oran': 'Daggers, Handguns',
            'Yellowboze': 'All Weapons, Meseta',
            'Whitill': 'Slicers, High-end Rares'
        };
        const secIds = Object.keys(secIdInfo);

        html += `
            <div style="margin-top: 15px; border: 1px solid rgba(0,255,255,0.2); background: rgba(0,0,0,0.5); padding: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0; color: #00ffff;">Select New Section ID</h4>
                <div class="section-id-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 15px;">
                    ${secIds.map((id, index) => `
                        <label class="secid-option-lbl" style="cursor: pointer; text-align: center; display: flex; flex-direction: column; align-items: center; border: 1px solid ${index === 0 ? '#00ffff' : 'rgba(0,255,255,0.1)'}; padding: 6px 2px; border-radius: 4px; background: ${index === 0 ? 'rgba(0,255,255,0.1)' : 'transparent'}; transition: all 0.2s;" onclick="document.querySelectorAll('.secid-option-lbl').forEach(el=>{el.style.background='transparent';el.style.borderColor='rgba(0,255,255,0.1)'});this.style.background='rgba(0,255,255,0.1)';this.style.borderColor='#00ffff';">
                            <input type="radio" name="new-section-id" value="${id}" style="display: none;" ${index === 0 ? 'checked' : ''}>
                            <img src="/img/section_ids/${id}.png" alt="${id}" style="width: 32px; height: 32px; margin-bottom: 3px;">
                            <span style="font-size: 0.7em; font-weight: bold; color: #eee;">${id}</span>
                            <span style="font-size: 0.55em; color: #999; margin-top: 2px; line-height: 1.2;">${secIdInfo[id]}</span>
                        </label>
                    `).join('')}
                </div>
                <button id="btn-change-secid" class="dl-btn" style="width: 100%; border-color: #00ffff; background: rgba(0, 255, 255, 0.15); color: #00ffff; padding: 12px; font-weight: bold; font-family: 'Share Tech Mono', 'Segoe UI', sans-serif;">Change Section ID</button>
                <div id="secid-message" style="margin-top: 10px; display: none; font-weight: bold;"></div>
            </div>
        `;

        secIdContainer.innerHTML = html;

        const changeBtn = document.getElementById('btn-change-secid');
        if (changeBtn) {
            changeBtn.addEventListener('click', () => submitSectionIdChange(activeCharacter ? activeCharacter.Name : ''));
        }

    } catch (e) {
        secIdContainer.innerHTML = '<p style="color: #ff4444;">Failed to load active character data.</p>';
    }
}

async function submitSectionIdChange(characterName) {
    const newSecId = document.querySelector('input[name="new-section-id"]:checked').value;
    const msgEl = document.getElementById('secid-message');
    const btn = document.getElementById('btn-change-secid');

    btn.disabled = true;
    btn.textContent = "Processing...";
    msgEl.style.display = 'none';

    try {
        const response = await fetch('/api/change_section_id.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({ character_name: characterName, new_section_id: newSecId })
        });
        const data = await response.json();

        if (response.ok && data.success) {
            msgEl.textContent = data.message;
            msgEl.style.color = '#00C851';
            msgEl.style.display = 'block';
            btn.textContent = "Success";

            // Reload the character data after 2 seconds
            setTimeout(() => {
                const userStr = sessionStorage.getItem('psobb_user');
                if (userStr) {
                    const user = JSON.parse(userStr);
                    loadActiveCharacterSectionId(user.AccountID);
                }
            }, 2500);
        } else {
            msgEl.textContent = data.error || "Failed to change Section ID.";
            msgEl.style.color = '#ff4444';
            msgEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = "Change Section ID";
        }
    } catch (e) {
        msgEl.textContent = "Connection error: " + e.message;
        msgEl.style.color = '#ff4444';
        msgEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = "Change Section ID";
    }
}

async function loadCharacterBankSwitcher(accountId) {
    const bankContainer = document.getElementById('bank-swap-container');
    if (!bankContainer) return;

    bankContainer.innerHTML = '<p>Checking for online characters...</p>';

    try {
        const response = await fetch('/api/summary.php');
        const data = await response.json();

        let activeCharacter = null;
        if (data.Clients) {
            activeCharacter = data.Clients.find(c => c.AccountID === accountId && c.Name);
        }

        let html = '';
        if (!activeCharacter) {
            html += `<p style="color: #ffaa00; margin-bottom: 15px;">Warning: You must be logged into a character in-game to apply a bank switch immediately. You can still pre-select one here.</p>`;
        }

        html += `
            <div style="border: 1px solid rgba(0,255,255,0.2); background: rgba(0,0,0,0.5); padding: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0; color: #00ffff;">Bank Management</h4>
                <p style="font-size: 0.9em; margin-bottom: 15px;">
                    Select a bank to swap out your current bank with in-game. To view items in these banks, you must access the counter in the game lobby.
                </p>
                <select id="target-bank-index" style="width: 100%; padding: 10px; margin-bottom: 15px; background: rgba(0, 0, 0, 0.5); color: #fff; border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 4px; box-sizing: border-box;">
                    <option value="-1" style="background:#111; color:#fff;">Shared Bank</option>
                    ${Array.from({ length: 20 }, (_, i) => `<option value="${i}" style="background:#111; color:#fff;">Slot ${i + 1} Character Bank</option>`).join('')}
                </select>
                <button id="btn-swap-bank" class="dl-btn" style="width: 100%; border-color: #00ffff; background: rgba(0, 255, 255, 0.15); color: #00ffff; padding: 12px; font-weight: bold; font-family: 'Share Tech Mono', 'Segoe UI', sans-serif;">Swap Bank In-Game</button>
                <div id="bank-message" style="margin-top: 10px; display: none; font-weight: bold;"></div>
            </div>
        `;

        bankContainer.innerHTML = html;

        const swapBtn = document.getElementById('btn-swap-bank');
        if (swapBtn) {
            swapBtn.addEventListener('click', () => submitBankSwap(activeCharacter ? activeCharacter.Name : ''));
        }

    } catch (e) {
        bankContainer.innerHTML = '<p style="color: #ff4444;">Failed to load active character data for bank swapping.</p>';
    }
}

async function submitBankSwap(characterName) {
    const targetBank = document.getElementById('target-bank-index').value;
    const msgEl = document.getElementById('bank-message');
    const btn = document.getElementById('btn-swap-bank');

    btn.disabled = true;
    btn.textContent = "Processing...";
    msgEl.style.display = 'none';

    try {
        const response = await fetch('/api/bank_swap.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({ character_name: characterName, target_bank_index: parseInt(targetBank) })
        });

        let data;
        const rawText = await response.text();
        try {
            data = JSON.parse(rawText);
        } catch (parseErr) {
            msgEl.textContent = "Server returned invalid response: " + rawText.substring(0, 200);
            msgEl.style.color = '#ff4444';
            msgEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = "Swap Bank In-Game";
            return;
        }

        if (response.ok && data.success) {
            msgEl.textContent = data.message;
            msgEl.style.color = '#00C851';
            msgEl.style.display = 'block';
            btn.textContent = "Success";

            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = "Swap Bank In-Game";
                msgEl.style.display = 'none';
            }, 3000);
        } else {
            msgEl.textContent = data.error || "Failed to swap bank.";
            msgEl.style.color = '#ff4444';
            msgEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = "Swap Bank In-Game";
        }
    } catch (e) {
        msgEl.textContent = "Connection error: " + e.message;
        msgEl.style.color = '#ff4444';
        msgEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = "Swap Bank In-Game";
    }
}


window.logout = function () {
    sessionStorage.removeItem('psobb_user');
    window.location.reload();
}

async function fetchServerStats() {
    try {
        const response = await fetch('/api/server.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();

        // Update elements if they exist
        updateElement('server-name', data.ServerName);
        updateElement('uptime', data.Uptime);
        updateElement('client-count', data.ClientCount);
        updateElement('game-count', data.GameCount);
        if (data.BBGlobalEXPMultiplier !== undefined) {
            updateElement('rate-exp', data.BBGlobalEXPMultiplier + 'x');
        }
        if (data.ServerGlobalDropRateMultiplier !== undefined) {
            updateElement('rate-drop', data.ServerGlobalDropRateMultiplier + 'x');
        }

    } catch (error) {
        console.error('Error fetching server stats:', error);
    }
}

async function fetchDetailedStats() {
    try {
        const response = await fetch('/api/summary.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();

        // Update simple stats using the Server object from summary
        if (data.Server) {
            updateElement('server-name', data.Server.ServerName);
            updateElement('uptime', data.Server.Uptime);
            updateElement('client-count', data.Server.ClientCount);
            updateElement('game-count', data.Server.GameCount);
            if (data.Server.BBGlobalEXPMultiplier !== undefined) {
                updateElement('rate-exp', data.Server.BBGlobalEXPMultiplier + 'x');
            }
            if (data.Server.ServerGlobalDropRateMultiplier !== undefined) {
                updateElement('rate-drop', data.Server.ServerGlobalDropRateMultiplier + 'x');
            }
        }

        renderPlayerList(data.Clients);
        renderGameList(data.Games);

    } catch (error) {
        console.error('Error fetching detailed stats:', error);
    }
}

const ID_MAP = {
    'server-name': ['server-name'],
    'uptime': ['uptime', 'uptime-stats'],
    'client-count': ['client-count', 'client-count-stats'],
    'game-count': ['game-count', 'game-count-stats'],
    'rate-exp': ['rate-exp', 'rate-exp-stats'],
    'rate-drop': ['rate-drop', 'rate-drop-stats']
};

function updateElement(key, value) {
    const ids = ID_MAP[key] || [key];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    });
}

function renderPlayerList(clients) {
    const list = document.getElementById('player-list');
    if (!list) return;

    list.innerHTML = '';

    // Filter out players with no name (connecting)
    const activeClients = (clients || []).filter(c => c.Name);

    if (activeClients.length === 0) {
        list.innerHTML = '<tr><td colspan="4" style="text-align:center">No players online</td></tr>';
        return;
    }

    activeClients.forEach(c => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${escapeHtml(c.Name)}</td>
            <td>${c.Level || '-'}</td>
            <td>${c.Class || '-'}</td>
            <td><span class="section-id id-${(c.SectionID || '').toLowerCase()}">${c.SectionID || '-'}</span></td>
        `;
        list.appendChild(row);
    });
}

function renderGameList(games) {
    const list = document.getElementById('game-list');
    if (!list) return;

    list.innerHTML = '';

    const activeGames = games || [];

    if (activeGames.length === 0) {
        list.innerHTML = '<tr><td colspan="5" style="text-align:center">No active games</td></tr>';
        return;
    }

    activeGames.forEach(g => {
        const row = document.createElement('tr');
        const players = g.Players !== undefined ? g.Players : '-';
        
        let displayName = g.Name || '';
        const mode = g.Mode || 'Normal';
        const displayMode = mode === 'Normal' ? 'Extermination/Normal' : mode;
        const modeClass = `mode-${mode.toLowerCase()}`;
        const passBadge = g.HasPassword 
            ? '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(255, 170, 0, 0.1); border: 1px solid rgba(255, 170, 0, 0.4); color: #ffaa00; padding: 2px 8px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;"><i class="fas fa-lock" style="font-size: 0.85em;"></i> PRIVATE</span>'
            : '<span style="display: inline-flex; align-items: center; gap: 4px; background: rgba(0, 255, 200, 0.1); border: 1px solid rgba(0, 255, 200, 0.3); color: #00ffc8; padding: 2px 8px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px;"><i class="fas fa-unlock" style="font-size: 0.85em;"></i> OPEN</span>';

        row.innerHTML = `
            <td><span style="font-weight: 600; color: #fff; text-shadow: 0 0 5px rgba(255,255,255,0.3);">${escapeHtml(displayName)}</span></td>
            <td><span class="mode-badge ${modeClass}">${displayMode}</span></td>
            <td>${g.Episode || 'Ep1'}</td>
            <td>${g.Difficulty || 'Normal'}</td>
            <td>${players}/4</td>
            <td>${passBadge}</td>
        `;
        list.appendChild(row);
    });
}


function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, '&')
        .replace(/</g, '<')
        .replace(/>/g, '>')
        .replace(/"/g, '"')
        .replace(/'/g, '&#039;');
}

// Star stream removed for stability

window.requestDeleteAccount = function () {
    const modal = document.getElementById('delete-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('delete-error').style.display = 'none';
        document.getElementById('delete-confirm-password').value = '';
    }
}

window.closeDeleteModal = function () {
    const modal = document.getElementById('delete-modal');
    if (modal) modal.style.display = 'none';
}

window.confirmDelete = async function () {
    const password = document.getElementById('delete-confirm-password').value;
    const errorEl = document.getElementById('delete-error');
    const btn = document.getElementById('btn-confirm-delete');

    if (!password) {
        errorEl.textContent = "Please enter your password.";
        errorEl.style.display = 'block';
        return;
    }

    const userStr = sessionStorage.getItem('psobb_user');
    if (!userStr) {
        window.location.reload();
        return;
    }
    const user = JSON.parse(userStr);

    // Extract Username from BBLicenses if available
    let username = user.username;
    if (!username && user.BBLicenses && user.BBLicenses.length > 0) {
        username = user.BBLicenses[0].UserName;
    }

    if (!username) {
        errorEl.textContent = "Could not determine username. Please re-login.";
        errorEl.style.display = 'block';
        return;
    }

    // Attempt deletion
    btn.disabled = true;
    btn.textContent = "Deleting...";

    try {
        const response = await fetch('/api/delete_account.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({ username: username, password: password })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert("Account deleted successfully.");
            logout();
        } else {
            errorEl.textContent = data.error || "Deletion failed.";
            errorEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = "Confirm Delete";
        }
    } catch (e) {
        errorEl.textContent = "Connection error.";
        errorEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = "Confirm Delete";
    }
};

// Change Password Logic
window.requestChangePassword = function () {
    const modal = document.getElementById('change-pass-modal');
    if (modal) {
        modal.display = 'flex'; // Wait, modal needs style.display
        modal.style.display = 'flex';
        document.getElementById('cp-old').value = '';
        document.getElementById('cp-new').value = '';
        document.getElementById('cp-confirm').value = '';
        document.getElementById('cp-error').style.display = 'none';
        document.getElementById('cp-success').style.display = 'none';
    }
};

window.closeChangePassModal = function () {
    const modal = document.getElementById('change-pass-modal');
    if (modal) modal.style.display = 'none';
};

window.confirmChangePass = async function () {
    const oldPass = document.getElementById('cp-old').value;
    const newPass = document.getElementById('cp-new').value;
    const confirmPass = document.getElementById('cp-confirm').value;
    const errEl = document.getElementById('cp-error');
    const succEl = document.getElementById('cp-success');
    const btn = document.getElementById('btn-confirm-cp');

    errEl.style.display = 'none';
    succEl.style.display = 'none';

    if (!oldPass || !newPass) {
        errEl.textContent = "Please fill in all fields.";
        errEl.style.display = 'block';
        return;
    }
    if (newPass !== confirmPass) {
        errEl.textContent = "New passwords do not match.";
        errEl.style.display = 'block';
        return;
    }
    if (newPass.includes(' ')) {
        errEl.textContent = "No spaces allowed.";
        errEl.style.display = 'block';
        return;
    }

    const userStr = sessionStorage.getItem('psobb_user');
    if (!userStr) { window.location.reload(); return; }
    const user = JSON.parse(userStr);

    // Extract Username
    let username = user.username;
    if (!username && user.BBLicenses && user.BBLicenses.length > 0) {
        username = user.BBLicenses[0].UserName;
    }

    btn.disabled = true;
    btn.textContent = "Updating...";

    try {
        const response = await fetch('/api/change_password.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({
                username: username,
                old_password: oldPass,
                new_password: newPass
            })
        });
        const data = await response.json();

        if (response.ok && data.success) {
            succEl.textContent = "Password Changed! Logging out...";
            succEl.style.display = 'block';
            btn.textContent = "Success";
            setTimeout(() => {
                closeChangePassModal();
                logout();
            }, 1000);
        } else {
            errEl.textContent = data.error || "Update failed.";
            errEl.style.display = 'block';
            btn.disabled = false;
            btn.textContent = "Update";
        }
    } catch (e) {
        errEl.textContent = "Connection error.";
        errEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = "Update";
    }
};

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', () => {
    const mobileMenu = document.getElementById('mobile-menu');
    const navList = document.querySelector('nav ul');

    if (mobileMenu && navList) {
        mobileMenu.addEventListener('click', () => {
            navList.classList.toggle('active');
        });
    }
});

// Enhanced to update rates from the new fields in /api/server.php
async function updateUIRates(data) {
    const expEl = document.getElementById('rate-exp');
    const dropEl = document.getElementById('rate-drop');
    if (expEl && data.EXP) expEl.textContent = parseFloat(data.EXP) + 'x';
    if (dropEl && data.Drop) dropEl.textContent = parseFloat(data.Drop) + 'x';
}
