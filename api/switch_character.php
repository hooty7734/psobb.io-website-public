<?php
/**
 * PSOBB API: Switch Character
 * 
 * Executed via NewServ shell command to instantly switch the player's active 
 * online character to another slot (1-4) without disconnecting.
 * Requires the player to be online and in a lobby block, not actively inside a game room.
 */
require_once __DIR__ . '/config.php';

if (ob_get_length()) ob_clean();

start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// 1. Verify user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in', 'success' => false]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];

// 2. Validate slot index input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['slot'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid slot index', 'success' => false]);
    exit;
}

$slot = clamp((int)$input['slot'], 0, 3);
$targetSlotNum = $slot + 1; // 1-based character slot for newserv command

function clamp($val, $min, $max) {
    return max($min, min($max, $val));
}

// 3. Check online state via newserv
$clientsUrl = $NEWSERV_API_URL . '/y/clients';
$clientsResponse = @file_get_contents($clientsUrl);

if ($clientsResponse === FALSE) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to the game server. Please try again later.', 'success' => false]);
    exit;
}

$clientsData = json_decode($clientsResponse, true);
if (!is_array($clientsData)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid server response', 'success' => false]);
    exit;
}

$isOnline = false;
foreach ($clientsData as $client) {
    if (isset($client['Account']['AccountID']) && (int)$client['Account']['AccountID'] === $accountId) {
        $isOnline = true;
        break;
    }
}

if (!$isOnline) {
    http_response_code(400);
    echo json_encode(['error' => 'You must be logged into a character in-game to switch characters instantly.', 'success' => false]);
    exit;
}

// 4. Double check if player is in a game room vs lobby block
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
    echo json_encode(['error' => 'You cannot switch characters while actively inside a game. Please return to the lobby first!', 'success' => false]);
    exit;
}

// 5. Helper to execute server commands
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

// 6. Execute the switch character command
$accountIdHex = dechex($accountId);
$switchCmd = 'on ' . $accountIdHex . ' cc $switchchar ' . $targetSlotNum;
$result = run_shell_command($switchCmd);

if ($result === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to trigger character switch on the game server.', 'success' => false]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => "Character swap command sent! Swapping instantly to Slot {$targetSlotNum}..."
]);
?>
