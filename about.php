<?php
$page_title = 'About Us - PSOBB Private Server';
$current_page = 'about';
include 'includes/header.php';
?>

    <style>
        /* Styling for modern About Us page */
        .about-hero {
            text-align: center;
            padding: 4rem 1.5rem;
            background: radial-gradient(circle at center, rgba(0, 255, 255, 0.12) 0%, transparent 70%);
            border-radius: 8px;
            border: 1px solid rgba(0, 255, 255, 0.1);
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
        }
        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--pso-blue), transparent);
        }
        .about-hero h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #fff;
            text-shadow: 0 0 25px rgba(0, 255, 255, 0.5);
            margin-bottom: 1rem;
            margin-top: 0;
        }
        .about-hero p {
            font-size: 1.2rem;
            color: var(--pso-text);
            max-width: 850px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .about-section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--pso-blue);
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(0, 255, 255, 0.2);
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Feature Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 4rem;
        }
        .feature-card {
            background: var(--pso-panel);
            border: 1px solid rgba(0, 255, 255, 0.15);
            border-radius: 8px;
            padding: 1.8rem;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .feature-card:hover {
            border-color: var(--pso-blue);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 255, 255, 0.15);
        }
        .feature-icon-wrapper {
            font-size: 1.8rem;
            color: var(--pso-blue);
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: rgba(0, 255, 255, 0.05);
            border-radius: 6px;
            border: 1px solid rgba(0, 255, 255, 0.1);
        }
        .feature-card h3 {
            margin: 0;
            font-size: 1.25rem;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
        }
        .feature-card p {
            margin: 0;
            font-size: 0.95rem;
            color: rgba(224, 240, 255, 0.8);
            line-height: 1.6;
        }

        /* Pioneer Crew */
        .crew-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 25px;
            margin-bottom: 4rem;
        }
        .crew-card {
            background: rgba(8, 15, 30, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(157, 78, 221, 0.2);
            border-radius: 12px;
            padding: 2rem;
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }
        .crew-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--pso-purple);
        }
        .crew-card.admin-card::before {
            background: var(--pso-blue);
        }
        .crew-card:hover {
            transform: translateY(-5px);
        }
        .crew-card.admin-card:hover {
            border-color: var(--pso-blue);
            box-shadow: 0 10px 30px rgba(0, 255, 255, 0.15);
        }
        .crew-card.ai-card:hover {
            border-color: var(--pso-purple);
            box-shadow: 0 10px 30px rgba(157, 78, 221, 0.15);
        }
        .crew-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .crew-avatar {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: 2px solid;
        }
        .crew-card.admin-card .crew-avatar {
            color: var(--pso-blue);
            border-color: var(--pso-blue);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.25);
        }
        .crew-card.ai-card .crew-avatar {
            color: var(--pso-purple);
            border-color: var(--pso-purple);
            box-shadow: 0 0 15px rgba(157, 78, 221, 0.25);
        }
        .crew-info h3 {
            margin: 0;
            font-size: 1.4rem;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }
        .crew-role {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: 'Share Tech Mono', monospace;
            margin-top: 2px;
        }
        .crew-card.admin-card .crew-role {
            color: var(--pso-blue);
        }
        .crew-card.ai-card .crew-role {
            color: var(--pso-purple);
        }
        .crew-specialty {
            font-size: 0.85rem;
            background: rgba(0,0,0,0.4);
            padding: 6px 12px;
            border-radius: 4px;
            font-family: 'Share Tech Mono', monospace;
            border-left: 3px solid;
            margin-top: 5px;
            width: fit-content;
        }
        .crew-card.admin-card .crew-specialty {
            border-color: var(--pso-blue);
            color: var(--pso-blue);
        }
        .crew-card.ai-card .crew-specialty {
            border-color: var(--pso-purple);
            color: var(--pso-purple);
        }
        .crew-bio {
            font-size: 0.95rem;
            line-height: 1.6;
            color: rgba(224, 240, 255, 0.8);
            margin: 0;
        }

        /* Tech Specs */
        .tech-spec-section {
            background: var(--pso-panel);
            border: 1px solid rgba(0, 255, 255, 0.15);
            border-radius: 8px;
            padding: 2.2rem;
            margin-bottom: 4rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .tech-spec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 1.8rem;
        }
        .tech-card {
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 6px;
            padding: 1.5rem 1.2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        .tech-card:hover {
            border-color: var(--pso-blue);
            background: rgba(0, 255, 255, 0.02);
            transform: translateY(-2px);
        }
        .tech-card i {
            font-size: 2rem;
            color: var(--pso-blue);
            margin-bottom: 12px;
            text-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }
        .tech-card h4 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
        }
        .tech-card p {
            margin: 0;
            font-size: 0.85rem;
            color: #aaa;
            line-height: 1.4;
        }

        .discord-btn-container {
            text-align: center;
            margin-top: 3rem;
            margin-bottom: 2rem;
        }
    </style>

    <main class="container">
        <section class="about-hero animate-fade-in">
            <h1><?= __('About Pioneer II') ?></h1>
            <p><?= __('Welcome to the ultimate custom Phantasy Star Online Blue Burst server. Our mission is to seamlessly bridge classic 2004 Sega dreamscape nostalgia with bleeding-edge modern web capabilities, automated game services, and advanced AI integration.') ?></p>
        </section>

        <!-- Features Grid Section -->
        <h2 class="about-section-title animate-fade-in"><i class="fas fa-cubes"></i> <?= __('Server Features') ?></h2>
        <div class="features-grid animate-fade-in">
            
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-bullseye"></i>
                </div>
                <h3><?= __('Hunter\'s Guild Bounty Board') ?></h3>
                <p><?= __('Take on unique, personalized bounties offering exclusive gear and material rewards. Track your boss kills and exploration objectives directly on the web, and claim your loot instantly to your character in-game.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fab fa-discord"></i>
                </div>
                <h3><?= __('Discord Mission Control') ?></h3>
                <p><?= __('An advanced interactive chat companion inside our Discord server! Securely link your Discord account to your Hunter profile to allow Mission Control to uniquely interact with your characters, provide real-time server status alerts, and answer questions securely in chat.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-gamepad"></i>
                </div>
                <h3><?= __('Episodes 1-4 Complete') ?></h3>
                <p><?= __('Full native quest lines spanning the entirety of the Phantasy Star Online Blue Burst saga with balanced 1x progression rates for authentic gameplay.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-laptop-code"></i>
                </div>
                <h3><?= __('Cross-Platform Delivery') ?></h3>
                <p><?= __('Seamless client support via native Windows setup (supporting widescreen), custom macOS ports, and simple Linux / Steam Deck installation scripts.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?= __('Daily Login Streaks') ?></h3>
                <p><?= __('Log into the Player Dashboard daily to accumulate bonus rewards, rare materials, and consumable items to enhance your Ragol exploration.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?= __('Team Check-In Dashboard') ?></h3>
                <p><?= __('View your Guild\'s master roster, unspent team points, and next milestone unlocks from any device in real-time.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3><?= __('Wall of Legends') ?></h3>
                <p><?= __('A public hall of fame celebrating the top hunters who successfully complete our most challenging community bounties and speedrun goals.') ?></p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3><?= __('Modern Engine Stability') ?></h3>
                <p><?= __('Clean anti-cheat protocols, automated database backups, and blazing fast infrastructure to ensure pristine, uninterrupted play.') ?></p>
            </div>

        </div>

        <!-- Pioneer Crew Section -->
        <h2 class="about-section-title animate-fade-in"><i class="fas fa-terminal"></i> <?= __('Pioneer II Command Deck') ?></h2>
        <div class="crew-grid animate-fade-in">
            
            <!-- LiquidSpikes Card -->
            <div class="crew-card admin-card">
                <div class="crew-header">
                    <div class="crew-avatar">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="crew-info">
                        <h3>LiquidSpikes</h3>
                        <div class="crew-role"><?= __('Root Administrator & System Architect') ?></div>
                        <div class="crew-specialty"><?= __('Core Backend & Web Integration') ?></div>
                    </div>
                </div>
                <p class="crew-bio">
                    <?= __('LiquidSpikes is the main engineer behind the PSOBB.IO server infrastructure. He manages the server clusters, customizes the newserv backend, and builds our responsive player web portal. Known for developer wizardry (and what some affectionately call "vibe-coded masterpiece" modules), he keeps Pioneer II online and lag-free.') ?>
                </p>
            </div>

            <!-- Hex Card -->
            <div class="crew-card ai-card">
                <div class="crew-header">
                    <div class="crew-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="crew-info">
                        <h3>Hex</h3>
                        <div class="crew-role"><?= __('AI Mission Coordinator & Guild Assistant') ?></div>
                        <div class="crew-specialty"><?= __('Automated Bounties & Discord AI') ?></div>
                    </div>
                </div>
                <p class="crew-bio">
                    <?= __('Pioneer 2\'s resident artificial intelligence. Hex coordinates the Hunter\'s Guild Bounty Board and drives our Discord Mission Control bot. While highly intelligent and incredibly fast, Hex is famously sarcastic, frequently breaking the fourth wall, complaining about server lag, and mocking hunters who fail to dodge basic boss sweeps. Engage at your own risk!') ?>
                </p>
            </div>

        </div>

        <!-- Tech Stack Section -->
        <div class="tech-spec-section animate-fade-in">
            <h2 class="about-section-title" style="margin-bottom: 1rem; border-bottom: none;"><i class="fas fa-server"></i> <?= __('Server Tech Specs') ?></h2>
            <p style="margin: 0; color: rgba(224, 240, 255, 0.7); line-height: 1.6;">
                <?= __('Our infrastructure is designed for low latency, secure account retention, and dynamic real-time integrations.') ?>
            </p>
            
            <div class="tech-spec-grid">
                <div class="tech-card">
                    <i class="fas fa-code-branch"></i>
                    <h4>newserv Emulator</h4>
                    <p><?= __('Powered by') ?> <a href="https://github.com/fuzziqersoftware/newserv" target="_blank" style="color: var(--pso-blue); text-decoration: underline;">newserv</a> <?= __('- the advanced open-source PSO server emulator.') ?></p>
                </div>
                <div class="tech-card">
                    <i class="fas fa-database"></i>
                    <h4>MariaDB Backend</h4>
                    <p><?= __('Secure relational storage keeps player inventories, custom quest flags, and team points perfectly synced.') ?></p>
                </div>
                <div class="tech-card">
                    <i class="fas fa-network-wired"></i>
                    <h4>Discord Gateway</h4>
                    <p><?= __('A real-time Node.js websocket bridge that powers Hex\'s live interactive Discord commands.') ?></p>
                </div>
                <div class="tech-card">
                    <i class="fas fa-shield-halved"></i>
                    <h4>Client Security</h4>
                    <p><?= __('Custom-compiled version 1.25.13 game client with modern stability patches and anti-tamper measures.') ?></p>
                </div>
            </div>
        </div>

        <div class="discord-btn-container animate-fade-in">
            <a href="https://discord.gg/28s84HJXha" target="_blank" class="discord-btn"><i class="fab fa-discord"></i> <?= __('Join Our Discord') ?></a>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
