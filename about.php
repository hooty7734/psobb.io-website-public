<?php
$page_title = 'About - PSOBB Private Server';
$current_page = 'about';
include 'includes/header.php';
?>

    <main class="container">
        <div class="main-header">
            <h1><?= __('About Our Server') ?></h1>
            <p><?= __('Experience Phantasy Star Online Blue Burst like never before on our private server powered by newserv.') ?>
            </p>
        </div>

        <div class="about-content">
            <h2><?= __('Features') ?></h2>
            <ul class="features-list" style="margin-bottom: 2rem; line-height: 1.6; display: flex; flex-direction: column; gap: 0.8rem;">
                <li><strong><?= __('Episodes 1-4 Complete:') ?></strong> <?= __('Full native quest lines spanning the entirety of the Phantasy Star Online Blue Burst saga with balanced 1x progression rates.') ?></li>
                <li><strong><?= __('Cross-Platform Delivery:') ?></strong> <?= __('Seamless client support via native Windows, macOS ports, and Linux / Steam Deck installations.') ?></li>
                <li><strong><?= __('Hunter\'s Guild Bounty Board:') ?></strong> <?= __('Take on unique, personalized bounties offering exclusive gear and material rewards. Track your boss kills and exploration objectives directly on the web, and claim your loot instantly to your character in-game.') ?></li>
                <li><strong><?= __('Discord Mission Control:') ?></strong> <?= __('An advanced interactive chat companion inside our Discord server! Securely link your Discord account to your Hunter profile to allow Mission Control to uniquely interact with your characters, provide real-time server status alerts, and answer questions about PSO lore, item drops, and guides securely in chat.') ?></li>
                <li><strong><?= __('Daily Login Streaks:') ?></strong> <?= __('Log into the Player Dashboard daily to accumulate bonus rewards to enhance your gameplay.') ?></li>
                <li><strong><?= __('Team Check-In Dashboard:') ?></strong> <?= __('View your Guild\'s master roster, unspent team points, and next milestone unlocks from any device.') ?></li>
                <li><strong><?= __('Wall of Legends Leaderboard:') ?></strong> <?= __('A public hall of fame celebrating the top hunters who successfully complete our most challenging bounties.') ?></li>
                <li><strong><?= __('Modern Engine Stability:') ?></strong> <?= __('Clean anti-cheat protocols and blazing fast infrastructure to ensure pristine, uninterrupted play.') ?></li>
            </ul>

            <h2><?= __('Server Tech') ?></h2>
            <p><?= __('Powered by') ?> <a href="https://github.com/Wowfunhappy/newserv" target="_blank">newserv</a> <?= __('- an open-source PSO BB server emulator.') ?></p>
            <p><?= __('Stats pulled automatically from server state (players, games, uptime).') ?></p>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="https://discord.gg/28s84HJXha" target="_blank" class="discord-btn"><?= __('Join Our Discord') ?></a>
            </div>
            
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
