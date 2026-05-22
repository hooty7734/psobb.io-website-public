<?php
/**
 * Dedicated cron daemon for processing Global Server Community Events tracking and AI Milestones.
 * Runs independently of cron_missions to ensure AI API latency does not bottleneck player telemetry.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$db = get_db();

// Verify NewServ configuration exists
if (empty($NEWSERV_API_URL) || empty($GEMINI_API_KEY)) {
    die("[CRON] NEWSERV_API_URL or GEMINI_API_KEY is not defined in .env\n");
}

$state_cache_file = __DIR__ . '/.cron_community_state.json';
$player_states = [];
if (file_exists($state_cache_file)) {
    $player_states = json_decode(file_get_contents($state_cache_file), true) ?: [];
}

echo "[CRON_COMMUNITY] Starting execution...\n";

// Fetch live game state
$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'GET'
    ]
];
$context = stream_context_create($options);
$response = @file_get_contents($NEWSERV_API_URL . '/y/clients', false, $context);

if (!$response) {
    die("[CRON_COMMUNITY] Failed to fetch clients from NewServ API.\n");
}

$clients = json_decode($response, true) ?: [];

$lobbies_response = @file_get_contents($NEWSERV_API_URL . '/y/lobbies', false, $context);
$lobbies = [];
if ($lobbies_response) {
    $lobbies = json_decode($lobbies_response, true) ?: [];
}
$lobby_episode_map = [];
foreach ($lobbies as $l) {
    if (isset($l['ID']) && isset($l['Episode'])) {
        $lobby_episode_map[$l['ID']] = $l['Episode'];
    }
}

if (empty($clients)) {
    echo "[CRON_COMMUNITY] No clients online. Exiting.\n";
    exit;
}

// Fetch active Community Events
$active_community_events = [];
$ce_res = $db->query("SELECT * FROM community_events WHERE status = 'active'");
if ($ce_res) {
    while ($row = $ce_res->fetchArray(SQLITE3_ASSOC)) {
        $active_community_events[] = $row;
    }
}

if (empty($active_community_events)) {
    echo "[CRON_COMMUNITY] No active community events. Proceeding with debug telemetry only.\n";
}

/**
 * Helper function to trigger Gemini AI for milestones and broadcast to NewServ.
 */
function trigger_ai_milestone($ce, $milestone_type, $pct_str) {
    global $GEMINI_API_KEY, $NEWSERV_API_URL, $db;
    
    $prompt = "You are 'Mission Control', the official AI game master for PSOBB. Keep your response extremely brief (under 50 words, maximum 2 sentences). ";
    $prompt .= "The server is currently running a Global Community Event titled '{$ce['title']}' where players work together to {$ce['goal_type']}. ";
    
    if ($milestone_type === 'start') {
        $prompt .= "The event has just started! Announce it to the server and rally the players to participate.";
        $db_flag = 'announced_start';
    } elseif ($milestone_type === '100') {
        $prompt .= "The server just completed 100% of the goal! Announce their victory and tell them they can claim their reward at psobb.io.";
        $db_flag = 'completed_at'; // Special case, standard completion handles it
    } else {
        $prompt .= "The server just reached the {$pct_str} milestone for this goal! Give a quick, encouraging status update to keep players motivated.";
        $db_flag = "announced_{$milestone_type}";
    }
    
    $prompt .= "\nCRITICAL LANGUAGE DIRECTIVE: Since this is a global server broadcast to an international playerbase, you MUST provide your announcement bilingually. First write the English sentence, followed by the Japanese translation.";

    echo "[CRON_COMMUNITY] Triggering Gemini AI for Milestone: {$milestone_type}%\n";

    $payload = [
        "contents" => [["parts" => [["text" => $prompt]]]],
        "generationConfig" => ["temperature" => 0.8, "maxOutputTokens" => 60]
    ];
    
    $g_options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload)
        ]
    ];
    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $GEMINI_API_KEY;
    $api_response = @file_get_contents($gemini_url, false, stream_context_create($g_options));

    $message = "";
    if ($api_response) {
        $data = json_decode($api_response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $msg = trim($data['candidates'][0]['content']['parts'][0]['text']);
            // Strip markdown
            $msg = str_replace(['**', '*'], '', $msg);
            $message = "[Mission Control] " . $msg;
        }
    }
    
    // Fallback if AI fails to respond
    if (empty($message)) {
        if ($milestone_type === 'start') $message = "[Mission Control] Global Event Started! / [指令室] グローバルイベント開始！";
        elseif ($milestone_type === '100') $message = "[Mission Control] Global Event Completed! Claim your reward at psobb.io. / [指令室] グローバルイベント達成！報酬はpsobb.ioで。";
        else $message = "[Mission Control] Global Event reached {$pct_str}! Keep it up! / [指令室] 目標{$pct_str}達成！この調子で頑張れ！";
    }

    // Broadcast
    $exec_payload = json_encode(["command" => "announce-mail " . $message]);
    $exec_options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $exec_payload
        ]
    ];
    @file_get_contents($NEWSERV_API_URL . "/y/shell-exec", false, stream_context_create($exec_options));

    // Flag DB
    if ($db_flag !== 'completed_at') {
        $db->query("UPDATE community_events SET {$db_flag} = 1 WHERE id = " . (int)$ce['id']);
    }
}


// --- 1. Milestone Tracking: START (0%) ---
foreach ($active_community_events as $ce) {
    if (empty($ce['announced_start'])) {
        trigger_ai_milestone($ce, 'start', '0%');
    }
}

// --- 2. Player Delta Accumulation ---
foreach ($clients as $client) {
    if (!isset($client['Account']['AccountID'])) continue;
    $accId = (string)$client['Account']['AccountID'];
    
    $current_exp = $client['EXP'] ?? 0;
    $current_item_count = count($client['InventoryItems'] ?? []);
    $prev_state = $player_states[$accId] ?? [];
    
    $prev_exp = $prev_state['exp'] ?? $current_exp;
    $prev_items = $prev_state['items'] ?? $current_item_count;
    $curr_level = $client['Level'] ?? 1;
    $prev_level = $prev_state['level'] ?? $curr_level;
    
    $curr_meseta = $client['Meseta'] ?? 0;
    $prev_meseta = $prev_state['meseta'] ?? $curr_meseta;
    
    $curr_playtime = $client['PlayTimeSeconds'] ?? 0;
    $prev_playtime = $prev_state['playtime'] ?? $curr_playtime;
    
    $curr_chal = count($client['ChallengeTimes'] ?? []);
    $prev_chal = $prev_state['chal'] ?? $curr_chal;
    
    $curr_mats = ($client['NumHPMaterialsUsed'] ?? 0) + ($client['NumTPMaterialsUsed'] ?? 0) + ($client['NumPowerMaterialsUsed'] ?? 0) + ($client['NumDefMaterialsUsed'] ?? 0) + ($client['NumMindMaterialsUsed'] ?? 0) + ($client['NumEvadeMaterialsUsed'] ?? 0) + ($client['NumLuckMaterialsUsed'] ?? 0);
    $prev_mats = $prev_state['mats'] ?? $curr_mats;
    
    $current_patrols = [];
    $curr_f = (int)($client['LocationFloor'] ?? -1);
    
    $lobby_id = $client['LobbyID'] ?? null;
    $lobby_episode = ($lobby_id !== null && isset($lobby_episode_map[$lobby_id])) ? $lobby_episode_map[$lobby_id] : null;
    
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
    $boss_floors_list = [9, 11, 12, 13, 14, 15];
    $pre_boss_floor = $prev_state['pre_boss_floor'] ?? -1;
    if (!in_array($curr_f, $boss_floors_list) && $curr_f >= 0) {
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

    // --- DEBUG TELEMETRY: Global Boss Kill Tracking ---
    $boss_floors = [
        11 => 'Dragon',           // Ep1 Forest Boss
        12 => 'De Rol Le / Gal Gryphon', // Ep1 Cave Boss / Ep2 CCA Boss (same floor ID)
        13 => 'Vol Opt / Olga Flow',     // Ep1 Mine Boss / Ep2 Seabed Boss
        14 => 'Dark Falz / Barba Ray',   // Ep1 Ruins Boss / Ep2 Temple Boss
        15 => 'Gol Dragon',              // Ep2 Spaceship Boss
        9  => 'Saint-Million'            // Ep4 Boss
    ];
    $floor = $curr_f;
    $prev_floor = (int)($prev_state['floor'] ?? -1);
    
    // Fast-Kill Race Condition Fix for Telemetry
    $fast_kill_boss_name = null;
    if (in_array($curr_f, [0, 231, 232, 233, 234, 235, 236, 237, 238, 239, 240]) && !isset($boss_floors[$prev_floor])) {
        // Player is in lobby (or Pioneer 2), but their previous floor wasn't a boss room.
        // This implies they cleared the boss so fast the cron never saw them in it.
        if ($prev_floor === 2) $fast_kill_boss_name = 'Dragon';
        elseif ($prev_floor === 5) $fast_kill_boss_name = 'De Rol Le';
        elseif ($prev_floor === 7) $fast_kill_boss_name = 'Vol Opt / Olga Flow';
        elseif ($prev_floor === 10) $fast_kill_boss_name = 'Dark Falz';
        elseif ($prev_floor === 4) $fast_kill_boss_name = 'Gol Dragon';
    }
    
    $in_boss_arena = isset($boss_floors[$floor]) || isset($boss_floors[$prev_floor]) || (isset($boss_floors[$last_boss_arena]) && (time() - $last_boss_arena_time < 120)) || ($fast_kill_boss_name !== null);
    if ($in_boss_arena) {
        $exp_gain = ($current_exp - $prev_exp) >= 10;
        $loot_gain = (($client['Level'] ?? 1) >= 200) && ($current_item_count > $prev_items);
        if ($exp_gain || $loot_gain) {
            // Boss kill detected!
            $boss_name = $fast_kill_boss_name ?? $boss_floors[$floor] ?? $boss_floors[$prev_floor] ?? $boss_floors[$last_boss_arena] ?? 'Unknown Boss';
            $debug_log_file = __DIR__ . '/.debug_telemetry.json';
            $logs = file_exists($debug_log_file) ? json_decode(file_get_contents($debug_log_file), true) : [];
            
            // Add new log to the TOP of the array
            array_unshift($logs, [
                'time' => time(),
                'char' => $client['Name'] ?? 'Unknown',
                'boss' => $boss_name,
                'exp_delta' => $current_exp - $prev_exp,
                'loot_delta' => $current_item_count - $prev_items
            ]);
            
            if (count($logs) > 50) $logs = array_slice($logs, 0, 50);
            file_put_contents($debug_log_file, json_encode($logs));
            echo "[CRON_COMMUNITY] DEBUG: Boss kill tracked for Acc $accId ($boss_name)\n";
        }
    }

    // Evaluate progression for each active CE
    foreach ($active_community_events as &$ce) {
        $ce_contribution = 0;
        
        if ($ce['goal_type'] === 'MESETA') {
            $delta = $curr_meseta - $prev_meseta;
            if ($delta > 0) $ce_contribution = $delta;
        } elseif ($ce['goal_type'] === 'LEVEL_UP') {
            $delta = $curr_level - $prev_level;
            if ($delta > 0) $ce_contribution = $delta;
        } elseif ($ce['goal_type'] === 'MAT_CONSUME') {
            $delta = $curr_mats - $prev_mats;
            if ($delta > 0) $ce_contribution = $delta;
        } elseif ($ce['goal_type'] === 'PLAYTIME') {
            $delta = $curr_playtime - $prev_playtime;
            if ($delta > 0) $ce_contribution = $delta;
        } elseif ($ce['goal_type'] === 'CHALLENGE_STAGES') {
            $delta = $curr_chal - $prev_chal;
            if ($delta > 0) $ce_contribution = $delta;
        } elseif ($ce['goal_type'] === 'ITEM') {
            if ($current_item_count > $prev_items) {
                $inventory = $client['InventoryItems'] ?? [];
                $target_val = trim((string)$ce['goal_target']);
                
                // Map of generic weapon types to their Hex IDs for backwards compatibility
                $generic_weapon_hex_map = [
                    'Saber' => '007000', 'Dagger' => '000300', 'Handgun' => '000600', 'Rifle' => '007600', 'Cane' => '007900', 'Rod' => '007A00', 'Wand' => '007B00',
                    'Brand' => '000101', 'Knife' => '000301', 'Autogun' => '000601', 'Sniper' => '000701', 'Stick' => '000A01', 'Baton' => '000C02',
                    'Buster' => '000102', 'Blade' => '007200', 'Lockgun' => '000602', 'Blaster' => '000702', 'Mace' => '000A02', 'Scepter' => '000C03',
                    'Pallasch' => '000103', 'Claymore' => '000203', 'Edge' => '000303', 'Berdys' => '000403', 'Sawcer' => '000503', 'Railgun' => '000603', 'Beam' => '000703', 'Launcher' => '00A600', 'Gatling' => '000803', 'Club' => '000A03', 'Striker' => '000B03',
                    'Gladius' => '000104', 'Calibur' => '000204', 'Ripper' => '000304', 'Gungnir' => '000404', 'Diska' => '000504', 'Raygun' => '000604', 'Laser' => '000704', 'Arms' => '000904', 'Vulcan' => '000804',
                ];

                // Backwards compatibility for existing string-based community events
                if (!ctype_xdigit($target_val)) {
                    // Try to match the string to a hex code. If not found, fall back to null.
                    $target_val = $generic_weapon_hex_map[ucfirst(strtolower($target_val))] ?? null;
                } else {
                    // Remove any modifiers from the hex if present
                    $target_val = explode(' ', $target_val)[0];
                }

                if ($target_val) {
                    foreach ($inventory as $inv_item) {
                        // Check if the item's hex Data starts with our target hex prefix
                        if (isset($inv_item['Data']) && strpos($inv_item['Data'], $target_val) === 0) {
                            $ce_contribution = 1;
                            break;
                        }
                    }
                }
            }
        } elseif ($ce['goal_type'] === 'BOSS_ARENA') {
            $target_floor = $ce['goal_target'];
            $original_target = $target_floor;
            $recent_boss_fight = false;
            
            $prev_f = (int)($prev_state['floor'] ?? -1);
            $curr_f = (int)($client['LocationFloor'] ?? -1);
            $was_fast_kill = false;

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
            } else {
                $target_floor = (int)$target_floor;
                
                // Backwards compatibility / robust mapping of synthetic IDs to raw IDs + episode
                $episode = null;
                if ($target_floor === 15) {
                    $mapped_floor = 12; // Gal Gryphon
                    $episode = 2;
                } elseif ($target_floor === 16) {
                    $mapped_floor = 15; // Gol Dragon
                    $episode = 2;
                } elseif ($target_floor === 17) {
                    $mapped_floor = 14; // Barba Ray
                    $episode = 2;
                } elseif ($target_floor === 18) {
                    $mapped_floor = 13; // Olga Flow
                    $episode = 2;
                } elseif ($target_floor === 19) {
                    $mapped_floor = 9;  // Saint-Million
                    $episode = 4;
                } else {
                    $mapped_floor = $target_floor;
                }

                // If episode not determined by legacy synthetic ID, use the context parser!
                if ($episode === null) {
                    $episode = get_boss_episode_by_context($ce['title'], $ce['description'] ?? '', $mapped_floor);
                }

                $target_floor = $mapped_floor;
                $comp_key = "{$target_floor}_{$episode}";

                $fast_kill_preceding = [
                    '11_1' => [2],             // Dragon from Forest 2
                    '11_4' => [8],             // Sil Dragon
                    '12_1' => [5],             // De Rol Le from Cave 3
                    '12_2' => [5, 6, 7, 8, 9], // Gal Gryphon from CCA/Jungle/Mtn/Seaside
                    '13_1' => [7],             // Vol Opt from Mine 2
                    '13_2' => [11],            // Olga Flow from Seabed Lower
                    '14_1' => [10],            // Dark Falz from Ruins 3
                    '14_2' => [2],             // Barba Ray from Temple Beta
                    '15_2' => [4],             // Gol Dragon from Spaceship Beta
                    '9_4'  => [8],             // Saint-Million from Crater Interior
                ];

                // Catch players who enter the boss arena, kill the boss, and warp to town all within the 60-second cron window.
                if (isset($fast_kill_preceding[$comp_key]) && in_array($prev_f, $fast_kill_preceding[$comp_key])) {
                    if ($curr_f !== $prev_f && $curr_f !== $target_floor && $curr_f >= 0) {
                        $was_fast_kill = true;
                    }
                }
                
                if ($last_boss_arena === $target_floor && (time() - $last_boss_arena_time < 120)) {
                    $was_fast_kill = true;
                }
                $recent_boss_fight = ($curr_f === $target_floor) || ($prev_f === $target_floor) || $was_fast_kill;

                // Preceding floor validation for community events to prevent cross-episode collision false positives!
                $valid_preceding_floors = [
                    '11_1' => [1, 2],           // Dragon: Forest 1-2
                    '11_4' => [5, 6, 7, 8],     // Sil Dragon Crater/Desert
                    '12_1' => [3, 4, 5],        // De Rol Le: Cave 1-3
                    '12_2' => [5, 6, 7, 8, 9],  // Gal Gryphon: CCA, Jungle, Mountain, Seaside
                    '13_1' => [6, 7],           // Vol Opt: Mine 1-2
                    '13_2' => [10, 11],         // Olga Flow: Seabed Upper/Lower
                    '14_1' => [8, 9, 10],       // Dark Falz: Ruins 1-3
                    '14_2' => [1, 2],           // Barba Ray: VR Temple Alpha/Beta
                    '15_2' => [3, 4],           // Gol Dragon: VR Ship Alpha/Beta
                    '9_4'  => [5, 6, 7, 8],     // Saint-Million: Crater Interior / Desert
                ];
                $valid_floors_for_target = $valid_preceding_floors[$comp_key] ?? null;
                if ($recent_boss_fight && $valid_floors_for_target !== null && $pre_boss_floor >= 0) {
                    if (!in_array($pre_boss_floor, $valid_floors_for_target)) {
                        echo "[CRON_COMMUNITY] Boss target {$original_target} (key {$comp_key}) rejected: pre_boss_floor={$pre_boss_floor} not in valid set [" . implode(',', $valid_floors_for_target) . "] — wrong episode\n";
                        $recent_boss_fight = false;
                    }
                }

                // Strict Episode Validation using LobbyEpisode telemetry
                $expected_lobby_episode = "Episode " . $episode;
                if ($recent_boss_fight && $lobby_episode !== null && $lobby_episode !== $expected_lobby_episode) {
                    echo "[CRON_COMMUNITY] Boss target {$original_target} (key {$comp_key}) rejected: player is in {$lobby_episode}, but mission is for {$expected_lobby_episode}\n";
                    $recent_boss_fight = false;
                }
            }

            $exp_gain = ($current_exp - $prev_exp) >= 300;
            $loot_gain = (($client['Level'] ?? 1) >= 200) && ($current_item_count > $prev_items);
            if ($recent_boss_fight && ($exp_gain || $loot_gain)) {
                $ce_contribution = 1;
            }
        } elseif ($ce['goal_type'] === 'PATROL') {
            if (($client['LocationFloor'] ?? -1) === (int)$ce['goal_target']) {
                $patrol_progress = ($prev_state['patrol']['CE_'.$ce['id']] ?? 0) + 1;
                $current_patrols['CE_'.$ce['id']] = $patrol_progress;
                if ($patrol_progress >= 10) {
                    $ce_contribution = 1;
                    $current_patrols['CE_'.$ce['id']] = 0;
                }
            } else {
                $current_patrols['CE_'.$ce['id']] = $prev_state['patrol']['CE_'.$ce['id']] ?? 0;
            }
        }

        if ($ce_contribution > 0) {
            echo "[CRON_COMMUNITY] Account $accId contributed $ce_contribution to CE " . $ce['id'] . "\n";
            $p_ins = $db->prepare("INSERT INTO community_event_participants (event_id, account_id, contribution_count) VALUES (:eid, :aid, :contrib) 
                                   ON CONFLICT(event_id, account_id) DO UPDATE SET contribution_count = contribution_count + :contrib");
            $p_ins->bindValue(':eid', $ce['id'], SQLITE3_INTEGER);
            $p_ins->bindValue(':aid', $accId, SQLITE3_INTEGER);
            $p_ins->bindValue(':contrib', $ce_contribution, SQLITE3_INTEGER);
            $p_ins->execute();

            $db->query("UPDATE community_events SET current_progress = current_progress + " . (int)$ce_contribution . " WHERE id = " . (int)$ce['id']);
            
            // Re-fetch to update local cache within the same script run
            $ce['current_progress'] += $ce_contribution;
        }
    }

    $player_states[(string)$accId] = [
        'exp' => $current_exp,
        'items' => $current_item_count,
        'floor' => $curr_f,
        'level' => $curr_level,
        'patrol' => $current_patrols,
        'meseta' => $curr_meseta,
        'playtime' => $curr_playtime,
        'chal' => $curr_chal,
        'mats' => $curr_mats,
        'last_boss_arena' => $last_boss_arena,
        'last_boss_arena_time' => $last_boss_arena_time,
        'pre_boss_floor' => $pre_boss_floor
    ];
}

file_put_contents($state_cache_file, json_encode($player_states));

// --- 3. Milestone Tracking: Progress (20, 50, 80, 100%) ---
foreach ($active_community_events as $ce) {
    if ($ce['status'] !== 'active') continue;

    $target = (int)$ce['target_amount'];
    if ($ce['goal_type'] === 'PLAYTIME') {
        $target = $target * 3600;
    }
    
    if ($target <= 0) continue;

    $progress = (int)$ce['current_progress'];
    $pct = ($progress / $target) * 100;

    // Ordered highest to lowest to prevent skipping
    if ($pct >= 100) {
        $db->query("UPDATE community_events SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = " . (int)$ce['id']);
        trigger_ai_milestone($ce, '100', '100%');
    } elseif ($pct >= 80 && empty($ce['announced_80'])) {
        trigger_ai_milestone($ce, '80', '80%');
    } elseif ($pct >= 50 && empty($ce['announced_50'])) {
        trigger_ai_milestone($ce, '50', '50%');
    } elseif ($pct >= 20 && empty($ce['announced_20'])) {
        trigger_ai_milestone($ce, '20', '20%');
    }
}

echo "[CRON_COMMUNITY] Run complete.\n";
?>
