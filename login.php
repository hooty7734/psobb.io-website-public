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
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h2><?= __('Welcome,') ?> <span id="dash-username-header">Hunter</span></h2>
                    <button onclick="logout()" class="dl-btn" style="border: 1px solid #00ffff; color: #00ffff; background: rgba(0, 255, 255, 0.1); padding: 5px 15px; box-shadow: 0 0 5px rgba(0, 255, 255, 0.2);"><?= __('Logout') ?></button>
                </div>
                
                <div class="dashboard-content-grid">
                    <!-- Left Column: Cards and Modifiers -->
                    <div class="dashboard-col-left" style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Hunter's License Card -->
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
        
                            </div>
                            <div class="hl-footer">
                                <span class="hl-status"><?= __('STATUS: ACTIVE') ?></span>
                                <img src="img/favicon.svg" class="hl-logo-sm" alt="logo">
                            </div>
                        </div>
        
                        <!-- Bank Swap Container -->
                        <div id="bank-swap-container" style="padding: 1rem; border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 255, 255, 0.05); border-radius: 8px;">
                            <!-- Content injected via JS -->
                        </div>
                    </div>

                    <!-- Center Column: Section ID -->
                    <div class="dashboard-col-center">
                        <!-- Section ID Container -->
                        <div id="section-id-change-container" style="padding: 1rem; border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 255, 255, 0.05); border-radius: 8px;">
                            <!-- Content injected via JS -->
                        </div>
                    </div>

                    <!-- Right Column: Actions and Integrations -->
                    <div class="dashboard-col-right" style="display: flex; flex-direction: column; gap: 1rem;">
                        <!-- Player Guide Button -->
                        <button id="player-guide-btn" onclick="openPlayerGuideModal()" class="dl-btn" style="display:block; text-align:center; width: 100%; box-sizing: border-box; border-color: #00ffff; background: rgba(0, 255, 255, 0.15); color: #00ffff; margin-bottom: 0.5rem; font-family: 'Share Tech Mono', monospace; font-weight: bold;">
                            <i class="fas fa-book-open"></i> <?= __('📖 Player Guide & Commands') ?>
                        </button>

                        <!-- Rewards Button -->
                        <a id="rewards-panel-btn" href="unlocks" class="dl-btn" style="display:none; text-align:center; width: 100%; box-sizing: border-box;"><?= __('🎁 Claim Level Rewards') ?></a>
        
                        <!-- Bounty Board Button -->
                        <a id="bounty-board-btn" href="missions.php" class="dl-btn" style="display:none; text-align:center; width: 100%; box-sizing: border-box;"><?= __('🎯 Hunter\'s Guild Bounty Board') ?></a>
        
                        <!-- Looking for Group Button -->
                        <a id="lfg-panel-btn" href="lfg.php" class="dl-btn" style="display:none; text-align:center; width: 100%; box-sizing: border-box; border-color: #ffaa00; background: rgba(255, 170, 0, 0.15); color: #ffaa00;">
                            <i class="fas fa-users"></i> <?= __('🤝 Looking for Group') ?>
                        </a>

                        <!-- Admin Button -->
                        <a id="admin-panel-btn" href="/admin/dashboard" class="dl-btn" style="display:none; text-align:center; width: 100%; box-sizing: border-box;"><?= __('Open Admin Panel') ?></a>
        
                        <!-- Account Actions -->
                        <div style="padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 0.5rem;">
                            <div id="integrations-container">
                                <p style="font-size: 0.9em; opacity: 0.7; margin-bottom: 0.5rem;"><?= __('Integrations') ?></p>
                                <div id="discord-integration-container" style="margin-bottom: 1rem;">
                                    <a id="btn-link-discord" href="/api/discord_auth.php" class="dl-btn" style="width:100%; display:block; text-align:center; text-decoration:none; box-sizing:border-box;"><i class="fab fa-discord"></i> <?= __('Sign in with Discord') ?></a>
                                    <div id="discord-linked-info" style="display: none; padding: 10px; border: 1px solid #5865F2; background: rgba(88, 101, 242, 0.1); border-radius: 4px; text-align: center; color: #fff;">
                                        <span style="display:block; margin-bottom: 5px;"><?= __('Discord Linked') ?> <i class="fas fa-check-circle" style="color: #00C851;"></i></span>
                                        <a href="/api/discord_unlink.php" style="color: #ff4444; font-size: 0.85em; text-decoration: underline;"><?= __('Unlink') ?></a>
                                    </div>
                                </div>
                            </div>
        
                            <p style="font-size: 0.9em; opacity: 0.7; margin-bottom: 0.5rem; margin-top: 1rem;"><?= __('Actions') ?></p>
                            <div style="margin-bottom: 1rem; padding: 12px; border: 1px solid rgba(0, 255, 255, 0.2); background: rgba(0, 255, 255, 0.05); border-radius: 6px;">
                                <label style="font-size: 0.85em; color: #aaa; display: block; margin-bottom: 6px;"><?= __('Leaderboard Display Name') ?></label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" id="display-name-input" placeholder="<?= __('Enter alias (2-20 chars)') ?>" maxlength="20" style="flex: 1; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,255,0.3); color: #fff; border-radius: 4px; font-family: 'Share Tech Mono', monospace;">
                                    <button onclick="saveDisplayName()" id="btn-save-alias" class="dl-btn" style="padding: 8px 16px; border-color: #00ffff; background: rgba(0,255,255,0.15); color: #00ffff; white-space: nowrap;"><?= __('Save') ?></button>
                                </div>
                                <div id="alias-message" style="margin-top: 6px; font-size: 0.85em; display: none;"></div>
                            </div>
                            <button onclick="requestChangePassword()" class="dl-btn" style="width:100%; margin-bottom: 1rem; box-sizing: border-box;"><?= __('Change Password') ?></button>
                            
                            <p style="font-size: 0.9em; opacity: 0.7; margin-bottom: 0.5rem; color:#ff4444; margin-top: 1rem;"><?= __('Danger Zone') ?></p>
                            <button onclick="requestDeleteAccount()" class="dl-btn" style="width:100%; box-sizing: border-box;"><?= __('Delete Account') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Change Password Modal -->
                <div id="change-pass-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
                    <div style="background: #1a1a1a; padding: 2rem; border-radius: 8px; border: 1px solid #7289da; max-width: 400px; width: 90%; box-shadow: 0 0 20px rgba(114, 137, 218, 0.2);">
                        <h3 style="color: #fff; margin-top:0;"><?= __('Change Password') ?></h3>
                        
                        <input type="password" id="cp-old" placeholder="<?= __('Current Password') ?>" style="width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #444; color: #fff;">
                        <input type="password" id="cp-new" placeholder="<?= __('New Password') ?>" style="width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #444; color: #fff;">
                        <input type="password" id="cp-confirm" placeholder="<?= __('Confirm New Password') ?>" style="width: 100%; padding: 10px; margin: 10px 0; background: #000; border: 1px solid #444; color: #fff;">
                        
                        <div id="cp-error" style="color: #ff4444; display: none; margin-bottom: 1rem;"></div>
                        <div id="cp-success" style="color: #00C851; display: none; margin-bottom: 1rem;"></div>
                        
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button onclick="closeChangePassModal()" class="dl-btn" style="background: rgba(255,255,255,0.1); border-color: #555;"><?= __('Cancel') ?></button>
                            <button onclick="confirmChangePass()" id="btn-confirm-cp" class="dl-btn" style="background: rgba(114, 137, 218, 0.2); border-color: #7289da; color: white;"><?= __('Update') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div id="delete-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
                    <div style="background: #1a1a1a; padding: 2rem; border-radius: 8px; border: 1px solid #ff4444; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 0 20px rgba(255,0,0,0.2);">
                        <h3 style="color: #ff4444; margin-top:0;"><?= __('Delete Account') ?></h3>
                        <p><?= __('Are you sure you want to delete your account? This action cannot be undone.') ?></p>
                        <p style="margin-bottom: 1.5rem;"><?= __('Please enter your password to confirm:') ?></p>
                        
                        <input type="password" id="delete-confirm-password" placeholder="<?= __('Password') ?>" style="width: 100%; padding: 10px; margin-bottom: 1rem; background: #000; border: 1px solid #444; color: #fff;">
                        <div id="delete-error" style="color: #ff4444; display: none; margin-bottom: 1rem;"></div>
                        
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button onclick="closeDeleteModal()" class="dl-btn" style="background: rgba(255,255,255,0.1); border-color: #555;"><?= __('Cancel') ?></button>
                            <button onclick="confirmDelete()" id="btn-confirm-delete" class="dl-btn" style="background: rgba(255,0,0,0.1); border-color: #ff4444; color: #ff4444;"><?= __('Confirm Delete') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Player Guide Modal -->
                <div id="player-guide-modal">
                    <div class="guide-modal-dialog">
                        <!-- Close Button -->
                        <button onclick="closePlayerGuideModal()" style="position: absolute; top: 15px; right: 20px; background: transparent; border: none; color: #00ffff; font-size: 1.5rem; cursor: pointer; transition: all 0.2s; z-index: 10;"><i class="fas fa-times"></i></button>

                        <!-- Modal Header -->
                        <h2 style="color: #00ffff; margin-top:0; font-family: 'Share Tech Mono', monospace; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid rgba(0, 255, 255, 0.2); padding-bottom: 10px; margin-bottom: 1rem;">
                            <i class="fas fa-terminal animate-pulse"></i> <?= __('PSOBB HUNTER\'S DATABASE & PORTAL GUIDE') ?>
                        </h2>

                        <!-- Tab Controls -->
                        <div style="display: flex; gap: 5px; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <button class="guide-tab-btn active" onclick="switchGuideTab('tab-portal')" data-tab="tab-portal"><?= __('PORTAL MANAGEMENT') ?></button>
                            <button class="guide-tab-btn" onclick="switchGuideTab('tab-lfg')" data-tab="tab-lfg"><?= __('LFG COORDINATION') ?></button>
                            <button class="guide-tab-btn" onclick="switchGuideTab('tab-drops')" data-tab="tab-drops"><?= __('DYNAMIC DROP CHARTS') ?></button>
                            <button class="guide-tab-btn" onclick="switchGuideTab('tab-commands')" data-tab="tab-commands"><?= __('IN-GAME COMMANDS') ?></button>
                        </div>

                        <!-- Tab Contents (Scrollable) -->
                        <div id="guide-modal-content" style="flex: 1; overflow-y: auto; padding-right: 10px;">
                            
                            <!-- Tab: Portal Management -->
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

                            <!-- Tab: LFG Coordination -->
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

                            <!-- Tab: Dynamic Drop Charts -->
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

                            <!-- Tab: In-Game Commands -->
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

                        <!-- Modal Footer -->
                        <div style="margin-top: 1rem; border-top: 1px solid rgba(0, 255, 255, 0.2); padding-top: 10px; display: flex; justify-content: space-between; align-items: center; font-size: 0.8em; opacity: 0.7; font-family: 'Share Tech Mono', monospace;">
                            <span><?= __('STATUS: ONLINE // DATABASE SECURE // THANK YOU FOR PLAYING!') ?></span>
                            <button type="button" onclick="closePlayerGuideModal()" class="dl-btn" style="padding: 4px 12px; font-size: 0.75rem; border-color: rgba(0, 255, 255, 0.5); font-weight: bold; background: rgba(0,255,255,0.05); color: #00ffff;"><?= __('CLOSE GUIDE') ?></button>
                        </div>
                    </div>
                </div>
            </div>
    </main>

<?php include 'includes/footer.php'; ?>
