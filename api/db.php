<?php
/**
 * PSOBB Website Database Provider
 * 
 * Manages the SQLite3 database connection and handles automatic schema 
 * initialization/migrations on the fly. Also provides the fallback 
 * Brevo email delivery mechanism.
 */
error_reporting(0);
ini_set('display_errors', 0);

/**
 * Retrieves a singleton-like SQLite3 database connection.
 * Automatically runs required schema migrations (e.g., adding missing columns or tables) 
 * to ensure the database is up-to-date with the current application version.
 *
 * @throws Exception If the database file cannot be opened or created.
 * @return SQLite3 The established SQLite3 database connection.
 */
function get_db()
{
    $path = __DIR__ . '/../db/website.db';
    try {
        $db = new SQLite3($path);
        $db->enableExceptions(true);
        // 30s timeout: cron jobs hold write locks for multiple seconds during
        // per-player batch processing; user-facing redemptions must wait them out.
        $db->busyTimeout(30000);

        // High-concurrency optimizations
        $db->exec("PRAGMA journal_mode = WAL;");
        $db->exec("PRAGMA synchronous = NORMAL;");
        $db->exec("PRAGMA temp_store = MEMORY;");
        $db->exec("PRAGMA cache_size = -8000;");  // 8MB page cache
        // Note: PRAGMA foreign_keys is enabled AFTER all schema migrations run,
        // so self-healing drops (e.g. bot_tokens FK repair) execute without errors.

        // --- Auto-migration for 'users' table ---
        // Ensure discord_id column exists
        $result = $db->query("PRAGMA table_info(users)");
        $hasDiscordId = false;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'discord_id') {
                $hasDiscordId = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasDiscordId) {
            $db->exec("ALTER TABLE users ADD COLUMN discord_id TEXT");
            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_discord ON users(discord_id) WHERE discord_id IS NOT NULL");
        }

        // Ensure language column exists
        $hasLanguage = false;
        $result = $db->query("PRAGMA table_info(users)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'language') {
                $hasLanguage = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasLanguage) {
            $db->exec("ALTER TABLE users ADD COLUMN language TEXT DEFAULT 'en'");
        }

        // Ensure display_name column exists (leaderboard alias)
        $hasDisplayName = false;
        $result = $db->query("PRAGMA table_info(users)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'display_name') {
                $hasDisplayName = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasDisplayName) {
            $db->exec("ALTER TABLE users ADD COLUMN display_name TEXT");
        }

        // Ensure receive_system_mail column exists (QoL notification setting)
        $hasReceiveSystemMail = false;
        $result = $db->query("PRAGMA table_info(users)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'receive_system_mail') {
                $hasReceiveSystemMail = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasReceiveSystemMail) {
            $db->exec("ALTER TABLE users ADD COLUMN receive_system_mail INTEGER DEFAULT 1");
        }

        // Ensure receive_discord_streak_msg column exists (QoL Discord DM streak alert setting)
        $hasReceiveDiscordStreakMsg = false;
        $result = $db->query("PRAGMA table_info(users)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'receive_discord_streak_msg') {
                $hasReceiveDiscordStreakMsg = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasReceiveDiscordStreakMsg) {
            $db->exec("ALTER TABLE users ADD COLUMN receive_discord_streak_msg INTEGER DEFAULT 1");
        }

        // --- Auto-migration for Bounty/Missions & Streaks tables ---
        $db->exec("
            CREATE TABLE IF NOT EXISTS missions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                goal_type TEXT NOT NULL,
                goal_target TEXT NOT NULL,
                reward_item_string TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS player_missions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                mission_id INTEGER NOT NULL,
                status TEXT DEFAULT 'in_progress',
                completed_at DATETIME,
                FOREIGN KEY(mission_id) REFERENCES missions(id)
            );
            
            CREATE TABLE IF NOT EXISTS daily_logins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                login_date TEXT NOT NULL,
                UNIQUE(account_id, login_date)
            );
            
            CREATE TABLE IF NOT EXISTS community_events (
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
            );
        ");

        // Ensure top_3_reward_item_string column exists in community_events
        $hasTop3 = false;
        $result = $db->query("PRAGMA table_info(community_events)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'top_3_reward_item_string') {
                $hasTop3 = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasTop3) {
            $db->exec("ALTER TABLE community_events ADD COLUMN top_3_reward_item_string TEXT");
        }
        
        // Ensure character_name exists in player_missions to support per-character bounties
        $hasCharName = false;
        $result = $db->query("PRAGMA table_info(player_missions)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'character_name') {
                $hasCharName = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasCharName) {
            $db->exec("ALTER TABLE player_missions ADD COLUMN character_name TEXT");
        }
        
        // --- Auto-migration for Community Event Participants & Game Mods ---
        $db->exec("
            CREATE TABLE IF NOT EXISTS community_event_participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                contribution_count INTEGER DEFAULT 0,
                reward_claimed BOOLEAN DEFAULT 0,
                UNIQUE(event_id, account_id),
                FOREIGN KEY(event_id) REFERENCES community_events(id)
            );
            
            CREATE TABLE IF NOT EXISTS mods (
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
            );

            CREATE TABLE IF NOT EXISTS mod_ratings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                mod_id TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                rating INTEGER NOT NULL CHECK(rating >= 1 AND rating <= 5),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(mod_id, account_id)
            );
            
            CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                data TEXT,
                last_accessed INTEGER NOT NULL
            );
        ");

        // Clean up legacy draft table if it exists to prevent conflicts
        $db->exec("DROP TABLE IF EXISTS game_mods");

        // --- Auto-migration for LFG requests table ---
        $db->exec("
            CREATE TABLE IF NOT EXISTS lfg_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                character_name TEXT NOT NULL,
                class TEXT NOT NULL,
                level INTEGER NOT NULL,
                section_id TEXT NOT NULL,
                game_id INTEGER,
                game_name TEXT,
                bounty_id INTEGER,
                looking_for TEXT,
                description TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Check for bounty_id column
        $hasBountyId = false;
        $result = $db->query("PRAGMA table_info(lfg_requests)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'bounty_id') {
                $hasBountyId = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasBountyId) {
            $db->exec("ALTER TABLE lfg_requests ADD COLUMN bounty_id INTEGER");
        }

        // Check for looking_for column
        $hasLookingFor = false;
        $result = $db->query("PRAGMA table_info(lfg_requests)");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === 'looking_for') {
                $hasLookingFor = true;
                break;
            }
        }
        $result->finalize();
        if (!$hasLookingFor) {
            $db->exec("ALTER TABLE lfg_requests ADD COLUMN looking_for TEXT");
        }

        // --- Bot API Tokens ---
        // Self-healing: if the table was created with the broken FK (REFERENCES users(account_id)),
        // drop it and recreate. The FK is invalid because account_id has no UNIQUE/PK constraint.
        $bad_schema = $db->querySingle(
            "SELECT sql FROM sqlite_master WHERE type='table' AND name='bot_tokens' AND sql LIKE '%REFERENCES users%'"
        );
        if ($bad_schema) {
            $db->exec("DROP TABLE IF EXISTS bot_tokens");
            $db->exec("DROP INDEX IF EXISTS idx_bot_tokens_hash");
        }
        $db->exec("
            CREATE TABLE IF NOT EXISTS bot_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME,
                expires_at DATETIME,
                revoked INTEGER DEFAULT 0
            );
            CREATE INDEX IF NOT EXISTS idx_bot_tokens_hash ON bot_tokens(token_hash) WHERE revoked = 0;
        ");

        // Enable FK enforcement only after all schema migrations are done
        $db->exec("PRAGMA foreign_keys = ON;");

        return $db;

    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Dispatches an email using either the Brevo HTTP API or the fallback PHP mail() function.
 * Logs all dispatch attempts and their results to 'db/mail.log'.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject line of the email.
 * @param string $message The plain-text body of the email.
 * @return bool True if the email was successfully accepted for delivery, false otherwise.
 */
function send_email($to, $subject, $message)
{
    global $BREVO_API_KEY, $SMTP_FROM;
    $from = $SMTP_FROM ?: 'pso@psobb.io';

    // Log intent
    $logEntry = "[" . date('Y-m-d H:i:s') . "] To: $to | Subject: $subject";

    if (!empty($BREVO_API_KEY)) {
        // Use Brevo HTTP API (Bypasses SMTP port blocks)
        $url = 'https://api.brevo.com/v3/smtp/email';
        $data = [
            'sender' => ['email' => $from],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'textContent' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $BREVO_API_KEY,
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300);
        $logEntry .= " | Via: Brevo API | Success: " . ($success ? 'Yes' : 'No') . " | Resp: $response\n";
    } else {
        // Fallback to local mail()
        $headers = 'From: ' . $from . "\r\n" .
            'Reply-To: ' . $from . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        $sent = @mail($to, $subject, $message, $headers);
        $logEntry .= " | Via: Internal mail() | Sent: " . ($sent ? 'Yes' : 'No') . "\n";
    }

    @file_put_contents(__DIR__ . '/../db/mail.log', $logEntry, FILE_APPEND);

    return isset($success) ? $success : $sent;
}
