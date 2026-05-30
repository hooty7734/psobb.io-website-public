<?php
/**
 * PSOBB API: Recalibrate / Reset Stat Materials
 * 
 * Safely resets all consumed materials (HP, TP, Power, Mind, Evade, Def, Luck) 
 * back to 0. 
 * - If the player is actively online: uses NewServ's in-game command ($edit material reset every) 
 *   to gracefully swap and recompute stats in-game. Prevents resets while inside active game rooms.
 * - If the player is offline: performs secure direct binary editing on their .psochar file, 
 *   recalculates display stats, and writes it back cleanly to prevent file corruption.
 */
require_once 'config.php';
require_once 'db.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// 1. Verify Authentication
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];

// Retrieve BB username from Session user data
$username = '';
if (isset($_SESSION['user']['BBLicenses']) && is_array($_SESSION['user']['BBLicenses']) && count($_SESSION['user']['BBLicenses']) > 0) {
    $username = $_SESSION['user']['BBLicenses'][0]['UserName'] ?? '';
}
if (empty($username)) {
    $username = $_SESSION['user']['LastPlayerName'] ?? $_SESSION['user']['username'] ?? '';
}
$username = strtolower(trim($username));

// Get character slot (0 to 3)
$input = json_decode(file_get_contents('php://input'), true);
$slot = isset($input['slot']) ? max(0, min(3, (int)$input['slot'])) : 0;

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing account username context."]);
    exit;
}

// Check online state via NewServ
$clientsUrl = $NEWSERV_API_URL . '/y/clients';
$clientsResponse = @file_get_contents($clientsUrl);
$isOnline = false;
$onlineCharName = '';

if ($clientsResponse !== FALSE) {
    $clientsData = json_decode($clientsResponse, true);
    if (is_array($clientsData)) {
        foreach ($clientsData as $c) {
            if (isset($c['Account']['AccountID']) && (int)$c['Account']['AccountID'] === $accountId) {
                $isOnline = true;
                $onlineCharName = $c['Name'] ?? '';
                break;
            }
        }
    }
}

// Path to players folder
$playersDir = '/opt/newserv/system/players/';
if (!is_dir($playersDir)) {
    $playersDir = __DIR__ . '/../../newserv/system/players/';
}

// Helper to resolve player files case-insensitively
function resolve_player_file($dir, $filename) {
    $fullPath = $dir . $filename;
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $f) {
            if (strcasecmp($f, $filename) === 0) {
                return $dir . $f;
            }
        }
    }
    return $fullPath;
}

$psocharPath = resolve_player_file($playersDir, "player_{$username}_{$slot}.psochar");

if (!file_exists($psocharPath)) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Character file for Slot " . ($slot + 1) . " does not exist."]);
    exit;
}

$charData = @file_get_contents($psocharPath);
if ($charData === false || strlen($charData) < 0x399C) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to read character file binary data safely."]);
    exit;
}

// Unpack character name from UTF-16LE starts at offset 852 + 116 = 968 (size 32)
$nameBytes = substr($charData, 852 + 116, 32);
$slotCharName = mb_convert_encoding($nameBytes, 'UTF-8', 'UTF-16LE');
$slotCharName = trim(str_replace("\x00", "", $slotCharName));

// Helper to POST shell commands to NewServ
function run_shell_command($cmd) {
    global $NEWSERV_API_URL;
    $url = $NEWSERV_API_URL . "/y/shell-exec";
    $body = json_encode(['command' => $cmd]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $body,
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    $ctx = stream_context_create($opts);
    return @file_get_contents($url, false, $ctx);
}

// -------------------------------------------------------------------------
// Case A: Character is Online
// -------------------------------------------------------------------------
if ($isOnline) {
    // 1. Double check if the online character matches the selected slot's character name
    if (strcasecmp($onlineCharName, $slotCharName) !== 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false, 
            "error" => "You are currently online in-game as character '{$onlineCharName}'. You cannot reset materials for slot " . ($slot + 1) . " ('{$slotCharName}') while playing on a different character. Please log out first!"
        ]);
        exit;
    }

    // 2. Double check if player is in an active game room vs lobby block
    $lobbiesUrl = $NEWSERV_API_URL . '/y/lobbies';
    $lobbiesResponse = @file_get_contents($lobbiesUrl);
    $inGame = false;
    
    if ($lobbiesResponse !== FALSE) {
        $lobbiesData = json_decode($lobbiesResponse, true);
        if (is_array($lobbiesData)) {
            foreach ($lobbiesData as $lobby) {
                if (!empty($lobby['IsGame']) && $lobby['IsGame'] == true) {
                    if (isset($lobby['Clients']) && is_array($lobby['Clients'])) {
                        foreach ($lobby['Clients'] as $lobbyClient) {
                            if ($lobbyClient !== null && isset($lobbyClient['Account']['AccountID']) && (int)$lobbyClient['Account']['AccountID'] === $accountId) {
                                $inGame = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }
    
    if ($inGame) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "You cannot reset materials while actively in a game. Please return to the lobby block or log out first!"]);
        exit;
    }
    
    // 2. Perform safe reset in the lobby block using NewServ commands
    $accountIdHex = dechex($accountId);
    $resetCmd = 'on ' . $accountIdHex . ' cc $edit material reset every';
    $saveCmd = 'on ' . $accountIdHex . ' cc $save';
    
    $res1 = run_shell_command($resetCmd);
    $res2 = run_shell_command($saveCmd);
    
    if ($res1 === false || $res2 === false) {
        http_response_code(502);
        echo json_encode(["success" => false, "error" => "Failed to trigger live material reset on the game server."]);
        exit;
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Stat materials successfully reset to 0 in-game!"
    ]);
    exit;
}

// -------------------------------------------------------------------------
// Case B: Character is Offline (Direct Binary Modification)
// -------------------------------------------------------------------------
try {
    // $charData is already loaded and validated at the top
    
    // 1. Extract consumed materials count to recalculate stats correctly
    // HP / TP materials (Starting at inventory offset 8)
    $hpMats = ord($charData[8 + 1]) >> 1;
    $tpMats = ord($charData[8 + 2]) >> 1;
    
    // Power, Mind, Evade, Def, Luck mats are stored in inventory slot items 8, 9, 10, 11, 12 extension byte 2
    $powerMats = ord($charData[8 + 4 + 8 * 28 + 3]);
    $mindMats  = ord($charData[8 + 4 + 9 * 28 + 3]);
    $evadeMats = ord($charData[8 + 4 + 10 * 28 + 3]);
    $defMats   = ord($charData[8 + 4 + 11 * 28 + 3]);
    $luckMats  = ord($charData[8 + 4 + 12 * 28 + 3]);
    
    // 2. Unpack display stats block (starts at offset 852)
    $dispBlock = substr($charData, 852, 400);
    $atp = unpack('v', substr($dispBlock, 0, 2))[1];
    $mst = unpack('v', substr($dispBlock, 2, 2))[1];
    $evp = unpack('v', substr($dispBlock, 4, 2))[1];
    $dfp = unpack('v', substr($dispBlock, 8, 2))[1];
    $lck = unpack('v', substr($dispBlock, 12, 2))[1];
    
    // 3. Subtract mats * 2 from each stat (recalculates stats)
    $newAtp = max(0, $atp - ($powerMats * 2));
    $newMst = max(0, $mst - ($mindMats * 2));
    $newEvp = max(0, $evp - ($evadeMats * 2));
    $newDfp = max(0, $dfp - ($defMats * 2));
    $newLck = max(0, $lck - ($luckMats * 2));
    
    // 4. Overwrite display stats inside $charData
    $charData[852 + 0] = chr($newAtp & 0xFF);
    $charData[852 + 1] = chr(($newAtp >> 8) & 0xFF);
    
    $charData[852 + 2] = chr($newMst & 0xFF);
    $charData[852 + 3] = chr(($newMst >> 8) & 0xFF);
    
    $charData[852 + 4] = chr($newEvp & 0xFF);
    $charData[852 + 5] = chr(($newEvp >> 8) & 0xFF);
    
    $charData[852 + 8] = chr($newDfp & 0xFF);
    $charData[852 + 9] = chr(($newDfp >> 8) & 0xFF);
    
    $charData[852 + 12] = chr($newLck & 0xFF);
    $charData[852 + 13] = chr(($newLck >> 8) & 0xFF);
    
    // 5. Overwrite materials counts to 0
    $charData[8 + 1] = chr(0); // HP materials
    $charData[8 + 2] = chr(0); // TP materials
    
    $charData[8 + 4 + 8 * 28 + 3]  = chr(0); // Power
    $charData[8 + 4 + 9 * 28 + 3]  = chr(0); // Mind
    $charData[8 + 4 + 10 * 28 + 3] = chr(0); // Evade
    $charData[8 + 4 + 11 * 28 + 3] = chr(0); // Def
    $charData[8 + 4 + 12 * 28 + 3] = chr(0); // Luck
    
    // 6. Save back to disk securely
    $saved = @file_put_contents($psocharPath, $charData);
    
    if ($saved === false) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Failed to write modified binary data back to Slot " . ($slot + 1) . "."]);
        exit;
    }
    
    echo json_encode([
        "success" => true,
        "message" => "Stat materials successfully reset and stats re-calibrated offline!"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "An error occurred while recalibrating stats: " . $e->getMessage()]);
}
?>
