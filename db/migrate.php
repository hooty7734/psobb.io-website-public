<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

$db = get_db();

try {
    $db->exec("BEGIN TRANSACTION");
    
    // Create new table with character_index
    $db->exec("CREATE TABLE IF NOT EXISTS rewards_claimed_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        character_name TEXT NOT NULL,
        character_index INTEGER NOT NULL DEFAULT 0,
        level_milestone INTEGER NOT NULL,
        category TEXT NOT NULL,
        item_string TEXT NOT NULL,
        claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(account_id, character_name, character_index, level_milestone)
    )");
    
    // Copy data
    $db->exec("INSERT INTO rewards_claimed_new (id, account_id, character_name, character_index, level_milestone, category, item_string, claimed_at)
               SELECT id, account_id, character_name, 0, level_milestone, category, item_string, claimed_at FROM rewards_claimed");
    
    // Drop old table
    $db->exec("DROP TABLE rewards_claimed");
    
    // Rename new table
    $db->exec("ALTER TABLE rewards_claimed_new RENAME TO rewards_claimed");
    
    $db->exec("COMMIT");
    echo "Migration successful.";
} catch (Exception $e) {
    $db->exec("ROLLBACK");
    echo "Migration failed: " . $e->getMessage();
}
?>
