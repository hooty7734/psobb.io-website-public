<?php
/**
 * PSOBB API: Get All My Bounties (Dashboard Portal)
 * 
 * Returns ALL bounties for the logged-in player:
 *  - in_progress: currently working on
 *  - ready_to_redeem: completed, awaiting claim
 * 
 * Used by the dashboard Bounties tab for a mini bounty board.
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

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
    
    // Fetch ALL active bounties for this player (in_progress + ready_to_redeem)
    $stmt = $db->prepare("
        SELECT pm.id AS player_mission_id, pm.mission_id, pm.character_name, pm.status,
               pm.accepted_at, pm.completed_at,
               m.title, m.description, m.goal_type, m.goal_target, m.reward_item_string
        FROM player_missions pm
        JOIN missions m ON pm.mission_id = m.id
        WHERE pm.account_id = :accId 
          AND pm.status IN ('in_progress', 'ready_to_redeem')
        ORDER BY 
            CASE pm.status 
                WHEN 'ready_to_redeem' THEN 0 
                WHEN 'in_progress' THEN 1 
            END,
            pm.id DESC
    ");
    $stmt->bindValue(':accId', $account_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    
    $bounties = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        // Add human-readable objective
        $row['objective'] = getClearObjective(
            $row['goal_type'], 
            $row['goal_target'], 
            $row['title'], 
            $row['description']
        );
        // Add rendered reward string (decode hex to item names)
        $row['reward_display'] = renderRewardString($row['reward_item_string']);
        // Identify team bounties
        $row['is_team'] = in_array($row['goal_type'], ['HARDCORE_MENTOR', 'DIVERSE_PARTY_BOSS']);
        // Strip internal fields — no raw hex for players
        unset($row['reward_item_string']);
        unset($row['goal_type']);
        unset($row['goal_target']);
        $bounties[] = $row;
    }
    
    // Count stats
    $in_progress = array_filter($bounties, fn($b) => $b['status'] === 'in_progress');
    $claimable = array_filter($bounties, fn($b) => $b['status'] === 'ready_to_redeem');
    
    echo json_encode([
        "success" => true,
        "bounties" => $bounties,
        "stats" => [
            "in_progress" => count($in_progress),
            "claimable" => count($claimable),
            "total" => count($bounties)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
