<?php
$db = new SQLite3(__DIR__ . '/../db/website.db');
$res = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='mods'");
$row = $res->fetchArray();
echo $row['sql'];
?>
