<?php
/**
 * PSOBB API: Redeem Bounty
 * 
 * Redeems an actively completed personal bounty mission for a user.
 * Validates that the character is online, in a game, and then converts 
 * the textual item string into a raw hex payload for the game server.
 */
require_once 'config.php';

if (ob_get_length()) ob_clean();

start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized. Please log in."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pm_id = intval($input['player_mission_id'] ?? 0);

if (!$pm_id) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid mission ID"]);
    exit;
}

$accId = $_SESSION['user']['account_id'];

function buildHexPayload($itemStr) {
    $itemStr = trim($itemStr);
    if (empty($itemStr)) return $itemStr;

    $parts = explode(' ', $itemStr);
    $firstPart = array_shift($parts);

    if (ctype_xdigit($firstPart) && strlen($firstPart) >= 6) {
        $hex = str_pad(substr($firstPart, 0, 32), 32, "0");
        $data = hex2bin($hex);
        
        $is_weapon = ($data[0] === "\x00");
        $is_armor_shield = ($data[0] === "\x01" && ($data[1] === "\x01" || $data[1] === "\x02"));
        $is_unit = ($data[0] === "\x01" && $data[1] === "\x03");

        if ($is_weapon) {
            if (!empty($parts) && strpos($parts[0], '/') !== false) {
                $stats = explode('/', $parts[0]);
                $idx = 6;
                for ($i = 0; $i < 4; $i++) {
                    if (isset($stats[$i]) && $stats[$i] > 0 && $idx < 12) {
                        $data[$idx] = chr($i + 1);
                        $data[$idx+1] = chr($stats[$i]);
                        $idx += 2;
                    }
                }
            }
        } else if ($is_armor_shield) {
            foreach ($parts as $token) {
                if (substr($token, 0, 1) === '+') {
                    if (strpos($token, 'def') !== false) {
                        $val = intval(str_replace('def', '', substr($token, 1)));
                        $data[6] = chr($val & 0xFF);
                        $data[7] = chr(($val >> 8) & 0xFF);
                    } else if (strpos($token, 'evp') !== false) {
                        $val = intval(str_replace('evp', '', substr($token, 1)));
                        $data[8] = chr($val & 0xFF);
                        $data[9] = chr(($val >> 8) & 0xFF);
                    } else {
                        $val = intval(substr($token, 1));
                        $data[5] = chr($val & 0xFF);
                    }
                }
            }
        } else if ($is_unit) {
            foreach ($parts as $token) {
                if (substr($token, 0, 1) === '+') {
                    preg_match('/\+([0-9]+)/', $token, $matches);
                    if (!empty($matches[1])) {
                        $val = intval($matches[1]);
                        $data[6] = chr($val & 0xFF);
                        $data[7] = chr(($val >> 8) & 0xFF);
                    }
                }
            }
        }
        
        return strtoupper(bin2hex($data));
    }
    
    return $itemStr;
}

try {
    require_once 'db.php';
    $db = get_db();

    // Verify ownership and status
    $stmt = $db->prepare("SELECT pm.id, pm.character_name, m.reward_item_string 
                          FROM player_missions pm
                          JOIN missions m ON pm.mission_id = m.id
                          WHERE pm.id = :pm_id AND pm.account_id = :accId AND pm.status = 'ready_to_redeem'");
    $stmt->bindValue(':pm_id', $pm_id, SQLITE3_INTEGER);
    $stmt->bindValue(':accId', $accId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $bounty = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;

    if (!$bounty) {
        http_response_code(404);
        echo json_encode(["error" => "Bounty not found or not ready to redeem"]);
        exit;
    }

    // 1. Fetch online clients from newserv to verify level
    $url = $NEWSERV_API_URL . "/y/clients";
    $data = @file_get_contents($url);

    if ($data === FALSE) {
        http_response_code(500);
        echo json_encode(["error" => "Game server is offline, cannot verify character."]);
        exit;
    }

    $clients = json_decode($data, true);
    $onlineCharacter = null;

    if (is_array($clients)) {
        foreach ($clients as $c) {
            if (isset($c['Account']) && $c['Account']['AccountID'] == $accId) {
                if (!empty($bounty['character_name']) && isset($c['Name']) && $c['Name'] !== $bounty['character_name']) {
                    continue; // Wrong character
                }
                $onlineCharacter = $c;
                break;
            }
        }
    }

    if (!$onlineCharacter) {
        http_response_code(400);
        $err_msg = "Character must be online to claim rewards.";
        if (!empty($bounty['character_name'])) {
            $err_msg = "You must be logged in as " . $bounty['character_name'] . " to claim this reward!";
        }
        echo json_encode(["error" => $err_msg]);
        exit;
    }

    // Protect against loading screen transitions
    if (!isset($onlineCharacter['EXP']) || ($onlineCharacter['EXP'] === 0 && ($onlineCharacter['Level'] ?? 1) > 1)) {
        http_response_code(400);
        echo json_encode(["error" => "Your character is currently in a loading screen. Please wait until you are fully spawned to claim."]);
        exit;
    }

    $lobbyId = $onlineCharacter['LobbyID'] ?? null;
    if ($lobbyId === null) {
        http_response_code(400);
        echo json_encode(["error" => "Character must be actively logged into a server lobby or game."]);
        exit;
    }

    // 1.5 Fetch lobbies to ensure the player is in an actual game, not just the lobby
    $lobbiesData = @file_get_contents($NEWSERV_API_URL . "/y/lobbies");
    if ($lobbiesData === FALSE) {
        http_response_code(500);
        echo json_encode(["error" => "Game server lobbies offline, cannot verify game status."]);
        exit;
    }

    $lobbies = json_decode($lobbiesData, true);
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
        echo json_encode(["error" => "You must be actively inside a game (not in a lobby) to claim a reward."]);
        exit;
    }

    // Try to drop the items at their feet!
    // We split compound AI strings like "Saber, Monomate and 300 Meseta" into isolated payloads
    $raw_string = trim(str_ireplace(' and ', ',', $bounty['reward_item_string']), ',');
    
    if (!function_exists('parse_and_drop_items')) {
        require_once 'functions.php';
    }
    
    $dropResult = parse_and_drop_items($accId, $raw_string, $bounty['character_name']);
    
    if (!$dropResult['success']) {
        http_response_code(400);
        echo json_encode(["error" => $dropResult['error']]);
        exit;
    }
    
    // Update status to 'completed'
    $upd = $db->prepare("UPDATE player_missions SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = :pm_id");
    $upd->bindValue(':pm_id', $pm_id, SQLITE3_INTEGER);
    $upd->execute();
    
    echo json_encode(["success" => true, "message" => "Reward dispensed in-game!"]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "System error: " . $e->getMessage()]);
}
?>
