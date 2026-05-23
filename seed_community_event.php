<?php
/**
 * Seeder to launch the next PSOBB Community Event
 * Run via CLI on your host: php seed_community_event.php
 */
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';

$db = get_db();

$title = "Operation: Digital Blasphemy";
$description = "Corrupted cybernetic mainframes are spawning simulations across Ragol! Defeat Vol Opt in Ep1 (+1 pt), Gol Dragon in Ep2 (+2 pts), or Shambertin in Ep4 (+3 pts). Earn difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)! Cooperate to earn 3 random rare drops tailored to your character level!";
$goal_type = "BOSS_ARENA";
$goal_target = "DIGITAL_BLASPHEMY"; // Custom multi-boss target tracked dynamically
$target_amount = 75000; // Scaled to span the entire month!
$reward_item_string = "3x Random Rare Drops"; // Intercepted dynamically at claim time
$top_3_reward_item_string = "Heart of Chao | Cell of MAG 502 | Amitie's Memo"; // Exclusively Cosmetic Mags

// Deactivate any currently active events to prevent overlaps
$db->exec("UPDATE community_events SET status = 'inactive' WHERE status = 'active'");

// Insert the new community event
$stmt = $db->prepare("INSERT INTO community_events (title, description, goal_type, goal_target, target_amount, reward_item_string, top_3_reward_item_string, status) 
                      VALUES (:t, :d, :gt, :gta, :amt, :ri, :top3, 'active')");
$stmt->bindValue(':t', $title, SQLITE3_TEXT);
$stmt->bindValue(':d', $description, SQLITE3_TEXT);
$stmt->bindValue(':gt', $goal_type, SQLITE3_TEXT);
$stmt->bindValue(':gta', $goal_target, SQLITE3_TEXT);
$stmt->bindValue(':amt', $target_amount, SQLITE3_INTEGER);
$stmt->bindValue(':ri', $reward_item_string, SQLITE3_TEXT);
$stmt->bindValue(':top3', $top_3_reward_item_string, SQLITE3_TEXT);

if ($stmt->execute()) {
    echo "\n[SUCCESS] Operation: Digital Blasphemy launched successfully!\n";
    echo "Event: " . $title . "\n";
    echo "Goal Target: " . $goal_target . " (Vol Opt, Gol Dragon, Shambertin)\n";
    echo "Target Progress Points: " . $target_amount . "\n";
    echo "Main Reward: " . $reward_item_string . " (dynamic level-fit drops)\n";
    echo "Top 3 Mags Choice: " . $top_3_reward_item_string . "\n\n";
} else {
    echo "\n[ERROR] Failed to insert community event.\n\n";
}
?>
