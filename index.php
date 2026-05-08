<?php
/**
 * PSOBB Website: Homepage
 * 
 * The primary landing page for the public website.
 * Renders the hero banner, latest news/events, and the live server status sidebar.
 * Automatically connects to NewServ's API via JS to pull active player counts.
 */
$page_title = 'PSOBB Private Server';
$current_page = 'home';
include 'includes/header.php';
?>

    <main class="container">
        <section class="hero">
            <div class="logo-wrapper animate-fade-in delay-1">
                <img src="img/header_logo.png" alt="PSOBB.io - Phantasy Star Online Blue Burst" class="hero-logo">
            </div>
            <p class="hero-subtitle animate-fade-in delay-2"><?= __('Join the adventure in the ultimate private Phantasy Star Online BlueBurst server experience.') ?></p>
            <div class="cta-group animate-fade-in delay-3">
                <a href="downloads.php" class="dl-btn"><?= __('Play Now') ?></a>
                <a href="https://discord.gg/28s84HJXha" class="discord-btn"><?= __('Join Discord') ?></a>
            </div>
        </section>

        <div class="section-header">
            <h2><?= __('Updates') ?></h2>
        </div>

        <div class="layout-grid">
            <section class="main-content">
                <!-- AUTO-MISSION-START -->
                <article class="news-item animate-fade-in">
                    <div class="event-badge" style="background: #00ff88; color: #000; padding: 2px 8px; display: inline-block; border-radius: 4px; font-size: 0.8rem; margin-bottom: 0.5rem; font-weight: bold;"><?= __('Active Event') ?></div>
                    <h3 style="color: var(--pso-orange);"><?= __('Deep Scan Titan 130: Triple EXP') ?></h3>
                    <p><strong><?= __('Mission Window:') ?></strong> May 1, 2026 (12 AM PST) - May 4, 2026 (12 AM PST)</p>
                    <p><?= __('The military has declassified restricted combat maneuvers for all Hunters. All enemies defeated this weekend will grant **3x Experience Points**!') ?></p>
                    <div style="font-size: 0.85rem; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 4px; border-left: 3px solid var(--pso-blue);">
                        <strong><?= __('Global Start Times:') ?></strong><br>
                        🇺🇸 PST: Friday, 12:00 AM<br>
                        🇺🇸 EST: Friday, 3:00 AM<br>
                        🇬🇧 GMT: Friday, 8:00 AM<br>
                        🇯🇵 JST: Friday, 5:00 PM
                    </div>
                    <p style="margin-top: 10px;"><em><?= __('Good luck down there, Hunters! Stay safe on the surface.') ?></em></p>
                </article>
                <!-- AUTO-MISSION-END -->

                <article class="news-item">
                    <div class="event-badge" style="background: #5865F2; color: #fff; padding: 2px 8px; display: inline-block; border-radius: 4px; font-size: 0.8rem; margin-bottom: 0.5rem; font-weight: bold;"><?= __('System Update') ?></div>
                    <h3 style="color: #00ffcc;"><?= __('Discord Integration & Mission Control Live!') ?></h3>
                    <p><?= __("We've officially launched our Discord account linking! You can now securely tie your Discord profile directly to your in-game Hunter characters. Plus, interact with <strong>Mission Control</strong>—our new intelligent Discord chat companion! It recognizes your linked characters, monitors custom server status alerts, and assists with real-time guides right from the chat!") ?></p>
                </article>

                <article class="news-item">
                    <h3><?= __('New Client Beta Released!') ?></h3>
                    <p><?= __('We\'ve updated our client to version <strong>1.25.13b</strong> (851MB). This new beta client supports both JP and EN players! Download it now from our <a href="downloads.php">downloads page</a>.') ?></p>
                    <p><?= __('Please Uninstall your existing client before installing this new release.') ?></p>
                </article>


            </section>

            <aside class="sidebar">
                <div class="server-status-widget">
                    <h3><?= __('Server Status') ?></h3>
                    <div class="status-row">
                        <span class="status-label"><?= __('Status:') ?></span>
                        <span class="status-val online"><?= __('Online') ?></span>
                    </div>
                    <div class="status-row">
                        <span class="status-label"><?= __('Uptime:') ?></span>
                        <span class="status-val" id="uptime">Loading...</span>
                    </div>
                    <div class="status-row">
                        <span class="status-label"><?= __('Players:') ?></span>
                        <span class="status-val" id="client-count">--</span>
                    </div>
                    <div class="status-row">
                        <span class="status-label"><?= __('Games:') ?></span>
                        <span class="status-val" id="game-count">--</span>
                    </div>

                    <div class="widget-divider"></div>

                    <h4><?= __('Rates') ?></h4>
                    <div class="rate-row">
                        <span><?= __('EXP:') ?></span> <span id="rate-exp">1x</span>
                    </div>
                    <div class="rate-row">
                        <span><?= __('Drop:') ?></span> <span id="rate-drop">1x</span>
                    </div>
                </div>

                <div class="sidebar-widget">
                    <h3><?= __('Quick Links') ?></h3>
                    <ul class="sidebar-links">
                        <li><a href="downloads.php"><?= __('Download Client') ?></a></li>
                        <li><a href="stats.php"><?= __('View Full Stats') ?></a></li>
                        <li><a href="missions.php" style="color: var(--pso-orange);"><?= __('Bounty Board') ?></a></li>
                        <li><a href="https://discord.gg/28s84HJXha"><?= __('Discord Community') ?></a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
