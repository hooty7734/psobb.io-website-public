<?php
require_once 'config.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$accountId = $_SESSION['user']['account_id'];

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
    echo json_encode(["error" => "You must be in a game to use the MAG feeder."]);
    exit;
}

$level = $onlineCharacter['Level'] ?? 1;
$name = $onlineCharacter['Name'] ?? 'Unknown';
$charClass = $onlineCharacter['CharClass'] ?? 'HUmar';
$clientId = $onlineCharacter['LobbyClientID'] ?? 0;

// Parse inventory items
$inventoryItems = $onlineCharacter['InventoryItems'] ?? [];
$mags = [];
$feedItems = [];

// Feedable item types (data1[0]=0x03 = tool/consumable)
// These are the item names that can be fed to a MAG
$feedableNames = [
    'Monomate', 'Dimate', 'Trimate',
    'Monofluid', 'Difluid', 'Trifluid',
    'Sol Atomizer', 'Moon Atomizer', 'Star Atomizer',
    'Antidote', 'Antiparalysis',
];

foreach ($inventoryItems as $item) {
    $desc = $item['Description'] ?? '';
    $data = $item['Data'] ?? '';
    $itemId = $item['ItemID'] ?? 0;
    $flags = $item['Flags'] ?? 0;

    // Decode hex data - first byte determines item type
    // 0x02 = MAG
    $data = preg_replace('/[^a-fA-F0-9]/', '', $data);
    $dataBytes = hex2bin($data);
    if ($dataBytes === false || strlen($dataBytes) < 12) continue;

    $type = ord($dataBytes[0]);

    if ($type === 0x02) {
        // MAG item: parse stats from item data
        // PSO BB MAG data format (data1 = 12 bytes, data2 = 4 bytes):
        // data1[0] = 0x02 (item type: MAG)
        // data1[1] = MAG type index
        // data1[2] = level
        // data1[3] = photon blast flags
        // data1[4-5] = DEF (uint16 LE, value * 100)
        // data1[6-7] = POW (uint16 LE, value * 100)
        // data1[8-9] = DEX (uint16 LE, value * 100)
        // data1[10-11] = MIND (uint16 LE, value * 100)
        // data2[0] = synchro
        // data2[1] = IQ
        // data2[2] = PB flags / color

        $magType = ord($dataBytes[1]);
        $magLevel = ord($dataBytes[2]);
        $pbFlags = ord($dataBytes[3]);

        // Stats are uint16 LE, representing level * 100
        $defRaw = unpack('v', substr($dataBytes, 4, 2))[1];
        $powRaw = unpack('v', substr($dataBytes, 6, 2))[1];
        $dexRaw = unpack('v', substr($dataBytes, 8, 2))[1];
        $mindRaw = unpack('v', substr($dataBytes, 10, 2))[1];

        // data2 bytes (start at byte 16 after 12-byte data1 and 4-byte ItemID)
        $synchro = strlen($dataBytes) > 16 ? ord($dataBytes[16]) : 0;
        $iq = strlen($dataBytes) > 17 ? ord($dataBytes[17]) : 0;

        $mags[] = [
            'item_id' => $itemId,
            'description' => $desc,
            'level' => $magLevel,
            'def' => $defRaw,
            'pow' => $powRaw,
            'dex' => $dexRaw,
            'mind' => $mindRaw,
            'synchro' => $synchro,
            'iq' => $iq,
            'pb_flags' => $pbFlags,
            'equipped' => ($flags & 0x08) !== 0,
        ];
    } elseif ($type === 0x03) {
        // Tool/consumable - check if it's feedable
        // Parse the description to match feedable names
        $isFeedable = false;
        $feedName = $desc;
        foreach ($feedableNames as $fn) {
            if (stripos($desc, $fn) !== false) {
                $isFeedable = true;
                $feedName = $fn;
                break;
            }
        }

        if ($isFeedable) {
            // For stacked items, data1[5] holds stack count
            $stackCount = ord($dataBytes[5]);
            if ($stackCount === 0) $stackCount = 1;

            $feedItems[] = [
                'item_id' => $itemId,
                'description' => $desc,
                'name' => $feedName,
                'count' => $stackCount,
            ];
        }
    }
}

echo json_encode([
    "character" => [
        "name" => $name,
        "level" => $level,
        "class" => $charClass,
        "client_id" => $clientId,
    ],
    "mags" => $mags,
    "feed_items" => $feedItems,
    "account_id" => $accountId,
]);
?>
