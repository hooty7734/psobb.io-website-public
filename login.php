<?php
/**
 * PSOBB Website: Login & Dashboard
 * 
 * Handles user authentication via the NewServ API (using the same credentials as the game client).
 * If logged in, renders the Player Dashboard which provides access to Account Settings, 
 * Character Management (Bank Swap, Section ID), and Discord Integration links.
 */
$page_title = 'Login - PSOBB Private Server';
$current_page = 'login';
include 'includes/header.php';
?>

    <main class="container">
        <div class="login-container">
            <h1><?= __('Hunter\'s License Login') ?></h1>
            
            <div class="login-container-form">
                <p><?= __('Access your account data, character stats, and bank.') ?></p>
                <div id="login-error" style="color: #ff4444; display: none; margin-bottom: 1rem; background: rgba(255, 0, 0, 0.1); padding: 10px; border: 1px solid #ff4444; border-radius: 4px;"></div>
                
                <form class="login-form" method="POST" action="login.php">
                    <div class="form-group">
                        <label for="username"><?= __('Username') ?></label>
                        <input type="text" id="username" name="username" placeholder="<?= __('Enter your username') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><?= __('Password') ?></label>
                        <input type="password" id="password" name="password" placeholder="<?= __('Enter your password') ?>" required>
                    </div>
                    
                    <div class="form-group" id="captcha-group" style="display:none;">
                        <label for="captcha"><?= __('Security Check') ?></label>
                        <div style="display:flex; gap:10px; align-items:center;">
                            <img id="captcha-img" src="api/captcha.php" alt="CAPTCHA" style="cursor:pointer; border:1px solid #444; height:40px;" title="Click to reload" onclick="this.src='api/captcha.php?'+Math.random()">
                            <input type="text" id="captcha" name="captcha" placeholder="<?= __('Enter code') ?>" style="width: 120px;">
                        </div>
                    </div>
                    
                    <button type="submit" class="dl-btn login-submit"><?= __('Login') ?></button>
                </form>
                
                <div class="login-help">
                    <p><a href="forgot_password" style="color: #aaa; font-size: 0.9em;"><?= __('Forgot Password?') ?></a></p>
                    <p style="margin-top: 5px;"><?= __('Don\'t have an account?') ?> <a href="register" style="color: #4CAF50;"><?= __('Create one here') ?></a>.</p>
                </div>
            </div>

            <div id="dashboard" style="display: none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:10px;">
                    <h2 style="margin:0; font-family:'Share Tech Mono', monospace;"><i class="fas fa-id-card animate-pulse" style="color:#00ffff; margin-right:8px;"></i><?= __('Welcome,') ?> <span id="dash-username-header" style="color:#00ffff;">Hunter</span></h2>
                    <div style="display:flex; gap:10px;">
                        <button id="player-guide-btn" onclick="openPlayerGuideModal()" class="dl-btn" style="border: 1px solid #00ffff; color: #00ffff; background: rgba(0, 255, 255, 0.1); padding: 5px 15px; box-shadow: 0 0 5px rgba(0, 255, 255, 0.2); font-family:'Share Tech Mono',monospace; font-weight:bold; font-size:0.85rem;"><i class="fas fa-terminal"></i> <?= __('Guide & Commands') ?></button>
                        <button onclick="logout()" class="dl-btn" style="border: 1px solid #ff4444; color: #ff4444; background: rgba(255, 68, 68, 0.1); padding: 5px 15px; box-shadow: 0 0 5px rgba(255, 68, 68, 0.2); font-family:'Share Tech Mono',monospace; font-weight:bold; font-size:0.85rem;"><i class="fas fa-sign-out-alt"></i> <?= __('Logout') ?></button>
                    </div>
                </div>

                <!-- Dashboard SPA Tabs Navigation (Desktop) -->
                <div class="dashboard-tabs desktop-only">
                    <button class="dl-btn tab-btn active" onclick="switchDashboardTab('tab-hub')" data-tab="tab-hub"><i class="fas fa-id-card"></i> <?= __('Account') ?></button>
                    <button class="dl-btn tab-btn" onclick="switchDashboardTab('tab-banks')" data-tab="tab-banks"><i class="fas fa-user-astronaut"></i> <?= __('Character') ?></button>
                    <button class="dl-btn tab-btn" onclick="switchDashboardTab('tab-guild')" data-tab="tab-guild"><i class="fas fa-crosshairs"></i> <?= __('Bounties') ?></button>
                    <button class="dl-btn tab-btn" onclick="switchDashboardTab('tab-chat')" data-tab="tab-chat"><i class="fas fa-comments"></i> <?= __('Chat') ?></button>
                    <button class="dl-btn tab-btn" onclick="switchDashboardTab('tab-lfg')" data-tab="tab-lfg"><i class="fas fa-users"></i> <?= __('LFG') ?></button>
                    <button class="dl-btn tab-btn" onclick="switchDashboardTab('tab-drops')" data-tab="tab-drops"><i class="fas fa-dice-d20"></i> <?= __('Drops') ?></button>
                    <button class="dl-btn tab-btn" onclick="switchDashboardTab('tab-settings')" data-tab="tab-settings"><i class="fas fa-cog"></i> <?= __('Settings') ?></button>
                </div>

                <!-- Mobile Bottom Navigation Bar (shown only on mobile after login) -->
                <nav class="mobile-bottom-nav" id="dashboard-bottom-nav">
                    <div class="mobile-bottom-nav-inner">
                        <button class="bottom-nav-item active" onclick="switchDashboardTab('tab-hub', this)" data-tab="tab-hub">
                            <i class="fas fa-id-card"></i>
                            <span><?= __('Account') ?></span>
                        </button>
                        <button class="bottom-nav-item" onclick="switchDashboardTab('tab-banks', this)" data-tab="tab-banks">
                            <i class="fas fa-user-astronaut"></i>
                            <span><?= __('Character') ?></span>
                        </button>
                        <button class="bottom-nav-item" onclick="switchDashboardTab('tab-guild', this)" data-tab="tab-guild">
                            <i class="fas fa-crosshairs"></i>
                            <span><?= __('Bounties') ?></span>
                        </button>
                        <button class="bottom-nav-item" onclick="switchDashboardTab('tab-chat', this)" data-tab="tab-chat">
                            <i class="fas fa-comments"></i>
                            <span><?= __('Chat') ?></span>
                        </button>
                        <button class="bottom-nav-item" onclick="switchDashboardTab('tab-lfg', this)" data-tab="tab-lfg">
                            <i class="fas fa-users"></i>
                            <span><?= __('LFG') ?></span>
                        </button>
                        <button class="bottom-nav-item" onclick="switchDashboardTab('tab-drops', this)" data-tab="tab-drops">
                            <i class="fas fa-dice-d20"></i>
                            <span><?= __('Drops') ?></span>
                        </button>
                        <button class="bottom-nav-item" onclick="switchDashboardTab('tab-settings', this)" data-tab="tab-settings">
                            <i class="fas fa-cog"></i>
                            <span><?= __('Settings') ?></span>
                        </button>
                    </div>
                </nav>

                <!-- Tab 1: Hub -->
                <div id="tab-hub" class="dashboard-tab-pane active">
                    <div class="dashboard-content-grid" style="display: grid; grid-template-columns: minmax(320px, 1fr) 1.5fr; gap: 1.5rem;">
                        <!-- Left side: Hunter's License Card & PWA card -->
                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <div class="hunters-license-card animate-float">
                                <div class="hl-header">
                                    <div class="hl-chip"></div>
                                    <div class="hl-title"><?= __('HUNTER\'S LICENSE') ?></div>
                                </div>
                                <div class="hl-body">
                                    <div class="hl-row">
                                        <span class="hl-label"><?= __('NAME') ?></span>
                                        <span class="hl-value" id="dash-username">--</span>
                                    </div>
                                    <div class="hl-row" style="border-top: 1px dashed rgba(0, 255, 255, 0.3); padding-top: 10px;">
                                        <span class="hl-label"><?= __('GUILD CARD ID') ?></span>
                                        <span class="hl-value" id="dash-account-id" style="font-family: monospace; letter-spacing: 2px;">--</span>
                                    </div>
                                    <div class="hl-row">
                                        <span class="hl-label"><?= __('TEAM') ?></span>
                                        <span class="hl-value" id="dash-team">--</span>
                                    </div>
                                    <div class="hl-row" style="border-top: 1px dashed rgba(0, 255, 255, 0.3); padding-top: 10px;">
                                        <span class="hl-label"><?= __('TIME PLAYED') ?></span>
                                        <span class="hl-value" id="dash-playtime">--</span>
                                    </div>
                                </div>
                                <div class="hl-footer">
                                    <span class="hl-status"><?= __('STATUS: ACTIVE') ?></span>
                                    <img src="img/favicon.svg" class="hl-logo-sm" alt="logo">
                                </div>
                            </div>

                            <!-- PWA Installation Card -->
                            <div id="pwa-install-card" class="pwa-install-card" style="display: none;">
                                <h3 style="color:#00ffff; font-family:'Share Tech Mono',monospace; margin-top:0; margin-bottom:10px;"><i class="fas fa-mobile-alt animate-pulse"></i> <?= __('Companion App Available') ?></h3>
                                <p style="font-size:0.85rem; color:rgba(255,255,255,0.7); margin-bottom:15px;"><?= __('Install the PSOBB.io Companion App directly on your mobile screen or desktop for instant access!') ?></p>
                                <button onclick="installPortalApp()" class="dl-btn pwa-install-btn"><i class="fas fa-download"></i> <?= __('Install PSOBB.io Companion App') ?></button>
                            </div>
                        </div>

                        <!-- Right side: Server stats and LFG Quick Warp -->
                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <div style="border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 255, 255, 0.05); padding: 1.5rem; border-radius: 8px;">
                                <h3 style="color:#00ffff; font-family:'Share Tech Mono',monospace; margin-top:0; border-bottom:1px solid rgba(0,255,255,0.2); padding-bottom:8px; margin-bottom:12px;"><i class="fas fa-satellite-dish animate-pulse"></i> <?= __('Server Telemetry') ?></h3>
                                <div class="server-telemetry-stats" style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div style="background:rgba(0,0,0,0.4); padding:10px; border-radius:4px; border:1px solid rgba(255,255,255,0.05);">
                                        <span style="font-size:0.75rem; color:#aaa; display:block;"><?= __('EXP MULTIPLIER') ?></span>
                                        <span id="rate-exp" style="font-size:1.5rem; font-family:'Share Tech Mono',monospace; color:#ffaa00; font-weight:bold;">1.0x</span>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.4); padding:10px; border-radius:4px; border:1px solid rgba(255,255,255,0.05);">
                                        <span style="font-size:0.75rem; color:#aaa; display:block;"><?= __('DROP MULTIPLIER') ?></span>
                                        <span id="rate-drop" style="font-size:1.5rem; font-family:'Share Tech Mono',monospace; color:#00ffc8; font-weight:bold;">1.0x</span>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.4); padding:10px; border-radius:4px; border:1px solid rgba(255,255,255,0.05);">
                                        <span style="font-size:0.75rem; color:#aaa; display:block;"><?= __('PLAYERS ONLINE') ?></span>
                                        <span id="client-count" style="font-size:1.5rem; font-family:'Share Tech Mono',monospace; color:#fff; font-weight:bold;">0</span>
                                    </div>
                                    <div style="background:rgba(0,0,0,0.4); padding:10px; border-radius:4px; border:1px solid rgba(255,255,255,0.05);">
                                        <span style="font-size:0.75rem; color:#aaa; display:block;"><?= __('ACTIVE ROOMS') ?></span>
                                        <span id="game-count" style="font-size:1.5rem; font-family:'Share Tech Mono',monospace; color:#fff; font-weight:bold;">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- LFG Quick Warp / Group Card -->
                            <div style="border: 1px solid rgba(255, 170, 0, 0.2); background: rgba(255, 170, 0, 0.05); padding: 1.5rem; border-radius: 8px;">
                                <h3 style="color:#ffaa00; font-family:'Share Tech Mono',monospace; margin-top:0; border-bottom:1px solid rgba(255,170,0,0.2); padding-bottom:8px; margin-bottom:12px;"><i class="fas fa-users"></i> <?= __('LFG Terminal & Group Warp') ?></h3>
                                <p style="font-size:0.85rem; color:rgba(255,255,255,0.7); margin-bottom:15px;"><?= __('Coordinate with other players and join active party rooms in-game instantly with Direct Warp capabilities.') ?></p>
                                <div style="display:flex; gap:10px;">
                                    <a href="lfg.php" class="dl-btn" style="flex:1; text-align:center; text-decoration:none; border-color: #ffaa00; background: rgba(255, 170, 0, 0.15); color: #ffaa00; font-weight: bold; font-family: 'Share Tech Mono', monospace; font-size:0.9rem; padding:10px;">
                                        <i class="fas fa-satellite"></i> <?= __('Open LFG Terminal') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Backpack & Banks -->
                <div id="tab-banks" class="dashboard-tab-pane">
                    <!-- Character Slots selector -->
                    <div class="character-slots-bar">
                        <button class="dl-btn slot-btn active" onclick="switchCharSlot(0)" data-slot="0">Character 1</button>
                        <button class="dl-btn slot-btn" onclick="switchCharSlot(1)" data-slot="1">Character 2</button>
                        <button class="dl-btn slot-btn" onclick="switchCharSlot(2)" data-slot="2">Character 3</button>
                        <button class="dl-btn slot-btn" onclick="switchCharSlot(3)" data-slot="3">Character 4</button>
                        <button id="btn-download-char" class="dl-btn" onclick="window.downloadCharacterData()" style="margin-left:auto; border-color:rgba(0,255,200,0.3); color:#00ffc8; background:rgba(0,255,200,0.06); font-size:0.75rem; padding:6px 12px;" title="Download .psochar and .psobank files">
                            <i class="fas fa-download"></i> <?= __('Export') ?>
                        </button>
                    </div>

                    <div id="viewer-loader" style="text-align: center; padding: 2rem; display: none;">
                        <i class="fas fa-spinner fa-spin fa-2x" style="color: #00ffff;"></i>
                        <p style="margin-top: 10px; font-family: 'Share Tech Mono', monospace; color: #aaa;">SYNCHRONIZING TELEMETRY...</p>
                    </div>

                    <div id="viewer-content-pane">
                        <div class="char-banks-grid">
                            <!-- Left Column: Character profile details, circular gauges, section ID and Reset -->
                            <div>
                                <div class="equipped-gear-box">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <h3 id="char-profile-name" style="margin:0; color:#fff; font-family:'Share Tech Mono', monospace; font-size:1.4rem;">Hunter</h3>
                                        <div id="char-profile-online" style="font-size:0.8rem; font-weight:bold;">--</div>
                                    </div>
                                    <p style="margin: 4px 0 12px 0; color:rgba(255,255,255,0.5); font-size:0.9rem; font-family:'Share Tech Mono', monospace;">
                                        <span id="char-profile-class">--</span> // <span id="char-profile-level">--</span>
                                    </p>

                                    <div style="display:flex; justify-content:center; align-items:center; background:rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.05); border-radius:8px; padding:15px; margin-bottom:1.5rem; position:relative; min-height:160px;">
                                        <img id="char-profile-avatar-fallback" src="" alt="avatar" style="max-height:140px; object-fit:contain; filter:drop-shadow(0 0 8px rgba(0, 255, 255, 0.3)); display:none;">
                                        <div id="char-profile-secid" style="position:absolute; bottom:8px; right:8px; display:flex; align-items:center; gap:5px; background:rgba(0,0,0,0.8); border:1px solid rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px;">
                                            <!-- SecID Icon -->
                                        </div>
                                    </div>

                                    <!-- Basic stats -->
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px; font-size:0.85rem; font-family:'Share Tech Mono', monospace;">
                                        <div class="tooltip-stat-row"><span>ATP:</span><span id="stat-val-atp" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>MST:</span><span id="stat-val-mst" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>EVP:</span><span id="stat-val-evp" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>HP:</span><span id="stat-val-hp" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>DFP:</span><span id="stat-val-dfp" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>ATA:</span><span id="stat-val-ata" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>LCK:</span><span id="stat-val-lck" class="tooltip-stat-val">--</span></div>
                                        <div class="tooltip-stat-row"><span>Play Time:</span><span id="char-profile-playtime" style="color:#fff;">--</span></div>
                                    </div>
                                </div>

                                <!-- Section ID Pre-Selector Container -->
                                <div id="section-id-change-container" style="margin-bottom:1.5rem;">
                                    <!-- Injected via JS -->
                                </div>

                                <!-- Stat Material Circular Gauges -->
                                <h3 style="color:#00ffff; font-family:'Share Tech Mono', monospace; font-size:1rem; margin-bottom:10px;"><i class="fas fa-shield-alt"></i> <?= __('Material Consumption') ?></h3>
                                
                                <div class="material-gauges-grid" style="display:grid; grid-template-columns: 1fr; gap:12px; margin-bottom:1.5rem;">
                                    <!-- HP gauge -->
                                    <div class="material-gauge-card" style="flex-direction:row; justify-content:flex-start; text-align:left; gap:15px; padding:10px;">
                                        <div class="gauge-circle-container" style="width:70px; height:70px;">
                                            <svg class="gauge-svg" width="70" height="70" viewBox="0 0 100 100">
                                                <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                                                <circle id="gauge-fill-hp" class="gauge-fill hp" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2"/>
                                            </svg>
                                            <div class="gauge-value" style="font-size:0.8rem;"><span id="gauge-val-hp">0</span><small>HP</small></div>
                                        </div>
                                        <div>
                                            <h4 style="font-size:0.9rem; color:#ff4444; margin:0;"><?= __('HP Materials') ?></h4>
                                            <span style="font-size:0.8rem; color:#aaa; font-family:'Share Tech Mono',monospace;" id="mat-val-hp">0 / 125</span>
                                        </div>
                                    </div>

                                    <!-- TP gauge -->
                                    <div class="material-gauge-card" style="flex-direction:row; justify-content:flex-start; text-align:left; gap:15px; padding:10px;">
                                        <div class="gauge-circle-container" style="width:70px; height:70px;">
                                            <svg class="gauge-svg" width="70" height="70" viewBox="0 0 100 100">
                                                <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                                                <circle id="gauge-fill-tp" class="gauge-fill tp" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2"/>
                                            </svg>
                                            <div class="gauge-value" style="font-size:0.8rem;"><span id="gauge-val-tp">0</span><small>TP</small></div>
                                        </div>
                                        <div>
                                            <h4 style="font-size:0.9rem; color:#aa66cc; margin:0;"><?= __('TP Materials') ?></h4>
                                            <span style="font-size:0.8rem; color:#aaa; font-family:'Share Tech Mono',monospace;" id="mat-val-tp">0 / 125</span>
                                        </div>
                                    </div>

                                    <!-- Stat gauge -->
                                    <div class="material-gauge-card" style="flex-direction:row; justify-content:flex-start; text-align:left; gap:15px; padding:10px;">
                                        <div class="gauge-circle-container" style="width:70px; height:70px;">
                                            <svg class="gauge-svg" width="70" height="70" viewBox="0 0 100 100">
                                                <circle class="gauge-bg" cx="50" cy="50" r="40"/>
                                                <circle id="gauge-fill-stats" class="gauge-fill stats" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2"/>
                                            </svg>
                                            <div class="gauge-value" style="font-size:0.8rem;"><span id="gauge-val-stats">0</span><small>STAT</small></div>
                                        </div>
                                        <div>
                                            <h4 style="font-size:0.9rem; color:#00ffcc; margin:0;"><?= __('Stat Materials') ?></h4>
                                            <span style="font-size:0.8rem; color:#aaa; font-family:'Share Tech Mono',monospace;" id="mat-val-stats">0 / 250</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Material details list -->
                                <div class="material-gauge-card" style="text-align:left; align-items:stretch; padding:12px;">
                                    <h4 style="margin:0 0 8px 0; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:4px; font-size:0.95rem; color:#00ffff;"><i class="fas fa-list-ul"></i> <?= __('Material Summary') ?></h4>
                                    <div class="mat-details-list" style="border:none; padding:0; margin:0;">
                                        <div class="mat-detail-row"><span>Power Materials:</span><span id="mat-val-power" class="mat-detail-val">0</span></div>
                                        <div class="mat-detail-row"><span>Mind Materials:</span><span id="mat-val-mind" class="mat-detail-val">0</span></div>
                                        <div class="mat-detail-row"><span>Evade Materials:</span><span id="mat-val-evade" class="mat-detail-val">0</span></div>
                                        <div class="mat-detail-row"><span>Def Materials:</span><span id="mat-val-def" class="mat-detail-val">0</span></div>
                                        <div class="mat-detail-row"><span>Luck Materials:</span><span id="mat-val-luck" class="mat-detail-val">0 / 45</span></div>
                                    </div>
                                </div>

                                <!-- Stat Material Recalibration button -->
                                <div class="mat-reset-box">
                                    <h3>⚠️ <?= __('Material Recalibration') ?></h3>
                                    <p><?= __('Wipe all consumed materials on this slot and recalculate display stats safely. Resets require you to be offline, or in a lobby block (not inside an active game room).') ?></p>
                                    <button onclick="triggerMaterialReset()" class="dl-btn mat-reset-btn" style="width:100%; font-family:'Share Tech Mono',monospace; font-weight:bold;"><i class="fas fa-trash-restore"></i> <?= __('WIPE ALL MATERIALS') ?></button>
                                    <div id="reset-mat-message" style="margin-top:10px; font-weight:bold; display:none; font-size:0.85rem;"></div>
                                </div>
                            </div>

                            <!-- Right Column: Equipped Gear, Backpack, and Bank -->
                            <div>
                                <!-- Equipped grid -->
                                <div class="equipped-gear-box">
                                    <h3 style="margin-top:0; font-family:'Share Tech Mono', monospace; font-size:1.05rem; color:#eee; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:6px;"><i class="fas fa-shield-halved"></i> <?= __('Equipped Gear') ?></h3>
                                    <div class="equipped-grid-container" id="viewer-equipped-grid">
                                        <!-- Injected by JS -->
                                    </div>
                                </div>

                                <!-- Backpack grid (30 slots) -->
                                <div class="item-grid-title">
                                    <span>🎒 <?= __('Backpack Inventory') ?></span>
                                    <span style="font-size:0.8rem; color:#aaa; font-family:'Share Tech Mono',monospace;" id="viewer-backpack-count">0 / 30</span>
                                </div>
                                <div class="backpack-grid-box" id="viewer-backpack-grid">
                                    <!-- Injected by JS -->
                                </div>

                                <!-- Bank section (200 slots) -->
                                <div style="border-top: 1px solid rgba(255,255,255,0.1); padding-top:1.5rem; margin-top:1.5rem;">
                                    <div class="item-grid-title" style="flex-wrap:wrap; gap:10px;">
                                        <span>🏦 <?= __('Bank Vault') ?></span>
                                        <span style="font-size:0.9rem; color:#ffaa00; font-family:'Share Tech Mono', monospace;" id="viewer-bank-meseta">0 Meseta</span>
                                    </div>

                                    <!-- Bank switcher dropdown & Swap button -->
                                    <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
                                        <select id="viewer-bank-select" style="flex:1; min-width:180px; padding: 10px; background: rgba(0, 0, 0, 0.5); color: #fff; border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 4px; font-family:'Share Tech Mono', monospace; box-sizing: border-box;">
                                            <option value="0"><?= __('Character Bank') ?></option>
                                            <option value="-1"><?= __('Shared Bank') ?></option>
                                        </select>
                                        
                                        <button id="viewer-btn-swap-bank" onclick="triggerBankSwap()" class="dl-btn" style="border-color:#00ffff; background:rgba(0, 255, 255, 0.1); color:#00ffff; font-family:'Share Tech Mono', monospace; font-weight:bold; padding:10px 20px;"><i class="fas fa-arrows-rotate"></i> <?= __('Swap Bank in Game') ?></button>
                                    </div>
                                    
                                    <div id="bank-swap-result-msg" style="margin-bottom:12px; font-weight:bold; display:none; font-size:0.85rem;"></div>

                                    <!-- Search bank -->
                                    <div style="margin-bottom:15px;">
                                        <input type="text" id="viewer-bank-search" placeholder="<?= __('Search bank items...') ?>" style="width:100%; padding:10px; background:rgba(0,0,0,0.5); border:1px solid rgba(0,255,255,0.3); color:#fff; border-radius:4px; font-size:0.9rem; box-sizing:border-box;">
                                    </div>

                                    <!-- Bank items grid (200 boxes) -->
                                    <div class="bank-grid-box" id="viewer-bank-grid">
                                        <!-- Injected by JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Hunters Guild -->
                <div id="tab-guild" class="dashboard-tab-pane">
                    <!-- Guild status & milestones alerts -->
                    <div id="unlocks-status" class="alert-box" style="display: none; margin-bottom: 2rem;"></div>

                    <div class="dashboard-grid-guild">
                        <!-- Left side: Milestones and Streaks -->
                        <div>
                            <!-- Daily Streak panel -->
                            <div id="streak-section" style="margin-bottom: 2rem;">
                                <h3 style="margin-top:0; color:#ffaa00; font-family:'Share Tech Mono', monospace;"><i class="fas fa-fire animate-pulse" style="color:#ffaa00; margin-right:8px;"></i><?= __('Daily Login Streak') ?></h3>
                                <div class="streak-container" style="background: rgba(0, 10, 20, 0.4); border-color: rgba(255, 170, 0, 0.3);">
                                    <div class="streak-info">
                                        <span id="streak-count" class="streak-number">0</span>
                                        <span class="streak-label"><?= __('consecutive days') ?></span>
                                    </div>
                                    <div class="streak-bar-wrapper">
                                        <div class="streak-bar">
                                            <div id="streak-fill" class="streak-fill" style="width: 0%;"></div>
                                        </div>
                                        <div class="streak-nodes">
                                            <div class="streak-node" data-day="7" data-milestone="7">
                                                <div class="streak-node-dot"></div>
                                                <div class="streak-node-label">7 Days</div>
                                                <div class="streak-node-reward">Random Mat</div>
                                            </div>
                                            <div class="streak-node" data-day="30" data-milestone="30">
                                                <div class="streak-node-dot"></div>
                                                <div class="streak-node-label">30 Days</div>
                                                <div class="streak-node-reward">Random Mat</div>
                                            </div>
                                            <div class="streak-node" data-day="90" data-milestone="90">
                                                <div class="streak-node-dot"></div>
                                                <div class="streak-node-label">90 Days</div>
                                                <div class="streak-node-reward">Random Mat</div>
                                            </div>
                                            <div class="streak-node" data-day="180" data-milestone="180">
                                                <div class="streak-node-dot"></div>
                                                <div class="streak-node-label">180 Days</div>
                                                <div class="streak-node-reward">Random Mat</div>
                                            </div>
                                            <div class="streak-node" data-day="270" data-milestone="270">
                                                <div class="streak-node-dot"></div>
                                                <div class="streak-node-label">270 Days</div>
                                                <div class="streak-node-reward">Random Mat</div>
                                            </div>
                                            <div class="streak-node" data-day="365" data-milestone="365">
                                                <div class="streak-node-dot"></div>
                                                <div class="streak-node-label">365 Days</div>
                                                <div class="streak-node-reward">Yahoo! Mag</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="streak-claims" class="streak-calendar" style="margin-top:1.5rem;"></div>
                                </div>
                            </div>

                            <!-- Level Milestones Board -->
                            <div>
                                <h3 style="color:#00ffff; font-family:'Share Tech Mono', monospace;"><i class="fas fa-gift" style="color:#00ffff; margin-right:8px;"></i><?= __('Level Milestone Crates') ?></h3>
                                <div id="character-info" class="server-status-widget" style="display: none; margin-bottom: 1.5rem; padding:15px; border-color:rgba(0,255,255,0.2);">
                                    <p style="margin:0; font-size:0.9rem; color:rgba(255,255,255,0.7);"><?= __('Active Character detected:') ?> <strong id="char-name" style="color:#fff;">--</strong> (<span id="char-class" style="color:#00ffff;">--</span>) Lvl <strong id="char-level" style="color:#ffaa00;">--</strong></p>
                                </div>
                                <div id="milestones-container" class="milestones-grid" style="margin-top: 1rem;">
                                    <p id="loading-text" style="color:#aaa; font-family:'Share Tech Mono', monospace;">Synchronizing character rewards...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right side: Daily Claim, Active Bounties, and Completed Rewards -->
                        <div>
                            <!-- Daily Item roll -->
                            <div id="daily-reward-section" style="margin-bottom: 2rem;">
                                <h3 style="margin-top:0; color:#00ffc8; font-family:'Share Tech Mono', monospace;"><i class="fas fa-dice" style="color:#00ffc8; margin-right:8px;"></i><?= __('Daily Reward Box') ?></h3>
                                <div class="streak-container" style="background: rgba(0, 10, 20, 0.4); border-color: rgba(0, 255, 200, 0.3);">
                                    <p style="color: rgba(255,255,255,0.85); margin-bottom: 0.5rem; font-size:0.85rem;"><?= __('Claim a free random item every day just for playing!') ?></p>
                                    <button id="daily-claim-btn" class="streak-claim-btn" style="width: 100%; padding: 0.8rem; font-size: 1rem; border-color:#00ff88; background:rgba(0,255,136,0.1);">
                                        🎲 <?= __('Claim Daily Reward') ?>
                                    </button>
                                    <div id="daily-result" style="margin-top: 1rem; display: none; text-align: center; color: #00ff88; font-family: 'Share Tech Mono', monospace;"></div>
                                </div>
                            </div>

                            <!-- Player's Active Bounties Board (Native) -->
                            <div style="border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 10, 20, 0.4); padding: 1.5rem; border-radius: 8px; margin-bottom:2rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                    <h3 style="color:#00ffff; font-family:'Share Tech Mono', monospace; margin:0;"><i class="fas fa-crosshairs animate-pulse" style="color:#00ffff; margin-right:8px;"></i><?= __('Your Bounties') ?></h3>
                                    <a href="missions.php" class="dl-btn" style="text-decoration:none; padding:4px 12px; font-size:0.7rem; border-color:rgba(0,255,255,0.3); color:#00ffff;"><i class="fas fa-bullseye"></i> <?= __('Full Board') ?></a>
                                </div>
                                
                                <!-- Bounty status bar -->
                                <div id="bounty-stats-bar" style="display:flex; gap:10px; margin-bottom:1rem;">
                                    <span style="font-size:0.75rem; color:#888; font-family:'Share Tech Mono',monospace;">
                                        <i class="fas fa-spinner fa-spin"></i> <?= __('Loading bounties...') ?>
                                    </span>
                                </div>

                                <!-- Alert/success banner for bounty actions -->
                                <div id="bounty-action-alert" style="display:none; padding:8px 12px; border-radius:6px; margin-bottom:12px; font-size:0.8rem; font-family:'Share Tech Mono',monospace;"></div>

                                <!-- Bounty cards container -->
                                <div id="bounty-cards-container" style="display:flex; flex-direction:column; gap:0.75rem;">
                                    <div class="skeleton" style="height:100px; border-radius:8px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Ragol Chat -->
                <div id="tab-chat" class="dashboard-tab-pane">
                    <div style="border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 10, 20, 0.5); padding: 1.5rem; border-radius: 8px;">
                        <h3 style="color:#00ffff; font-family:'Share Tech Mono', monospace; margin-top:0; border-bottom:1px solid rgba(0,255,255,0.2); padding-bottom:8px; margin-bottom:12px;"><i class="fas fa-terminal animate-pulse" style="color:#00ffff; margin-right:8px;"></i><?= __('Web-to-Game Chat Console') ?></h3>
                        <p style="font-size:0.85rem; color:rgba(255,255,255,0.7); margin-bottom:15px;"><?= __('Send chat messages directly to your active in-game character\'s lobby or game block! Live lobby feed shows player joins, leaves, and room changes.') ?></p>
                        
                        <!-- Live Lobby Status Header -->
                        <div id="chat-lobby-header" style="padding:10px 14px; margin-bottom:10px; background:rgba(0,20,40,0.6); border:1px solid rgba(0,255,255,0.15); border-radius:6px; font-family:'Share Tech Mono',monospace; font-size:0.85rem; color:rgba(255,255,255,0.6); min-height:30px;">
                            <span style="color:#888;"><i class="fas fa-satellite-dish"></i> <?= __('Connecting to lobby feed...') ?></span>
                        </div>

                        <div class="chat-messages-log" id="chat-messages-log" style="margin-bottom:15px;">
                            <div class="chat-message-bubble system"><?= __('SYSTEM: Live lobby console active. Your messages appear in-game, and you\'ll see player joins/leaves here.') ?></div>
                        </div>
                        
                        <div class="chat-input-row" style="flex-direction:column; gap:10px;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <label style="font-size:0.85rem; color:#aaa; font-family:'Share Tech Mono', monospace; white-space:nowrap;"><?= __('Message From:') ?></label>
                                <select id="chat-character-select" style="flex:1; padding: 8px; background: rgba(0, 0, 0, 0.5); color: #fff; border: 1px solid rgba(0, 255, 255, 0.3); border-radius: 4px; font-family:'Share Tech Mono', monospace;">
                                    <!-- Populated via JS characters -->
                                    <option value=""><?= __('Select Character') ?></option>
                                </select>
                            </div>
                            
                            <div style="display:flex; gap:10px;">
                                <input type="text" id="chat-message-input" placeholder="<?= __('Type message to game (max 200 chars)...') ?>" maxlength="200" style="flex:1; padding: 10px; background: rgba(0,0,0,0.8); border: 1px solid rgba(0,255,255,0.3); color:#fff; border-radius:4px; font-size:0.95rem;">
                                <button onclick="sendWebToGameMessage()" id="chat-send-btn" class="dl-btn chat-send-btn"><i class="fas fa-paper-plane"></i> <?= __('Send') ?></button>
                            </div>
                            
                            <div id="chat-status-message" style="display:none; font-weight:bold; font-size:0.85rem; margin-top:5px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab 5: Settings -->
                <div id="tab-settings" class="dashboard-tab-pane">
                    <div class="dashboard-grid-settings">
                        <!-- Profile & Preferences -->
                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            <!-- System mail preferences toggle -->
                            <div class="switch-container">
                                <div class="switch-label-block">
                                    <h4><?= __('In-Game System Mail') ?></h4>
                                    <p><?= __('Receive Simple Mail notifications for bounties directly in-game. Uncheck to suppress.') ?></p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="system-mail-toggle" onchange="toggleSystemMailPref()">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <!-- Discord streak DM preferences toggle -->
                            <div class="switch-container">
                                <div class="switch-label-block">
                                    <h4><?= __('Discord Streak Alerts') ?></h4>
                                    <p><?= __('Receive Discord DM alerts when your login streak is about to expire. Uncheck to disable.') ?></p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="discord-streak-toggle" onchange="toggleDiscordStreakPref()">
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <!-- Display alias name -->
                            <div style="padding: 15px; border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 10, 20, 0.4); border-radius: 8px;">
                                <h4 style="margin-top: 0; color: #00ffff; font-family:'Share Tech Mono',monospace;"><?= __('Leaderboard Display Alias') ?></h4>
                                <p style="font-size:0.8rem; color:#aaa; margin:0 0 10px 0;"><?= __('Set a customized alias (2-20 characters) to represent you on public leaderboards instead of your Guild Card ID.') ?></p>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" id="display-name-input" placeholder="<?= __('Enter alias (2-20 chars)') ?>" maxlength="20" style="flex: 1; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); color: #fff; border-radius: 4px; font-family: 'Share Tech Mono', monospace;">
                                    <button onclick="saveDisplayName()" id="btn-save-alias" class="dl-btn" style="padding: 8px 16px; border-color: #00ffff; background: rgba(0,255,255,0.15); color: #00ffff; white-space: nowrap;"><?= __('Save') ?></button>
                                </div>
                                <div id="alias-message" style="margin-top: 6px; font-size: 0.85em; display: none;"></div>
                            </div>
                        </div>

                        <!-- Integrations & Danger zone -->
                        <div style="display:flex; flex-direction:column; gap:1.5rem;">
                            <div style="padding: 15px; border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 10, 20, 0.4); border-radius: 8px;">
                                <h4 style="margin-top: 0; color: #00ffff; font-family:'Share Tech Mono',monospace;"><?= __('Integrations') ?></h4>
                                <div id="discord-integration-container">
                                    <a id="btn-link-discord" href="/api/discord_auth.php" class="dl-btn" style="width:100%; display:block; text-align:center; text-decoration:none; box-sizing:border-box;"><i class="fab fa-discord"></i> <?= __('Sign in with Discord') ?></a>
                                    <div id="discord-linked-info" style="display: none; padding: 10px; border: 1px solid #5865F2; background: rgba(88, 101, 242, 0.1); border-radius: 4px; text-align: center; color: #fff;">
                                        <span style="display:block; margin-bottom: 5px;"><?= __('Discord Linked') ?> <i class="fas fa-check-circle" style="color: #00C851;"></i></span>
                                        <a href="/api/discord_unlink.php" style="color: #ff4444; font-size: 0.85em; text-decoration: underline;"><?= __('Unlink') ?></a>
                                    </div>
                                </div>
                            </div>

                            <div style="padding: 15px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0, 10, 20, 0.4); border-radius: 8px;">
                                <h4 style="margin-top: 0; color: #fff; font-family:'Share Tech Mono',monospace;"><?= __('Account Actions & Security') ?></h4>
                                <button onclick="requestChangePassword()" class="dl-btn" style="width:100%; margin-bottom: 1rem; box-sizing: border-box;"><i class="fas fa-key"></i> <?= __('Change Password') ?></button>
                                
                                <h4 style="margin-top: 15px; color: #ff4444; font-family:'Share Tech Mono',monospace;"><?= __('Danger Zone') ?></h4>
                                <button onclick="requestDeleteAccount()" class="dl-btn" style="width:100%; border-color:#ff4444; color:#ff4444; background:rgba(255, 68, 68, 0.1); box-sizing: border-box;"><i class="fas fa-user-slash"></i> <?= __('Delete Account') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 6: LFG Terminal (Native) -->
                <div id="tab-lfg" class="dashboard-tab-pane">
                    <!-- Alert banner -->
                    <div id="lfg-alert" style="display:none; padding:10px 15px; border-radius:6px; margin-bottom:1rem; font-size:0.85rem; font-family:'Share Tech Mono', monospace;"></div>

                    <!-- Character sync + quick post (compact row) -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                        <!-- Character Status -->
                        <div style="border:1px solid rgba(255,170,0,0.2); background:rgba(0,10,20,0.5); border-radius:8px; padding:1rem;">
                            <h4 style="margin:0 0 8px; color:#ffaa00; font-family:'Share Tech Mono',monospace; font-size:0.85rem;"><i class="fas fa-satellite-dish"></i> <?= __('CHARACTER STATUS') ?></h4>
                            <div id="lfg-char-sync" style="font-size:0.85rem; color:#888;"><?= __('Synchronizing...') ?></div>
                        </div>
                        <!-- Quick Post -->
                        <div style="border:1px solid rgba(255,170,0,0.2); background:rgba(0,10,20,0.5); border-radius:8px; padding:1rem;">
                            <h4 style="margin:0 0 8px; color:#ffaa00; font-family:'Share Tech Mono',monospace; font-size:0.85rem;"><i class="fas fa-plus-circle"></i> <?= __('QUICK POST') ?></h4>
                            <div style="display:flex; gap:8px;">
                                <input type="text" id="lfg-quick-desc" placeholder="<?= __('e.g. Running TTF Ultimate, need FO') ?>" maxlength="250" style="flex:1; padding:8px; background:rgba(0,0,0,0.5); border:1px solid rgba(255,170,0,0.3); color:#fff; border-radius:4px; font-size:0.85rem;">
                                <button id="lfg-quick-post-btn" onclick="window.portalLfgPost()" class="dl-btn" style="border-color:#ffaa00; color:#ffaa00; background:rgba(255,170,0,0.1); padding:8px 14px; white-space:nowrap; font-size:0.8rem;" disabled>
                                    <i class="fas fa-lock"></i> <?= __('Post') ?>
                                </button>
                            </div>
                            <div style="display:flex; gap:6px; margin-top:8px;">
                                <label style="font-size:0.7rem; color:#aaa; display:flex; align-items:center; gap:3px; cursor:pointer;">
                                    <input type="checkbox" class="lfg-seek-check" value="HU" checked style="accent-color:#ff4444;"> <span style="color:#ff6666;">HU</span>
                                </label>
                                <label style="font-size:0.7rem; color:#aaa; display:flex; align-items:center; gap:3px; cursor:pointer;">
                                    <input type="checkbox" class="lfg-seek-check" value="RA" checked style="accent-color:#44ff44;"> <span style="color:#66ff66;">RA</span>
                                </label>
                                <label style="font-size:0.7rem; color:#aaa; display:flex; align-items:center; gap:3px; cursor:pointer;">
                                    <input type="checkbox" class="lfg-seek-check" value="FO" checked style="accent-color:#4488ff;"> <span style="color:#6699ff;">FO</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Active Groups Feed -->
                    <div style="border:1px solid rgba(255,170,0,0.2); background:rgba(0,10,20,0.4); border-radius:8px; padding:1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h3 style="margin:0; color:#ffaa00; font-family:'Share Tech Mono',monospace; font-size:0.95rem;"><i class="fas fa-users"></i> <?= __('ACTIVE GROUPS') ?> <span id="lfg-count-badge" style="display:inline-block; background:rgba(255,170,0,0.15); border:1px solid rgba(255,170,0,0.3); color:#ffaa00; padding:1px 8px; border-radius:10px; font-size:0.7rem; margin-left:6px;">0</span></h3>
                            <button onclick="window.portalLfgRefresh()" class="dl-btn" style="padding:4px 10px; font-size:0.7rem; border-color:rgba(255,170,0,0.3); color:#ffaa00;"><i class="fas fa-sync-alt"></i></button>
                        </div>
                        <div id="lfg-listings-feed" style="display:flex; flex-direction:column; gap:0.75rem;">
                            <div class="skeleton" style="height:80px; border-radius:8px;"></div>
                            <div class="skeleton" style="height:80px; border-radius:8px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab 7: Drop Chart (Native) -->
                <div id="tab-drops" class="dashboard-tab-pane">
                    <div style="border:1px solid rgba(0,255,200,0.2); background:rgba(0,10,20,0.4); border-radius:8px; padding:1rem;">
                        <h3 style="margin:0 0 12px; color:#00ffc8; font-family:'Share Tech Mono',monospace;"><i class="fas fa-dice-d20"></i> <?= __('Drop Chart') ?></h3>

                        <!-- Filters row -->
                        <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:1rem;">
                            <select id="drops-episode" style="flex:1; min-width:120px; padding:8px; background:rgba(0,0,0,0.5); border:1px solid rgba(0,255,200,0.3); color:#fff; border-radius:4px; font-family:'Share Tech Mono',monospace;">
                                <option value="Episode_1"><?= __('Episode 1') ?></option>
                                <option value="Episode_2"><?= __('Episode 2') ?></option>
                                <option value="Episode_4"><?= __('Episode 4') ?></option>
                            </select>
                            <select id="drops-difficulty" style="flex:1; min-width:120px; padding:8px; background:rgba(0,0,0,0.5); border:1px solid rgba(0,255,200,0.3); color:#fff; border-radius:4px; font-family:'Share Tech Mono',monospace;">
                                <option value="Normal"><?= __('Normal') ?></option>
                                <option value="Hard"><?= __('Hard') ?></option>
                                <option value="VHard"><?= __('Very Hard') ?></option>
                                <option value="Ultimate" selected><?= __('Ultimate') ?></option>
                            </select>
                            <select id="drops-section-id" style="flex:1; min-width:120px; padding:8px; background:rgba(0,0,0,0.5); border:1px solid rgba(0,255,200,0.3); color:#fff; border-radius:4px; font-family:'Share Tech Mono',monospace;">
                                <option value="Viridia">Viridia</option>
                                <option value="Greenill">Greenill</option>
                                <option value="Skyly">Skyly</option>
                                <option value="Bluefull">Bluefull</option>
                                <option value="Purplenum">Purplenum</option>
                                <option value="Pinkal">Pinkal</option>
                                <option value="Redria">Redria</option>
                                <option value="Oran">Oran</option>
                                <option value="Yellowboze">Yellowboze</option>
                                <option value="Whitill">Whitill</option>
                            </select>
                            <button onclick="window.portalLoadDrops()" class="dl-btn" style="padding:8px 16px; border-color:#00ffc8; color:#00ffc8; background:rgba(0,255,200,0.1); font-family:'Share Tech Mono',monospace;">
                                <i class="fas fa-search"></i> <?= __('Search') ?>
                            </button>
                        </div>

                        <!-- Active character section ID hint -->
                        <div id="drops-char-hint" style="display:none; margin-bottom:12px; padding:8px 12px; background:rgba(0,255,200,0.06); border:1px solid rgba(0,255,200,0.15); border-radius:6px; font-size:0.8rem; color:#00ffc8;">
                        </div>

                        <!-- Drops table -->
                        <div id="drops-table-container" style="overflow-x:auto;">
                            <p style="color:#888; font-family:'Share Tech Mono',monospace; text-align:center; padding:2rem;"><?= __('Select filters and press Search to load drop tables.') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Level Milestone Crate Claim Modal -->
                <div id="claim-modal" class="modal" style="display: none;">
                    <div class="modal-content" style="border-color: #00ffff; box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);">
                        <span class="close-modal" onclick="document.getElementById('claim-modal').style.display='none'">&times;</span>
                        <h2 id="modal-title" style="font-family: 'Share Tech Mono', 'Segoe UI', monospace; color: #00ffff; margin-top:0; border-bottom:1px solid rgba(0,255,255,0.2); padding-bottom:8px;"><?= __('Claim Level') ?> <span id="modal-level"></span> <?= __('Reward') ?></h2>
                        <p style="margin-top: 1rem; margin-bottom: 1.5rem; color: rgba(255, 255, 255, 0.7); font-size:0.9rem;"><?= __('Select your preferred reward category below. The item will be dropped instantly beside your character in-game!') ?></p>
                        
                        <div class="reward-options">
                            <button class="dl-btn claim-category-btn" data-category="Weapon" style="width: 100%; border-color: #ff4444; background: rgba(255, 68, 68, 0.15); color: #ffaaaa; font-weight:bold; font-family:'Share Tech Mono',monospace; padding:10px;"><?= __('Weapon Package') ?></button>
                            <button class="dl-btn claim-category-btn" data-category="Armor" style="width: 100%; border-color: #33b5e5; background: rgba(51, 181, 229, 0.15); color: #aaddff; font-weight:bold; font-family:'Share Tech Mono',monospace; padding:10px;"><?= __('Armor / Frame (4 Slots)') ?></button>
                            <button class="dl-btn claim-category-btn" data-category="Shield" style="width: 100%; border-color: #33b5e5; background: rgba(51, 181, 229, 0.15); color: #aaddff; font-weight:bold; font-family:'Share Tech Mono',monospace; padding:10px;"><?= __('Shield / Barrier') ?></button>
                            <button class="dl-btn claim-category-btn" data-category="Mag" style="width: 100%; border-color: #00c8c8; background: rgba(0, 200, 200, 0.15); color: #80f0f0; font-weight:bold; font-family:'Share Tech Mono',monospace; padding:10px;"><?= __('Rare custom Mag (1x)') ?></button>
                            <button class="dl-btn claim-category-btn" data-category="Random" style="width: 100%; border-color: #00C851; background: rgba(0, 200, 81, 0.15); color: #aaffaa; font-weight:bold; font-family:'Share Tech Mono',monospace; padding:10px;"><?= __('Utility / Tools (3x Drops)') ?></button>
                        </div>
                        
                        <div id="modal-error" style="color: #ff4444; margin-top: 1rem; display: none; font-weight:bold;"></div>
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

                <!-- Change Password Modal -->
                <div id="change-pass-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
                    <div style="background: #1a1a1a; padding: 2rem; border-radius: 8px; border: 1px solid #00ffff; max-width: 400px; width: 90%; box-shadow: 0 0 20px rgba(0, 255, 255, 0.2);">
                        <h3 style="color: #fff; margin-top:0; font-family:'Share Tech Mono',monospace;"><?= __('Change Password') ?></h3>
                        
                        <input type="password" id="cp-old" placeholder="<?= __('Current Password') ?>" style="width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #444; color: #fff; border-radius:4px;">
                        <input type="password" id="cp-new" placeholder="<?= __('New Password') ?>" style="width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #444; color: #fff; border-radius:4px;">
                        <input type="password" id="cp-confirm" placeholder="<?= __('Confirm New Password') ?>" style="width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #444; color: #fff; border-radius:4px;">
                        
                        <div id="cp-error" style="color: #ff4444; display: none; margin-bottom: 1rem; font-weight:bold;"></div>
                        <div id="cp-success" style="color: #00C851; display: none; margin-bottom: 1rem; font-weight:bold;"></div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="closeChangePassModal()" class="dl-btn" style="background: rgba(255,255,255,0.1); border-color: #555;"><?= __('Cancel') ?></button>
                            <button onclick="confirmChangePass()" id="btn-confirm-cp" class="dl-btn" style="background: rgba(0, 255, 255, 0.15); border-color: #00ffff; color: white;"><?= __('Update') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div id="delete-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
                    <div style="background: #1a1a1a; padding: 2rem; border-radius: 8px; border: 1px solid #ff4444; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 0 20px rgba(255,0,0,0.2);">
                        <h3 style="color: #ff4444; margin-top:0; font-family:'Share Tech Mono',monospace;"><?= __('Delete Account') ?></h3>
                        <p><?= __('Are you sure you want to delete your account? This action cannot be undone.') ?></p>
                        <p style="margin-bottom: 1.5rem;"><?= __('Please enter your password to confirm:') ?></p>
                        
                        <input type="password" id="delete-confirm-password" placeholder="<?= __('Password') ?>" style="width: 100%; padding: 10px; margin-bottom: 1rem; background: #000; border: 1px solid #444; color: #fff; border-radius:4px;">
                        <div id="delete-error" style="color: #ff4444; display: none; margin-bottom: 1rem; font-weight:bold;"></div>
                        
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button onclick="closeDeleteModal()" class="dl-btn" style="background: rgba(255,255,255,0.1); border-color: #555;"><?= __('Cancel') ?></button>
                            <button onclick="confirmDelete()" id="btn-confirm-delete" class="dl-btn" style="background: rgba(255,0,0,0.1); border-color: #ff4444; color: #ff4444;"><?= __('Confirm Delete') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Player Guide Modal -->
                <div id="player-guide-modal">
                    <div class="guide-modal-dialog">
                        <button onclick="closePlayerGuideModal()" style="position: absolute; top: 15px; right: 20px; background: transparent; border: none; color: #00ffff; font-size: 1.5rem; cursor: pointer; transition: all 0.2s; z-index: 10;"><i class="fas fa-times"></i></button>

                        <h2 style="color: #00ffff; margin-top:0; font-family: 'Share Tech Mono', monospace; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid rgba(0, 255, 255, 0.2); padding-bottom: 10px; margin-bottom: 1rem;">
                            <i class="fas fa-terminal animate-pulse"></i> <?= __('PSOBB HUNTER\'S DATABASE & PORTAL GUIDE') ?>
                        </h2>

                        <div style="display: flex; gap: 5px; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <button class="guide-tab-btn active" onclick="switchGuideTab('tab-portal')" data-tab="tab-portal"><?= __('PORTAL MANAGEMENT') ?></button>
                            <button class="guide-tab-btn" onclick="switchGuideTab('tab-lfg')" data-tab="tab-lfg"><?= __('LFG COORDINATION') ?></button>
                            <button class="guide-tab-btn" onclick="switchGuideTab('tab-drops')" data-tab="tab-drops"><?= __('DYNAMIC DROP CHARTS') ?></button>
                            <button class="guide-tab-btn" onclick="switchGuideTab('tab-commands')" data-tab="tab-commands"><?= __('IN-GAME COMMANDS') ?></button>
                        </div>

                        <div id="guide-modal-content" style="flex: 1; overflow-y: auto; padding-right: 10px;">
                            <div id="tab-portal" class="guide-tab-pane">
                                <div class="guide-section-grid">
                                    <div class="guide-card-glass">
                                        <h3><i class="fas fa-university"></i> <?= __('Character & Bank Swapping') ?></h3>
                                        <p><strong><?= __('Bank Management:') ?></strong> <?= __('Swap your inventory bank container on the fly using the pre-selector dropdown. Switch to the Shared Bank or any character bank (Character 1-20).') ?></p>
                                        <p style="color: #ffaa00; font-size: 0.85em; margin-top: 5px;"><i class="fas fa-exclamation-triangle"></i> <?= __('Note: Your character must be online in-game but NOT currently standing at the bank counter, and not in Battle or Challenge mode, to successfully swap banks.') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Section ID Pre-selector:') ?></strong> <?= __('Change your drop Section ID pre-selector before launching games. Only characters level 50 and below are permitted to modify their Section ID.') ?></p>
                                    </div>
                                    <div class="guide-card-glass">
                                        <h3><i class="fas fa-users-cog"></i> <?= __('Profile & Account Actions') ?></h3>
                                        <p><strong><?= __('Leaderboard Display Name:') ?></strong> <?= __('Set a customized alias (2-20 characters) in your profile actions. This alias will represent your hunter on the public leaderboards instead of your account ID.') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Discord Integration:') ?></strong> <?= __('Link your Discord account under Integrations to enable secure instant login, community telemetry sync, and guild notification broadcasts.') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Level Milestones:') ?></strong> <?= __('Check the Level Rewards panel to claim exclusive gifts as your characters reach crucial level milestones on the server!') ?></p>
                                    </div>
                                </div>
                                
                                <div class="guide-card-glass" style="margin-bottom: 0;">
                                    <h3><i class="fas fa-crosshairs"></i> <?= __('Hunter\'s Guild Bounty Board & Events') ?></h3>
                                    <p><strong><?= __('Bounty Board & Personal Quests:') ?></strong> <?= __('Accept custom-tailored personal bounties from the Hunters Guild Bounty Board. Complete target goals in-game to unlock rare items and meseta. Completed bounties will appear in your Guild Claim Center on the website to claim!') ?></p>
                                    <p style="margin-top: 10px;"><strong><?= __('Cooperative Server Events:') ?></strong> <?= __('Collaborate server-wide during active community events to pool points. Event rewards feature high-end rare drops tailored to your character\'s class and level at the moment of claiming.') ?></p>
                                    <div style="background: rgba(255, 170, 0, 0.08); border: 1px solid rgba(255, 170, 0, 0.2); padding: 12px; border-radius: 6px; margin-top: 10px;">
                                        <strong style="color: #ffaa00; display: block; margin-bottom: 6px;"><i class="fas fa-gift"></i> <?= __('Dynamic Reward Scaling Tiers:') ?></strong>
                                        <ul style="margin: 0; padding-left: 20px; font-size: 0.85em; line-height: 1.5; color: rgba(255,255,255,0.95);">
                                            <li><strong><?= __('Base Tier:') ?></strong> <?= __('1x Class-Fit Rare Drop + 5,000 Meseta (0+ points).') ?></li>
                                            <li><strong><?= __('Escalation:') ?></strong> <?= __('+1 Rare Drop & +5,000 Meseta for every 50 contribution points.') ?></li>
                                            <li><strong><?= __('Ultimate Cap:') ?></strong> <?= __('Up to a massive 10x Rare Drops + 50,000 Meseta (at 450+ points).') ?></li>
                                            <li><strong><?= __('Top 3 Champions:') ?></strong> <?= __('The top 3 event contributors receive a grand 100,000 Meseta prize and a prestigious choice of bonus rare items!') ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div id="tab-lfg" class="guide-tab-pane" style="display:none;">
                                <div class="guide-section-grid">
                                    <div class="guide-card-glass">
                                        <h3><i class="fas fa-satellite-dish"></i> <?= __('LFG Creation & Syncing') ?></h3>
                                        <p><strong><?= __('Live Character Syncing:') ?></strong> <?= __('The LFG Terminal synchronizes with the server in real-time, displaying your current online status, active character class, level, and active game lobby ID.') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Creating LFG Posts:') ?></strong> <?= __('Define the mission or objectives you are pursuing. Select which class archetypes you seek (Hunters HU, Rangers RA, Forces FO), and link one of your active Bounty Board quests so others know what you are hunting!') ?></p>
                                    </div>
                                    <div class="guide-card-glass">
                                        <h3><i class="fas fa-space-shuttle"></i> <?= __('Teleportation & Group Controls') ?></h3>
                                        <p><strong><?= __('Warp Direct Teleportation:') ?></strong> <?= __('Find a group seeking your character class? If your level meets the room requirement, click the glowing cyan') ?> <strong style="color: var(--pso-blue);"><i class="fas fa-rocket"></i> <?= __('Warp Direct') ?></strong> <?= __('button on the LFG dashboard. The game server will instantly transition your active character directly into their room in-game!') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Leaving a Group:') ?></strong> <?= __('Need to return to public lobbies? Simply click the') ?> <strong style="color: #ff4444;"><i class="fas fa-sign-out-alt"></i> <?= __('Leave Group') ?></strong> <?= __('button to warp your active character back to public Pioneer 2 lobbies gracefully.') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div id="tab-drops" class="guide-tab-pane" style="display:none;">
                                <div class="guide-section-grid">
                                    <div class="guide-card-glass">
                                        <h3><i class="fas fa-search"></i> <?= __('Search, Filter & Sort') ?></h3>
                                        <p><strong><?= __('Live Server Synchronization:') ?></strong> <?= __('Our drop database fetches rates directly from active server game data files. If multipliers change or drops are updated, rates in the chart adjust instantly and are 100% accurate.') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Dynamic Filters:') ?></strong> <?= __('Filter drops by Episode (EP1, EP2, EP4) and Difficulty (Normal, Hard, Very Hard, Ultimate).') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Target Search:') ?></strong> <?= __('Search by item names (e.g., Heavenly/Battle) or monster names (e.g., Tollaw) to find exact drop rates.') ?></p>
                                    </div>
                                    <div class="guide-card-glass">
                                        <h3><i class="fas fa-shapes"></i> <?= __('Class Compatibility & Section IDs') ?></h3>
                                        <p><strong><?= __('Class Specific Filtering:') ?></strong> <?= __('Toggle class tags (HUmar, FOnewearl, RAcast, etc.) to view only items usable by your class.') ?></p>
                                        <p style="margin-top: 10px;"><strong><?= __('Section ID Mechanics:') ?></strong> <?= __('In PSOBB, rare drops are determined solely by the Section ID of the') ?> <strong><?= __('Room Creator') ?></strong> <?= __('(game leader). Coordinate your party\'s Section ID before generating the game to ensure the monsters drop the items you seek!') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div id="tab-commands" class="guide-tab-pane" style="display:none;">
                                <div class="guide-card-glass" style="margin-bottom: 1.5rem;">
                                    <h3><i class="fas fa-terminal"></i> <?= __('General & Utility Commands') ?></h3>
                                    <p style="margin-bottom: 10px; font-size: 0.9em; opacity: 0.8;"><?= __('Type these commands in the in-game chat to retrieve server telemetry, details, and adjustments:') ?></p>
                                    
                                    <div class="command-row">
                                        <span class="command-name">$ping</span>
                                        <span class="command-desc"><?= __('Check your latency to the server.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$li</span>
                                        <span class="command-desc"><?= __('Display current lobby information and active room details.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$si</span>
                                        <span class="command-desc"><?= __('Get global server telemetry and active player counts.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$where</span>
                                        <span class="command-desc"><?= __('Print the exact coordinates of all players on your current floor.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$what</span>
                                        <span class="command-desc"><?= __('Identify the exact specs and attributes of an item on the floor near you.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$arrow [color]</span>
                                        <span class="command-desc"><?= __('Change lobby arrow indicator (red, blue, green, yellow, purple, cyan, white, black, etc.).') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$song [id]</span>
                                        <span class="command-desc"><?= __('Change the lobby background jukebox song (lobby only).') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$announcerares</span>
                                        <span class="command-desc"><?= __('Toggles global broadcast announcements when you find rare items.') ?></span>
                                    </div>
                                </div>

                                <div class="guide-card-glass">
                                    <h3><i class="fas fa-user-shield"></i> <?= __('Character Statistics & CAP Checks') ?></h3>
                                    <p style="margin-bottom: 10px; font-size: 0.9em; opacity: 0.8;"><?= __('Track materials consumed, force save, swap banks, or count rare weapon kills:') ?></p>
                                    
                                    <div class="command-row">
                                        <span class="command-name">$bank [index]</span>
                                        <span class="command-desc"><?= __('Swap inventory bank on the fly! $bank 0 for Shared Bank, $bank 1-127 for Character Banks.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$save</span>
                                        <span class="command-desc"><?= __('Force save your character state to the server database.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$checkchar</span>
                                        <span class="command-desc"><?= __('List character slots on your account, indicating which are used or free.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$matcount</span>
                                        <span class="command-desc"><?= __('Tally all consumed Stat Materials (Power, Mind, HP, TP, Evade, Def, Luck) and progress toward caps.') ?></span>
                                    </div>
                                    <div class="command-row">
                                        <span class="command-name">$killcount</span>
                                        <span class="command-desc"><?= __('View exact monster kill progress for equipped sealed rare weapons (e.g. Sealed J-Sword).') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1rem; border-top: 1px solid rgba(0, 255, 255, 0.2); padding-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 0.8em; opacity: 0.7; font-family: 'Share Tech Mono', monospace;">
                            <span><?= __('STATUS: ONLINE // DATABASE SECURE // THANK YOU FOR PLAYING!') ?></span>
                            <button type="button" onclick="closePlayerGuideModal()" class="dl-btn" style="padding: 4px 12px; font-size: 0.75rem; border-color: rgba(0, 255, 255, 0.5); font-weight: bold; background: rgba(0,255,255,0.05); color: #00ffff;"><?= __('CLOSE GUIDE') ?></button>
                        </div>
                    </div>
                </div>
            </div>
    </main>

<?php include 'includes/footer.php'; ?>
