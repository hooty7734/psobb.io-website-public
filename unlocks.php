<?php
$page_title = 'Unlocks - PSOBB Private Server';
$current_page = 'unlocks';
include 'includes/header.php';
?>

    <div class="pso-spinner-svg">
        <canvas id="star-canvas-unlocks"></canvas>
        <svg class="hex2"><!-- hex SVG --></svg>
    </div>

    <main class="container">
        <div class="main-header" style="margin-bottom: 2rem;">
            <h1>Level Unlocks</h1>
            <p>Claim exclusive rewards for reaching level milestones!</p>
        </div>

        <div class="layout-grid">
            <section class="main-content">
                <div id="unlocks-status" class="alert-box" style="display: none; margin-bottom: 2rem;"></div>

                <div id="character-info" class="server-status-widget" style="display: none; margin-bottom: 2rem;">
                    <h3>Active Character</h3>
                    <div class="status-row">
                        <span>Name:</span>
                        <span id="char-name" class="highlight-text"></span>
                    </div>
                    <div class="status-row">
                        <span>Class:</span>
                        <span id="char-class" class="highlight-text"></span>
                    </div>
                    <div class="status-row">
                        <span>Level:</span>
                        <span id="char-level" class="highlight-text"></span>
                    </div>
                </div>

                <!-- Daily Streak Section -->
                <div id="streak-section" style="display: none; margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1rem;">🔥 Daily Login Streak</h2>
                    <div class="streak-container">
                        <div class="streak-info">
                            <span id="streak-count" class="streak-number">0</span>
                            <span class="streak-label">consecutive days</span>
                        </div>
                        <div class="streak-bar-wrapper">
                            <div class="streak-bar">
                                <div id="streak-fill" class="streak-fill" style="width: 0%;"></div>
                            </div>
                            <div class="streak-nodes">
                                <div class="streak-node" data-day="3" data-milestone="3">
                                    <div class="streak-node-dot"></div>
                                    <div class="streak-node-label">3 Days</div>
                                    <div class="streak-node-reward">Monogrinder</div>
                                </div>
                                <div class="streak-node" data-day="7" data-milestone="7">
                                    <div class="streak-node-dot"></div>
                                    <div class="streak-node-label">7 Days</div>
                                    <div class="streak-node-reward">Stat Mat</div>
                                </div>
                                <div class="streak-node" data-day="14" data-milestone="14">
                                    <div class="streak-node-dot"></div>
                                    <div class="streak-node-label">14 Days</div>
                                    <div class="streak-node-reward">Stat Mat</div>
                                </div>
                                <div class="streak-node" data-day="21" data-milestone="21">
                                    <div class="streak-node-dot"></div>
                                    <div class="streak-node-label">21 Days</div>
                                    <div class="streak-node-reward">Stat Mat</div>
                                </div>
                                <div class="streak-node" data-day="28" data-milestone="28">
                                    <div class="streak-node-dot"></div>
                                    <div class="streak-node-label">28 Days</div>
                                    <div class="streak-node-reward">Stat Mat</div>
                                </div>
                                <div class="streak-node" data-day="30" data-milestone="30">
                                    <div class="streak-node-dot"></div>
                                    <div class="streak-node-label">30 Days</div>
                                    <div class="streak-node-reward">Trigrinder</div>
                                </div>
                            </div>
                        </div>
                        <div id="streak-claims" class="streak-calendar"></div>
                    </div>
                </div>

                <!-- Daily Reward Section -->
                <div id="daily-reward-section" style="display: none; margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1rem;">🎁 Daily Reward</h2>
                    <div class="streak-container" style="border-color: rgba(0, 200, 200, 0.4);">
                        <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem;">Claim a free random item every day just for playing!</p>
                        <button id="daily-claim-btn" class="streak-claim-btn" style="width: 100%; padding: 0.8rem; font-size: 1rem;">
                            🎲 Claim Daily Reward
                        </button>
                        <div id="daily-result" style="margin-top: 1rem; display: none; text-align: center; color: #00ff88; font-family: 'Share Tech Mono', monospace;"></div>
                    </div>
                </div>

                <h2>Available Milestones</h2>
                <div id="milestones-container" class="milestones-grid">
                    <p id="loading-text">Loading your character data...</p>
                </div>
            </section>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h3>How it Works</h3>
                    <p style="margin-bottom: 1rem; color: var(--text-muted);">
                        You must be <strong>logged into the game</strong> with the character you want to claim rewards on.
                    </p>
                    <p style="margin-bottom: 1rem; color: var(--text-muted);">
                        Every 5 levels, you unlock a new reward crate! Choose carefully, each milestone can only be claimed once per character.
                    </p>
                    <p style="color: var(--text-muted);">
                        Weapons and armors are automatically curated based on your level and class. Armors come with 4 slots and high stats!
                    </p>
                    <div class="widget-divider"></div>
                    <ul class="sidebar-links">
                        <li><a href="stats.php">View Server Stats</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

    <!-- Modal for claiming -->
    <div id="claim-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 id="modal-title" style="font-family: 'Share Tech Mono', 'Segoe UI', monospace; color: var(--pso-blue);">Claim Level <span id="modal-level"></span> Reward</h2>
            <p style="margin-top: 1rem; margin-bottom: 1.5rem; color: rgba(255, 255, 255, 0.7);">Select your preferred reward category below. The item will be dropped instantly beside your character in-game!</p>
            
            <div class="reward-options">
                <button class="dl-btn claim-category-btn" data-category="Weapon" style="width: 100%; border-color: #ff4444; background: rgba(255, 68, 68, 0.15); color: #ffaaaa;">Weapon</button>
                <button class="dl-btn claim-category-btn" data-category="Armor" style="width: 100%; border-color: #33b5e5; background: rgba(51, 181, 229, 0.15); color: #aaddff;">Armor / Frame</button>
                <button class="dl-btn claim-category-btn" data-category="Shield" style="width: 100%; border-color: #33b5e5; background: rgba(51, 181, 229, 0.15); color: #aaddff;">Shield / Barrier</button>
                <button class="dl-btn claim-category-btn" data-category="Mag" style="width: 100%; border-color: #00c8c8; background: rgba(0, 200, 200, 0.15); color: #80f0f0;">Rare Mag (1x)</button>
                <button class="dl-btn claim-category-btn" data-category="Random" style="width: 100%; border-color: #00C851; background: rgba(0, 200, 81, 0.15); color: #aaffaa;">Random / Utility (3x drops)</button>
            </div>
            
            <div id="modal-error" style="color: #ff4444; margin-top: 1rem; display: none;"></div>
        </div>
    </div>

    <!-- Drop Animation Overlay -->
    <div id="drop-animation-overlay" class="drop-overlay" style="display: none;">
        <div id="countdown-text" class="countdown-text"></div>
        <div class="thank-you-text" id="thank-you-text">THANK YOU FOR PLAYING!</div>
        <div class="drop-item-box" id="drop-item-box">
            <div class="drop-box-core">
                <div class="face front"></div>
                <div class="face back"></div>
                <div class="face right"></div>
                <div class="face left"></div>
                <div class="face top"></div>
                <div class="face bottom"></div>
            </div>
            <div class="drop-box-glow"></div>
        </div>
    </div>

    <!-- Extra styles for unlocks -->
    <style>
        /* Daily Streak Styles */
        .streak-container {
            background: var(--pso-panel);
            border: 1px solid rgba(255, 170, 0, 0.4);
            border-radius: 12px;
            padding: 1.5rem;
        }
        .streak-info {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .streak-number {
            font-family: 'Share Tech Mono', monospace;
            font-size: 3rem;
            font-weight: bold;
            color: #ffaa00;
            text-shadow: 0 0 20px rgba(255, 170, 0, 0.6);
            line-height: 1;
        }
        .streak-label {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.6);
        }
        .streak-bar-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .streak-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 3px;
            overflow: visible;
            position: relative;
        }
        .streak-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffaa00, #ff6600);
            border-radius: 3px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 12px rgba(255, 170, 0, 0.6), 0 0 4px rgba(255, 170, 0, 0.8);
            position: relative;
        }
        .streak-fill::after {
            content: '';
            position: absolute;
            right: -3px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 10px #ffaa00, 0 0 20px rgba(255, 170, 0, 0.5);
        }
        .streak-nodes {
            display: flex;
            justify-content: space-between;
            margin-top: 0.75rem;
        }
        .streak-node {
            text-align: center;
            position: relative;
        }
        .streak-node-dot {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            background: var(--pso-dark);
            margin: 0 auto 0.4rem;
            transition: all 0.3s ease;
        }
        .streak-node.reached .streak-node-dot {
            border-color: #ffaa00;
            background: #ffaa00;
            box-shadow: 0 0 12px rgba(255, 170, 0, 0.8);
        }
        .streak-node.claimable .streak-node-dot {
            border-color: #00ff88;
            background: #00ff88;
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.8);
            animation: pulse-glow 1.5s ease-in-out infinite;
        }
        .streak-node.claimed .streak-node-dot {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: none;
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 10px rgba(0, 255, 136, 0.6); }
            50% { box-shadow: 0 0 25px rgba(0, 255, 136, 1); }
        }
        .streak-node-label {
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }
        .streak-node-reward {
            font-size: 0.7rem;
            color: #ffaa00;
            margin-top: 0.2rem;
        }

        /* Streak Calendar Grid */
        .streak-calendar {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }
        .streak-day {
            position: relative;
            border-radius: 8px;
            padding: 10px 6px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            transition: all 0.25s ease;
            cursor: default;
            overflow: hidden;
        }
        .streak-day .day-num {
            font-family: 'Share Tech Mono', monospace;
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.25);
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .streak-day .day-reward {
            font-family: 'Rajdhani', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
            color: rgba(255, 255, 255, 0.35);
        }

        /* Reward tier coloring */
        .streak-day.tier-mono { border-color: rgba(255, 170, 0, 0.12); }
        .streak-day.tier-mono .day-reward { color: rgba(255, 170, 0, 0.4); }
        .streak-day.tier-dig { border-color: rgba(51, 181, 229, 0.12); }
        .streak-day.tier-dig .day-reward { color: rgba(51, 181, 229, 0.4); }
        .streak-day.tier-stat { border-color: rgba(170, 102, 204, 0.12); }
        .streak-day.tier-stat .day-reward { color: rgba(170, 102, 204, 0.4); }
        .streak-day.tier-tri { border-color: rgba(255, 215, 0, 0.15); }
        .streak-day.tier-tri .day-reward { color: rgba(255, 215, 0, 0.5); }

        /* Claimed state */
        .streak-day.day-claimed {
            background: rgba(255, 255, 255, 0.04);
            opacity: 0.5;
        }
        .streak-day.day-claimed .day-reward {
            text-decoration: line-through;
            text-decoration-color: rgba(255,255,255,0.15);
        }
        .streak-day.day-claimed .day-check {
            position: absolute;
            top: 4px;
            right: 5px;
            font-size: 0.65rem;
            color: rgba(0, 255, 136, 0.7);
        }

        /* Claimable state */
        .streak-day.day-claimable {
            border-color: rgba(0, 255, 136, 0.5);
            background: rgba(0, 255, 136, 0.08);
            cursor: pointer;
            animation: day-pulse 2s ease-in-out infinite;
        }
        .streak-day.day-claimable .day-num { color: rgba(0, 255, 136, 0.8); }
        .streak-day.day-claimable .day-reward { color: #00ff88; }
        .streak-day.day-claimable:hover {
            background: rgba(0, 255, 136, 0.18);
            border-color: rgba(0, 255, 136, 0.8);
            box-shadow: 0 0 18px rgba(0, 255, 136, 0.3), inset 0 0 15px rgba(0, 255, 136, 0.05);
            transform: translateY(-2px);
        }
        .streak-day.day-claimable .claim-label {
            display: block;
            font-family: 'Rajdhani', sans-serif;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #00ff88;
            margin-top: 4px;
            text-shadow: 0 0 8px rgba(0, 255, 136, 0.5);
        }
        @keyframes day-pulse {
            0%, 100% { box-shadow: 0 0 8px rgba(0, 255, 136, 0.15); }
            50% { box-shadow: 0 0 16px rgba(0, 255, 136, 0.3); }
        }

        /* Reached but not yet claimable (already passed) — slightly brighter than locked */
        .streak-day.day-reached {
            background: rgba(255, 170, 0, 0.04);
            border-color: rgba(255, 170, 0, 0.15);
        }
        .streak-day.day-reached .day-num { color: rgba(255, 170, 0, 0.6); }

        /* Day 30 final reward highlight */
        .streak-day.tier-tri.day-claimable {
            border-color: rgba(255, 215, 0, 0.6);
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 140, 0, 0.08));
            animation: day-pulse-gold 2s ease-in-out infinite;
        }
        .streak-day.tier-tri.day-claimable .day-reward { color: #ffd700; }
        .streak-day.tier-tri.day-claimable .claim-label { color: #ffd700; text-shadow: 0 0 8px rgba(255, 215, 0, 0.5); }
        @keyframes day-pulse-gold {
            0%, 100% { box-shadow: 0 0 8px rgba(255, 215, 0, 0.15); }
            50% { box-shadow: 0 0 20px rgba(255, 215, 0, 0.35); }
        }

        /* Streak claim button (kept for daily reward section) */
        .streak-claim-btn {
            flex: 1;
            min-width: 120px;
            padding: 0.6rem 1rem;
            border: 1px solid #00ff88;
            background: rgba(0, 255, 136, 0.1);
            color: #00ff88;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Rajdhani', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s ease;
        }
        .streak-claim-btn:hover {
            background: rgba(0, 255, 136, 0.25);
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.3);
        }
        .streak-claim-btn:disabled {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
            box-shadow: none;
        }

        @media (max-width: 600px) {
            .streak-calendar {
                grid-template-columns: repeat(5, 1fr);
                gap: 6px;
            }
            .streak-day { padding: 8px 4px; }
            .streak-day .day-reward { font-size: 0.65rem; }
        }

        .milestones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .milestone-card {
            background: var(--pso-panel);
            border: 1px solid var(--pso-blue);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
        }
        .milestone-card:hover {
            border-color: var(--pso-purple);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(157, 78, 221, 0.2);
        }
        .milestone-level {
            font-size: 1.5rem;
            color: var(--pso-blue);
            font-family: 'Share Tech Mono', 'Segoe UI', monospace;
            margin-bottom: 1rem;
            text-shadow: 0 0 5px rgba(0, 255, 255, 0.3);
        }
        .milestone-card.claimed {
            opacity: 0.6;
            border-color: rgba(255, 255, 255, 0.1);
        }
        .milestone-card.claimed:hover {
            transform: none;
            box-shadow: none;
            border-color: rgba(255, 255, 255, 0.1);
        }
        .milestone-card.locked {
            opacity: 0.4;
            filter: grayscale(1);
        }
        
        .open-claim-btn {
            width: 100%;
            margin-top: 1.5rem;
            background: rgba(0, 255, 255, 0.1) !important;
            color: var(--pso-blue) !important;
            border: 1px solid var(--pso-blue) !important;
            padding: 10px 5px !important;
            font-size: 1rem !important;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.2);
            text-transform: uppercase;
            font-family: inherit;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .open-claim-btn:hover:not([disabled]) {
            background: rgba(0, 255, 255, 0.25) !important;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
            color: #fff !important;
        }
        .open-claim-btn[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            color: rgba(255, 255, 255, 0.5) !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        
        .modal {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: var(--pso-dark);
            border: 1px solid var(--pso-blue);
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.15);
        }
        .close-modal {
            position: absolute;
            top: 10px; right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.5);
            transition: color 0.3s ease;
        }
        .close-modal:hover { color: white; text-shadow: 0 0 5px white; }
        .reward-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .alert-box {
            padding: 1rem;
            border-radius: 4px;
            background: rgba(255, 170, 0, 0.1);
            border: 1px solid var(--pso-orange);
            color: var(--pso-orange);
        }
        .alert-box.success {
            background: rgba(30, 255, 100, 0.1);
            border-color: #1eff64;
            color: #1eff64;
        }

        /* Custom Drop Animation */
        .drop-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }
        .drop-item-box {
            position: relative;
            width: 60px;
            height: 60px;
            transform-style: preserve-3d;
            animation: dropIn 1s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }
        .thank-you-text {
            position: absolute;
            top: 20%;
            width: 100%;
            text-align: center;
            font-family: 'Share Tech Mono', 'Segoe UI', monospace;
            font-size: 4rem;
            color: #fff;
            text-shadow: 0 0 20px rgba(0, 255, 255, 1), 0 0 40px rgba(0, 255, 255, 0.8);
            z-index: 10000;
            opacity: 0;
            pointer-events: none;
        }
        
        .countdown-text {
            position: absolute;
            top: 40%;
            width: 100%;
            text-align: center;
            font-family: 'Share Tech Mono', 'Segoe UI', monospace;
            font-size: 8rem;
            color: #fff;
            text-shadow: 0 0 30px rgba(255, 255, 255, 1);
            z-index: 10001;
            transition: transform 0.2s ease;
            pointer-events: none;
        }

        .drop-box-core {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transform: rotateX(-30deg) rotateY(45deg);
            animation: spinBox 3s linear infinite;
        }
        .drop-box-core .face {
            position: absolute;
            width: 60px;
            height: 60px;
            background: rgba(255, 42, 42, 0.8);
            border: 2px solid #ffaaaa;
            box-shadow: inset 0 0 15px rgba(255,255,255,0.5);
            backface-visibility: inherit;
        }
        .drop-box-core .face.front  { transform: rotateY(  0deg) translateZ(30px); }
        .drop-box-core .face.right  { transform: rotateY( 90deg) translateZ(30px); }
        .drop-box-core .face.back   { transform: rotateY(180deg) translateZ(30px); }
        .drop-box-core .face.left   { transform: rotateY(-90deg) translateZ(30px); }
        .drop-box-core .face.top    { transform: rotateX( 90deg) translateZ(30px); }
        .drop-box-core .face.bottom { transform: rotateX(-90deg) translateZ(30px); }
        .drop-box-glow {
            position: absolute;
            top: 50%; left: 50%;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(255, 42, 42, 0.8) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            opacity: 0;
            animation: glowBurst 1.5s 0.8s forwards; /* drops at 0.8s */
        }

        @keyframes dropIn {
            0% { transform: translateY(-300px) scale(0.5); opacity: 0; }
            60% { transform: translateY(20px) scale(1.1); opacity: 1; }
            80% { transform: translateY(-10px) scale(0.95); }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        @keyframes textDrop {
            0% { transform: translateY(-100px) scale(0.8); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        @keyframes spinBox {
            from { transform: rotateX(-20deg) rotateY(0deg); }
            to { transform: rotateX(-20deg) rotateY(360deg); }
        }
        @keyframes glowBurst {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.5); }
            50% { opacity: 1; transform: translate(-50%, -50%) scale(1.2); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(2); }
        }

        /* Green box variations for random items */
        .drop-item-box.green-box .drop-box-core .face {
            background: rgba(42, 255, 42, 0.8);
            border-color: #aaffaa;
        }
        .drop-item-box.green-box .drop-box-glow {
            background: radial-gradient(circle, rgba(42, 255, 42, 0.8) 0%, transparent 70%);
        }

        /* Orange box variations for common weapons */
        .drop-item-box.orange-box .drop-box-core .face {
            background: rgba(255, 128, 0, 0.8);
            border-color: #ffcc99;
        }
        .drop-item-box.orange-box .drop-box-glow {
            background: radial-gradient(circle, rgba(255, 128, 0, 0.8) 0%, transparent 70%);
        }

        /* Blue box variations for armor/shield items */
        .drop-item-box.blue-box .drop-box-core .face {
            background: rgba(42, 117, 255, 0.8);
            border-color: #aaccff;
        }
        .drop-item-box.blue-box .drop-box-glow {
            background: radial-gradient(circle, rgba(42, 117, 255, 0.8) 0%, transparent 70%);
        }

        /* Teal box variations for Mag items */
        .drop-item-box.teal-box .drop-box-core .face {
            background: rgba(0, 200, 200, 0.8);
            border-color: #80f0f0;
        }
        .drop-item-box.teal-box .drop-box-glow {
            background: radial-gradient(circle, rgba(0, 200, 200, 0.8) 0%, transparent 70%);
        }
    </style>

    <script src="js/unlocks.js"></script>

<?php include 'includes/footer.php'; ?>
