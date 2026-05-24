<?php
// Comprehensive patch script to fix ALL broken mission targets
require_once 'db.php';
require_once 'reward_tables.php';
$db = get_db();

$total_updated = 0;

// Shared mappings
$floor_map = [
    'Forest 1' => 1, 'Forest 2' => 2, 'Cave 1' => 3, 'Cave 2' => 4, 'Cave 3' => 5,
    'Mine 1' => 6, 'Mine 2' => 7, 'Ruins 1' => 8, 'Ruins 2' => 9, 'Ruins 3' => 10
];

// Real newserv floor IDs (StaticGameData.cc floor_defs).
// Ep2 bosses share the same floor numbers as Ep1 bosses; episode context disambiguates.
$boss_map = [
    'Dragon' => 11, 'De Rol Le' => 12, 'Vol Opt' => 13, 'Dark Falz' => 14, 
    'Barba Ray' => 14, 'Gol Dragon' => 15, 'Gal Gryphon' => 12, 'Olga Flow' => 13, 'Saint-Milion' => 9
];

// 1. Fix EXPLORATION and PATROL
$res = $db->query("SELECT id, goal_target FROM missions WHERE goal_type IN ('EXPLORATION', 'PATROL')");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    if (!is_numeric($row['goal_target'])) {
        $floor_name = trim($row['goal_target']);
        foreach ($floor_map as $name => $fid) {
            if (strcasecmp($floor_name, $name) === 0) {
                $upd = $db->prepare("UPDATE missions SET goal_target = :fid WHERE id = :id");
                $upd->bindValue(':fid', $fid, SQLITE3_TEXT);
                $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $upd->execute();
                $total_updated += $db->changes();
                break;
            }
        }
    }
}

// 2. Fix SPEEDRUN_FLOOR
$res = $db->query("SELECT id, goal_target FROM missions WHERE goal_type = 'SPEEDRUN_FLOOR'");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $parts = explode('_', $row['goal_target']);
    $floor_part = trim($parts[0]);
    $time_part = isset($parts[1]) ? (int)$parts[1] : null;

    if (!is_numeric($floor_part)) {
        $floor_name = "";
        $total_seconds = 0;
        
        // Extract string data
        if (preg_match('/([a-zA-Z]+\s*\d*)\s+in under\s+(\d+)\s+min(?:ute)?s?(?: and\s+(\d+)\s+sec(?:ond)?s?)?/i', $floor_part, $matches)) {
            $floor_name = trim($matches[1]);
            $mins = (int)$matches[2];
            $secs = isset($matches[3]) ? (int)$matches[3] : 0;
            $total_seconds = ($mins * 60) + $secs;
        } else {
            $floor_name = trim($floor_part);
            $total_seconds = $time_part; // might be null
        }
        
        // Match mapping
        foreach ($floor_map as $name => $fid) {
            if (strcasecmp($floor_name, $name) === 0) {
                if (!$total_seconds || $total_seconds <= 0) {
                    $lower_name = strtolower($floor_name);
                    if (strpos($lower_name, 'ruins') !== false || strpos($lower_name, 'cave 3') !== false || strpos($lower_name, 'mine 2') !== false) {
                        $total_seconds = 1800; // 30 minutes
                    } elseif (strpos($lower_name, 'mine') !== false) {
                        $total_seconds = 1500; // 25 minutes
                    } else {
                        $total_seconds = 1200; // 20 minutes
                    }
                }
                
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

// 3. Fix SPEEDRUN_BOSS
$res = $db->query("SELECT id, goal_target FROM missions WHERE goal_type = 'SPEEDRUN_BOSS'");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $target = trim($row['goal_target']);
    $parts = explode('_', $target);
    
    // Case 1: Pure number with NO underscore (e.g. "182", "297") - The AI only output the time!
    if (count($parts) === 1 && is_numeric($target)) {
        // We have to randomly assign a boss since we don't know who it was. Assign Dragon (11).
        $time_limit = (int)$target < 600 ? 600 : (int)$target;
        $new_target = '11_' . $time_limit;
        $upd = $db->prepare("UPDATE missions SET goal_target = :new_target WHERE id = :id");
        $upd->bindValue(':new_target', $new_target, SQLITE3_TEXT);
        $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $upd->execute();
        $total_updated += $db->changes();
        continue;
    }
    
    // Case 1.5: Valid format (e.g. "12_300") but time is under 10 minutes
    if (count($parts) >= 2 && is_numeric($parts[0])) {
        $time_limit = (int)$parts[1];
        if ($time_limit < 600) {
            $new_target = $parts[0] . '_600';
            $upd = $db->prepare("UPDATE missions SET goal_target = :new_target WHERE id = :id");
            $upd->bindValue(':new_target', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            $total_updated += $db->changes();
        }
        continue;
    }
    
    // Case 2: The Boss part is a string instead of ID (e.g. "Vol Opt in under 7 minutes...", "Dragon")
    $boss_part = trim($parts[0]);
    if (!is_numeric($boss_part)) {
        $boss_name = "";
        $total_seconds = isset($parts[1]) ? (int)$parts[1] : 0;
        
        // Extract string data (handles "Vol Opt in under 7 minutes and 32 seconds" OR "Dragon under 6 minutes and 20 seconds")
        if (preg_match('/([a-zA-Z\s]+?)\s*(?:in )?under\s+(\d+)\s+min(?:ute)?s?(?: and\s+(\d+)\s+sec(?:ond)?s?)?/i', $boss_part, $matches)) {
            $boss_name = trim($matches[1]);
            $mins = (int)$matches[2];
            $secs = isset($matches[3]) ? (int)$matches[3] : 0;
            $total_seconds = ($mins * 60) + $secs;
        } else {
            $boss_name = trim($boss_part);
        }
        
        // Match mapping
        foreach ($boss_map as $name => $bid) {
            if (strpos(strtolower($boss_name), strtolower($name)) !== false) {
                if (!$total_seconds || $total_seconds < 600) {
                    $total_seconds = 600; // 10 minute minimum
                }
                
                $new_target = $bid . '_' . $total_seconds;
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

// 4. Fix Invalid/Hallucinated Reward Strings
$res = $db->query("SELECT id, reward_item_string FROM missions");
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $reward_string = trim($row['reward_item_string']);
    $segments = explode(',', $reward_string);
    $is_valid = true;
    $needs_clean = false;
    $clean_segments = [];
    
    foreach ($segments as $segment) {
        $segment = trim($segment);
        if (empty($segment)) continue;
        
        // Valid Meseta string
        if (preg_match('/^\d+\s+meseta$/i', $segment)) {
            $clean_segments[] = $segment;
            continue;
        }
        
        // Valid Hex String (starting with 6 or 32 character hex)
        $first_part = explode(' ', $segment)[0];
        if (ctype_xdigit($first_part) && (strlen($first_part) === 6 || strlen($first_part) >= 32)) {
            // Strip "+Xevp" and "+Xdef" from Units (hex starts with 0103)
            if (strpos($first_part, '0103') === 0) {
                $cleaned = preg_replace('/\s+\+\d+(?:def|evp)/i', '', $segment);
                if ($cleaned !== $segment) {
                    $needs_clean = true;
                    $segment = $cleaned;
                }
            }
            $clean_segments[] = $segment;
            continue;
        }
        
        // Valid legacy untekked weapons (renderRewardString expects this, but parse_and_drop_items DOES NOT)
        // Since we know parse_and_drop_items fails on these if they aren't in item_hex.txt, we consider them invalid.
        
        // Otherwise, it's invalid!
        $is_valid = false;
        break;
    }
    
    if (!$is_valid) {
        // Regenerate a generic safe reward using the new reward tables logic
        // E.g., 2000-5000 Meseta and a common random material/grinder
        $new_reward = get_common_reward_item(80, 'HUmar', 'Random') . ", " . (mt_rand(2, 5) * 1000) . " Meseta";
        
        $upd = $db->prepare("UPDATE missions SET reward_item_string = :ri WHERE id = :id");
        $upd->bindValue(':ri', $new_reward, SQLITE3_TEXT);
        $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $upd->execute();
        $total_updated += $db->changes();
    } elseif ($needs_clean) {
        // Update the database with the cleaned string
        $new_reward = implode(', ', $clean_segments);
        $upd = $db->prepare("UPDATE missions SET reward_item_string = :ri WHERE id = :id");
        $upd->bindValue(':ri', $new_reward, SQLITE3_TEXT);
        $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $upd->execute();
        $total_updated += $db->changes();
    }
}

echo "Comprehensive Migration Complete! Fixed $total_updated bugged mission rows.\n";
