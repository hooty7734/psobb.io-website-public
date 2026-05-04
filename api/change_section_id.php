<?php
/**
 * PSOBB API: Change Section ID
 * 
 * Allows a user to change the Section ID of one of their characters.
 * Deducts 50,000 Meseta from the character's bank via the NewServ API.
 * The character must be offline to perform this action.
 */
require_once 'config.php';

if (ob_get_length()) ob_clean();

start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// 1. Verify Login
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$character_name = $input['character_name'] ?? '';
$new_section_id = $input['new_section_id'] ?? '';

if (empty($character_name) || empty($new_section_id)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing character name or new Section ID"]);
    exit;
}

// Allowed Section IDs
$allowed_section_ids = [
    'Viridia', 'Greenill', 'Skyly', 'Bluefull', 'Purplenum',
    'Pinkal', 'Redria', 'Oran', 'Yellowboze', 'Whitill'
];

if (!in_array($new_section_id, $allowed_section_ids)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Section ID selected"]);
    exit;
}

$targetAccountId = $_SESSION['user']['account_id'];

// 2. Fetch Online Clients
$url = $NEWSERV_API_URL . "/y/clients";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Game server is currently offline"]);
    exit;
}

$clients = json_decode($data, true);
$foundClient = null;

if (is_array($clients)) {
    foreach ($clients as $client) {
        if (isset($client['Account']['AccountID']) && $client['Account']['AccountID'] == $targetAccountId) {
            if (isset($client['Name']) && $client['Name'] === $character_name) {
                $foundClient = $client;
                break;
            }
        }
    }
}

if (!$foundClient) {
    http_response_code(404);
    echo json_encode(["error" => "Character not found or not currently online. You must be logged in-game to change your Section ID."]);
    exit;
}

// Check if the user is in a lobby vs game
$lobbies_url = $NEWSERV_API_URL . "/y/lobbies";
$lobbies_data = @file_get_contents($lobbies_url);
if ($lobbies_data !== FALSE) {
    $lobbies = json_decode($lobbies_data, true);
    $in_game = false;
    
    if (is_array($lobbies)) {
        foreach ($lobbies as $lobby) {
            if (!empty($lobby['IsGame']) && $lobby['IsGame'] == true) {
                // If the player's ClientId is in this game's Clients array
                if (isset($lobby['Clients']) && is_array($lobby['Clients'])) {
                    foreach ($lobby['Clients'] as $lobbyClient) {
                        if ($lobbyClient !== null && isset($lobbyClient['Account']['AccountID']) && $lobbyClient['Account']['AccountID'] == $targetAccountId) {
                            $in_game = true;
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    if ($in_game) {
        http_response_code(403);
        echo json_encode(["error" => "You cannot change your Section ID while actively in a game. Please return to the lobby block to change it!"]);
        exit;
    }
}

// 3. Verify Level <= 50
$level = $foundClient['Level'] ?? 0;
if ($level > 50) {
    http_response_code(403);
    echo json_encode(["error" => "Only characters level 50 and below can change their Section ID."]);
    exit;
}

// 4. Send the Commands to the Server
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
    $result = @file_get_contents($url, false, $ctx);
    
    // Capture HTTP response headers for debugging
    $response_code = null;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $response_code = intval($matches[1]);
            }
        }
    }
    
    return ['body' => $result, 'http_code' => $response_code];
}

// Use account ID (hex) to identify the client — the `on` command's parser
// uses skip_non_whitespace which doesn't handle quoted strings, so names
// with spaces or quotes would break. Account ID is always unambiguous.
$account_id_hex = dechex($targetAccountId);

// Execute the change
$change_cmd = 'on ' . $account_id_hex . ' cc $edit secid ' . strtolower($new_section_id);
$res1 = run_shell_command($change_cmd);

if ($res1['body'] === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to game server shell API.']);
    exit;
}

// Force a save to cleanly write it to the .psochar
$save_cmd = 'on ' . $account_id_hex . ' cc $save';
run_shell_command($save_cmd);

echo json_encode(["success" => true, "message" => "Section ID changed successfully! Check your Guild Card or Lobby Info (\$li) in-game."]);
?>
