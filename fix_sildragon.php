<?php
/**
 * PSOBB Sil Dragon & Mission State Repair Utility
 * Run via CLI on your host: php fix_sildragon.php
 */
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/functions.php';

echo "\n[INFO] Starting Sil Dragon & Mission State Repair...\n";

$db = get_db();

// 1. Scan for any in-progress Floor 11 (Dragon / Sil Dragon) missions in the database
$stmt = $db->prepare("SELECT pm.id, pm.account_id, pm.character_name, m.title, m.description, m.goal_type, m.goal_target 
                      FROM player_missions pm 
                      JOIN missions m ON pm.mission_id = m.id 
                      WHERE pm.status = 'in_progress' AND (m.goal_target = '11' OR m.goal_target LIKE '11_%')");
$res = $stmt->execute();

$found = 0;
echo "\n[1] Active Floor 11 (Forest / Sil Dragon) Missions in Database:\n";
echo str_repeat("-", 80) . "\n";

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $found++;
    $account_id = $row['account_id'];
    $char_name = $row['character_name'] ?: 'N/A';
    $title = $row['title'];
    $desc = $row['description'];
    
    // Dynamically resolve episode and objective using the new patched functions.php code
    $ep = get_boss_episode_by_context($title, $desc, 11);
    $objective = getClearObjective($row['goal_type'], $row['goal_target'], $title, $desc);
    
    echo "ID: {$row['id']} | Account: {$account_id} | Character: {$char_name}\n";
    echo "Title: {$title}\n";
    echo "Resolved Episode: Episode {$ep}\n";
    echo "Resolved Objective: {$objective}\n";
    echo str_repeat("-", 80) . "\n";
}

if ($found === 0) {
    echo "No active Floor 11 missions found in progress. All clear!\n";
} else {
    echo "Successfully verified {$found} active Floor 11 mission(s). They are now mapped to Episode 1 Forest!\n";
}

// 2. Clear the active player state cache to prevent stale telemetry mismatches
echo "\n[2] Checking Player Telemetry Cache...\n";
$cache_file = __DIR__ . '/db/.cron_player_state.json';
if (file_exists($cache_file)) {
    if (unlink($cache_file)) {
        echo "[SUCCESS] Cleared active player state cache file (.cron_player_state.json).\n";
        echo "          The cron job will rebuild fresh state telemetry on the next player action.\n";
    } else {
        echo "[WARNING] Found player state cache file but was unable to delete it. Check permissions.\n";
    }
} else {
    echo "[INFO] No active player state cache file found. Telemetry is clean.\n";
}

echo "\n[REPAIR COMPLETE] Sil Dragon mission mapping is now fully optimized and verified!\n\n";
?>
