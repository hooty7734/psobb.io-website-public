<?php
/**
 * Shared 6-Month Curated highly scaled month-long community boss rush events roster.
 */

$roster = [
    1 => [
        'title' => "Operation: Digital Blasphemy",
        'description' => "Corrupted cybernetic mainframes are spawning simulations across Ragol! Cooperate server-wide to defeat Vol Opt in Ep1 (+1 pt), Gol Dragon in Ep2 (+2 pts), or Shambertin in Ep4 (+3 pts). Earn difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)! Earn 3 random rare drops tailored to your character level!",
        'goal_type' => "BOSS_ARENA",
        'goal_target' => "DIGITAL_BLASPHEMY",
        'target_amount' => 75000,
        'reward_item_string' => "3x Random Rare Drops",
        'top_3_reward_item_string' => "Heart of Chao | Cell of MAG 502 | Amitie's Memo"
    ],
    2 => [
        'title' => "Operation: Ancient Cataclysm",
        'description' => "An ancient seal has broken, triggering a massive assault from the wildlife! Defeat any Episode 1 boss: Dragon (+1 pt), De Rol Le (+2 pts), Vol Opt (+3 pts), or Dark Falz (+4 pts). Earn difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)!",
        'goal_type' => "BOSS_ARENA",
        'goal_target' => "EP1_BOSS_RUSH",
        'target_amount' => 90000,
        'reward_item_string' => "3x Random Rare Drops",
        'top_3_reward_item_string' => "Kit of DREAMCAST | Kit of SEGA SATURN | Kit of GENESIS"
    ],
    3 => [
        'title' => "Operation: Seabed Infestation",
        'description' => "The Sub-Seabed laboratories are overflowing with mutated threats! Defeat any Episode 2 boss: Barba Ray (+1 pt), Gol Dragon (+2 pts), Gal Gryphon (+3 pts), or Olga Flow (+4 pts). Earn difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)!",
        'goal_type' => "BOSS_ARENA",
        'goal_target' => "EP2_BOSS_RUSH",
        'target_amount' => 75000,
        'reward_item_string' => "3x Random Rare Drops",
        'top_3_reward_item_string' => "Heart of Opa Opa | Parts of RoboChao | Heart of Angel"
    ],
    4 => [
        'title' => "Operation: Draconic Dominion",
        'description' => "Ancient and simulated dragons have established a scorching grip across all episodes! Defeat Dragon in Ep1 (+1 pt), Gol Dragon in Ep2 (+2 pts), or Shambertin in Ep4 (+3 pts). Earn difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)!",
        'goal_type' => "BOSS_ARENA",
        'goal_target' => "DRACONIC_DOMINION",
        'target_amount' => 80000,
        'reward_item_string' => "3x Random Rare Drops",
        'top_3_reward_item_string' => "Dragon Scale | Heart of Devil | Heart of Morolian"
    ],
    5 => [
        'title' => "Operation: The Ultimate Gauntlet",
        'description' => "The Hunters Guild has issued an absolute extermination order. Defeat ANY boss in the entire game! Points are scaled by boss tier (Dragon/Barba Ray +1, De Rol Le/Gol Dragon +2, Vol Opt/Gal Gryphon +3, Dark Falz/Olga Flow/Shambertin +4) plus difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)!",
        'goal_type' => "BOSS_ARENA",
        'goal_target' => "ALL_BOSSES",
        'target_amount' => 150000,
        'reward_item_string' => "3x Random Rare Drops",
        'top_3_reward_item_string' => "Kit of MASTER SYSTEM | Kit of MARK3 | Kit of Hamburger"
    ],
    6 => [
        'title' => "Operation: Cataclysmic Core",
        'description' => "The core dimensional anomalies threaten to destabilize Ragol! Cooperate to defeat the final boss of each episode: Dark Falz in Ep1 (+3 pts), Olga Flow in Ep2 (+4 pts), or Shambertin in Ep4 (+4 pts). Earn difficulty bonuses (+1 Hard, +2 VHard, +3 Ult)!",
        'goal_type' => "BOSS_ARENA",
        'goal_target' => "CATACLYSMIC_CORE",
        'target_amount' => 50000,
        'reward_item_string' => "3x Random Rare Drops",
        'top_3_reward_item_string' => "Heart of Chao | Cell of MAG 502 | Amitie's Memo"
    ]
];

function launch_event($db, $roster, $index) {
    if (!isset($roster[$index])) {
        if (php_sapi_name() === 'cli') {
            echo "[ERROR] Invalid event index: $index\n";
        }
        return false;
    }
    
    $event = $roster[$index];
    
    try {
        // Deactivate active events
        $db->exec("UPDATE community_events SET status = 'inactive' WHERE status = 'active'");
        
        // Insert new active event
        $stmt = $db->prepare("INSERT INTO community_events (title, description, goal_type, goal_target, target_amount, reward_item_string, top_3_reward_item_string, status) 
                              VALUES (:t, :d, :gt, :gta, :amt, :ri, :top3, 'active')");
        $stmt->bindValue(':t', $event['title'], SQLITE3_TEXT);
        $stmt->bindValue(':d', $event['description'], SQLITE3_TEXT);
        $stmt->bindValue(':gt', $event['goal_type'], SQLITE3_TEXT);
        $stmt->bindValue(':gta', $event['goal_target'], SQLITE3_TEXT);
        $stmt->bindValue(':amt', $event['target_amount'], SQLITE3_INTEGER);
        $stmt->bindValue(':ri', $event['reward_item_string'], SQLITE3_TEXT);
        $stmt->bindValue(':top3', $event['top_3_reward_item_string'], SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            if (php_sapi_name() === 'cli') {
                echo "\n[SUCCESS] Launched Event #$index: " . $event['title'] . "\n";
                echo "Description: " . $event['description'] . "\n\n";
            }
            return true;
        }
    } catch (Exception $e) {
        if (php_sapi_name() === 'cli') {
            echo "[ERROR] Failed to launch event: " . $e->getMessage() . "\n";
        }
    }
    return false;
}
?>
