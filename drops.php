<?php
$page_title = 'Drop Chart - PSOBB Private Server';
$current_page = 'drops';
include 'includes/header.php';

$section_ids = ["Viridia", "Greenill", "Skyly", "Bluefull", "Purplenum", "Pinkal", "Redria", "Oran", "Yellowboze", "Whitill"];
?>

<link rel="stylesheet" href="/css/drops.css?v=<?php echo time(); ?>">

<div class="pso-spinner-svg">
    <canvas id="star-canvas-stats"></canvas>
    <svg class="hex2"><!-- hex SVG --></svg>
</div>

<main class="container drops-container animate-fade-in">
    <div class="drops-header">
        <h1><?= __('Dynamic Drop Chart') ?></h1>
        <p style="color: #aaa;"><?= __('Explore the latest item drop rates directly from the live game server.') ?></p>
        <div id="active-char-info" style="display:none; margin-top:10px; background: rgba(0,255,255,0.1); padding: 8px; border-radius: 6px; font-size: 0.9rem;"></div>
    </div>

    <div class="drops-filters">
        <div class="filter-row">
            <div class="filter-group">
                <label>Episode</label>
                <div class="toggle-btn-group">
                    <button class="toggle-btn active ep-toggle" data-val="All">ALL</button>
                    <button class="toggle-btn ep-toggle" data-val="1">EP 1</button>
                    <button class="toggle-btn ep-toggle" data-val="2">EP 2</button>
                    <button class="toggle-btn ep-toggle" data-val="4">EP 4</button>
                </div>
            </div>

            <div class="filter-group">
                <label>Difficulty</label>
                <div class="toggle-btn-group">
                    <button class="toggle-btn diff-toggle" data-val="All">ALL</button>
                    <button class="toggle-btn diff-toggle" data-val="Normal">Normal</button>
                    <button class="toggle-btn diff-toggle" data-val="Hard">Hard</button>
                    <button class="toggle-btn diff-toggle" data-val="Very Hard">V.Hard</button>
                    <button class="toggle-btn diff-toggle active" data-val="Ultimate">Ultimate</button>
                </div>
            </div>
        </div>

        <div class="filter-group" style="width: 100%;">
            <label>Section ID</label>
            <div class="section-id-toggles">
                <?php foreach($section_ids as $sid): ?>
                    <div class="sid-toggle" data-val="<?= $sid ?>">
                        <img src="/img/section_ids/<?= $sid ?>.png" alt="<?= $sid ?>">
                        <span><?= strtoupper(substr($sid, 0, 3)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="search-bar-container">
            <i class="fas fa-search"></i>
            <input type="text" id="drop-search" class="drops-search" placeholder="Search by item or monster name...">
        </div>
    </div>

    <div class="drops-results-info">
        <span id="drops-info-text">Loading...</span>
    </div>

    <div id="drops-grid" class="drops-grid">
        <!-- Rendered via drops.js -->
    </div>
</main>

<script src="/js/drops.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer.php'; ?>
