<?php
/**
 * PSOBB API: Claim Tekker Tokens
 *
 * GET  — Returns unclaimed tekker tokens for the linked Discord user.
 * POST — Claims a token, picks one of 3 chosen weapons, constructs stats, and drops it in game.
 */
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];
$db = get_db();

// ----------------------------------------------------------------
// GET — returns count + list of unclaimed tokens for this user
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if Discord is linked
    $stmt = $db->prepare("SELECT discord_id FROM users WHERE account_id = :uid");
    $stmt->bindValue(':uid', $accountId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $userRow = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    $discordId = $userRow ? trim($userRow['discord_id'] ?? '') : null;

    if (empty($discordId)) {
        echo json_encode([
            'linked' => false,
            'count' => 0,
            'tokens' => []
        ]);
        exit;
    }

    $stmt = $db->prepare("
        SELECT token_id, stat_native, stat_abeast, stat_machine, stat_dark, stat_hit, created_at
        FROM tekker_tokens
        WHERE trim(owner_id, char(13)||char(10)||' '||char(9)) = :discordId AND is_claimed = 0
        ORDER BY created_at DESC
    ");
    $stmt->bindValue(':discordId', $discordId, SQLITE3_TEXT);
    $res = $stmt->execute();

    $tokens = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $tokens[] = [
            'token_id'     => trim($row['token_id']),
            'stat_native'  => (int)$row['stat_native'],
            'stat_abeast'  => (int)$row['stat_abeast'],
            'stat_machine' => (int)$row['stat_machine'],
            'stat_dark'    => (int)$row['stat_dark'],
            'stat_hit'     => (int)$row['stat_hit'],
            'created_at'   => $row['created_at'],
        ];
    }

    echo json_encode([
        'linked' => true,
        'count' => count($tokens),
        'tokens' => $tokens
    ]);
    exit;
}

// ----------------------------------------------------------------
// POST — claims a specific token
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Check custom headers, JSON input, or standard POST for CSRF token
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    verify_csrf_token($csrfToken);

    $tokenIds = $input['token_ids'] ?? [];
    if (empty($tokenIds) && !empty($input['token_id'])) {
        $tokenIds = [$input['token_id']];
    }
    $weapons = $input['weapons'] ?? [];

    if (empty($tokenIds) || !is_array($tokenIds) || count($tokenIds) < 1 || count($tokenIds) > 3) {
        http_response_code(400);
        echo json_encode(['error' => 'You must select between 1 and 3 tokens']);
        exit;
    }

    if (!is_array($weapons) || count($weapons) !== 3) {
        http_response_code(400);
        echo json_encode(['error' => 'You must select exactly 3 weapons']);
        exit;
    }

    // Weapons grouped by star rating tier
    $tier9_weapons = [
        '004400' => 'RED HANDGUN',
        '003E00' => 'RED PARTISAN',
        '004100' => 'RED SLICER',
        '000E00' => 'DOUBLE SABER',
        '000105' => "DB'S SABER"
    ];
    $tier10_weapons = [
        '003400' => 'RED SWORD',
        '004200' => 'HANDGUN:GULD',
        '004300' => 'HANDGUN:MILLA',
        '004500' => 'FROZEN SHOOTER',
        '001300' => 'HOLY RAY',
        '002100' => 'CHAIN SAWD',
        '001000' => 'OROTIAGITO',
        '003001' => 'GIRASOLE',
        '000F02' => 'GOD HAND',
        '00C800' => 'DAYLIGHT SCAR'
    ];
    $tier11_weapons = [
        '001200' => 'SPREAD NEEDLE',
        '00AB00' => "LAME D'ARGENT"
    ];

    // Allowed weapons dynamically unlock based on number of tokens claimed
    $tokenCount = count($tokenIds);
    $allowed_weapons = $tier9_weapons;
    if ($tokenCount >= 2) {
        $allowed_weapons = array_merge($allowed_weapons, $tier10_weapons);
    }
    if ($tokenCount >= 3) {
        $allowed_weapons = array_merge($allowed_weapons, $tier11_weapons);
    }

    foreach ($weapons as $w) {
        if (!isset($allowed_weapons[$w])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid weapon selection for the selected token tier.']);
            exit;
        }
    }

    // Lookup user's discord_id
    $stmt = $db->prepare("SELECT discord_id FROM users WHERE account_id = :uid");
    $stmt->bindValue(':uid', $accountId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $userRow = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    $discordId = $userRow ? trim($userRow['discord_id'] ?? '') : null;

    if (empty($discordId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Please link your Discord account in Settings first.']);
        exit;
    }

    // Fetch and lock tokens
    $tokens = [];
    foreach ($tokenIds as $tid) {
        $tid = trim($tid);
        $stmt = $db->prepare("
            SELECT token_id, stat_native, stat_abeast, stat_machine, stat_dark, stat_hit
            FROM tekker_tokens
            WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :tokenId 
              AND trim(owner_id, char(13)||char(10)||' '||char(9)) = :discordId 
              AND is_claimed = 0
            LIMIT 1
        ");
        $stmt->bindValue(':tokenId', $tid, SQLITE3_TEXT);
        $stmt->bindValue(':discordId', $discordId, SQLITE3_TEXT);
        $res = $stmt->execute();
        $tokenRow = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;

        if (!$tokenRow) {
            http_response_code(404);
            echo json_encode(['error' => "Token '{$tid}' not found or already claimed."]);
            exit;
        }
        $tokens[] = $tokenRow;
    }

    // Aggregate stats from all selected tokens
    $combined_stats = [
        'stat_native' => 0,
        'stat_abeast' => 0,
        'stat_machine' => 0,
        'stat_dark' => 0,
        'stat_hit' => 0
    ];
    foreach ($tokens as $t) {
        $combined_stats['stat_native']  += (int)$t['stat_native'];
        $combined_stats['stat_abeast']  += (int)$t['stat_abeast'];
        $combined_stats['stat_machine'] += (int)$t['stat_machine'];
        $combined_stats['stat_dark']    += (int)$t['stat_dark'];
        $combined_stats['stat_hit']     += (int)$t['stat_hit'];
    }

    // Cap combined stats at 100% per attribute
    $combined_stats['stat_native']  = min(100, $combined_stats['stat_native']);
    $combined_stats['stat_abeast']  = min(100, $combined_stats['stat_abeast']);
    $combined_stats['stat_machine'] = min(100, $combined_stats['stat_machine']);
    $combined_stats['stat_dark']    = min(100, $combined_stats['stat_dark']);
    $combined_stats['stat_hit']     = min(100, $combined_stats['stat_hit']);

    // 1. Fetch online clients from newserv to verify status
    $url = $NEWSERV_API_URL . "/y/clients";
    $clients_raw = @file_get_contents($url);

    if ($clients_raw === FALSE) {
        http_response_code(500);
        echo json_encode(['error' => 'Game server is offline, cannot verify character status.']);
        exit;
    }

    $clients = json_decode($clients_raw, true);
    $onlineCharacter = null;

    if (is_array($clients)) {
        foreach ($clients as $c) {
            if (isset($c['Account']) && $c['Account']['AccountID'] == $accountId) {
                $onlineCharacter = $c;
                break;
            }
        }
    }

    if (!$onlineCharacter) {
        http_response_code(400);
        echo json_encode([
            'error' => 'You must be online in-game to claim rewards.',
            'offline' => true
        ]);
        exit;
    }

    // Protect against loading screen transitions
    if (!isset($onlineCharacter['EXP']) || ($onlineCharacter['EXP'] === 0 && ($onlineCharacter['Level'] ?? 1) > 1)) {
        http_response_code(400);
        echo json_encode(['error' => 'Your character is currently in a loading screen. Please wait until you are fully spawned.']);
        exit;
    }

    $lobbyId = $onlineCharacter['LobbyID'] ?? null;
    if ($lobbyId === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Character must be actively logged into a server lobby or game.']);
        exit;
    }

    // Fetch lobbies to ensure the player is in an actual game, not just the lobby
    $lobbies_raw = @file_get_contents($NEWSERV_API_URL . "/y/lobbies");
    if ($lobbies_raw === FALSE) {
        http_response_code(500);
        echo json_encode(['error' => 'Game server lobbies offline, cannot verify game status.']);
        exit;
    }

    $lobbies = json_decode($lobbies_raw, true);
    $inGame = false;

    if (is_array($lobbies)) {
        foreach ($lobbies as $l) {
            if (isset($l['ID']) && $l['ID'] === $lobbyId) {
                if (!empty($l['IsGame'])) {
                    $inGame = true;
                }
                break;
            }
        }
    }

    if (!$inGame) {
        http_response_code(400);
        echo json_encode(['error' => 'You must be actively inside a game (not in a lobby block) to receive the item drop.']);
        exit;
    }

    // Define buildHexPayload so functions.php:parse_and_drop_items can use it
    if (!function_exists('buildHexPayload')) {
        function buildHexPayload($itemStr)
        {
            $itemStr = trim($itemStr);
            if (empty($itemStr)) return $itemStr;

            $parts = explode(' ', $itemStr);
            $firstPart = array_shift($parts);

            if (ctype_xdigit($firstPart) && strlen($firstPart) >= 6) {
                $hex = str_pad(substr($firstPart, 0, 32), 32, "0");
                $data = hex2bin($hex);
                
                $is_weapon = ($data[0] === "\x00");

                if ($is_weapon) {
                    if (!empty($parts) && strpos($parts[0], '/') !== false) {
                        $stats = explode('/', $parts[0]);
                        $idx = 6;
                        // Native=1, A.Beast=2, Machine=3, Dark=4, Hit=5
                        for ($i = 0; $i < 5; $i++) {
                            if (isset($stats[$i]) && (int)$stats[$i] > 0 && $idx < 12) {
                                $data[$idx] = chr($i + 1);
                                $data[$idx+1] = chr((int)$stats[$i]);
                                $idx += 2;
                            }
                        }
                    }
                }
                
                return strtoupper(bin2hex($data));
            }
            
            return $itemStr;
        }
    }

    // Randomly pick one of the 3 weapons
    $chosenWeaponHex = $weapons[array_rand($weapons)];
    $chosenWeaponName = $allowed_weapons[$chosenWeaponHex];

    // Format item string
    $statStr = "{$combined_stats['stat_native']}/{$combined_stats['stat_abeast']}/{$combined_stats['stat_machine']}/{$combined_stats['stat_dark']}/{$combined_stats['stat_hit']}";
    $itemString = $chosenWeaponHex . "008000000000000000000000 " . $statStr;

    // Trigger drop
    $dropResult = parse_and_drop_items($accountId, $itemString);

    if (!$dropResult['success']) {
        http_response_code(500);
        echo json_encode(['error' => $dropResult['error'] ?? 'Server drop execution failed']);
        exit;
    }

    // Mark all selected tokens as claimed inside a transaction
    $db->exec("BEGIN TRANSACTION;");
    try {
        foreach ($tokenIds as $tid) {
            $upd = $db->prepare("
                UPDATE tekker_tokens 
                SET is_claimed = 1, claimed_by = :claimed_by, claimed_at = datetime('now') 
                WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :tokenId
            ");
            $upd->bindValue(':claimed_by', $accountId, SQLITE3_INTEGER);
            $upd->bindValue(':tokenId', trim($tid), SQLITE3_TEXT);
            $upd->execute();
        }
        $db->exec("COMMIT;");
    } catch (Exception $e) {
        $db->exec("ROLLBACK;");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to commit token claim: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'weapon_name' => $chosenWeaponName,
        'message' => "Your unidentified {$chosenWeaponName} has dropped at your feet!"
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
