<?php
/**
 * PSOBB Website: Top Hunters
 * 
 * Displays the global leaderboard for the Hunter's Guild bounties.
 */
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/functions.php';
start_secure_session();

$page_title = 'Top Hunters';
$current_page = 'top_hunters';
include 'includes/header.php';
require_once 'api/db.php';

$db = get_db();

// -------------------------------------------------------------------------
// Fetch Global Top 100 Leaderboard
// -------------------------------------------------------------------------
$leaderboard = [];
$lb_res = $db->query("SELECT COALESCE(u.display_name, 'Hunter #' || u.account_id) as hunter_name, COUNT(pm.id) as completions 
                      FROM player_missions pm 
                      JOIN users u ON pm.account_id = u.account_id 
                      WHERE pm.status = 'completed' 
                      GROUP BY u.account_id 
                      ORDER BY completions DESC 
                      LIMIT 100");
while ($row = $lb_res->fetchArray(SQLITE3_ASSOC)) {
    $leaderboard[] = $row;
}

?>

<link rel="stylesheet" href="css/missions.css">

<main class="container">
    <section class="missions-hero animate-fade-in" style="margin-bottom: 3rem;">
        <h1><?= __('Global Top Hunters') ?></h1>
        <p><?= __('The most elite Hunters on Pioneer 2. These legends have proven their worth by completing the most bounties.') ?></p>
    </section>

    <div style="max-width: 800px; margin: 0 auto;">
        <div class="leaderboard-widget delay-1" style="background: linear-gradient(160deg, rgba(16, 24, 32, 0.85) 0%, rgba(8, 12, 16, 0.95) 100%); padding: 2rem; border-radius: 12px; border: 1px solid rgba(0, 255, 255, 0.15); box-shadow: 0 8px 25px rgba(0,0,0,0.4);">
            <h2 style="text-align: center; color: var(--pso-blue); margin-bottom: 2rem; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 1rem;"><?= __('Hall of Fame') ?></h2>
            
            <?php if (empty($leaderboard)): ?>
                <p style="text-align:center; opacity:0.6; padding: 2rem;"><?= __('The leaderboard is waiting for its first legend.') ?></p>
            <?php else: ?>
                <ul class="leaderboard-list">
                    <?php foreach ($leaderboard as $idx => $lb): ?>
                        <?php $rank = $idx + 1; ?>
                        <li class="rank-<?= $rank ?>" style="padding: 15px 20px; font-size: 1.1rem; <?= $rank > 3 ? 'background: rgba(255,255,255,0.03);' : '' ?>">
                            <div class="rank-wrapper" style="gap: 15px;">
                                <div class="rank-badge" style="<?= $rank > 3 ? 'background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: #ccc;' : '' ?>"><?= $rank ?></div>
                                <span class="player-name" style="<?= $rank > 3 ? 'color: #ddd;' : '' ?>"><?= htmlspecialchars($lb['hunter_name']) ?></span>
                            </div>
                            <span class="completion-count" style="<?= $rank > 3 ? 'color: #ccc;' : '' ?>"><?= number_format($lb['completions']) ?> <?= __('Bounties') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
