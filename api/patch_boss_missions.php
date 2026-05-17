<?php
// Run this script ONCE on the live server to fix any bugged boss missions
require_once 'db.php';
$db = get_db();

$bosses = [
    'Dragon' => 11,
    'De Rol Le' => 12,
    'Vol Opt' => 13,
    'Dark Falz' => 14,
    'Barba Ray' => 17,
    'Gol Dragon' => 16,
    'Gal Gryphon' => 15,
    'Olga Flow' => 18,
    'Saint-Million' => 19
];

$total_updated = 0;

foreach ($bosses as $name => $id) {
    // 1. Fix standard Boss missions (BOSS_ARENA, MENTOR_BOSS, HARDCORE_MENTOR, DIVERSE_PARTY_BOSS)
    $stmt = $db->prepare("UPDATE missions SET goal_target = :id WHERE goal_type IN ('BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS') AND goal_target = :name");
    $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->execute();
    $total_updated += $db->changes();

    // 2. Fix SPEEDRUN_BOSS missions (format: "BossName_TimeLimit")
    $res = $db->query("SELECT id, goal_target FROM missions WHERE goal_type = 'SPEEDRUN_BOSS'");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($row['goal_target'] === $name) {
            // It's purely "Dragon" with no time limit!
            $new_target = $id . '_300'; // Default 5 minutes
            
            $upd = $db->prepare("UPDATE missions SET goal_target = :new_target WHERE id = :id");
            $upd->bindValue(':new_target', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            $total_updated += $db->changes();
        } elseif (strpos($row['goal_target'], $name . '_') === 0) {
            // It's "Dragon_300"
            $parts = explode('_', $row['goal_target']);
            $time_limit = $parts[1] ?? 300;
            $new_target = $id . '_' . $time_limit;
            
            $upd = $db->prepare("UPDATE missions SET goal_target = :new_target WHERE id = :id");
            $upd->bindValue(':new_target', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            $total_updated += $db->changes();
        }
    }
}

// 3. Fix SPEEDRUN_FLOOR missions that lack underscores and contain text like "Cave 1 in under 23 minutes and 22 seconds"
$floor_map = [
    'Forest 1' => 1, 'Forest 2' => 2, 'Cave 1' => 3, 'Cave 2' => 4, 'Cave 3' => 5,
    'Mine 1' => 6, 'Mine 2' => 7, 'Ruins 1' => 8, 'Ruins 2' => 9, 'Ruins 3' => 10
];

$res = $db->query("SELECT id, goal_target FROM missions WHERE goal_type = 'SPEEDRUN_FLOOR'");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $parts = explode('_', $row['goal_target']);
    $floor_part = trim($parts[0]);
    $time_part = isset($parts[1]) ? (int)$parts[1] : null;

    if (!is_numeric($floor_part)) {
        $floor_name = "";
        $total_seconds = 1200; // Default
        
        // Example: "Cave 1 in under 23 minutes and 22 seconds"
        if (preg_match('/([a-zA-Z]+\s*\d*)\s+in under\s+(\d+)\s+min(?:ute)?s?(?: and\s+(\d+)\s+sec(?:ond)?s?)?/i', $floor_part, $matches)) {
            $floor_name = trim($matches[1]);
            $mins = (int)$matches[2];
            $secs = isset($matches[3]) ? (int)$matches[3] : 0;
            $total_seconds = ($mins * 60) + $secs;
        } else {
            // It might just be the literal floor name like "Cave 2" or "Cave 2_0"
            $floor_name = trim($floor_part);
            
            // If the time limit is valid (greater than 0), use it. Otherwise, assign a default.
            if ($time_part > 0) {
                $total_seconds = $time_part;
            } else {
                // Assign reasonable default times based on floor length
                $lower_name = strtolower($floor_name);
                if (strpos($lower_name, 'ruins') !== false || strpos($lower_name, 'cave 3') !== false || strpos($lower_name, 'mine 2') !== false) {
                    $total_seconds = 1800; // 30 minutes for long floors
                } elseif (strpos($lower_name, 'mine') !== false) {
                    $total_seconds = 1500; // 25 minutes
                } else {
                    $total_seconds = 1200; // 20 minutes for early floors
                }
            }
        }
        
        // Map string name back to integer ID
        foreach ($floor_map as $name => $fid) {
            if (strcasecmp($floor_name, $name) === 0) {
                $new_target = $fid . '_' . $total_seconds;
                
                $upd = $db->prepare("UPDATE missions SET goal_target = :new_target WHERE id = :id");
                $upd->bindValue(':new_target', $new_target, SQLITE3_TEXT);
                $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $upd->execute();
                $total_updated += $db->changes();
                break;
            }
        }
    }
}

echo "Migration Complete! Fixed $total_updated bugged mission rows.\n";
