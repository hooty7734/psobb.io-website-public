<?php
$page_title = 'Wall of Legends - PSOBB Private Server';
$current_page = 'stats';
include 'includes/header.php';
require_once 'api/db.php';
$db = get_db();

// Fetch Completed Bounties (Wall of Legends)
$completed_bounties = [];
$cb_res = $db->query("SELECT u.username, m.title, m.reward_item_string, pm.completed_at 
                      FROM player_missions pm 
                      JOIN missions m ON pm.mission_id = m.id 
                      JOIN users u ON pm.account_id = u.account_id 
                      WHERE pm.status = 'completed' 
                      ORDER BY pm.completed_at DESC 
                      LIMIT 50");
while ($row = $cb_res->fetchArray(SQLITE3_ASSOC)) {
    // Format timestamp nicely
    $row['completed_at'] = date('M j, Y - g:i A', strtotime($row['completed_at'] . ' UTC'));
    $completed_bounties[] = $row;
}
?>

    <main class="container">
        <div class="main-header" style="margin-bottom: 2rem;">
            <h1>Wall of Legends</h1>
            <p style="color: #aaa;">Historic catalog of recently slayed bounties and claimed prestige.</p>
        </div>

        <div class="layout-grid">
            <section class="main-content">
                <?php if (empty($completed_bounties)): ?>
                    <p style="text-align: center; margin: 3rem; opacity: 0.5;">No bounties have been resolved yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Hunter</th>
                                    <th>Directive</th>
                                    <th>Reward Drop</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completed_bounties as $c): ?>
                                    <tr>
                                        <td style="color: #00ff88; font-weight:bold;"><?= htmlspecialchars($c['username']) ?></td>
                                        <td><?= htmlspecialchars($c['title']) ?></td>
                                        <td style="color: #ffaa00; font-family: monospace; font-size: 0.9em;"><?= htmlspecialchars($c['reward_item_string']) ?></td>
                                        <td style="opacity:0.6; font-size: 0.85em;"><?= $c['completed_at'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="sidebar">
                <div class="sidebar-widget">
                    <h3>Quick Links</h3>
                    <ul class="sidebar-links">
                        <li><a href="stats.php">Back to Stats</a></li>
                        <li><a href="missions.php" style="color: var(--pso-orange);">Active Bounty Board</a></li>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

<?php include 'includes/footer.php'; ?>
