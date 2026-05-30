<?php
/**
 * PSOBB API: Live Lobby Feed
 * 
 * Returns the current lobby state for the logged-in player:
 * - Players in their lobby/game room
 * - Lobby/game name and metadata
 * - Player join/leave context for the chat console
 * 
 * No data is stored — this is a live snapshot from newserv.
 */
require_once 'config.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];

// 1. Find our client and their lobby
$clientsData = @file_get_contents($NEWSERV_API_URL . '/y/clients');
if ($clientsData === false) {
    echo json_encode(["online" => false, "error" => "Server offline"]);
    exit;
}

$clients = json_decode($clientsData, true);
if (!is_array($clients)) {
    echo json_encode(["online" => false, "error" => "Invalid server response"]);
    exit;
}

$myClient = null;
foreach ($clients as $c) {
    if (isset($c['Account']['AccountID']) && (int)$c['Account']['AccountID'] === $accountId) {
        $myClient = $c;
        break;
    }
}

if (!$myClient) {
    echo json_encode([
        "online" => false,
        "message" => "You are not logged into the game."
    ]);
    exit;
}

$lobbyId = $myClient['LobbyID'] ?? null;
$myName = $myClient['Name'] ?? 'Unknown';

if ($lobbyId === null) {
    echo json_encode([
        "online" => true,
        "in_lobby" => false,
        "character" => $myName,
        "message" => "Not in a lobby yet."
    ]);
    exit;
}

// 2. Fetch lobby details
$lobbiesData = @file_get_contents($NEWSERV_API_URL . '/y/lobbies');
$lobbyInfo = null;
if ($lobbiesData !== false) {
    $lobbies = json_decode($lobbiesData, true);
    if (is_array($lobbies)) {
        foreach ($lobbies as $l) {
            if (isset($l['ID']) && $l['ID'] === $lobbyId) {
                $lobbyInfo = $l;
                break;
            }
        }
    }
}

// 3. Find all players in the same lobby
$lobbyPlayers = [];
foreach ($clients as $c) {
    if (isset($c['LobbyID']) && $c['LobbyID'] === $lobbyId) {
        $lobbyPlayers[] = [
            "name" => $c['Name'] ?? 'Unknown',
            "level" => $c['Level'] ?? 1,
            "class" => $c['CharClass'] ?? 'Unknown',
            "is_you" => (isset($c['Account']['AccountID']) && (int)$c['Account']['AccountID'] === $accountId)
        ];
    }
}

// 4. Build response
$result = [
    "online" => true,
    "in_lobby" => true,
    "character" => $myName,
    "lobby" => [
        "id" => $lobbyId,
        "is_game" => $lobbyInfo['IsGame'] ?? false,
        "name" => $lobbyInfo['Name'] ?? 'Lobby',
        "episode" => $lobbyInfo['Episode'] ?? null,
        "difficulty" => $lobbyInfo['Difficulty'] ?? null,
        "section_id" => $lobbyInfo['SectionID'] ?? null,
        "quest" => isset($lobbyInfo['Quest']) && $lobbyInfo['Quest'] ? ($lobbyInfo['Quest']['Name'] ?? null) : null,
    ],
    "players" => $lobbyPlayers,
    "player_count" => count($lobbyPlayers)
];

echo json_encode($result);
?>
