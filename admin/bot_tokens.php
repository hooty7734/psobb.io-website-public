<?php
require_once __DIR__ . '/../api/config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header("Location: ../login.php");
    exit;
}
$page_title = "Bot Token Manager — Admin";
include '../includes/header.php';
?>

<style>
.token-page { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap;
}
.page-header h1 {
    font-size: 1.6rem; font-weight: 700; color: #fff;
    display: flex; align-items: center; gap: .6rem; margin: 0;
}
.page-header h1 i { color: #a78bfa; }
.back-link { color: #6ee7f7; text-decoration: none; font-size: .9rem; }
.back-link:hover { color: #fff; }

/* Create form card */
.create-card {
    background: rgba(167,139,250,.07);
    border: 1px solid rgba(167,139,250,.35);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.create-card h2 { font-size: 1rem; font-weight: 600; color: #c4b5fd; margin: 0 0 1rem; display: flex; align-items: center; gap: .4rem; }
.form-row { display: flex; gap: .75rem; align-items: flex-end; flex-wrap: wrap; }
.form-group { display: flex; flex-direction: column; gap: .3rem; flex: 1; min-width: 180px; }
.form-group label { font-size: .78rem; color: #9ca3af; font-weight: 500; letter-spacing: .04em; text-transform: uppercase; }
.form-group input {
    background: rgba(0,0,0,.4); border: 1px solid #374151;
    border-radius: 7px; color: #f9fafb; padding: .5rem .75rem;
    font-size: .9rem; font-family: inherit; outline: none; transition: border .2s;
}
.form-group input:focus { border-color: #a78bfa; }
.btn-create {
    background: rgba(167,139,250,.2); border: 1px solid #a78bfa;
    color: #c4b5fd; border-radius: 7px; padding: .5rem 1.2rem;
    font-size: .9rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: .4rem; white-space: nowrap;
    transition: all .2s; height: 38px; align-self: flex-end;
}
.btn-create:hover { background: rgba(167,139,250,.35); color: #fff; }

/* Token reveal banner */
.token-reveal {
    display: none;
    background: rgba(16, 185, 129, .08);
    border: 1px solid rgba(16, 185, 129, .5);
    border-radius: 10px;
    padding: 1.1rem 1.3rem;
    margin-bottom: 2rem;
    animation: fadeIn .3s;
}
.token-reveal .reveal-header { display: flex; align-items: center; gap: .5rem; color: #34d399; font-weight: 700; margin-bottom: .5rem; }
.token-reveal .token-value {
    background: #0d1117; border: 1px solid #30363d; border-radius: 6px;
    padding: .5rem .8rem; font-family: 'Share Tech Mono', monospace;
    font-size: .9rem; color: #58a6ff; word-break: break-all;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
}
.copy-btn {
    background: rgba(88,166,255,.15); border: 1px solid rgba(88,166,255,.4);
    color: #58a6ff; border-radius: 5px; padding: .25rem .6rem;
    font-size: .78rem; cursor: pointer; flex-shrink: 0; transition: all .2s;
}
.copy-btn:hover { background: rgba(88,166,255,.3); }
.reveal-warning { font-size: .8rem; color: #fbbf24; margin-top: .5rem; display: flex; align-items: center; gap: .35rem; }

/* Table */
.token-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #1f2937; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #111827; }
th { padding: .7rem 1rem; text-align: left; font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; font-weight: 600; border-bottom: 1px solid #1f2937; white-space: nowrap; }
tbody tr { border-bottom: 1px solid #111827; transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,.02); }
td { padding: .75rem 1rem; font-size: .875rem; color: #d1d5db; vertical-align: middle; }
.token-name { font-weight: 600; color: #f9fafb; }
.token-meta { font-size: .78rem; color: #6b7280; margin-top: .15rem; }

.badge {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 20px; font-size: .75rem; font-weight: 600; white-space: nowrap;
}
.badge-active  { background: rgba(16,185,129,.12); color: #34d399; border: 1px solid rgba(16,185,129,.3); }
.badge-revoked { background: rgba(239,68,68,.1);  color: #f87171; border: 1px solid rgba(239,68,68,.25); }
.badge-expired { background: rgba(251,191,36,.1); color: #fbbf24; border: 1px solid rgba(251,191,36,.25); }

.mono { font-family: 'Share Tech Mono', monospace; font-size: .82rem; color: #9ca3af; }

.action-btns { display: flex; gap: .5rem; align-items: center; }
.btn-revoke, .btn-delete {
    border-radius: 6px; padding: .3rem .7rem; font-size: .78rem; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; gap: .3rem; transition: all .2s; border: 1px solid;
}
.btn-revoke { background: rgba(251,191,36,.1); border-color: rgba(251,191,36,.4); color: #fbbf24; }
.btn-revoke:hover { background: rgba(251,191,36,.25); }
.btn-revoke:disabled { opacity: .35; cursor: not-allowed; }
.btn-delete { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.35); color: #f87171; }
.btn-delete:hover { background: rgba(239,68,68,.25); }

.empty-state { text-align: center; padding: 3rem; color: #4b5563; }
.empty-state i { font-size: 2rem; margin-bottom: .75rem; color: #374151; display: block; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

.toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
    background: #1f2937; border: 1px solid #374151; border-radius: 10px;
    padding: .75rem 1.1rem; color: #f9fafb; font-size: .875rem;
    display: flex; align-items: center; gap: .5rem;
    animation: fadeIn .25s; box-shadow: 0 8px 30px rgba(0,0,0,.5);
}
.toast.error { border-color: rgba(239,68,68,.5); color: #f87171; }
.toast.success { border-color: rgba(16,185,129,.5); color: #34d399; }
</style>

<main class="token-page">
    <div class="page-header">
        <h1><i class="fas fa-key"></i> Bot Token Manager</h1>
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Create Token -->
    <div class="create-card">
        <h2><i class="fas fa-plus-circle"></i> Issue New Token</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Token Name / Label</label>
                <input type="text" id="token-name" placeholder="e.g. Discord Bot – Production" maxlength="80">
            </div>
            <div class="form-group" style="max-width: 160px;">
                <label>Expires in (days)</label>
                <input type="number" id="token-expires" placeholder="Never" min="1" max="3650">
            </div>
            <button class="btn-create" id="btn-create"><i class="fas fa-bolt"></i> Generate Token</button>
        </div>
    </div>

    <!-- Token Reveal Banner (shown after creation) -->
    <div class="token-reveal" id="token-reveal">
        <div class="reveal-header"><i class="fas fa-shield-check"></i> Token Created Successfully</div>
        <div class="token-value">
            <span id="token-raw" class="token-text"></span>
            <button class="copy-btn" id="copy-btn" onclick="copyToken()"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <div class="reveal-warning"><i class="fas fa-exclamation-triangle"></i> This token will never be shown again. Copy it now.</div>
    </div>

    <!-- Token Table -->
    <div class="token-table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>Last Used</th>
                    <th>Expires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="token-tbody">
                <tr><td colspan="7" class="empty-state"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr>
            </tbody>
        </table>
    </div>
</main>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function api(action, body = null) {
    const opts = {
        method: body ? 'POST' : 'GET',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
    };
    if (body) opts.body = JSON.stringify(body);
    return fetch(`/api/admin_bot_tokens.php?action=${action}`, opts).then(r => r.json());
}

function badge(tok) {
    if (tok.revoked)    return `<span class="badge badge-revoked"><i class="fas fa-ban"></i> Revoked</span>`;
    if (tok.is_expired) return `<span class="badge badge-expired"><i class="fas fa-clock"></i> Expired</span>`;
    return `<span class="badge badge-active"><i class="fas fa-circle"></i> Active</span>`;
}

function fmt(dt) {
    if (!dt) return '<span style="color:#4b5563">—</span>';
    return new Date(dt).toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
}

function render(tokens) {
    const tbody = document.getElementById('token-tbody');
    if (!tokens.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="empty-state"><i class="fas fa-key"></i>No tokens yet. Create one above.</td></tr>`;
        return;
    }
    tbody.innerHTML = tokens.map(tok => {
        const disabled = tok.revoked || tok.is_expired;
        return `<tr data-id="${tok.id}">
            <td>
                <div class="token-name">${esc(tok.name)}</div>
                <div class="token-meta mono">ID: ${tok.id}</div>
            </td>
            <td>${badge(tok)}</td>
            <td><span class="mono">${esc(tok.created_by)}</span></td>
            <td>${fmt(tok.created_at)}</td>
            <td>${fmt(tok.last_used_at)}</td>
            <td>${tok.expires_at ? fmt(tok.expires_at) : '<span style="color:#4b5563">Never</span>'}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-revoke" ${disabled ? 'disabled title="Already inactive"' : ''}
                        onclick="revokeToken(${tok.id})">
                        <i class="fas fa-ban"></i> Revoke
                    </button>
                    <button class="btn-delete" onclick="deleteToken(${tok.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function loadTokens() {
    const data = await api('list');
    if (data.tokens) render(data.tokens);
    else toast(data.error ?? 'Failed to load tokens', 'error');
}

async function createToken() {
    const name = document.getElementById('token-name').value.trim();
    const days = document.getElementById('token-expires').value.trim();
    if (!name) { toast('Token name is required', 'error'); return; }

    const btn = document.getElementById('btn-create');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…';

    const data = await api('create', { name, expires_days: days ? parseInt(days) : null });
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-bolt"></i> Generate Token';

    if (data.token) {
        // Show reveal banner
        const reveal = document.getElementById('token-reveal');
        document.getElementById('token-raw').textContent = data.token;
        reveal.style.display = 'block';
        reveal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        document.getElementById('token-name').value = '';
        document.getElementById('token-expires').value = '';
        loadTokens();
        toast('Token created — copy it now!', 'success');
    } else {
        toast(data.error ?? 'Failed to create token', 'error');
    }
}

async function revokeToken(id) {
    if (!confirm('Revoke this token? It will stop working immediately.')) return;
    const data = await api('revoke', { id });
    if (data.success) { toast('Token revoked', 'success'); loadTokens(); }
    else toast(data.error ?? 'Failed to revoke', 'error');
}

async function deleteToken(id) {
    if (!confirm('Permanently delete this token record? This cannot be undone.')) return;
    const data = await api('delete', { id });
    if (data.success) { toast('Token deleted', 'success'); loadTokens(); }
    else toast(data.error ?? 'Failed to delete', 'error');
}

function copyToken() {
    const text = document.getElementById('token-raw').textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i> Copy', 2000);
    });
}

function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
    el.innerHTML = `<i class="fas fa-${icon}"></i> ${esc(msg)}`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4000);
}

document.getElementById('btn-create').addEventListener('click', createToken);
document.getElementById('token-name').addEventListener('keydown', e => { if (e.key === 'Enter') createToken(); });

loadTokens();
</script>

<?php include '../includes/footer.php'; ?>
