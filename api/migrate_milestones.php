<?php
require_once 'db.php';
$db = get_db();
try {
  $db->exec("ALTER TABLE community_events ADD COLUMN announced_start BOOLEAN DEFAULT 0");
  $db->exec("ALTER TABLE community_events ADD COLUMN announced_20 BOOLEAN DEFAULT 0");
  $db->exec("ALTER TABLE community_events ADD COLUMN announced_50 BOOLEAN DEFAULT 0");
  $db->exec("ALTER TABLE community_events ADD COLUMN announced_80 BOOLEAN DEFAULT 0");
  echo "Migration complete.\n";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage() . "\n";
}
unlink(__FILE__);
