/**
 * PSOBB Dashboard Portal Modules
 * 
 * Native JS implementations for:
 *  - Bounties: Player's active bounties with claim/abandon
 *  - LFG: Looking for Group with character sync and warp-join
 *  - Drops: Drop chart with episode/difficulty/section ID filters
 * 
 * No iframes. Everything is native AJAX.
 */
(function() {
    'use strict';

    // Shared utilities
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function formatTimeAgo(dbTimeStr) {
        if (!dbTimeStr) return '';
        const utcStr = dbTimeStr.replace(' ', 'T') + 'Z';
        const postTime = new Date(utcStr);
        const diffMs = Date.now() - postTime.getTime();
        const diffMins = Math.max(1, Math.floor(diffMs / 60000));
        if (diffMins < 60) return `${diffMins}m ago`;
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${Math.floor(diffHours / 24)}d ago`;
    }

    function getArchetype(charClass) {
        if (!charClass) return '';
        const upper = charClass.toUpperCase();
        if (upper.startsWith('HU')) return 'HU';
        if (upper.startsWith('RA')) return 'RA';
        if (upper.startsWith('FO')) return 'FO';
        return '';
    }

    const archColors = { HU: '#ff4444', RA: '#44ff44', FO: '#4488ff' };

    // =========================================================================
    // BOUNTIES MODULE
    // =========================================================================
    let bountiesLoaded = false;

    window.portalLoadBounties = async function() {
        const container = document.getElementById('bounty-cards-container');
        const statsBar = document.getElementById('bounty-stats-bar');
        if (!container) return;

        try {
            const res = await fetch('/api/my_bounties_all.php');
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = `<p style="color:#ff4444; font-size:0.85rem;">${escapeHtml(data.error || 'Failed to load bounties.')}</p>`;
                return;
            }

            const bounties = data.bounties || [];
            const stats = data.stats || {};

            // Stats bar
            if (statsBar) {
                if (bounties.length === 0) {
                    statsBar.innerHTML = `<span style="font-size:0.75rem; color:#888; font-family:'Share Tech Mono',monospace;"><i class="fas fa-info-circle"></i> No active bounties. <a href="missions.php" style="color:#00ffff;">Accept bounties from the Guild Board</a></span>`;
                } else {
                    let statsHtml = '';
                    if (stats.claimable > 0) {
                        statsHtml += `<span style="font-size:0.72rem; padding:2px 8px; border-radius:10px; background:rgba(0,255,136,0.12); border:1px solid rgba(0,255,136,0.3); color:#00ff88; font-family:'Share Tech Mono',monospace;"><i class="fas fa-gift"></i> ${stats.claimable} Ready to Claim</span>`;
                    }
                    if (stats.in_progress > 0) {
                        statsHtml += `<span style="font-size:0.72rem; padding:2px 8px; border-radius:10px; background:rgba(0,255,255,0.08); border:1px solid rgba(0,255,255,0.2); color:#00ffff; font-family:'Share Tech Mono',monospace;"><i class="fas fa-hourglass-half"></i> ${stats.in_progress} In Progress</span>`;
                    }
                    statsBar.innerHTML = statsHtml;
                }
            }

            // Render bounty cards
            if (bounties.length === 0) {
                container.innerHTML = `
                    <div style="text-align:center; padding:2rem; border:1px dashed rgba(0,255,255,0.15); border-radius:8px; color:#888;">
                        <i class="fas fa-clipboard-list" style="font-size:2rem; margin-bottom:8px; color:rgba(0,255,255,0.3);"></i><br>
                        <span style="font-size:0.85rem;">No active bounties assigned.</span><br>
                        <a href="missions.php" class="dl-btn" style="display:inline-block; margin-top:12px; text-decoration:none; padding:8px 20px; border-color:#00ffff; color:#00ffff; font-size:0.85rem;">
                            <i class="fas fa-bullseye"></i> Browse Bounty Board
                        </a>
                    </div>`;
                return;
            }

            container.innerHTML = '';
            bounties.forEach(b => {
                const isClaimable = b.status === 'ready_to_redeem';
                const isTeam = b.is_team;
                const borderColor = isClaimable ? 'rgba(0,255,136,0.3)' : (isTeam ? 'rgba(157,78,221,0.3)' : 'rgba(0,255,255,0.2)');
                const statusColor = isClaimable ? '#00ff88' : '#00ffff';
                const statusText = isClaimable ? 'READY TO CLAIM' : 'IN PROGRESS';
                const statusIcon = isClaimable ? 'fa-gift' : 'fa-hourglass-half';

                const card = document.createElement('div');
                card.style.cssText = `border:1px solid ${borderColor}; background:rgba(0,10,20,0.5); border-radius:8px; padding:12px 14px; transition:border-color 0.3s ease, box-shadow 0.3s ease;`;
                if (isClaimable) {
                    card.style.boxShadow = '0 0 12px rgba(0,255,136,0.08)';
                }

                let actionHtml = '';
                if (isClaimable) {
                    actionHtml = `<button onclick="window.portalRedeemBounty(${b.player_mission_id}, this)" class="dl-btn" style="padding:5px 12px; font-size:0.75rem; border-color:#00ff88; color:#00ff88; background:rgba(0,255,136,0.1); white-space:nowrap;"><i class="fas fa-gift"></i> Claim</button>`;
                } else {
                    actionHtml = `
                        <div style="display:flex; gap:6px;">
                            <a href="missions.php" class="dl-btn" style="text-decoration:none; padding:5px 10px; font-size:0.7rem; border-color:rgba(0,255,255,0.3); color:#00ffff;"><i class="fas fa-eye"></i></a>
                            <button onclick="window.portalAbandonBounty(${b.player_mission_id}, this)" class="dl-btn" style="padding:5px 10px; font-size:0.7rem; border-color:rgba(255,68,68,0.3); color:#ff4444; background:rgba(255,68,68,0.05);"><i class="fas fa-times"></i></button>
                        </div>`;
                }

                card.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
                                <span style="font-size:0.65rem; padding:1px 6px; border-radius:3px; background:${isClaimable ? 'rgba(0,255,136,0.12)' : 'rgba(0,255,255,0.08)'}; border:1px solid ${isClaimable ? 'rgba(0,255,136,0.25)' : 'rgba(0,255,255,0.15)'}; color:${statusColor}; font-family:'Share Tech Mono',monospace; text-transform:uppercase;"><i class="fas ${statusIcon}"></i> ${statusText}</span>
                                ${isTeam ? '<span style="font-size:0.6rem; padding:1px 5px; border-radius:3px; background:rgba(157,78,221,0.12); border:1px solid rgba(157,78,221,0.25); color:#d288ff; font-family:\'Share Tech Mono\',monospace;">TEAM</span>' : ''}
                            </div>
                            <h4 style="margin:4px 0 2px; color:#fff; font-size:0.9rem; font-family:'Share Tech Mono',monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(b.title)}</h4>
                            <p style="margin:0 0 6px; font-size:0.78rem; color:rgba(255,255,255,0.55); line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${escapeHtml(b.description)}</p>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <span style="font-size:0.7rem; color:#ffaa00;"><i class="fas fa-trophy"></i> ${escapeHtml(b.reward_display || 'Reward')}</span>
                                ${b.character_name ? `<span style="font-size:0.65rem; color:#888;"><i class="fas fa-user"></i> ${escapeHtml(b.character_name)}</span>` : ''}
                            </div>
                        </div>
                        <div style="flex-shrink:0; display:flex; align-items:center;">
                            ${actionHtml}
                        </div>
                    </div>`;
                container.appendChild(card);
            });

            bountiesLoaded = true;
        } catch (e) {
            container.innerHTML = `<p style="color:#ff4444; font-size:0.85rem;"><i class="fas fa-exclamation-triangle"></i> Connection error: ${escapeHtml(e.message)}</p>`;
        }
    };

    // Redeem a bounty from the portal
    window.portalRedeemBounty = async function(pmId, btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        const alertEl = document.getElementById('bounty-action-alert');

        try {
            const response = await fetch('/api/redeem_bounty.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                body: JSON.stringify({ player_mission_id: pmId })
            });
            const data = await response.json();

            if (response.ok && data.success) {
                if (alertEl) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = 'rgba(0,255,136,0.1)';
                    alertEl.style.border = '1px solid rgba(0,255,136,0.3)';
                    alertEl.style.color = '#00ff88';
                    alertEl.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message || 'Reward claimed!'}`;
                }
                // Refresh bounties
                setTimeout(() => window.portalLoadBounties(), 1500);
            } else {
                throw new Error(data.error || 'Claim failed.');
            }
        } catch (e) {
            btnEl.disabled = false;
            btnEl.innerHTML = '<i class="fas fa-gift"></i> Claim';
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(255,68,68,0.1)';
                alertEl.style.border = '1px solid rgba(255,68,68,0.3)';
                alertEl.style.color = '#ff4444';
                alertEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${escapeHtml(e.message)}`;
            }
        }
    };

    // Abandon a bounty from the portal
    window.portalAbandonBounty = async function(pmId, btnEl) {
        if (!confirm('Abandon this bounty? You can accept it again later from the Guild Board.')) return;
        
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch('/api/abandon_bounty.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                body: JSON.stringify({ player_mission_id: pmId })
            });
            const data = await response.json();

            if (response.ok && data.success) {
                window.portalLoadBounties();
            } else {
                alert(data.error || 'Failed to abandon bounty.');
                btnEl.disabled = false;
                btnEl.innerHTML = '<i class="fas fa-times"></i>';
            }
        } catch (e) {
            alert('Connection error: ' + e.message);
            btnEl.disabled = false;
            btnEl.innerHTML = '<i class="fas fa-times"></i>';
        }
    };

    // =========================================================================
    // LFG MODULE
    // =========================================================================
    let lfgInitialized = false;
    let lfgPollInterval = null;
    let lfgCharSyncInterval = null;
    let lfgActiveChar = null;
    let lfgMyAccountId = 0;

    window.portalLfgInit = function() {
        // Get account ID from page (set in showDashboard)
        const accIdEl = document.getElementById('dashboard');
        if (!lfgMyAccountId) {
            // Try to get from sessionStorage or a global
            lfgMyAccountId = window._portalAccountId || 0;
        }

        if (!lfgInitialized) {
            lfgInitialized = true;
            portalLfgSyncChar();
            portalLfgPoll();
            // Poll every 10 seconds
            lfgPollInterval = setInterval(portalLfgPoll, 10000);
            lfgCharSyncInterval = setInterval(portalLfgSyncChar, 20000);
        } else {
            // Just refresh
            portalLfgPoll();
        }
    };

    window.portalLfgRefresh = function() {
        portalLfgPoll();
        portalLfgSyncChar();
    };

    async function portalLfgSyncChar() {
        const panel = document.getElementById('lfg-char-sync');
        const postBtn = document.getElementById('lfg-quick-post-btn');
        if (!panel) return;

        try {
            const res = await fetch('/api/summary.php');
            const data = await res.json();

            lfgActiveChar = null;
            lfgMyAccountId = window._portalAccountId || 0;

            if (data.Clients && lfgMyAccountId) {
                lfgActiveChar = data.Clients.find(c => parseInt(c.AccountID) === lfgMyAccountId && c.Name);
            }

            if (lfgActiveChar) {
                const arch = getArchetype(lfgActiveChar.Class);
                const archColor = archColors[arch] || '#00ffff';
                let inGame = false;

                if (lfgActiveChar.LobbyID !== null && data.Games) {
                    inGame = data.Games.some(g => parseInt(g.ID) === parseInt(lfgActiveChar.LobbyID));
                }
                lfgActiveChar.inGame = inGame;

                panel.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:#00ffc8; font-weight:bold;"><i class="fas fa-user-circle"></i> ${escapeHtml(lfgActiveChar.Name)}</span>
                        <span style="font-size:0.7rem; color:${archColor}; font-weight:bold;">${arch} Lv.${lfgActiveChar.Level}</span>
                    </div>
                    <div style="font-size:0.72rem; color:#aaa; margin-top:4px; border-top:1px dashed rgba(255,255,255,0.05); padding-top:3px;">
                        ${inGame 
                            ? `<span style="color:var(--pso-blue);">In Game #${lfgActiveChar.LobbyID}</span>` 
                            : `<span>Lobby ${lfgActiveChar.LobbyID !== null ? '#' + lfgActiveChar.LobbyID : '...'}</span>`
                        }
                    </div>`;

                // Enable/disable post button
                if (postBtn) {
                    if (inGame) {
                        postBtn.disabled = false;
                        postBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Post';
                    } else {
                        postBtn.disabled = true;
                        postBtn.innerHTML = '<i class="fas fa-lock"></i> Need Party';
                    }
                }
            } else {
                panel.innerHTML = `<div style="color:#ffaa00; font-size:0.8rem; text-align:center;"><i class="fas fa-exclamation-triangle"></i> OFFLINE<br><span style="font-size:0.7rem; color:#888;">Log in-game to use LFG.</span></div>`;
                if (postBtn) {
                    postBtn.disabled = true;
                    postBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Offline';
                }
            }
        } catch (e) {
            panel.innerHTML = '<span style="color:#ff4444;">Sync error</span>';
        }
    }

    async function portalLfgPoll() {
        const feed = document.getElementById('lfg-listings-feed');
        const badge = document.getElementById('lfg-count-badge');
        if (!feed) return;

        try {
            const [gamesRes, listingsRes] = await Promise.all([
                fetch('/api/lfg_games.php'),
                fetch('/api/lfg_requests.php')
            ]);
            const gamesData = await gamesRes.json();
            const listingsData = await listingsRes.json();
            const games = (gamesData.success && gamesData.games) ? gamesData.games : [];
            const listings = (listingsData.success && listingsData.listings) ? listingsData.listings : [];

            if (badge) badge.textContent = listings.length;

            if (listings.length === 0) {
                feed.innerHTML = `
                    <div style="text-align:center; padding:2rem; border:1px dashed rgba(255,170,0,0.15); border-radius:8px; color:#888;">
                        <i class="fas fa-satellite-dish" style="font-size:2rem; margin-bottom:8px; color:rgba(255,170,0,0.3);"></i><br>
                        <span style="font-size:0.85rem;">No active LFG posts. Be the first!</span>
                    </div>`;
                return;
            }

            feed.innerHTML = '';
            listings.forEach(l => {
                const arch = getArchetype(l.class);
                const archColor = archColors[arch] || '#aaa';
                const isMine = lfgMyAccountId && parseInt(l.account_id) === lfgMyAccountId;
                const timeAgo = formatTimeAgo(l.created_at);

                // Find matching game
                let matchingGame = null;
                if (l.game_id !== null) {
                    matchingGame = games.find(g => parseInt(g.ID) === parseInt(l.game_id));
                }

                // Check if this player wants my archetype
                let isWanted = false;
                if (lfgActiveChar && l.looking_for && !isMine) {
                    const myArch = getArchetype(lfgActiveChar.Class);
                    isWanted = l.looking_for.split(',').includes(myArch);
                }

                // Build card
                const card = document.createElement('div');
                const borderColor = isWanted ? 'rgba(255,170,0,0.4)' : (isMine ? 'rgba(0,255,255,0.25)' : 'rgba(255,255,255,0.08)');
                card.style.cssText = `border:1px solid ${borderColor}; background:rgba(0,10,20,0.5); border-radius:8px; padding:12px; transition:all 0.3s ease;`;
                if (isWanted) card.style.boxShadow = '0 0 12px rgba(255,170,0,0.1)';

                // Seeking badges
                let seekHtml = '';
                if (l.looking_for) {
                    l.looking_for.split(',').forEach(a => {
                        const c = archColors[a] || '#aaa';
                        seekHtml += `<span style="font-size:0.6rem; padding:1px 5px; border-radius:3px; background:rgba(${a==='HU'?'255,68,68':a==='RA'?'68,255,68':'68,136,255'},0.12); border:1px solid ${c}; color:${c}; font-family:'Share Tech Mono',monospace;">${a}</span>`;
                    });
                }

                // Game info
                let gameHtml = '';
                if (matchingGame) {
                    const g = matchingGame;
                    gameHtml = `
                        <div style="margin-top:8px; padding:8px; background:rgba(0,255,255,0.03); border:1px solid rgba(0,255,255,0.1); border-radius:6px; font-size:0.75rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="color:#00ffc8; font-family:'Share Tech Mono',monospace;"><i class="fas fa-gamepad"></i> ${escapeHtml(g.Name)}</span>
                                <span style="color:#888;">${g.Players}/${g.MaxClients}</span>
                            </div>
                            <div style="display:flex; gap:4px; margin-top:4px; flex-wrap:wrap;">
                                <span style="font-size:0.6rem; padding:1px 4px; border-radius:3px; background:rgba(0,255,255,0.08); border:1px solid rgba(0,255,255,0.15); color:#00ffff;">${g.Episode}</span>
                                <span style="font-size:0.6rem; padding:1px 4px; border-radius:3px; background:rgba(157,78,221,0.08); border:1px solid rgba(157,78,221,0.2); color:#d288ff;">${g.Difficulty}</span>
                                <span style="font-size:0.6rem; padding:1px 4px; border-radius:3px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); color:#aaa;">Lv.${g.MinLevel}-${g.MaxLevel}</span>
                            </div>
                        </div>`;
                }

                // Bounty badge
                let bountyHtml = '';
                if (l.bounty_id && l.bounty_title) {
                    bountyHtml = `<div style="margin-top:6px; font-size:0.72rem; color:#ffaa00;"><i class="fas fa-crosshairs"></i> Bounty: ${escapeHtml(l.bounty_title)}</div>`;
                }

                // Action button
                let actionBtn = '';
                if (isMine) {
                    actionBtn = `<button onclick="window.portalLfgDelete(${l.id})" class="dl-btn" style="padding:4px 8px; font-size:0.7rem; border-color:#ff4444; color:#ff4444;"><i class="fas fa-trash-alt"></i></button>`;
                } else if (matchingGame && lfgActiveChar) {
                    const lobbyFull = matchingGame.Players >= matchingGame.MaxClients;
                    if (lobbyFull) {
                        actionBtn = `<span style="font-size:0.7rem; color:#888;"><i class="fas fa-users"></i> FULL</span>`;
                    } else {
                        actionBtn = `<button onclick="window.portalLfgJoin(${l.game_id})" class="dl-btn" style="padding:4px 10px; font-size:0.7rem; border-color:#00ff88; color:#00ff88; background:rgba(0,255,136,0.08);"><i class="fas fa-rocket"></i> Warp</button>`;
                    }
                }

                card.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;">
                                <span style="color:#ffaa00; font-weight:bold; font-size:0.85rem;">${escapeHtml(l.character_name)}</span>
                                <span style="font-size:0.6rem; padding:1px 4px; border-radius:3px; background:rgba(${arch==='HU'?'255,68,68':arch==='RA'?'68,255,68':'68,136,255'},0.12); border:1px solid ${archColor}; color:${archColor}; font-family:'Share Tech Mono',monospace;">${arch}</span>
                                <span style="font-size:0.65rem; color:#888;">Lv.${l.level}</span>
                                ${isWanted ? '<span style="font-size:0.6rem; color:#ffaa00; font-weight:bold;">🔥 MATCH</span>' : ''}
                            </div>
                            <p style="margin:0; font-size:0.8rem; color:rgba(255,255,255,0.85); font-style:italic; line-height:1.3;">"${escapeHtml(l.description)}"</p>
                            ${seekHtml ? `<div style="display:flex; gap:3px; margin-top:6px; align-items:center;"><span style="font-size:0.6rem; color:#888;">Seeking:</span>${seekHtml}</div>` : ''}
                            ${bountyHtml}
                            ${gameHtml}
                        </div>
                        <div style="flex-shrink:0; display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                            <span style="font-size:0.65rem; color:#666;"><i class="far fa-clock"></i> ${timeAgo}</span>
                            ${actionBtn}
                        </div>
                    </div>`;
                feed.appendChild(card);
            });
        } catch (e) {
            feed.innerHTML = `<p style="color:#ff4444; font-size:0.85rem;"><i class="fas fa-exclamation-triangle"></i> Error: ${escapeHtml(e.message)}</p>`;
        }
    }

    // Quick post LFG
    window.portalLfgPost = async function() {
        const desc = document.getElementById('lfg-quick-desc')?.value;
        const alertEl = document.getElementById('lfg-alert');
        const btn = document.getElementById('lfg-quick-post-btn');

        if (!desc || !desc.trim()) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(255,170,0,0.1)';
                alertEl.style.border = '1px solid rgba(255,170,0,0.3)';
                alertEl.style.color = '#ffaa00';
                alertEl.innerHTML = '<i class="fas fa-info-circle"></i> Please enter a description.';
                setTimeout(() => alertEl.style.display = 'none', 3000);
            }
            return;
        }

        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        const checkboxes = document.querySelectorAll('.lfg-seek-check:checked');
        const lookingFor = Array.from(checkboxes).map(cb => cb.value).join(',') || null;

        try {
            const response = await fetch('/api/lfg_requests.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                body: JSON.stringify({ description: desc, looking_for: lookingFor })
            });
            const data = await response.json();

            if (response.ok && data.success) {
                document.getElementById('lfg-quick-desc').value = '';
                if (alertEl) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = 'rgba(0,255,136,0.1)';
                    alertEl.style.border = '1px solid rgba(0,255,136,0.3)';
                    alertEl.style.color = '#00ff88';
                    alertEl.innerHTML = `<i class="fas fa-check-circle"></i> ${data.message || 'Posted!'}`;
                    setTimeout(() => alertEl.style.display = 'none', 4000);
                }
                portalLfgPoll();
            } else {
                throw new Error(data.error || 'Failed to post.');
            }
        } catch (e) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(255,68,68,0.1)';
                alertEl.style.border = '1px solid rgba(255,68,68,0.3)';
                alertEl.style.color = '#ff4444';
                alertEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${escapeHtml(e.message)}`;
            }
        } finally {
            if (btn && lfgActiveChar && lfgActiveChar.inGame) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plus-circle"></i> Post';
            }
        }
    };

    // Delete LFG post
    window.portalLfgDelete = async function(id) {
        if (!confirm('Close your LFG listing?')) return;
        try {
            const response = await fetch('/api/lfg_requests.php', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                body: JSON.stringify({ id: id })
            });
            const data = await response.json();
            if (response.ok && data.success) portalLfgPoll();
        } catch (e) { console.error('Delete LFG error:', e); }
    };

    // Join game warp
    window.portalLfgJoin = async function(lobbyId) {
        if (!lfgActiveChar) return;
        if (!confirm('Warp to this game? Your client will transition immediately.')) return;
        try {
            const response = await fetch('/api/lfg_join.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRFToken() },
                body: JSON.stringify({ lobby_id: lobbyId })
            });
            const data = await response.json();
            const alertEl = document.getElementById('lfg-alert');
            if (response.ok && data.success) {
                if (alertEl) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = 'rgba(0,255,136,0.1)';
                    alertEl.style.border = '1px solid rgba(0,255,136,0.3)';
                    alertEl.style.color = '#00ff88';
                    alertEl.innerHTML = `<i class="fas fa-rocket"></i> ${data.message}`;
                    setTimeout(() => alertEl.style.display = 'none', 5000);
                }
            } else {
                if (alertEl) {
                    alertEl.style.display = 'block';
                    alertEl.style.background = 'rgba(255,68,68,0.1)';
                    alertEl.style.border = '1px solid rgba(255,68,68,0.3)';
                    alertEl.style.color = '#ff4444';
                    alertEl.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${escapeHtml(data.error || 'Warp failed.')}`;
                }
            }
        } catch (e) { alert('Warp error: ' + e.message); }
    };

    // =========================================================================
    // DROPS MODULE
    // =========================================================================
    let dropsInitialized = false;

    window.portalDropsInit = function() {
        if (!dropsInitialized) {
            dropsInitialized = true;
            // Auto-detect section ID from active character
            portalDropsDetectChar();
        }
    };

    async function portalDropsDetectChar() {
        try {
            const res = await fetch('/api/summary.php');
            const data = await res.json();
            const accId = window._portalAccountId || 0;
            if (data.Clients && accId) {
                const char = data.Clients.find(c => parseInt(c.AccountID) === accId && c.Name);
                if (char && char.SectionID) {
                    const hint = document.getElementById('drops-char-hint');
                    if (hint) {
                        hint.style.display = 'block';
                        hint.innerHTML = `<i class="fas fa-user-circle"></i> <strong>${escapeHtml(char.Name)}</strong> is <strong>${escapeHtml(char.SectionID)}</strong> — auto-selected.`;
                    }
                    const select = document.getElementById('drops-section-id');
                    if (select) {
                        for (let opt of select.options) {
                            if (opt.value.toLowerCase() === char.SectionID.toLowerCase()) {
                                select.value = opt.value;
                                break;
                            }
                        }
                    }
                    // Auto-load drops
                    window.portalLoadDrops();
                }
            }
        } catch (e) { /* silent */ }
    }

    window.portalLoadDrops = async function() {
        const container = document.getElementById('drops-table-container');
        if (!container) return;

        const episode = document.getElementById('drops-episode')?.value || 'Episode_1';
        const difficulty = document.getElementById('drops-difficulty')?.value || 'Ultimate';
        const sectionId = document.getElementById('drops-section-id')?.value || 'Viridia';

        container.innerHTML = '<div class="skeleton" style="height:200px; border-radius:8px;"></div>';

        try {
            const res = await fetch(`/api/get_drops.php?episode=${encodeURIComponent(episode)}&difficulty=${encodeURIComponent(difficulty)}&section_id=${encodeURIComponent(sectionId)}`);
            const data = await res.json();

            if (!data.success || !data.drops || data.drops.length === 0) {
                container.innerHTML = `<p style="color:#888; text-align:center; padding:2rem; font-family:'Share Tech Mono',monospace;"><i class="fas fa-search"></i> No drop data found for ${episode.replace('_', ' ')} / ${difficulty} / ${sectionId}.</p>`;
                return;
            }

            // Build responsive table
            let tableHtml = `
                <table style="width:100%; border-collapse:collapse; font-size:0.8rem; font-family:'Share Tech Mono',monospace;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(0,255,200,0.2);">
                            <th style="padding:8px; text-align:left; color:#00ffc8; font-weight:bold;">Enemy</th>
                            <th style="padding:8px; text-align:left; color:#00ffc8; font-weight:bold;">Item</th>
                            <th style="padding:8px; text-align:right; color:#00ffc8; font-weight:bold;">Rate</th>
                        </tr>
                    </thead>
                    <tbody>`;

            data.drops.forEach((drop, i) => {
                const bg = i % 2 === 0 ? 'rgba(0,255,200,0.02)' : 'transparent';
                const rateColor = parseFloat(drop.rate) <= 1 ? '#ff4444' : (parseFloat(drop.rate) <= 10 ? '#ffaa00' : '#00ffc8');
                tableHtml += `
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.03); background:${bg};">
                        <td style="padding:6px 8px; color:rgba(255,255,255,0.7);">${escapeHtml(drop.enemy || drop.monster)}</td>
                        <td style="padding:6px 8px; color:#fff; font-weight:500;">${escapeHtml(drop.item || drop.item_name)}</td>
                        <td style="padding:6px 8px; text-align:right; color:${rateColor}; font-weight:bold;">${escapeHtml(drop.rate || drop.drop_rate)}</td>
                    </tr>`;
            });

            tableHtml += '</tbody></table>';
            container.innerHTML = tableHtml;
        } catch (e) {
            container.innerHTML = `<p style="color:#ff4444; font-size:0.85rem;"><i class="fas fa-exclamation-triangle"></i> Error loading drops: ${escapeHtml(e.message)}</p>`;
        }
    };

})();
