<?php
$page_title = 'Stats - PSOBB Private Server';
$current_page = 'stats';
include 'includes/header.php';
?>

    <div class="pso-spinner-svg">
        <canvas id="star-canvas-stats"></canvas>
        <svg class="hex2"><!-- hex SVG --></svg>
    </div>

    <main class="container">
        <div class="main-header" style="margin-bottom: 2rem;">
            <h1><?= __('Server Statistics') ?></h1>
        </div>

        <div class="layout-grid">
            <section class="main-content">

            <div class="server-status-widget" style="margin-bottom: 2rem;">
                <h3><?= __('Live Server Stats') ?></h3>
                <div class="status-row">
                    <span><?= __('Total Players:') ?></span>
                    <span id="client-count-stats">--</span>
                </div>
                <div class="status-row">
                    <span><?= __('Active Games:') ?></span>
                    <span id="game-count-stats">--</span>
                </div>
                <div class="status-row">
                    <span><?= __('Uptime:') ?></span>
                    <span id="uptime-stats">Loading...</span>
                </div>
                <div class="status-row">
                    <span><?= __('Server Name:') ?></span>
                    <span id="server-name">--</span>
                </div>
                <div class="widget-divider" style="margin: 0.75rem 0; border-top: 1px dashed rgba(0, 255, 255, 0.2);"></div>
                <div class="status-row" style="color: #ff6666;">
                    <span><i class="fas fa-shield-halved" style="width: 16px; margin-right: 5px;"></i> <?= __('Hunters (HU):') ?></span>
                    <span id="class-hu-count" style="font-weight: bold; text-shadow: 0 0 5px rgba(255, 70, 70, 0.3);">0</span>
                </div>
                <div class="status-row" style="color: #55ccff;">
                    <span><i class="fas fa-crosshairs" style="width: 16px; margin-right: 5px;"></i> <?= __('Rangers (RA):') ?></span>
                    <span id="class-ra-count" style="font-weight: bold; text-shadow: 0 0 5px rgba(0, 170, 255, 0.3);">0</span>
                </div>
                <div class="status-row" style="color: #cc88ff;">
                    <span><i class="fas fa-wand-sparkles" style="width: 16px; margin-right: 5px;"></i> <?= __('Forces (FO):') ?></span>
                    <span id="class-fo-count" style="font-weight: bold; text-shadow: 0 0 5px rgba(157, 78, 221, 0.3);">0</span>
                </div>
            </div>

            <h2><?= __('Online Players') ?></h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Level') ?></th>
                            <th><?= __('Class') ?></th>
                            <th><?= __('Section ID') ?></th>
                        </tr>
                    </thead>
                    <tbody id="player-list">
                        <tr>
                            <td colspan="4"><?= __('Loading players...') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2><?= __('Active Games') ?></h2>
            <div class="table-responsive">
                <table class="games-table">
                    <thead>
                        <tr>
                            <th><?= __('Game Name') ?></th>
                            <th><?= __('Mode') ?></th>
                            <th><?= __('Episode') ?></th>
                            <th><?= __('Difficulty') ?></th>
                            <th><?= __('Players') ?></th>
                            <th><?= __('Access') ?></th>
                        </tr>
                    </thead>
                    <tbody id="game-list">
                        <tr>
                            <td colspan="6"><?= __('Loading games...') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="sidebar">
            <div class="sidebar-widget">
                <h3><?= __('Quick Stats') ?></h3>
                <div class="status-row">
                    <span><?= __('EXP Rate:') ?></span>
                    <span id="rate-exp-stats">1x</span>
                </div>
                <div class="status-row">
                    <span><?= __('Drop Rate:') ?></span>
                    <span id="rate-drop-stats">1x</span>
                </div>
                <div class="widget-divider"></div>
                <ul class="sidebar-links">
                    <li><a href="legends.php" style="color: #00ff88; font-weight: bold;"><?= __('Wall of Legends') ?></a></li>
                    <li><a href="index.php"><?= __('Back to Home') ?></a></li>
                </ul>
            </div>
        </aside>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
