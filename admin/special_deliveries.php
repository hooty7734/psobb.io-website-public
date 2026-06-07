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
.sd-page { max-width: 1050px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
}
.page-header h1 { font-size: 1.6rem; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: .6rem; }
.page-header h1 i { color: #fb923c; }
.back-link { color: #6ee7f7; text-decoration: none; font-size: .9rem; }
.back-link:hover { color: #fff; }

/* Create card */
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

/* Table */
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
                <input type="text" id="sd-username" placeholder="exactusername" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Item Display Name</label>
                <input type="text" id="sd-item-name" placeholder="e.g. Sealed J-Sword +80" maxlength="80">
            </div>
            <div class="form-group">
                <label>Item String</label>
                <input type="text" id="sd-item-string" placeholder='e.g. "Photon Drop x3" or "001006 0/30/0/20"'>
                <span class="form-hint">Same format as mission reward strings. Supports multipliers (x2) and attributes (0/30/0/20).</span>
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
        ['sd-username','sd-item-name','sd-item-string','sd-note'].forEach(id => document.getElementById(id).value = '');
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

document.getElementById('btn-create-delivery').addEventListener('click', create);
load();
</script>

<?php include '../includes/footer.php'; ?>
