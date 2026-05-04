<?php
/**
 * PSOBB API: Mag Feeder
 * 
 * A web-based Minigame interface that allows players to feed their Mags
 * while offline. Reads Mag inventory data, consumes selected items, and
 * mathematically updates the Mag's stats based on standard PSO feeding charts.
 */
require_once 'config.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$accountId = $_SESSION['user']['account_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$magItemId = intval($input['mag_item_id'] ?? 0);
$feedItemId = intval($input['feed_item_id'] ?? 0);

if ($magItemId <= 0 || $feedItemId <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid item IDs."]);
    exit;
}

// Fetch online clients from newserv
$url = $NEWSERV_API_URL . "/y/clients";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Game server is offline."]);
    exit;
}

$clients = json_decode($data, true);
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
    echo json_encode(["error" => "Character must be online."]);
    exit;
}

// Check if in game
$lobbyId = $onlineCharacter['LobbyID'] ?? null;
$inGame = false;

if ($lobbyId !== null) {
    $lobbiesData = @file_get_contents($NEWSERV_API_URL . "/y/lobbies");
    if ($lobbiesData !== FALSE) {
        $lobbies = json_decode($lobbiesData, true);
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
    }
}

if (!$inGame) {
    http_response_code(400);
    echo json_encode(["error" => "You must be in a game to feed your MAG."]);
    exit;
}

$clientLobbyId = $onlineCharacter['LobbyClientID'] ?? 0;

// Validate the MAG and feed item exist in inventory
$inventoryItems = $onlineCharacter['InventoryItems'] ?? [];
$foundMag = false;
$foundFeed = false;

foreach ($inventoryItems as $item) {
    $itemId = $item['ItemID'] ?? 0;
    $dataHex = $item['Data'] ?? '';
    $dataHex = preg_replace('/[^a-fA-F0-9]/', '', $dataHex);
    $dataBytes = hex2bin($dataHex);
    if ($dataBytes === false || strlen($dataBytes) < 1) continue;

    $type = ord($dataBytes[0]);

    if ($itemId === $magItemId && $type === 0x02) {
        $foundMag = true;
    }
    if ($itemId === $feedItemId && $type === 0x03) {
        $foundFeed = true;
    }
}

if (!$foundMag) {
    http_response_code(400);
    echo json_encode(["error" => "MAG not found in your inventory."]);
    exit;
}

if (!$foundFeed) {
    http_response_code(400);
    echo json_encode(["error" => "Feed item not found in your inventory."]);
    exit;
}

// Build the 6x28 FeedMag subcommand packet
// Format: 60 03 00 00 28 03 CC CC MM MM MM MM FF FF FF FF
//
// Outer command: 0x60 (game subcommand), size=0x03 (in dwords after header = 12 bytes payload)
// Inner G_FeedMag_6x28:
//   subcommand = 0x28
//   size = 0x03 (in 4-byte units = 12 bytes)
//   client_id = uint16 LE
//   mag_item_id = uint32 LE
//   fed_item_id = uint32 LE

$packetData = pack(
    'vvVCCvVV',
    0x0014,                 // size (20 bytes total = 8 header + 12 payload)
    0x0060,                 // command (game subcommand broadcast)
    0x00000000,             // flag
    0x28,                   // subcommand: FeedMag
    0x03,                   // sub size (3 dwords = 12 bytes)
    $clientLobbyId,         // client_id (uint16 LE)
    $magItemId,             // mag_item_id (uint32 LE)
    $feedItemId             // fed_item_id (uint32 LE)
);

// Convert to hex string
$hexString = strtoupper(bin2hex($packetData));

$shellUrl = $NEWSERV_API_URL . "/y/shell-exec";

// Send via "ss" to process the feed server-side. This correctly handles:
// - MAG stat updates (DEF/POW/DEX/MIND)
// - MAG evolution and photon blast learning
// - Fed item removal from inventory
// Note: The BB client doesn't receive real-time visual updates from server-side
// feeds. The player must change rooms or reload to see the updated MAG in-game.
$cmd = "on " . $accountId . " ss " . $hexString;

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
$result = @file_get_contents($shellUrl, false, $ctx);

if ($result === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to send feed command to game server."]);
    exit;
}

$resultData = json_decode($result, true);
if (isset($resultData['error'])) {
    http_response_code(500);
    echo json_encode(["error" => "Server error: " . $resultData['error']]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "MAG fed successfully! Change rooms in-game to see updated stats.",
]);
?>
