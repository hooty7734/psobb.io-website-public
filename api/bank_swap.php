<?php
/**
 * PSOBB API: Bank Swap
 * 
 * Interacts with the NewServ database via shell-exec to swap a character's
 * primary bank with an alternative bank tab (0-4).
 * The character must be online to perform this action.
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

// 2. Validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['character_name']) || !isset($input['target_bank_index'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data', 'success' => false]);
    exit;
}

$characterName = trim($input['character_name']);
$targetBankIndex = (int)$input['target_bank_index'];

// Bank Index Validation: -1 is Shared Bank, 0-19 are character banks.
if ($targetBankIndex < -1 || $targetBankIndex > 19) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid bank index selected', 'success' => false]);
    exit;
}

// 3. Fetch online clients from newserv
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

$foundClient = null;
foreach ($clientsData as $client) {
    if (isset($client['Account']['AccountID']) && (int)$client['Account']['AccountID'] === $accountId) {
        $foundClient = $client;
        break;
    }
}

if (!$foundClient) {
    http_response_code(403);
    echo json_encode(['error' => 'The selected character is not currently online or does not belong to you', 'success' => false]);
    exit;
}

// 4. Build the $bank command
// newserv: $bank 0 or negative = shared bank, $bank 1-127 = character bank at index n-1
if ($targetBankIndex === -1) {
    $cmdIndex = '0';
} else {
    $cmdIndex = (string)($targetBankIndex + 1);
}

// Use account ID hex to identify client (quoted names break newserv's parser)
$accountIdHex = dechex($accountId);

// 5. Helper to POST shell commands to newserv
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

// 6. Execute the bank swap
$bankCmd = 'on ' . $accountIdHex . ' cc $bank ' . $cmdIndex;
$bankResult = run_shell_command($bankCmd);

if ($bankResult === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute bank swap on the server.', 'success' => false]);
    exit;
}

// 7. Save the character file
$saveCmd = 'on ' . $accountIdHex . ' cc $save';
run_shell_command($saveCmd);

echo json_encode([
    'success' => true,
    'message' => 'Bank successfully swapped! Please check your bank in-game.'
]);
?>
