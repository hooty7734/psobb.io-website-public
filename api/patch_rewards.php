<?php
require_once 'db.php';
require_once 'reward_tables.php';
$db = get_db();

$total_updated = 0;
$res = $db->query("SELECT id, reward_item_string FROM missions");

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $reward_string = trim($row['reward_item_string']);
    $segments = explode(',', $reward_string);
    $is_valid = true;
    
    foreach ($segments as $segment) {
        $segment = trim($segment);
        if (empty($segment)) continue;
        
        // Is it Meseta?
        if (preg_match('/^\d+\s+meseta$/i', $segment)) continue;
        
        // Does it start with a hex payload?
        $first_part = explode(' ', $segment)[0];
        if (ctype_xdigit($first_part) && (strlen($first_part) === 6 || strlen($first_part) >= 32)) {
            continue;
        }
        
        // If neither, it's invalid!
        $is_valid = false;
        break;
    }
    
    if (!$is_valid) {
        // Regenerate a generic reward using the common reward table
        // We'll give them a random 1000-5000 Meseta and a random material/grinder
        $new_reward = get_common_reward_item(80, 'HUmar', 'Random') . ", " . (mt_rand(1, 5) * 1000) . " Meseta";
        
        $upd = $db->prepare("UPDATE missions SET reward_item_string = :ri WHERE id = :id");
        $upd->bindValue(':ri', $new_reward, SQLITE3_TEXT);
        $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
        $upd->execute();
        $total_updated += $db->changes();
    }
}

echo "Reward Patch Complete! Fixed $total_updated broken reward strings.\n";
