<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Boss & Floor Tracker — High-Frequency Cron (5-second intervals)
 * --------------------------------------------------------------------------
 * Lightweight daemon that polls newserv every 5 seconds to track:
 *   - Boss floor entry/exit transitions + EXP/item deltas → boss kills
 *   - All floor transitions with timestamps → speedrun floor clears
 *
 * Writes to two event files consumed by cron_missions.php:
 *   db/.boss_kills.json  — Confirmed boss kill events
 *   db/.floor_clears.json — Floor transition events with timing
 * --------------------------------------------------------------------------
 */
require_once __DIR__ . '/config.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

// Enforce single instance
$lock_file = fopen(__DIR__ . '/../db/.cron_boss_tracker.lock', 'c');
if (!flock($lock_file, LOCK_EX | LOCK_NB)) {
    echo "[TRACKER] Another instance is running. Exiting.\n";
    exit;
}

$boss_floors = [9, 11, 12, 13, 14, 15];
$state_file = __DIR__ . '/../db/.boss_tracker_state.json';
$kills_file = __DIR__ . '/../db/.boss_kills.json';
$floor_clears_file = __DIR__ . '/../db/.floor_clears.json';

$script_start = time();

// Run for up to 55 seconds (fits within 1-minute crontab)
while (time() - $script_start < 55) {
    $data = @file_get_contents($NEWSERV_API_URL . "/y/clients");
    if (!$data) {
        sleep(5);
        continue;
    }

    $clients = json_decode(iconv('UTF-8', 'UTF-8//IGNORE', $data), true);
    if (!is_array($clients) || empty($clients)) {
        sleep(5);
        continue;
    }

    // Load previous state
    $prev_states = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
    if (!is_array($prev_states)) $prev_states = [];

    // Load existing events
    $kill_events = file_exists($kills_file) ? json_decode(file_get_contents($kills_file), true) : [];
    if (!is_array($kill_events)) $kill_events = [];
    $floor_clear_events = file_exists($floor_clears_file) ? json_decode(file_get_contents($floor_clears_file), true) : [];
    if (!is_array($floor_clear_events)) $floor_clear_events = [];

    $new_states = [];

    foreach ($clients as $client) {
        if (!isset($client['Account']['AccountID'])) continue;
        if (!isset($client['EXP'])) continue;

        $accId = (string)$client['Account']['AccountID'];
        $charName = $client['Name'] ?? 'Unknown';
        $key = $accId . '_' . $charName;

        $curr_floor = (int)($client['LocationFloor'] ?? -1);
        $curr_exp = (int)($client['EXP'] ?? 0);
        $curr_items = count($client['InventoryItems'] ?? []);
        $curr_level = (int)($client['Level'] ?? 1);
        $lobby_id = $client['LobbyID'] ?? null;

        $prev = $prev_states[$key] ?? null;
        $prev_floor = $prev ? (int)$prev['floor'] : -1;

        // Skip zero-EXP loading screen artifacts
        if ($curr_exp === 0 && $prev && $prev['exp'] > 0) {
            $new_states[$key] = $prev;
            continue;
        }

        $in_boss = in_array($curr_floor, $boss_floors);
        $was_in_boss = $prev ? in_array($prev_floor, $boss_floors) : false;

        // ================================================================
        // FLOOR TRANSITION TRACKING (for SPEEDRUN_FLOOR)
        // Record every floor-to-floor transition with timing
        // ================================================================
        if ($prev && $curr_floor !== $prev_floor && $curr_floor >= 0 && $prev_floor >= 0) {
            $time_on_prev_floor = time() - ($prev['floor_entry_time'] ?? time());
            
            // Only record meaningful dungeon transitions (not town/lobby)
            if ($prev_floor > 0) {
                $floor_clear_events[$key][] = [
                    'from_floor' => $prev_floor,
                    'to_floor' => $curr_floor,
                    'time_on_floor' => $time_on_prev_floor,
                    'time' => time(),
                    'lobby_id' => $lobby_id,
                ];
            }
        }

        // ================================================================
        // BOSS KILL TRACKING
        // ================================================================

        // ── Player just entered a boss room ──
        if ($in_boss && !$was_in_boss) {
            $pre_boss = ($prev && $prev_floor > 0 && !in_array($prev_floor, $boss_floors))
                ? $prev_floor
                : ($prev['pre_boss_floor'] ?? -1);
            
            $new_states[$key] = [
                'floor' => $curr_floor,
                'exp' => $curr_exp,
                'items' => $curr_items,
                'level' => $curr_level,
                'lobby_id' => $lobby_id,
                'floor_entry_time' => time(),
                'entry_exp' => $curr_exp,
                'entry_items' => $curr_items,
                'pre_boss_floor' => $pre_boss,
                'in_boss' => true,
            ];

            continue;
        }

        // ── Player is still in the boss room ──
        if ($in_boss && $was_in_boss && $curr_floor === $prev_floor) {
            $entry_exp = $prev['entry_exp'] ?? $curr_exp;
            $entry_items = $prev['entry_items'] ?? $curr_items;
            $exp_delta = $curr_exp - $entry_exp;
            $item_delta = $curr_items - $entry_items;

            $min_exp = 20;
            $killed = ($exp_delta >= $min_exp) || ($curr_level >= 200 && $item_delta > 0);

            if ($killed && empty($prev['kill_recorded'])) {
                $entry_time = $prev['floor_entry_time'] ?? time();
                $pre_boss = $prev['pre_boss_floor'] ?? -1;
                $time_in_arena = time() - $entry_time;

                echo "[TRACKER] KILL! {$charName} (Acc {$accId}) on floor {$curr_floor} | entered " . date('H:i:s', $entry_time) . " | killed " . date('H:i:s') . " | {$time_in_arena}s in arena | EXP +{$exp_delta} items +{$item_delta}\n";

                $kill_events[$key][] = [
                    'floor' => $curr_floor,
                    'time' => time(),
                    'exp_delta' => $exp_delta,
                    'item_delta' => $item_delta,
                    'entry_time' => $entry_time,
                    'time_in_arena' => $time_in_arena,
                    'pre_boss_floor' => $pre_boss,
                    'lobby_id' => $lobby_id,
                ];

                $new_states[$key] = array_merge($prev, [
                    'floor' => $curr_floor,
                    'exp' => $curr_exp,
                    'items' => $curr_items,
                    'kill_recorded' => true,
                ]);
                continue;
            }

            // Still fighting
            $new_states[$key] = array_merge($prev, [
                'floor' => $curr_floor,
                'exp' => $curr_exp,
                'items' => $curr_items,
            ]);
            continue;
        }

        // ── Player just left a boss room ──
        if (!$in_boss && $was_in_boss && $prev) {
            $entry_exp = $prev['entry_exp'] ?? $prev['exp'];
            $entry_items = $prev['entry_items'] ?? $prev['items'];
            $exp_delta = $curr_exp - $entry_exp;
            $item_delta = $curr_items - $entry_items;

            $min_exp = 20;
            $killed = ($exp_delta >= $min_exp) || ($curr_level >= 200 && $item_delta > 0);

            if ($killed && empty($prev['kill_recorded'])) {
                $entry_time = $prev['floor_entry_time'] ?? time();
                $pre_boss = $prev['pre_boss_floor'] ?? -1;
                $time_in_arena = time() - $entry_time;
                $boss_floor = $prev_floor;

                echo "[TRACKER] EXIT KILL! {$charName} (Acc {$accId}) floor {$boss_floor} | entered " . date('H:i:s', $entry_time) . " | exited " . date('H:i:s') . " | {$time_in_arena}s in arena | EXP +{$exp_delta}\n";

                $kill_events[$key][] = [
                    'floor' => $boss_floor,
                    'time' => time(),
                    'exp_delta' => $exp_delta,
                    'item_delta' => $item_delta,
                    'entry_time' => $entry_time,
                    'time_in_arena' => $time_in_arena,
                    'pre_boss_floor' => $pre_boss,
                    'lobby_id' => $lobby_id,
                ];
            }
        }

        // ── Default state ──
        $pre_boss = $prev['pre_boss_floor'] ?? -1;
        if (!$in_boss && $curr_floor > 0) {
            $pre_boss = $curr_floor;
        }

        // Track floor entry time — reset on transition, preserve if same floor
        $floor_entry_time = ($prev && $curr_floor === $prev_floor)
            ? ($prev['floor_entry_time'] ?? time())
            : time();

        $new_states[$key] = [
            'floor' => $curr_floor,
            'exp' => $curr_exp,
            'items' => $curr_items,
            'level' => $curr_level,
            'lobby_id' => $lobby_id,
            'pre_boss_floor' => $pre_boss,
            'floor_entry_time' => $floor_entry_time,
            'in_boss' => false,
        ];
    }

    // Prune old events (older than 5 minutes)
    $cutoff = time() - 300;
    foreach ($kill_events as $key => &$events) {
        $events = array_filter($events, fn($e) => $e['time'] > $cutoff);
        $events = array_values($events);
        if (empty($events)) unset($kill_events[$key]);
    }
    unset($events);
    foreach ($floor_clear_events as $key => &$events) {
        $events = array_filter($events, fn($e) => $e['time'] > $cutoff);
        $events = array_values($events);
        if (empty($events)) unset($floor_clear_events[$key]);
    }
    unset($events);

    // Save
    file_put_contents($state_file, json_encode($new_states));
    file_put_contents($kills_file, json_encode($kill_events));
    file_put_contents($floor_clears_file, json_encode($floor_clear_events));

    sleep(5);
}


?>
