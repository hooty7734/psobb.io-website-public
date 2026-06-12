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
        '000207' => 'DRAGON SLAYER',
        '008900' => 'MUSASHI',
        '000206' => 'LAST SURVIVOR',
        '000407' => 'GAE BOLG',
        '000307' => 'CROSS SCAR',
        '000405' => 'BRIONAC',
        '000305' => 'BLADE DANCE',
        '000306' => 'BLOODY ART',
        '00CD00' => 'TANEGASHIMA',
        '00D200' => 'ANO BAZOOKA',
        '000707' => 'JUSTY-23ST',
        '000706' => 'WALS-MK2',
        '000705' => 'VISK-235W',
        '008B00' => 'PHOTON LAUNCHER',
        '000907' => 'FINAL IMPACT',
        '000906' => 'METEOR SMASH',
        '000905' => 'CRUSH BULLET',
        '000B06' => 'ALIVE AQHU',
        '005500' => 'RABBIT WAND',
        '000B05' => 'BRAVE HAMMER',
        '000B04' => 'BATTLE VERGE',
        '008C00' => 'TALIS',
        '000A06' => 'CLUB OF ZUMIURAN',
        '000A05' => 'MACE OF ADAMAN',
        '000A04' => 'CLUB OF LACONIUM',
        '000C06' => 'STORM WAND: INDRA',
        '000C05' => 'ICE STAFF: DAGON',
        '000F00' => 'BRAVE KNUCKLE',
        '000107' => 'DURANDAL',
        '000106' => 'KALADBOLG',
        '000D00' => 'PHOTON CLAW',
        '00CB00' => "TYRELL'S PARASOL",
        '000607' => 'BRAVACE',
        '000605' => 'VARISTA',
        '000606' => 'CUSTOM RAY VER.OO',
        '000E00' => 'DOUBLE SABER',
        '000506' => 'DISKA OF LIBERATOR'
    ];
    $tier10_weapons = [
        '00B700' => 'SHOUREN',
        '002001' => 'LACONIUM AXE',
        '006900' => 'HEART OF POUMN',
        '008A02' => 'KAMUI',
        '003400' => 'RED SWORD',
        '00070B' => 'RIANOV 303SNR-3',
        '004E00' => 'PANZER FAUST',
        '00070C' => 'RIANOV 303SNR-4',
        '001500' => 'FLAME VISIT',
        '006B00' => 'YASMINKOV 7000V',
        '000C07' => 'EARTH WAND BROWNIE',
        '00C400' => 'SIREN GLASS HAMMER',
        '002200' => 'CADUCEUS',
        '00C200' => 'SOLFERINO',
        '009200' => 'GUARDIANNA',
        '00B500' => 'SACRED DUSTER',
        '000F02' => 'GOD HAND',
        '009800' => "RIKA'S CLAW",
        '002900' => 'YAMIGARASU',
        '00B400' => 'KUSANAGI',
        '002700' => 'ANCIENT SABER',
        '001101' => 'SOUL BANISH',
        '000B07' => 'VALKYRIE',
        '000D03' => 'PHOENIX CLAW',
        '000F01' => 'ANGRY FIST',
        '00C600' => 'SHICHISHITO',
        '009400' => 'MORNING GLORY'
    ];
    $tier11_weapons = [
        '001001' => 'AGITO',
        '008D00' => 'NUG2000-BAZOOKA',
        '00C900' => 'DECALOG',
        '005A00' => 'PROPHETS OF MOTAV',
        '003A00' => "MADAM'S PARASOL"
    ];

    // Allowed weapons dynamically unlock based on number of tokens claimed
    $tokenCount = count($tokenIds);

    // Validate each weapon's tier and duplicate constraints
    $weapon_counts = array_count_values($weapons);
    foreach ($weapon_counts as $w => $c) {
        $tier = null;
        if (isset($tier9_weapons[$w])) {
            $tier = 9;
        } elseif (isset($tier10_weapons[$w])) {
            $tier = 10;
        } elseif (isset($tier11_weapons[$w])) {
            $tier = 11;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid weapon choice.']);
            exit;
        }

        // Ensure selected tier is unlocked for the token count
        if ($tier > 8 + $tokenCount) {
            http_response_code(400);
            echo json_encode(['error' => "Weapon tier {$tier} is not unlocked by the number of selected tokens."]);
            exit;
        }

        // Enforce duplicate constraints based on token count and tier
        $maxAllowed = $tokenCount - ($tier - 9);
        if ($c > $maxAllowed) {
            http_response_code(400);
            echo json_encode(['error' => 'Weapon duplication count exceeds the allowance for the selected tier and tokens.']);
            exit;
        }
    }

    // Build list of allowed weapons and set chosen weapon name map
    $allowed_weapons = $tier9_weapons;
    if ($tokenCount >= 2) {
        $allowed_weapons = array_merge($allowed_weapons, $tier10_weapons);
    }
    if ($tokenCount >= 3) {
        $allowed_weapons = array_merge($allowed_weapons, $tier11_weapons);
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

    // Count how many attributes are active (>0) before any filtering
    $active_stats = [];
    foreach ($combined_stats as $k => $val) {
        if ($val > 0) {
            $active_stats[] = $k;
        }
    }

    // If combined stats span >3 columns, player must select exactly 3 to keep
    if (count($active_stats) > 3) {
        $keep = $input['keep_attributes'] ?? [];
        if (!is_array($keep) || count($keep) !== 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Combined stats span more than 3 attributes. You must select exactly 3 to keep.']);
            exit;
        }

        $valid_keys = ['stat_native', 'stat_abeast', 'stat_machine', 'stat_dark', 'stat_hit'];
        foreach ($keep as $k) {
            if (!in_array($k, $valid_keys)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid attribute key selected to keep.']);
                exit;
            }
        }

        // Zero out any attributes not explicitly selected to keep
        foreach ($combined_stats as $k => $val) {
            if (!in_array($k, $keep)) {
                $combined_stats[$k] = 0;
            }
        }
    }

    // Clamp combined stats at 90% per attribute
    $combined_stats['stat_native']  = min(90, $combined_stats['stat_native']);
    $combined_stats['stat_abeast']  = min(90, $combined_stats['stat_abeast']);
    $combined_stats['stat_machine'] = min(90, $combined_stats['stat_machine']);
    $combined_stats['stat_dark']    = min(90, $combined_stats['stat_dark']);
    $combined_stats['stat_hit']     = min(90, $combined_stats['stat_hit']);

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

    // Consolidated claim log: one row per claim event recording the player, every
    // token spent, and the item produced (name + the combined stats it was rolled
    // with). The per-token is_claimed flags below remain the source of truth for
    // token state; this is the human-readable audit log of who claimed what for what.
    $db->exec("
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
        )
    ");

    $trimmedTokenIds = array_map('trim', $tokenIds);

    // Record the claim AND remove the spent tokens inside one transaction: the log
    // row (who claimed what, for which item) is written FIRST, then the source
    // tokens are deleted. Because both happen atomically, tokens are only ever
    // removed once their permanent claim-log record exists — never before.
    $db->exec("BEGIN TRANSACTION;");
    try {
        $logStmt = $db->prepare("
            INSERT INTO tekker_claim_log
                (account_id, discord_id, token_ids, token_count, weapon_hex, weapon_name,
                 stat_native, stat_abeast, stat_machine, stat_dark, stat_hit)
            VALUES
                (:account_id, :discord_id, :token_ids, :token_count, :weapon_hex, :weapon_name,
                 :sn, :sa, :sm, :sd, :sh)
        ");
        $logStmt->bindValue(':account_id', $accountId, SQLITE3_INTEGER);
        $logStmt->bindValue(':discord_id', $discordId, SQLITE3_TEXT);
        $logStmt->bindValue(':token_ids', json_encode($trimmedTokenIds), SQLITE3_TEXT);
        $logStmt->bindValue(':token_count', count($trimmedTokenIds), SQLITE3_INTEGER);
        $logStmt->bindValue(':weapon_hex', $chosenWeaponHex, SQLITE3_TEXT);
        $logStmt->bindValue(':weapon_name', $chosenWeaponName, SQLITE3_TEXT);
        $logStmt->bindValue(':sn', $combined_stats['stat_native'], SQLITE3_INTEGER);
        $logStmt->bindValue(':sa', $combined_stats['stat_abeast'], SQLITE3_INTEGER);
        $logStmt->bindValue(':sm', $combined_stats['stat_machine'], SQLITE3_INTEGER);
        $logStmt->bindValue(':sd', $combined_stats['stat_dark'], SQLITE3_INTEGER);
        $logStmt->bindValue(':sh', $combined_stats['stat_hit'], SQLITE3_INTEGER);
        $logStmt->execute();

        // Claim is now permanently recorded — delete the spent tokens from the live
        // store. The claim log is the record of these tokens from here on.
        foreach ($trimmedTokenIds as $tid) {
            $del = $db->prepare("
                DELETE FROM tekker_tokens
                WHERE trim(token_id, char(13)||char(10)||' '||char(9)) = :tokenId
            ");
            $del->bindValue(':tokenId', $tid, SQLITE3_TEXT);
            $del->execute();
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
