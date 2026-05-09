// psobb-website/js/drops.js

let allDrops = [];
let currentFilters = {
    episode: 'All',
    difficulty: 'Ultimate',
    sectionId: 'All',
    itemType: 'All',
    search: '',
    sortBy: 'rarity_asc'
};

document.addEventListener('DOMContentLoaded', async () => {
    initUIControls();
    await fetchActiveCharacter();
    fetchDropData();
});

async function fetchActiveCharacter() {
    const userStr = sessionStorage.getItem('psobb_user');
    if (!userStr) return;
    
    try {
        const user = JSON.parse(userStr);
        const response = await fetch('/api/summary.php');
        const data = await response.json();
        
        if (data.Clients) {
            const activeChar = data.Clients.find(c => c.AccountID === user.AccountID && c.Name);
            if (activeChar && activeChar.SectionID) {
                // Auto select this section ID
                currentFilters.sectionId = activeChar.SectionID;
                updateSectionIdUI(activeChar.SectionID);
                
                // Show a nice little toast or info text
                const info = document.getElementById('active-char-info');
                if(info) {
                    info.innerHTML = `Auto-filtered to <span style="color:#00ffff">${activeChar.Name}</span>'s Section ID: <strong class="id-${activeChar.SectionID.toLowerCase()}">${activeChar.SectionID}</strong>`;
                    info.style.display = 'block';
                }
            }
        }
    } catch (e) {
        console.error("Failed to fetch active character for drops filter", e);
    }
}

function initUIControls() {
    // Episode Toggles
    document.querySelectorAll('.ep-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.ep-toggle').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentFilters.episode = e.target.dataset.val;
            renderDrops();
        });
    });

    // Difficulty Toggles
    document.querySelectorAll('.diff-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.diff-toggle').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentFilters.difficulty = e.target.dataset.val;
            renderDrops();
        });
    });

    // Section ID Toggles
    document.querySelectorAll('.sid-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const target = e.currentTarget;
            const val = target.dataset.val;
            
            if (currentFilters.sectionId === val) {
                // Deselect if already selected
                currentFilters.sectionId = 'All';
                target.classList.remove('active');
            } else {
                document.querySelectorAll('.sid-toggle').forEach(b => b.classList.remove('active'));
                target.classList.add('active');
                currentFilters.sectionId = val;
            }
            renderDrops();
        });
    });

    // Item Type Toggles
    document.querySelectorAll('.type-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.type-toggle').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentFilters.itemType = e.target.dataset.val;
            renderDrops();
        });
    });

    // Search Bar
    const searchInput = document.getElementById('drop-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentFilters.search = e.target.value.toLowerCase();
            renderDrops();
        });
    }

    // Sort Toggles
    document.querySelectorAll('.sort-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.sort-toggle').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentFilters.sortBy = e.target.dataset.val;
            renderDrops();
        });
    });
}

function updateSectionIdUI(sid) {
    document.querySelectorAll('.sid-toggle').forEach(btn => {
        if (btn.dataset.val === sid) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

async function fetchDropData() {
    const container = document.getElementById('drops-grid');
    container.innerHTML = '<div class="drops-loading"><i class="fas fa-circle-notch"></i><p>Decoding Drop Tables...</p></div>';

    try {
        const response = await fetch('/api/get_drops.php');
        const data = await response.json();
        
        if (data.success && data.data) {
            allDrops = data.data;
            if(data.mock) {
                console.warn("Using mock drop data. Ensure newserv endpoint is reachable.");
            }
            renderDrops();
        } else {
            container.innerHTML = '<div style="color:#ff4444; padding: 20px;">Failed to load drop data. Is the game server online?</div>';
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = '<div style="color:#ff4444; padding: 20px;">Connection error while fetching drops.</div>';
    }
}

function renderDrops() {
    const container = document.getElementById('drops-grid');
    const info = document.getElementById('drops-info-text');
    
    // Filter data
    const filtered = allDrops.filter(drop => {
        // Ep
        if (currentFilters.episode !== 'All' && drop.episode.toString() !== currentFilters.episode.toString()) return false;
        
        // Diff
        if (currentFilters.difficulty !== 'All' && drop.difficulty !== currentFilters.difficulty) return false;
        
        // Section ID
        if (currentFilters.sectionId !== 'All' && drop.section_id !== currentFilters.sectionId) return false;
        
        // Item Type
        if (currentFilters.itemType !== 'All') {
            if (drop.type !== currentFilters.itemType) return false;
        }
        
        // Search
        if (currentFilters.search) {
            const term = currentFilters.search;
            if (!drop.item.toLowerCase().includes(term) && !drop.monster.toLowerCase().includes(term)) {
                return false;
            }
        }
        
        return true;
    });

    // Sort logic
    filtered.sort((a, b) => {
        if (currentFilters.sortBy === 'rarity_asc') {
            return a.rate_percent - b.rate_percent;
        } else if (currentFilters.sortBy === 'rarity_desc') {
            return b.rate_percent - a.rate_percent;
        } else if (currentFilters.sortBy === 'type') {
            const typeCompare = (a.type || '').localeCompare(b.type || '');
            if (typeCompare === 0) return a.rate_percent - b.rate_percent;
            return typeCompare;
        } else if (currentFilters.sortBy === 'name') {
            return a.item.localeCompare(b.item);
        } else if (currentFilters.sortBy === 'enemy') {
            return a.monster.localeCompare(b.monster) || b.rate_percent - a.rate_percent;
        }
        return 0;
    });

    if (info) {
        info.textContent = `Showing ${filtered.length} drops`;
    }

    container.innerHTML = '';

    if (filtered.length === 0) {
        container.innerHTML = '<div style="color:#aaa; text-align:center; padding: 40px; grid-column: 1/-1;">No drops found matching these filters.</div>';
        return;
    }

    // Render cards with staggered animation
    filtered.forEach((drop, index) => {
        const delay = Math.min(index * 0.03, 1.5); // Cap delay so it doesn't take forever
        
        const card = document.createElement('div');
        card.className = `drop-card sid-${drop.section_id.toLowerCase()}`;
        card.style.animationDelay = `${delay}s`;
        
        let typeBadge = '';
        if (drop.type) {
            typeBadge = `<span style="font-size:0.7rem; background: rgba(0,255,255,0.1); border: 1px solid #00ffff; color:#00ffff; padding: 2px 6px; border-radius: 4px; float: right;">${drop.type}</span>`;
        }

        card.innerHTML = `
            <div class="dc-header">
                <span class="dc-item-name">${escapeHtml(drop.item)}</span>
                <img src="/img/section_ids/${drop.section_id}.png" alt="${drop.section_id}" style="width:20px; height:20px;" title="${drop.section_id}">
            </div>
            ${typeBadge}
            <div class="dc-monster" style="margin-top: 5px;">
                <i class="fas fa-ghost"></i> <span>${escapeHtml(drop.monster)}</span>
            </div>
            <div class="dc-details">
                <div>
                    <div>Ep ${drop.episode}</div>
                    <div style="color:#00ffff">${drop.difficulty}</div>
                </div>
                <div class="dc-rate-box">
                    <div class="rate-frac">${drop.rate}</div>
                    <div class="rate-pct">${drop.rate_percent.toFixed(4)}%</div>
                </div>
            </div>
        `;
        
        container.appendChild(card);
    });
}
