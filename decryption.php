<?php
$page_title = 'Agent Decryption - PSOBB Private Server';
$current_page = 'decryption';
include 'includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;600;700&family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    /* Scoped Dashboard Styling to avoid global bleed */
    .decryption-dashboard {
        --panel-bg: linear-gradient(145deg, rgba(10, 15, 29, 0.9), rgba(15, 22, 45, 0.7));
        --panel-border: rgba(0, 237, 255, 0.15);
        --accent-primary: #00EDFF;
        --accent-secondary: #5E69FF;
        --accent-success: #23D160;
        
        font-family: 'Rajdhani', sans-serif;
        color: #f8fafc;
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 20px;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .decryption-dashboard .glass-panel {
        background: var(--panel-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--panel-border);
        border-radius: 8px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    .decryption-dashboard .highlight-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 40px;
        background: linear-gradient(135deg, rgba(94, 105, 255, 0.2), rgba(0, 237, 255, 0.08));
        border: 1px solid rgba(0, 237, 255, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    .decryption-dashboard .highlight-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 50%;
        height: 100%;
        background: linear-gradient(to right, transparent, rgba(255,255,255,0.08), transparent);
        transform: skewX(-20deg);
        animation: shine 8s infinite;
    }
    
    @keyframes shine {
        0% { left: -100%; }
        20% { left: 200%; }
        100% { left: 200%; }
    }

    .decryption-dashboard h2 {
        font-family: 'Exo 2', sans-serif;
        font-size: 1.8rem;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 10px;
    }

    .decryption-dashboard .circular-progress {
        position: relative;
        width: clamp(180px, 20vw, 250px);
        height: clamp(180px, 20vw, 250px);
        border-radius: 50%;
        background: conic-gradient(var(--accent-primary) calc(var(--percentage) * 1%), rgba(255,255,255,0.05) 0);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 30px rgba(0, 237, 255, 0.2), inset 0 0 20px rgba(0, 0, 0, 0.5);
    }

    .decryption-dashboard .circular-progress::before {
        content: "";
        position: absolute;
        width: 90%;
        height: 90%;
        background-color: #060B1E;
        border-radius: 50%;
        box-shadow: inset 0 0 20px rgba(0, 237, 255, 0.2);
    }

    .decryption-dashboard .progress-value {
        position: relative;
        font-family: 'Exo 2', sans-serif;
        font-size: clamp(2.5rem, 4vw, 3.5rem);
        font-weight: 700;
        color: #fff;
        text-shadow: 0 0 15px var(--accent-primary);
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .decryption-dashboard .progress-label {
        font-size: clamp(0.9rem, 1.5vw, 1.2rem);
        color: var(--accent-secondary);
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-top: 5px;
    }

    .decryption-dashboard .stats-block {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }

    .decryption-dashboard .stat-card {
        padding: 24px;
        text-align: center;
        display: flex;
        flex-direction: column;
        gap: 15px;
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        border: 1px solid rgba(0, 237, 255, 0.05);
    }
    
    .decryption-dashboard .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 237, 255, 0.15), inset 0 0 15px rgba(0, 237, 255, 0.05);
        border-color: rgba(0, 237, 255, 0.3);
    }

    .decryption-dashboard .stat-card .label {
        color: #94a3b8;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .decryption-dashboard .stat-card .value {
        font-size: 3rem;
        font-family: 'JetBrains Mono', monospace;
        color: var(--accent-success);
        text-shadow: 0 0 10px rgba(35, 209, 96, 0.3);
    }

    .decryption-dashboard .stat-card.span-2 {
        grid-column: span 2;
    }

    .decryption-dashboard .recent-impacts {
        display: flex;
        flex-direction: column;
        gap: 15px;
        padding: 24px;
    }

    .decryption-dashboard .impact-row {
        display: flex;
        justify-content: space-between;
        padding: 16px;
        background: rgba(0, 0, 0, 0.3);
        border-left: 4px solid var(--accent-secondary);
        border-radius: 4px;
        transition: transform 0.2s ease, border-left-color 0.2s ease, background 0.2s ease;
    }

    .decryption-dashboard .impact-row > div:first-child {
        flex: 1;
        min-width: 0;
        overflow-wrap: break-word;
        word-wrap: break-word;
        word-break: break-word;
        margin-right: 15px;
    }

    .decryption-dashboard .impact-row:hover {
        transform: translateX(5px);
        border-left-color: var(--accent-primary);
        background: rgba(0, 237, 255, 0.05);
    }

    @keyframes smoothPulse {
        0% { box-shadow: 0 0 0 0 rgba(35, 209, 96, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(35, 209, 96, 0); }
        100% { box-shadow: 0 0 0 0 rgba(35, 209, 96, 0); }
    }

    .pulse-ring {
        display: inline-block;
        width: 12px;
        height: 12px;
        background-color: var(--accent-success);
        border-radius: 50%;
        margin-right: 15px;
        animation: smoothPulse 2s infinite;
    }

    /* Terminal Feed Styles */
    .decryption-dashboard .console-wrapper {
        background: #040816;
        border: 1px solid var(--accent-secondary);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .decryption-dashboard .console-wrapper.fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 9999;
        border-radius: 0;
        background: rgba(4, 8, 22, 0.95);
        backdrop-filter: blur(10px);
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
    }

    .decryption-dashboard .console-wrapper.fullscreen .swarm-grid {
        max-height: none;
        flex: 1;
    }

    .decryption-dashboard .swarm-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        padding: 24px;
        max-height: 500px;
        overflow-y: auto;
    }

    .decryption-dashboard .agent-terminal {
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(0, 237, 255, 0.1);
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .decryption-dashboard .agent-terminal-header {
        background: rgba(94, 105, 255, 0.15);
        padding: 10px 15px;
        font-family: 'Exo 2', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--accent-primary);
        border-bottom: 1px solid rgba(0, 237, 255, 0.1);
        display: flex;
        justify-content: space-between;
    }

    .decryption-dashboard .terminal-feed {
        padding: 15px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
        font-family: 'JetBrains Mono', monospace;
        scroll-behavior: smooth;
        height: 300px;
    }

    .decryption-dashboard .console-wrapper.fullscreen .terminal-feed {
        height: 500px;
    }

    /* Terminal Scrollbar */
    .decryption-dashboard .swarm-grid::-webkit-scrollbar,
    .decryption-dashboard .terminal-feed::-webkit-scrollbar { width: 6px; }
    .decryption-dashboard .swarm-grid::-webkit-scrollbar-track,
    .decryption-dashboard .terminal-feed::-webkit-scrollbar-track { background: #060B1E; }
    .decryption-dashboard .swarm-grid::-webkit-scrollbar-thumb,
    .decryption-dashboard .terminal-feed::-webkit-scrollbar-thumb { background: var(--accent-secondary); border-radius: 3px; }
    .decryption-dashboard .swarm-grid::-webkit-scrollbar-thumb:hover,
    .decryption-dashboard .terminal-feed::-webkit-scrollbar-thumb:hover { background: var(--accent-primary); }

    .decryption-dashboard .log-entry {
        font-size: 0.9rem;
        line-height: 1.6;
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .decryption-dashboard .log-entry.system { color: var(--accent-secondary); }
    .decryption-dashboard .log-entry.tool { 
        color: var(--accent-primary); 
        background: rgba(0, 237, 255, 0.05); 
        padding: 12px; 
        border-radius: 4px; 
        border-left: 4px solid var(--accent-primary);
    }
    .decryption-dashboard .log-entry.thought { 
        color: #00EDFF; 
        opacity: 0.85;
        font-style: italic;
        border-left: 2px solid #00EDFF;
        padding-left: 12px;
        background: linear-gradient(90deg, rgba(0, 237, 255, 0.05) 0%, transparent 100%);
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .decryption-dashboard .log-time {
        color: #475569;
        font-size: 0.85rem;
        margin-right: 12px;
    }

    /* Tablet Responsiveness */
    @media (max-width: 1024px) {
        .decryption-dashboard .stats-block {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .decryption-dashboard .highlight-card {
            padding: 30px;
        }
        .decryption-dashboard .stat-card.empty-card {
            display: none;
        }
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
        .decryption-dashboard {
            padding: 15px 10px;
        }

        .decryption-dashboard .highlight-card {
            flex-direction: column;
            gap: 25px;
            text-align: center;
            padding: 25px 15px;
        }

        .decryption-dashboard .stats-block {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px;
        }

        .decryption-dashboard .stat-card {
            padding: 15px;
            gap: 8px;
        }

        .decryption-dashboard .stat-card.span-2 {
            grid-column: span 2 !important;
        }

        .decryption-dashboard .stat-card .label {
            font-size: 0.85rem;
        }

        .decryption-dashboard .stat-card .value {
            font-size: 1.8rem;
        }

        .agent-status-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 12px !important;
            margin-bottom: 25px !important;
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .agent-status-header > div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: left !important;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .agent-status-header > div:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .agent-status-header > div > span:first-child {
            margin-bottom: 0;
        }

        .decryption-dashboard .impact-row {
            flex-direction: column;
            gap: 10px;
        }

        .decryption-dashboard .impact-row > div:last-child {
            text-align: left !important;
            opacity: 0.7;
        }
        
        .decryption-dashboard h2 {
            font-size: 1.4rem;
            text-align: center;
        }
        
        .main-header h1 {
            font-size: 1.6rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .main-header p {
            text-align: center;
            font-size: 0.95rem !important;
            padding: 0 10px;
        }
        
        /* Vertical Pipeline Tracker for Mobile */
        .pipeline-container {
            flex-direction: column !important;
            gap: 25px !important;
            align-items: flex-start !important;
            padding-left: 20px;
            padding-top: 10px;
            padding-bottom: 10px;
        }
        
        .pipeline-line {
            width: 2px !important;
            height: calc(100% - 40px) !important;
            top: 20px !important;
            left: 39px !important; /* Align exactly with center of 40px icons */
            bottom: auto !important;
            right: auto !important;
            background: linear-gradient(to bottom, rgba(0, 237, 255, 0.4), rgba(0, 237, 255, 0.05)) !important;
        }
        
        .pipeline-step {
            width: 100% !important;
            flex-direction: row !important;
            justify-content: flex-start !important;
            align-items: center !important;
            gap: 20px !important;
            position: relative;
            z-index: 2;
        }
        
        .pipeline-step span {
            font-size: 1.1rem !important;
            text-align: left !important;
            font-weight: 600;
        }
        
        /* Terminal responsiveness */
        .decryption-dashboard .swarm-grid {
            grid-template-columns: 1fr;
            padding: 15px;
        }
    }

    @media (max-width: 480px) {
        .decryption-dashboard .stats-block {
            grid-template-columns: 1fr !important; /* Stack single column on tiny screens */
        }
        .decryption-dashboard .stat-card.span-2 {
            grid-column: span 1 !important;
        }
    }
</style>

<div class="pso-spinner-svg">
    <canvas id="star-canvas-stats"></canvas>
    <svg class="hex2"><!-- hex SVG --></svg>
</div>

<main class="container" style="margin-top: 100px;">
    <div class="main-header" style="margin-bottom: 2rem;">
        <h1><div class="pulse-ring"></div> Agent Decryption Matrix</h1>
        <p style="color: #94a3b8; font-family: 'Exo 2', sans-serif; font-size: 1.1rem; max-width: 800px; margin-top: 10px; line-height: 1.6;">
            <strong>[PIONEER 2 LAB TRANSMISSION]</strong><br>
            Attention Hunters. Our autonomous analytics network is currently deployed to decrypt the foundational architecture of the Pioneer project's archives, actively reverse-engineering the <strong>Phantasy Star Online Blue Burst Client / Tethealla 125.13 client</strong>. What you are witnessing below is a live feed from the central AI as it maps unknown structures, isolates legacy code formats, and stabilizes the combat data grid. This real-time structural analysis is crucial to fortifying the Pioneer 2 mainframe for future operations on Ragol.
        </p>
    </div>

    <div class="decryption-dashboard">
        
        <!-- Live Status Subheader -->
        <div class="agent-status-header" style="display: flex; justify-content: flex-end; gap: 40px; margin-bottom: -10px; flex-wrap: wrap;">
            <div style="text-align: right;">
                <span style="color: #5E69FF; font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; display: block;">AGENT STATUS</span>
                <span id="m-status" style="font-family: 'JetBrains Mono', monospace; font-size: 1.2rem; font-weight: bold; color: #23D160; text-shadow: 0 0 10px rgba(35, 209, 96, 0.4);">Initializing...</span>
            </div>
            <div style="text-align: right;">
                <span style="color: #5E69FF; font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; display: block;">AI ENGINE</span>
                <span id="m-model" style="font-family: 'JetBrains Mono', monospace; font-size: 1.2rem; font-weight: bold; color: #00EDFF; text-shadow: 0 0 10px rgba(0, 237, 255, 0.4);">Detecting...</span>
            </div>
            <div style="text-align: right;">
                <span style="color: #5E69FF; font-size: 0.8rem; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; display: block;">EST. TIME REMAINING</span>
                <span id="m-eta" style="font-family: 'JetBrains Mono', monospace; font-size: 1.2rem; font-weight: bold; color: var(--accent-success); text-shadow: 0 0 10px rgba(35, 209, 96, 0.4);">Calculating...</span>
            </div>
        </div>

        <!-- Pipeline Phase Tracker -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 10px;">
            <h2 style="font-size: 1.2rem; margin-bottom: 20px;">Orchestration Pipeline Status</h2>
            <div class="pipeline-container" style="display: flex; justify-content: space-between; position: relative;">
                <div class="pipeline-line" style="position: absolute; top: 35%; left: 10%; right: 10%; height: 2px; background: rgba(0, 237, 255, 0.15); z-index: 1;"></div>
                
                <div class="pipeline-step" id="step-1" style="z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <div class="step-icon" style="width: 40px; height: 40px; border-radius: 50%; background: var(--panel-bg); border: 2px solid var(--accent-success); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent-success); box-shadow: 0 0 10px rgba(35, 209, 96, 0.4);">1</div>
                    <span style="font-size: 0.9rem; text-transform: uppercase; color: var(--accent-success);">Renaming</span>
                </div>
                
                <div class="pipeline-step" id="step-2" style="z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <div class="step-icon" style="width: 40px; height: 40px; border-radius: 50%; background: var(--panel-bg); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; color: rgba(255,255,255,0.5);">2</div>
                    <span style="font-size: 0.9rem; text-transform: uppercase; color: #94a3b8;">C++ Extraction</span>
                </div>
                
                <div class="pipeline-step" id="step-3" style="z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <div class="step-icon" style="width: 40px; height: 40px; border-radius: 50%; background: var(--panel-bg); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; color: rgba(255,255,255,0.5);">3</div>
                    <span style="font-size: 0.9rem; text-transform: uppercase; color: #94a3b8;">MSVC Recompile</span>
                </div>
                
                <div class="pipeline-step" id="step-4" style="z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <div class="step-icon" style="width: 40px; height: 40px; border-radius: 50%; background: var(--panel-bg); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; color: rgba(255,255,255,0.5);">4</div>
                    <span style="font-size: 0.9rem; text-transform: uppercase; color: #94a3b8;">Modular Breakdown</span>
                </div>
                
                <div class="pipeline-step" id="step-5" style="z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <div class="step-icon" style="width: 40px; height: 40px; border-radius: 50%; background: var(--panel-bg); border: 2px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-weight: bold; color: rgba(255,255,255,0.5);">5</div>
                    <span style="font-size: 0.9rem; text-transform: uppercase; color: #94a3b8;">Git Sync</span>
                </div>
            </div>
        </div>

        <div class="glass-panel highlight-card">
            <div>
                <h2>Binary Decompilation Progress</h2>
                <p style="color: #94a3b8; font-size: 1.1rem; max-width: 500px; line-height: 1.5;">Tracking the remaining unknown functions against the total binary size in real-time as the autonomous agent executes.</p>
            </div>
            
            <div class="circular-progress" id="progress-circle" style="--percentage: 0;">
                <div class="progress-value">
                    <span id="progress-text">0%</span>
                    <span class="progress-label">Solved</span>
                </div>
            </div>
        </div>

        <div class="stats-block">
            <div class="glass-panel stat-card">
                <span class="label">Functions</span>
                <span class="value" id="s-unknown" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card">
                <span class="label">Thunks</span>
                <span class="value" id="s-unknown-thunks" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card">
                <span class="label">Data (DAT)</span>
                <span class="value" id="s-unknown-dat" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card">
                <span class="label">Pointers</span>
                <span class="value" id="s-unknown-ptr" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card">
                <span class="label">vTables</span>
                <span class="value" id="s-unknown-vtables" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card">
                <span class="label">Floats</span>
                <span class="value" id="s-unknown-floats" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card">
                <span class="label">Strings</span>
                <span class="value" id="s-unknown-strings" style="color: #f8fafc;">...</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">Tokens Burned</span>
                <span class="value" id="s-tokens" style="color: #5E69FF;">0</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">Tokens/sec</span>
                <span class="value" id="s-tps" style="color: #23D160;">0.0</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">Total DB Mods</span>
                <span class="value" id="s-mods">0</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">AI Loops</span>
                <span class="value" id="s-batch" style="color: #5E69FF;">0</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 2;">
                <span class="label">Recompiler Status</span>
                <span class="value" id="s-recompiler-status" style="color: #00EDFF;">Standby</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">Compile Attempts</span>
                <span class="value" id="s-recompiler-attempts">0</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">Compile Errors</span>
                <span class="value" id="s-compile-errors" style="color: #FF3366;">0</span>
            </div>
            <div class="glass-panel stat-card" style="grid-column: span 1;">
                <span class="label">Extracted Files</span>
                <span class="value" id="s-extracted-files">0</span>
            </div>
        </div>

        <div class="glass-panel">
            <h2 style="padding: 24px; border-bottom: 1px solid rgba(0, 237, 255, 0.15); margin: 0;">Recent Significant Impacts</h2>
            <div class="recent-impacts" id="impact-feed">
                <div style="color: #94a3b8; text-align: center; padding: 20px; font-style: italic;">Awaiting database activity...</div>
            </div>
        </div>

        <div class="glass-panel" style="margin-bottom: 24px;">
            <h2 style="padding: 24px; border-bottom: 1px solid rgba(255, 51, 102, 0.3); margin: 0; background: rgba(255, 51, 102, 0.05);">MSVC 2003 Build Output</h2>
            <div style="padding: 24px; overflow-y: auto; max-height: 400px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: #cbd5e1; background: #060B1E; margin: 15px; border-radius: 4px; border: 1px solid rgba(255, 51, 102, 0.2);">
                <pre id="msvc-feed" style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">Awaiting compilation attempt...</pre>
            </div>
        </div>

        <div class="glass-panel console-wrapper" id="console-wrapper">
            <h2 style="padding: 24px; border-bottom: 1px solid rgba(94, 105, 255, 0.3); margin: 0; background: rgba(94, 105, 255, 0.1); display: flex; justify-content: space-between; align-items: center;">
                <span>Live Cognitive Stream</span>
                <button id="fs-toggle-btn" onclick="toggleConsoleFS()" style="background: transparent; border: 1px solid var(--accent-primary); color: var(--accent-primary); padding: 5px 15px; border-radius: 4px; cursor: pointer; font-family: 'Rajdhani', sans-serif; font-size: 1rem; text-transform: uppercase; transition: all 0.2s ease;" onmouseover="this.style.background='var(--accent-primary)'; this.style.color='#000';" onmouseout="this.style.background='transparent'; this.style.color='var(--accent-primary)';">Fullscreen</button>
            </h2>
            <div class="swarm-grid" id="swarm-grid">
                <!-- Agent terminals injected here -->
            </div>
        </div>

    </div>
</main>

<script>
function toggleConsoleFS() {
    const wrapper = document.getElementById('console-wrapper');
    const btn = document.getElementById('fs-toggle-btn');
    wrapper.classList.toggle('fullscreen');
    if (wrapper.classList.contains('fullscreen')) {
        btn.textContent = 'Exit Fullscreen (ESC)';
        btn.style.borderColor = '#FF3366';
        btn.style.color = '#FF3366';
        btn.onmouseover = function() { this.style.background='#FF3366'; this.style.color='#000'; };
        btn.onmouseout = function() { this.style.background='transparent'; this.style.color='#FF3366'; };
    } else {
        btn.textContent = 'Fullscreen';
        btn.style.borderColor = 'var(--accent-primary)';
        btn.style.color = 'var(--accent-primary)';
        btn.onmouseover = function() { this.style.background='var(--accent-primary)'; this.style.color='#000'; };
        btn.onmouseout = function() { this.style.background='transparent'; this.style.color='var(--accent-primary)'; };
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        const wrapper = document.getElementById('console-wrapper');
        if (wrapper.classList.contains('fullscreen')) {
            toggleConsoleFS();
        }
    }
});
</script>

<script src="/js/decryption.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer.php'; ?>
