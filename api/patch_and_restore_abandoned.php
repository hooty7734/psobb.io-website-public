<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Comprehensive DB Fix & Restore Abandoned Quests Script
 * --------------------------------------------------------------------------
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/db.php';
$db = get_db();

$floor_map = [
    'Forest 1' => 1, 'Forest 2' => 2, 'Cave 1' => 3, 'Cave 2' => 4, 'Cave 3' => 5,
    'Mine 1' => 6, 'Mine 2' => 7, 'Ruins 1' => 8, 'Ruins 2' => 9, 'Ruins 3' => 10
];

$boss_map = [
    'Dragon' => 11, 'De Rol Le' => 12, 'Vol Opt' => 13, 'Dark Falz' => 14, 
    'Barba Ray' => 14, 'Gol Dragon' => 15, 'Gal Gryphon' => 12, 'Olga Flow' => 13, 'Saint-Milion' => 9
];

$missions_repaired = 0;
$abandoned_restored = 0;

echo "==========================================================\n";
echo "Starting PSOBB Quest Database Repair & Restore Utility...\n";
echo "==========================================================\n\n";

// 1. Get all player missions that were abandoned to see if we can salvage them
$abandoned_res = $db->query("
    SELECT pm.id as player_mission_id, pm.account_id, pm.character_name, pm.status,
           m.id as mission_id, m.title, m.goal_type, m.goal_target 
    FROM player_missions pm
    JOIN missions m ON pm.mission_id = m.id
    WHERE pm.status = 'abandoned'
");

$abandoned_list = [];
if ($abandoned_res) {
    while ($row = $abandoned_res->fetchArray(SQLITE3_ASSOC)) {
        $abandoned_list[] = $row;
    }
}

echo "Found " . count($abandoned_list) . " abandoned player missions. Evaluating for bugs...\n\n";

// 2. Loop through and repair bugged missions
$all_missions_res = $db->query("SELECT id, title, goal_type, goal_target FROM missions");
$missions_to_check = [];
if ($all_missions_res) {
    while ($row = $all_missions_res->fetchArray(SQLITE3_ASSOC)) {
        $missions_to_check[] = $row;
    }
}

foreach ($missions_to_check as $m) {
    $mid = $m['id'];
    $title = $m['title'];
    $goal_type = $m['goal_type'];
    $goal_target = trim($m['goal_target']);
    $repaired_target = null;

    // A. Fix EXPLORATION and PATROL
    if (in_array($goal_type, ['EXPLORATION', 'PATROL']) && !is_numeric($goal_target)) {
        foreach ($floor_map as $name => $fid) {
            if (strcasecmp($goal_target, $name) === 0 || strpos(strtolower($goal_target), strtolower($name)) !== false) {
                $repaired_target = (string)$fid;
                break;
            }
        }
    }

    // B. Fix standard Boss missions (BOSS_ARENA, MENTOR_BOSS, HARDCORE_MENTOR, DIVERSE_PARTY_BOSS)
    elseif (in_array($goal_type, ['BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS']) && !is_numeric($goal_target) && $goal_target !== 'ANY_DRAGON') {
        foreach ($boss_map as $name => $bid) {
            if (strcasecmp($goal_target, $name) === 0 || strpos(strtolower($goal_target), strtolower($name)) !== false) {
                $repaired_target = (string)$bid;
                break;
            }
        }
    }

    // C. Fix SPEEDRUN_FLOOR
    elseif ($goal_type === 'SPEEDRUN_FLOOR') {
        $parts = explode('_', $goal_target);
        $floor_part = trim($parts[0]);
        $time_part = isset($parts[1]) ? (int)$parts[1] : 0;

        if (!is_numeric($floor_part)) {
            $floor_name = "";
            $total_seconds = 1200; // default
            
            if (preg_match('/([a-zA-Z]+\s*\d*)\s+in under\s+(\d+)\s+min(?:ute)?s?(?: and\s+(\d+)\s+sec(?:ond)?s?)?/i', $floor_part, $matches)) {
                $floor_name = trim($matches[1]);
                $mins = (int)$matches[2];
                $secs = isset($matches[3]) ? (int)$matches[3] : 0;
                $total_seconds = ($mins * 60) + $secs;
            } else {
                $floor_name = trim($floor_part);
                if ($time_part > 0) $total_seconds = $time_part;
            }

            foreach ($floor_map as $name => $fid) {
                if (strcasecmp($floor_name, $name) === 0 || strpos(strtolower($floor_name), strtolower($name)) !== false) {
                    $repaired_target = $fid . '_' . $total_seconds;
                    break;
                }
            }
        }
    }

    // D. Fix SPEEDRUN_BOSS
    elseif ($goal_type === 'SPEEDRUN_BOSS') {
        $parts = explode('_', $goal_target);
        
        // Case 1: Pure number with NO underscore (e.g. "182", "297") - Only time limit was entered
        if (count($parts) === 1 && is_numeric($goal_target)) {
            $time_limit = (int)$goal_target < 600 ? 600 : (int)$goal_target;
            $repaired_target = '11_' . $time_limit; // Fallback to Dragon
        }
        
        // Case 2: Underscore present but boss part is non-numeric string
        elseif (count($parts) >= 1 && !is_numeric($parts[0])) {
            $boss_part = trim($parts[0]);
            $total_seconds = isset($parts[1]) ? (int)$parts[1] : 0;
            $boss_name = "";

            if (preg_match('/([a-zA-Z\s]+?)\s*(?:in )?under\s+(\d+)\s+min(?:ute)?s?(?: and\s+(\d+)\s+sec(?:ond)?s?)?/i', $boss_part, $matches)) {
                $boss_name = trim($matches[1]);
                $mins = (int)$matches[2];
                $secs = isset($matches[3]) ? (int)$matches[3] : 0;
                $total_seconds = ($mins * 60) + $secs;
            } else {
                $boss_name = trim($boss_part);
            }

            foreach ($boss_map as $name => $bid) {
                if (strpos(strtolower($boss_name), strtolower($name)) !== false) {
                    if ($total_seconds < 600) $total_seconds = 600;
                    $repaired_target = $bid . '_' . $total_seconds;
                    break;
                }
            }
        }
    }

    // E. Convert unambiguous legacy synthetic targets to real newserv floor IDs.
    // Floor 15 is NOT mapped because it's a valid real ID (Gol Dragon, Ep2 0x0F).
    // Old synthetic 15 (Gal Gryphon) requires context-aware disambiguation — see repair_floor_ids.php.
    if (in_array($goal_type, ['BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS'])) {
        if (is_numeric($goal_target)) {
            $val = (int)$goal_target;
            if ($val === 16) $repaired_target = '15';        // Gol Dragon (synthetic 16 → real 15)
            elseif ($val === 17) $repaired_target = '14';    // Barba Ray (synthetic 17 → real 14)
            elseif ($val === 18) $repaired_target = '13';    // Olga Flow (synthetic 18 → real 13)
            elseif ($val === 19) $repaired_target = '9';     // Saint-Milion (synthetic 19 → real 9)
        }
    }
    elseif ($goal_type === 'SPEEDRUN_BOSS') {
        $parts = explode('_', $goal_target);
        if (count($parts) >= 2 && is_numeric($parts[0])) {
            $val = (int)$parts[0];
            $time_limit = (int)$parts[1];
            $mapped_floor = null;
            if ($val === 16) $mapped_floor = 15;
            elseif ($val === 17) $mapped_floor = 14;
            elseif ($val === 18) $mapped_floor = 13;
            elseif ($val === 19) $mapped_floor = 9;
            
            if ($mapped_floor !== null) {
                $repaired_target = $mapped_floor . '_' . $time_limit;
            }
        }
    }

    // If we repaired the target format, update it in the missions table
    if ($repaired_target !== null) {
        $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
        $upd->bindValue(':t', $repaired_target, SQLITE3_TEXT);
        $upd->bindValue(':id', $mid, SQLITE3_INTEGER);
        $upd->execute();
        $missions_repaired++;

        echo "[FIX] Repaired Mission ID $mid ($title) | Original target: '$goal_target' -> Fixed: '$repaired_target'\n";

        // Now, find all player_missions associated with this repaired mission.
        // If it was abandoned, we RESTORE it to 'in_progress'!
        $restore_stmt = $db->prepare("
            UPDATE player_missions 
            SET status = 'in_progress' 
            WHERE mission_id = :mid AND status = 'abandoned'
        ");
        $restore_stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
        $restore_stmt->execute();
        $restored_count = $db->changes();

        if ($restored_count > 0) {
            $abandoned_restored += $restored_count;
            echo "  --> [RESTORED] Changed $restored_count abandoned instance(s) of this quest back to 'in_progress'!\n";
        }
    }
}

echo "\n==========================================================\n";
echo "Database Repair Completed Successfully!\n";
echo "Missions repaired in lookup table: $missions_repaired\n";
echo "Abandoned player bounties restored back to active: $abandoned_restored\n";
echo "==========================================================\n";
