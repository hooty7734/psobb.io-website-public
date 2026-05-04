<?php
$page_title = 'MAG Feeder - PSOBB Private Server';
$current_page = 'magfeeder';
include 'includes/header.php';
?>

    <div class="pso-spinner-svg">
        <canvas id="star-canvas-magfeeder"></canvas>
        <svg class="hex2"><!-- hex SVG --></svg>
    </div>

    <main class="container">
        <div class="main-header" style="margin-bottom: 2rem;">
            <h1>🧲 MAG Feeder</h1>
            <p>Feed your MAG remotely while you play!</p>
        </div>

        <div class="layout-grid">
            <section class="main-content">
                <div id="mag-status" class="alert-box" style="display: none; margin-bottom: 1.5rem;"></div>

                <div id="mag-login-prompt" style="display: none; margin-bottom: 2rem;">
                    <div class="server-status-widget">
                        <h3>⚠️ Not Available</h3>
                        <p style="color: var(--text-secondary); margin-top: 0.5rem;">You must be logged in and actively in a game to use the MAG feeder.</p>
                        <a href="/login.php" class="dl-btn" style="margin-top: 1rem; display: inline-block;">Login</a>
                    </div>
                </div>

                <!-- Character Info -->
                <div id="mag-char-info" class="server-status-widget" style="display: none; margin-bottom: 1.5rem;">
                    <h3>Active Character</h3>
                    <div class="status-row">
                        <span>Name:</span>
                        <span id="mag-char-name" class="highlight-text"></span>
                    </div>
                    <div class="status-row">
                        <span>Class:</span>
                        <span id="mag-char-class" class="highlight-text"></span>
                    </div>
                    <div class="status-row">
                        <span>Level:</span>
                        <span id="mag-char-level" class="highlight-text"></span>
                    </div>
                </div>

                <!-- MAG Display -->
                <div id="mag-display" style="display: none; margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2>Your MAG</h2>
                        <button id="mag-refresh-btn" class="dl-btn" style="font-size: 0.85rem; padding: 8px 16px;" onclick="loadMagData()">
                            🔄 Refresh
                        </button>
                    </div>
                    <div id="mag-card-container"></div>
                </div>

                <!-- Hunger Timer -->
                <div id="hunger-timer-section" style="display: none; margin-bottom: 2rem;">
                    <div class="server-status-widget" id="hunger-timer-widget">
                        <h3 id="hunger-label">🍖 MAG is Hungry!</h3>
                        <div id="hunger-timer-bar-wrapper" style="margin-top: 1rem;">
                            <div style="background: rgba(255,255,255,0.1); border-radius: 8px; overflow: hidden; height: 28px; position: relative;">
                                <div id="hunger-timer-fill" style="height: 100%; background: linear-gradient(90deg, #00ff88, #00ccff); border-radius: 8px; transition: width 1s linear; width: 100%;"></div>
                                <span id="hunger-timer-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; font-size: 0.9rem; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5);">Ready to feed!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feed Items Grid -->
                <div id="feed-items-section" style="display: none; margin-bottom: 2rem;">
                    <h2 style="margin-bottom: 1rem;">📦 Feed Items</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">Tap an item to feed it to your MAG</p>
                    <div id="feed-items-grid" class="mag-feed-grid"></div>
                    <div id="no-feed-items" style="display: none; color: var(--text-secondary); text-align: center; padding: 2rem;">
                        No feedable items in your inventory.<br>Stock up on Monomates, Fluids, and Atomizers!
                    </div>
                </div>

            </section>
        </div>
    </main>

    <style>
        .mag-card {
            background: linear-gradient(135deg, rgba(0, 255, 136, 0.08), rgba(0, 204, 255, 0.08));
            border: 1px solid rgba(0, 255, 136, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .mag-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .mag-card-header h3 {
            color: #00ff88;
            font-size: 1.2rem;
            margin: 0;
        }
        .mag-card-header .mag-level {
            background: linear-gradient(135deg, #00ff88, #00ccff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.4rem;
            font-weight: bold;
        }
        .mag-stat-row {
            display: flex;
            align-items: center;
            margin-bottom: 0.6rem;
            gap: 0.5rem;
        }
        .mag-stat-label {
            width: 55px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        .mag-stat-bar-bg {
            flex: 1;
            height: 20px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }
        .mag-stat-bar-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        .mag-stat-bar-fill.def { background: linear-gradient(90deg, #4488ff, #66aaff); }
        .mag-stat-bar-fill.pow { background: linear-gradient(90deg, #ff4444, #ff8866); }
        .mag-stat-bar-fill.dex { background: linear-gradient(90deg, #ffaa00, #ffcc44); }
        .mag-stat-bar-fill.mind { background: linear-gradient(90deg, #aa44ff, #cc88ff); }
        .mag-stat-value {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.75rem;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 1px 3px rgba(0,0,0,0.8);
        }
        .mag-meta-row {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .mag-meta-item {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        .mag-meta-item span {
            color: #00ff88;
            font-weight: bold;
        }

        .mag-feed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .feed-item-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .feed-item-card:hover, .feed-item-card:active {
            background: rgba(0, 255, 136, 0.12);
            border-color: rgba(0, 255, 136, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 255, 136, 0.15);
        }
        .feed-item-card.disabled {
            opacity: 0.4;
            pointer-events: none;
            cursor: not-allowed;
        }
        .feed-item-card .feed-icon {
            font-size: 2rem;
            margin-bottom: 0.3rem;
        }
        .feed-item-card .feed-name {
            font-size: 0.8rem;
            color: var(--text-primary);
            font-weight: 600;
        }
        .feed-item-card .feed-count {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        @media (max-width: 768px) {
            .mag-feed-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            .feed-item-card {
                padding: 0.7rem 0.5rem;
            }
            .feed-item-card .feed-icon {
                font-size: 1.5rem;
            }
            .feed-item-card .feed-name {
                font-size: 0.7rem;
            }
            .mag-stat-label {
                width: 45px;
                font-size: 0.75rem;
            }
        }
    </style>

    <script src="/js/magfeeder.js"></script>

<?php include 'includes/footer.php'; ?>
