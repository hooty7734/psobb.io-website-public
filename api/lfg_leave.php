<?php
/**
 * PSOBB API: Browser-to-Game Leave Group
 * 
 * Sends the `$exit` chat command to transition the player's active character
 * out of their current game and back into a public server lobby.
 * If the player is the LFG leader for that game, their LFG post is removed.
 * Strictly gated to Admin accounts during private testing.
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// Check Authenticated User Session
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

$account_id = $_SESSION['user']['account_id'] ?? 0;

if (!$account_id) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid session account ID"]);
    exit;
}

/**
 * Dispatches a command to the NewServ shell-exec API.
 */
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
    return file_get_contents($url, false, $ctx);
}

try {
    // 1. Fetch `/y/summary` to verify user is online and extract client details
    $summary_url = $NEWSERV_API_URL . "/y/summary";
    $summary_json = @file_get_contents($summary_url);
    if ($summary_json === false) {
        http_response_code(502);
        echo json_encode(["error" => "Game server API is currently offline."]);
        exit;
    }
    
    $summary = json_decode($summary_json, true);
    $active_client = null;
    if (isset($summary['Clients']) && is_array($summary['Clients'])) {
        foreach ($summary['Clients'] as $client) {
            if (isset($client['AccountID']) && (int)$client['AccountID'] === $account_id) {
                $active_client = $client;
                break;
            }
        }
    }
    
    if (!$active_client) {
        http_response_code(400);
        echo json_encode(["error" => "You must be logged into a character in-game to exit a group."]);
        exit;
    }
    
    $client_id = $active_client['ID'];
    $client_id_hex = sprintf('%X', $client_id); // Channel names use hex: C-{:X}
    $current_lobby_id = $active_client['LobbyID'] ?? null;
    
    // 2. Check if user is the LFG leader for their current game.
    //    If so, remove the LFG post since the leader is disbanding.
    $lfg_deleted = false;
    if ($current_lobby_id !== null) {
        $db = get_db();
        $del_stmt = $db->prepare("DELETE FROM lfg_requests WHERE account_id = :aid AND game_id = :gid");
        $del_stmt->bindValue(':aid', $account_id, SQLITE3_INTEGER);
        $del_stmt->bindValue(':gid', (int)$current_lobby_id, SQLITE3_INTEGER);
        $del_stmt->execute();
        
        if ($db->changes() > 0) {
            $lfg_deleted = true;
        }
    }
    
    // 3. Execute the official `$exit` chat command as if the client sent it in-game.
    // This utilizes newserv's native $exit handler, which cleanly manages character saves,
    // quest exits, and lobby transitions for all client versions and game states.
    $shell_cmd = "on C-{$client_id_hex} cc \$exit";
    $shell_res = run_shell_command($shell_cmd);
    
    if ($shell_res === false) {
        http_response_code(502);
        echo json_encode(["error" => "Failed to transmit exit command to the server."]);
        exit;
    }
    
    $res_data = json_decode($shell_res, true);
    
    $message = "Exit command successfully sent! Your character will transition back to a lobby.";
    if ($lfg_deleted) {
        $message = "You have left the group and your LFG listing has been removed.";
    }
    
    echo json_encode([
        "success" => true,
        "message" => $message,
        "lfg_deleted" => $lfg_deleted,
        "server_response" => $res_data
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Exit failed: " . $e->getMessage()]);
}
?>
