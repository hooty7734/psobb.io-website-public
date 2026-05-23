<?php
/**
 * PSOBB API: Looking For Group (LFG) Requests Manager
 * 
 * Handles listing, creating, and deleting LFG request postings.
 * Strictly gated to Admin accounts during private testing.
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// Check Authenticated User Session
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$account_id = $_SESSION['user']['account_id'] ?? 0;
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = get_db();

    if ($method === 'GET') {
        // Fetch active game IDs from the game server to verify party existence
        $lobbies_url = $NEWSERV_API_URL . "/y/lobbies";
        $lobbies_json = @file_get_contents($lobbies_url);
        $active_lobby_ids = [];
        if ($lobbies_json !== false) {
            $lobbies = json_decode($lobbies_json, true);
            if (is_array($lobbies)) {
                foreach ($lobbies as $l) {
                    if (!empty($l['IsGame']) && isset($l['ID'])) {
                        $active_lobby_ids[] = (int)$l['ID'];
                    }
                }
            }
        }

        // Retrieve LFG requests from the last 2 hours
        // JOIN with missions to enrich linked bounty details if present
        $stmt = $db->prepare("
            SELECT lfg.*, 
                   m.title AS bounty_title, m.description AS bounty_description, m.reward_item_string AS bounty_reward
            FROM lfg_requests lfg
            LEFT JOIN missions m ON lfg.bounty_id = m.id
            WHERE lfg.created_at >= DATETIME('now', '-2 hours')
            ORDER BY lfg.created_at DESC
        ");
        $res = $stmt->execute();
        
        $listings = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            // Filter out listings whose linked party is no longer active
            $gid = $row['game_id'] !== null ? (int)$row['game_id'] : null;
            if ($lobbies_json !== false && $gid !== null && !in_array($gid, $active_lobby_ids)) {
                continue;
            }

            // Clean up description and name for safety
            $row['character_name'] = htmlspecialchars($row['character_name']);
            $row['description'] = htmlspecialchars($row['description']);
            
            // Strip mode prefix and extract game_mode dynamically
            $raw_game_name = trim($row['game_name'] ?? '');
            $game_mode = 'Normal';
            if (strlen($raw_game_name) > 0) {
                $modeChar = strtoupper($raw_game_name[0]);
                if (in_array($modeChar, ['E', 'B', 'C'])) {
                    $raw_game_name = trim(substr($raw_game_name, 1));
                    if ($modeChar === 'B') {
                        $game_mode = 'Battle';
                    } elseif ($modeChar === 'C') {
                        $game_mode = 'Challenge';
                    } else {
                        $game_mode = 'Normal';
                    }
                }
            }
            $row['game_name'] = htmlspecialchars($raw_game_name);
            $row['game_mode'] = $game_mode;
            
            // Format raw bounty reward details for LFG display
            if (!empty($row['bounty_reward'])) {
                $row['bounty_reward'] = renderRewardString($row['bounty_reward']);
            }
            
            $listings[] = $row;
        }
        
        echo json_encode([
            "success" => true,
            "listings" => $listings
        ]);
        exit;
    }

    if ($method === 'POST') {
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $description = trim($input['description'] ?? '');
        $bounty_id = isset($input['bounty_id']) && is_numeric($input['bounty_id']) ? (int)$input['bounty_id'] : null;
        $looking_for = isset($input['looking_for']) ? trim($input['looking_for']) : null; // e.g. "HU,RA"
        
        if (empty($description)) {
            http_response_code(400);
            echo json_encode(["error" => "Description is required."]);
            exit;
        }
        
        // 1. Query Server Summary to verify player is online
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
                if (isset($client['AccountID']) && (int)$client['AccountID'] === $account_id && !empty($client['Name'])) {
                    $active_client = $client;
                    break;
                }
            }
        }
        
        if (!$active_client) {
            http_response_code(400);
            echo json_encode(["error" => "You must be logged into a character in-game to create an LFG listing."]);
            exit;
        }
        
        // 2. Identify their current game / lobby
        $game_id = $active_client['LobbyID'] ?? null;
        $game_name = null;
        
        if ($game_id !== null) {
            // Check if this lobby ID is an active game in the summary list
            if (isset($summary['Games']) && is_array($summary['Games'])) {
                foreach ($summary['Games'] as $g) {
                    if (isset($g['ID']) && $g['ID'] == $game_id) {
                        $rawName = trim($g['Name'] ?? 'Active Game');
                        if (strlen($rawName) > 0 && in_array(strtoupper($rawName[0]), ['E', 'B', 'C'])) {
                            $game_name = trim(substr($rawName, 1));
                        } else {
                            $game_name = $rawName;
                        }
                        break;
                    }
                }
            }
            if (!$game_name) {
                http_response_code(400);
                echo json_encode(["error" => "You must be inside an active joinable party to create an LFG post."]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "You must be inside an active joinable party to create an LFG post."]);
            exit;
        }
        
        // 3. Optional: Validate the bounty actually belongs to the user, is in progress, and is a Team Bounty
        if ($bounty_id !== null) {
            $b_stmt = $db->prepare("
                SELECT pm.id 
                FROM player_missions pm
                JOIN missions m ON pm.mission_id = m.id
                WHERE pm.mission_id = :mid 
                  AND pm.account_id = :aid 
                  AND pm.status = 'in_progress'
                  AND m.goal_type IN ('HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS')
            ");
            $b_stmt->bindValue(':mid', $bounty_id, SQLITE3_INTEGER);
            $b_stmt->bindValue(':aid', $account_id, SQLITE3_INTEGER);
            $b_res = $b_stmt->execute();
            if (!$b_res->fetchArray(SQLITE3_ASSOC)) {
                // Not a valid active team bounty for this user
                $bounty_id = null;
            }
        }
        
        // 4. Delete any older LFG requests from the same account to prevent spam
        $del_stmt = $db->prepare("DELETE FROM lfg_requests WHERE account_id = :aid");
        $del_stmt->bindValue(':aid', $account_id, SQLITE3_INTEGER);
        $del_stmt->execute();
        
        // 5. Insert new LFG Request
        $ins = $db->prepare("
            INSERT INTO lfg_requests (account_id, character_name, class, level, section_id, game_id, game_name, bounty_id, looking_for, description)
            VALUES (:aid, :cname, :class, :lvl, :sec, :gid, :gname, :bid, :lf, :desc)
        ");
        $ins->bindValue(':aid', $account_id, SQLITE3_INTEGER);
        $ins->bindValue(':cname', $active_client['Name'], SQLITE3_TEXT);
        $ins->bindValue(':class', $active_client['Class'], SQLITE3_TEXT);
        $ins->bindValue(':lvl', (int)$active_client['Level'], SQLITE3_INTEGER);
        $ins->bindValue(':sec', $active_client['SectionID'], SQLITE3_TEXT);
        $ins->bindValue(':gid', $game_id, SQLITE3_INTEGER);
        $ins->bindValue(':gname', $game_name, SQLITE3_TEXT);
        $ins->bindValue(':bid', $bounty_id, SQLITE3_INTEGER);
        $ins->bindValue(':lf', $looking_for, SQLITE3_TEXT);
        $ins->bindValue(':desc', $description, SQLITE3_TEXT);
        
        $ins->execute();
        
        echo json_encode([
            "success" => true,
            "message" => "LFG request posted successfully!"
        ]);
        exit;
    }

    if ($method === 'DELETE') {
        verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $post_id = isset($input['id']) ? (int)$input['id'] : 0;
        
        if (!$post_id) {
            http_response_code(400);
            echo json_encode(["error" => "Request ID is required for deletion."]);
            exit;
        }
        
        // Admins can delete their own LFG request
        $del = $db->prepare("DELETE FROM lfg_requests WHERE id = :id AND account_id = :aid");
        $del->bindValue(':id', $post_id, SQLITE3_INTEGER);
        $del->bindValue(':aid', $account_id, SQLITE3_INTEGER);
        $del->execute();
        
        if ($db->changes() > 0) {
            echo json_encode(["success" => true, "message" => "LFG request removed."]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Listing not found or not owned by you."]);
        }
        exit;
    }

    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
