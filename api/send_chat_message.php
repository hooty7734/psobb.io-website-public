<?php
/**
 * PSOBB API: Web-to-Game Chat Message
 * 
 * Secure endpoint that sends chat messages typed from the dashboard Web Portal
 * straight to the player's active in-game lobby/room, operating as a vital QoL
 * keyboard bypass for Steam Deck and mobile sessions.
 */
require_once 'config.php';
require_once 'db.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// 1. Verify User Login
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];

// 2. Validate Inputs
$input = json_decode(file_get_contents('php://input'), true);
$characterName = trim($input['character_name'] ?? '');
$messageText = trim($input['message'] ?? '');

if (empty($characterName) || empty($messageText)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Character name and message text are required."]);
    exit;
}

// Enforce message length restriction for in-game chat buffer
if (mb_strlen($messageText) > 64) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Message exceeds the in-game limit of 64 characters."]);
    exit;
}

// 3. Verify character is online via NewServ
$clientsUrl = $NEWSERV_API_URL . '/y/clients';
$clientsResponse = @file_get_contents($clientsUrl);

if ($clientsResponse === FALSE) {
    http_response_code(502);
    echo json_encode(["success" => false, "error" => "Failed to reach the game server to verify player status."]);
    exit;
}

$clientsData = json_decode($clientsResponse, true);
if (!is_array($clientsData)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Invalid response from the game server."]);
    exit;
}

$foundClient = null;
foreach ($clientsData as $client) {
    if (isset($client['Account']['AccountID']) && (int)$client['Account']['AccountID'] === $accountId) {
        if (isset($client['Name']) && $client['Name'] === $characterName) {
            $foundClient = $client;
            break;
        }
    }
}

if (!$foundClient) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "The character " . htmlspecialchars($characterName) . " is not currently online. Please log in-game first!"]);
    exit;
}

// 4. Send command to NewServ shell-exec
// newserv: on <hex_account_id> c <message>
$accountIdHex = dechex($accountId);
$chatCmd = 'on ' . $accountIdHex . ' c ' . $messageText;

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

$execRes = run_shell_command($chatCmd);

if ($execRes === false) {
    http_response_code(502);
    echo json_encode(["success" => false, "error" => "Failed to transmit message through the game server API."]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Message broadcasted inside your game lobby successfully!"
]);
?>
