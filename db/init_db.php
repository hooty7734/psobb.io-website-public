<?php
/**
 * PSOBB Website Database Initializer
 *
 * Creates the SQLite database and all required tables from scratch.
 * Safe to re-run — uses CREATE TABLE IF NOT EXISTS throughout.
 * Run via CLI: php db/init_db.php
 */
$dbPath = __DIR__ . '/website.db';
$db = new SQLite3($dbPath);
$db->enableExceptions(true);
$db->busyTimeout(5000);

// High-concurrency optimizations
$db->exec("PRAGMA journal_mode = WAL;");
$db->exec("PRAGMA synchronous = NORMAL;");
$db->exec("PRAGMA temp_store = MEMORY;");
$db->exec("PRAGMA foreign_keys = ON;");

// =============================================
// Core Account Tables
// =============================================

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    account_id INTEGER NOT NULL,
    discord_id TEXT,
    language TEXT DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_discord ON users(discord_id) WHERE discord_id IS NOT NULL");

$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    token TEXT PRIMARY KEY,
    email TEXT NOT NULL,
    username TEXT NOT NULL,
    expires_at INTEGER NOT NULL
)");

// =============================================
// Milestone Rewards
// =============================================

$db->exec("CREATE TABLE IF NOT EXISTS rewards_claimed (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    character_name TEXT NOT NULL,
    level_milestone INTEGER NOT NULL,
    category TEXT NOT NULL,
    item_string TEXT NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(account_id, character_name, level_milestone)
)");

// =============================================
// Login Streaks & Daily Rewards
// =============================================

$db->exec("CREATE TABLE IF NOT EXISTS daily_logins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    login_date TEXT NOT NULL,
    UNIQUE(account_id, login_date)
)");

$db->exec("CREATE TABLE IF NOT EXISTS streak_claims (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    streak_cycle INTEGER NOT NULL DEFAULT 1,
    milestone INTEGER NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(account_id, streak_cycle, milestone)
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_rewards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    claim_date TEXT NOT NULL,
    item_string TEXT NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(account_id, claim_date)
)");

// =============================================
// Bounty Board (AI Missions)
// =============================================

$db->exec("CREATE TABLE IF NOT EXISTS missions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    goal_type TEXT NOT NULL,
    goal_target TEXT NOT NULL,
    reward_item_string TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS player_missions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    mission_id INTEGER NOT NULL,
    status TEXT DEFAULT 'in_progress',
    completed_at DATETIME,
    UNIQUE(account_id, mission_id),
    FOREIGN KEY(mission_id) REFERENCES missions(id)
)");

// =============================================
// Community Events
// =============================================

$db->exec("CREATE TABLE IF NOT EXISTS community_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    goal_type TEXT NOT NULL,
    goal_target TEXT NOT NULL,
    target_amount INTEGER NOT NULL,
    current_progress INTEGER DEFAULT 0,
    reward_item_string TEXT NOT NULL,
    top_3_reward_item_string TEXT,
    status TEXT DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    announced_start BOOLEAN DEFAULT 0,
    announced_20 BOOLEAN DEFAULT 0,
    announced_50 BOOLEAN DEFAULT 0,
    announced_80 BOOLEAN DEFAULT 0
)");

$db->exec("CREATE TABLE IF NOT EXISTS community_event_participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    account_id INTEGER NOT NULL,
    contribution_count INTEGER DEFAULT 0,
    reward_claimed BOOLEAN DEFAULT 0,
    UNIQUE(event_id, account_id),
    FOREIGN KEY(event_id) REFERENCES community_events(id)
)");

// =============================================
// Mod Manager
// =============================================

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

$db->exec("CREATE TABLE IF NOT EXISTS mod_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mod_id TEXT NOT NULL,
    account_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(mod_id, account_id)
)");

echo "Database initialized successfully at $dbPath\n";
?>