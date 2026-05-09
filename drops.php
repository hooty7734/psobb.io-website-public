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
            
            <div class="filter-group">
                <label>Item Type</label>
                <div class="toggle-btn-group">
                    <button class="toggle-btn type-toggle active" data-val="All">ALL</button>
                    <button class="toggle-btn type-toggle" data-val="Weapon">Weapon</button>
                    <button class="toggle-btn type-toggle" data-val="Armor">Armor</button>
                    <button class="toggle-btn type-toggle" data-val="Shield">Shield</button>
                    <button class="toggle-btn type-toggle" data-val="Unit">Unit</button>
                    <button class="toggle-btn type-toggle" data-val="Tool">Tool</button>
                </div>
            </div>
        </div>

        <div class="filter-row" id="sub-type-row" style="display: none; margin-top: 15px;">
            <div class="filter-group" style="width: 100%;">
                <label id="sub-type-label">Sub-Category</label>
                <div class="toggle-btn-group" id="sub-type-toggles" style="flex-wrap: wrap;">
                    <!-- Dynamically populated by JS -->
                </div>
            </div>
        </div>

        <div class="filter-row" style="margin-top: 15px;">
            <div class="filter-group" style="width: 100%;">
                <label>Display Items only Usable By Class</label>
                <div class="toggle-btn-group" id="class-toggles" style="flex-wrap: wrap;">
                    <button class="toggle-btn class-toggle active" data-val="All">ALL</button>
                    <button class="toggle-btn class-toggle" data-val="HUmar">HUmar</button>
                    <button class="toggle-btn class-toggle" data-val="HUnewearl">HUnewearl</button>
                    <button class="toggle-btn class-toggle" data-val="HUcast">HUcast</button>
                    <button class="toggle-btn class-toggle" data-val="HUcaseal">HUcaseal</button>
                    <button class="toggle-btn class-toggle" data-val="RAmar">RAmar</button>
                    <button class="toggle-btn class-toggle" data-val="RAmarl">RAmarl</button>
                    <button class="toggle-btn class-toggle" data-val="RAcast">RAcast</button>
                    <button class="toggle-btn class-toggle" data-val="RAcaseal">RAcaseal</button>
                    <button class="toggle-btn class-toggle" data-val="FOmar">FOmar</button>
                    <button class="toggle-btn class-toggle" data-val="FOmarl">FOmarl</button>
                    <button class="toggle-btn class-toggle" data-val="FOnewm">FOnewm</button>
                    <button class="toggle-btn class-toggle" data-val="FOnewearl">FOnewearl</button>
                </div>
            </div>
        </div>

        <div class="filter-group" style="width: 100%;">
            <label>Section ID</label>
            <div class="section-id-toggles">
                <?php foreach($section_ids as $sid): ?>
                    <div class="sid-toggle" data-val="<?= $sid ?>">
                        <img src="/img/section_ids/<?= $sid ?>.png" alt="<?= $sid ?>">
                        <span><?= $sid ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-row" style="margin-top: 20px;">
            <div class="filter-group" style="width: 100%;">
                <label>Sort By</label>
                <div class="toggle-btn-group">
                    <button class="toggle-btn sort-toggle active" data-val="rarity_asc">Rarest First</button>
                    <button class="toggle-btn sort-toggle" data-val="rarity_desc">Common First</button>
                    <button class="toggle-btn sort-toggle" data-val="type">Item Type</button>
                    <button class="toggle-btn sort-toggle" data-val="name">Item Name</button>
                    <button class="toggle-btn sort-toggle" data-val="enemy">Enemy Name</button>
                </div>
            </div>
        </div>

        <div class="search-bar-container" style="display: flex; gap: 10px; margin-top: 20px;">
            <div style="position: relative; flex: 1;">
                <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #00ffff;"></i>
                <input type="text" id="drop-search" class="drops-search" placeholder="Search by item or monster name..." style="width: 100%;">
            </div>
        </div>
    </div>

    <div class="drops-results-info">
        <span id="drops-info-text">Loading...</span>
    </div>

    <div id="drops-grid" class="drops-grid">
        <!-- Rendered via drops.js -->
    </div>
    
    <div id="scroll-sentinel" style="height: 1px; width: 100%;"></div>
</main>

<script src="/js/drops.js?v=<?php echo time(); ?>"></script>

<?php include 'includes/footer.php'; ?>
