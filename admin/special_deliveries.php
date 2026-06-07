<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("Location: ../login.php");
    exit;
}
$page_title = "Special Deliveries — Admin";
$current_page = 'special_deliveries';
include '../includes/header.php';
?>

<style>
.sd-page { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
}
.page-header h1 { font-size: 1.6rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: .6rem; }
.page-header h1 i { color: #fb923c; }
.back-link { color: #6ee7f7; text-decoration: none; font-size: .9rem; }
.back-link:hover { color: #fff; }

/* ── Item Search ── */
.item-search-wrap { position: relative; }
.item-search-results {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 200;
    background: #0f172a; border: 1px solid #374151; border-radius: 8px;
    max-height: 260px; overflow-y: auto; display: none;
    box-shadow: 0 12px 40px rgba(0,0,0,.6);
}
.item-result {
    display: flex; align-items: center; justify-content: space-between;
    padding: .5rem .75rem; cursor: pointer; gap: .5rem;
    transition: background .1s;
}
.item-result:hover { background: rgba(251,146,60,.1); }
.item-result .r-name { color: #f9fafb; font-size: .875rem; font-weight: 500; }
.item-result .r-code { font-family: 'Share Tech Mono', monospace; font-size: .78rem; color: #fb923c; }
.item-result .r-cat  { font-size: .7rem; color: #6b7280; background: #1f2937; border-radius: 4px; padding: .1rem .4rem; }

/* ── User Search dropdown (reuses item-search styles) ── */
.user-result {
    display: flex; align-items: center; justify-content: space-between;
    padding: .45rem .75rem; cursor: pointer; gap: .5rem; transition: background .1s;
}
.user-result:hover { background: rgba(251,146,60,.1); }
.user-result .u-name { color: #f9fafb; font-size: .875rem; font-weight: 500; font-family: 'Share Tech Mono', monospace; }
.user-result .u-id   { font-size: .72rem; color: #6b7280; }
.user-result .u-disc { font-size: .7rem; border-radius: 4px; padding: .1rem .4rem; }
.u-disc.linked   { background: rgba(52,211,153,.1); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.u-disc.unlinked { background: rgba(107,114,128,.1); color: #6b7280; border: 1px solid #374151; }

.attr-builder {
    background: rgba(0,0,0,.25); border: 1px solid #1f2937;
    border-radius: 8px; padding: .75rem; margin-top: .5rem;
    display: none;
}
.attr-builder.visible { display: block; }
.attr-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .4rem; flex-wrap: wrap; }
.attr-row label { width: 65px; font-size: .73rem; color: #9ca3af; text-transform: uppercase; }
.attr-row input[type=range] { flex: 1; min-width: 80px; accent-color: #fb923c; }
.attr-row .attr-val { width: 32px; text-align: right; font-family: 'Share Tech Mono', monospace; font-size: .8rem; color: #fdba74; }
.attr-grind-row { display: flex; align-items: center; gap: .5rem; margin-top: .5rem; padding-top: .5rem; border-top: 1px solid #1f2937; }
.attr-grind-row label { width: 65px; font-size: .73rem; color: #9ca3af; text-transform: uppercase; }
.attr-grind-row input[type=range] { flex:1; min-width: 80px; accent-color: #60a5fa; }
.attr-grind-row .attr-val { width: 32px; text-align: right; font-family: 'Share Tech Mono', monospace; font-size: .8rem; color: #93c5fd; }
.attr-preview {
    margin-top: .5rem; padding: .4rem .7rem;
    background: rgba(0,0,0,.4); border-radius: 6px;
    font-family: 'Share Tech Mono', monospace; font-size: .82rem; color: #34d399;
    display: flex; align-items: center; justify-content: space-between; gap: .5rem;
}
.btn-apply-attrs {
    background: rgba(52,211,153,.15); border: 1px solid rgba(52,211,153,.4);
    color: #34d399; border-radius: 5px; padding: .25rem .65rem;
    font-size: .75rem; font-weight: 600; cursor: pointer; white-space: nowrap;
    transition: all .15s;
}
.btn-apply-attrs:hover { background: rgba(52,211,153,.3); }

/* ── Create card / form ── */
.create-card {
    background: rgba(251,146,60,.06);
    border: 1px solid rgba(251,146,60,.3);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.create-card h2 { font-size: 1rem; font-weight: 600; color: #fdba74; margin: 0 0 1rem; display: flex; align-items: center; gap: .4rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.form-group { display: flex; flex-direction: column; gap: .3rem; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: .78rem; color: #9ca3af; font-weight: 500; letter-spacing: .04em; text-transform: uppercase; }
.form-group input, .form-group textarea {
    background: rgba(0,0,0,.4); border: 1px solid #374151;
    border-radius: 7px; color: #f9fafb; padding: .5rem .75rem;
    font-size: .875rem; font-family: inherit; outline: none; transition: border .2s; resize: vertical;
}
.form-group input:focus, .form-group textarea:focus { border-color: #fb923c; }
.form-hint { font-size: .72rem; color: #6b7280; margin-top: .2rem; }
.btn-create {
    background: rgba(251,146,60,.2); border: 1px solid #fb923c;
    color: #fdba74; border-radius: 7px; padding: .5rem 1.3rem;
    font-size: .875rem; font-weight: 600; cursor: pointer;
    display: inline-flex; align-items: center; gap: .4rem;
    transition: all .2s; margin-top: .75rem;
}
.btn-create:hover { background: rgba(251,146,60,.35); color: #fff; }

/* ── Table ── */
.table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #1f2937; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #111827; }
th { padding: .7rem 1rem; text-align: left; font-size: .74rem; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; border-bottom: 1px solid #1f2937; white-space: nowrap; }
tbody tr { border-bottom: 1px solid #111827; transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,.02); }
td { padding: .75rem 1rem; font-size: .875rem; color: #d1d5db; vertical-align: middle; }
.item-name { font-weight: 600; color: #fff; }
.item-string { font-family: 'Share Tech Mono', monospace; font-size: .78rem; color: #9ca3af; }
.note-text { font-size: .8rem; color: #6b7280; font-style: italic; }
.mono { font-family: 'Share Tech Mono', monospace; font-size: .82rem; }

.badge { display: inline-flex; align-items: center; gap: .25rem; padding: .2rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }
.badge-pending  { background: rgba(251,146,60,.12); color: #fdba74; border: 1px solid rgba(251,146,60,.3); }
.badge-redeemed { background: rgba(16,185,129,.1);  color: #34d399; border: 1px solid rgba(16,185,129,.3); }
.badge-revoked  { background: rgba(107,114,128,.1); color: #9ca3af; border: 1px solid #374151; }

.btn-revoke {
    background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.35);
    color: #f87171; border-radius: 6px; padding: .3rem .7rem;
    font-size: .78rem; font-weight: 600; cursor: pointer; transition: all .2s;
}
.btn-revoke:hover { background: rgba(239,68,68,.25); }
.btn-revoke:disabled { opacity: .3; cursor: not-allowed; }

.empty-state { text-align: center; padding: 3rem; color: #4b5563; }
.empty-state i { font-size: 2rem; margin-bottom: .5rem; display: block; color: #374151; }

.toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    background: #1f2937; border: 1px solid #374151; border-radius: 10px;
    padding: .75rem 1.1rem; color: #f9fafb; font-size: .875rem;
    display: flex; align-items: center; gap: .5rem;
    animation: fadeSlide .25s; box-shadow: 0 8px 30px rgba(0,0,0,.5);
}
.toast.success { border-color: rgba(16,185,129,.5); color: #34d399; }
.toast.error   { border-color: rgba(239,68,68,.5);  color: #f87171; }
@keyframes fadeSlide { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
</style>

<main class="sd-page">
    <div class="page-header">
        <h1><i class="fas fa-gift"></i> Special Deliveries</h1>
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Create Form -->
    <div class="create-card">
        <h2><i class="fas fa-plus-circle"></i> Create Delivery</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>Player Username</label>
                <div class="item-search-wrap">
                    <input type="text" id="sd-username" placeholder="Search username…" autocomplete="off">
                    <div class="item-search-results" id="sd-user-results"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Item Display Name</label>
                <input type="text" id="sd-item-name" placeholder="e.g. Dual Bird +21 0/0/0/70" maxlength="80">
            </div>

            <!-- Item Search -->
            <div class="form-group full">
                <label>Search Item</label>
                <div class="item-search-wrap">
                    <input type="text" id="sd-item-search" placeholder="Type item name to search… (e.g. Dual Bird, Cannon Rouge)" autocomplete="off">
                    <div class="item-search-results" id="sd-search-results"></div>
                </div>
                <span class="form-hint">Search selects the item code. Then use the attribute builder below to set grind and stats.</span>
            </div>

            <!-- Item String -->
            <div class="form-group full">
                <label>Item String <span style="color:#4b5563;font-weight:400;text-transform:none;font-size:.72rem">— auto-filled by search + builder, or type manually</span></label>
                <input type="text" id="sd-item-string" placeholder='e.g. "Photon Drop x3" or "004B0115 0/0/0/70/0"'>
                <span class="form-hint">Weapons: <code style="color:#fb923c">XXXXXX[GG] N/AB/M/D/H</code> &nbsp;|&nbsp; Armor: <code style="color:#fb923c">XXXXXX +Ndef +Nevp</code> &nbsp;|&nbsp; Tools: item name or <code style="color:#fb923c">Disk:Megid Lv.15</code> &nbsp;|&nbsp; Meseta: <code style="color:#fb923c">50000 Meseta</code></span>
            </div>

            <!-- Attribute Builder (shown after item selected) -->
            <div class="form-group full" id="attr-builder-wrap" style="display:none;">
                <label id="attr-builder-label">Builder</label>

                <!-- WEAPON: grind + 5 elements -->
                <div class="attr-builder visible" id="builder-weapon" style="display:none;">
                    <div class="attr-row"><label>Grind</label>    <input type="range" id="ab-grind" min="0" max="30" value="0" oninput="updatePreview()"><span class="attr-val" id="ab-grind-v">0</span></div>
                    <div class="attr-row"><label>Native</label>   <input type="range" id="ab-n"  min="0" max="100" value="0" oninput="updatePreview()"><span class="attr-val" id="ab-n-v">0</span></div>
                    <div class="attr-row"><label>ABeast</label>   <input type="range" id="ab-ab" min="0" max="100" value="0" oninput="updatePreview()"><span class="attr-val" id="ab-ab-v">0</span></div>
                    <div class="attr-row"><label>Machine</label>  <input type="range" id="ab-m"  min="0" max="100" value="0" oninput="updatePreview()"><span class="attr-val" id="ab-m-v">0</span></div>
                    <div class="attr-row"><label>Dark</label>     <input type="range" id="ab-d"  min="0" max="100" value="0" oninput="updatePreview()"><span class="attr-val" id="ab-d-v">0</span></div>
                    <div class="attr-row"><label>Hit</label>      <input type="range" id="ab-h"  min="0" max="100" value="0" oninput="updatePreview()"><span class="attr-val" id="ab-h-v">0</span></div>
                    <div class="attr-preview"><span id="ab-preview-weapon">—</span><button class="btn-apply-attrs" onclick="applyAttrs()"><i class="fas fa-check"></i> Apply</button></div>
                </div>

                <!-- ARMOR / SHIELD: slots + def bonus + evp bonus -->
                <div class="attr-builder visible" id="builder-armor" style="display:none;">
                    <div class="attr-row">
                        <label>Slots</label>
                        <input type="range" id="ab-slots" min="0" max="4" value="0" oninput="updatePreview()">
                        <span class="attr-val" id="ab-slots-v">0</span>
                    </div>
                    <div class="attr-row">
                        <label>DEF +</label>
                        <input type="range" id="ab-def" min="0" max="200" value="0" oninput="updatePreview()">
                        <span class="attr-val" id="ab-def-v">0</span>
                    </div>
                    <div class="attr-row">
                        <label>EVP +</label>
                        <input type="range" id="ab-evp" min="0" max="200" value="0" oninput="updatePreview()">
                        <span class="attr-val" id="ab-evp-v">0</span>
                    </div>
                    <div class="attr-preview"><span id="ab-preview-armor">—</span><button class="btn-apply-attrs" onclick="applyAttrs()"><i class="fas fa-check"></i> Apply</button></div>
                </div>

                <!-- UNIT: +/- modifier -->
                <div class="attr-builder visible" id="builder-unit" style="display:none;">
                    <div class="attr-row">
                        <label>Modifier</label>
                        <select id="ab-unit-sign" onchange="updatePreview()" style="background:#0f172a;border:1px solid #374151;border-radius:5px;color:#f9fafb;padding:.25rem .5rem;font-size:.85rem;">
                            <option value="+">+ (enhance)</option>
                            <option value="-">− (reduce)</option>
                        </select>
                        <input type="range" id="ab-unit-val" min="0" max="3" value="0" oninput="updatePreview()" style="flex:1;">
                        <span class="attr-val" id="ab-unit-val-v">0</span>
                    </div>
                    <div class="attr-preview"><span id="ab-preview-unit">—</span><button class="btn-apply-attrs" onclick="applyAttrs()"><i class="fas fa-check"></i> Apply</button></div>
                </div>
            </div>

            <div class="form-group">
                <label>Note to Player <span style="color:#4b5563">(optional)</span></label>
                <input type="text" id="sd-note" placeholder="e.g. Thanks for bug testing!" maxlength="200">
            </div>
        </div>
        <button class="btn-create" id="btn-create-delivery"><i class="fas fa-paper-plane"></i> Queue Delivery</button>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Item</th>
                    <th>Note</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Redeemed</th>
                    <th>By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="sd-tbody">
                <tr><td colspan="8" class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
            </tbody>
        </table>
    </div>
</main>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function api(action, body = null) {
    return fetch(`/api/admin_special_delivery.php?action=${action}`, {
        method: body ? 'POST' : 'GET',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    }).then(r => r.json());
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function fmt(dt) {
    if (!dt) return '<span style="color:#4b5563">—</span>';
    return new Date(dt).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
}
function badge(status) {
    const map = {
        pending:  `<span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>`,
        redeemed: `<span class="badge badge-redeemed"><i class="fas fa-check"></i> Redeemed</span>`,
        revoked:  `<span class="badge badge-revoked"><i class="fas fa-ban"></i> Revoked</span>`,
    };
    return map[status] ?? `<span class="badge">${esc(status)}</span>`;
}

function render(rows) {
    const tbody = document.getElementById('sd-tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="empty-state"><i class="fas fa-gift"></i>No deliveries yet.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td><span class="mono">${esc(r.recipient_name)}</span><br><span style="color:#4b5563;font-size:.75rem">ID: ${r.recipient_id}</span></td>
        <td>
            <div class="item-name">${esc(r.item_name)}</div>
            <div class="item-string">${esc(r.item_string)}</div>
        </td>
        <td><span class="note-text">${r.admin_note ? esc(r.admin_note) : '—'}</span></td>
        <td>${badge(r.status)}</td>
        <td>${fmt(r.created_at)}</td>
        <td>${fmt(r.redeemed_at)}</td>
        <td><span class="mono" style="font-size:.78rem">${esc(r.created_by)}</span></td>
        <td>
            <button class="btn-revoke" ${r.status !== 'pending' ? 'disabled' : ''}
                onclick="revoke(${r.id})"><i class="fas fa-times"></i> Revoke</button>
        </td>
    </tr>`).join('');
}

async function load() {
    const data = await api('list');
    if (data.deliveries) render(data.deliveries);
    else toast(data.error ?? 'Failed to load', 'error');
}

async function create() {
    const username    = document.getElementById('sd-username').value.trim();
    const item_name   = document.getElementById('sd-item-name').value.trim();
    const item_string = document.getElementById('sd-item-string').value.trim();
    const admin_note  = document.getElementById('sd-note').value.trim();

    if (!username || !item_name || !item_string) {
        toast('Username, item name, and item string are required', 'error'); return;
    }

    const btn = document.getElementById('btn-create-delivery');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Queuing…';

    const data = await api('create', { username, item_name, item_string, admin_note });
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Queue Delivery';

    if (data.success) {
        toast(`Delivery queued for ${data.recipient_name}`, 'success');
        ['sd-username','sd-item-name','sd-item-string','sd-note','sd-item-search'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('attr-builder-wrap').style.display = 'none';
        resetAllBuilders();
        load();
    } else {
        toast(data.error ?? 'Failed to create delivery', 'error');
    }
}

async function revoke(id) {
    if (!confirm('Revoke this delivery? The player will no longer be able to claim it.')) return;
    const data = await api('revoke', { id });
    if (data.success) { toast('Delivery revoked', 'success'); load(); }
    else toast(data.error ?? 'Failed', 'error');
}

function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
    el.innerHTML = `<i class="fas fa-${icon}"></i> ${esc(msg)}`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

// ── User Search ──────────────────────────────────────────────────
let userTimer = null;
const userInput   = document.getElementById('sd-username');
const userResults = document.getElementById('sd-user-results');

userInput.addEventListener('input', () => {
    clearTimeout(userTimer);
    const q = userInput.value.trim();
    if (q.length < 1) { userResults.style.display = 'none'; return; }
    userTimer = setTimeout(() => doUserSearch(q), 180);
});

userInput.addEventListener('blur', () => {
    setTimeout(() => { userResults.style.display = 'none'; }, 150);
});

async function doUserSearch(q) {
    const res  = await fetch(`/api/admin_user_search.php?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) {
        userResults.innerHTML = '<div class="user-result" style="color:#6b7280;cursor:default;">No users found</div>';
    } else {
        userResults.innerHTML = data.map(u => `
            <div class="user-result" onmousedown="selectUser('${u.username.replace(/'/g,"\\'")}')">
                <span class="u-name">${esc(u.username)}</span>
                <span style="display:flex;align-items:center;gap:.5rem;">
                    <span class="u-id">ID: ${u.account_id}</span>
                    <span class="u-disc ${u.linked ? 'linked' : 'unlinked'}">${u.linked ? '<i class="fab fa-discord"></i> linked' : 'no discord'}</span>
                </span>
            </div>`).join('');
    }
    userResults.style.display = 'block';
}

function selectUser(username) {
    userInput.value = username;
    userResults.style.display = 'none';
}

// ── Item Search ──────────────────────────────────────────────────
let searchTimer = null;
let selectedCode = '';
let selectedCat  = '';

const searchInput   = document.getElementById('sd-item-search');
const searchResults = document.getElementById('sd-search-results');

searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 2) { searchResults.style.display = 'none'; return; }
    searchTimer = setTimeout(() => doSearch(q), 200);
});

searchInput.addEventListener('blur', () => {
    setTimeout(() => { searchResults.style.display = 'none'; }, 150);
});

async function doSearch(q) {
    const res  = await fetch(`/api/admin_item_search.php?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) {
        searchResults.innerHTML = '<div class="item-result" style="color:#6b7280;cursor:default;">No results</div>';
    } else {
        searchResults.innerHTML = data.map(item => `
            <div class="item-result" onmousedown="selectItem('${item.code}','${item.name.replace(/'/g,"\\'").replace(/"/g,'&quot;')}','${item.cat}')">
                <span class="r-name">${esc(item.name)}</span>
                <span style="display:flex;align-items:center;gap:.5rem;">
                    <span class="r-cat">${esc(item.cat)}</span>
                    <span class="r-code">${esc(item.code)}</span>
                </span>
            </div>`).join('');
    }
    searchResults.style.display = 'block';
}

function selectItem(code, name, cat) {
    selectedCode = code;
    selectedCat  = cat;
    searchInput.value = name;
    searchResults.style.display = 'none';

    // Auto-fill display name if empty
    const nameField = document.getElementById('sd-item-name');
    if (!nameField.value) nameField.value = name;

    const wrap  = document.getElementById('attr-builder-wrap');
    const label = document.getElementById('attr-builder-label');

    // Hide all sub-builders first
    ['builder-weapon','builder-armor','builder-unit'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });

    resetAllBuilders();
    selectedCode = code; selectedCat = cat;

    if (cat === 'Weapon') {
        label.textContent = 'Grind & Attribute Builder';
        document.getElementById('builder-weapon').style.display = 'block';
        wrap.style.display = 'block';
        updatePreview();
    } else if (cat === 'Armor' || cat === 'Shield') {
        label.textContent = cat === 'Shield' ? 'Shield Builder (DEF / EVP bonus)' : 'Armor Builder (Slots / DEF / EVP bonus)';
        // Shields don't have slots in PSO
        document.getElementById('ab-slots').closest('.attr-row').style.display = cat === 'Shield' ? 'none' : 'flex';
        document.getElementById('builder-armor').style.display = 'block';
        wrap.style.display = 'block';
        updatePreview();
    } else if (cat === 'Unit') {
        label.textContent = 'Unit Modifier Builder';
        document.getElementById('builder-unit').style.display = 'block';
        wrap.style.display = 'block';
        updatePreview();
    } else {
        // Mag, Tool, Disk — just set the code directly
        wrap.style.display = 'none';
        document.getElementById('sd-item-string').value = code;
    }
}

// ── Preview / Apply ──────────────────────────────────────────────
function getVal(id) { return parseInt(document.getElementById(id).value) || 0; }

function updatePreview() {
    if (selectedCat === 'Weapon') {
        const grind = getVal('ab-grind');
        const n = getVal('ab-n'), ab = getVal('ab-ab'), m = getVal('ab-m'), d = getVal('ab-d'), h = getVal('ab-h');
        document.getElementById('ab-grind-v').textContent = grind;
        document.getElementById('ab-n-v').textContent  = n;
        document.getElementById('ab-ab-v').textContent = ab;
        document.getElementById('ab-m-v').textContent  = m;
        document.getElementById('ab-d-v').textContent  = d;
        document.getElementById('ab-h-v').textContent  = h;

        const grindHex   = grind.toString(16).padStart(2, '0').toUpperCase();
        const codeGrind  = selectedCode + grindHex;
        const preview    = `${codeGrind} ${n}/${ab}/${m}/${d}/${h}`;
        document.getElementById('ab-preview-weapon').textContent = selectedCode ? preview : '—';

    } else if (selectedCat === 'Armor' || selectedCat === 'Shield') {
        const slots = getVal('ab-slots');
        const def   = getVal('ab-def');
        const evp   = getVal('ab-evp');
        document.getElementById('ab-slots-v').textContent = slots;
        document.getElementById('ab-def-v').textContent   = def;
        document.getElementById('ab-evp-v').textContent   = evp;

        let parts = [selectedCode];
        if (selectedCat !== 'Shield' && slots > 0) parts.push(`+${slots}`);
        if (def > 0) parts.push(`+${def}def`);
        if (evp > 0) parts.push(`+${evp}evp`);
        document.getElementById('ab-preview-armor').textContent = selectedCode ? parts.join(' ') : '—';

    } else if (selectedCat === 'Unit') {
        const val  = getVal('ab-unit-val');
        const sign = document.getElementById('ab-unit-sign').value;
        document.getElementById('ab-unit-val-v').textContent = val;
        const preview = selectedCode ? (val > 0 ? `${selectedCode} ${sign}${val}` : selectedCode) : '—';
        document.getElementById('ab-preview-unit').textContent = preview;
    }
}

function applyAttrs() {
    let preview = '';
    let nameAppend = '';

    if (selectedCat === 'Weapon') {
        preview    = document.getElementById('ab-preview-weapon').textContent;
        const grind = getVal('ab-grind');
        if (grind > 0) nameAppend = ` +${grind}`;
    } else if (selectedCat === 'Armor' || selectedCat === 'Shield') {
        preview = document.getElementById('ab-preview-armor').textContent;
    } else if (selectedCat === 'Unit') {
        preview = document.getElementById('ab-preview-unit').textContent;
    }

    if (preview && preview !== '—') {
        document.getElementById('sd-item-string').value = preview;

        if (nameAppend) {
            const nf = document.getElementById('sd-item-name');
            if (nf.value && !nf.value.includes('+')) nf.value = nf.value.trimEnd() + nameAppend;
        }
        toast('Applied to item string ✓', 'success');
    }
}

function resetAllBuilders() {
    ['ab-grind','ab-n','ab-ab','ab-m','ab-d','ab-h','ab-slots','ab-def','ab-evp','ab-unit-val'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = 0;
    });
    const sign = document.getElementById('ab-unit-sign');
    if (sign) sign.value = '+';
    selectedCode = '';
    selectedCat  = '';
}

document.getElementById('btn-create-delivery').addEventListener('click', create);
load();
</script>

<?php include '../includes/footer.php'; ?>
