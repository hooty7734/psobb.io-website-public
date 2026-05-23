<?php
/**
 * PSOBB LFG (Looking for Group) Portal
 * 
 * An advanced cyber-terminal interface for hunter coordination.
 * Gated strictly to admins for private testing.
 */
require_once __DIR__ . '/api/config.php';
start_secure_session();

// Gate access to logged-in players
if (empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Hunter\'s LFG Terminal - PSOBB';
$current_page = 'lfg';
include 'includes/header.php';
?>

<main class="container missions-wide">
    <!-- Cyber Terminal Header -->
    <div class="lfg-terminal-header animate-float">
        <div class="terminal-grid">
            <div>
                <h1 class="terminal-title"><i class="fas fa-satellite-dish"></i> LFG COORDINATION PORTAL</h1>
                <p class="terminal-status"><span class="pulse-dot"></span> LIVE SATELLITE FEED // ACTIVE COORDINATION SESSION</p>
            </div>
            <div id="character-sync-panel" class="char-sync-glass">
                <p style="margin: 0; color: #888; font-size: 0.85em;">SYNCHRONIZING IN-GAME STATS...</p>
            </div>
        </div>
    </div>

    <!-- Alert / Message Banner -->
    <div id="terminal-alert" class="terminal-banner" style="display: none;"></div>

    <div class="terminal-layout">
        <!-- Control Panel Column (Create Listing) -->
        <div class="terminal-controls-col">
            <div class="lfg-glass-panel">
                <h2 class="panel-section-title"><i class="fas fa-plus-circle"></i> CREATE LFG POST</h2>
                
                <form id="lfg-post-form" style="margin-top: 1rem;">
                    <div class="terminal-form-group">
                        <label for="lfg-description">MISSION / COMMENT</label>
                        <textarea id="lfg-description" name="description" placeholder="e.g. Seeking high-level group to run TTF on Ultimate. Section ID hunting Skyly rares!" required maxlength="250"></textarea>
                    </div>

                    <div class="terminal-form-group">
                        <label for="lfg-bounty">LINK ACTIVE BOUNTY <span class="badge-optional">OPTIONAL</span></label>
                        <select id="lfg-bounty" name="bounty_id">
                            <option value="">-- No Bounty Linked --</option>
                        </select>
                        <p style="font-size: 0.75em; color: #888; margin-top: 4px; line-height: 1.3;">Link an in-progress Hunter's Guild Bounty. Other players will see the objective and rewards.</p>
                    </div>

                    <div class="terminal-form-group">
                        <label>CLASSES SOUGHT</label>
                        <div class="archetype-checklist">
                            <label class="archetype-check-label">
                                <input type="checkbox" name="looking_for[]" value="HU" checked>
                                <span class="check-custom hu">HU</span> (Hunters)
                            </label>
                            <label class="archetype-check-label">
                                <input type="checkbox" name="looking_for[]" value="RA" checked>
                                <span class="check-custom ra">RA</span> (Rangers)
                            </label>
                            <label class="archetype-check-label">
                                <input type="checkbox" name="looking_for[]" value="FO" checked>
                                <span class="check-custom fo">FO</span> (Forces)
                            </label>
                        </div>
                    </div>

                    <button type="submit" id="submit-post-btn" class="dl-btn warning-btn" style="width: 100%; margin-top: 1rem; border-color: #ffaa00;">
                        <i class="fas fa-plus-circle"></i> CREATE LFG POST
                    </button>
                </form>
            </div>
        </div>

        <!-- Terminal Displays Column -->
        <div class="terminal-feeds-col">
            <!-- Active Hunter Coordination Feed -->
            <div class="lfg-glass-panel">
                <h2 class="panel-section-title"><i class="fas fa-users"></i> GROUPS <span id="request-count-badge" class="count-badge">0</span></h2>
                <p style="font-size: 0.85em; color: #ffaa00; margin-top: 4px; margin-bottom: 1.5rem;"><i class="fas fa-info-circle"></i> live postings from online hunters. Warp directly into active parties or coordinate lobby games.</p>

                <div id="lfg-listings-grid" class="feeds-grid">
                    <!-- Loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
</main>

<link rel="stylesheet" href="css/lfg.css?v=<?php echo time(); ?>">

<!-- Terminal JavaScript Integration -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const preselectedBountyId = <?= isset($_GET['bounty_id']) ? (int)$_GET['bounty_id'] : 'null' ?>;
    const myAccountId = <?= (int)($_SESSION['user']['account_id'] ?? 0) ?>;

    // 1. Get CSRF utility
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Live player character state (updated dynamically)
    let myActiveChar = null;
    let formPrefilled = false;
    let hasActiveListing = false;

    // Trigger Initial Load
    syncCharacterState();
    loadMyBounties();
    pollLfgTerminal();

    // Poll feeds every 10 seconds
    setInterval(pollLfgTerminal, 10000);
    // Sync character online state every 20 seconds
    setInterval(syncCharacterState, 20000);

    // Form Submission
    const form = document.getElementById('lfg-post-form');
    if (form) {
        form.addEventListener('submit', handlePostLfg);
    }

    /**
     * Shows a flash alert banner at the top of the terminal
     */
    function showAlert(text, type = 'success') {
        const banner = document.getElementById('terminal-alert');
        if (!banner) return;
        banner.className = `terminal-banner ${type}`;
        banner.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i> ${text}`;
        banner.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Hide after 6 seconds
        setTimeout(() => {
            banner.style.display = 'none';
        }, 6000);
    }

    /**
     * Formats character classes into HU / RA / FO archetype codes
     */
    function getArchetype(charClass) {
        if (!charClass) return '';
        const upper = charClass.toUpperCase();
        if (upper.startsWith('HU')) return 'HU';
        if (upper.startsWith('RA')) return 'RA';
        if (upper.startsWith('FO')) return 'FO';
        return '';
    }

    /**
     * Checks server to see if player's character is online
     */
    async function syncCharacterState() {
        const panel = document.getElementById('character-sync-panel');
        if (!panel) return;

        try {
            const res = await fetch('/api/summary.php');
            const data = await res.json();
            
            // Uses securely injected global PHP session Account ID instead of tab-dependent sessionStorage

            myActiveChar = null;
            if (data.Clients) {
                myActiveChar = data.Clients.find(c => parseInt(c.AccountID) === myAccountId && c.Name);
            }

            if (myActiveChar) {
                const arch = getArchetype(myActiveChar.Class);
                
                // Determine if they are in an active game rather than a lobby
                let inGame = false;
                if (myActiveChar.LobbyID !== null && data.Games) {
                    inGame = data.Games.some(g => parseInt(g.ID) === parseInt(myActiveChar.LobbyID));
                }

                myActiveChar.inGame = inGame;

                let syncHtml = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="color:#00ffc8; font-weight:bold;"><i class="fas fa-user-circle"></i> ${escapeHtml(myActiveChar.Name)}</span>
                            <span style="font-size:0.8em; color:#aaa; margin-left:5px;">(Lv. ${myActiveChar.Level})</span>
                        </div>
                        <span class="check-custom ${arch.toLowerCase()}" style="margin:0; box-shadow:none;">${arch}</span>
                    </div>
                `;

                if (inGame) {
                    syncHtml += `
                        <div style="font-size:0.75em; color:#aaa; margin-top:5px; border-top:1px dashed rgba(255,255,255,0.05); padding-top:4px; display:flex; justify-content:space-between; align-items:center;">
                            <span>In Game: <strong style="color:var(--lfg-blue);">#${myActiveChar.LobbyID}</strong></span>
                            <button class="dl-btn danger-btn" style="padding: 2px 6px; font-size: 0.6rem; border-radius: 4px; margin: 0; line-height: 1.1; text-shadow: none; border-color: #ff4444;" onclick="leaveCurrentGroup()"><i class="fas fa-sign-out-alt"></i> LEAVE GROUP</button>
                        </div>
                    `;
                } else {
                    syncHtml += `
                        <div style="font-size:0.75em; color:#aaa; margin-top:5px; border-top:1px dashed rgba(255,255,255,0.05); padding-top:4px;">
                            Online in Lobby: <strong style="color:var(--lfg-blue);">${myActiveChar.LobbyID !== null ? '#' + myActiveChar.LobbyID : 'Connecting'}</strong>
                        </div>
                    `;
                }
                panel.innerHTML = syncHtml;

                // Dynamically toggle the submit button state based on active party eligibility
                const submitBtn = document.getElementById('submit-post-btn');
                if (submitBtn) {
                    if (inGame) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('disabled');
                        submitBtn.innerHTML = hasActiveListing 
                            ? '<i class="fas fa-edit"></i> UPDATE LFG POST' 
                            : '<i class="fas fa-plus-circle"></i> CREATE LFG POST';
                    } else {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('disabled');
                        submitBtn.innerHTML = '<i class="fas fa-lock"></i> REQUIRES JOINABLE PARTY';
                    }
                }
            } else {
                panel.innerHTML = `
                    <div style="color:#ffaa00; text-align:center; font-size:0.85em;">
                        <i class="fas fa-exclamation-triangle"></i> CHARACTER OFFLINE<br>
                        <span style="font-size:0.8em; color:#888;">Log in in-game to post / join.</span>
                    </div>
                `;

                // Disable submit button if character is offline
                const submitBtn = document.getElementById('submit-post-btn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('disabled');
                    submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> CHARACTER OFFLINE';
                }
            }
        } catch (e) {
            panel.innerHTML = '<span style="color:#ff4444;">SYNC FAILURE // CONFIG ERROR</span>';
        }
    }

    /**
     * Loads the logged-in player's in-progress bounties into the select dropdown
     */
    async function loadMyBounties() {
        const select = document.getElementById('lfg-bounty');
        if (!select) return;

        try {
            const res = await fetch('/api/my_bounties.php');
            const data = await res.json();

            if (data.success && data.bounties) {
                data.bounties.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.mission_id;
                    opt.textContent = `${b.title} (${b.goal_type})`;
                    select.appendChild(opt);
                });

                if (preselectedBountyId) {
                    select.value = preselectedBountyId;
                    const selectedOpt = select.options[select.selectedIndex];
                    if (selectedOpt && selectedOpt.value) {
                        const descField = document.getElementById('lfg-description');
                        if (descField && !descField.value) {
                            descField.value = `Seeking a group to help complete Team Bounty: ${selectedOpt.textContent.split(' (')[0]}!`;
                        }
                    }
                }
            }
        } catch (e) {
            console.error('Failed to load active bounties:', e);
        }
    }

    /**
     * Submits a new LFG listing to the backend
     */
    async function handlePostLfg(e) {
        e.preventDefault();
        
        if (!myActiveChar) {
            showAlert('You must be online on a character in-game to create an LFG post.', 'error');
            return;
        }

        if (!myActiveChar.inGame) {
            showAlert('You must be inside an active joinable party to create an LFG post.', 'error');
            return;
        }

        const btn = document.getElementById('submit-post-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> POSTING...';

        const desc = document.getElementById('lfg-description').value;
        const bountyId = document.getElementById('lfg-bounty').value || null;
        
        // Extract selected checkboxes
        const checkboxes = document.querySelectorAll('input[name="looking_for[]"]:checked');
        const lookingForArr = Array.from(checkboxes).map(cb => cb.value);
        const looking_for = lookingForArr.length > 0 ? lookingForArr.join(',') : null;

        try {
            const response = await fetch('/api/lfg_requests.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({
                    description: desc,
                    bounty_id: bountyId,
                    looking_for: looking_for
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showAlert(data.message, 'success');
                document.getElementById('lfg-description').value = '';
                document.getElementById('lfg-bounty').value = '';
                
                // Refresh terminal feed instantly
                pollLfgTerminal();
            } else {
                showAlert(data.error || 'Failed to post LFG request.', 'error');
            }
        } catch (e) {
            showAlert('Connection error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = hasActiveListing 
                ? '<i class="fas fa-edit"></i> UPDATE LFG POST' 
                : '<i class="fas fa-plus-circle"></i> CREATE LFG POST';
        }
    }

    /**
     * Polls the backend active games and custom listings
     */
    async function pollLfgTerminal() {
        try {
            // Load Active Games Feed
            const gamesRes = await fetch('/api/lfg_games.php');
            const gamesData = await gamesRes.json();
            const games = (gamesData.success && gamesData.games) ? gamesData.games : [];

            // Load Coordination Requests Feed (LFG Listings)
            const listingsRes = await fetch('/api/lfg_requests.php');
            const listingsData = await listingsRes.json();

            if (listingsData.success && listingsData.listings) {
                renderLfgListings(listingsData.listings, games);
            }
        } catch (e) {
            console.error('Error polling LFG terminal:', e);
        }
    }

    /**
     * Renders unified custom hunter listings with live game details attached
     */
    function renderLfgListings(listings, games) {
        const grid = document.getElementById('lfg-listings-grid');
        const badge = document.getElementById('request-count-badge');
        if (!grid) return;

        grid.innerHTML = '';
        badge.textContent = listings.length;

        // Uses the global myAccountId

        // Detect if the logged-in player has an active listing
        const myListing = listings.find(l => parseInt(l.account_id) === parseInt(myAccountId));
        hasActiveListing = !!myListing;

        // Update the submit button text dynamically if online and in game
        const submitBtn = document.getElementById('submit-post-btn');
        if (submitBtn && myActiveChar && myActiveChar.inGame) {
            submitBtn.innerHTML = hasActiveListing 
                ? '<i class="fas fa-edit"></i> UPDATE LFG POST' 
                : '<i class="fas fa-plus-circle"></i> CREATE LFG POST';
        }

        // Pre-fill the form ONCE if they have an active post
        if (!formPrefilled && myListing) {
            formPrefilled = true;
            const descField = document.getElementById('lfg-description');
            const bountyField = document.getElementById('lfg-bounty');
            
            if (descField && !descField.value) {
                descField.value = myListing.description || '';
            }
            if (bountyField) {
                bountyField.value = myListing.bounty_id || '';
            }
            if (myListing.looking_for) {
                const archetypes = myListing.looking_for.split(',');
                document.querySelectorAll('input[name="looking_for[]"]').forEach(cb => {
                    cb.checked = archetypes.includes(cb.value);
                });
            }
        }

        if (listings.length === 0) {
            grid.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; color: #888; padding: 3rem; border: 1px dashed rgba(255,255,255,0.1); border-radius: 8px;">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 12px; color: var(--lfg-orange);"></i><br>
                    NO ACTIVE COORDINATION POSTS FOUND
                </div>
            `;
            return;
        }

        // Uses the global myAccountId

        listings.forEach(l => {
            const card = document.createElement('div');
            card.className = 'terminal-card hunter-post';

            // 1. Seeking class badges
            let seekHtml = '';
            let isWanted = false; // Checks if the logged in player matches their wanted list
            
            if (l.looking_for) {
                const archetypes = l.looking_for.split(',');
                archetypes.forEach(a => {
                    seekHtml += `<span class="seek-badge ${a.toLowerCase()}">${a}</span>`;
                });

                // If active user is online, check if their archetype matches and it's not their own group or post
                if (myActiveChar) {
                    const myArch = getArchetype(myActiveChar.Class);
                    const isOwnPost = parseInt(l.account_id) === parseInt(myAccountId);
                    const isInSameLobby = l.game_id !== null && myActiveChar.LobbyID !== null && parseInt(myActiveChar.LobbyID) === parseInt(l.game_id);
                    
                    if (archetypes.includes(myArch) && !isOwnPost && !isInSameLobby) {
                        isWanted = true;
                        card.classList.add('wanted-highlight');
                    }
                }
            }

            const wantedBadge = isWanted 
                ? `<div class="wanted-banner"><i class="fas fa-fire animate-pulse"></i> 🔥 LFG MATCH: ${getArchetype(myActiveChar.Class)} WANTED HERE!</div>`
                : '';

            // 2. Linked Bounty Hunt Details
            let bountyHtml = '';
            if (l.bounty_id && l.bounty_title) {
                bountyHtml = `
                    <div class="bounty-glass-badge">
                        <div class="bounty-badge-title"><i class="fas fa-crosshairs"></i> BOUNTY TARGET</div>
                        <strong style="color:#fff; font-size:0.85rem;">${escapeHtml(l.bounty_title)}</strong>
                        <div style="font-size:0.7em; color:#aaa; margin-top:2px;">Reward: <span style="color:#ffaa00;">${escapeHtml(l.bounty_reward || 'Standard Payout')}</span></div>
                    </div>
                `;
            }

            // 3. Find and enrich matching active game lobby if it exists
            let gameHtml = '';
            let levelLocked = false;
            let lobbyFull = false;
            let matchingGame = null;

            if (l.game_id !== null) {
                matchingGame = games.find(g => parseInt(g.ID) === parseInt(l.game_id));
            }

            if (matchingGame) {
                const g = matchingGame;
                const myLevel = myActiveChar ? parseInt(myActiveChar.Level) : 0;
                levelLocked = myActiveChar && (myLevel < g.MinLevel || myLevel > g.MaxLevel);
                lobbyFull = g.Players >= g.MaxClients;

                let levelBadge = `<span class="c-badge level-badge"><i class="fas fa-shield-alt"></i> Lv. ${g.MinLevel} - ${g.MaxLevel}</span>`;
                if (levelLocked) {
                    levelBadge = `<span class="c-badge level-badge" style="background:rgba(255,0,0,0.15); border-color:#ff4444; color:#ff6666;"><i class="fas fa-lock"></i> Lv. ${g.MinLevel} - ${g.MaxLevel}</span>`;
                }

                const passBadge = g.HasPassword 
                    ? '<span class="c-badge private-badge" style="font-size:0.6rem; padding:1px 4px;"><i class="fas fa-lock"></i> PRIVATE</span>'
                    : '<span class="c-badge lobby-badge" style="background:rgba(0, 255, 255, 0.1); border-color:var(--lfg-blue); color:var(--lfg-blue); font-size:0.6rem; padding:1px 4px;"><i class="fas fa-unlock"></i> OPEN</span>';

                let slotHtml = '';
                for (let i = 0; i < g.MaxClients; i++) {
                    const cClass = g.ClientClasses[i];
                    if (cClass) {
                        const sArch = getArchetype(cClass);
                        slotHtml += `<span class="c-slot filled-${sArch.toLowerCase()}" title="${cClass}">${sArch}</span>`;
                    } else {
                        slotHtml += `<span class="c-slot" title="Empty Slot">--</span>`;
                    }
                }

                gameHtml = `
                    <div style="background: rgba(0, 255, 255, 0.03); border: 1px solid rgba(0, 255, 255, 0.12); border-radius: 8px; padding: 12px; margin-top: 12px; margin-bottom: 12px; box-shadow: inset 0 0 10px rgba(0,255,255,0.02);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <strong style="color:#00ffc8; font-size:0.85rem; font-family:'Share Tech Mono', monospace; letter-spacing:0.5px;"><i class="fas fa-gamepad"></i> LIVE ROOM: "${escapeHtml(g.Name)}"</strong>
                            <span class="count-badge" style="background:rgba(0, 255, 200, 0.1); border-color:#00ffc8; color:#00ffc8; font-size:0.65rem; padding:1px 6px;">${g.Players}/${g.MaxClients}</span>
                        </div>
                        <div class="card-meta-row" style="margin-bottom: 8px; gap: 4px;">
                            <span class="mode-badge mode-${g.Mode.toLowerCase()}" style="font-size:0.6rem; padding:1px 6px; border-radius:4px; font-weight:600;">${g.Mode === 'Normal' ? 'Extermination' : g.Mode}</span>
                            <span class="c-badge lobby-badge" style="font-size:0.6rem; padding:1px 4px;">${g.Episode}</span>
                            <span class="c-badge lobby-badge" style="background:rgba(157,78,221,0.1); border-color:var(--lfg-purple); color:#d288ff; font-size:0.6rem; padding:1px 4px;">${g.Difficulty}</span>
                            ${levelBadge}
                            ${passBadge}
                        </div>
                        <div style="font-size:0.65rem; color:#888; font-family:'Share Tech Mono', monospace; margin-bottom: 4px;">PARTY COMPOSITION:</div>
                        <div class="slot-badge-container" style="margin: 0; padding: 4px 8px;">
                            ${slotHtml}
                        </div>
                    </div>
                `;
            }

            // 4. Action Buttons logic at the bottom right
            let actionBtn = '';
            if (parseInt(l.account_id) === parseInt(myAccountId)) {
                actionBtn = `<button class="dl-btn danger-btn" style="padding: 4px 10px; font-size: 0.75rem;" onclick="deleteLfgPost(${l.id})"><i class="fas fa-trash-alt"></i> CLOSE POST</button>`;
            } else if (l.game_id !== null && myActiveChar) {
                if (lobbyFull) {
                    actionBtn = `<button class="dl-btn disabled" style="padding: 4px 10px; font-size: 0.75rem; color:#888; border-color:#555;"><i class="fas fa-users"></i> FULL</button>`;
                } else if (levelLocked) {
                    actionBtn = `<button class="dl-btn disabled" style="padding: 4px 10px; font-size: 0.75rem; color:#ff4444; border-color:#ff4444;"><i class="fas fa-lock"></i> LV LOCKED</button>`;
                } else {
                    actionBtn = `<button class="dl-btn success-btn" style="padding: 4px 10px; font-size: 0.75rem;" onclick="joinActiveGame(${l.game_id})"><i class="fas fa-rocket"></i> WARP DIRECT</button>`;
                }
            } else if (!myActiveChar) {
                actionBtn = `<button class="dl-btn disabled" style="padding: 4px 10px; font-size: 0.75rem;"><i class="fas fa-sign-in-alt"></i> OFFLINE</button>`;
            }

            const arch = getArchetype(l.class);
            const timeAgo = formatTimeAgo(l.created_at);

            card.innerHTML = `
                <div>
                    ${wantedBadge}
                    
                    <div class="card-header" style="margin-bottom:0.5rem;">
                        <h3 class="card-title" style="color:var(--lfg-orange);">${escapeHtml(l.character_name)}</h3>
                        <span class="check-custom ${arch.toLowerCase()}" style="margin:0; font-size:0.65rem; height:18px; line-height:18px; width:28px;">${arch}</span>
                    </div>

                    <div class="card-meta-row" style="margin-bottom:12px;">
                        <span class="c-badge level-badge">Lv. ${l.level}</span>
                        <span class="c-badge secid-badge id-${l.section_id.toLowerCase()}" style="padding: 2px 6px; font-size: 0.65rem; border:1px solid rgba(255,255,255,0.15);">${l.section_id}</span>
                        ${l.game_name ? `<span class="c-badge lobby-badge"><i class="fas fa-compass"></i> ${escapeHtml(l.game_name)}</span>` : ''}
                        ${l.game_mode ? `<span class="mode-badge mode-${l.game_mode.toLowerCase()}" style="font-size:0.6rem; padding:1px 6px; border-radius:4px; font-weight:600;">${l.game_mode === 'Normal' ? 'Extermination' : l.game_mode}</span>` : ''}
                    </div>

                    <p class="card-desc" style="font-style: italic; background: rgba(0,0,0,0.25); padding: 8px 12px; border-radius: 6px; border-left: 2px solid var(--lfg-orange); margin: 8px 0; font-size: 0.85rem; line-height: 1.4; color: rgba(255,255,255,0.95); font-family: inherit;">
                        "${escapeHtml(l.description)}"
                    </p>
                    
                    ${bountyHtml}

                    ${seekHtml ? `
                        <div class="seek-container">
                            <span class="seek-label">SEEKING:</span>
                            ${seekHtml}
                        </div>
                    ` : ''}

                    ${gameHtml}
                </div>

                <div class="card-footer">
                    <span class="card-time"><i class="far fa-clock"></i> ${timeAgo}</span>
                    ${actionBtn}
                </div>
            `;
            grid.appendChild(card);
        });
    }

    /**
     * Delete LFG post action
     */
    window.deleteLfgPost = async function(id) {
        if (!confirm('Are you sure you want to close and remove your LFG listing?')) return;

        try {
            const response = await fetch('/api/lfg_requests.php', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showAlert(data.message, 'success');
                pollLfgTerminal();
            } else {
                showAlert(data.error || 'Failed to remove listing.', 'error');
            }
        } catch (e) {
            showAlert('Connection error: ' + e.message, 'error');
        }
    }

    /**
     * Browser-to-Game Join Warp execution
     */
    window.joinActiveGame = async function(lobbyId) {
        if (!myActiveChar) {
            showAlert('Warp failed: You must be online in-game to teleport.', 'error');
            return;
        }

        if (!confirm('Initiate Browser-to-Game warp? Your game client will instantly transition to this party!')) return;

        try {
            const response = await fetch('/api/lfg_join.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({ lobby_id: lobbyId })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showAlert('🚀 TELEPORT MATRIX ACTIVE! ' + data.message, 'success');
            } else {
                showAlert('Warp rejected by game gateway: ' + (data.error || 'Unknown server error.'), 'error');
            }
        } catch (e) {
            showAlert('Teleport connection lost: ' + e.message, 'error');
        }
    }

    /**
     * Leave current game group and return to lobby
     */
    window.leaveCurrentGroup = async function() {
        if (!myActiveChar) {
            showAlert('Leave failed: Character is offline.', 'error');
            return;
        }

        if (!confirm('Are you sure you want to leave your active game group? Your character will warp back to a public lobby.')) return;

        try {
            const response = await fetch('/api/lfg_leave.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showAlert('✓ ' + data.message, 'success');
                syncCharacterState();
                pollLfgTerminal();
            } else {
                showAlert('Leave group rejected: ' + (data.error || 'Unknown server error.'), 'error');
            }
        } catch (e) {
            showAlert('Leave group connection lost: ' + e.message, 'error');
        }
    }

    /**
     * Helper to format SQLite datetime strings into friendly "X min ago"
     */
    function formatTimeAgo(dbTimeStr) {
        // SQLite CURRENT_TIMESTAMP is in UTC. Let's calculate difference
        // We replace space with 'T' and add 'Z' for standardized UTC parse
        const utcStr = dbTimeStr.replace(' ', 'T') + 'Z';
        const postTime = new Date(utcStr);
        const now = new Date();
        
        const diffMs = now.getTime() - postTime.getTime();
        const diffMins = Math.max(1, Math.floor(diffMs / 60000));

        if (diffMins < 60) {
            return `${diffMins}m ago`;
        } else {
            const diffHours = Math.floor(diffMins / 60);
            return `${diffHours}h ago`;
        }
    }

    /**
     * Helper to escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
