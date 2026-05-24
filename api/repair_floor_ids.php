<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Floor ID Repair Script
 * --------------------------------------------------------------------------
 * 
 * Repairs all missions in the database that were stored with fabricated
 * "synthetic" boss floor IDs that don't exist in newserv.
 *
 * The incorrect synthetic IDs and their correct replacements:
 *   15 (was Gal Gryphon)   → 12 (real newserv floor: Ep2 0x0C, same as De Rol Le)
 *   16 (was Gol Dragon)    → 15 (real newserv floor: Ep2 0x0F)
 *   17 (was Barba Ray)     → 14 (real newserv floor: Ep2 0x0E, same as Dark Falz)
 *   18 (was Olga Flow)     → 13 (real newserv floor: Ep2 0x0D, same as Vol Opt)
 *   19 (was Saint-Milion)  → 9  (real newserv floor: Ep4 0x09, same as Ruins 2)
 *
 * Reference: newserv/src/StaticGameData.cc floor_defs[] (lines 523-574)
 * 
 * Run via CLI: php repair_floor_ids.php
 * Add --dry-run flag to preview changes without modifying the database.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$dry_run = in_array('--dry-run', $argv);

require_once __DIR__ . '/db.php';
$db = get_db();

// Synthetic → Real floor ID mapping
$synthetic_to_real = [
    15 => 12,  // Gal Gryphon  → De Rol Le floor (Ep2 0x0C)
    16 => 15,  // Gol Dragon   → VR Spaceship Final (Ep2 0x0F)
    17 => 14,  // Barba Ray    → Dark Falz floor (Ep2 0x0E)
    18 => 13,  // Olga Flow    → Vol Opt floor (Ep2 0x0D)
    19 => 9,   // Saint-Milion → Ruins 2 floor (Ep4 0x09)
];

$synthetic_names = [
    15 => 'Gal Gryphon',
    16 => 'Gol Dragon',
    17 => 'Barba Ray',
    18 => 'Olga Flow',
    19 => 'Saint-Milion',
];

$boss_types = ['BOSS_ARENA', 'MENTOR_BOSS', 'HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS'];

$fixed_boss = 0;
$fixed_speedrun = 0;

echo "==========================================================\n";
echo "PSOBB Floor ID Repair Script\n";
if ($dry_run) echo "[DRY RUN MODE — no changes will be made]\n";
echo "==========================================================\n\n";

// =====================================================================
// 1. Fix Boss-type missions with synthetic goal_target IDs
// =====================================================================
echo "--- Boss Missions (BOSS_ARENA, MENTOR_BOSS, HARDCORE_MENTOR, DIVERSE_PARTY_BOSS) ---\n\n";

$placeholders = implode(',', array_fill(0, count($boss_types), '?'));
$stmt = $db->prepare("
    SELECT m.id, m.title, m.goal_type, m.goal_target
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
    if (isset($synthetic_to_real[$val])) {
        $new_target = (string)$synthetic_to_real[$val];
        $boss_name = $synthetic_names[$val];
        
        if (!$dry_run) {
            $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
            $upd->bindValue(':t', $new_target, SQLITE3_TEXT);
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
        }
        $fixed_boss++;
        
        $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
        echo "$action Mission #{$row['id']} \"{$row['title']}\" ({$row['goal_type']}) | '$target' ($boss_name) → '$new_target'\n";
    }
}

if ($fixed_boss === 0) {
    echo "No boss missions with synthetic floor IDs found. All clear!\n";
}

echo "\n";

// =====================================================================
// 2. Fix SPEEDRUN_BOSS missions with synthetic floor IDs in "FLOORID_TIME" format
// =====================================================================
echo "--- Speedrun Boss Missions ---\n\n";

$stmt2 = $db->prepare("SELECT id, title, goal_target FROM missions WHERE goal_type = 'SPEEDRUN_BOSS' ORDER BY id");
$result2 = $stmt2->execute();

while ($row = $result2->fetchArray(SQLITE3_ASSOC)) {
    $target = trim($row['goal_target']);
    $parts = explode('_', $target);
    
    if (count($parts) >= 2 && is_numeric($parts[0])) {
        $floor_val = (int)$parts[0];
        $time_limit = $parts[1];
        
        if (isset($synthetic_to_real[$floor_val])) {
            $new_floor = $synthetic_to_real[$floor_val];
            $new_target = $new_floor . '_' . $time_limit;
            $boss_name = $synthetic_names[$floor_val];
            
            if (!$dry_run) {
                $upd = $db->prepare("UPDATE missions SET goal_target = :t WHERE id = :id");
                $upd->bindValue(':t', $new_target, SQLITE3_TEXT);
                $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $upd->execute();
            }
            $fixed_speedrun++;
            
            $action = $dry_run ? "[WOULD FIX]" : "[FIXED]";
            echo "$action Mission #{$row['id']} \"{$row['title']}\" | '$target' ($boss_name) → '$new_target'\n";
        }
    }
}

if ($fixed_speedrun === 0) {
    echo "No speedrun boss missions with synthetic floor IDs found. All clear!\n";
}

echo "\n";

// =====================================================================
// 3. Summary
// =====================================================================
$total = $fixed_boss + $fixed_speedrun;
echo "==========================================================\n";
if ($dry_run) {
    echo "[DRY RUN] Would fix $total mission(s) total:\n";
} else {
    echo "Repair Completed! Fixed $total mission(s) total:\n";
}
echo "  Boss missions with synthetic IDs fixed: $fixed_boss\n";
echo "  Speedrun boss missions with synthetic IDs fixed: $fixed_speedrun\n";
echo "==========================================================\n";

if ($dry_run && $total > 0) {
    echo "\nRe-run without --dry-run to apply these changes.\n";
}
