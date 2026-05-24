<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Floor ID Repair Script  (v2 — context-aware)
 * --------------------------------------------------------------------------
 *
 * Repairs missions stored with fabricated "synthetic" boss floor IDs.
 *
 * AMBIGUITY WARNING — Floor ID 15:
 *   Old synthetic: 15 = Gal Gryphon  → should become 12
 *   Real newserv:  15 = Gol Dragon   → should STAY 15
 *   We disambiguate by checking the mission title/description.
 *
 * Unambiguous synthetic IDs (these numbers don't exist in newserv):
 *   16 (was Gol Dragon)    → 15 (real newserv floor: Ep2 0x0F)
 *   17 (was Barba Ray)     → 14 (real newserv floor: Ep2 0x0E)
 *   18 (was Olga Flow)     → 13 (real newserv floor: Ep2 0x0D)
 *   19 (was Saint-Milion)  → 9  (real newserv floor: Ep4 0x09)
 *
 * Reference: newserv/src/StaticGameData.cc floor_defs[] (lines 523-574)
 *
 * Usage:
 *   php repair_floor_ids.php --dry-run    Preview changes
 *   php repair_floor_ids.php              Apply changes
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$dry_run = in_array('--dry-run', $argv);

require_once __DIR__ . '/db.php';
$db = get_db();

// =====================================================================
// Unambiguous synthetic → real mappings (these IDs don't exist in newserv)
// =====================================================================
$unambiguous_map = [
    16 => 15,  // Gol Dragon   → VR Spaceship Final (Ep2 0x0F)
    17 => 14,  // Barba Ray    → Dark Falz floor (Ep2 0x0E)
    18 => 13,  // Olga Flow    → Vol Opt floor (Ep2 0x0D)
    19 => 9,   // Saint-Milion → Ruins 2 floor (Ep4 0x09)
];

$unambiguous_names = [
    16 => 'Gol Dragon',
    17 => 'Barba Ray',
    18 => 'Olga Flow',
    19 => 'Saint-Milion',
];

// =====================================================================
// Ambiguous ID 15: Gal Gryphon (synthetic) vs Gol Dragon (real)
// Keywords that indicate it's a REAL Gol Dragon mission (keep as 15)
// =====================================================================
$gol_dragon_keywords = [
    'gol dragon', 'gol_dragon', 'spaceship', 'vr ship',
    'ゴルドラゴン', '宇宙船'
];

// Keywords that indicate it's a synthetic Gal Gryphon mission (change to 12)
$gal_gryphon_keywords = [
    'gal gryphon', 'gryphon', 'gal_gryphon', 'cca', 'jungle',
    'mountain', 'seaside', 'gal da val',
    'グリフォン', '中央管理区', '高山', '海岸', 'ジャングル'
];

$boss_types = ['BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS'];

$fixed_boss = 0;
$fixed_speedrun = 0;
$fixed_community = 0;
$skipped_ambiguous = 0;

echo "==========================================================\n";
echo "PSOBB Floor ID Repair Script (v2 — context-aware)\n";
if ($dry_run) echo "[DRY RUN MODE — no changes will be made]\n";
echo "==========================================================\n\n";

/**
 * Determine if floor 15 is Gal Gryphon (synthetic) or Gol Dragon (real)
 * by checking the mission title + description for known keywords.
 * Returns: 'gal_gryphon' | 'gol_dragon' | 'ambiguous'
 */
function classify_floor_15($title, $description = '') {
    global $gol_dragon_keywords, $gal_gryphon_keywords;
    $text = strtolower($title . ' ' . $description);

    foreach ($gol_dragon_keywords as $kw) {
        if (strpos($text, $kw) !== false) return 'gol_dragon';
    }
    foreach ($gal_gryphon_keywords as $kw) {
        if (strpos($text, $kw) !== false) return 'gal_gryphon';
    }
    return 'ambiguous';
}

// =====================================================================
// 1. Fix Boss-type missions
// =====================================================================
echo "--- Boss Missions (BOSS_ARENA, MENTOR_BOSS, HARDCORE_MENTOR, DIVERSE_PARTY_BOSS) ---\n\n";

$placeholders = implode(',', array_fill(0, count($boss_types), '?'));
$stmt = $db->prepare("
    SELECT m.id, m.title, m.description, m.goal_type, m.goal_target
    FROM missions m
    WHERE m.goal_type IN ($placeholders)
    ORDER BY m.id
");
foreach ($boss_types as $i => $type) {
    $stmt->bindValue($i + 1, $type, SQLITE3_TEXT);
}
$result = $stmt->execute();

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $target = trim($row['goal_target']);
    if (!is_numeric($target)) continue;

    $val = (int)$target;

    // --- Unambiguous synthetic IDs (16, 17, 18, 19) ---
    if (isset($unambiguous_map[$val])) {
        $new_target = (string)$unambiguous_map[$val];
        $boss_name = $unambiguous_names[$val];

        if (!$dry_run) {
            $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
            $upd->bindValue(':t', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
        }
        $fixed_boss++;
        $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
        echo "$action Mission #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '$target' ($boss_name) → '$new_target'\n";
        continue;
    }

    // --- Ambiguous ID 15: Gal Gryphon (synthetic) vs Gol Dragon (real) ---
    if ($val === 15) {
        $classification = classify_floor_15($row['title'], $row['description'] ?? '');

        if ($classification === 'gol_dragon') {
            // It's a real Gol Dragon mission — 15 is already correct
            echo "[SKIP] Mission #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '15' is real Gol Dragon — already correct\n";
            continue;
        } elseif ($classification === 'gal_gryphon') {
            // It's a synthetic Gal Gryphon — change 15 → 12
            if (!$dry_run) {
                $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
                $upd->bindValue(':t', '12', SQLITE3_TEXT);
                $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $upd->execute();
            }
            $fixed_boss++;
            $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
            echo "$action Mission #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '15' (Gal Gryphon synthetic) → '12'\n";
        } else {
            // Can't tell from the title — flag for manual review
            $skipped_ambiguous++;
            echo "[AMBIGUOUS] Mission #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '15' — cannot determine if Gal Gryphon or Gol Dragon. MANUAL REVIEW NEEDED.\n";
        }
    }
}

if ($fixed_boss === 0) {
    echo "No boss missions with synthetic floor IDs found. All clear!\n";
}

echo "\n";

// =====================================================================
// 2. Fix SPEEDRUN_BOSS missions (format: "FLOORID_TIME")
// =====================================================================
echo "--- Speedrun Boss Missions ---\n\n";

$stmt2 = $db->prepare("SELECT id, title, description, goal_target FROM missions WHERE goal_type = 'SPEEDRUN_BOSS' ORDER BY id");
$result2 = $stmt2->execute();

while ($row = $result2->fetchArray(SQLITE3_ASSOC)) {
    $target = trim($row['goal_target']);
    $parts = explode('_', $target);

    if (count($parts) >= 2 && is_numeric($parts[0])) {
        $floor_val = (int)$parts[0];
        $time_limit = $parts[1];

        // Unambiguous synthetics
        if (isset($unambiguous_map[$floor_val])) {
            $new_floor = $unambiguous_map[$floor_val];
            $new_target = $new_floor . '_' . $time_limit;
            $boss_name = $unambiguous_names[$floor_val];

            if (!$dry_run) {
                $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
                $upd->bindValue(':t', $new_target, SQLITE3_TEXT);
                $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $upd->execute();
            }
            $fixed_speedrun++;
            $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
            echo "$action Mission #{$row['id']} \"{$row['title']}\" | '$target' ($boss_name) → '$new_target'\n";
            continue;
        }

        // Ambiguous 15
        if ($floor_val === 15) {
            $classification = classify_floor_15($row['title'], $row['description'] ?? '');

            if ($classification === 'gol_dragon') {
                echo "[SKIP] Mission #{$row['id']} \"{$row['title']}\" | '15_$time_limit' is real Gol Dragon — already correct\n";
            } elseif ($classification === 'gal_gryphon') {
                $new_target = '12_' . $time_limit;
                if (!$dry_run) {
                    $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
                    $upd->bindValue(':t', $new_target, SQLITE3_TEXT);
                    $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $upd->execute();
                }
                $fixed_speedrun++;
                $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
                echo "$action Mission #{$row['id']} \"{$row['title']}\" | '$target' (Gal Gryphon synthetic) → '$new_target'\n";
            } else {
                $skipped_ambiguous++;
                echo "[AMBIGUOUS] Mission #{$row['id']} \"{$row['title']}\" | '15_$time_limit' — cannot determine. MANUAL REVIEW NEEDED.\n";
            }
        }
    }
}

if ($fixed_speedrun === 0) {
    echo "No speedrun boss missions with synthetic floor IDs found. All clear!\n";
}

echo "\n";

// =====================================================================
// 3. Fix Community Events
// =====================================================================
echo "--- Community Events ---\n\n";

$stmt3 = $db->prepare("SELECT id, title, description, goal_type, goal_target FROM community_events ORDER BY id");
$result3 = $stmt3->execute();

while ($row = $result3->fetchArray(SQLITE3_ASSOC)) {
    $target = trim($row['goal_target']);
    if (!is_numeric($target)) continue;

    $val = (int)$target;

    if (isset($unambiguous_map[$val])) {
        $new_target = (string)$unambiguous_map[$val];
        $boss_name = $unambiguous_names[$val];
        if (!$dry_run) {
            $upd = $db->prepare("UPDATE community_events SET goal_target = :t WHERE id = :id");
            $upd->bindValue(':t', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
        }
        $fixed_community++;
        $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
        echo "$action Event #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '$target' ($boss_name) → '$new_target'\n";
    } elseif ($val === 15) {
        $classification = classify_floor_15($row['title'], $row['description'] ?? '');
        if ($classification === 'gol_dragon') {
            echo "[SKIP] Event #{$row['id']} \"{$row['title']}\" | '15' is real Gol Dragon — already correct\n";
        } elseif ($classification === 'gal_gryphon') {
            if (!$dry_run) {
                $upd = $db->prepare("UPDATE community_events SET goal_target = :t WHERE id = :id");
                $upd->bindValue(':t', '12', SQLITE3_TEXT);
                $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $upd->execute();
            }
            $fixed_community++;
            $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
            echo "$action Event #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '15' (Gal Gryphon synthetic) → '12'\n";
        } else {
            $skipped_ambiguous++;
            echo "[AMBIGUOUS] Event #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '15' — MANUAL REVIEW NEEDED.\n";
        }
    }
}

if ($fixed_community === 0) {
    echo "No community events with synthetic floor IDs found. All clear!\n";
}

echo "\n";

// =====================================================================
// 4. Summary
// =====================================================================
$total = $fixed_boss + $fixed_speedrun + $fixed_community;
echo "==========================================================\n";
if ($dry_run) {
    echo "[DRY RUN] Would fix $total record(s) total:\n";
} else {
    echo "Repair Completed! Fixed $total record(s) total:\n";
}
echo "  Boss missions fixed:      $fixed_boss\n";
echo "  Speedrun missions fixed:  $fixed_speedrun\n";
echo "  Community events fixed:   $fixed_community\n";
if ($skipped_ambiguous > 0) {
    echo "\n  ⚠ AMBIGUOUS records:      $skipped_ambiguous (need manual review!)\n";
}
echo "==========================================================\n";

if ($dry_run && $total > 0) {
    echo "\nRe-run without --dry-run to apply these changes.\n";
}
