<?php
require_once 'config.php';
require_once 'db.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}
$accountId = $_SESSION['user']['account_id'];

// 1. Check if the player is currently online
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
    echo json_encode(["error" => "You must be logged into the game to claim daily rewards."]);
    exit;
}

// Protect against loading screen transitions
if (!isset($onlineClient['EXP']) || ($onlineClient['EXP'] === 0 && ($onlineClient['Level'] ?? 1) > 1)) {
    http_response_code(400);
    echo json_encode(["error" => "Your character is currently in a loading screen. Please wait until you are fully spawned to claim."]);
    exit;
}

// 2. Check if they're in a game (not just lobby)
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

if (!$inGame) {
    http_response_code(400);
    echo json_encode(["error" => "You must be in a game (not lobby) to claim daily rewards."]);
    exit;
}

// 3. Check if already claimed today
$db = get_db();
$today = date('Y-m-d');

// Create daily_rewards table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS daily_rewards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL,
    claim_date TEXT NOT NULL,
    item_string TEXT NOT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(account_id, claim_date)
)");

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM daily_rewards WHERE account_id = :aid AND claim_date = :date");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':date', $today, SQLITE3_TEXT);
$result = $stmt->execute()->fetchArray();

if ($result['cnt'] > 0) {
    http_response_code(400);
    echo json_encode(["error" => "You have already claimed your daily reward today. Come back tomorrow!"]);
    exit;
}

// 4. Pick a random daily reward
$dailyPool = [
    '04000000E80300000000000000000000' /* 1000 Meseta */ => 100,
    '030500' /* Star Atomizer */ => 60,
    '030400' /* Moon Atomizer */ => 80,
    '030002' /* Trimate */ => 100,
    '030102' /* Trifluid */ => 80,
    '030300' /* Sol Atomizer */ => 40,
    '030700' /* Telepipe */ => 100,
    '030900' /* Scape Doll */ => 15,
    '031000, 031000' /* Photon Drop x2 */ => 30,
    '030600' /* Antidote */ => 60,
    '030601' /* Antiparalysis */ => 60,
];

// Weighted random selection
$totalWeight = array_sum($dailyPool);
$roll = rand(1, $totalWeight);
$cumulative = 0;
$chosenItem = '';
foreach ($dailyPool as $item => $weight) {
    $cumulative += $weight;
    if ($roll <= $cumulative) {
        $chosenItem = $item;
        break;
    }
}

// 5. Drop the item
if (!function_exists('parse_and_drop_items')) {
    require_once 'functions.php';
}

$dropResult = parse_and_drop_items($accountId, $chosenItem);

if (!$dropResult['success']) {
    http_response_code(400);
    echo json_encode(["error" => $dropResult['error']]);
    exit;
}

// 6. Record the claim
$stmt = $db->prepare("INSERT INTO daily_rewards (account_id, claim_date, item_string) VALUES (:aid, :date, :item)");
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':date', $today, SQLITE3_TEXT);
$stmt->bindValue(':item', $chosenItem, SQLITE3_TEXT);
$stmt->execute();

echo json_encode([
    "success" => true,
    "item" => $chosenItem,
    "message" => "Daily reward: {$chosenItem} dropped in-game!"
]);
