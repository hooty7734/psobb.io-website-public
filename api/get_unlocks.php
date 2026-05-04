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

// 1. Fetch online clients from newserv
$url = $NEWSERV_API_URL . "/y/clients";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Game server is offline, cannot fetch character data."]);
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
    // Return early with a message requiring them to log in
    echo json_encode([
        "is_online" => false,
        "message" => "Please log into the game with a character to view and claim its rewards!"
    ]);
    exit;
}

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

$level = $onlineCharacter['Level'] ?? 1;
$name = $onlineCharacter['Name'] ?? 'Unknown';
$charClass = $onlineCharacter['CharClass'] ?? 'HUmar';

// 2. Query claimed rewards for this character
try {
    $db = get_db();
    $stmt = $db->prepare("SELECT level_milestone, category FROM rewards_claimed WHERE account_id = :aid AND character_name = :cname");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':cname', $name, SQLITE3_TEXT);
    $result = $stmt->execute();

    $claimed = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $claimed[$row['level_milestone']] = $row;
    }

    // 3. Generate milestone list up to current level
    $milestones = [];
    $maxMilestone = floor($level / 5) * 5;

    for ($m = 5; $m <= $maxMilestone; $m += 5) {
        $milestoneData = [
            "level" => $m,
            "claimed" => isset($claimed[$m]),
            "claimed_category" => isset($claimed[$m]) ? $claimed[$m]['category'] : null
        ];
        $milestones[] = $milestoneData;
    }

    echo json_encode([
        "is_online" => true,
        "in_game" => $inGame,
        "character" => [
            "name" => $name,
            "level" => $level,
            "class" => $charClass
        ],
        "milestones" => array_reverse($milestones) // Show highest levels first
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
