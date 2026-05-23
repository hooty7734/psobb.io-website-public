<?php
/**
 * PSOBB API: Unified Active Games Registry
 * 
 * Fetches, filters, and enriches lobbies and active games from the server's state,
 * compiling level constraints and player classes for the LFG coordination client.
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

try {
    // 1. Fetch `/y/lobbies`
    $lobbies_url = $NEWSERV_API_URL . "/y/lobbies";
    $lobbies_json = @file_get_contents($lobbies_url);
    if ($lobbies_json === false) {
        http_response_code(502);
        echo json_encode(["error" => "Game server lobbies API is offline."]);
        exit;
    }
    
    // 2. Fetch `/y/summary` (to resolve Client classes)
    $summary_url = $NEWSERV_API_URL . "/y/summary";
    $summary_json = @file_get_contents($summary_url);
    if ($summary_json === false) {
        http_response_code(502);
        echo json_encode(["error" => "Game server summary API is offline."]);
        exit;
    }

    $lobbies = json_decode($lobbies_json, true);
    $summary = json_decode($summary_json, true);

    // Build client registry index by Client ID for fast lookups
    $client_registry = [];
    if (isset($summary['Clients']) && is_array($summary['Clients'])) {
        foreach ($summary['Clients'] as $c) {
            if (isset($c['ID'])) {
                $client_registry[$c['ID']] = $c;
            }
        }
    }

    // 3. Fetch active LFG request game IDs from the database (last 2 hours)
    $db = get_db();
    $lfg_stmt = $db->prepare("
        SELECT DISTINCT game_id 
        FROM lfg_requests 
        WHERE created_at >= DATETIME('now', '-2 hours') AND game_id IS NOT NULL
    ");
    $lfg_res = $lfg_stmt->execute();
    $lfg_game_ids = [];
    while ($row = $lfg_res->fetchArray(SQLITE3_ASSOC)) {
        $lfg_game_ids[] = (int)$row['game_id'];
    }

    $active_games = [];
    if (is_array($lobbies)) {
        foreach ($lobbies as $l) {
            // Only process actual game lobbies
            if (empty($l['IsGame'])) {
                continue;
            }

            // Expose only games actively searching for LFGs
            $game_id = (int)($l['ID'] ?? 0);
            if (!in_array($game_id, $lfg_game_ids)) {
                continue;
            }

            $max_clients = isset($l['MaxClients']) ? (int)$l['MaxClients'] : 4;
            $client_ids = isset($l['ClientIDs']) && is_array($l['ClientIDs']) ? $l['ClientIDs'] : [];
            
            // Map connected client classes
            $client_classes = [];
            $players_count = 0;
            
            for ($i = 0; $i < $max_clients; $i++) {
                $cid = $client_ids[$i] ?? null;
                if ($cid !== null && isset($client_registry[$cid])) {
                    $client_classes[] = $client_registry[$cid]['Class'] ?? 'Unknown';
                    $players_count++;
                } else {
                    $client_classes[] = null;
                }
            }

            // Exclude modes characters in game name if present (handled in summary typically)
            $cleanName = trim($l['Name'] ?? 'Active Game');
            $gameMode = 'Normal';
            if (strlen($cleanName) > 0) {
                $modeChar = strtoupper($cleanName[0]);
                if (in_array($modeChar, ['E', 'B', 'C'])) {
                    $cleanName = trim(substr($cleanName, 1));
                    if ($modeChar === 'B') {
                        $gameMode = 'Battle';
                    } elseif ($modeChar === 'C') {
                        $gameMode = 'Challenge';
                    } else {
                        $gameMode = 'Normal';
                    }
                }
            }

            $active_games[] = [
                "ID" => (int)($l['ID'] ?? 0),
                "Name" => $cleanName,
                "Players" => $players_count,
                "MaxClients" => $max_clients,
                "MinLevel" => empty($l['MinLevel']) ? 1 : (int)$l['MinLevel'],
                "MaxLevel" => empty($l['MaxLevel']) ? 200 : (int)$l['MaxLevel'],
                "Episode" => $l['Episode'] ?? 'Ep1',
                "Difficulty" => $l['Difficulty'] ?? 'Normal',
                "HasPassword" => !empty($l['HasPassword']),
                "SectionID" => $l['SectionID'] ?? null,
                "Mode" => $gameMode,
                "ClientClasses" => $client_classes
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "games" => $active_games
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Unified active games resolve failed: " . $e->getMessage()]);
}
?>
