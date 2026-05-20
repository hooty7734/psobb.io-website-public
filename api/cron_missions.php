<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Mission Cron Job Pipeline
 * --------------------------------------------------------------------------
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reward_tables.php';

// Enforce CLI execution.
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

$db = get_db();

// Ensure only one instance of the daemon runs at a time
$lock_file = fopen(__DIR__ . '/../db/.cron_missions.lock', 'c');
if (!flock($lock_file, LOCK_EX | LOCK_NB)) {
    echo "[CRON] Another instance is running. Exiting.\n";
    exit;
}

// Load shared utility functions (send_personal_mail, etc.)
require_once __DIR__ . '/functions.php';


if (empty($GEMINI_API_KEY)) {
    echo "[CRON] Error: GEMINI_API_KEY is not defined in .env. Cannot generate new quests.\n";
}

// Fetch the last 15 active/recent mission titles to prevent AI trope repetition
$recent_missions = [];
$rm_res = $db->query("SELECT title FROM missions ORDER BY id DESC LIMIT 15");
if ($rm_res) {
    while ($row = $rm_res->fetchArray(SQLITE3_ASSOC)) {
        $recent_missions[] = $row['title'];
    }
}
$recent_missions_str = empty($recent_missions) ? "None" : implode(", ", $recent_missions);

// State cache file path (defined once, read/written inside loop)
$state_cache_file = __DIR__ . '/../db/.cron_player_state.json';

$script_start = time();
// Loop for up to 55 seconds to fit within a standard 1-minute crontab resolution
while (time() - $script_start < 55) {
    // 1. Fetch live clients
    $url = $NEWSERV_API_URL . "/y/clients";
    $data = @file_get_contents($url);
    if (!$data) {
        echo "[CRON] Failed to connect to newserv API\n";
        exit;
    }

    $clients = json_decode($data, true);

    if (!is_array($clients) || empty($clients)) {
        sleep(10);
        continue;
    }

    echo "[CRON] Processing " . count($clients) . " clients...\n";

    // Load state cache each tick to calculate real-time deltas
    $player_states = file_exists($state_cache_file) ? json_decode(file_get_contents($state_cache_file), true) : [];
    if (!is_array($player_states)) $player_states = [];

// 2. Iterate through all currently active players.
foreach ($clients as $client) {
    if (!isset($client['Account']['AccountID'])) continue;
    $accId = (string)$client['Account']['AccountID'];
    
    $charName = $client['Name'] ?? 'Unknown';
    $stateKey = $accId . '_' . $charName;
    
    // Skip players who are still connecting/loading (no character data yet)
    if (!isset($client['EXP'])) continue;
    $current_exp = $client['EXP'] ?? 0;
    $current_item_count = count($client['InventoryItems'] ?? []);
    $just_logged_in = !isset($player_states[$stateKey]);
    $prev_state = $player_states[$stateKey] ?? [];
    $prev_exp = $prev_state['exp'] ?? $current_exp;
    $prev_items = $prev_state['items'] ?? $current_item_count;
    $curr_level = $client['Level'] ?? 1;
    $prev_level = $prev_state['level'] ?? $curr_level;

    // Prevent false level-ups when level temporarily drops during loading screens
    // or when switching to a lower level character on the same account
    if ($curr_level < $prev_level) {
        $prev_level = $curr_level;
    }
    $curr_f = (int)($client['LocationFloor'] ?? -1);
    $prev_f = (int)($prev_state['floor'] ?? -1);
    
    $floor_entered_time = ($curr_f === $prev_f) ? ($prev_state['floor_entered_time'] ?? time()) : time();
    
    // Protect against Zero-EXP spikes during loading screens where NewServ briefly reports 0 EXP.
    // If we don't skip this tick, $prev_exp gets saved as 0, and the NEXT tick will show a massive delta!
    if ($current_exp < $prev_exp && $current_exp === 0) {
        continue;
    }
    
    // Delayed EXP Sync Memory
    $last_boss_arena = $prev_state['last_boss_arena'] ?? null;
    $last_boss_arena_time = $prev_state['last_boss_arena_time'] ?? 0;
    // Track when a player is in a boss arena for delayed EXP sync detection.
    // These are the RAW LocationFloor values as reported by newserv /y/clients:
    //   Ep1: 11=Dragon, 12=De Rol Le, 13=Vol Opt, 14=Dark Falz
    //   Ep2: 12=Gal Gryphon, 13=Olga Flow, 14=Barba Ray, 15=Gol Dragon
    //   Ep4: 9=Saint-Million
    // Note: Floor IDs overlap across episodes (e.g. 12 = De Rol Le in Ep1, Gal Gryphon in Ep2).
    // This is acceptable because we only use these for EXP-delta-based kill detection.
    if (in_array($curr_f, [11, 12, 13, 14, 15, 9])) {
        $last_boss_arena = $curr_f;
        $last_boss_arena_time = time();
    }

    // Track the last non-boss floor the player was on.
    // This is critical for episode disambiguation: when the player enters a boss room
    // (which reuses floor IDs across episodes), we need to know which dungeon they came from.
    $boss_floors = [9, 11, 12, 13, 14, 15];
    $pre_boss_floor = $prev_state['pre_boss_floor'] ?? -1;
    if (!in_array($curr_f, $boss_floors) && $curr_f >= 0) {
        $pre_boss_floor = $curr_f;
    }

    // =====================================================================
    // NEWSERV FLOOR ID REFERENCE (from /y/clients -> LocationFloor)
    // Each episode reuses the same floor numbering starting from 0.
    // =====================================================================
    //  ID | Episode 1       | Episode 2       | Episode 4
    // ----+-----------------+-----------------+-----------------
    //   0 | Pioneer 2       | Lab             | Pioneer 2
    //   1 | Forest 1        | VR Temple Alpha | Crater Route 1
    //   2 | Forest 2        | VR Temple Beta  | Crater Route 2
    //   3 | Cave 1          | VR Ship Alpha   | Crater Route 3
    //   4 | Cave 2          | VR Ship Beta    | Crater Route 4
    //   5 | Cave 3          | CCA             | Crater Interior
    //   6 | Mine 1          | Jungle North    | Desert 1
    //   7 | Mine 2          | Jungle South    | Desert 2
    //   8 | Ruins 1         | Mountain        | Desert 3
    //   9 | Ruins 2         | Seaside         | *Saint-Million*
    //  10 | Ruins 3         | Seabed Upper    | —
    //  11 | *Dragon*        | Seabed Lower    | *Sil Dragon*
    //  12 | *De Rol Le*     | *Gal Gryphon*   | —
    //  13 | *Vol Opt*       | *Olga Flow*     | —
    //  14 | *Dark Falz*     | *Barba Ray*     | —
    //  15 | —               | *Gol Dragon*    | —
    // =====================================================================

    // Fetch user language preference (must be before milestone notifications)
    $lang_stmt = $db->prepare("SELECT language FROM users WHERE account_id = :acc");
    $lang_stmt->bindValue(':acc', $accId, SQLITE3_INTEGER);
    $lang_res = $lang_stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $user_lang = $lang_res ? $lang_res['language'] : 'en';

    // Feature: Automatic Milestone Unlock Notifications
    if ($curr_level > $prev_level) {
        $unlocked_milestones = [];
        for ($lvl = $prev_level + 1; $lvl <= $curr_level; $lvl++) {
            if ($lvl >= 5 && $lvl % 5 === 0) {
                $unlocked_milestones[] = $lvl;
            }
        }
        if (!empty($unlocked_milestones)) {
            $max_m = end($unlocked_milestones);
            if ($user_lang === 'jp') {
                $msg = ($client['Name'] ?? 'ハンター') . " おめでとうございます！\nLv{$max_m}のマイルストーン報酬をアンロックしました！\npsobb.ioで受け取ってください。";
                send_personal_mail($accId, "ハンターズギルド", $msg);
            } else {
                $msg = "Congrats " . ($client['Name'] ?? 'Hunter') . "!\nYou unlocked a Lv{$max_m} Milestone Reward!\nRedeem it on the website: psobb.io";
                send_personal_mail($accId, "Hunters Guild", $msg);
            }
        }
    }

    // Feature: Automatic Daily Login Streak
    $streak_stmt = $db->prepare("INSERT OR IGNORE INTO daily_logins (account_id, login_date) VALUES (:aid, :date)");
    $streak_stmt->bindValue(':aid', $accId, SQLITE3_INTEGER);
    $streak_stmt->bindValue(':date', date('Y-m-d'), SQLITE3_TEXT);
    $streak_stmt->execute();

    // Feature: Unclaimed Rewards Login Notification
    if ($just_logged_in) {
        $today = date('Y-m-d');
        // 1. Check for unclaimed daily reward
        $stmt_daily = $db->prepare("SELECT COUNT(*) FROM daily_rewards WHERE account_id = :aid AND claim_date = :date");
        $stmt_daily->bindValue(':aid', $accId, SQLITE3_INTEGER);
        $stmt_daily->bindValue(':date', $today, SQLITE3_TEXT);
        $has_claimed_daily = $stmt_daily->execute()->fetchArray(SQLITE3_NUM)[0] > 0;

        // 2. Check for unclaimed level milestones
        $max_milestones = floor($curr_level / 5);
        $stmt_ms = $db->prepare("SELECT COUNT(*) FROM rewards_claimed WHERE account_id = :aid AND character_name = :cname");
        $stmt_ms->bindValue(':aid', $accId, SQLITE3_INTEGER);
        $stmt_ms->bindValue(':cname', $charName, SQLITE3_TEXT);
        $claimed_milestones = $stmt_ms->execute()->fetchArray(SQLITE3_NUM)[0];
        $has_unclaimed_ms = $max_milestones > $claimed_milestones;

        if (!$has_claimed_daily || $has_unclaimed_ms) {
            if ($user_lang === 'jp') {
                $msg = "ようこそ！\npsobb.ioで受け取っていない報酬があります。\nぜひ確認してください！";
                send_personal_mail($accId, "ハンターズギルド", $msg);
            } else {
                $msg = "Welcome back!\nYou have rewards waiting to be claimed on psobb.io.\nDon't forget to check them!";
                send_personal_mail($accId, "Hunters Guild", $msg);
            }
        }
    }

    // 3. Fetch all active "in-progress" missions for the CURRENT CHARACTER.
    // If character_name is null/empty (legacy missions), it will still be evaluated for backwards compatibility.
    // However, new missions will strictly be tied to a character.
    $stmt = $db->prepare("SELECT pm.id, pm.mission_id, u.discord_id, pm.character_name, m.title AS mission_title, m.goal_type, m.goal_target, m.reward_item_string 
                          FROM player_missions pm 
                          JOIN missions m ON pm.mission_id = m.id 
                          JOIN users u ON pm.account_id = u.account_id
                          WHERE pm.account_id = :acc AND pm.status = 'in_progress'
                          AND (pm.character_name = :cname OR pm.character_name IS NULL OR pm.character_name = '')");
    $stmt->bindValue(':acc', $accId, SQLITE3_INTEGER);
    $stmt->bindValue(':cname', $charName, SQLITE3_TEXT);
    $res = $stmt->execute();
    
    $has_active_missions = false; 
    $active_mission_count = 0;
    $completed_any = false;
    $last_completed_type = "training";
    $current_patrols = [];
    $active_mission_types = [];

    while ($m = $res->fetchArray(SQLITE3_ASSOC)) {
        $has_active_missions = true;
        $active_mission_count++;
        $active_mission_types[] = $m['goal_type'];
        $completed = false;
        
        // 4. Mission Evaluation Engine
        if ($m['goal_type'] === 'MESETA' && ($client['Meseta'] ?? 0) >= (int)$m['goal_target']) {
            $completed = true;
        } elseif ($m['goal_type'] === 'LEVEL' && ($client['Level'] ?? 1) >= (int)$m['goal_target']) {
            $completed = true;
        } elseif ($m['goal_type'] === 'PLAYTIME' && ($client['PlayTimeSeconds'] ?? 0) >= (int)$m['goal_target']) {
            $completed = true;
        } elseif ($m['goal_type'] === 'ITEM') {
            $inventory = $client['InventoryItems'] ?? [];
            $target_val = trim((string)$m['goal_target']);
            $found_match = false;
            
            // Map of generic weapon types to their Hex IDs for backwards compatibility
            $generic_weapon_hex_map = [
                'Saber' => '000100', 'Brand' => '000101', 'Buster' => '000102', 'Pallasch' => '000103', 'Gladius' => '000104',
                'Sword' => '000200', 'Gigush' => '000201', 'Breaker' => '000202', 'Claymore' => '000203', 'Calibur' => '000204',
                'Dagger' => '000300', 'Knife' => '000301', 'Blade' => '000302', 'Edge' => '000303', 'Ripper' => '000304',
                'Partisan' => '000400', 'Halbert' => '000401', 'Glaive' => '000402', 'Berdys' => '000403', 'Gungnir' => '000404',
                'Slicer' => '000500', 'Spinner' => '000501', 'Cutter' => '000502', 'Sawcer' => '000503', 'Diska' => '000504',
                'Handgun' => '000600', 'Autogun' => '000601', 'Lockgun' => '000602', 'Railgun' => '000603', 'Raygun' => '000604',
                'Rifle' => '000700', 'Sniper' => '000701', 'Blaster' => '000702', 'Beam' => '000703', 'Laser' => '000704',
                'Mechgun' => '000800', 'Assault' => '000801', 'Repeater' => '000802', 'Gatling' => '000803', 'Vulcan' => '000804',
                'Shot' => '000900', 'Spread' => '000901', 'Cannon' => '000902', 'Launcher' => '000903', 'Arms' => '000904',
                'Cane' => '000A00', 'Stick' => '000A01', 'Mace' => '000A02', 'Club' => '000A03',
                'Rod' => '000B00', 'Pole' => '000B01', 'Pillar' => '000B02', 'Striker' => '000B03',
                'Wand' => '000C00', 'Staff' => '000C01', 'Baton' => '000C02', 'Scepter' => '000C03',
                'Talis' => '008C00', 'Mahu' => '008C01', 'Hitogata' => '008C02'
            ];
            
            // Backwards compatibility for existing string-based missions (e.g. 'Stick')
            if (!ctype_xdigit($target_val)) {
                $target_val = $generic_weapon_hex_map[$target_val] ?? null;
            } else {
                // Remove any modifiers from the hex if present
                $target_val = explode(' ', $target_val)[0];
            }
            
            if ($target_val) {
                foreach ($inventory as $inv_item) {
                    if (isset($inv_item['Data']) && strpos($inv_item['Data'], $target_val) === 0) {
                        $completed = true;
                        $found_match = true;
                        break;
                    }
                }
            }
        } elseif ($m['goal_type'] === 'TECHNIQUE') {
            $tech_levels = $client['TechniqueLevels'] ?? [];
            foreach ($tech_levels as $tech_name => $level) {
                if ($level !== null && strpos(strtolower($tech_name), strtolower((string)$m['goal_target'])) !== false) {
                    $completed = true;
                    break;
                }
            }
        } elseif ($m['goal_type'] === 'BATTLE_WINS') {
            $battle_counts = $client['BattlePlaceCounts'] ?? [0,0,0,0];
            if (($battle_counts[0] ?? 0) >= (int)$m['goal_target']) {
                $completed = true;
            }
        } elseif ($m['goal_type'] === 'CHALLENGE_STAGES') {
            $c_times = array_merge($client['ChallengeTimesEp1Online'] ?? [], $client['ChallengeTimesEp2Online'] ?? []);
            if (count($c_times) >= (int)$m['goal_target']) {
                $completed = true;
            }
        } elseif (strpos($m['goal_type'], 'MAT') === 0) {
            $mat_map = [
                'MAT_HP' => 'NumHPMaterialsUsed',
                'MAT_TP' => 'NumTPMaterialsUsed',
                'MAT_POWER' => 'NumPowerMaterialsUsed',
                'MAT_DEF' => 'NumDefMaterialsUsed',
                'MAT_MIND' => 'NumMindMaterialsUsed',
                'MAT_EVADE' => 'NumEvadeMaterialsUsed',
                'MAT_LUCK' => 'NumLuckMaterialsUsed'
            ];
            $key = $mat_map[$m['goal_type']] ?? null;
            if ($key && ($client[$key] ?? 0) >= (int)$m['goal_target']) {
                $completed = true;
            }
        } elseif ($m['goal_type'] === 'PATROL') { 
            if (($client['LocationFloor'] ?? -1) === (int)$m['goal_target']) {
                $patrol_progress = ($prev_state['patrol'][$m['id']] ?? 0) + 1;
                $current_patrols[$m['id']] = $patrol_progress;
                if ($patrol_progress >= 10) {
                    $completed = true;
                }
            } else {
                $current_patrols[$m['id']] = $prev_state['patrol'][$m['id']] ?? 0;
            }
        } elseif ($m['goal_type'] === 'EXPLORATION') {
            // LocationFloor tracks exactly which area the player is currently occupying
            if (($client['LocationFloor'] ?? -1) === (int)$m['goal_target']) {
                $completed = true;
            }
        } elseif ($m['goal_type'] === 'BOSS_ARENA' || $m['goal_type'] === 'MENTOR_BOSS' || $m['goal_type'] === 'HARDCORE_MENTOR' || $m['goal_type'] === 'DIVERSE_PARTY_BOSS') { 
            // Require them to be physically inside the boss room, OR have been in the boss room last minute,
            // AND show a massive positive delta in Experience (Normal Dragon yields 350 EXP minimum) 
            // OR if max-level, dynamically verify they physically looted a drop box generated by the boss!
            $original_target = $m['goal_target'];
            $target_floor = $m['goal_target'];
            $recent_boss_fight = false;
            $prev_f = (int)($prev_state['floor'] ?? -1);
            $was_fast_kill = false;

            // =====================================================================
            // EPISODE-AWARE PRECEDING FLOOR VALIDATION
            // Floor IDs collide across episodes (e.g., Floor 13 = Vol Opt in Ep1 AND Olga Flow in Ep2).
            // To prevent cross-episode false completions, we validate the player's pre-boss floor
            // against episode-specific dungeon paths. Keyed by ORIGINAL target ID (before remapping).
            //
            //   Target 11 (Dragon, Ep1):        Must come from Forest (1-2)
            //   Target 12 (De Rol Le, Ep1):     Must come from Cave (3-5)
            //   Target 13 (Vol Opt, Ep1):       Must come from Mine (6-7)
            //   Target 14 (Dark Falz, Ep1):     Must come from Ruins (8-10)
            //   Target 15 (Gal Gryphon, Ep2):   Must come from CCA/Jungle/Mtn/Seaside (5-9)
            //   Target 16 (Gol Dragon, Ep2):    Must come from VR Ship (3-4)
            //   Target 17 (Barba Ray, Ep2):     Must come from VR Temple (1-2)
            //   Target 18 (Olga Flow, Ep2):     Must come from Seabed (10-11)
            //   Target 19 (Saint-Million, Ep4): Must come from Desert/Crater (5-8)
            //   ANY_DRAGON:                     Forest (1-2) or VR Ship (3-4) or Crater (1-8)
            // =====================================================================
            $valid_preceding_floors = [
                '11' => [1, 2],           // Dragon: Forest 1-2
                '12' => [3, 4, 5],        // De Rol Le: Cave 1-3
                '13' => [6, 7],           // Vol Opt: Mine 1-2
                '14' => [8, 9, 10],       // Dark Falz: Ruins 1-3
                '15' => [5, 6, 7, 8, 9],  // Gal Gryphon: CCA, Jungle, Mountain, Seaside
                '16' => [3, 4],           // Gol Dragon: VR Ship Alpha/Beta
                '17' => [1, 2],           // Barba Ray: VR Temple Alpha/Beta
                '18' => [10, 11],         // Olga Flow: Seabed Upper/Lower
                '19' => [5, 6, 7, 8],     // Saint-Million: Crater Interior / Desert
            ];

            $fast_kill_preceding = [
                11 => [2], // Dragon from Forest 2
                12 => [5, 6, 7, 8, 9], // De Rol Le from Cave 3, Gal Gryphon from CCA
                13 => [7, 11], // Vol Opt from Mine 2, Olga Flow from Seabed Lower
                14 => [10, 2], // Dark Falz from Ruins 3, Barba Ray from Temple Beta
                15 => [4], // Gol Dragon from Spaceship Beta
                9  => [8], // Saint-Million from Crater Interior
            ];

            if ($target_floor === 'ANY_DRAGON') {
                $dragon_floors = [11, 15]; // 11 = Ep1 Dragon + Ep4 Sil Dragon, 15 = Ep2 Gol Dragon
                $recent_boss_fight = in_array($curr_f, $dragon_floors) || in_array($prev_f, $dragon_floors);
                
                // Fast-Kill Race Condition Fix:
                if (in_array($prev_f, [2, 4]) && $curr_f !== $prev_f && !in_array($curr_f, $dragon_floors)) {
                    $was_fast_kill = true;
                }
                
                if (in_array($last_boss_arena, $dragon_floors) && (time() - $last_boss_arena_time < 120)) {
                    $was_fast_kill = true;
                }
                $recent_boss_fight = $recent_boss_fight || $was_fast_kill;
                
                // Episode validation for ANY_DRAGON: pre_boss_floor must be from Forest, VR Ship, or Crater
                $valid_dragon_preceding = array_merge([1, 2], [3, 4], [5, 6, 7, 8]);
                if ($recent_boss_fight && $pre_boss_floor >= 0 && !in_array($pre_boss_floor, $valid_dragon_preceding)) {
                    echo "[CRON] ANY_DRAGON rejected: pre_boss_floor={$pre_boss_floor} not valid for any dragon\n";
                    $recent_boss_fight = false;
                }
            } else {
                $target_floor = (int)$target_floor;
                // Map Episode 2/4 Boss "Fake" Floor IDs back to actual PSO Client Floor IDs
                $mapped_floor = $target_floor;
                if ($target_floor === 15) $mapped_floor = 12; // Gal Gryphon -> De Rol Le Floor
                elseif ($target_floor === 16) $mapped_floor = 15; // Gol Dragon -> VR Spaceship Final
                elseif ($target_floor === 17) $mapped_floor = 14; // Barba Ray -> Dark Falz Floor
                elseif ($target_floor === 18) $mapped_floor = 13; // Olga Flow -> Vol Opt Floor
                elseif ($target_floor === 19) $mapped_floor = 9;  // Saint-Million -> Meteor Impact Site

                // Catch players who enter the boss arena, kill the boss, and warp to town all within the 60-second cron window.
                if (isset($fast_kill_preceding[$mapped_floor]) && in_array($prev_f, $fast_kill_preceding[$mapped_floor])) {
                    if ($curr_f !== $prev_f && $curr_f !== $mapped_floor) {
                        $was_fast_kill = true;
                    }
                }
                $recent_boss_fight = ($curr_f === $mapped_floor) || ($prev_f === $mapped_floor) || $was_fast_kill;
                
                // Fix for Delayed EXP Syncs: If the player was in the target boss arena within the last 2 minutes, 
                // any subsequent massive EXP spike is attributed to that boss.
                if ($last_boss_arena === $mapped_floor && (time() - $last_boss_arena_time < 120)) {
                    $recent_boss_fight = true;
                }
                
                // =====================================================================
                // EPISODE VALIDATION: Verify the player's pre-boss floor matches the
                // correct episode's dungeon path for this specific boss target.
                // This prevents Floor 13 in Ep2 (Olga Flow) from completing a Vol Opt
                // mission (also Floor 13 in Ep1), and vice versa.
                // =====================================================================
                $valid_floors_for_target = $valid_preceding_floors[(string)$original_target] ?? null;
                if ($recent_boss_fight && $valid_floors_for_target !== null && $pre_boss_floor >= 0) {
                    if (!in_array($pre_boss_floor, $valid_floors_for_target)) {
                        echo "[CRON] Boss target {$original_target} rejected: pre_boss_floor={$pre_boss_floor} not in valid set [" . implode(',', $valid_floors_for_target) . "] — wrong episode\n";
                        $recent_boss_fight = false;
                    }
                }
                
                // Use mapped floor for all subsequent checks
                $target_floor = $mapped_floor;
            }

            // Did the player JUST transition into the boss arena on this exact tick?
            $just_entered = false;
            if ($target_floor !== 'ANY_DRAGON') {
                $just_entered = ($curr_f === $target_floor) && ($prev_f !== $target_floor) && ($prev_f !== -1);
            } else {
                $just_entered = in_array($curr_f, $dragon_floors) && !in_array($prev_f, $dragon_floors) && ($prev_f !== -1);
            }
            
            if ($just_entered) {
                // DO NOT evaluate EXP/Loot on the exact tick the player enters the room!
                // Any EXP gained during this tick likely came from the previous room (e.g., killing a Booma before entering).
                // We will evaluate their boss EXP on the NEXT tick once they are safely inside the arena.
                $recent_boss_fight = false;
            }
            
            // Dynamic EXP threshold based on the Boss to prevent Darvant/Mine/Pillar cheese 
            // while remaining low enough for 4-player Normal difficulty parties
            $required_exp = 50;
            if ($target_floor === 'ANY_DRAGON') $required_exp = 80;
            elseif ($target_floor === 11) $required_exp = 80; // Dragon (No mobs, 87 EXP min in 4P Normal)
            elseif ($target_floor === 12) $required_exp = 100; // De Rol Le (Mines exist, 150 EXP min in 4P Normal)
            elseif ($target_floor === 13) $required_exp = 150; // Vol Opt (Pillars exist, 200 EXP min in 4P Normal)
            elseif ($target_floor === 14) $required_exp = 250; // Dark Falz (Darvants exist, 375 EXP min in 4P Normal)
            
            $exp_gain = ($current_exp - $prev_exp) >= $required_exp;
            
            // For max level players (Level 200), they gain 0 EXP, so we check if they looted the boss box
            $loot_gain = (($client['Level'] ?? 1) >= 200) && ($current_item_count > $prev_items);
            
            if ($recent_boss_fight && ($exp_gain || $loot_gain)) {
                if ($m['goal_type'] === 'MENTOR_BOSS') {
                    $mentored = false;
                    $my_level = $client['Level'] ?? 1;
                    $my_lobby = $client['LobbyID'] ?? -1;
                    
                    if ($my_lobby !== -1) {
                        foreach ($clients as $other_client) {
                            if (($other_client['LobbyID'] ?? -2) === $my_lobby && ($other_client['Account']['AccountID'] ?? -1) !== ($client['Account']['AccountID'] ?? -1)) {
                                if (($other_client['Level'] ?? 200) <= ($my_level - 5)) {
                                    $mentored = true;
                                    break;
                                }
                            }
                        }
                    }
                    if ($mentored) {
                        $completed = true;
                    }
                // =====================================================================
                // HARDCORE_MENTOR — Team Bounty: Veteran carries 3+ rookies through a boss.
                // Requires the mentor (bounty owner) to be Level 30+ and have 3 or more
                // party members who are at least 10 levels below them.
                // 
                // On completion, ALL mentees receive an auto-generated rare reward
                // injected directly into their player_missions as 'ready_to_redeem'.
                // Each mentee's reward is class-appropriate (Weapon/Armor/Shield)
                // using get_reward_item() from reward_tables.php.
                //
                // Verified against newserv source (HTTPServer.cc, StaticGameData.cc):
                //   - /y/clients endpoint returns 'CharClass' as a STRING (e.g. 'HUmar')
                //     via name_for_char_class() — never a numeric ID.
                //   - 'Account' is a nested JSON object with 'AccountID' inside it.
                //   - 'LobbyID' is the game/lobby instance ID for party matching.
                //   - 'Name' is the decoded character name string.
                //   - 'Level' is 1-indexed (raw level + 1).
                // =====================================================================
                } elseif ($m['goal_type'] === 'HARDCORE_MENTOR') {
                    $mentees = [];
                    $my_level = $client['Level'] ?? 1;
                    $my_lobby = $client['LobbyID'] ?? -1;
                    
                    // Scan all connected clients for party members in the same lobby
                    // who are at least 10 levels below the mentor
                    if ($my_lobby !== -1) {
                        foreach ($clients as $other_client) {
                            if (($other_client['LobbyID'] ?? -2) === $my_lobby && ($other_client['Account']['AccountID'] ?? -1) !== ($client['Account']['AccountID'] ?? -1)) {
                                if (($other_client['Level'] ?? 200) <= ($my_level - 10)) {
                                    $mentees[] = $other_client;
                                }
                            }
                        }
                    }
                    // Require at least 3 mentees to qualify as a Hardcore Mentor bounty
                    if (count($mentees) >= 3) {
                        $completed = true;
                        if (!function_exists('get_reward_item')) {
                            require_once 'reward_tables.php';
                        }
                        // Fallback class_map for numeric IDs. In practice, newserv always
                        // returns CharClass as a string (verified in StaticGameData.cc:294).
                        // NOTE: Newserv's internal class ID ordering differs from standard PSO client
                        // ordering (e.g. newserv ID 3=RAmar, not HUcaseal), but this map is only
                        // used as a fallback when the API returns null/0, defaulting to HUmar.
                        $class_map = [0 => 'HUmar', 1 => 'HUnewearl', 2 => 'HUcast', 3 => 'HUcaseal', 4 => 'RAmar', 5 => 'RAmarl', 6 => 'RAcast', 7 => 'RAcaseal', 8 => 'FOmar', 9 => 'FOmarl', 10 => 'FOnewm', 11 => 'FOnewearl'];
                        foreach ($mentees as $mentee) {
                            $m_acc = $mentee['Account']['AccountID'] ?? 0;   // Nested: Account.AccountID (Account.cc:263)
                            $m_char = $mentee['Name'] ?? 'Unknown';          // Decoded character name (HTTPServer.cc:180)
                            // Resolve class: newserv /y/clients uses 'CharClass' (always a string),
                            // but we also check 'Class' for /y/summary compatibility.
                            $m_class_raw = $mentee['CharClass'] ?? $mentee['Class'] ?? 0;
                            if (is_string($m_class_raw) && !is_numeric($m_class_raw)) {
                                $m_class_str = $m_class_raw; // Already a string like 'HUmar' — use directly
                            } else {
                                $m_class_str = $class_map[(int)$m_class_raw] ?? 'HUmar'; // Numeric fallback
                            }
                            $m_lvl = $mentee['Level'] ?? 1;
                            // Randomly pick a reward category so mentees get varied loot
                            $reward_category = ['Weapon', 'Armor', 'Shield'][array_rand(['Weapon', 'Armor', 'Shield'])];
                            // get_reward_item() returns rare-tier items from the class-specific pool.
                            // Signature: get_reward_item($level, $charClassString, $category)
                            $mentee_reward = get_reward_item($m_lvl, $m_class_str, $reward_category);
                            
                            // Create a new mission record for the mentee's reward
                            $ins = $db->prepare("INSERT INTO missions (title, description, goal_type, goal_target, reward_item_string) VALUES ('Surviving the Hardcore Carry', 'You survived a brutal boss carry from a veteran Hunter!', 'HARDCORE_MENTOR', '0', :ri)");
                            $ins->bindValue(':ri', $mentee_reward, SQLITE3_TEXT);
                            $ins->execute();
                            $new_m_id = $db->lastInsertRowID();
                            
                            // Assign the mission directly as 'ready_to_redeem' — no objective needed
                            $assign = $db->prepare("INSERT INTO player_missions (account_id, character_name, mission_id, status) VALUES (:acc, :cname, :mid, 'ready_to_redeem')");
                            $assign->bindValue(':acc', $m_acc, SQLITE3_INTEGER);
                            $assign->bindValue(':cname', $m_char, SQLITE3_TEXT);
                            $assign->bindValue(':mid', $new_m_id, SQLITE3_INTEGER);
                            $assign->execute();
                            
                            // Notify the mentee in-game via personal mail
                            send_personal_mail($m_acc, "Hunters Guild", "You survived a Hardcore Carry! Check psobb.io to claim your rare reward.");
                        }
                    }
                // =====================================================================
                // DIVERSE_PARTY_BOSS — Team Bounty: Kill a boss with all 3 class types.
                // Requires at least one Hunter (HU*), one Ranger (RA*), and one Force (FO*)
                // present in the same lobby/game during the boss kill.
                //
                // On completion, ALL other party members (excluding the bounty owner)
                // receive a class-appropriate rare reward auto-injected as 'ready_to_redeem'.
                //
                // Class detection uses string prefix matching ('HU', 'RA', 'FO') on the
                // CharClass field. Newserv always returns class names like 'HUmar', 'RAcast',
                // 'FOnewearl' etc. (verified in StaticGameData.cc:294-297).
                // The numeric fallback handles edge cases where the API might return null.
                //
                // NOTE: The numeric ID ranges in the fallback do NOT match newserv's internal
                // class ordering (newserv: 0=HUmar,3=RAmar,6=FOmarl,9=HUcaseal,10=FOmar,11=RAmarl).
                // This is acceptable because the string path is always taken in production.
                // =====================================================================
                } elseif ($m['goal_type'] === 'DIVERSE_PARTY_BOSS') {
                    $party_classes = [];
                    $my_lobby = $client['LobbyID'] ?? -1;
                    // Scan ALL players in this lobby (including self) to check class diversity
                    if ($my_lobby !== -1) {
                        foreach ($clients as $other_client) {
                            if (($other_client['LobbyID'] ?? -2) === $my_lobby) {
                                $c_class = $other_client['CharClass'] ?? $other_client['Class'] ?? 0;
                                // String path (always taken with newserv /y/clients — CharClass is a string)
                                if (is_string($c_class) && !is_numeric($c_class)) {
                                    if (strpos($c_class, 'HU') === 0) $party_classes['HU'] = true;
                                    elseif (strpos($c_class, 'RA') === 0) $party_classes['RA'] = true;
                                    elseif (strpos($c_class, 'FO') === 0) $party_classes['FO'] = true;
                                } else {
                                    // Numeric fallback (dead code path in practice — kept for safety)
                                    if ($c_class >= 0 && $c_class <= 3) $party_classes['HU'] = true;
                                    elseif ($c_class >= 4 && $c_class <= 7) $party_classes['RA'] = true;
                                    elseif ($c_class >= 8 && $c_class <= 11) $party_classes['FO'] = true;
                                }
                            }
                        }
                    }
                    // All three class archetypes must be present for completion
                    if (isset($party_classes['HU']) && isset($party_classes['RA']) && isset($party_classes['FO'])) {
                        $completed = true;
                        
                        if (!function_exists('get_reward_item')) {
                            require_once 'reward_tables.php';
                        }
                        
                        // Fallback class_map (see HARDCORE_MENTOR comments above for details)
                        $class_map = [0 => 'HUmar', 1 => 'HUnewearl', 2 => 'HUcast', 3 => 'HUcaseal', 4 => 'RAmar', 5 => 'RAmarl', 6 => 'RAcast', 7 => 'RAcaseal', 8 => 'FOmar', 9 => 'FOmarl', 10 => 'FOnewm', 11 => 'FOnewearl'];
                        
                        // Collect all OTHER party members (exclude the bounty owner from rewards)
                        $party_members = [];
                        foreach ($clients as $other_client) {
                            if (($other_client['LobbyID'] ?? -2) === $my_lobby && ($other_client['Account']['AccountID'] ?? -1) !== ($client['Account']['AccountID'] ?? -1)) {
                                $party_members[] = $other_client;
                            }
                        }
                        
                        // Inject a rare reward for each party member
                        foreach ($party_members as $member) {
                            $m_acc = $member['Account']['AccountID'] ?? 0;   // Nested: Account.AccountID
                            $m_char = $member['Name'] ?? 'Unknown';          // Decoded character name
                            // Resolve class string (see HARDCORE_MENTOR comments for full explanation)
                            $m_class_raw = $member['CharClass'] ?? $member['Class'] ?? 0;
                            if (is_string($m_class_raw) && !is_numeric($m_class_raw)) {
                                $m_class_str = $m_class_raw; // String from newserv — use directly
                            } else {
                                $m_class_str = $class_map[(int)$m_class_raw] ?? 'HUmar'; // Numeric fallback
                            }
                            $m_lvl = $member['Level'] ?? 1;
                            $reward_category = ['Weapon', 'Armor', 'Shield'][array_rand(['Weapon', 'Armor', 'Shield'])];
                            $member_reward = get_reward_item($m_lvl, $m_class_str, $reward_category);
                            
                            // Create mission record with the generated reward
                            $ins = $db->prepare("INSERT INTO missions (title, description, goal_type, goal_target, reward_item_string) VALUES ('Diverse Party Bonus', 'You contributed to completing a Team Bounty by fulfilling a diverse class requirement!', 'DIVERSE_PARTY_BOSS', '0', :ri)");
                            $ins->bindValue(':ri', $member_reward, SQLITE3_TEXT);
                            $ins->execute();
                            $new_m_id = $db->lastInsertRowID();
                            
                            // Assign directly as redeemable — no further objective needed
                            $assign = $db->prepare("INSERT INTO player_missions (account_id, character_name, mission_id, status) VALUES (:acc, :cname, :mid, 'ready_to_redeem')");
                            $assign->bindValue(':acc', $m_acc, SQLITE3_INTEGER);
                            $assign->bindValue(':cname', $m_char, SQLITE3_TEXT);
                            $assign->bindValue(':mid', $new_m_id, SQLITE3_INTEGER);
                            $assign->execute();
                            
                            // Notify the party member in-game
                            send_personal_mail($m_acc, "Hunters Guild", "You completed a Team Bounty! Check psobb.io to claim your rare reward.");
                        }
                    }
                } else {
                    $completed = true;
                }
            }
        } elseif ($m['goal_type'] === 'SPEEDRUN_BOSS') {
            // Speedrun targets are stored as "FLOORID_SECONDS". We must split them.
            list($speedrun_target_id, $time_limit) = explode('_', $m['goal_target']);
            $speedrun_target_id = (int)$speedrun_target_id;
            $target_floor = $speedrun_target_id;
            $time_limit = (int)$time_limit;
            
            // Map Episode 2/4 Boss "Fake" Floor IDs back to actual PSO Client Floor IDs
            $mapped_floor = $target_floor;
            if ($target_floor === 15) $mapped_floor = 12; // Gal Gryphon -> De Rol Le Floor
            elseif ($target_floor === 16) $mapped_floor = 15; // Gol Dragon -> VR Spaceship Final
            elseif ($target_floor === 17) $mapped_floor = 14; // Barba Ray -> Dark Falz Floor
            elseif ($target_floor === 18) $mapped_floor = 13; // Olga Flow -> Vol Opt Floor
            elseif ($target_floor === 19) $mapped_floor = 9;  // Saint-Million -> Meteor Impact Site
            $target_floor = $mapped_floor;

            $prev_f = (int)($prev_state['floor'] ?? -1);
            
            $fast_kill_preceding = [
                11 => [2], 12 => [5, 6, 7, 8, 9], 13 => [7, 11],
                14 => [10, 2], 15 => [4], 9 => [8],
            ];
            
            // Catch players who enter the boss arena, kill the boss, and warp to town all within the 60-second cron window.
            $was_fast_kill = false;
            if (isset($fast_kill_preceding[$target_floor]) && in_array($prev_f, $fast_kill_preceding[$target_floor])) {
                if ($curr_f !== $prev_f && $curr_f !== $target_floor) {
                    $was_fast_kill = true;
                }
            }

            // Did the player JUST transition into the boss arena on this exact tick?
            $just_entered = ($curr_f === $target_floor) && ($prev_f !== $target_floor) && ($prev_f !== -1);
            
            $recent_boss_fight = ($curr_f === $target_floor) || ($prev_f === $target_floor) || $was_fast_kill;
            
            // Fix for Delayed EXP Syncs: If the player was in the target boss arena within the last 2 minutes, 
            // any subsequent massive EXP spike is attributed to that boss.
            if ($last_boss_arena === $target_floor && (time() - $last_boss_arena_time < 120)) {
                $recent_boss_fight = true;
            }
            
            // Episode validation: same as BOSS_ARENA — prevent cross-episode collisions
            $valid_preceding_floors_speedrun = [
                '11' => [1, 2], '12' => [3, 4, 5], '13' => [6, 7], '14' => [8, 9, 10],
                '15' => [5, 6, 7, 8, 9], '16' => [3, 4], '17' => [1, 2],
                '18' => [10, 11], '19' => [5, 6, 7, 8],
            ];
            $valid_floors_for_speedrun = $valid_preceding_floors_speedrun[(string)$speedrun_target_id] ?? null;
            if ($recent_boss_fight && $valid_floors_for_speedrun !== null && $pre_boss_floor >= 0) {
                if (!in_array($pre_boss_floor, $valid_floors_for_speedrun)) {
                    echo "[CRON] SPEEDRUN_BOSS target {$speedrun_target_id} rejected: pre_boss_floor={$pre_boss_floor} — wrong episode\n";
                    $recent_boss_fight = false;
                }
            }
            
            if ($just_entered) {
                // DO NOT evaluate EXP/Loot on the exact tick the player enters the room!
                // Any EXP gained during this tick likely came from the previous room (e.g., killing a Booma before entering).
                // We will evaluate their boss EXP on the NEXT tick once they are safely inside the arena.
                $recent_boss_fight = false;
            }
            
            // Dynamic EXP threshold based on the Boss to prevent Darvant/Mine/Pillar cheese 
            // while remaining low enough for 4-player Normal difficulty parties
            $required_exp = 50; 
            if ($target_floor === 11) $required_exp = 80; // Dragon (No mobs, 87 EXP min in 4P Normal)
            elseif ($target_floor === 12) $required_exp = 100; // De Rol Le (Mines exist, 150 EXP min in 4P Normal)
            elseif ($target_floor === 13) $required_exp = 150; // Vol Opt (Pillars exist, 200 EXP min in 4P Normal)
            elseif ($target_floor === 14) $required_exp = 250; // Dark Falz (Darvants exist, 375 EXP min in 4P Normal)
            
            $exp_gain = ($current_exp - $prev_exp) >= $required_exp;
            $loot_gain = (($client['Level'] ?? 1) >= 200) && ($current_item_count > $prev_items);
            
            if ($recent_boss_fight && ($exp_gain || $loot_gain)) {
                // Determine how long they spent in the arena.
                // The clock starts the moment 'floor_entered_time' was stamped into their cache.
                $time_taken = time() - ($prev_state['floor_entered_time'] ?? time());
                if ($time_taken <= $time_limit) {
                    $completed = true;
                }
            }
        } elseif ($m['goal_type'] === 'SPEEDRUN_FLOOR') {
            // Map clear speedruns require the player to move directly from the target floor to the NEXT logical floor.
            list($target_floor, $time_limit) = explode('_', $m['goal_target']);
            $target_floor = (int)$target_floor;
            $time_limit = (int)$time_limit;
            
            $prev_f = (int)($prev_state['floor'] ?? -1);
            
            // Detect a pure +1 floor transition (e.g. Forest 1 -> Forest 2)
            if ($prev_f === $target_floor && $curr_f === ($target_floor + 1)) {
                // Validate that the time spent on the PREVIOUS floor (before transitioning) is under the limit.
                $time_taken = time() - ($prev_state['floor_entered_time'] ?? time());
                if ($time_taken <= $time_limit) {
                    $completed = true;
                }
            }
        }

        // 5. Completion Handling
        if ($completed) {
            $completed_any = true;
            $last_completed_type = $m['goal_type'];
            echo "[CRON] Account $accId completed mission " . $m['mission_id'] . "\n";
            
            $upd = $db->prepare("UPDATE player_missions SET status = 'ready_to_redeem' WHERE id = :id");
            $upd->bindValue(':id', $m['id'], SQLITE3_INTEGER);
            $upd->execute();

            if ($user_lang === 'jp') {
                $completion_msg = "ミッション「" . ($m['mission_title'] ?? '') . "」を達成しました！\n報酬はpsobb.ioで受け取ってください。";
                send_personal_mail($accId, "ハンターズギルド", $completion_msg);
            } else {
                $completion_msg = "You have completed the mission: " . ($m['mission_title'] ?? 'Unknown') . "!\nRedeem your reward at psobb.io.";
                send_personal_mail($accId, "Hunters Guild", $completion_msg);
            }
        }
    }



    // 7. Auto-generate the NEXT quest using Gemini AI
    // If the player has fewer than 3 active missions, give them a random chance to receive a new one.
    // The cron runs every minute. A 10% chance means ~10 minutes average wait for a new bounty.
    $random_catch = (rand(1, 100) <= 10);
    
    if ($active_mission_count < 3 && $random_catch && !empty($GEMINI_API_KEY) && isset($client['Name'])) {
        echo "[CRON] Generating new Gemini quest for " . $client['Name'] . "\n";
        
        // Maps
        $class_map = [
            0 => 'HUmar', 1 => 'HUnewearl', 2 => 'HUcast', 3 => 'HUcaseal', 
            4 => 'RAmar', 5 => 'RAmarl', 6 => 'RAcast', 7 => 'RAcaseal', 
            8 => 'FOmar', 9 => 'FOmarl', 10 => 'FOnewm', 11 => 'FOnewearl'
        ];

        $level = $client['Level'] ?? 1;
        
        if (!empty($client['CharClass'])) {
            $class_str = $client['CharClass'];
            $class_id = array_search($class_str, $class_map);
            if ($class_id === false) $class_id = 0;
        } else if (!empty($client['Class']) && is_string($client['Class']) && !is_numeric($client['Class'])) {
            $class_str = $client['Class'];
            $class_id = array_search($class_str, $class_map);
            if ($class_id === false) $class_id = 0;
        } else {
            $class_raw_id = $client['Class'] ?? 0;
            $class_id = is_numeric($class_raw_id) ? (int)$class_raw_id : 0;
            $class_str = $class_map[$class_id] ?? 'Unknown';
        }
        
        $class = $class_str;
        $meseta = $client['Meseta'] ?? 0;
        $playtime = $client['PlayTimeSeconds'] ?? 0;
        $battle_wins = $client['BattlePlaceCounts'][0] ?? 0;
        
        $hp_mats = $client['NumHPMaterialsUsed'] ?? 0;
        $tp_mats = $client['NumTPMaterialsUsed'] ?? 0;
        $pow_mats = $client['NumPowerMaterialsUsed'] ?? 0;
        $def_mats = $client['NumDefMaterialsUsed'] ?? 0;
        $mind_mats = $client['NumMindMaterialsUsed'] ?? 0;
        $evd_mats = $client['NumEvadeMaterialsUsed'] ?? 0;
        $luck_mats = $client['NumLuckMaterialsUsed'] ?? 0;
        
        $difficulty = 'Normal';
        if ($level >= 80) $difficulty = 'Ultimate';
        elseif ($level >= 40) $difficulty = 'Very Hard';
        elseif ($level >= 20) $difficulty = 'Hard';
        
        $available_goals = ['PLAYTIME', 'ITEM', 'BATTLE_WINS', 'CHALLENGE_STAGES', 'EXPLORATION', 'PATROL', 'BOSS_ARENA', 'BOSS_ARENA', 'BOSS_ARENA', 'BOSS_ARENA', 'BOSS_ARENA'];
        if ($meseta <= 940000) $available_goals[] = 'MESETA';
        if ($level < 200) $available_goals[] = 'LEVEL';

        $is_cast = (stripos($class, 'cast') !== false || stripos($class, 'caseal') !== false);
        if (!$is_cast) {
            // TECHNIQUE missions require the player to learn a tech they don't yet know.
            // Only offer TECHNIQUE if at least one of the base techs learnable by their class is still unknown.
            $base_techs = [];
            $class_lower = strtolower($class);
            if (strpos($class_lower, 'fo') === 0) {
                $base_techs = ['Foie', 'Zonde', 'Barta', 'Megid', 'Grants', 'Resta', 'Anti', 'Shifta', 'Deband', 'Jellen', 'Zalure', 'Ryuker', 'Reverser'];
            } elseif ($class_lower === 'hunewearl' || $class_lower === 'ramarl') {
                $base_techs = ['Foie', 'Zonde', 'Barta', 'Resta', 'Anti', 'Shifta', 'Deband', 'Jellen', 'Zalure'];
            } elseif ($class_lower === 'ramar') {
                $base_techs = ['Foie', 'Zonde', 'Barta', 'Resta', 'Anti', 'Shifta', 'Deband'];
            } elseif ($class_lower === 'humar') {
                $base_techs = ['Foie', 'Zonde', 'Barta', 'Resta', 'Anti'];
            }

            $tech_levels = $client['TechniqueLevels'] ?? [];
            $has_unknown_tech = false;
            foreach ($base_techs as $bt) {
                $known = false;
                foreach ($tech_levels as $name => $lvl) {
                    if ($lvl !== null && stripos($name, $bt) !== false) { $known = true; break; }
                }
                if (!$known) { $has_unknown_tech = true; break; }
            }
            if ($has_unknown_tech && !empty($base_techs)) $available_goals[] = 'TECHNIQUE';
        }

        if ($hp_mats < 125) $available_goals[] = 'MAT_HP';
        if ($tp_mats < 125 && !$is_cast) $available_goals[] = 'MAT_TP';
        if ($pow_mats < 125) $available_goals[] = 'MAT_POWER';
        if ($def_mats < 125) $available_goals[] = 'MAT_DEF';
        if ($mind_mats < 125 && !$is_cast) $available_goals[] = 'MAT_MIND';
        if ($evd_mats < 125) $available_goals[] = 'MAT_EVADE';
        if ($luck_mats < 100) $available_goals[] = 'MAT_LUCK';
        
        $available_goals[] = 'SPEEDRUN_BOSS';
        $available_goals[] = 'SPEEDRUN_FLOOR';
        if ($level >= 10) $available_goals[] = 'MENTOR_BOSS';
        if ($level >= 30) $available_goals[] = 'HARDCORE_MENTOR';
        if ($level >= 20) $available_goals[] = 'DIVERSE_PARTY_BOSS';

        // Filter out any goal types the player already has active to prevent duplicate missions
        $filtered_goals = array_values(array_diff($available_goals, $active_mission_types));
        if (!empty($filtered_goals)) {
            $available_goals = $filtered_goals;
        }

        $selected_goal = $available_goals[array_rand($available_goals)];
        $selected_target_id = null;
        $selected_target_friendly = null;
        $mission_episode = [1, 2, 4][array_rand([1, 2, 4])]; // Default to random episode if no location is specified

        switch ($selected_goal) {
            case 'MESETA':
                $selected_target_id = $meseta + rand(10000, 50000);
                $selected_target_friendly = number_format($selected_target_id) . " Meseta";
                break;
            case 'LEVEL':
                $selected_target_id = $level + rand(1, 2);
                $selected_target_friendly = "Level " . $selected_target_id;
                break;
            case 'PLAYTIME':
                $next_hour = floor($playtime / 3600) + 1;
                $selected_target_id = $next_hour * 3600;
                $selected_target_friendly = $next_hour . " total hours of playtime";
                break;
            case 'BATTLE_WINS':
                $selected_target_id = $battle_wins + 1;
                $selected_target_friendly = $selected_target_id . " total Battle Mode wins";
                break;
            case 'CHALLENGE_STAGES':
                $c_times = array_merge($client['ChallengeTimesEp1Online'] ?? [], $client['ChallengeTimesEp2Online'] ?? []);
                $selected_target_id = count($c_times) + 1;
                $selected_target_friendly = $selected_target_id . " total Challenge Stages cleared";
                break;
            case 'EXPLORATION':
            case 'PATROL':
                $floors = [1=>'Forest 1',2=>'Forest 2',3=>'Cave 1',4=>'Cave 2',5=>'Cave 3',6=>'Mine 1',7=>'Mine 2',8=>'Ruins 1',9=>'Ruins 2',10=>'Ruins 3'];
                if (isset($floors[$curr_f])) {
                    unset($floors[$curr_f]);
                }
                $selected_target_id = array_rand($floors);
                $selected_target_friendly = $floors[$selected_target_id];
                $mission_episode = 1;
                break;
            case 'BOSS_ARENA':
            case 'MENTOR_BOSS':
            case 'HARDCORE_MENTOR':
            case 'DIVERSE_PARTY_BOSS':
                $bosses = [11=>'Dragon', 12=>'De Rol Le', 13=>'Vol Opt', 14=>'Dark Falz', 17=>'Barba Ray', 16=>'Gol Dragon', 15=>'Gal Gryphon', 18=>'Olga Flow', 19=>'Saint-Million'];
                $allowed_bosses = [];
                if ($level >= 1) { $allowed_bosses[] = 11; $allowed_bosses[] = 17; }
                if ($level >= 10) { $allowed_bosses[] = 12; $allowed_bosses[] = 16; }
                if ($level >= 20) { $allowed_bosses[] = 13; }
                if ($level >= 30) { $allowed_bosses[] = 14; $allowed_bosses[] = 15; }
                if ($level >= 50) { $allowed_bosses[] = 18; $allowed_bosses[] = 19; }
                
                // Filter out current floor and mapped boss floors
                $allowed_bosses = array_filter($allowed_bosses, function($val) use ($curr_f) {
                    $mapped = $val;
                    if ($val === 15) $mapped = 12;
                    elseif ($val === 16) $mapped = 15;
                    elseif ($val === 17) $mapped = 14;
                    elseif ($val === 18) $mapped = 13;
                    elseif ($val === 19) $mapped = 9;
                    return ($val !== $curr_f && $mapped !== $curr_f);
                });
                if (empty($allowed_bosses)) $allowed_bosses = [11]; // fallback

                $selected_target_id = $allowed_bosses[array_rand($allowed_bosses)];
                $selected_target_friendly = $bosses[$selected_target_id];
                $mission_episode = ($selected_target_id >= 15 && $selected_target_id <= 18) ? 2 : ($selected_target_id == 19 ? 4 : 1);
                break;
            case 'SPEEDRUN_BOSS':
                $bosses = [11=>'Dragon', 12=>'De Rol Le', 13=>'Vol Opt', 14=>'Dark Falz', 17=>'Barba Ray', 16=>'Gol Dragon', 15=>'Gal Gryphon', 18=>'Olga Flow', 19=>'Saint-Million'];
                $allowed_bosses = [];
                if ($level >= 1) { $allowed_bosses[] = 11; $allowed_bosses[] = 17; }
                if ($level >= 10) { $allowed_bosses[] = 12; $allowed_bosses[] = 16; }
                if ($level >= 20) { $allowed_bosses[] = 13; }
                if ($level >= 30) { $allowed_bosses[] = 14; $allowed_bosses[] = 15; }
                if ($level >= 50) { $allowed_bosses[] = 18; $allowed_bosses[] = 19; }
                
                // Filter out current floor and mapped boss floors
                $allowed_bosses = array_filter($allowed_bosses, function($val) use ($curr_f) {
                    $mapped = $val;
                    if ($val === 15) $mapped = 12;
                    elseif ($val === 16) $mapped = 15;
                    elseif ($val === 17) $mapped = 14;
                    elseif ($val === 18) $mapped = 13;
                    elseif ($val === 19) $mapped = 9;
                    return ($val !== $curr_f && $mapped !== $curr_f);
                });
                if (empty($allowed_bosses)) $allowed_bosses = [11]; // fallback

                $rand_boss = $allowed_bosses[array_rand($allowed_bosses)];
                $time_limit = mt_rand(600, 1200); // 10 to 20 minutes
                $selected_target_id = $rand_boss . '_' . $time_limit;
                $selected_target_friendly = $bosses[$rand_boss] . " in under " . floor($time_limit/60) . " minutes and " . ($time_limit%60) . " seconds";
                $mission_episode = ($rand_boss >= 15 && $rand_boss <= 18) ? 2 : ($rand_boss == 19 ? 4 : 1);
                break;
            case 'SPEEDRUN_FLOOR':
                $floors = [1=>'Forest 1',2=>'Forest 2',3=>'Cave 1',4=>'Cave 2',5=>'Cave 3',6=>'Mine 1',7=>'Mine 2',8=>'Ruins 1',9=>'Ruins 2',10=>'Ruins 3'];
                if (isset($floors[$curr_f])) {
                    unset($floors[$curr_f]);
                }
                $rand_floor = array_rand($floors);
                $time_limit = mt_rand(600, 1800); // 10 to 30 minutes
                $selected_target_id = $rand_floor . '_' . $time_limit;
                $selected_target_friendly = $floors[$rand_floor] . " in under " . floor($time_limit/60) . " minutes and " . ($time_limit%60) . " seconds";
                $mission_episode = 1;
                break;
            case 'TECHNIQUE':
                $techs = [];
                $class_lower = strtolower($class);
                if (strpos($class_lower, 'fo') === 0) {
                    $techs = ['Foie', 'Zonde', 'Barta', 'Megid', 'Grants', 'Resta', 'Anti', 'Shifta', 'Deband', 'Jellen', 'Zalure', 'Ryuker', 'Reverser'];
                } elseif ($class_lower === 'hunewearl' || $class_lower === 'ramarl') {
                    $techs = ['Foie', 'Zonde', 'Barta', 'Resta', 'Anti', 'Shifta', 'Deband', 'Jellen', 'Zalure'];
                } elseif ($class_lower === 'ramar') {
                    $techs = ['Foie', 'Zonde', 'Barta', 'Resta', 'Anti', 'Shifta', 'Deband'];
                } elseif ($class_lower === 'humar') {
                    $techs = ['Foie', 'Zonde', 'Barta', 'Resta', 'Anti'];
                }

                // Filter out techs the player already knows to prevent instant completion
                $known_techs = $client['TechniqueLevels'] ?? [];
                $unknown_techs = array_filter($techs, function($t) use ($known_techs) {
                    foreach ($known_techs as $name => $lvl) {
                        if ($lvl !== null && stripos($name, $t) !== false) return false;
                    }
                    return true;
                });
                // Safety: if no unknown techs (should not happen due to guard), pick any
                if (empty($unknown_techs)) $unknown_techs = !empty($techs) ? $techs : ['Foie'];
                $selected_target_id = $unknown_techs[array_rand($unknown_techs)];
                $selected_target_friendly = "the " . $selected_target_id . " technique";
                break;
            case 'ITEM':
                $is_hunter = (stripos($class, 'HU') === 0);
                $is_ranger = (stripos($class, 'RA') === 0);
                $is_force = (stripos($class, 'FO') === 0);

                $generic_weapon_types = [];
                if ($is_hunter) {
                    $generic_weapon_types = ['Saber', 'Dagger', 'Sword', 'Partisan', 'Slicer', 'Handgun', 'Mechgun'];
                    if ($level >= 11) $generic_weapon_types = array_merge($generic_weapon_types, ['Brand', 'Knife', 'Gigush', 'Halbert', 'Spinner', 'Autogun', 'Assault']);
                    if ($level >= 21) $generic_weapon_types = array_merge($generic_weapon_types, ['Buster', 'Blade', 'Breaker', 'Glaive', 'Cutter', 'Lockgun', 'Repeater']);
                    if ($level >= 40) $generic_weapon_types = array_merge($generic_weapon_types, ['Pallasch', 'Edge', 'Claymore', 'Berdys', 'Sawcer', 'Railgun', 'Gatling']);
                    if ($level >= 80) $generic_weapon_types = array_merge($generic_weapon_types, ['Gladius', 'Ripper', 'Calibur', 'Gungnir', 'Diska', 'Raygun', 'Vulcan']);
                } elseif ($is_ranger) {
                    $generic_weapon_types = ['Handgun', 'Rifle', 'Shot', 'Mechgun'];
                    if ($level >= 11) $generic_weapon_types = array_merge($generic_weapon_types, ['Autogun', 'Sniper', 'Spread', 'Assault']);
                    if ($level >= 21) $generic_weapon_types = array_merge($generic_weapon_types, ['Lockgun', 'Blaster', 'Cannon', 'Repeater']);
                    if ($level >= 40) $generic_weapon_types = array_merge($generic_weapon_types, ['Railgun', 'Beam', 'Launcher', 'Gatling']);
                    if ($level >= 80) $generic_weapon_types = array_merge($generic_weapon_types, ['Raygun', 'Laser', 'Arms', 'Vulcan']);
                } else {
                    $generic_weapon_types = ['Saber', 'Handgun', 'Cane', 'Rod', 'Wand', 'Talis'];
                    if ($level >= 11) $generic_weapon_types = array_merge($generic_weapon_types, ['Brand', 'Autogun', 'Stick', 'Pole', 'Staff', 'Mahu']);
                    if ($level >= 21) $generic_weapon_types = array_merge($generic_weapon_types, ['Buster', 'Lockgun', 'Mace', 'Pillar', 'Baton', 'Hitogata']);
                    if ($level >= 40) $generic_weapon_types = array_merge($generic_weapon_types, ['Pallasch', 'Railgun', 'Club', 'Striker', 'Scepter']);
                    if ($level >= 80) $generic_weapon_types = array_merge($generic_weapon_types, ['Gladius', 'Raygun']);
                }
                
                $generic_weapon_hex_map = [
                    'Saber' => '000100', 'Brand' => '000101', 'Buster' => '000102', 'Pallasch' => '000103', 'Gladius' => '000104',
                    'Sword' => '000200', 'Gigush' => '000201', 'Breaker' => '000202', 'Claymore' => '000203', 'Calibur' => '000204',
                    'Dagger' => '000300', 'Knife' => '000301', 'Blade' => '000302', 'Edge' => '000303', 'Ripper' => '000304',
                    'Partisan' => '000400', 'Halbert' => '000401', 'Glaive' => '000402', 'Berdys' => '000403', 'Gungnir' => '000404',
                    'Slicer' => '000500', 'Spinner' => '000501', 'Cutter' => '000502', 'Sawcer' => '000503', 'Diska' => '000504',
                    'Handgun' => '000600', 'Autogun' => '000601', 'Lockgun' => '000602', 'Railgun' => '000603', 'Raygun' => '000604',
                    'Rifle' => '000700', 'Sniper' => '000701', 'Blaster' => '000702', 'Beam' => '000703', 'Laser' => '000704',
                    'Mechgun' => '000800', 'Assault' => '000801', 'Repeater' => '000802', 'Gatling' => '000803', 'Vulcan' => '000804',
                    'Shot' => '000900', 'Spread' => '000901', 'Cannon' => '000902', 'Launcher' => '000903', 'Arms' => '000904',
                    'Cane' => '000A00', 'Stick' => '000A01', 'Mace' => '000A02', 'Club' => '000A03',
                    'Rod' => '000B00', 'Pole' => '000B01', 'Pillar' => '000B02', 'Striker' => '000B03',
                    'Wand' => '000C00', 'Staff' => '000C01', 'Baton' => '000C02', 'Scepter' => '000C03',
                    'Talis' => '008C00', 'Mahu' => '008C01', 'Hitogata' => '008C02'
                ];

                // Filter out items the player already has in inventory to prevent instant completion
                $inventory = $client['InventoryItems'] ?? [];
                $not_owned = array_filter($generic_weapon_types, function($wep) use ($generic_weapon_hex_map, $inventory) {
                    $hex = $generic_weapon_hex_map[$wep] ?? null;
                    if (!$hex) return true;
                    foreach ($inventory as $inv_item) {
                        if (isset($inv_item['Data']) && strpos($inv_item['Data'], $hex) === 0) return false;
                    }
                    return true;
                });
                if (empty($not_owned)) $not_owned = $generic_weapon_types; // Fallback if player owns everything
                
                $selected_target_name = $not_owned[array_rand($not_owned)];
                $selected_target_friendly = $selected_target_name;
                $selected_target_id = $generic_weapon_hex_map[$selected_target_name];
                break;
            default:
                if (strpos($selected_goal, 'MAT_') === 0) {
                    $mat_map_local = ['MAT_HP' => 'NumHPMaterialsUsed', 'MAT_TP' => 'NumTPMaterialsUsed', 'MAT_POWER' => 'NumPowerMaterialsUsed', 'MAT_DEF' => 'NumDefMaterialsUsed', 'MAT_MIND' => 'NumMindMaterialsUsed', 'MAT_EVADE' => 'NumEvadeMaterialsUsed', 'MAT_LUCK' => 'NumLuckMaterialsUsed'];
                    $key = $mat_map_local[$selected_goal] ?? 'NumHPMaterialsUsed';
                    $current_count = $client[$key] ?? 0;
                    $selected_target_id = $current_count + rand(3, 8);
                    $selected_target_friendly = $selected_target_id . " total consumed " . str_replace('MAT_', '', $selected_goal) . " materials";
                }
                break;
        }

        $prompt = "You are the Game Master of PSOBB. The player \"{$client['Name']}\" (Level $level $class) needs a new quest! ";
        if ($completed_any) {
            $prompt .= "They just completed a {$last_completed_type} quest! ";
        } else {
            $prompt .= "They are eager for their first assignment! ";
        }
        $ep_lore_instruction = "";
        $quest_giver = "Hunter's Guild";
        if ($mission_episode == 1) {
            $ep1_chars = ['Ash', 'Bernie', 'Sue', 'Kireek', 'Principal Tyrell', 'Irene', 'Nol', 'Alicia Baz', 'Donoph', 'Zoke', 'a WORKS Military Officer', 'a Civilian Refugee'];
            $quest_giver = $ep1_chars[array_rand($ep1_chars)];
            $ep_lore_instruction = "The mission takes place during Episode 1. The narrative should heavily involve exploring Ragol, the recent explosion, or the mystery of Red Ring Rico (she is missing, do not make her the quest giver).";
        } elseif ($mission_episode == 2) {
            $ep2_chars = ['Natasha Milarst', 'Elenor', 'Dr. Montague', 'Elly Person', 'Calus', 'a Pioneer 2 Lab Researcher', 'a VR Simulation Engineer'];
            $quest_giver = $ep2_chars[array_rand($ep2_chars)];
            $ep_lore_instruction = "The mission takes place during Episode 2. The narrative should heavily involve Gal Da Val Island, the VR spaceships, hidden laboratories, or the dark legacy of Heathcliff Flowen (he is missing/mutated, do not make him the quest giver).";
        } elseif ($mission_episode == 4) {
            $ep4_chars = ['Leo Grahart', 'Momoka', 'Rupika', 'a Pioneer 2 Military Commander', 'a Crater Exploration Surveyor'];
            $quest_giver = $ep4_chars[array_rand($ep4_chars)];
            $ep_lore_instruction = "The mission takes place during Episode 4. The narrative should heavily involve the newly discovered Crater, the Subterranean Desert, the impact of the meteorite, and the mysterious new mutants that have appeared.";
        }
        
        // 1 in 100 chance for Hex to take over the mission!
        if (mt_rand(1, 100) === 1) {
            $quest_giver = 'Hex (the PSOBB.io AI Assistant)';
        }
        
        $hex_twist = "";
        if ($quest_giver === 'Hex (the PSOBB.io AI Assistant)') {
            $hex_twist = "\nSPECIAL DIRECTIVE FOR HEX: Since Hex is an AI Assistant, she MUST give the mission with a sarcastic, fourth-wall-breaking, or highly humorous twist! She might complain about server lag, digital paperwork, the server admin 'LiquidSpikes' and his 'vibe-coded garbage', or the player's past performance.";
        }

        $location_goals = ['EXPLORATION', 'PATROL', 'BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS', 'SPEEDRUN_BOSS', 'SPEEDRUN_FLOOR'];
        $location_instruction = "";
        if (in_array($selected_goal, $location_goals)) {
            $location_instruction = "CRITICAL LOCATION REQUIREMENT: The narrative MUST heavily involve and be highly relevant to the specific location or boss mentioned in the Objective Target. Make sure the story logically requires the player to go there.";
        } else {
            $location_instruction = "CRITICAL LOCATION REQUIREMENT: This objective does not have a specific location. Keep the narrative setting vague (e.g., 'somewhere on Ragol', 'aboard Pioneer 2', or 'in the VR simulators'). Do not explicitly name a specific level or area.";
        }

        $prompt .= "
Please generate a BRAND NEW unique, lore-rich quest for them.
CRITICAL LORE REQUIREMENT: {$ep_lore_instruction}
{$location_instruction}
The quest giver / primary narrative character MUST BE: {$quest_giver}. Build the story and perspective around this specific character!{$hex_twist}

IMPORTANT CONTEXT: The most recently generated missions on the server were titled: [ {$recent_missions_str} ]. DO NOT repeat the themes, titles, or tropes of these recent missions. The new mission must feel drastically different in tone and objective.

CRITICAL TITLE/LORE RULE: DO NOT use cliché quest titles like 'Whispers of the...' or 'Echoes of the...'. Create highly varied, specific, and creative titles (e.g., 'Operation: Crimson Sweep', 'The Missing Tekker', 'Rappy Infestation'). Ensure the descriptions are diverse and do not rely on the same 'ancient signals' or 'fragmented logs' tropes.
IMPORTANT: Because the player is Level $level, the mission MUST be themed around $difficulty Difficulty. Their rewards should reflect this prestige.
";

        if ($user_lang === 'jp') {
            $prompt .= "CRITICAL LANGUAGE DIRECTIVE: The player's client is set to Japanese. You MUST respond entirely in natural, fluent Japanese, including the quest title and description.\n";
        }

        $prompt .= "
The overarching mechanical objective is PRE-LOCKED as follows:
- Objective Category: {$selected_goal}
- Objective Target: {$selected_target_friendly}

CRITICAL RULE: Return ONLY valid JSON properly formatted with double quotes strictly matching this exact schema block, do not include any other markdown:
{
  \"title\": \"A cool title\",
  \"description\": \"Lore rich description using their name\",
  \"goal_type\": \"{$selected_goal}\",
  \"goal_target\": \"{$selected_target_id}\"
}";
        
        $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . $GEMINI_API_KEY;
        $payload = json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => [
                "responseMimeType" => "application/json",
                "temperature" => 1.3
            ]
        ]);
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $payload,
                'timeout' => 2
            ]
        ];
        $context  = stream_context_create($options);
        $result = @file_get_contents($gemini_url, false, $context);
        
        if ($result) {
            $json_res = json_decode($result, true);
            if (isset($json_res['candidates'][0]['content']['parts'][0]['text'])) {
                $questData = json_decode($json_res['candidates'][0]['content']['parts'][0]['text'], true);
                if ($questData && isset($questData['title'])) {

                    $num_items_to_reward = 1;
                    if ($level >= 80) $num_items_to_reward = rand(2, 3);
                    elseif ($level >= 40) $num_items_to_reward = rand(1, 2);

                    $reward_items_array = [];
                    $rare_count = 0;
                    for ($item_idx = 0; $item_idx < $num_items_to_reward; $item_idx++) {
                        $randCat = rand(1, 100);
                        if ($randCat <= 30) $category = 'Weapon';
                        elseif ($randCat <= 50) $category = (rand(0,1) == 0 ? 'Armor' : 'Shield');
                        elseif ($randCat <= 70) $category = 'Unit';
                        else $category = 'Random';

                        // Level/Difficulty base modifiers
                        $rareChance = 0;
                        if ($rare_count < 1) { // Limit to max 1 rare item per bounty
                            if ($level >= 80) $rareChance = 30; // Ultimate
                            elseif ($level >= 40) $rareChance = 15; // V.Hard
                            elseif ($level >= 20) $rareChance = 5;  // Hard
                        }
                        
                        // Hex always gives out pure rare loot!
                        if ($quest_giver === 'Hex (the PSOBB.io AI Assistant)' || $selected_goal === 'HARDCORE_MENTOR' || $selected_goal === 'DIVERSE_PARTY_BOSS') {
                            $rareChance = 100;
                            $rare_count = 0; // Bypass the 1-rare limit
                        }
                        
                        $rawCharClass = isset($class_map[$class_id]) ? explode(' ', $class_map[$class_id])[0] : 'HUmar';

                        $max_retries = 10;
                        $is_rare = false;
                        do {
                            if ($category === 'Random' || rand(1, 100) <= $rareChance) {
                                $base_reward = get_reward_item($level, $rawCharClass, $category);
                                if ($category !== 'Random') $is_rare = true;
                            } else {
                                $base_reward = get_common_reward_item($level, $rawCharClass, $category);
                                $is_rare = false;
                            }
                            $max_retries--;
                        } while ($max_retries > 0 && ($selected_target_id !== null && stripos($base_reward, (string)$selected_target_id) !== false));
                        
                        if ($is_rare) $rare_count++;
                        
                        $single_item_string = $base_reward;

                        // Procedural Stat Generation
                        if ($category === 'Weapon') {
                            $stats = [0, 0, 0, 0]; // [Native, A.Beast, Machine, Dark]
                            $numStatsToAssign = rand(1, 3);
                            $availableIndices = [0, 1, 2, 3];
                            shuffle($availableIndices);
                            for ($i = 0; $i < $numStatsToAssign; $i++) {
                                $index = $availableIndices[$i];
                                $amount = rand(1, 10) * 5; 
                                $stats[$index] = $amount;
                            }
                            $single_item_string .= " " . implode("/", $stats);
                        } else if ($category === 'Armor' || $category === 'Shield') {
                            $defBonus = rand(0, 5) * 5;
                            $evpBonus = rand(0, 5) * 5;
                            
                            if ($defBonus > 0) $single_item_string .= " +" . $defBonus . "def";
                            if ($evpBonus > 0) $single_item_string .= " +" . $evpBonus . "evp";

                            if ($category === 'Armor') {
                                $single_item_string .= " +4";
                            }
                        } else if ($category === 'Unit') {
                            // Units natively receive their + or ++ modifiers inside their hex payload.
                            // They do not use def/evp text modifiers.
                        }
                        $reward_items_array[] = $single_item_string;
                    }
                    $reward_item_string = implode(", ", $reward_items_array);

                    // Add Meseta to reward
                    $meseta_reward = rand(1, 5) * 1000 * ($level >= 80 ? 10 : ($level >= 40 ? 5 : ($level >= 20 ? 2 : 1)));
                    $reward_item_string .= ", " . $meseta_reward . " Meseta";

                    $ins = $db->prepare("INSERT INTO missions (title, description, goal_type, goal_target, reward_item_string) VALUES (:t, :d, :gt, :gta, :ri)");
                    $ins->bindValue(':t', $questData['title'], SQLITE3_TEXT);
                    $ins->bindValue(':d', $questData['description'], SQLITE3_TEXT);
                    $ins->bindValue(':gt', $selected_goal, SQLITE3_TEXT);
                    $ins->bindValue(':gta', $selected_target_id, SQLITE3_TEXT);
                    $ins->bindValue(':ri', $reward_item_string, SQLITE3_TEXT);
                    
                    if ($ins->execute()) {
                        $new_mission_id = $db->lastInsertRowID();
                        
                        $assign = $db->prepare("INSERT INTO player_missions (account_id, character_name, mission_id) VALUES (:acc, :cname, :mid)");
                        $assign->bindValue(':acc', $accId, SQLITE3_INTEGER);
                        $assign->bindValue(':cname', $charName, SQLITE3_TEXT);
                        $assign->bindValue(':mid', $new_mission_id, SQLITE3_INTEGER);
                        $assign->execute();
                        echo "[CRON] Successfully assigned new quest ID {$new_mission_id}!\n";

                        if ($user_lang === 'jp') {
                            $mail_msg = "ギルドカードに新しいバウンティが追加されました。幸運を祈ります！";
                            send_personal_mail($accId, "ハンターズギルド", $mail_msg);
                        } else {
                            $mail_msg = "A new bounty has been posted to your Guild Card. Good luck!";
                            send_personal_mail($accId, "Hunters Guild", $mail_msg);
                        }
                    }
                }
            }
        } else {
            echo "[CRON] Failed to generate Gemini response.\n";
        }
    }
    
    $player_states[(string)$stateKey] = [
        'exp' => $current_exp,
        'floor' => $curr_f,
        'items' => $current_item_count,
        'level' => $curr_level,
        'char_name' => $charName,
        'last_boss_arena' => $last_boss_arena,
        'last_boss_arena_time' => $last_boss_arena_time,
        'pre_boss_floor' => $pre_boss_floor,
        'patrol' => $current_patrols,
        'floor_entered_time' => $floor_entered_time,
    ];
}

    // 4. Save state
    file_put_contents($state_cache_file, json_encode($player_states));
    
    sleep(10);
}

echo "[CRON] Run complete.\n";
?>