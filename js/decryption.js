const API_URL = "/api/agent_state.json";

// Estimated total functions for base binary (used to calculate %)
const TOTAL_FUNCTIONS = 8500; 
let agentFeedLengths = {};
let agentFeedStartTimestamps = {};

async function fetchState() {
    try {
        const response = await fetch(API_URL, { cache: "no-store" });
        if (!response.ok) throw new Error("API Offline");
        const state = await response.json();
        
        updateMetrics(state);
        updateImpacts(state.modifications);
        updateTerminalFeed(state.agents || {});
        
    } catch (err) {
        document.getElementById('m-status').textContent = "OFFLINE OR STANDBY";
        document.getElementById('m-status').style.color = "#94a3b8";
        document.getElementById('m-status').style.textShadow = "none";
    }
}

function updateMetrics(state) {
    const statusEl = document.getElementById('m-status');
    statusEl.textContent = state.status;
    
    if (state.status.includes("Thinking") || state.status.includes("Executing")) {
        statusEl.style.color = "#23D160";
        statusEl.style.textShadow = "0 0 10px rgba(35, 209, 96, 0.4)";
    } else {
        statusEl.style.color = "#f8fafc";
        statusEl.style.textShadow = "none";
    }
    
    document.getElementById('m-model').textContent = state.model;
    document.getElementById('s-batch').textContent = state.batch_num;
    document.getElementById('s-mods').textContent = state.total_mods_all_time || state.modifications.length;
    
    if (document.getElementById('s-tokens')) {
        document.getElementById('s-tokens').textContent = (state.total_tokens || 0).toLocaleString();
    }
    
    if (document.getElementById('s-tps')) {
        document.getElementById('s-tps').textContent = state.tps !== undefined ? state.tps : "0.0";
    }
    
    if (document.getElementById('m-eta')) {
        document.getElementById('m-eta').textContent = state.eta || "Calculating...";
    }
    
    const setStat = (id, val) => {
        const el = document.getElementById(id);
        if (!el) return;
        const card = el.closest('.stat-card');
        
        if (val === undefined || val === "Loading..." || val === "N/A" || val === "") {
            if (card) card.style.display = 'none';
        } else {
            if (card) card.style.display = 'flex';
            if (val === 0 || val === "0") {
                el.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center; gap: 12px; font-size: 2rem; color: #23D160; text-shadow: 0 0 15px rgba(35, 209, 96, 0.6); font-family: 'Exo 2', sans-serif; font-weight: 700;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>
                        SOLVED
                    </div>`;
            } else {
                el.textContent = val;
            }
        }
    };
    
    setStat('s-unknown-dat', state.unknown_dat);
    setStat('s-unknown-ptr', state.unknown_ptr);
    setStat('s-unknown-floats', state.unknown_floats);
    setStat('s-unknown-strings', state.unknown_strings);
    setStat('s-unknown-thunks', state.unknown_thunks);
    setStat('s-unknown-vtables', state.unknown_vtables);
    
    let unknownFnsCount = parseInt(String(state.unknown_fns).replace(/,/g, ''));
    let totalFnsCount = parseInt(String(state.total_fns || "19362").replace(/,/g, ''));
    
    let unknownVarsCount = parseInt(String(state.unknown_vars).replace(/,/g, ''));
    let totalVarsCount = parseInt(String(state.total_vars || "0").replace(/,/g, ''));

    if (!isNaN(unknownFnsCount) && !isNaN(totalFnsCount) && totalFnsCount > 0) {
        if (unknownFnsCount === 0) {
            document.getElementById('s-unknown').innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 12px; font-size: 2rem; color: #23D160; text-shadow: 0 0 15px rgba(35, 209, 96, 0.6); font-family: 'Exo 2', sans-serif; font-weight: 700;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>
                    SOLVED
                </div>`;
        } else {
            document.getElementById('s-unknown').textContent = unknownFnsCount.toLocaleString();
        }
        
        let solvedFns = Math.max(0, totalFnsCount - unknownFnsCount);
        let solvedVars = 0;
        let totalItems = totalFnsCount;
        
        if (!isNaN(unknownVarsCount) && !isNaN(totalVarsCount) && totalVarsCount > 0) {
            solvedVars = Math.max(0, totalVarsCount - unknownVarsCount);
            totalItems += totalVarsCount;
        }

        let totalSolved = solvedFns + solvedVars;
        let percent = Math.min(100, Math.max(0, (totalSolved / totalItems) * 100));
        let percentStr = percent.toFixed(1);
        
        document.getElementById('progress-text').textContent = percentStr + '%';
        document.getElementById('progress-circle').style.setProperty('--percentage', percentStr);
    } else {
        if (state.unknown_fns === 0 || state.unknown_fns === "0") {
            document.getElementById('s-unknown').innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; gap: 12px; font-size: 2rem; color: #23D160; text-shadow: 0 0 15px rgba(35, 209, 96, 0.6); font-family: 'Exo 2', sans-serif; font-weight: 700;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>
                    SOLVED
                </div>`;
        } else if (state.unknown_fns === undefined || state.unknown_fns === "Loading..." || state.unknown_fns === "N/A" || state.unknown_fns === "") {
            const card = document.getElementById('s-unknown').closest('.stat-card');
            if (card) card.style.display = 'none';
        } else {
            document.getElementById('s-unknown').textContent = state.unknown_fns;
        }
    }
    
    // Pipeline tracking
    const currentPhase = state.pipeline_phase || 1;
    
    // MSVC Output
    if (document.getElementById('msvc-feed')) {
        document.getElementById('msvc-feed').textContent = state.last_build_output || "Awaiting compilation attempt...";
    }
    for (let i = 1; i <= 5; i++) {
        const step = document.getElementById(`step-${i}`);
        if (!step) continue;
        const icon = step.querySelector('.step-icon');
        const text = step.querySelector('span');
        
        if (i < currentPhase) {
            // Completed
            icon.style.borderColor = 'var(--accent-success)';
            icon.style.color = 'var(--accent-success)';
            icon.style.boxShadow = 'none';
            text.style.color = 'var(--accent-success)';
        } else if (i === currentPhase) {
            // Active
            icon.style.borderColor = 'var(--accent-primary)';
            icon.style.color = 'var(--accent-primary)';
            icon.style.boxShadow = '0 0 10px rgba(0, 237, 255, 0.4)';
            text.style.color = 'var(--accent-primary)';
        } else {
            // Pending
            icon.style.borderColor = 'rgba(255,255,255,0.2)';
            icon.style.color = 'rgba(255,255,255,0.5)';
            icon.style.boxShadow = 'none';
            text.style.color = '#94a3b8';
        }
    }

    if (document.getElementById('s-recompiler-status')) {
        const rStatus = document.getElementById('s-recompiler-status');
        rStatus.textContent = state.recompiler_status || 'Standby';
        if (state.recompiler_status && state.recompiler_status.includes('Fail')) {
            rStatus.style.color = '#FF3366';
        } else if (state.recompiler_status && state.recompiler_status.includes('Pass')) {
            rStatus.style.color = 'var(--accent-success)';
        } else {
            rStatus.style.color = '#00EDFF';
        }
    }
    
    if (document.getElementById('s-recompiler-attempts')) {
        document.getElementById('s-recompiler-attempts').textContent = state.recompiler_attempts || '0';
    }
    
    if (document.getElementById('s-compile-errors')) {
        document.getElementById('s-compile-errors').textContent = state.compile_errors || '0';
    }
    
    if (document.getElementById('s-extracted-files')) {
        document.getElementById('s-extracted-files').textContent = state.extracted_files || '0';
    }
}

function updateImpacts(mods) {
    const container = document.getElementById('impact-feed');
    
    if (mods.length === 0) {
        container.innerHTML = `<div style="color: #94a3b8; text-align: center; padding: 20px; font-style: italic;">Awaiting database activity...</div>`;
        return;
    }
    
    container.innerHTML = "";
    
    const recentMods = mods.slice(0, 5);
    
    recentMods.forEach(mod => {
        const div = document.createElement('div');
        div.className = "impact-row";
        
        let cleanedDetails = mod.details.replace(/^{|}$/g, '').replace(/',/g, "' ");
        if (cleanedDetails.length > 150) {
            cleanedDetails = cleanedDetails.substring(0, 150) + "...";
        }
        
        div.innerHTML = `
            <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.95rem;">
                <strong style="color: #00EDFF;">${mod.action}</strong>
                <span style="color: #94a3b8; margin-left: 10px;">${cleanedDetails}</span>
            </div>
            <div style="font-size: 0.85rem; color: #64748b; font-family: 'JetBrains Mono', monospace; min-width: 80px; text-align: right;">
                ${mod.timestamp}
            </div>
        `;
        
        container.appendChild(div);
    });
}

function updateTerminalFeed(agents) {
    const grid = document.getElementById('swarm-grid');
    if (!grid) return;
    
    // Convert to array and sort by agent ID
    const agentIds = Object.keys(agents).sort((a, b) => parseInt(a) - parseInt(b));
    
    for (const agentId of agentIds) {
        const agentState = agents[agentId];
        const feed = agentState.terminal_feed || [];
        
        // Create terminal container if it doesn't exist
        let termWrapper = document.getElementById(`agent-terminal-${agentId}`);
        if (!termWrapper) {
            termWrapper = document.createElement('div');
            termWrapper.className = 'agent-terminal';
            termWrapper.id = `agent-terminal-${agentId}`;
            
            termWrapper.innerHTML = `
                <div class="agent-terminal-header">
                    <span>AGENT ${agentId}</span>
                    <span style="color: var(--accent-success); animation: pulse 2s infinite;">ACTIVE</span>
                </div>
                <div class="terminal-feed" id="terminal-feed-${agentId}"></div>
            `;
            grid.appendChild(termWrapper);
        }
        
        const container = document.getElementById(`terminal-feed-${agentId}`);
        let lastLen = agentFeedLengths[agentId] || 0;
        
        if (feed.length > 0 && lastLen > 0 && feed[0].timestamp !== agentFeedStartTimestamps[agentId]) {
            lastLen = 0;
            container.innerHTML = '';
        }
        if (feed.length > 0) agentFeedStartTimestamps[agentId] = feed[0].timestamp;
        
        if (feed.length === lastLen) continue;
        
        if (feed.length < lastLen) {
            lastLen = 0;
            container.innerHTML = '';
        }
        
        for (let i = lastLen; i < feed.length; i++) {
            const entry = feed[i];
            const div = document.createElement('div');
            div.className = `log-entry ${entry.type}`;
            
            // Clean up the "[Agent X] " prefix from the PHP ingestion if it exists
            let content = entry.content.replace(/^\[Agent \d+\] /, '').replace(/\n/g, "<br>");
            div.innerHTML = `<span class="log-time">[${entry.timestamp}]</span> ${content}`;
            
            container.appendChild(div);
        }
        
        agentFeedLengths[agentId] = feed.length;
        container.scrollTop = container.scrollHeight;
    }
}

// 2 second polling for live effect!
setInterval(fetchState, 2000);
fetchState();
