<?php
/**
 * PSOBB Website: Character & Bank Viewer
 * 
 * Renders the master cyberpunk dashboard containing the player's active character stats,
 * equipped gear, material counts, inventory backpack, and tabbed bank selections.
 * Restricts access strictly to authenticated hunter licenses.
 */
$page_title = 'Character & Bank Viewer - PSOBB';
$current_page = 'character_viewer';
include 'includes/header.php';

// Enforce authentication check at PHP level
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    echo "<script>window.location.href = '/login.php';</script>";
    exit;
}
?>

<!-- Import Modular Cyberpunk Styles and Javascript Controller -->
<link rel="stylesheet" href="/css/character_viewer.css?v=<?= time() ?>">
<script src="/js/character_viewer.js?v=<?= time() ?>" defer></script>

<main class="container">
    <div class="viewer-container">
        
        <!-- Header Bar and Selector tabs -->
        <div class="viewer-header-bar animate-fade-in">
            <h1><i class="fas fa-database"></i> <?= __('HUNTER DATABASE & BANK VIEWER') ?></h1>
            <div class="slot-selector">
                <button class="slot-tab-btn active" data-slot="0"><?= __('Slot 1') ?></button>
                <button class="slot-tab-btn" data-slot="1"><?= __('Slot 2') ?></button>
                <button class="slot-tab-btn" data-slot="2"><?= __('Slot 3') ?></button>
                <button class="slot-tab-btn" data-slot="3"><?= __('Slot 4') ?></button>
            </div>
        </div>

        <!-- Global search status/legend -->
        <div id="viewer-search-legend" style="display: none; background: rgba(255, 200, 0, 0.1); border: 1px solid #ffc800; padding: 10px; border-radius: 4px; color: #ffc800; font-family: 'Share Tech Mono', monospace; font-size: 0.9em; margin-bottom: 0.5rem;" class="animate-pulse">
            <!-- Populated dynamically via JS -->
        </div>

        <!-- Loading spinner overlay -->
        <div id="viewer-loader" style="display: none; text-align: center; padding: 3rem; background: rgba(0,0,0,0.5); border-radius: 8px; border: 1px solid rgba(0,255,255,0.15); margin-bottom: 1.5rem;">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: var(--pso-blue); margin-bottom: 1rem;"></i>
            <p style="color: #fff; font-family: 'Share Tech Mono', monospace; letter-spacing: 1px;"><?= __('RETRIEVING ENCRYPTED TELEMETRY MATRIX...') ?></p>
        </div>

        <!-- Master content pane -->
        <div id="viewer-content-pane" class="animate-fade-in" style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Top Section: Character Stats sheet -->
            <div class="viewer-card">
                <div class="char-info-grid">
                    
                    <!-- Col 1: Name, Class Avatar, Section ID -->
                    <div class="char-identity-panel">
                        <img id="char-profile-avatar" src="/img/classes/humar.png" class="char-class-avatar" alt="Avatar">
                        <h2 id="char-profile-name" class="char-name-title">--</h2>
                        <span id="char-profile-class" class="class-badge">--</span>
                        <div id="char-profile-online" style="margin-bottom: 0.75rem; font-family: 'Share Tech Mono', monospace; font-size: 0.85rem;">--</div>
                        <div id="char-profile-secid" class="section-id-badge">
                            <!-- Populated dynamically -->
                        </div>
                    </div>

                    <!-- Col 2: In-Game Stats -->
                    <div>
                        <h3 class="stats-header"><i class="fas fa-sliders-h"></i> <?= __('Character Attributes') ?></h3>
                        <div class="stats-list">
                            <div class="stat-row">
                                <span class="stat-label">LVL</span>
                                <span id="char-profile-level" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">ATP</span>
                                <span id="stat-val-atp" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">MST</span>
                                <span id="stat-val-mst" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">EVP</span>
                                <span id="stat-val-evp" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">HP</span>
                                <span id="stat-val-hp" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">DFP</span>
                                <span id="stat-val-dfp" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">ATA</span>
                                <span id="stat-val-ata" class="stat-value">--</span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">LCK</span>
                                <span id="stat-val-lck" class="stat-value">--</span>
                            </div>
                            <div class="stat-row" style="grid-column: span 2; border-color: rgba(0, 255, 255, 0.3); background: rgba(0, 255, 255, 0.03);">
                                <span class="stat-label" style="color: var(--pso-blue); font-weight: bold;"><?= __('MESETA TOTAL') ?></span>
                                <span id="stat-val-meseta" class="stat-value" style="color: var(--pso-blue);">--</span>
                            </div>
                        </div>
                    </div>

                    <!-- Col 3: Materials Used -->
                    <div>
                        <h3 class="stats-header">
                            <i class="fas fa-pills"></i> <?= __('Material Consumed Progress') ?> 
                            <span id="char-profile-playtime" style="float: right; font-size: 0.75em; color: #888; text-transform: none;">--</span>
                        </h3>
                        <div class="mats-list">
                            <!-- HP Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">HP Material</span>
                                    <span id="mat-val-hp" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-hp" class="mat-bar-fill" style="width: 0%;"></div></div>
                            </div>
                            <!-- TP Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">TP Material</span>
                                    <span id="mat-val-tp" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-tp" class="mat-bar-fill" style="width: 0%; background: #9b5DE5; box-shadow: 0 0 5px rgba(155,93,229,0.5);"></div></div>
                            </div>
                            <!-- Power Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">Power Material</span>
                                    <span id="mat-val-power" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-power" class="mat-bar-fill" style="width: 0%; background: #FF5E5B; box-shadow: 0 0 5px rgba(255,94,91,0.5);"></div></div>
                            </div>
                            <!-- Mind Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">Mind Material</span>
                                    <span id="mat-val-mind" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-mind" class="mat-bar-fill" style="width: 0%; background: #00F5D4; box-shadow: 0 0 5px rgba(0,245,212,0.5);"></div></div>
                            </div>
                            <!-- Def Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">Def Material</span>
                                    <span id="mat-val-def" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-def" class="mat-bar-fill" style="width: 0%; background: #FFD166; box-shadow: 0 0 5px rgba(255,209,102,0.5);"></div></div>
                            </div>
                            <!-- Evade Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">Evade Material</span>
                                    <span id="mat-val-evade" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-evade" class="mat-bar-fill" style="width: 0%; background: #FF9F1C; box-shadow: 0 0 5px rgba(255,159,28,0.5);"></div></div>
                            </div>
                            <!-- Luck Mats -->
                            <div class="mat-bar-container">
                                <div class="mat-bar-header">
                                    <span class="mat-bar-label">Luck Material</span>
                                    <span id="mat-val-luck" class="mat-bar-value">--</span>
                                </div>
                                <div class="mat-bar-bg"><div id="mat-bar-luck" class="mat-bar-fill" style="width: 0%; background: #2EC4B6; box-shadow: 0 0 5px rgba(46,196,182,0.5);"></div></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Dual Columns: Backpack on Left, Banks on Right -->
            <div class="grids-section">
                
                <!-- Left Column: Inventory (Equipped Gear & 30-slot backpack) -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    
                    <!-- Equipped Gear Pane -->
                    <div class="viewer-card">
                        <h3 class="stats-header" style="margin-bottom: 0.5rem;"><i class="fas fa-shield-alt"></i> <?= __('Equipped Gear') ?></h3>
                        <div id="viewer-equipped-grid" class="equipped-grid">
                            <!-- Injected dynamically via JS -->
                        </div>
                    </div>

                    <!-- Backpack inventory (30 slots) -->
                    <div class="viewer-card" style="flex: 1;">
                        <h3 class="stats-header" style="margin-bottom: 1rem;"><i class="fas fa-briefcase"></i> <?= __('Backpack Inventory (30 slots)') ?></h3>
                        <div id="viewer-backpack-grid" class="backpack-grid">
                            <!-- Injected dynamically via JS -->
                        </div>
                    </div>

                </div>

                <!-- Right Column: Banks (Dropdown selector, Live search filter, 200 grid slots, Bank Swap integration) -->
                <div class="viewer-card">
                    <div class="bank-control-header">
                        <div class="bank-select-wrapper">
                            <span style="font-family: 'Share Tech Mono', monospace; font-weight: bold; color: var(--pso-blue);"><i class="fas fa-university"></i> <?= __('Bank Select:') ?></span>
                            <select id="viewer-bank-select" class="bank-select-element">
                                <option value="0"><?= __('Character Bank (Active slot)') ?></option>
                                <option value="-1"><?= __('Shared Bank') ?></option>
                            </select>
                        </div>
                        <input type="text" id="viewer-bank-search" class="bank-search-input" placeholder="<?= __('🔍 Search bank items...') ?>">
                    </div>

                    <!-- Meseta Display -->
                    <div style="display: flex; justify-content: flex-end; margin-bottom: 10px; font-family: 'Share Tech Mono', monospace; font-size: 0.95em;">
                        <span style="color: #888; margin-right: 8px;"><?= __('Bank Vault:') ?></span>
                        <strong id="viewer-bank-meseta" style="color: var(--pso-orange);">0 Meseta</strong>
                    </div>

                    <!-- 200 Bank slots Grid layout -->
                    <div id="viewer-bank-grid" class="bank-grid">
                        <!-- Injected dynamically via JS -->
                    </div>

                    <!-- Bank swap execution actions -->
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <p style="font-size: 0.85em; color: #888; margin: 0; max-width: 480px;">
                                <i class="fas fa-info-circle" style="color: var(--pso-blue);"></i> <?= __('You can swap your active in-game bank container to the selected bank above instantly! To swap banks successfully, you must be online in a lobby block, not actively in a battle or challenge quest.') ?>
                            </p>
                            <button id="viewer-btn-swap-bank" class="dl-btn" style="border-color: var(--pso-blue); background: rgba(0, 255, 255, 0.12); color: var(--pso-blue); font-family: 'Share Tech Mono', monospace; font-weight: bold; padding: 10px 20px;">
                                <?= __('Swap Bank In-Game') ?>
                            </button>
                        </div>
                        <div id="viewer-swap-msg" style="display: none; font-weight: bold; font-family: 'Share Tech Mono', monospace; font-size: 0.9em; padding: 8px; background: rgba(0,0,0,0.3); border-radius: 4px; border: 1px solid rgba(255,255,255,0.05);">
                            <!-- Populated dynamically via JS swap alerts -->
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </div>
</main>

<?php include 'includes/footer.php'; ?>
