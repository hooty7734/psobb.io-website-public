<?php
/**
 * PSOBB API: Claim Login Streak
 * 
 * Processes daily login streak rewards for authenticated users.
 * Verifies that the character is currently online and fully loaded,
 * then dispatches the hex payloads to the NewServ shell-exec API.
 */
require_once 'config.php';
require_once 'db.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// 1. Session Enforcement
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$accountId = $_SESSION['user']['account_id'];

// Parse incoming requested milestone mapping
$input = json_decode(file_get_contents('php://input'), true);
$milestone = intval($input['milestone'] ?? 0);

// Validate milestone falls within standard 30-day epoch cycle
$validMilestones = range(1, 30);
if (!in_array($milestone, $validMilestones)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid streak milestone."]);
    exit;
}

// --------------------------------------------------------------------------
// 2. Game Client Validation
// --------------------------------------------------------------------------
// We must pull live client state. Dropping an item while a player is offline
// or sitting on the ship lobby will result in the item vanishing into the void.
$url = $NEWSERV_API_URL . "/y/clients";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Game server is offline."]);
    exit;
}

$clients = json_decode($data, true);
$onlineClient = null;

if (is_array($clients)) {
    foreach ($clients as $c) {
        if (isset($c['Account']) && $c['Account']['AccountID'] == $accountId) {
            $onlineClient = $c;
            break;
        }
    }
}

if (!$onlineClient) {
    http_response_code(400);
    echo json_encode(["error" => "You must be logged into the game to claim streak rewards."]);
    exit;
}

// Protect against loading screen transitions
if (!isset($onlineClient['EXP']) || ($onlineClient['EXP'] === 0 && ($onlineClient['Level'] ?? 1) > 1)) {
    http_response_code(400);
    echo json_encode(["error" => "Your character is currently in a loading screen. Please wait until you are fully spawned to claim."]);
    exit;
}

// 3. Lobby vs. In-Game Verification
// Cross-reference the client's `LobbyID` with the Live Lobbies API to ensure `IsGame` is true.
$lobbyId = $onlineClient['LobbyID'] ?? null;
$inGame = false;
if ($lobbyId) {
    $lobbyUrl = $NEWSERV_API_URL . "/y/lobbies";
    $lobbyData = @file_get_contents($lobbyUrl);
    if ($lobbyData !== FALSE) {
        $lobbies = json_decode($lobbyData, true);
        if (is_array($lobbies)) {
            foreach ($lobbies as $lobby) {
                if (isset($lobby['ID']) && $lobby['ID'] == $lobbyId && isset($lobby['IsGame']) && $lobby['IsGame']) {
                    $inGame = true;
                    break;
                }
            }
        }
    }
}

// Reject if they are sitting at the Pioneer 2 front desk without a game spawned
if (!$inGame) {
    http_response_code(400);
    echo json_encode(["error" => "You must be in a game (not lobby) to claim streak rewards."]);
    exit;
}

// --------------------------------------------------------------------------
// 4. Server-Side Streak Verification
// --------------------------------------------------------------------------
// Never trust the client payload. We recompute the streak dynamically.
$db = get_db();
$today = date('Y-m-d');
$streak = 0;
$checkDate = new DateTime($today);

while (true) {
    $dateStr = $checkDate->format('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM daily_logins WHERE account_id = :aid AND login_date = :date");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':date', $dateStr, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray();
    
    if ($result['cnt'] > 0) {
        $streak++;
        $checkDate->modify('-1 day');
    } else {
        break;
    }
}

if ($streak < $milestone) {
    http_response_code(400);
    echo json_encode(["error" => "Your streak is only {$streak} days. You need {$milestone} days."]);
    exit;
}

// --------------------------------------------------------------------------
// 5. Reward Claim Logic & Replay Prevention
// --------------------------------------------------------------------------
$stmt = $db->prepare("SELECT COALESCE(MAX(streak_cycle), 1) as cycle FROM streak_claims WHERE account_id = :aid");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();
$currentCycle = $result['cycle'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM streak_claims WHERE account_id = :aid AND streak_cycle = :cycle AND milestone = 30");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':cycle', $currentCycle, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();
if ($result['cnt'] > 0) {
    $currentCycle++;
}

// Replay Prevention: Has this specific milestone already been redeemed in this cycle?
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM streak_claims WHERE account_id = :aid AND streak_cycle = :cycle AND milestone = :ms");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':cycle', $currentCycle, SQLITE3_INTEGER);
$stmt->bindValue(':ms', $milestone, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();

if ($result['cnt'] > 0) {
    http_response_code(400);
    echo json_encode(["error" => "You have already claimed the {$milestone}-day streak reward this cycle."]);
    exit;
}

// --------------------------------------------------------------------------
// 6. Execute Game Payload (Drop Generation)
// --------------------------------------------------------------------------
$materials = [
    '030B00' /* Power Material */,
    '030B01' /* Mind Material */,
    '030B03' /* HP Material */,
    '030B04' /* TP Material */,
    '030B05' /* Def Material */,
    '030B02' /* Evade Material */,
    '030B06' /* Luck Material */
];

// Map the milestone index to a dynamic reward scale
if ($milestone === 30) {
    $itemString = '030A02'; // Trigrinder
} else if ($milestone % 2 === 0) {
    $itemString = $materials[array_rand($materials)];
} else if ($milestone % 3 === 0) {
    $itemString = '030A01'; // Digrinder
} else {
    $itemString = '030A00'; // Monogrinder
}

// Execute the robust item parser to handle the drop securely
if (!function_exists('parse_and_drop_items')) {
    require_once 'functions.php';
}

$dropResult = parse_and_drop_items($accountId, $itemString);

if (!$dropResult['success']) {
    http_response_code(400);
    echo json_encode(["error" => $dropResult['error']]);
    exit;
}

// 5. Hardened Security DB Update
// Only fires after verification the item has actually manifested on the game server.

// --------------------------------------------------------------------------
// 7. Success Finalization
// --------------------------------------------------------------------------
// Ensure the DB marks exactly what was claimed so it cannot be claimed twice
$stmt = $db->prepare("INSERT INTO streak_claims (account_id, streak_cycle, milestone) VALUES (:aid, :cycle, :ms)");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':cycle', $currentCycle, SQLITE3_INTEGER);
$stmt->bindValue(':ms', $milestone, SQLITE3_INTEGER);
$stmt->execute();

echo json_encode([
    "success" => true,
    "item" => $itemString,
    "milestone" => $milestone,
    "message" => "{$itemString} dropped in-game! Keep the streak going!"
]);
?>
