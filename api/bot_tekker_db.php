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
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
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
            is_active INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE IF NOT EXISTS tekker_player_state (
            user_id TEXT NOT NULL,
            drop_id TEXT NOT NULL,
            attempts_used INTEGER NOT NULL,
            max_attempts INTEGER NOT NULL,
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
    ");
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
            $stmt = $db->prepare("INSERT INTO tekker_active_drops
                (drop_id, stat_native, stat_abeast, stat_machine, stat_dark, stat_hit, hint_attribute, is_active)
                VALUES (:id,:n,:a,:m,:d,:h,:hint,1)");
            $stmt->bindValue(':id', $in['drop_id'], SQLITE3_TEXT);
            $stmt->bindValue(':n', (int)$in['stat_native'], SQLITE3_INTEGER);
            $stmt->bindValue(':a', (int)$in['stat_abeast'], SQLITE3_INTEGER);
            $stmt->bindValue(':m', (int)$in['stat_machine'], SQLITE3_INTEGER);
            $stmt->bindValue(':d', (int)$in['stat_dark'], SQLITE3_INTEGER);
            $stmt->bindValue(':h', (int)$in['stat_hit'], SQLITE3_INTEGER);
            $stmt->bindValue(':hint', $in['hint_attribute'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
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
            $stmt = $db->prepare("INSERT INTO tekker_player_state (user_id, drop_id, attempts_used, max_attempts)
                VALUES (:u,:d,:au,:ma)
                ON CONFLICT(user_id, drop_id) DO UPDATE SET attempts_used = excluded.attempts_used");
            $stmt->bindValue(':u', $in['userId'], SQLITE3_TEXT);
            $stmt->bindValue(':d', $in['dropId'], SQLITE3_TEXT);
            $stmt->bindValue(':au', (int)$in['attemptsUsed'], SQLITE3_INTEGER);
            $stmt->bindValue(':ma', (int)$in['maxAttempts'], SQLITE3_INTEGER);
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
            $stmt->bindValue(':t', $in['token_id'], SQLITE3_TEXT);
            $stmt->bindValue(':o', $in['owner_id'], SQLITE3_TEXT);
            $stmt->bindValue(':n', (int)$in['stat_native'], SQLITE3_INTEGER);
            $stmt->bindValue(':a', (int)$in['stat_abeast'], SQLITE3_INTEGER);
            $stmt->bindValue(':m', (int)$in['stat_machine'], SQLITE3_INTEGER);
            $stmt->bindValue(':d', (int)$in['stat_dark'], SQLITE3_INTEGER);
            $stmt->bindValue(':h', (int)$in['stat_hit'], SQLITE3_INTEGER);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'getToken': {
            $stmt = $db->prepare("SELECT * FROM tekker_tokens WHERE token_id = :t");
            $stmt->bindValue(':t', $in['tokenId'], SQLITE3_TEXT);
            $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
            $result = $r ? $r : null;
            break;
        }
        case 'getUnclaimedTokens': {
            $stmt = $db->prepare("SELECT * FROM tekker_tokens WHERE owner_id = :o AND is_claimed = 0 ORDER BY created_at DESC");
            $stmt->bindValue(':o', $in['ownerId'], SQLITE3_TEXT);
            $res = $stmt->execute();
            $rows = [];
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
            $result = $rows;
            break;
        }
        case 'getAllTokens': {
            $res = $db->query("SELECT * FROM tekker_tokens ORDER BY created_at DESC");
            $rows = [];
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
            $result = $rows;
            break;
        }
        case 'transferToken': {
            $stmt = $db->prepare("UPDATE tekker_tokens SET owner_id = :o WHERE token_id = :t");
            $stmt->bindValue(':o', $in['newOwnerId'], SQLITE3_TEXT);
            $stmt->bindValue(':t', $in['tokenId'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'markTokenClaimed': {
            $stmt = $db->prepare("UPDATE tekker_tokens SET is_claimed = 1, claimed_by = :c, claimed_at = datetime('now') WHERE token_id = :t");
            $stmt->bindValue(':c', $in['claimerId'], SQLITE3_TEXT);
            $stmt->bindValue(':t', $in['tokenId'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
            break;
        }
        case 'deleteToken': {
            $stmt = $db->prepare("DELETE FROM tekker_tokens WHERE token_id = :t");
            $stmt->bindValue(':t', $in['tokenId'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true, 'deleted' => $db->changes()];
            break;
        }
        case 'setTokenClaimed': {
            if (!empty($in['claimed'])) {
                $stmt = $db->prepare("UPDATE tekker_tokens SET is_claimed = 1, claimed_by = :c, claimed_at = datetime('now') WHERE token_id = :t");
                $stmt->bindValue(':c', $in['claimerId'] ?? null, SQLITE3_TEXT);
            } else {
                $stmt = $db->prepare("UPDATE tekker_tokens SET is_claimed = 0, claimed_by = NULL, claimed_at = NULL WHERE token_id = :t");
            }
            $stmt->bindValue(':t', $in['tokenId'], SQLITE3_TEXT);
            $stmt->execute();
            $result = ['ok' => true];
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
