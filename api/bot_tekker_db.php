<?php
// =====================================================================
// Discord Tekker Challenge — storage backend
// The bot keeps the game logic and calls this endpoint for all persistence
// (it carries no local database). Bearer-authed, same as bot_api.php.
// Request:  POST JSON { "op": "<operation>", ...params }
// Response: { "success": true, "result": ... } | { "success": false, "error": ... }
// Tables (tekker_*) are auto-created by db.php.
// =====================================================================
ini_set('display_errors', '0');
register_shutdown_function(function () {
    $err = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($err && in_array($err['type'], $fatalTypes, true)) {
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'error' => 'PHP fatal', 'message' => $err['message'], 'file' => $err['file'], 'line' => $err['line']]);
    }
});
set_exception_handler(function ($e) {
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'error' => 'PHP exception', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
});

require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

// --- Bearer auth (mirrors bot_api.php: legacy secret OR a bcrypt bot_tokens row) ---
$auth = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('getallheaders')) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $auth = $headers['authorization'] ?? '';
}
$provided = (str_starts_with($auth, 'Bearer ')) ? substr($auth, 7) : $auth;

$authenticated = false;
if (!empty($BOT_API_SECRET) && hash_equals($BOT_API_SECRET, $provided)) {
    $authenticated = true;
}
if (!$authenticated && !empty($provided)) {
    $db = get_db();
    $res = $db->query("SELECT id, token_hash FROM bot_tokens WHERE revoked = 0 AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if (password_verify($provided, $row['token_hash'])) {
            $authenticated = true;
            $upd = $db->prepare("UPDATE bot_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id");
            $upd->bindValue(':id', $row['id'], SQLITE3_INTEGER);
            $upd->execute();
            break;
        }
    }
}
if (!$authenticated) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

// --- Dispatch ---
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$op = $in['op'] ?? '';
$db = get_db();

// Auto-create Tekker Challenge tables if they do not exist.
// This isolates the minigame database schema migrations entirely to this endpoint,
// keeping the core db.php clean and unmodified.
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS tekker_active_drops (
            drop_id TEXT PRIMARY KEY,
            stat_native INTEGER NOT NULL,
            stat_abeast INTEGER NOT NULL,
            stat_machine INTEGER NOT NULL,
            stat_dark INTEGER NOT NULL,
            stat_hit INTEGER NOT NULL,
            hint_attribute TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            base_native INTEGER DEFAULT 0,
            base_abeast INTEGER DEFAULT 0,
            base_machine INTEGER DEFAULT 0,
            base_dark INTEGER DEFAULT 0,
            base_hit INTEGER DEFAULT 0,
            spawn_time TEXT,
            despawn_time TEXT,
            guesses_since_shift INTEGER DEFAULT 0,
            second_zero_discovered INTEGER DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS tekker_player_state (
            user_id TEXT NOT NULL,
            drop_id TEXT NOT NULL,
            attempts_used INTEGER NOT NULL,
            max_attempts INTEGER NOT NULL,
            lifetime_attempts INTEGER DEFAULT 0,
            attempts_remaining INTEGER DEFAULT 0,
            last_guess_at TEXT,
            PRIMARY KEY (user_id, drop_id)
        );
        CREATE TABLE IF NOT EXISTS tekker_telemetry (
            log_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            drop_id TEXT NOT NULL,
            guess_array TEXT NOT NULL,
            result_state TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS tekker_tokens (
            token_id TEXT PRIMARY KEY,
            owner_id TEXT NOT NULL,
            stat_native INTEGER NOT NULL,
            stat_abeast INTEGER NOT NULL,
            stat_machine INTEGER NOT NULL,
            stat_dark INTEGER NOT NULL,
            stat_hit INTEGER NOT NULL,
            is_claimed INTEGER DEFAULT 0,
            claimed_by TEXT,
            claimed_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS tekker_active_users (
            user_id TEXT PRIMARY KEY
        );
        CREATE TABLE IF NOT EXISTS tekker_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS tekker_claim_log (
            claim_id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            discord_id TEXT,
            token_ids TEXT NOT NULL,
            token_count INTEGER NOT NULL,
            weapon_hex TEXT NOT NULL,
            weapon_name TEXT NOT NULL,
            stat_native INTEGER DEFAULT 0,
            stat_abeast INTEGER DEFAULT 0,
            stat_machine INTEGER DEFAULT 0,
            stat_dark INTEGER DEFAULT 0,
            stat_hit INTEGER DEFAULT 0,
            claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Dynamic auto-migration: add new columns if they do not exist
    // 1. For tekker_active_drops
    $result = $db->query("PRAGMA table_info(tekker_active_drops)");
    $cols = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $cols[] = $row['name'];
    }
    $result->finalize();

    if (!in_array('base_native', $cols)) {
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN base_native INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN base_abeast INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN base_machine INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN base_dark INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN base_hit INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN spawn_time TEXT");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN despawn_time TEXT");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN guesses_since_shift INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_active_drops ADD COLUMN second_zero_discovered INTEGER DEFAULT 0");
    }

    // 2. For tekker_player_state
    $result = $db->query("PRAGMA table_info(tekker_player_state)");
    $cols = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $cols[] = $row['name'];
    }
    $result->finalize();

    if (!in_array('lifetime_attempts', $cols)) {
        $db->exec("ALTER TABLE tekker_player_state ADD COLUMN lifetime_attempts INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_player_state ADD COLUMN attempts_remaining INTEGER DEFAULT 0");
        $db->exec("ALTER TABLE tekker_player_state ADD COLUMN last_guess_at TEXT");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Schema migration error: ' . $e->getMessage()]);
    exit;
}

try {
    $result = null;
    switch ($op) {
        case 'ping':
            $result = ['ok' => true];
            break;

        case 'getActiveDrop': {
            $r = $db->querySingle("SELECT * FROM tekker_active_drops WHERE is_active = 1 LIMIT 1", true);
            $result = $r ? $r : null;
            break;
        }
        case 'createDrop': {
            $db->exec("UPDATE tekker_active_drops SET is_active = 0 WHERE is_active = 1");
            
            // Choose 2 locked zeros randomly
            $categories = ["Native", "A.Beast", "Machine", "Dark", "Hit"];
            $lockedIdx = array_rand($categories, 2);
            $locked1 = $categories[$lockedIdx[0]];
            $locked2 = $categories[$lockedIdx[1]];
            
            // Choose one of the locked zeros as public hint
            $hintAttr = (rand(0, 1) === 0) ? $locked1 : $locked2;
            
            $base_stats = [
                'Native' => 0, 'A.Beast' => 0, 'Machine' => 0, 'Dark' => 0, 'Hit' => 0
            ];
            $active_stats = [
                'Native' => 0, 'A.Beast' => 0, 'Machine' => 0, 'Dark' => 0, 'Hit' => 0
            ];
            
            $variances = [-10, -5, 0, 5, 10];
            foreach ($categories as $cat) {
                if ($cat === $locked1 || $cat === $locked2) {
                    $base_stats[$cat] = 0;
                    $active_stats[$cat] = 0;
                } else {
                    $base = rand(3, 16) * 5; // 15 to 80
                    $base_stats[$cat] = $base;
                    
                    $var = $variances[array_rand($variances)];
                    $active = $base + $var;
                    $active_stats[$cat] = max(0, min(90, $active));
                }
            }
            
            $dropId = 'd-' . round(microtime(true) * 1000);
            $spawnTime = date('Y-m-d H:i:s');
            $despawnTime = date('Y-m-d H:i:s', time() + 7200); // 2 hours from now
            
            $stmt = $db->prepare("INSERT INTO tekker_active_drops
                (drop_id, stat_native, stat_abeast, stat_machine, stat_dark, stat_hit, hint_attribute, is_active,
                 base_native, base_abeast, base_machine, base_dark, base_hit, spawn_time, despawn_time, guesses_since_shift, second_zero_discovered)
                VALUES (:id, :sn, :sa, :sm, :sd, :sh, :hint, 1, :bn, :ba, :bm, :bd, :bh, :spawn, :despawn, 0, 0)");
            
            $stmt->bindValue(':id', $dropId, SQLITE3_TEXT);
            $stmt->bindValue(':sn', $active_stats['Native'], SQLITE3_INTEGER);
            $stmt->bindValue(':sa', $active_stats['A.Beast'], SQLITE3_INTEGER);
            $stmt->bindValue(':sm', $active_stats['Machine'], SQLITE3_INTEGER);
            $stmt->bindValue(':sd', $active_stats['Dark'], SQLITE3_INTEGER);
            $stmt->bindValue(':sh', $active_stats['Hit'], SQLITE3_INTEGER);
            $stmt->bindValue(':hint', $hintAttr, SQLITE3_TEXT);
            
            $stmt->bindValue(':bn', $base_stats['Native'], SQLITE3_INTEGER);
            $stmt->bindValue(':ba', $base_stats['A.Beast'], SQLITE3_INTEGER);
            $stmt->bindValue(':bm', $base_stats['Machine'], SQLITE3_INTEGER);
            $stmt->bindValue(':bd', $base_stats['Dark'], SQLITE3_INTEGER);
            $stmt->bindValue(':bh', $base_stats['Hit'], SQLITE3_INTEGER);
            
            $stmt->bindValue(':spawn', $spawnTime, SQLITE3_TEXT);
            $stmt->bindValue(':despawn', $despawnTime, SQLITE3_TEXT);
            
            $stmt->execute();
            
            $result = [
                'drop_id' => $dropId,
                'stat_native' => $active_stats['Native'],
                'stat_abeast' => $active_stats['A.Beast'],
                'stat_machine' => $active_stats['Machine'],
                'stat_dark' => $active_stats['Dark'],
                'stat_hit' => $active_stats['Hit'],
                'hint_attribute' => $hintAttr,
                'base_native' => $base_stats['Native'],
                'base_abeast' => $base_stats['A.Beast'],
                'base_machine' => $base_stats['Machine'],
                'base_dark' => $base_stats['Dark'],
                'base_hit' => $base_stats['Hit'],
                'spawn_time' => $spawnTime,
                'despawn_time' => $despawnTime
            ];
            break;
        }
        case 'deactivateDrop': {
            $stmt = $db->prepare("UPDATE tekker_active_drops SET is_active = 0 WHERE drop_id = :id");
            $stmt->bindValue(':id', $in['dropId'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'getPlayerState': {
            $stmt = $db->prepare("SELECT * FROM tekker_player_state WHERE user_id = :u AND drop_id = :d");
            $stmt->bindValue(':u', $in['userId'], SQLITE3_TEXT);
            $stmt->bindValue(':d', $in['dropId'], SQLITE3_TEXT);
            $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            $result = $r ? $r : null;
            break;
        }
        case 'upsertPlayerState': {
            $stmt = $db->prepare("INSERT INTO tekker_player_state 
                (user_id, drop_id, attempts_used, max_attempts, lifetime_attempts, attempts_remaining, last_guess_at)
                VALUES (:u, :d, :au, :ma, :la, :ar, :lga)
                ON CONFLICT(user_id, drop_id) DO UPDATE SET 
                    attempts_used = excluded.attempts_used,
                    lifetime_attempts = excluded.lifetime_attempts,
                    attempts_remaining = excluded.attempts_remaining,
                    last_guess_at = excluded.last_guess_at");
            $stmt->bindValue(':u', $in['userId'], SQLITE3_TEXT);
            $stmt->bindValue(':d', $in['dropId'], SQLITE3_TEXT);
            $stmt->bindValue(':au', (int)$in['attemptsUsed'], SQLITE3_INTEGER);
            $stmt->bindValue(':ma', (int)$in['maxAttempts'], SQLITE3_INTEGER);
            $stmt->bindValue(':la', (int)$in['lifetimeAttempts'], SQLITE3_INTEGER);
            $stmt->bindValue(':ar', (int)$in['attemptsRemaining'], SQLITE3_INTEGER);
            $stmt->bindValue(':lga', $in['lastGuessAt'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'addTelemetryLog': {
            $stmt = $db->prepare("INSERT INTO tekker_telemetry (user_id, drop_id, guess_array, result_state)
                VALUES (:u,:d,:g,:r)");
            $stmt->bindValue(':u', $in['userId'], SQLITE3_TEXT);
            $stmt->bindValue(':d', $in['dropId'], SQLITE3_TEXT);
            $stmt->bindValue(':g', json_encode($in['guessArray'] ?? []), SQLITE3_TEXT);
            $stmt->bindValue(':r', $in['resultState'] ?? '', SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'addActiveUser': {
            $stmt = $db->prepare("INSERT OR IGNORE INTO tekker_active_users (user_id) VALUES (:u)");
            $stmt->bindValue(':u', $in['userId'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'getActiveUserCount': {
            $result = (int)$db->querySingle("SELECT COUNT(*) FROM tekker_active_users");
            break;
        }
        case 'clearActiveUsers': {
            $db->exec("DELETE FROM tekker_active_users");
            $result = ['ok' => true];
            break;
        }
        case 'getTriggerThreshold': {
            $v = $db->querySingle("SELECT value FROM tekker_settings WHERE key = 'trigger_threshold'");
            $result = ($v !== false && $v !== null) ? (int)$v : 30;
            break;
        }
        case 'setTriggerThreshold': {
            $stmt = $db->prepare("INSERT INTO tekker_settings (key, value) VALUES ('trigger_threshold', :v)
                ON CONFLICT(key) DO UPDATE SET value = excluded.value");
            $stmt->bindValue(':v', (string)($in['value'] ?? 30), SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'createToken': {
            $stmt = $db->prepare("INSERT INTO tekker_tokens
                (token_id, owner_id, stat_native, stat_abeast, stat_machine, stat_dark, stat_hit, is_claimed)
                VALUES (:t,:o,:n,:a,:m,:d,:h,0)");
            $stmt->bindValue(':t', trim($in['token_id'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':o', trim($in['owner_id'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':n', (int)($in['stat_native'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':a', (int)($in['stat_abeast'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':m', (int)($in['stat_machine'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':d', (int)($in['stat_dark'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':h', (int)($in['stat_hit'] ?? 0), SQLITE3_INTEGER);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'getToken': {
            $stmt = $db->prepare("SELECT * FROM tekker_tokens WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :t");
            $stmt->bindValue(':t', trim($in['tokenId'] ?? ''), SQLITE3_TEXT);
            $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ($r) {
                $r['token_id'] = trim($r['token_id']);
                $r['owner_id'] = trim($r['owner_id']);
            }
            $result = $r ? $r : null;
            break;
        }
        case 'getUnclaimedTokens': {
            $stmt = $db->prepare("SELECT * FROM tekker_tokens WHERE trim(owner_id, char(13)||char(10)||' '||char(9)) = :o AND is_claimed = 0 ORDER BY created_at DESC");
            $stmt->bindValue(':o', trim($in['ownerId'] ?? ''), SQLITE3_TEXT);
            $res = $stmt->execute();
            $rows = [];
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $row['token_id'] = trim($row['token_id']);
                $row['owner_id'] = trim($row['owner_id']);
                $rows[] = $row;
            }
            $result = $rows;
            break;
        }
        case 'getAllTokens': {
            $res = $db->query("SELECT * FROM tekker_tokens ORDER BY created_at DESC");
            $rows = [];
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $row['token_id'] = trim($row['token_id']);
                $row['owner_id'] = trim($row['owner_id']);
                $rows[] = $row;
            }
            $result = $rows;
            break;
        }
        case 'transferToken': {
            $stmt = $db->prepare("UPDATE tekker_tokens SET owner_id = :o WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :t");
            $stmt->bindValue(':o', trim($in['newOwnerId'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':t', trim($in['tokenId'] ?? ''), SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'markTokenClaimed': {
            $stmt = $db->prepare("UPDATE tekker_tokens SET is_claimed = 1, claimed_by = :c, claimed_at = datetime('now') WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :t");
            $stmt->bindValue(':c', trim($in['claimerId'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':t', trim($in['tokenId'] ?? ''), SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'deleteToken': {
            $stmt = $db->prepare("DELETE FROM tekker_tokens WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :t");
            $stmt->bindValue(':t', trim($in['tokenId'] ?? ''), SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true, 'deleted' => $db->changes()];
            break;
        }
        case 'getClaimLog': {
            // Consolidated claim history: who claimed, which tokens, and for what item.
            // Newest first. Optional ?limit (default 100, capped 500).
            $limit = isset($in['limit']) ? max(1, min(500, (int)$in['limit'])) : 100;
            $res = $db->query("SELECT * FROM tekker_claim_log ORDER BY datetime(claimed_at) DESC, claim_id DESC LIMIT " . (int)$limit);
            $rows = [];
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                if (isset($row['discord_id'])) $row['discord_id'] = trim((string)$row['discord_id']);
                $rows[] = $row;
            }
            $result = $rows;
            break;
        }
        case 'shiftActiveDropStats': {
            $dropId = $in['dropId'];
            $stmt = $db->prepare("SELECT * FROM tekker_active_drops WHERE drop_id = :id LIMIT 1");
            $stmt->bindValue(':id', $dropId, SQLITE3_TEXT);
            $drop = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            
            if ($drop) {
                $categories = ["Native", "A.Beast", "Machine", "Dark", "Hit"];
                $variances = [-10, -5, 0, 5, 10];
                $new_active = [];
                
                foreach ($categories as $cat) {
                    $baseKey = 'base_' . strtolower(str_replace('.', '', $cat));
                    $base = (int)$drop[$baseKey];
                    if ($base > 0) {
                        $var = $variances[array_rand($variances)];
                        $active = $base + $var;
                        $new_active[$cat] = max(0, min(90, $active));
                    } else {
                        $new_active[$cat] = 0;
                    }
                }
                
                $upd = $db->prepare("UPDATE tekker_active_drops SET
                    stat_native = :sn,
                    stat_abeast = :sa,
                    stat_machine = :sm,
                    stat_dark = :sd,
                    stat_hit = :sh,
                    guesses_since_shift = 0
                    WHERE drop_id = :id");
                
                $upd->bindValue(':id', $dropId, SQLITE3_TEXT);
                $upd->bindValue(':sn', $new_active['Native'], SQLITE3_INTEGER);
                $upd->bindValue(':sa', $new_active['A.Beast'], SQLITE3_INTEGER);
                $upd->bindValue(':sm', $new_active['Machine'], SQLITE3_INTEGER);
                $upd->bindValue(':sd', $new_active['Dark'], SQLITE3_INTEGER);
                $upd->bindValue(':sh', $new_active['Hit'], SQLITE3_INTEGER);
                $upd->execute();
                
                $result = [
                    'ok' => true,
                    'stat_native' => $new_active['Native'],
                    'stat_abeast' => $new_active['A.Beast'],
                    'stat_machine' => $new_active['Machine'],
                    'stat_dark' => $new_active['Dark'],
                    'stat_hit' => $new_active['Hit']
                ];
            } else {
                $result = ['ok' => false, 'error' => 'Drop not found'];
            }
            break;
        }
        case 'incrementDropGuesses': {
            $dropId = $in['dropId'];
            
            $stmt = $db->prepare("UPDATE tekker_active_drops 
                SET guesses_since_shift = guesses_since_shift + 1 
                WHERE drop_id = :id");
            $stmt->bindValue(':id', $dropId, SQLITE3_TEXT);
            $stmt->execute();
            
            $stmt = $db->prepare("SELECT guesses_since_shift FROM tekker_active_drops WHERE drop_id = :id LIMIT 1");
            $stmt->bindValue(':id', $dropId, SQLITE3_TEXT);
            $val = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            $count = $val ? (int)$val['guesses_since_shift'] : 0;
            
            $shiftTriggered = false;
            if ($count >= 12) {
                $stmtObj = $db->prepare("SELECT * FROM tekker_active_drops WHERE drop_id = :id LIMIT 1");
                $stmtObj->bindValue(':id', $dropId, SQLITE3_TEXT);
                $drop = $stmtObj->execute()->fetchArray(SQLITE3_ASSOC);
                
                if ($drop) {
                    $categories = ["Native", "A.Beast", "Machine", "Dark", "Hit"];
                    $variances = [-10, -5, 0, 5, 10];
                    $new_active = [];
                    foreach ($categories as $cat) {
                        $baseKey = 'base_' . strtolower(str_replace('.', '', $cat));
                        $base = (int)$drop[$baseKey];
                        if ($base > 0) {
                            $var = $variances[array_rand($variances)];
                            $active = $base + $var;
                            $new_active[$cat] = max(0, min(90, $active));
                        } else {
                            $new_active[$cat] = 0;
                        }
                    }
                    
                    $upd = $db->prepare("UPDATE tekker_active_drops SET
                        stat_native = :sn,
                        stat_abeast = :sa,
                        stat_machine = :sm,
                        stat_dark = :sd,
                        stat_hit = :sh,
                        guesses_since_shift = 0
                        WHERE drop_id = :id");
                    $upd->bindValue(':id', $dropId, SQLITE3_TEXT);
                    $upd->bindValue(':sn', $new_active['Native'], SQLITE3_INTEGER);
                    $upd->bindValue(':sa', $new_active['A.Beast'], SQLITE3_INTEGER);
                    $upd->bindValue(':sm', $new_active['Machine'], SQLITE3_INTEGER);
                    $upd->bindValue(':sd', $new_active['Dark'], SQLITE3_INTEGER);
                    $upd->bindValue(':sh', $new_active['Hit'], SQLITE3_INTEGER);
                    $upd->execute();
                    $shiftTriggered = true;
                }
            }
            
            $result = [
                'ok' => true,
                'count' => $count,
                'shift_triggered' => $shiftTriggered
            ];
            break;
        }
        case 'discoverSecondZero': {
            $dropId = $in['dropId'];
            $stmt = $db->prepare("UPDATE tekker_active_drops SET second_zero_discovered = 1 WHERE drop_id = :id");
            $stmt->bindValue(':id', $dropId, SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'pulseDespawnTime': {
            $dropId = $in['dropId'];
            $stmt = $db->prepare("SELECT spawn_time, despawn_time FROM tekker_active_drops WHERE drop_id = :id LIMIT 1");
            $stmt->bindValue(':id', $dropId, SQLITE3_TEXT);
            $drop = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            
            if ($drop) {
                $spawn = strtotime($drop['spawn_time']);
                $despawn = strtotime($drop['despawn_time']);
                
                $newDespawn = $despawn + 1800; // +30 mins
                $hardCap = $spawn + 28800; // +8 hours
                if ($newDespawn > $hardCap) {
                    $newDespawn = $hardCap;
                }
                
                $newDespawnStr = date('Y-m-d H:i:s', $newDespawn);
                
                $upd = $db->prepare("UPDATE tekker_active_drops SET despawn_time = :despawn WHERE drop_id = :id");
                $upd->bindValue(':id', $dropId, SQLITE3_TEXT);
                $upd->bindValue(':despawn', $newDespawnStr, SQLITE3_TEXT);
                $upd->execute();
                
                $result = [
                    'ok' => true,
                    'despawn_time' => $newDespawnStr
                ];
            } else {
                $result = ['ok' => false, 'error' => 'Drop not found'];
            }
            break;
        }
        default:
            echo json_encode(['success' => false, 'error' => "Unknown tekker_db op: " . $op]);
            exit;
    }
    echo json_encode(['success' => true, 'result' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
