<?php
require_once '/var/www/html/psobb.io-website/api/db.php';
$db = get_db();

// Tier 1 Weapons list for safe defaults
$tier1_weapons = ['Saber', 'Handgun', 'Cane', 'Sword', 'Dagger', 'Partisan', 'Slicer', 'Rifle', 'Shot', 'Mechgun', 'Wand', 'Staff'];

// Find ALL bugged missions with '?' in the goal_target
$res = $db->query("SELECT id, title, goal_target FROM missions WHERE goal_target LIKE '%?%'");
if (!$res) {
    die("Query failed: " . $db->lastErrorMsg() . "\n");
}

while($row = $res->fetchArray(SQLITE3_ASSOC)) {
    echo "Found bugged mission: ";
    print_r($row);
    
    // Pick a random Tier 1 weapon as a better default
    $safe_target = $tier1_weapons[array_rand($tier1_weapons)];
    
    $stmt = $db->prepare("UPDATE missions SET goal_target = :target WHERE id = :id");
    if (!$stmt) {
        echo "Prepare failed: " . $db->lastErrorMsg() . "\n";
        continue;
    }
    $stmt->bindValue(':target', $safe_target, SQLITE3_TEXT);
    $stmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
    $exec = $stmt->execute();
    if ($exec) {
        echo "Updated bugged mission ID " . $row['id'] . " (" . $row['title'] . ") to target '" . $safe_target . "'\n";
    } else {
        echo "Update failed: " . $db->lastErrorMsg() . "\n";
    }
}
