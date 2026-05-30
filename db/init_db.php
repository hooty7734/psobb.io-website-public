<?php
$dbPath = __DIR__ . '/website.db';
$db = new SQLite3($dbPath);
$db->enableExceptions(true);
$db->busyTimeout(5000);

// High-concurrency optimizations
$db->exec("PRAGMA journal_mode = WAL;");
$db->exec("PRAGMA synchronous = NORMAL;");
$db->exec("PRAGMA temp_store = MEMORY;");
$db->exec("PRAGMA foreign_keys = ON;");

// Users table
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    account_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Password Resets table
$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    token TEXT PRIMARY KEY,
    username TEXT NOT NULL,
    expires_at INTEGER NOT NULL
)");

// Rewards Claimed table
$db->exec("CREATE TABLE IF NOT EXISTS rewards_claimed (
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

// Daily Logins table (tracks unique play days per account)
$db->exec("CREATE TABLE IF NOT EXISTS daily_logins (
    account_id INTEGER NOT NULL,
    login_date TEXT NOT NULL,
    UNIQUE(account_id, login_date)
)");

// Streak Claims table (tracks which streak milestones have been claimed per cycle)
$db->exec("CREATE TABLE IF NOT EXISTS streak_claims (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    streak_cycle INTEGER NOT NULL DEFAULT 1,
    milestone INTEGER NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(account_id, streak_cycle, milestone)
)");

// Missions table
$db->exec("CREATE TABLE IF NOT EXISTS missions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    goal_type TEXT NOT NULL,
    goal_target INTEGER NOT NULL,
    reward_item_string TEXT NOT NULL
)");

// Player Missions Tracking
$db->exec("CREATE TABLE IF NOT EXISTS player_missions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    mission_id INTEGER NOT NULL,
    character_name TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'in_progress',
    progress INTEGER NOT NULL DEFAULT 0,
    accepted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    UNIQUE(account_id, mission_id)
)");

// Schema migrations for existing databases — add columns if missing
$cols = [];
$colRes = $db->query("PRAGMA table_info(player_missions)");
while ($col = $colRes->fetchArray(SQLITE3_ASSOC)) {
    $cols[] = $col['name'];
}
if (!in_array('character_name', $cols)) {
    $db->exec("ALTER TABLE player_missions ADD COLUMN character_name TEXT NOT NULL DEFAULT ''");
}
if (!in_array('accepted_at', $cols)) {
    $db->exec("ALTER TABLE player_missions ADD COLUMN accepted_at DATETIME DEFAULT CURRENT_TIMESTAMP");
}

// Mods table
$db->exec("CREATE TABLE IF NOT EXISTS mods (
    mod_id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    author TEXT NOT NULL,
    submitted_by TEXT NOT NULL,
    version TEXT NOT NULL,
    description TEXT NOT NULL,
    purpose TEXT NOT NULL,
    category TEXT NOT NULL,
    file_path TEXT NOT NULL,
    image_path TEXT,
    file_size INTEGER NOT NULL,
    status TEXT DEFAULT 'pending',
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Mod Ratings table
$db->exec("CREATE TABLE IF NOT EXISTS mod_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mod_id TEXT NOT NULL,
    account_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(mod_id, account_id)
)");

echo "Database initialized at $dbPath\n";
?>