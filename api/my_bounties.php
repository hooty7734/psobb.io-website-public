<?php
/**
 * PSOBB API: Get My Active Bounties
 * 
 * Returns a list of in-progress bounties for the logged-in admin user.
 * Restricted strictly to Admin testing sessions for now.
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

$account_id = $_SESSION['user']['account_id'] ?? 0;

if (!$account_id) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid session account ID"]);
    exit;
}

try {
    $db = get_db();
    
    // Select in-progress bounties for this player that are Team Bounties
    $stmt = $db->prepare("
        SELECT pm.id AS player_mission_id, pm.mission_id, pm.character_name, pm.status,
               m.title, m.description, m.goal_type, m.goal_target, m.reward_item_string
        FROM player_missions pm
        JOIN missions m ON pm.mission_id = m.id
        WHERE pm.account_id = :accId 
          AND pm.status = 'in_progress'
          AND m.goal_type IN ('HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS')
    ");
    $stmt->bindValue(':accId', $account_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    
    $bounties = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $bounties[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "bounties" => $bounties
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error while fetching active bounties: " . $e->getMessage()]);
}
?>
