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
                        <!-- Rewards Button -->
                        <a id="rewards-panel-btn" href="unlocks" class="dl-btn" style="display:none; text-align:center; width: 100%; box-sizing: border-box;"><?= __('🎁 Claim Level Rewards') ?></a>
        
                        <!-- Bounty Board Button -->
                        <a id="bounty-board-btn" href="missions.php" class="dl-btn" style="display:none; text-align:center; width: 100%; box-sizing: border-box;"><?= __('🎯 Hunter\'s Guild Bounty Board') ?></a>
        
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
            </div>
    </main>

<?php include 'includes/footer.php'; ?>
