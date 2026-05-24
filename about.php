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
        
        /* Crew Color Schemes */
        .crew-card.founder-card::before { background: #ff2a6d; }
        .crew-card.dev-card::before { background: #00e676; }
        .crew-card.vibe-card::before { background: #ffaa00; }
        .crew-card.mod-card::before { background: #03a9f4; }

        .crew-card.founder-card:hover { border-color: #ff2a6d; box-shadow: 0 10px 30px rgba(255, 42, 109, 0.15); }
        .crew-card.dev-card:hover { border-color: #00e676; box-shadow: 0 10px 30px rgba(0, 230, 118, 0.15); }
        .crew-card.vibe-card:hover { border-color: #ffaa00; box-shadow: 0 10px 30px rgba(255, 170, 0, 0.15); }
        .crew-card.mod-card:hover { border-color: #03a9f4; box-shadow: 0 10px 30px rgba(3, 169, 244, 0.15); }

        .crew-card.founder-card .crew-avatar { color: #ff2a6d; border-color: #ff2a6d; box-shadow: 0 0 15px rgba(255, 42, 109, 0.25); }
        .crew-card.dev-card .crew-avatar { color: #00e676; border-color: #00e676; box-shadow: 0 0 15px rgba(0, 230, 118, 0.25); }
        .crew-card.vibe-card .crew-avatar { color: #ffaa00; border-color: #ffaa00; box-shadow: 0 0 15px rgba(255, 170, 0, 0.25); }
        .crew-card.mod-card .crew-avatar { color: #03a9f4; border-color: #03a9f4; box-shadow: 0 0 15px rgba(3, 169, 244, 0.25); }

        .crew-card.founder-card .crew-role { color: #ff2a6d; }
        .crew-card.dev-card .crew-role { color: #00e676; }
        .crew-card.vibe-card .crew-role { color: #ffaa00; }
        .crew-card.mod-card .crew-role { color: #03a9f4; }

        .crew-card.founder-card .crew-specialty { border-color: #ff2a6d; color: #ff2a6d; }
        .crew-card.dev-card .crew-specialty { border-color: #00e676; color: #00e676; }
        .crew-card.vibe-card .crew-specialty { border-color: #ffaa00; color: #ffaa00; }
        .crew-card.mod-card .crew-specialty { border-color: #03a9f4; color: #03a9f4; }
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
            <h1><?= __('About psobb.io') ?></h1>
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
        <h2 class="about-section-title animate-fade-in"><i class="fas fa-terminal"></i> <?= __('psobb.io Command Deck') ?></h2>
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
                    <?= __('LiquidSpikes is one of the builders of the psobb.io server infrastructure. He helps manage the backend clusters, keeps the database ticking, and maintains the web dashboard. He is incredibly grateful to the amazing community of hunters who call psobb.io home—thank you so much for playing, exploring, and keeping this timeless Sega classic alive!') ?>
                </p>
            </div>

            <!-- LucindaRie Card -->
            <div class="crew-card founder-card">
                <div class="crew-header">
                    <div class="crew-avatar">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="crew-info">
                        <h3>LucindaRie</h3>
                        <div class="crew-role"><?= __('Server Co-Founder & Creative Muse') ?></div>
                        <div class="crew-specialty"><?= __('Preservation & Community Vibe') ?></div>
                    </div>
                </div>
                <p class="crew-bio">
                    <?= __('LucindaRie is the co-founder of psobb.io and the wife of LiquidSpikes. She cares deeply about preserving the original aesthetic and design inspiration of Phantasy Star Online. LucindaRie acts as our creative guide, ensuring our features and community spaces stay fully aligned with the timeless, nostalgic magic of the 2004 classic.') ?>
                </p>
            </div>

            <!-- Oman Computar / Repflez Card -->
            <div class="crew-card dev-card">
                <div class="crew-header">
                    <div class="crew-avatar">
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="crew-info">
                        <h3>Oman Computar / Repflez</h3>
                        <div class="crew-role"><?= __('Contributor & newserv Pioneer') ?></div>
                        <div class="crew-specialty"><?= __('Core Server Development') ?></div>
                    </div>
                </div>
                <p class="crew-bio">
                    <?= __('Oman Computar (also known as Repflez) is an expert contributor to the open-source newserv server emulator and has worked extensively on several legacy Phantasy Star Online projects. His deep understanding of custom server logic and network packets has been vital to our server development and core engine refinement.') ?>
                </p>
            </div>

            <!-- Pixelated Card -->
            <div class="crew-card vibe-card">
                <div class="crew-header">
                    <div class="crew-avatar">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="crew-info">
                        <h3>Pixelated</h3>
                        <div class="crew-role"><?= __('Community & Discord Developer') ?></div>
                        <div class="crew-specialty"><?= __('Vibe Coding Beast') ?></div>
                    </div>
                </div>
                <p class="crew-bio">
                    <?= __('Pixelated is our resident vibe-coding beast, creating all kinds of awesome features and integrations inside our Discord community. From interactive bots to custom alerts, Pixelated works magic to keep our players connected, engaged, and supported with excellent gaming utilities and cool tools.') ?>
                </p>
            </div>

            <!-- Hooty7734 Card -->
            <div class="crew-card mod-card">
                <div class="crew-header">
                    <div class="crew-avatar">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="crew-info">
                        <h3>Hooty7734</h3>
                        <div class="crew-role"><?= __('Discord Administrator & Moderator') ?></div>
                        <div class="crew-specialty"><?= __('Community Management') ?></div>
                    </div>
                </div>
                <p class="crew-bio">
                    <?= __('Hooty7734 is our seasoned Discord Admin, bringing years of dedicated experience from managing and moderating other large online communities. He works to keep our community spaces safe, welcoming, and organized for all hunters who join our ranks.') ?>
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
                    <?= __('psobb.io\'s resident artificial intelligence. Hex coordinates the Hunter\'s Guild Bounty Board and drives our Discord Mission Control bot. While highly intelligent and incredibly fast, she is notoriously glitchy and famously sarcastic—frequently breaking the fourth wall, complaining about server lag, and mocking hunters who fail to dodge basic boss sweeps. Engage at your own risk!') ?>
                </p>
            </div>

        </div>

        <!-- Tech Stack Section -->
        <div class="tech-spec-section animate-fade-in">
            <h2 class="about-section-title" style="margin-bottom: 1rem; border-bottom: none;"><i class="fas fa-server"></i> <?= __('Server Tech Specs') ?></h2>
            <p style="margin: 0; color: rgba(224, 240, 255, 0.7); line-height: 1.6;">
                <?= __('Our infrastructure is designed for low latency, secure account retention, and seamless database integration.') ?>
            </p>
            
            <div class="tech-spec-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); max-width: 800px; margin: 1.8rem auto 0 auto;">
                <div class="tech-card">
                    <i class="fas fa-code-branch"></i>
                    <h4>newserv Emulator</h4>
                    <p><?= __('Powered by') ?> <a href="https://github.com/fuzziqersoftware/newserv" target="_blank" style="color: var(--pso-blue); text-decoration: underline;">newserv</a> <?= __('- the advanced open-source PSO server emulator.') ?></p>
                </div>
                <div class="tech-card">
                    <i class="fas fa-database"></i>
                    <h4>SQLite Database</h4>
                    <p><?= __('Lightweight, high-performance local database for secure player telemetry, custom quest flags, and community event progress.') ?></p>
                </div>
            </div>
        </div>

        <div class="discord-btn-container animate-fade-in">
            <a href="https://discord.gg/28s84HJXha" target="_blank" class="discord-btn"><i class="fab fa-discord"></i> <?= __('Join Our Discord') ?></a>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
