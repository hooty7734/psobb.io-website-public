// psobb-website/js/main.js
/**
 * PSOBB Website Frontend Logic
 * 
 * Handles all client-side dynamic behavior including:
 * - Session Management (Login, Logout, Dashboard State)
 * - Server Telemetry Fetching (Player counts, Active Games)
 * - DOM manipulation and layout animation (Intersection Observers)
 * - CSRF Header Injection for secure API interaction */

document.addEventListener('DOMContentLoaded', () => {
    // Detect PWA Standalone Mode
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) {
        document.body.classList.add('pwa-standalone');
        const installCard = document.getElementById('pwa-install-card');
        if (installCard) {
            installCard.style.setProperty('display', 'none', 'important');
        }
    }

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

        const signupBtn = document.querySelector('.signup-nav-btn');
        if (signupBtn) signupBtn.style.display = 'none';

        // Admin only links
        try {
            const userData = JSON.parse(userStr);
            if (userData && userData.isAdmin) {
                const adminDropdown = document.getElementById('nav-admin-dropdown');
                if (adminDropdown) adminDropdown.style.display = '';
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

    // Mobile Menu Toggle
    const mobileMenu = document.getElementById('mobile-menu');
    const navUl = document.querySelector('nav ul');
    if (mobileMenu && navUl) {
        mobileMenu.addEventListener('click', () => {
            navUl.classList.toggle('active');
        });
    }

    // Dropdown toggling for mobile
    const dropBtns = document.querySelectorAll('.dropbtn');
    dropBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const parentDropdown = btn.closest('.dropdown');
                
                // Close other open dropdowns
                document.querySelectorAll('.dropdown.mobile-open').forEach(d => {
                    if (d !== parentDropdown) d.classList.remove('mobile-open');
                });
                
                parentDropdown.classList.toggle('mobile-open');
            }
        });
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

        // Populate dashboard header
        const lastPlayer = (user.BBLicenses && user.BBLicenses.length > 0) ? user.BBLicenses[0].UserName : (user.LastPlayerName || 'Hunter');

        document.getElementById('dash-username-header').textContent = lastPlayer;
        document.getElementById('dash-username').textContent = lastPlayer;
        document.getElementById('dash-account-id').textContent = user.AccountID;

        document.getElementById('dash-team').textContent = user.BBTeamID ? 'Team #' + user.BBTeamID : 'None';

        const playtimeEl = document.getElementById('dash-playtime');
        if (playtimeEl) {
            playtimeEl.textContent = user.total_play_time_hours ? `${user.total_play_time_hours} hrs` : '--';
        }

        // PWA Install check
        const installCard = document.getElementById('pwa-install-card');
        if (installCard && window.deferredPrompt) {
            installCard.style.display = 'block';
        }

        // Initialize display name alias
        loadDisplayName();

        // Initialize system mail checkbox preference
        loadSystemMailPref();

        // Initialize Discord streak DM checkbox preference
        loadDiscordStreakPref();

        // Initialize milestone categories claim triggers inside portal
        initClaimModalCategoryButtons();

        // Initialize Backpack & Bank pre-selector dropdown and search bar listeners
        const bankSelect = document.getElementById('viewer-bank-select');
        if (bankSelect) {
            bankSelect.onchange = (e) => {
                window.activeBankIndex = parseInt(e.target.value);
                renderActiveBank();
            };
        }

        const searchInput = document.getElementById('viewer-bank-search');
        if (searchInput) {
            searchInput.oninput = (e) => {
                filterBankGrid(e.target.value.toLowerCase());
            };
        }

        // Load active character slot 0 dynamically
        window.activeSlot = 0;
        switchCharSlot(0);
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

    // Dynamic Class Archetype Counter
    let huCount = 0;
    let raCount = 0;
    let foCount = 0;
    activeClients.forEach(c => {
        if (c.Class) {
            const upper = c.Class.toUpperCase();
            if (upper.startsWith('HU')) huCount++;
            else if (upper.startsWith('RA')) raCount++;
            else if (upper.startsWith('FO')) foCount++;
        }
    });
    const huEl = document.getElementById('class-hu-count');
    const raEl = document.getElementById('class-ra-count');
    const foEl = document.getElementById('class-fo-count');
    if (huEl) huEl.textContent = huCount;
    if (raEl) raEl.textContent = raCount;
    if (foEl) foEl.textContent = foCount;

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

// Enhanced to update rates from the new fields in /api/server.php
async function updateUIRates(data) {
    const expEl = document.getElementById('rate-exp');
    const dropEl = document.getElementById('rate-drop');
    if (expEl && data.EXP) expEl.textContent = parseFloat(data.EXP) + 'x';
    if (dropEl && data.Drop) dropEl.textContent = parseFloat(data.Drop) + 'x';
}

// ==========================================================================
// Player Guide Modal Actions & Tab Toggling
// ==========================================================================
window.openPlayerGuideModal = function () {
    const modal = document.getElementById('player-guide-modal');
    if (modal) {
        modal.style.display = 'flex';
        // Reset scroll position to top
        const scrollBox = document.getElementById('guide-modal-content');
        if (scrollBox) scrollBox.scrollTop = 0;
        // Default to the first tab
        window.switchGuideTab('tab-portal');
        
        // Add keyboard ESC listener
        window.addEventListener('keydown', handleGuideEscKey);
        
        // Add click listener on the modal overlay itself to close it
        modal.addEventListener('click', handleGuideOverlayClick);
    }
};

window.closePlayerGuideModal = function () {
    const modal = document.getElementById('player-guide-modal');
    if (modal) {
        modal.style.display = 'none';
        // Cleanup listeners
        window.removeEventListener('keydown', handleGuideEscKey);
        modal.removeEventListener('click', handleGuideOverlayClick);
    }
};

window.switchGuideTab = function (tabId) {
    // Hide all tab panes
    const panes = document.querySelectorAll('.guide-tab-pane');
    panes.forEach(pane => {
        pane.style.display = 'none';
    });

    // Show the requested pane
    const targetPane = document.getElementById(tabId);
    if (targetPane) {
        targetPane.style.display = 'block';
    }

    // Update active tab buttons
    const buttons = document.querySelectorAll('.guide-tab-btn');
    buttons.forEach(btn => {
        if (btn.getAttribute('data-tab') === tabId) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
};

function handleGuideEscKey(e) {
    if (e.key === 'Escape') {
        window.closePlayerGuideModal();
    }
}

function handleGuideOverlayClick(e) {
    // If the click happened on the outer container (#player-guide-modal) and not inside the inner dialog
    if (e.target.id === 'player-guide-modal') {
        window.closePlayerGuideModal();
    }
}

// ==========================================================================
// Progressive Web App (PWA) & Single-Page Application (SPA) Portal Controller
// ==========================================================================

window.deferredPrompt = null;
window.activeSlot = 0;
window.activeCharData = null;
window.activeBankIndex = 0; // 0 = character, -1 = shared
window.bankCache = {};
window.currentClaimLevel = 0;

// Intercept PWA Install Prompts
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPrompt = e;
    // Show install card in Home/Hub if authenticated
    const installCard = document.getElementById('pwa-install-card');
    if (installCard && sessionStorage.getItem('psobb_user')) {
        installCard.style.display = 'block';
    }
});

// Trigger App Installation
window.installPortalApp = async function() {
    if (!window.deferredPrompt) {
        alert('The installation prompt is not ready. If you are using an iOS device, please use "Add to Home Screen" from Safari\'s share menu.');
        return;
    }
    window.deferredPrompt.prompt();
    const { outcome } = await window.deferredPrompt.userChoice;
    console.log(`[PWA] Install prompt outcome: ${outcome}`);
    window.deferredPrompt = null;
    const installCard = document.getElementById('pwa-install-card');
    if (installCard) {
        installCard.style.display = 'none';
    }
};

// Switch Dashboard Tab Panes
window.switchDashboardTab = function(tabId) {
    document.querySelectorAll('.dashboard-tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    const target = document.getElementById(tabId);
    if (target) target.classList.add('active');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-tab') === tabId) {
            btn.classList.add('active');
        }
    });

    // Lazy load tab data
    if (tabId === 'tab-banks' || tabId === 'tab-bank') {
        if (window.activeCharData) {
            if (tabId === 'tab-bank') renderActiveBank();
        } else {
            window.loadCharSlot(window.activeSlot || 0);
        }
    } else if (tabId === 'tab-guild') {
        window.loadUnlocks();
        window.loadStreak();
        window.loadMyBounties();
    }

    // Start/stop lobby feed polling based on chat tab visibility
    if (tabId === 'tab-chat') {
        if (window.startLobbyFeed) window.startLobbyFeed();
    } else {
        if (window.stopLobbyFeed) window.stopLobbyFeed();
    }
};

// Switch Character Slot
window.switchCharSlot = function(slotIndex) {
    window.activeSlot = slotIndex;
    document.querySelectorAll('.slot-btn').forEach(btn => {
        btn.classList.remove('active');
        if (parseInt(btn.getAttribute('data-slot')) === slotIndex) {
            btn.classList.add('active');
        }
    });
    window.loadCharSlot(slotIndex);
};

// Load Character Data via API
window.loadCharSlot = async function(slotIndex) {
    const pane = document.getElementById('viewer-content-pane');
    const loader = document.getElementById('viewer-loader');
    if (pane) pane.style.opacity = '0.4';
    if (loader) loader.style.display = 'block';

    try {
        const res = await fetch(`/api/character_viewer.php?slot=${slotIndex}`, { credentials: 'same-origin' });
        const data = await res.json();
        if (res.ok && data.success) {
            window.activeCharData = data.character;
            window.bankCache[slotIndex] = data.character.bank.items;
            window.bankCache['shared'] = data.character.shared_bank.items;

            renderCharacterProfile();
            renderInventory();
            renderActiveBank();
            populateChatCharacterSelect();
            renderActiveCharacterSectionId(data.character);
        } else {
            throw new Error(data.error || 'Failed to sync character metadata.');
        }
    } catch (e) {
        console.error(e);
        const contentPane = document.getElementById('viewer-content-pane');
        if (contentPane) {
            contentPane.innerHTML = `<div style="text-align:center; padding:3rem; color:#ff4444; font-family:'Share Tech Mono',monospace;">⚠️ ${e.message}</div>`;
        }
    } finally {
        if (pane) pane.style.opacity = '1';
        if (loader) loader.style.display = 'none';
    }
};

// Render Character Stats & Hero Card
function renderCharacterProfile() {
    const c = window.activeCharData;
    if (!c) return;

    document.getElementById('char-profile-name').textContent = c.name;
    document.getElementById('char-profile-level').textContent = c.level;
    document.getElementById('char-profile-playtime').textContent = `${c.play_time_hours} hrs`;

    const onlineBadge = document.getElementById('char-profile-online');
    if (onlineBadge) {
        if (c.online) {
            onlineBadge.innerHTML = '<span style="color: #00ffc8; text-shadow: 0 0 5px rgba(0,255,200,0.5);"><i class="fas fa-circle animate-pulse"></i> ONLINE</span>';
        } else {
            onlineBadge.innerHTML = '<span style="color: #666;"><i class="far fa-circle"></i> OFFLINE</span>';
        }
    }

    const classBadge = document.getElementById('char-profile-class');
    if (classBadge) classBadge.textContent = c.class;

    const fallbackImg = document.getElementById('char-profile-avatar-fallback');
    if (fallbackImg) {
        fallbackImg.src = `/img/classes/${c.class.toLowerCase()}.png`;
        fallbackImg.style.display = 'block';
        fallbackImg.onerror = () => { fallbackImg.src = '/img/favicon.svg'; };
    }

    const secIdBadge = document.getElementById('char-profile-secid');
    if (secIdBadge) {
        secIdBadge.innerHTML = `
            <img src="/img/section_ids/${c.section_id}.png" alt="${c.section_id}" style="width:18px; height:18px;">
            <span class="section-id id-${c.section_id.toLowerCase()}" style="font-size:0.7rem; font-weight:bold; font-family:'Share Tech Mono',monospace;">${c.section_id}</span>
        `;
    }

    // Meseta display
    const mesetaEl = document.getElementById('char-meseta-val');
    if (mesetaEl) mesetaEl.textContent = parseInt(c.stats.Meseta || 0).toLocaleString();

    // Animated stat bars
    const statMaxes = { ATP: 2500, DFP: 1000, MST: 2500, ATA: 300, EVP: 1500, LCK: 200, HP: 2500 };
    const stats = ['ATP', 'DFP', 'MST', 'ATA', 'EVP', 'LCK', 'HP'];
    stats.forEach(s => {
        const valEl = document.getElementById(`stat-val-${s.toLowerCase()}`);
        const barEl = document.getElementById(`bar-${s.toLowerCase()}`);
        const val = parseInt(c.stats[s]) || 0;
        if (valEl) valEl.textContent = val;
        if (barEl) {
            const pct = Math.min(100, (val / statMaxes[s]) * 100);
            setTimeout(() => { barEl.style.width = pct + '%'; }, 100);
        }
    });

    // Material values (compact grid)
    const matVals = {
        'mat-val-hp': c.mats.HP,
        'mat-val-tp': c.mats.TP,
        'mat-val-power': c.mats.Power,
        'mat-val-mind': c.mats.Mind,
        'mat-val-evade': c.mats.Evade,
        'mat-val-def': c.mats.Def,
        'mat-val-luck': c.mats.Luck
    };
    Object.keys(matVals).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = matVals[id];
    });
}

// Render Backpack Inventory
function renderInventory() {
    const c = window.activeCharData;
    if (!c || !c.inventory) return;

    const gearSlots = {
        'weapon': null, 'armor': null, 'shield': null, 
        'unit1': null, 'unit2': null, 'unit3': null, 'unit4': null, 'mag': null
    };
    let unitCount = 1;

    c.inventory.forEach(item => {
        if (item.equipped) {
            if (item.group === 0x00) {
                gearSlots['weapon'] = item;
            } else if (item.group === 0x01) {
                // type1: 1=Armor, 2=Shield, 3=Unit
                if (item.type1 === 1) gearSlots['armor'] = item;
                else if (item.type1 === 2) gearSlots['shield'] = item;
                else if (item.type1 === 3 && unitCount <= 4) {
                    gearSlots[`unit${unitCount}`] = item;
                    unitCount++;
                }
            } else if (item.group === 0x02) {
                gearSlots['mag'] = item;
            }
        }
    });

    // --- Populate Paper Doll Slots ---
    const slotNames = ['weapon', 'armor', 'shield', 'unit1', 'unit2', 'unit3', 'unit4', 'mag'];
    const namesListEl = document.getElementById('equipped-item-names');
    if (namesListEl) namesListEl.innerHTML = '';

    slotNames.forEach(key => {
        const slotBox = document.getElementById(`pd-slot-${key}`);
        if (!slotBox) return;
        slotBox.innerHTML = '';
        slotBox.className = 'pd-slot-box' + (key === 'armor' ? ' pd-armor' : '');
        slotBox.removeAttribute('data-hex');

        const item = gearSlots[key];
        if (item) {
            slotBox.setAttribute('data-hex', item.hex);

            // Icon
            let iconCat = 'tool';
            if (item.group === 0x00) iconCat = 'weapon';
            else if (item.group === 0x01) {
                if (item.type1 === 1) iconCat = 'armor';
                else if (item.type1 === 2) iconCat = 'shield';
                else iconCat = 'unit';
            } else if (item.group === 0x02) iconCat = 'mag';

            const img = document.createElement('img');
            img.src = `/img/items/${iconCat}.png`;
            img.onerror = () => { img.src = '/img/favicon.svg'; };
            slotBox.appendChild(img);

            // Rarity glow
            const nameLower = (item.stats && item.stats.Name) ? item.stats.Name.toLowerCase() : item.name.toLowerCase();
            if (nameLower.includes('psycho wand') || nameLower.includes('sealed j-sword') || nameLower.includes('sato')) {
                slotBox.classList.add('pd-rare-red');
            } else if (nameLower.includes('spread needle') || nameLower.includes('heaven punisher') || nameLower.includes('diwari')) {
                slotBox.classList.add('pd-rare-orange');
            } else if (nameLower.includes('luminous field') || nameLower.includes('stand still') || nameLower.includes('photon')) {
                slotBox.classList.add('pd-rare-purple');
            }

            // Add to names list
            if (namesListEl) {
                const row = document.createElement('div');
                row.className = 'eq-name-row';
                const tag = document.createElement('span');
                tag.className = 'eq-slot-tag';
                tag.textContent = key.replace(/(\d)/, ' $1').toUpperCase();
                const nameSpan = document.createElement('span');
                nameSpan.className = 'eq-item-name';
                nameSpan.textContent = item.name;
                row.appendChild(tag);
                row.appendChild(nameSpan);
                namesListEl.appendChild(row);
            }
        }
    });

    // --- Legacy equipped grid (hidden, kept for tooltip compatibility) ---
    const equippedBox = document.getElementById('viewer-equipped-grid');
    if (equippedBox) equippedBox.style.display = 'none';

    // --- Backpack Grid ---
    const backpackGrid = document.getElementById('viewer-backpack-grid');
    if (backpackGrid) {
        backpackGrid.innerHTML = '';
        let count = 0;
        for (let i = 0; i < 30; i++) {
            const item = c.inventory[i] || null;
            if (item) count++;
            backpackGrid.appendChild(createItemSlotElement(item));
        }
        document.getElementById('viewer-backpack-count').textContent = `${count} / 30`;
    }

    // --- MAG Stats Card ---
    const magCard = document.getElementById('mag-stats-card');
    if (magCard) {
        const magItem = gearSlots['mag'];
        if (magItem && magItem.name) {
            magCard.style.display = 'block';
            // Parse MAG description: "Kalki LV26 9.83/9.56/8.02/0.58 52% 114IQ PB:E (black)"
            const desc = magItem.name;
            const lvMatch = desc.match(/LV(\d+)/i);
            const statMatch = desc.match(/([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+)/);
            const synchMatch = desc.match(/(\d+)%/);
            const iqMatch = desc.match(/(\d+)IQ/i);
            const pbMatch = desc.match(/PB:(\S+)/i);
            const colorMatch = desc.match(/\((\w+)\)/);
            const magName = desc.split(/\s+LV/i)[0] || 'MAG';
            const magLv = lvMatch ? lvMatch[1] : '?';
            const defVal = statMatch ? parseFloat(statMatch[1]) : 0;
            const powVal = statMatch ? parseFloat(statMatch[2]) : 0;
            const dexVal = statMatch ? parseFloat(statMatch[3]) : 0;
            const mindVal = statMatch ? parseFloat(statMatch[4]) : 0;
            const synchro = synchMatch ? synchMatch[1] : '?';
            const iq = iqMatch ? iqMatch[1] : '?';
            const pb = pbMatch ? pbMatch[1] : '--';
            const color = colorMatch ? colorMatch[1] : '';

            const maxStat = 200;
            const pct = v => Math.min(100, (v / maxStat) * 100);

            magCard.innerHTML = `
                <div class="mag-header">
                    <div class="mag-icon"><img src="/img/items/mag.png" onerror="this.src='/img/favicon.svg'"></div>
                    <div class="mag-name">${magName}</div>
                    <div class="mag-level">LV ${magLv}</div>
                </div>
                <div class="mag-stat-bars">
                    <div class="mag-stat-item"><span class="ms-label">DEF</span><div class="ms-bar"><div class="ms-fill ms-def" style="width:${pct(defVal)}%"></div></div><span class="ms-val">${defVal}</span></div>
                    <div class="mag-stat-item"><span class="ms-label">POW</span><div class="ms-bar"><div class="ms-fill ms-pow" style="width:${pct(powVal)}%"></div></div><span class="ms-val">${powVal}</span></div>
                    <div class="mag-stat-item"><span class="ms-label">DEX</span><div class="ms-bar"><div class="ms-fill ms-dex" style="width:${pct(dexVal)}%"></div></div><span class="ms-val">${dexVal}</span></div>
                    <div class="mag-stat-item"><span class="ms-label">MIND</span><div class="ms-bar"><div class="ms-fill ms-mind" style="width:${pct(mindVal)}%"></div></div><span class="ms-val">${mindVal}</span></div>
                </div>
                <div class="mag-info-row">
                    <span>Synchro: <span class="mi-val">${synchro}%</span></span>
                    <span>IQ: <span class="mi-val">${iq}</span></span>
                    <span>PB: <span class="mi-pb">${pb}</span></span>
                    ${color ? `<span>Color: <span class="mi-val">${color}</span></span>` : ''}
                </div>
            `;
        } else {
            magCard.style.display = 'none';
        }
    }

    setupTooltipTriggers();
}

// Render Bank Grid
function renderActiveBank() {
    const grid = document.getElementById('viewer-bank-grid');
    if (!grid) return;
    grid.innerHTML = '';

    const c = window.activeCharData;
    if (!c) return;

    const currentBank = window.activeBankIndex === -1 ? c.shared_bank : c.bank;
    document.getElementById('viewer-bank-meseta').textContent = parseInt(currentBank.meseta).toLocaleString() + ' Meseta';

    for (let i = 0; i < 200; i++) {
        const item = currentBank.items[i] || null;
        grid.appendChild(createItemSlotElement(item));
    }

    setupTooltipTriggers();

    const searchInput = document.getElementById('viewer-bank-search');
    if (searchInput && searchInput.value) {
        filterBankGrid(searchInput.value.toLowerCase());
    }
}

// Trigger Bank Swap
window.triggerBankSwap = async function() {
    const c = window.activeCharData;
    const targetSelect = document.getElementById('viewer-bank-select');
    const swapResult = document.getElementById('bank-swap-result-msg');
    const swapBtn = document.getElementById('viewer-btn-swap-bank');
    if (!c || !targetSelect || !swapResult || !swapBtn) return;

    const targetBankIdx = parseInt(targetSelect.value);

    swapBtn.disabled = true;
    swapBtn.textContent = 'SWAPPING...';
    swapResult.style.display = 'none';

    try {
        const response = await fetch('/api/bank_swap.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({
                character_name: c.name,
                target_bank_index: targetBankIdx
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            swapResult.style.color = '#00ff88';
            swapResult.textContent = `✓ ${data.message}`;
            swapResult.style.display = 'block';
            setTimeout(() => window.loadCharSlot(window.activeSlot), 2000);
        } else {
            throw new Error(data.error || 'Failed to swap bank.');
        }
    } catch (e) {
        swapResult.style.color = '#ff4444';
        swapResult.textContent = `⚠️ ${e.message}`;
        swapResult.style.display = 'block';
    } finally {
        swapBtn.disabled = false;
        swapBtn.textContent = 'Swap Bank in Game';
    }
};

// Section ID change render
function renderActiveCharacterSectionId(character) {
    const secIdContainer = document.getElementById('section-id-change-container');
    if (!secIdContainer) return;

    let html = '';
    if (character) {
        html += `<p style="font-size:0.85rem; margin-bottom:8px; font-family:'Share Tech Mono',monospace;">Current Section ID: <strong class="section-id id-${character.section_id.toLowerCase()}">${character.section_id}</strong></p>`;
        if (character.level > 50) {
            html += `<p style="color: #ff4444; font-size:0.8rem; margin: 4px 0 0 0; font-weight:bold;">Only characters level 50 and below can change their Section ID.</p>`;
            secIdContainer.innerHTML = html;
            return;
        }
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
        <div style="border: 1px solid rgba(0,255,255,0.2); background: rgba(0,0,0,0.5); padding: 12px; border-radius: 6px;">
            <h4 style="margin-top: 0; margin-bottom:10px; color: #00ffff; font-family:'Share Tech Mono',monospace; font-size:0.95rem;"><i class="fas fa-arrows-spin"></i> Select New Section ID</h4>
            <div class="section-id-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; margin-bottom: 12px;">
                ${secIds.map((id) => `
                    <label class="secid-option-lbl" style="cursor: pointer; display: flex; align-items: center; gap: 8px; border: 1px solid ${id === character.section_id ? '#00ffff' : 'rgba(0,255,255,0.1)'}; padding: 8px 10px; border-radius: 6px; background: ${id === character.section_id ? 'rgba(0,255,255,0.1)' : 'transparent'}; transition: all 0.2s;" onclick="document.querySelectorAll('.secid-option-lbl').forEach(el=>{el.style.background='transparent';el.style.borderColor='rgba(0,255,255,0.1)'});this.style.background='rgba(0,255,255,0.1)';this.style.borderColor='#00ffff';">
                        <input type="radio" name="new-section-id" value="${id}" style="display: none;" ${id === character.section_id ? 'checked' : ''}>
                        <img src="/img/section_ids/${id}.png" alt="${id}" style="width: 22px; height: 22px; flex-shrink:0;">
                        <div style="min-width:0;">
                            <div style="font-size: 0.8rem; font-weight: bold; color: #eee; font-family:'Share Tech Mono',monospace;">${id}</div>
                            <div style="font-size: 0.6rem; color: #999; font-family:'Share Tech Mono',monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${secIdInfo[id]}</div>
                        </div>
                    </label>
                `).join('')}
            </div>
            <button id="btn-change-secid" onclick="triggerSectionIdChange()" class="dl-btn" style="width: 100%; border-color: #00ffff; background: rgba(0, 255, 255, 0.15); color: #00ffff; padding: 8px; font-weight: bold; font-family: 'Share Tech Mono', monospace; font-size:0.85rem;">Change Section ID</button>
            <div id="secid-message" style="margin-top: 8px; display: none; font-weight: bold; font-size:0.8rem;"></div>
        </div>
    `;
    secIdContainer.innerHTML = html;
}

window.triggerSectionIdChange = async function() {
    const checked = document.querySelector('input[name="new-section-id"]:checked');
    const c = window.activeCharData;
    const msgEl = document.getElementById('secid-message');
    const btn = document.getElementById('btn-change-secid');
    if (!checked || !c || !msgEl || !btn) return;

    const newSecId = checked.value;
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
            body: JSON.stringify({ character_name: c.name, new_section_id: newSecId })
        });
        const data = await response.json();

        if (response.ok && data.success) {
            msgEl.textContent = `✓ ${data.message}`;
            msgEl.style.color = '#00C851';
            msgEl.style.display = 'block';
            btn.textContent = "Success";
            setTimeout(() => window.loadCharSlot(window.activeSlot), 2000);
        } else {
            throw new Error(data.error || "Failed to change Section ID.");
        }
    } catch (e) {
        msgEl.textContent = `⚠️ ${e.message}`;
        msgEl.style.color = '#ff4444';
        msgEl.style.display = 'block';
        btn.disabled = false;
        btn.textContent = "Change Section ID";
    }
};

// Material Reset
window.triggerMaterialReset = async function() {
    const c = window.activeCharData;
    const msgEl = document.getElementById('reset-mat-message');
    if (!c || !msgEl) return;

    const confirmed = confirm(`CAUTION: Are you absolutely sure you want to reset all consumed materials back to 0 for Character Slot ${window.activeSlot + 1} (${c.name})?\n\nThis will permanently reset your character's stats and CANNOT be undone!`);
    if (!confirmed) return;

    const typedConfirm = prompt(`To confirm this permanent reset, please type the word "WIPE" in all caps below:`);
    if (typedConfirm !== "WIPE") {
        alert("Action cancelled. The confirmation word did not match.");
        return;
    }

    msgEl.textContent = "Recalibrating stats...";
    msgEl.style.color = "#ffaa00";
    msgEl.style.display = "block";

    try {
        const res = await fetch('/api/reset_materials.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({ slot: window.activeSlot })
        });
        const data = await res.json();
        if (res.ok && data.success) {
            msgEl.style.color = "#00ff88";
            msgEl.textContent = `✓ ${data.message}`;
            setTimeout(() => window.loadCharSlot(window.activeSlot), 2000);
        } else {
            throw new Error(data.error || 'Failed to reset materials.');
        }
    } catch(e) {
        msgEl.style.color = "#ff4444";
        msgEl.textContent = `⚠️ ${e.message}`;
    }
};

// Helper: item slot element creation
function createItemSlotElement(item, label = '') {
    const slotEl = document.createElement('div');
    slotEl.className = 'item-slot';

    if (item) {
        slotEl.setAttribute('data-hex', item.hex);
        
        const nameLower = item.name.toLowerCase();
        if (nameLower.includes('psycho wand') || nameLower.includes('sealed j-sword') || nameLower.includes('sato')) {
            slotEl.classList.add('rare-red');
        } else if (nameLower.includes('spread needle') || nameLower.includes('heaven punisher') || nameLower.includes('diwari')) {
            slotEl.classList.add('rare-orange');
        } else if (nameLower.includes('luminous field') || nameLower.includes('stand still') || nameLower.includes('photon')) {
            slotEl.classList.add('rare-purple');
        }

        const imgEl = document.createElement('img');
        imgEl.className = 'item-slot-icon';

        let iconCat = 'tool';
        if (item.group === 0x00) iconCat = 'weapon';
        else if (item.group === 0x01) {
            if (item.type1 === 1) iconCat = 'armor';
            else if (item.type1 === 2) iconCat = 'shield';
            else iconCat = 'unit';
        } else if (item.group === 0x02) iconCat = 'mag';

        imgEl.src = `/img/items/${iconCat}.png`;
        imgEl.onerror = () => { imgEl.src = '/img/favicon.svg'; };
        slotEl.appendChild(imgEl);

        if (item.equipped) {
            const eqBadge = document.createElement('span');
            eqBadge.style = 'position:absolute; top:2px; right:2px; background:#00ff88; color:#000; font-size:0.5rem; font-weight:bold; padding:1px 3px; border-radius:2px;';
            eqBadge.textContent = 'E';
            slotEl.appendChild(eqBadge);
        }
    }

    if (label) {
        const lbl = document.createElement('span');
        lbl.className = 'item-slot-label';
        lbl.textContent = label;
        slotEl.appendChild(lbl);
    }

    return slotEl;
}

// Tooltip mechanisms
function setupTooltipTriggers() {
    let tooltip = document.getElementById('viewer-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'viewer-tooltip';
        tooltip.className = 'item-tooltip';
        document.body.appendChild(tooltip);
    }

    document.querySelectorAll('.item-slot, .pd-slot-box').forEach(slot => {
        const hex = slot.getAttribute('data-hex');
        if (!hex) return;

        slot.onmouseenter = (e) => {
            const item = findItemByHex(hex);
            if (!item) return;

            let statsHtml = '';
            if (item.stats) {
                Object.keys(item.stats).forEach(s => {
                    statsHtml += `<div class="tooltip-stat-row"><span>${s}:</span><span class="tooltip-stat-val">${item.stats[s]}</span></div>`;
                });
            }

            tooltip.innerHTML = `
                <div class="tooltip-title">${item.name}</div>
                ${statsHtml}
                <div style="font-size:0.65rem; color:#666; margin-top:6px; font-family:monospace;">HEX: ${item.hex}</div>
            `;
            tooltip.style.display = 'block';
        };

        slot.onmousemove = (e) => {
            tooltip.style.left = (e.pageX + 15) + 'px';
            tooltip.style.top = (e.pageY + 15) + 'px';
        };

        slot.onmouseleave = () => {
            tooltip.style.display = 'none';
        };
    });
}

function findItemByHex(hex) {
    if (!window.activeCharData) return null;
    
    let found = window.activeCharData.inventory.find(i => i.hex === hex);
    if (found) return found;

    const currentBank = window.activeBankIndex === -1 ? window.activeCharData.shared_bank : window.activeCharData.bank;
    found = currentBank.items.find(i => i && i.hex === hex);
    return found;
}

function filterBankGrid(query) {
    document.querySelectorAll('#viewer-bank-grid .item-slot').forEach(slot => {
        const hex = slot.getAttribute('data-hex');
        if (!hex) {
            // Empty slot: hide when searching, show when cleared
            slot.style.display = query ? 'none' : '';
            return;
        }
        const item = findItemByHex(hex);
        if (!query || (item && item.name.toLowerCase().includes(query))) {
            slot.style.display = '';
        } else {
            slot.style.display = 'none';
        }
    });
}

// Chat select population
function populateChatCharacterSelect() {
    const select = document.getElementById('chat-character-select');
    if (!select || !window.activeCharData) return;
    
    const charName = window.activeCharData.name;
    let exists = false;
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === charName) {
            exists = true;
            break;
        }
    }
    if (!exists) {
        const opt = document.createElement('option');
        opt.value = charName;
        opt.textContent = `${charName} (Lvl ${window.activeCharData.level})`;
        select.appendChild(opt);
    }
    select.value = charName;
}

// Web-to-Game chat sender
window.sendWebToGameMessage = async function() {
    const select = document.getElementById('chat-character-select');
    const input = document.getElementById('chat-message-input');
    const statusMsg = document.getElementById('chat-status-message');
    const log = document.getElementById('chat-messages-log');
    const btn = document.getElementById('chat-send-btn');
    if (!select || !input || !statusMsg || !log || !btn) return;

    const charName = select.value;
    const msg = input.value.trim();

    if (!charName) {
        statusMsg.style.color = '#ff4444';
        statusMsg.textContent = '⚠️ Please select a character.';
        statusMsg.style.display = 'block';
        return;
    }
    if (!msg) {
        statusMsg.style.color = '#ff4444';
        statusMsg.textContent = '⚠️ Message cannot be empty.';
        statusMsg.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Sending...';
    statusMsg.style.display = 'none';

    try {
        const response = await fetch('/api/send_chat_message.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({
                character_name: charName,
                message: msg
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            statusMsg.style.color = '#00ff88';
            statusMsg.textContent = `✓ Message sent to game!`;
            statusMsg.style.display = 'block';
            input.value = '';

            appendChatBubble('sent', `[${charName}]: ${msg}`);
        } else {
            throw new Error(data.error || 'Failed to send message.');
        }
    } catch (e) {
        statusMsg.style.color = '#ff4444';
        statusMsg.textContent = `⚠️ ${e.message}`;
        statusMsg.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
    }
};

// ---- Live Lobby Feed for Chat Console ----
window._lobbyFeedInterval = null;
window._lobbyFeedPlayers = new Set();
window._lobbyFeedLastState = null;

function appendChatBubble(type, text) {
    const log = document.getElementById('chat-messages-log');
    if (!log) return;
    const bubble = document.createElement('div');
    bubble.className = `chat-message-bubble ${type}`;
    bubble.textContent = text;
    log.appendChild(bubble);
    while (log.children.length > 200) {
        log.removeChild(log.firstChild);
    }
    log.scrollTop = log.scrollHeight;
}

function updateLobbyHeader(data) {
    const header = document.getElementById('chat-lobby-header');
    if (!header) return;

    if (!data.online) {
        header.innerHTML = '<span style="color:#ff4444;"><i class="fas fa-times-circle"></i> Offline — Log into the game to use chat</span>';
        return;
    }
    if (!data.in_lobby) {
        header.innerHTML = '<span style="color:#ffaa00;"><i class="fas fa-hourglass-half"></i> Connected as ' + data.character + ' — Joining lobby...</span>';
        return;
    }

    const lobby = data.lobby || {};
    let lobbyDesc = '';
    if (lobby.is_game) {
        lobbyDesc = `<span style="color:#00ff88;"><i class="fas fa-gamepad"></i> ${lobby.name || 'Game'}`;
        if (lobby.difficulty) lobbyDesc += ` — ${lobby.difficulty}`;
        if (lobby.episode) lobbyDesc += ` ${lobby.episode}`;
        lobbyDesc += `</span>`;
        if (lobby.quest) {
            lobbyDesc += ` <span style="color:#ffaa00; font-size:0.8rem;"><i class="fas fa-scroll"></i> ${lobby.quest}</span>`;
        }
    } else {
        lobbyDesc = `<span style="color:#00ffff;"><i class="fas fa-users"></i> Lobby</span>`;
    }

    const playerList = (data.players || []).map(p => {
        const youTag = p.is_you ? ' (You)' : '';
        return `<span style="color:${p.is_you ? '#00ff88' : '#ccc'}; font-size:0.75rem;">${p.name} Lv${p.level}${youTag}</span>`;
    }).join(' · ');

    header.innerHTML = lobbyDesc + '<div style="margin-top:4px;">' + playerList + '</div>';
}

async function pollLobbyFeed() {
    try {
        const res = await fetch('/api/get_lobby_feed.php', { credentials: 'same-origin' });
        const data = await res.json();

        updateLobbyHeader(data);

        if (!data.online || !data.in_lobby) {
            window._lobbyFeedPlayers.clear();
            return;
        }

        const currentPlayers = new Set((data.players || []).map(p => p.name));

        const currentLobbyId = data.lobby?.id;
        if (window._lobbyFeedLastState !== null && window._lobbyFeedLastState !== currentLobbyId) {
            window._lobbyFeedPlayers.clear();
            const lobbyName = data.lobby?.is_game ? (data.lobby.name || 'Game') : 'Lobby';
            appendChatBubble('system', `SYSTEM: Moved to ${lobbyName}`);
        }
        window._lobbyFeedLastState = currentLobbyId;

        if (window._lobbyFeedPlayers.size > 0) {
            for (const name of currentPlayers) {
                if (!window._lobbyFeedPlayers.has(name)) {
                    const player = (data.players || []).find(p => p.name === name);
                    const cls = player ? ` (${player.class} Lv${player.level})` : '';
                    appendChatBubble('system', `▶ ${name}${cls} joined`);
                }
            }
            for (const name of window._lobbyFeedPlayers) {
                if (!currentPlayers.has(name)) {
                    appendChatBubble('system', `◀ ${name} left`);
                }
            }
        } else if (currentPlayers.size > 0) {
            const names = (data.players || []).map(p => `${p.name} (Lv${p.level})`).join(', ');
            appendChatBubble('system', `LOBBY: ${names}`);
        }

        window._lobbyFeedPlayers = currentPlayers;
    } catch (e) {
        // Silent fail
    }
}

window.startLobbyFeed = function() {
    if (window._lobbyFeedInterval) return;
    pollLobbyFeed();
    window._lobbyFeedInterval = setInterval(pollLobbyFeed, 5000);
};

window.stopLobbyFeed = function() {
    if (window._lobbyFeedInterval) {
        clearInterval(window._lobbyFeedInterval);
        window._lobbyFeedInterval = null;
    }
};

// System mail configurations pref
window.loadSystemMailPref = function() {
    const userStr = sessionStorage.getItem('psobb_user');
    if (!userStr) return;
    const user = JSON.parse(userStr);
    const checkbox = document.getElementById('system-mail-toggle');
    if (checkbox) {
        checkbox.checked = (user.receive_system_mail !== 0);
    }
};

window.toggleSystemMailPref = async function() {
    const checkbox = document.getElementById('system-mail-toggle');
    const userStr = sessionStorage.getItem('psobb_user');
    if (!checkbox || !userStr) return;

    const user = JSON.parse(userStr);
    const enabled = checkbox.checked;

    try {
        const response = await fetch('/api/toggle_system_mail.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({
                receive_system_mail: enabled ? 1 : 0
            })
        });
        const data = await response.json();
        if (response.ok && data.success) {
            user.receive_system_mail = enabled ? 1 : 0;
            sessionStorage.setItem('psobb_user', JSON.stringify(user));
            console.log(`[Preferences] System mail toggled successfully: ${enabled}`);
        } else {
            throw new Error(data.error);
        }
    } catch (e) {
        console.error('System mail preferences update failed:', e);
        checkbox.checked = !enabled;
    }
};

// Discord streak DM preferences toggle controllers
window.loadDiscordStreakPref = function() {
    const userStr = sessionStorage.getItem('psobb_user');
    if (!userStr) return;
    const user = JSON.parse(userStr);
    const checkbox = document.getElementById('discord-streak-toggle');
    if (checkbox) {
        checkbox.checked = (user.receive_discord_streak_msg !== 0);
    }
};

window.toggleDiscordStreakPref = async function() {
    const checkbox = document.getElementById('discord-streak-toggle');
    const userStr = sessionStorage.getItem('psobb_user');
    if (!checkbox || !userStr) return;

    const user = JSON.parse(userStr);
    const enabled = checkbox.checked;

    try {
        const response = await fetch('/api/toggle_discord_streak.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.getCSRFToken()
            },
            body: JSON.stringify({
                receive_discord_streak_msg: enabled ? 1 : 0
            })
        });
        const data = await response.json();
        if (response.ok && data.success) {
            user.receive_discord_streak_msg = enabled ? 1 : 0;
            sessionStorage.setItem('psobb_user', JSON.stringify(user));
            console.log(`[Preferences] Discord streak DM alerts toggled successfully: ${enabled}`);
        } else {
            throw new Error(data.error);
        }
    } catch (e) {
        console.error('Discord streak alerts preferences update failed:', e);
        checkbox.checked = !enabled;
    }
};

// Load bounties & community events for Guild tab
window.loadMyBounties = async function() {
    try {
        const res = await fetch('/api/my_bounties.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.success) return;

        // --- Community Events ---
        const ceSection = document.getElementById('community-event-section');
        const ceCards = document.getElementById('community-event-cards');
        if (ceSection && ceCards && data.community_events && data.community_events.length > 0) {
            ceSection.style.display = 'block';
            ceCards.innerHTML = data.community_events.map(ce => {
                const pct = Math.min(100, ce.progress_pct);
                return `
                <div style="border: 1px solid rgba(255,170,0,0.3); background: rgba(0,10,20,0.5); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong style="color:#ffaa00; font-family:'Share Tech Mono',monospace; font-size:0.95rem;">${ce.title}</strong>
                        <span style="color:#fff; font-size:0.8rem; font-family:'Share Tech Mono',monospace;">${pct}%</span>
                    </div>
                    <p style="color:rgba(255,255,255,0.65); font-size:0.8rem; margin:0 0 10px 0;">${ce.description || ''}</p>
                    <div style="background:rgba(255,255,255,0.06); border-radius:4px; height:10px; overflow:hidden; margin-bottom:8px;">
                        <div style="height:100%; background: linear-gradient(90deg, #ffaa00, #ff6600); border-radius:4px; transition: width 0.8s ease; width:${pct}%;"></div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:rgba(255,255,255,0.5); font-family:'Share Tech Mono',monospace;">
                        <span>Progress: ${Number(ce.current_progress).toLocaleString()} / ${Number(ce.target_amount).toLocaleString()}</span>
                        <span>Your Contribution: <strong style="color:#ffaa00;">${ce.user_contribution || 0}</strong></span>
                    </div>
                </div>`;
            }).join('');
        }

        // --- Claimable Community Events ---
        if (data.claimable_events && data.claimable_events.length > 0) {
            const claimSection = document.getElementById('claimable-bounties-section');
            const claimList = document.getElementById('claimable-bounties-list');
            if (claimSection && claimList) {
                ceSection && (ceSection.style.display = 'block');
                // Prepend claimable event cards to claimable bounties
                const eventCards = data.claimable_events.map(ce => `
                    <div style="border: 1px solid rgba(0,255,136,0.4); background: rgba(0,255,136,0.05); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="color:#00ff88; font-weight:bold; font-family:'Share Tech Mono',monospace;">🏆 ${ce.title}</span>
                                <div style="color:rgba(255,255,255,0.5); font-size:0.75rem; margin-top:4px;">Community Event Reward</div>
                            </div>
                            <a href="missions.php" class="dl-btn" style="text-decoration:none; border-color:#00ff88; color:#00ff88; background:rgba(0,255,136,0.15); font-size:0.8rem; padding:6px 14px;">Claim →</a>
                        </div>
                    </div>
                `).join('');
                claimList.innerHTML = eventCards + (claimList.innerHTML || '');
                claimSection.style.display = 'block';
            }
        }

        // --- Bounties ---
        const completed = data.bounties.filter(b => b.status === 'completed');
        const inProgress = data.bounties.filter(b => b.status === 'in_progress');

        // Claimable bounties
        if (completed.length > 0) {
            const claimSection = document.getElementById('claimable-bounties-section');
            const claimList = document.getElementById('claimable-bounties-list');
            if (claimSection && claimList) {
                claimSection.style.display = 'block';
                claimList.innerHTML += completed.map(b => `
                    <div style="border: 1px solid rgba(0,255,136,0.4); background: rgba(0,255,136,0.05); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="color:#00ff88; font-weight:bold; font-family:'Share Tech Mono',monospace;">✅ ${b.title}</span>
                                <div style="color:rgba(255,255,255,0.5); font-size:0.75rem; margin-top:4px;">${b.character_name || 'Unknown'} · ${b.goal_type}</div>
                            </div>
                            <a href="missions.php" class="dl-btn" style="text-decoration:none; border-color:#00ff88; color:#00ff88; background:rgba(0,255,136,0.15); font-size:0.8rem; padding:6px 14px;">Claim →</a>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Active in-progress bounties
        if (inProgress.length > 0) {
            const activeSection = document.getElementById('active-bounties-section');
            const activeList = document.getElementById('active-bounties-list');
            if (activeSection && activeList) {
                activeSection.style.display = 'block';
                activeList.innerHTML = inProgress.map(b => `
                    <div style="border: 1px solid rgba(0,255,255,0.2); background: rgba(0,10,20,0.4); border-radius: 8px; padding: 1rem; margin-bottom: 0.75rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span style="color:#00ffff; font-weight:bold; font-family:'Share Tech Mono',monospace;">${b.title}</span>
                                <div style="color:rgba(255,255,255,0.5); font-size:0.75rem; margin-top:4px;">${b.character_name || 'Unknown'} · ${b.goal_type} · ${b.goal_target || ''}</div>
                            </div>
                            <span style="color:#ffaa00; font-size:0.75rem; font-family:'Share Tech Mono',monospace;">IN PROGRESS</span>
                        </div>
                    </div>
                `).join('');
            }
        }

    } catch (e) {
        console.error('Failed to load bounties:', e);
    }
};

// Milestone reward loading
window.loadUnlocks = function() {
    const container = document.getElementById('milestones-container');
    const statusBox = document.getElementById('unlocks-status');
    const charInfo = document.getElementById('character-info');
    if (!container) return;

    fetch('/api/get_unlocks.php', { credentials: 'same-origin' })
        .then(res => {
            if (res.status === 401) {
                sessionStorage.removeItem('psobb_user');
                window.location.reload();
            }
            return res.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);

            if (!data.is_online) {
                if (statusBox) {
                    statusBox.style.display = 'block';
                    statusBox.className = 'alert-box';
                    statusBox.innerHTML = `⚠️ ${data.message || "You must be online in-game to view and claim rewards on your character."}`;
                }
                container.innerHTML = '';
                if (charInfo) charInfo.style.display = 'none';
                return;
            }

            if (charInfo) {
                charInfo.style.display = 'block';
                document.getElementById('char-name').textContent = data.character.name;
                document.getElementById('char-class').textContent = data.character.class;
                document.getElementById('char-level').textContent = data.character.level;
            }

            if (!data.in_game) {
                if (statusBox) {
                    statusBox.style.display = 'block';
                    statusBox.className = 'alert-box';
                    statusBox.innerHTML = "⚠️ Character found in Lobby. <b>You must join or create a Game in-game to claim milestone rewards!</b>";
                }
            } else {
                if (statusBox) statusBox.style.display = 'none';
            }

            renderMilestones(data.milestones, data.in_game);
        })
        .catch(err => {
            if (statusBox) {
                statusBox.style.display = 'block';
                statusBox.className = 'alert-box';
                statusBox.innerHTML = err.message;
            }
            container.innerHTML = '';
        });
};

function renderMilestones(milestones, inGame) {
    const container = document.getElementById('milestones-container');
    if (!container) return;

    if (!milestones || milestones.length === 0) {
        container.innerHTML = '<p style="color: rgba(255,255,255,0.4); font-family:\'Share Tech Mono\',monospace;">You have not reached Level 5 yet. Keep hunting!</p>';
        return;
    }

    const unclaimed = milestones.filter(m => !m.claimed);
    const claimed = milestones.filter(m => m.claimed);

    container.innerHTML = '';

    const summaryBar = document.createElement('div');
    summaryBar.style.cssText = 'display:flex; gap:12px; margin-bottom:1rem; flex-wrap:wrap;';
    summaryBar.innerHTML = `
        <span style="font-family:'Share Tech Mono',monospace; font-size:0.8rem; padding:4px 10px; border-radius:4px; background:rgba(0,255,136,0.15); border:1px solid rgba(0,255,136,0.3); color:#00ff88;">
            <i class="fas fa-gift"></i> ${unclaimed.length} Available
        </span>
        <span style="font-family:'Share Tech Mono',monospace; font-size:0.8rem; padding:4px 10px; border-radius:4px; background:rgba(170,102,204,0.15); border:1px solid rgba(170,102,204,0.3); color:#aa66cc;">
            <i class="fas fa-check"></i> ${claimed.length} Claimed
        </span>
    `;
    container.appendChild(summaryBar);

    if (unclaimed.length > 0) {
        unclaimed.forEach(m => {
            const card = document.createElement('div');
            card.className = 'milestone-card';
            const disabledStr = !inGame ? 'disabled' : '';
            const glowClass = (m.level % 25 === 0) ? 'milestone-major' : '';
            card.innerHTML = `
                <div class="milestone-level" ${glowClass ? 'style="color:#ffaa00; text-shadow:0 0 10px rgba(255,170,0,0.5);"' : ''}>Level ${m.level}</div>
                <button class="open-claim-btn" data-level="${m.level}" ${disabledStr}>
                    <i class="fas fa-gift"></i> Claim Reward
                </button>
            `;
            container.appendChild(card);
        });
    } else {
        const allDone = document.createElement('p');
        allDone.style.cssText = 'color:#00ff88; font-family:"Share Tech Mono",monospace; text-align:center; padding:1rem;';
        allDone.innerHTML = '<i class="fas fa-check-circle"></i> All available milestones claimed! Keep leveling for more.';
        container.appendChild(allDone);
    }

    if (claimed.length > 0) {
        const toggle = document.createElement('button');
        toggle.style.cssText = 'background:rgba(170,102,204,0.1); border:1px solid rgba(170,102,204,0.3); color:#aa66cc; padding:8px 16px; border-radius:6px; font-family:"Share Tech Mono",monospace; font-size:0.8rem; cursor:pointer; width:100%; margin-top:1rem; transition:all 0.3s;';
        toggle.innerHTML = `<i class="fas fa-chevron-down"></i> Show ${claimed.length} Claimed Milestones`;
        
        const claimedContainer = document.createElement('div');
        claimedContainer.style.cssText = 'display:none; margin-top:0.75rem;';
        claimedContainer.className = 'milestones-grid';

        claimed.forEach(m => {
            const card = document.createElement('div');
            card.className = 'milestone-card claimed';
            card.style.cssText = 'opacity:0.6; transform:scale(0.95);';
            card.innerHTML = `
                <div class="milestone-level">Level ${m.level}</div>
                <p style="color:#aa66cc; margin-top:0.5rem; font-family:'Share Tech Mono',monospace; font-size:0.75rem; text-shadow:0 0 5px rgba(255,255,255,0.1);">
                    <i class="fas fa-check"></i> ${m.claimed_category}
                </p>
            `;
            claimedContainer.appendChild(card);
        });

        toggle.addEventListener('click', () => {
            const isHidden = claimedContainer.style.display === 'none';
            claimedContainer.style.display = isHidden ? '' : 'none';
            toggle.innerHTML = isHidden 
                ? `<i class="fas fa-chevron-up"></i> Hide ${claimed.length} Claimed Milestones`
                : `<i class="fas fa-chevron-down"></i> Show ${claimed.length} Claimed Milestones`;
        });

        container.appendChild(toggle);
        container.appendChild(claimedContainer);
    }

    document.querySelectorAll('.open-claim-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!inGame) return;
            window.currentClaimLevel = parseInt(e.target.closest('.open-claim-btn').getAttribute('data-level'));
            const modal = document.getElementById('claim-modal');
            const levelSpan = document.getElementById('modal-level');
            const modalError = document.getElementById('modal-error');
            if (modal && levelSpan && modalError) {
                levelSpan.textContent = window.currentClaimLevel;
                modalError.style.display = 'none';
                modal.style.display = 'flex';
            }
        });
    });
}

function initClaimModalCategoryButtons() {
    const claimBtns = document.querySelectorAll('.claim-category-btn');
    if (claimBtns.length === 0) return;

    // Attach listeners once
    claimBtns.forEach(btn => {
        // Remove existing listener if any by cloning
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener('click', (e) => {
            const category = e.target.getAttribute('data-category');
            const modal = document.getElementById('claim-modal');
            const modalError = document.getElementById('modal-error');
            if (modal) modal.style.display = 'none';

            const overlay = document.getElementById('drop-animation-overlay');
            const box = document.getElementById('drop-item-box');
            const countdownEl = document.getElementById('countdown-text');
            const thankYouText = document.getElementById('thank-you-text');

            if (category === 'Random') {
                box.className = 'drop-item-box green-box';
            } else if (category === 'Armor' || category === 'Shield') {
                box.className = 'drop-item-box blue-box';
            } else if (category === 'Mag') {
                box.className = 'drop-item-box teal-box';
            } else if (category === 'Weapon') {
                if (window.currentClaimLevel % 25 === 0) {
                    box.className = 'drop-item-box';
                } else {
                    box.className = 'drop-item-box orange-box';
                }
            } else {
                box.className = 'drop-item-box';
            }

            thankYouText.style.animation = 'none';
            thankYouText.style.opacity = '0';

            const newBox = box.cloneNode(true);
            box.parentNode.replaceChild(newBox, box);

            overlay.style.display = 'flex';

            let count = 3;
            countdownEl.style.display = 'block';
            countdownEl.textContent = count;

            const countdownInterval = setInterval(() => {
                count--;
                if (count > 0) {
                    countdownEl.textContent = count;
                    countdownEl.style.transform = 'scale(1.5)';
                    setTimeout(() => countdownEl.style.transform = 'scale(1)', 100);
                } else if (count === 0) {
                    countdownEl.style.transform = 'scale(1.5)';
                    countdownEl.textContent = "DROPPING!";

                    fetch('/api/claim_unlock.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 
                            'Content-Type': 'application/json', 
                            'X-CSRF-Token': window.getCSRFToken() 
                        },
                        body: JSON.stringify({ level: window.currentClaimLevel, category: category })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) {
                            clearInterval(countdownInterval);
                            overlay.style.display = 'none';
                            if (modalError) {
                                modalError.textContent = data.error;
                                modalError.style.display = 'block';
                            }
                            if (modal) modal.style.display = 'flex';
                        } else {
                            countdownEl.style.display = 'none';
                            thankYouText.style.animation = 'textDrop 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
                            createFireworks();

                            setTimeout(() => {
                                overlay.style.display = 'none';
                                const statusBox = document.getElementById('unlocks-status');
                                if (statusBox) {
                                    statusBox.style.display = 'block';
                                    statusBox.className = 'alert-box success';
                                    statusBox.innerHTML = `🎉 <b>Success!</b> ${category} reward dropped inside your game room. Enjoy!`;
                                }
                                loadUnlocks();
                            }, 3500);
                        }
                    })
                    .catch(err => {
                        clearInterval(countdownInterval);
                        overlay.style.display = 'none';
                        if (modalError) {
                            modalError.textContent = "A connection error occurred.";
                            modalError.style.display = 'block';
                        }
                        if (modal) modal.style.display = 'flex';
                    });

                    clearInterval(countdownInterval);
                }
            }, 1000);
        });
    });
}

function createFireworks() {
    const overlay = document.getElementById('drop-animation-overlay');
    const colors = ['#ff4444', '#33b5e5', '#00C851', '#ffaa00', '#aa66cc', '#ffffff'];

    for (let b = 0; b < 6; b++) {
        setTimeout(() => {
            const centerX = window.innerWidth / 2 + (Math.random() - 0.5) * 600;
            const centerY = window.innerHeight / 2 + (Math.random() - 0.5) * 500 - 150;

            for (let i = 0; i < 80; i++) {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.left = centerX + 'px';
                particle.style.top = centerY + 'px';
                particle.style.width = (Math.random() * 8 + 4) + 'px';
                particle.style.height = particle.style.width;
                particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                particle.style.borderRadius = '50%';
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '9998';
                particle.style.boxShadow = `0 0 15px ${particle.style.backgroundColor}, 0 0 30px ${particle.style.backgroundColor}`;

                overlay.appendChild(particle);

                const angle = Math.random() * Math.PI * 2;
                const velocity = 100 + Math.random() * 300;
                const tx = Math.cos(angle) * velocity;
                const ty = Math.sin(angle) * velocity;

                particle.animate([
                    { transform: 'translate(0,0) scale(1)', opacity: 1 },
                    { transform: `translate(${tx}px, ${ty}px) scale(0)`, opacity: 0 }
                ], {
                    duration: 1000 + Math.random() * 800,
                    easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                    fill: 'forwards'
                });

                setTimeout(() => { if (particle.parentNode) particle.remove(); }, 2000);
            }
        }, b * 300);
    }
}

// Daily Streak Calendar claims
window.loadStreak = function() {
    // Non-linear fill calculation based on evenly spaced milestone segments
    function calculateFillPercentage(streak) {
        const milestones = [0, 7, 30, 90, 180, 270, 365];
        const segmentWidth = 100 / (milestones.length - 1); // 20% per segment
        for (let i = 0; i < milestones.length - 1; i++) {
            const current = milestones[i];
            const next = milestones[i+1];
            if (streak >= current && streak <= next) {
                const segmentProgress = (streak - current) / (next - current);
                return (i * segmentWidth) + (segmentProgress * segmentWidth);
            }
        }
        return 100;
    }

    fetch('/api/get_streak.php', { credentials: 'same-origin' })
        .then(res => {
            if (res.status === 401) {
                sessionStorage.removeItem('psobb_user');
            }
            return res.json();
        })
        .then(data => {
            if (data.error) return;

            document.getElementById('streak-count').textContent = data.streak;

            const node365 = document.querySelector('.streak-node[data-day="365"] .streak-node-reward');
            if (node365) {
                node365.textContent = data.has_claimed_yahoo ? 'Rare Drop' : 'Yahoo! Mag';
            }

            const fillPct = calculateFillPercentage(data.streak);
            document.getElementById('streak-fill').style.width = fillPct + '%';

            const nodes = document.querySelectorAll('.streak-node');
            nodes.forEach(node => {
                const day = parseInt(node.dataset.day);
                node.classList.remove('reached', 'claimable', 'claimed');

                if (data.claimed.includes(day)) {
                    node.classList.add('claimed');
                } else if (data.claimable.includes(day)) {
                    node.classList.add('claimable');
                } else if (data.streak >= day) {
                    node.classList.add('reached');
                }
            });

            // Streak Calendar
            const claimsDiv = document.getElementById('streak-claims');
            if (claimsDiv) {
                claimsDiv.innerHTML = '';

                // Sliding 30-day window centered around their current streak
                let startDay = 1;
                if (data.streak > 15) {
                    startDay = Math.max(1, Math.min(336, data.streak - 14));
                }
                // Align to 10-day boundaries for clean grid row alignment
                startDay = Math.floor((startDay - 1) / 10) * 10 + 1;
                const endDay = Math.min(365, startDay + 29);

                // Add range label above the grid
                let labelEl = document.getElementById('streak-range-label');
                if (!labelEl) {
                    labelEl = document.createElement('div');
                    labelEl.id = 'streak-range-label';
                    labelEl.style.fontSize = '0.85rem';
                    labelEl.style.color = '#ffaa00';
                    labelEl.style.fontFamily = "'Share Tech Mono', monospace";
                    labelEl.style.marginBottom = '12px';
                    labelEl.style.textAlign = 'right';
                    labelEl.style.letterSpacing = '1px';
                    claimsDiv.parentNode.insertBefore(labelEl, claimsDiv);
                }
                labelEl.innerHTML = `<i class="fas fa-calendar-alt"></i> REWARD SCHEDULE: DAYS ${startDay} - ${endDay}`;

                const daysArray = [];
                for (let i = startDay; i <= endDay; i++) {
                    daysArray.push(i);
                }
                daysArray.forEach(m => {
                    let rewardName = 'Monogrinder';
                    let tierClass = 'tier-mono';
                    
                    if (m === 365) {
                        rewardName = data.has_claimed_yahoo ? 'Rare Drop' : 'Yahoo! Mag';
                        tierClass = 'tier-yahoo';
                    } else if (m === 7 || m === 30 || m === 90 || m === 180 || m === 270) {
                        rewardName = 'Random Mat';
                        tierClass = 'tier-stat';
                    } else if (m <= 30) {
                        if (m % 5 === 0) {
                            rewardName = 'Random Mat';
                            tierClass = 'tier-stat';
                        } else if (m % 3 === 0) {
                            rewardName = 'Digrinder';
                            tierClass = 'tier-dig';
                        } else {
                            rewardName = 'Monogrinder';
                            tierClass = 'tier-mono';
                        }
                    } else if (m <= 90) {
                        if (m % 5 === 0) {
                            rewardName = 'Random Mat';
                            tierClass = 'tier-stat';
                        } else if (m % 3 === 0) {
                            rewardName = 'Trigrinder';
                            tierClass = 'tier-tri';
                        } else {
                            rewardName = 'Digrinder';
                            tierClass = 'tier-dig';
                        }
                    } else if (m <= 180) {
                        if (m % 4 === 0) {
                            rewardName = 'Random Mat';
                            tierClass = 'tier-stat';
                        } else {
                            rewardName = 'Trigrinder';
                            tierClass = 'tier-tri';
                        }
                    } else {
                        rewardName = 'Random Mat';
                        tierClass = 'tier-stat';
                    }

                    const day = document.createElement('div');
                    day.className = `streak-day ${tierClass}`;

                    let stateHtml = '';
                    if (data.claimed.includes(m)) {
                        day.classList.add('day-claimed');
                        stateHtml = '<span class="day-check">✓</span>';
                    } else if (data.claimable.includes(m)) {
                        day.classList.add('day-claimable');
                        stateHtml = '<span class="claim-label">Claim</span>';
                        day.addEventListener('click', () => claimStreak(m));
                    } else if (data.streak >= m) {
                        day.classList.add('day-reached');
                    }

                    day.innerHTML = `
                        ${stateHtml}
                        <div class="day-num">Day ${m}</div>
                        <div class="day-reward">${rewardName}</div>
                    `;
                    claimsDiv.appendChild(day);
                });
            }

            const dailyBtn = document.getElementById('daily-claim-btn');
            const dailyResult = document.getElementById('daily-result');
            if (dailyBtn) {
                if (data.daily_claimed) {
                    startDailyCountdown(dailyBtn, data.next_daily_reset, data.server_time);
                } else if (!data.is_online) {
                    dailyBtn.textContent = 'Log into the game first';
                    dailyBtn.disabled = true;
                } else {
                    dailyBtn.disabled = false;
                    dailyBtn.onclick = () => claimDaily();
                }
            }
        })
        .catch(err => console.error('Streak fetch error:', err));
};

function claimStreak(milestone) {
    const overlay = document.getElementById('drop-animation-overlay');
    const box = document.getElementById('drop-item-box');
    const thankYouText = document.getElementById('thank-you-text');
    const countdown = document.getElementById('countdown-text');
    if (!overlay || !box || !thankYouText || !countdown) return;

    box.className = 'drop-item-box green-box';
    thankYouText.style.animation = 'none';
    thankYouText.style.opacity = '0';

    const newBox = box.cloneNode(true);
    box.parentNode.replaceChild(newBox, box);
    overlay.style.display = 'flex';

    let count = 3;
    countdown.textContent = count;
    countdown.style.display = 'block';

    const countInterval = setInterval(() => {
        count--;
        if (count > 0) {
            countdown.textContent = count;
            countdown.style.transform = 'scale(1.5)';
            setTimeout(() => countdown.style.transform = 'scale(1)', 100);
        } else {
            clearInterval(countInterval);
            countdown.style.transform = 'scale(1.5)';
            countdown.textContent = 'DROPPING!';
            setTimeout(() => { countdown.style.display = 'none'; }, 600);

            fetch('/api/claim_streak.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCSRFToken() },
                body: JSON.stringify({ milestone })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    overlay.style.display = 'none';
                    alert(data.error);
                } else {
                    thankYouText.style.animation = 'textDrop 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
                    createFireworks();
                    setTimeout(() => {
                        overlay.style.display = 'none';
                        window.loadStreak();
                    }, 4000);
                }
            })
            .catch(() => {
                overlay.style.display = 'none';
                alert('Connection error. Please try again.');
            });
        }
    }, 1000);
}

function claimDaily() {
    const dailyBtn = document.getElementById('daily-claim-btn');
    const dailyResult = document.getElementById('daily-result');
    if (!dailyBtn || !dailyResult) return;

    dailyBtn.disabled = true;
    dailyBtn.textContent = 'Preparing...';

    const overlay = document.getElementById('drop-animation-overlay');
    const box = document.getElementById('drop-item-box');
    const thankYouText = document.getElementById('thank-you-text');
    const countdown = document.getElementById('countdown-text');

    box.className = 'drop-item-box teal-box';
    thankYouText.style.animation = 'none';
    thankYouText.style.opacity = '0';

    const newBox = box.cloneNode(true);
    box.parentNode.replaceChild(newBox, box);
    overlay.style.display = 'flex';

    let count = 3;
    countdown.textContent = count;
    countdown.style.display = 'block';

    const countInterval = setInterval(() => {
        count--;
        if (count > 0) {
            countdown.textContent = count;
            countdown.style.transform = 'scale(1.5)';
            setTimeout(() => countdown.style.transform = 'scale(1)', 100);
        } else {
            clearInterval(countInterval);
            countdown.style.transform = 'scale(1.5)';
            countdown.textContent = 'DROPPING!';
            setTimeout(() => { countdown.style.display = 'none'; }, 600);

            fetch('/api/claim_daily.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCSRFToken() },
                body: JSON.stringify({})
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    overlay.style.display = 'none';
                    dailyBtn.disabled = false;
                    dailyBtn.textContent = '🎲 Claim Daily Reward';
                    dailyResult.style.display = 'block';
                    dailyResult.style.color = '#ff4444';
                    dailyResult.textContent = data.error;
                } else {
                    thankYouText.style.animation = 'textDrop 1.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards';
                    createFireworks();

                    setTimeout(() => {
                        overlay.style.display = 'none';
                        dailyResult.style.display = 'block';
                        dailyResult.style.color = '#00ff88';
                        dailyResult.textContent = '🎉 ' + data.item + ' dropped in-game!';

                        const nowUnix = Math.floor(Date.now() / 1000);
                        const midnightEstimate = nowUnix + (86400 - (nowUnix % 86400));
                        startDailyCountdown(dailyBtn, midnightEstimate, nowUnix);
                    }, 4000);
                }
            })
            .catch(() => {
                overlay.style.display = 'none';
                dailyBtn.disabled = false;
                dailyBtn.textContent = '🎲 Claim Daily Reward';
                dailyResult.style.display = 'block';
                dailyResult.style.color = '#ff4444';
                dailyResult.textContent = 'Connection error.';
            });
        }
    }, 1000);
}

let dailyCountdownInterval = null;
function startDailyCountdown(btn, resetTimestamp, serverTime) {
    btn.disabled = true;
    btn.style.borderColor = 'rgba(255,255,255,0.15)';
    const offset = serverTime - Math.floor(Date.now() / 1000);

    function updateCountdown() {
        const nowServer = Math.floor(Date.now() / 1000) + offset;
        const remaining = resetTimestamp - nowServer;

        if (remaining <= 0) {
            btn.textContent = '🎲 Claim Daily Reward';
            btn.disabled = false;
            btn.style.borderColor = '#00ff88';
            if (dailyCountdownInterval) clearInterval(dailyCountdownInterval);
            window.loadStreak();
            return;
        }

        const hours = Math.floor(remaining / 3600);
        const mins = Math.floor((remaining % 3600) / 60);
        const secs = remaining % 60;
        btn.textContent = `✓ Claimed — Next in ${hours}h ${mins}m ${secs}s`;
    }

    updateCountdown();
    if (dailyCountdownInterval) clearInterval(dailyCountdownInterval);
    dailyCountdownInterval = setInterval(updateCountdown, 1000);
}

