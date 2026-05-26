// js/character_viewer.js
/**
 * PSOBB Client Controller: Character & Bank Viewer
 * 
 * Manages rendering, tooltip overlays, slot selections, multi-bank live filtering,
 * and integration broadcasts to the game server.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial State Definitions
    let activeSlot = 0;
    let bankCache = {}; // Cache of all slots bank items to support global cross-bank searches
    let activeCharData = null;
    let activeBankIndex = 0; // 0 = character, -1 = shared
    
    // Create floating tooltip element dynamically
    const tooltipEl = document.createElement('div');
    tooltipEl.id = 'viewer-tooltip';
    tooltipEl.className = 'item-tooltip';
    document.body.appendChild(tooltipEl);
    
    // Render slot buttons click listeners
    document.querySelectorAll('.slot-tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const slot = parseInt(e.currentTarget.getAttribute('data-slot'));
            switchSlot(slot);
        });
    });
    
    // Render Bank selector change
    const bankSelect = document.getElementById('viewer-bank-select');
    if (bankSelect) {
        bankSelect.addEventListener('change', (e) => {
            activeBankIndex = parseInt(e.target.value);
            renderActiveBank();
        });
    }
    
    // Live Search Keyup
    const searchInput = document.getElementById('viewer-bank-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            filterBankGrid(e.target.value.toLowerCase());
        });
    }
    
    // Bank Swap button Click
    const swapBtn = document.getElementById('viewer-btn-swap-bank');
    if (swapBtn) {
        swapBtn.addEventListener('click', () => {
            triggerBankSwap();
        });
    }
    
    // Start initial load
    loadCharacter(0);
    
    // 2. Fetch and Load Character Slots
    async function loadCharacter(slotIndex) {
        const mainContainer = document.getElementById('viewer-content-pane');
        const loaderEl = document.getElementById('viewer-loader');
        
        if (mainContainer) mainContainer.style.opacity = '0.4';
        if (loaderEl) loaderEl.style.display = 'block';
        
        try {
            const res = await fetch(`/api/character_viewer.php?slot=${slotIndex}`);
            const data = await res.json();
            
            if (res.ok && data.success) {
                activeCharData = data.character;
                
                // Cache bank items for search context
                bankCache[slotIndex] = data.character.bank.items;
                bankCache['shared'] = data.character.shared_bank.items;
                
                // Populating stats & metadata
                renderCharacterProfile();
                renderInventory();
                renderActiveBank();
                
                // Reset search bar on character switch
                if (searchInput) searchInput.value = '';
                
            } else {
                throw new Error(data.error || 'Failed to retrieve character data.');
            }
        } catch (e) {
            console.error(e);
            alert(`Error: ${e.message}`);
        } finally {
            if (mainContainer) mainContainer.style.opacity = '1';
            if (loaderEl) loaderEl.style.display = 'none';
        }
    }
    
    function switchSlot(slotIndex) {
        document.querySelectorAll('.slot-tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (parseInt(btn.getAttribute('data-slot')) === slotIndex) {
                btn.classList.add('active');
            }
        });
        activeSlot = slotIndex;
        loadCharacter(slotIndex);
    }
    
    // 3. Render Profile (Stats & Materials)
    function renderCharacterProfile() {
        const c = activeCharData;
        
        // Identity details
        document.getElementById('char-profile-name').textContent = c.name;
        document.getElementById('char-profile-level').textContent = `Lvl ${c.level}`;
        document.getElementById('char-profile-playtime').textContent = `${c.play_time_hours} hrs`;
        
        // Online Badge
        const onlineBadge = document.getElementById('char-profile-online');
        if (onlineBadge) {
            if (c.online) {
                onlineBadge.innerHTML = '<span style="color: #00ffc8; text-shadow: 0 0 5px rgba(0,255,200,0.5);"><i class="fas fa-circle animate-pulse"></i> ONLINE</span>';
            } else {
                onlineBadge.innerHTML = '<span style="color: #666;"><i class="far fa-circle"></i> OFFLINE</span>';
            }
        }
        
        // Class Badge & Avatar
        const classBadge = document.getElementById('char-profile-class');
        if (classBadge) {
            classBadge.textContent = c.class;
        }
        
        const avatarEl = document.getElementById('char-profile-avatar');
        if (avatarEl) {
            avatarEl.src = `/img/classes/${c.class.toLowerCase()}.png`;
            avatarEl.onerror = () => { avatarEl.src = '/img/favicon.svg'; }; // Fallback
        }
        
        // Section ID Badge
        const secIdBadge = document.getElementById('char-profile-secid');
        if (secIdBadge) {
            secIdBadge.innerHTML = `
                <img src="/img/section_ids/${c.section_id}.png" alt="${c.section_id}">
                <span class="section-id id-${c.section_id.toLowerCase()}">${c.section_id}</span>
            `;
        }
        
        // Stats grid
        const stats = ['ATP', 'MST', 'EVP', 'HP', 'DFP', 'ATA', 'LCK', 'Meseta'];
        stats.forEach(s => {
            const el = document.getElementById(`stat-val-${s.toLowerCase()}`);
            if (el) {
                el.textContent = s === 'Meseta' ? parseInt(c.stats[s]).toLocaleString() : c.stats[s];
            }
        });
        
        // Material Usage Bars
        const maxMats = (c.class.startsWith('HU') || c.class.startsWith('RA')) ? 250 : 150;
        const hpMax = 125;
        const tpMax = 125;
        
        const matProgresses = {
            'hp': { val: c.mats.HP, max: hpMax },
            'tp': { val: c.mats.TP, max: tpMax },
            'power': { val: c.mats.Power, max: maxMats },
            'mind': { val: c.mats.Mind, max: maxMats },
            'evade': { val: c.mats.Evade, max: maxMats },
            'def': { val: c.mats.Def, max: maxMats },
            'luck': { val: c.mats.Luck, max: 45 }
        };
        
        Object.keys(matProgresses).forEach(m => {
            const data = matProgresses[m];
            const valEl = document.getElementById(`mat-val-${m}`);
            const barEl = document.getElementById(`mat-bar-${m}`);
            
            if (valEl) valEl.textContent = `${data.val} / ${data.max}`;
            if (barEl) {
                const pct = Math.min(100, (data.val / data.max) * 100);
                barEl.style.width = `${pct}%`;
            }
        });
    }
    
    // 4. Render Inventory (Equipped & Grid)
    function renderInventory() {
        const inv = activeCharData.inventory;
        
        // Equipped Slots Initialization
        const gearSlots = {
            'weapon': null, 'armor': null, 'shield': null, 
            'unit1': null, 'unit2': null, 'unit3': null, 'unit4': null, 'mag': null
        };
        
        let unitCount = 1;
        
        // Fill equipped gear
        inv.forEach(item => {
            if (item.equipped) {
                if (item.group === 0x00) {
                    gearSlots['weapon'] = item;
                } else if (item.group === 0x01) {
                    if (item.name === 'Armor') gearSlots['armor'] = item;
                    else if (item.name === 'Shield') gearSlots['shield'] = item;
                    else if (item.name === 'Unit' && unitCount <= 4) {
                        gearSlots[`unit${unitCount}`] = item;
                        unitCount++;
                    }
                } else if (item.group === 0x02) {
                    gearSlots['mag'] = item;
                }
            }
        });
        
        // Render Equipped Grid HTML
        const equippedBox = document.getElementById('viewer-equipped-grid');
        if (equippedBox) {
            equippedBox.innerHTML = '';
            Object.keys(gearSlots).forEach(slotKey => {
                const item = gearSlots[slotKey];
                equippedBox.appendChild(createItemSlotElement(item, slotKey));
            });
        }
        
        // Render Backpack Grid HTML (30 slots)
        const backpackGrid = document.getElementById('viewer-backpack-grid');
        if (backpackGrid) {
            backpackGrid.innerHTML = '';
            for (let i = 0; i < 30; i++) {
                const item = inv[i] || null;
                backpackGrid.appendChild(createItemSlotElement(item));
            }
        }
        
        setupTooltipTriggers();
    }
    
    // 5. Render active Bank tab (Shared vs Character Bank)
    function renderActiveBank() {
        const bankItemsGrid = document.getElementById('viewer-bank-grid');
        if (!bankItemsGrid) return;
        
        bankItemsGrid.innerHTML = '';
        
        const currentBank = activeBankIndex === -1 ? activeCharData.shared_bank : activeCharData.bank;
        
        // Update Bank Meseta total display
        const bankMesetaEl = document.getElementById('viewer-bank-meseta');
        if (bankMesetaEl) {
            bankMesetaEl.textContent = parseInt(currentBank.meseta).toLocaleString() + ' Meseta';
        }
        
        // Render the 200 grid boxes
        for (let i = 0; i < 200; i++) {
            const item = currentBank.items[i] || null;
            bankItemsGrid.appendChild(createItemSlotElement(item));
        }
        
        setupTooltipTriggers();
        
        // Keep active search filter alive
        if (searchInput && searchInput.value) {
            filterBankGrid(searchInput.value.toLowerCase());
        }
    }
    
    // Helper to generate a single visual slot box
    function createItemSlotElement(item, label = '') {
        const slotEl = document.createElement('div');
        slotEl.className = 'item-slot';
        
        if (item) {
            slotEl.setAttribute('data-hex', item.hex);
            
            // Set rarity classes for borders
            if (item.name.includes('Psycho Wand') || item.name.includes('Sealed J-Sword') || item.name.includes('Sato')) {
                slotEl.classList.add('rare-red');
            } else if (item.name.includes('Spread Needle') || item.name.includes('Heaven Punisher') || item.name.includes('Diwari')) {
                slotEl.classList.add('rare-orange');
            } else if (item.name.includes('Luminous Field') || item.name.includes('Stand Still') || item.name.includes('Photon')) {
                slotEl.classList.add('rare-purple');
            }
            
            // Item Icon mapping based on groups
            const imgEl = document.createElement('img');
            imgEl.className = 'item-slot-icon';
            
            let iconCat = 'tool';
            if (item.group === 0x00) iconCat = 'weapon';
            else if (item.group === 0x01) {
                if (item.name === 'Armor') iconCat = 'armor';
                else if (item.name === 'Shield') iconCat = 'shield';
                else iconCat = 'unit';
            } else if (item.group === 0x02) iconCat = 'mag';
            
            // Safe fallback icons
            imgEl.src = `/img/items/${iconCat}.png`;
            imgEl.onerror = () => { imgEl.src = '/img/favicon.svg'; };
            slotEl.appendChild(imgEl);
            
            // If equipped, render badge
            if (item.equipped && !label) {
                const eqBadge = document.createElement('span');
                eqBadge.className = 'item-slot-equipped-badge';
                eqBadge.textContent = 'Eq';
                slotEl.appendChild(eqBadge);
            }
            
            // Count overlay (tools or stacked items)
            if (item.count && item.count > 1) {
                const countBadge = document.createElement('span');
                countBadge.className = 'item-slot-count';
                countBadge.textContent = item.count;
                slotEl.appendChild(countBadge);
            }
            
            // Attach full item details inside a hidden JSON data-attribute for tooltips
            slotEl.setAttribute('data-item-json', JSON.stringify(item));
        } else {
            // Slot is empty
            slotEl.classList.add('empty-slot');
            if (label) {
                const lbl = document.createElement('span');
                lbl.className = 'slot-gear-label';
                lbl.textContent = label;
                slotEl.appendChild(lbl);
            }
        }
        
        return slotEl;
    }
    
    // 6. Tooltip hover actions
    function setupTooltipTriggers() {
        const slots = document.querySelectorAll('.item-slot');
        slots.forEach(slot => {
            slot.addEventListener('mouseenter', (e) => {
                const rawJson = e.currentTarget.getAttribute('data-item-json');
                if (!rawJson) {
                    showEmptyTooltip(e);
                    return;
                }
                const item = JSON.parse(rawJson);
                showItemTooltip(e, item);
            });
            
            slot.addEventListener('mousemove', (e) => {
                const mouseX = e.pageX;
                const mouseY = e.pageY;
                
                // Offset tooltip location relative to mouse pointer
                tooltipEl.style.left = `${mouseX + 15}px`;
                tooltipEl.style.top = `${mouseY + 15}px`;
            });
            
            slot.addEventListener('mouseleave', () => {
                tooltipEl.style.display = 'none';
            });
        });
    }
    
    function showItemTooltip(e, item) {
        let html = `<div class="tooltip-title">${escapeHtml(item.name)}</div>`;
        
        if (item.group === 0x00) {
            // Weapon specs
            html += `<div class="tooltip-spec">Grind: +${item.grind || 0}</div>`;
            if (item.attrs && item.attrs.length > 0) {
                html += '<div class="tooltip-attrs">';
                item.attrs.forEach(a => {
                    const color = a.value > 0 ? '#00ffc8' : '#ff4444';
                    html += `
                        <div class="tooltip-attr-row">
                            <span>${a.type}</span>
                            <span style="color: ${color}; font-weight: bold;">${a.value > 0 ? '+' : ''}${a.value}%</span>
                        </div>
                    `;
                });
                html += '</div>';
            }
        } else if (item.group === 0x01) {
            // Defense slots
            if (item.name === 'Armor') {
                html += `<div class="tooltip-spec">Slots: ${item.slots} // Def Bonus: +${item.def_bonus}</div>`;
                html += `<div class="tooltip-spec">Evasion Bonus: +${item.evp_bonus}</div>`;
            } else if (item.name === 'Shield') {
                html += `<div class="tooltip-spec">Def Bonus: +${item.def_bonus} // Ev Bonus: +${item.evp_bonus}</div>`;
            } else if (item.name === 'Unit') {
                html += `<div class="tooltip-spec">Modifier: ${item.modifier > 0 ? '+' : ''}${item.modifier}</div>`;
            }
        } else if (item.group === 0x02) {
            // MAG stats
            const m = item.mag_stats;
            html += `
                <div class="tooltip-spec">Level: ${m.level}</div>
                <div class="tooltip-mag-grid">
                    <span>DEF: ${m.def.toFixed(1)}</span>
                    <span>POW: ${m.pow.toFixed(1)}</span>
                    <span>DEX: ${m.dex.toFixed(1)}</span>
                    <span>MND: ${m.mind.toFixed(1)}</span>
                    <span style="grid-column: span 2; border-top: 1px dashed rgba(255,255,255,0.1); padding-top:4px; margin-top:4px;">Synchro: ${m.synchro}% // IQ: ${m.iq}</span>
                </div>
            `;
        } else if (item.count) {
            html += `<div class="tooltip-spec">Qty: ${item.count}</div>`;
        }
        
        // Hex details in monospace footer
        html += `<div style="font-size:0.65rem; color:#666; margin-top:8px; border-top:1px solid rgba(255,255,255,0.08); padding-top:4px; font-family: monospace; letter-spacing: 0.5px;">HEX: ${item.hex.substring(0, 16)}...</div>`;
        
        tooltipEl.innerHTML = html;
        tooltipEl.style.display = 'block';
    }
    
    function showEmptyTooltip(e) {
        const label = e.currentTarget.querySelector('.slot-gear-label');
        if (label) {
            tooltipEl.innerHTML = `<div class="empty-slot-tooltip">Empty ${label.textContent} Slot</div>`;
            tooltipEl.style.display = 'block';
        }
    }
    
    // 7. Live search filters
    function filterBankGrid(query) {
        const slots = document.querySelectorAll('#viewer-bank-grid .item-slot');
        
        // If query is empty, remove all filter overrides
        if (!query) {
            slots.forEach(s => {
                s.classList.remove('search-match');
                s.classList.remove('search-mismatch');
            });
            updateGlobalSearchBadge('');
            return;
        }
        
        slots.forEach(s => {
            const rawJson = s.getAttribute('data-item-json');
            if (rawJson) {
                const item = JSON.parse(rawJson);
                const match = item.name.toLowerCase().includes(query);
                if (match) {
                    s.classList.add('search-match');
                    s.classList.remove('search-mismatch');
                } else {
                    s.classList.add('search-mismatch');
                    s.classList.remove('search-match');
                }
            } else {
                s.classList.add('search-mismatch');
            }
        });
        
        // Global cross-bank search tallying
        performGlobalCrossBankSearch(query);
    }
    
    function performGlobalCrossBankSearch(query) {
        let globalMatches = [];
        
        // Tally items in cached bank slots
        Object.keys(bankCache).forEach(bankKey => {
            const items = bankCache[bankKey];
            items.forEach(item => {
                if (item.name.toLowerCase().includes(query)) {
                    const bankLabel = bankKey === 'shared' ? 'Shared Bank' : `Slot ${parseInt(bankKey) + 1} Bank`;
                    globalMatches.push({ name: item.name, location: bankLabel });
                }
            });
        });
        
        if (globalMatches.length > 0) {
            const details = globalMatches.slice(0, 3).map(m => `"${m.name}" in ${m.location}`).join(', ');
            const suffix = globalMatches.length > 3 ? ` (+${globalMatches.length - 3} more)` : '';
            updateGlobalSearchBadge(`Found ${globalMatches.length} matching items across all banks: ${details}${suffix}`);
        } else {
            updateGlobalSearchBadge('No matching items found in any bank.');
        }
    }
    
    function updateGlobalSearchBadge(msg) {
        const badge = document.getElementById('viewer-search-legend');
        if (badge) {
            if (msg) {
                badge.textContent = msg;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    // 8. Bank Swap trigger call
    async function triggerBankSwap() {
        const swapMsgEl = document.getElementById('viewer-swap-msg');
        const swapBtn = document.getElementById('viewer-btn-swap-bank');
        
        if (!activeCharData) return;
        
        if (swapMsgEl) swapMsgEl.style.display = 'none';
        
        // Verify online status
        if (!activeCharData.online) {
            showSwapMessage('Warning: You must be logged into a character in-game to apply the bank swap immediately.', '#ffaa00');
        }
        
        swapBtn.disabled = true;
        swapBtn.textContent = 'Swapping Bank...';
        
        try {
            const response = await fetch('/api/bank_swap.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.getCSRFToken()
                },
                body: JSON.stringify({ character_name: activeCharData.name, target_bank_index: activeBankIndex })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                showSwapMessage(`✓ Success: ${data.message}`, '#00C851');
                swapBtn.textContent = 'Success!';
                setTimeout(() => {
                    swapBtn.disabled = false;
                    swapBtn.textContent = 'Swap Bank In-Game';
                }, 3000);
            } else {
                throw new Error(data.error || 'Failed to execute bank swap.');
            }
        } catch (e) {
            showSwapMessage(`Error: ${e.message}`, '#ff4444');
            swapBtn.disabled = false;
            swapBtn.textContent = 'Swap Bank In-Game';
        }
    }
    
    function showSwapMessage(text, color) {
        const swapMsgEl = document.getElementById('viewer-swap-msg');
        if (swapMsgEl) {
            swapMsgEl.textContent = text;
            swapMsgEl.style.color = color;
            swapMsgEl.style.display = 'block';
        }
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
});
