// MAG Feeder JavaScript
// Handles fetching MAG data, rendering UI, feeding, and hunger timer

const FEED_COOLDOWN_MS = 210000; // 3 minutes 30 seconds (PSO MAG feed interval)
const TIMER_STORAGE_KEY = 'mag_feed_timer_end';
const FEED_COUNT_KEY = 'mag_feed_count';
const MAX_FEEDS_BEFORE_FULL = 3;

// Item icons mapping
const ITEM_ICONS = {
    'Monomate': '💊',
    'Dimate': '💊',
    'Trimate': '💊',
    'Monofluid': '🧪',
    'Difluid': '🧪',
    'Trifluid': '🧪',
    'Sol Atomizer': '☀️',
    'Moon Atomizer': '🌙',
    'Star Atomizer': '⭐',
    'Antidote': '🧬',
    'Antiparalysis': '⚡',
};

let currentMagData = null;
let currentFeedItems = [];
let selectedMagId = null;
let feedCooldownInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadMagData();
    checkExistingTimer();
});

async function loadMagData() {
    const statusDiv = document.getElementById('mag-status');
    const loginPrompt = document.getElementById('mag-login-prompt');
    const charInfo = document.getElementById('mag-char-info');
    const magDisplay = document.getElementById('mag-display');
    const feedSection = document.getElementById('feed-items-section');
    const timerSection = document.getElementById('hunger-timer-section');

    // Reset
    statusDiv.style.display = 'none';
    loginPrompt.style.display = 'none';

    try {
        const res = await fetch('/api/mag_inventory.php', { credentials: 'same-origin' });
        const data = await res.json();

        if (!res.ok) {
            if (res.status === 401) {
                sessionStorage.removeItem('psobb_user');
                loginPrompt.style.display = 'block';
                charInfo.style.display = 'none';
                magDisplay.style.display = 'none';
                feedSection.style.display = 'none';
                timerSection.style.display = 'none';
                return;
            }
            showStatus(data.error || 'Failed to load MAG data.', 'error');
            return;
        }

        // Show character info
        charInfo.style.display = 'block';
        document.getElementById('mag-char-name').textContent = data.character.name;
        document.getElementById('mag-char-class').textContent = data.character.class;
        document.getElementById('mag-char-level').textContent = data.character.level;

        currentFeedItems = data.feed_items || [];

        if (data.mags && data.mags.length > 0) {
            magDisplay.style.display = 'block';
            timerSection.style.display = 'block';
            feedSection.style.display = 'block';
            renderMags(data.mags);
            renderFeedItems(currentFeedItems);
        } else {
            magDisplay.style.display = 'block';
            timerSection.style.display = 'none';
            feedSection.style.display = 'none';
            document.getElementById('mag-card-container').innerHTML = `
                <div class="server-status-widget" style="text-align: center; padding: 2rem;">
                    <p style="color: var(--text-secondary);">No MAG found in your inventory.</p>
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.5rem;">Equip a MAG in-game first!</p>
                </div>
            `;
        }

    } catch (err) {
        showStatus('Failed to connect to the server.', 'error');
    }
}

function renderMags(mags) {
    const container = document.getElementById('mag-card-container');
    container.innerHTML = '';

    mags.forEach((mag, idx) => {
        // If only one MAG, auto-select it
        if (mags.length === 1) selectedMagId = mag.item_id;

        // Stats are raw values * 100 (e.g., DEF 500 = 5.00 levels)
        const defLevel = (mag.def / 100).toFixed(1);
        const powLevel = (mag.pow / 100).toFixed(1);
        const dexLevel = (mag.dex / 100).toFixed(1);
        const mindLevel = (mag.mind / 100).toFixed(1);

        // Progress bars represent the fractional progress to the next level (0-99%)
        const defPct = mag.def % 100;
        const powPct = mag.pow % 100;
        const dexPct = mag.dex % 100;
        const mindPct = mag.mind % 100;

        const isSelected = selectedMagId === mag.item_id;

        const card = document.createElement('div');
        card.className = 'mag-card' + (isSelected ? ' selected' : '');
        if (mags.length > 1) {
            card.style.cursor = 'pointer';
            card.onclick = () => {
                selectedMagId = mag.item_id;
                renderMags(mags);
            };
        }

        // Photon Blast names
        const pbNames = parsePBFlags(mag.pb_flags);

        card.innerHTML = `
            <div class="mag-card-header">
                <h3>${mag.description}${mag.equipped ? ' <span style="font-size: 0.75rem; color: #ffaa00;">⚔️ Equipped</span>' : ''}</h3>
                <div class="mag-level">Lv.${mag.level}</div>
            </div>

            <div class="mag-stat-row">
                <span class="mag-stat-label" style="color: #66aaff;">DEF</span>
                <div class="mag-stat-bar-bg">
                    <div class="mag-stat-bar-fill def" style="width: ${defPct}%;"></div>
                    <span class="mag-stat-value">${defLevel}</span>
                </div>
            </div>
            <div class="mag-stat-row">
                <span class="mag-stat-label" style="color: #ff6644;">POW</span>
                <div class="mag-stat-bar-bg">
                    <div class="mag-stat-bar-fill pow" style="width: ${powPct}%;"></div>
                    <span class="mag-stat-value">${powLevel}</span>
                </div>
            </div>
            <div class="mag-stat-row">
                <span class="mag-stat-label" style="color: #ffcc00;">DEX</span>
                <div class="mag-stat-bar-bg">
                    <div class="mag-stat-bar-fill dex" style="width: ${dexPct}%;"></div>
                    <span class="mag-stat-value">${dexLevel}</span>
                </div>
            </div>
            <div class="mag-stat-row">
                <span class="mag-stat-label" style="color: #cc88ff;">MIND</span>
                <div class="mag-stat-bar-bg">
                    <div class="mag-stat-bar-fill mind" style="width: ${mindPct}%;"></div>
                    <span class="mag-stat-value">${mindLevel}</span>
                </div>
            </div>

            <div class="mag-meta-row">
                <div class="mag-meta-item">Synchro: <span>${mag.synchro}%</span></div>
                <div class="mag-meta-item">IQ: <span>${mag.iq}</span></div>
                ${pbNames.length > 0 ? `<div class="mag-meta-item">PB: <span>${pbNames.join(', ')}</span></div>` : ''}
            </div>
        `;

        container.appendChild(card);
    });
}

function parsePBFlags(flags) {
    // PSO Photon Blast mapping from flags
    // The PB flags byte encodes which PBs the MAG has
    // Bits 0-2: center PB, bits 3-5: right PB, bit 6: left PB
    const pbList = [];
    const pbNames = ['Farlla', 'Estlla', 'Golla', 'Pilla', 'Leilla', 'Mylla & Youlla'];

    // Simple approach: check common PB patterns
    if (flags & 0x01) pbList.push('Farlla');
    if (flags & 0x02) pbList.push('Estlla');
    if (flags & 0x04) pbList.push('Golla');
    if (flags & 0x08) pbList.push('Pilla');
    if (flags & 0x10) pbList.push('Leilla');
    if (flags & 0x20) pbList.push('Mylla & Youlla');

    return pbList;
}

function renderFeedItems(items) {
    const grid = document.getElementById('feed-items-grid');
    const noItems = document.getElementById('no-feed-items');

    if (!items || items.length === 0) {
        grid.style.display = 'none';
        noItems.style.display = 'block';
        return;
    }

    grid.style.display = 'grid';
    noItems.style.display = 'none';
    grid.innerHTML = '';

    const isCooldown = isOnCooldown();

    items.forEach(item => {
        const card = document.createElement('div');
        card.className = 'feed-item-card' + (isCooldown ? ' disabled' : '');
        const icon = ITEM_ICONS[item.name] || '📦';

        card.innerHTML = `
            <div class="feed-icon">${icon}</div>
            <div class="feed-name">${item.name}</div>
            <div class="feed-count">x${item.count}</div>
        `;

        if (!isCooldown) {
            card.onclick = () => feedMag(item);
        }

        grid.appendChild(card);
    });
}

async function feedMag(feedItem) {
    if (!selectedMagId) {
        showStatus('Select a MAG first!', 'error');
        return;
    }

    if (isOnCooldown()) {
        showStatus('MAG is not hungry yet! Wait for the timer.', 'error');
        return;
    }

    // Disable all feed buttons immediately
    document.querySelectorAll('.feed-item-card').forEach(c => c.classList.add('disabled'));

    try {
        const res = await fetch('/api/mag_feed.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mag_item_id: selectedMagId,
                feed_item_id: feedItem.item_id,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            showStatus(data.error || 'Feed failed.', 'error');
            document.querySelectorAll('.feed-item-card').forEach(c => c.classList.remove('disabled'));
            return;
        }

        // Track feed count — MAG can eat 3 times before getting full
        let feedCount = parseInt(localStorage.getItem(FEED_COUNT_KEY) || '0', 10) + 1;
        localStorage.setItem(FEED_COUNT_KEY, feedCount.toString());

        if (feedCount >= MAX_FEEDS_BEFORE_FULL) {
            showStatus(`✅ Fed ${feedItem.name} to your MAG! MAG is now full. Change rooms in-game to see updated stats.`, 'success');
            localStorage.setItem(FEED_COUNT_KEY, '0');
            startCooldownTimer();
        } else {
            const remaining = MAX_FEEDS_BEFORE_FULL - feedCount;
            showStatus(`✅ Fed ${feedItem.name}! (${remaining} feed${remaining !== 1 ? 's' : ''} left) Change rooms in-game to see updated stats.`, 'success');
            updateFeedCounter(feedCount);
            // Re-enable buttons since MAG is still hungry
            document.querySelectorAll('.feed-item-card').forEach(c => c.classList.remove('disabled'));
        }

        // Refresh MAG data after a brief delay to let the server process
        setTimeout(loadMagData, 1500);

    } catch (err) {
        showStatus('Failed to send feed command.', 'error');
        document.querySelectorAll('.feed-item-card').forEach(c => c.classList.remove('disabled'));
    }
}

function showStatus(message, type) {
    const statusDiv = document.getElementById('mag-status');
    statusDiv.style.display = 'block';
    statusDiv.className = 'alert-box';

    if (type === 'success') {
        statusDiv.style.borderColor = '#00ff88';
        statusDiv.style.color = '#00ff88';
    } else {
        statusDiv.style.borderColor = '#ff4444';
        statusDiv.style.color = '#ff4444';
    }

    statusDiv.textContent = message;

    // Auto-hide after 5 seconds
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 8000);
}

// ===== Hunger Timer =====

function startCooldownTimer() {
    const endTime = Date.now() + FEED_COOLDOWN_MS;
    localStorage.setItem(TIMER_STORAGE_KEY, endTime.toString());
    runTimer(endTime);
}

function checkExistingTimer() {
    const stored = localStorage.getItem(TIMER_STORAGE_KEY);
    if (stored) {
        const endTime = parseInt(stored, 10);
        if (endTime > Date.now()) {
            runTimer(endTime);
        } else {
            localStorage.removeItem(TIMER_STORAGE_KEY);
            localStorage.setItem(FEED_COUNT_KEY, '0');
        }
    }
    // Update feed counter display
    const feedCount = parseInt(localStorage.getItem(FEED_COUNT_KEY) || '0', 10);
    updateFeedCounter(feedCount);
}

function updateFeedCounter(count) {
    const label = document.getElementById('hunger-label');
    const fill = document.getElementById('hunger-timer-fill');
    const text = document.getElementById('hunger-timer-text');
    if (!label) return;

    if (isOnCooldown()) return; // Timer is running, don't override

    const remaining = MAX_FEEDS_BEFORE_FULL - count;
    if (remaining > 0) {
        label.textContent = '🍖 MAG is Hungry!';
        const pct = (remaining / MAX_FEEDS_BEFORE_FULL) * 100;
        fill.style.width = pct + '%';
        fill.style.background = 'linear-gradient(90deg, #00ff88, #00ccff)';
        text.textContent = `${remaining} of ${MAX_FEEDS_BEFORE_FULL} feeds remaining`;
    }
}

function runTimer(endTime) {
    const label = document.getElementById('hunger-label');
    const fill = document.getElementById('hunger-timer-fill');
    const text = document.getElementById('hunger-timer-text');

    if (feedCooldownInterval) clearInterval(feedCooldownInterval);

    const updateTimer = () => {
        const remaining = endTime - Date.now();

        if (remaining <= 0) {
            clearInterval(feedCooldownInterval);
            feedCooldownInterval = null;
            localStorage.removeItem(TIMER_STORAGE_KEY);
            localStorage.setItem(FEED_COUNT_KEY, '0');

            label.textContent = '🍖 MAG is Hungry!';
            fill.style.width = '100%';
            fill.style.background = 'linear-gradient(90deg, #00ff88, #00ccff)';
            text.textContent = `${MAX_FEEDS_BEFORE_FULL} of ${MAX_FEEDS_BEFORE_FULL} feeds remaining`;

            // Re-enable feed cards
            document.querySelectorAll('.feed-item-card').forEach(c => c.classList.remove('disabled'));
            return;
        }

        label.textContent = '⏳ MAG is Full...';
        const pct = (remaining / FEED_COOLDOWN_MS) * 100;
        fill.style.width = pct + '%';
        fill.style.background = 'linear-gradient(90deg, #ff8844, #ffaa00)';

        const totalSecs = Math.ceil(remaining / 1000);
        const mins = Math.floor(totalSecs / 60);
        const secs = totalSecs % 60;
        text.textContent = `${mins}:${secs.toString().padStart(2, '0')} until hungry`;

        // Disable feed cards
        document.querySelectorAll('.feed-item-card').forEach(c => c.classList.add('disabled'));
    };

    updateTimer();
    feedCooldownInterval = setInterval(updateTimer, 1000);
}

function isOnCooldown() {
    const stored = localStorage.getItem(TIMER_STORAGE_KEY);
    if (!stored) return false;
    return parseInt(stored, 10) > Date.now();
}
