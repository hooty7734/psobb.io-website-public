<?php
$page_title = 'Development Resources - PSOBB Private Server';
$current_page = 'development';
include 'includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;600;700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    .dev-dashboard {
        --panel-bg: rgba(10, 15, 29, 0.85);
        --panel-border: rgba(0, 237, 255, 0.15);
        --accent-primary: #00EDFF;
        --accent-secondary: #5E69FF;
        --accent-success: #23D160;
        
        font-family: 'Rajdhani', sans-serif;
        color: #f8fafc;
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .glass-panel {
        background: var(--panel-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--panel-border);
        border-radius: 4px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), inset 0 0 10px rgba(0, 237, 255, 0.02);
        padding: 40px;
    }

    .highlight-card {
        background: linear-gradient(135deg, rgba(94, 105, 255, 0.15), rgba(0, 237, 255, 0.05));
    }

    .dev-dashboard h2 {
        font-family: 'Exo 2', sans-serif;
        font-size: 1.8rem;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .dev-dashboard p {
        color: #94a3b8;
        font-size: 1.1rem;
        line-height: 1.6;
        margin-bottom: 20px;
    }

    .resource-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-top: 30px;
    }

    .resource-card {
        background: rgba(0, 0, 0, 0.3);
        border-left: 4px solid var(--accent-secondary);
        padding: 24px;
        border-radius: 4px;
        transition: transform 0.2s ease, border-left-color 0.2s ease;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .resource-card:hover {
        transform: translateY(-5px);
        border-left-color: var(--accent-primary);
    }

    .resource-card h3 {
        color: #fff;
        font-family: 'Exo 2', sans-serif;
        font-size: 1.4rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .resource-card h3 i {
        color: var(--accent-primary);
    }

    .resource-card p {
        margin: 0;
        font-size: 1rem;
    }

    .pulse-ring {
        display: inline-block;
        width: 12px;
        height: 12px;
        background-color: var(--accent-primary);
        border-radius: 50%;
        box-shadow: 0 0 15px var(--accent-primary);
        margin-right: 15px;
    }
</style>

<div class="pso-spinner-svg">
    <canvas id="star-canvas-stats"></canvas>
    <svg class="hex2"></svg>
</div>

<main class="container" style="margin-top: 100px;">
    <div class="main-header" style="margin-bottom: 2rem;">
        <h1><div class="pulse-ring"></div> Development Resources</h1>
        <p style="color: #94a3b8; font-family: 'Exo 2', sans-serif; font-size: 1.1rem; max-width: 800px; margin-top: 10px; line-height: 1.6;">
            <strong>[PIONEER 2 ARCHIVE ACCESS]</strong><br>
            Welcome to the centralized development hub for PSOBB.IO. This portal provides direct access to our core infrastructure, repositories, and active development environments. Whether you are debugging server logic, modifying the client, or building new game features, the resources here will serve as your primary toolkit.
        </p>
    </div>

    <div class="dev-dashboard">
        <div class="glass-panel highlight-card">
            <h2><i class="fas fa-server"></i> Core Infrastructure</h2>
            <p>Access our private source control and live development environments. These are restricted environments intended only for active contributors.</p>
            
            <div class="resource-grid">
                <a href="https://gitlab.psobb.io" target="_blank" class="resource-card">
                    <h3><i class="fab fa-gitlab"></i> GitLab Repository</h3>
                    <p>The primary source code repository for the PSOBB.IO website, backend APIs, and community tools. Manage issues, review merge requests, and deploy code.</p>
                </a>
                
                <a href="https://pioneer0.psobb.io" target="_blank" class="resource-card">
                    <h3><i class="fas fa-satellite-dish"></i> Pioneer 0 (Dev Server)</h3>
                    <p>Our staging and development game server environment. Used for live-testing new quests, drop tables, and backend modifications before public deployment.</p>
                </a>
            </div>
        </div>

        <div class="glass-panel">
            <h2><i class="fas fa-tools"></i> Local Tools</h2>
            <p>Direct links to our custom-built web tools for analyzing and modifying the game client and server data.</p>

            <div class="resource-grid">
                <a href="/mods.php" class="resource-card">
                    <h3><i class="fas fa-laptop-code"></i> Client Mods</h3>
                    <p>Configure and generate patched executable files, widescreen fixes, and modern UI enhancements for the game client.</p>
                </a>
                
                <a href="/quest-editor" class="resource-card">
                    <h3><i class="fas fa-map-marked-alt"></i> Quest Editor</h3>
                    <p>Web-based interface for visualizing, modifying, and creating custom quests, NPC spawns, and map layouts.</p>
                </a>

                <a href="/decryption.php" class="resource-card">
                    <h3><i class="fas fa-microchip"></i> Data Decryption</h3>
                    <p>Live telemetry from our autonomous Ghidra agents as they reverse-engineer and document the game's binary executable.</p>
                </a>
            </div>
        </div>
    </div>
</main>

<script>
    // Initialize background stars
    if(typeof initStarBackground === 'function') {
        initStarBackground('star-canvas-stats');
    }
</script>

<?php include 'includes/footer.php'; ?>
