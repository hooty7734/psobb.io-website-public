<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Patch: Fix "Boss at Floor 0" Missions & Community Events
 * --------------------------------------------------------------------------
 * 
 * Repairs all missions AND community events with boss-type goal_types 
 * that have goal_target = '0' where they shouldn't.
 * 
 * Player Missions:
 *   - DIVERSE_PARTY_BOSS / HARDCORE_MENTOR bonus missions with target '0'
 *     that are stuck as 'in_progress' → promoted to 'ready_to_redeem'
 * 
 * Community Events:
 *   - "Operation: Digital Blasphemy" has goal_target = '0' but should be
 *     'DIGITAL_BLASPHEMY' (the custom multi-boss tracker used by cron_community.php)
 *   - Any other BOSS_ARENA community events with target '0' are flagged
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/db.php';
$db = get_db();

$boss_types = ['BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS'];

$promoted = 0;
$already_ok = 0;
$events_fixed = 0;

echo "==========================================================\n";
echo "PSOBB Patch: Fix 'Boss at Floor 0' Missions & Events\n";
echo "==========================================================\n\n";

// =====================================================================
// 1. Fix Community Events with goal_target = '0'
// =====================================================================
echo "--- Community Events ---\n\n";

$ce_stmt = $db->prepare("SELECT id, title, goal_type, goal_target, status FROM community_events WHERE goal_type = 'BOSS_ARENA' AND goal_target = '0'");
$ce_result = $ce_stmt->execute();
$ce_rows = [];
while ($row = $ce_result->fetchArray(SQLITE3_ASSOC)) {
    $ce_rows[] = $row;
}

if (empty($ce_rows)) {
    echo "No community events with goal_target='0' found.\n\n";
} else {
    foreach ($ce_rows as $ce) {
        $title_lower = strtolower($ce['title']);
        $new_target = null;

        // Match known events by title to restore their correct goal_target
        if (strpos($title_lower, 'digital blasphemy') !== false) {
            $new_target = 'DIGITAL_BLASPHEMY';
        } elseif (strpos($title_lower, 'boss rush') !== false && strpos($title_lower, 'ep1') !== false) {
            $new_target = 'EP1_BOSS_RUSH';
        } elseif (strpos($title_lower, 'boss rush') !== false && strpos($title_lower, 'ep2') !== false) {
            $new_target = 'EP2_BOSS_RUSH';
        } elseif (strpos($title_lower, 'draconic') !== false) {
            $new_target = 'DRACONIC_DOMINION';
        } elseif (strpos($title_lower, 'cataclysmic') !== false) {
            $new_target = 'CATACLYSMIC_CORE';
        }

        if ($new_target) {
            $upd = $db->prepare("UPDATE community_events SET goal_target = :target WHERE id = :id");
            $upd->bindValue(':target', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $ce['id'], SQLITE3_INTEGER);
            $upd->execute();
            $events_fixed++;
            echo "[FIXED]  CE#{$ce['id']} \"{$ce['title']}\" | '0' → '{$new_target}' (status: {$ce['status']})\n";
        } else {
            echo "[WARN]   CE#{$ce['id']} \"{$ce['title']}\" | goal_target='0' but title not recognized. Manual review needed.\n";
        }
    }
    echo "\n";
}

// =====================================================================
// 2. Fix Player Missions with goal_target = '0' (impossible objectives)
// =====================================================================
echo "--- Player Missions ---\n\n";

$placeholders = implode(',', array_fill(0, count($boss_types), '?'));
$stmt = $db->prepare("
    SELECT m.id as mission_id, m.title, m.goal_type, m.goal_target, m.reward_item_string,
           pm.id as pm_id, pm.account_id, pm.character_name, pm.status
    FROM missions m
    JOIN player_missions pm ON pm.mission_id = m.id
    WHERE m.goal_type IN ($placeholders) AND m.goal_target = '0'
    ORDER BY pm.status, pm.id
");

foreach ($boss_types as $i => $type) {
    $stmt->bindValue($i + 1, $type, SQLITE3_TEXT);
}

$result = $stmt->execute();
$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

if (empty($rows)) {
    echo "No player missions with boss-type goal_target='0' found.\n\n";
} else {
    echo "Found " . count($rows) . " player mission(s) with boss-type goal_target='0'.\n\n";

    foreach ($rows as $row) {
        $status = $row['status'];
        $pm_id = $row['pm_id'];
        $acc = $row['account_id'];
        $title = $row['title'];
        $type = $row['goal_type'];
        $char = $row['character_name'] ?? 'N/A';

        if ($status === 'in_progress') {
            // Promote to ready_to_redeem — this mission was impossible to complete
            $upd = $db->prepare("UPDATE player_missions SET status = 'ready_to_redeem' WHERE id = :id");
            $upd->bindValue(':id', $pm_id, SQLITE3_INTEGER);
            $upd->execute();
            $promoted++;
            echo "[PROMOTED] PM#{$pm_id} | Account: {$acc} | Char: {$char} | \"{$title}\" ({$type}) | in_progress → ready_to_redeem\n";
        } else {
            $already_ok++;
            echo "[OK]       PM#{$pm_id} | Account: {$acc} | Char: {$char} | \"{$title}\" ({$type}) | Status: {$status} (no action needed)\n";
        }
    }
}

echo "\n==========================================================\n";
echo "Patch Completed!\n";
echo "Community events fixed: $events_fixed\n";
echo "Impossible missions promoted to redeemable: $promoted\n";
echo "Already in correct state (no change): $already_ok\n";
echo "==========================================================\n";
