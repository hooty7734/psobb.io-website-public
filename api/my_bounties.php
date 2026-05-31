<?php
/**
 * PSOBB API: Get My Bounties & Community Events
 * 
 * Returns all bounties for the logged-in user (in_progress and completed),
 * plus active community event status with user contribution.
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
    
    // All bounties for this player (in_progress + completed but not yet redeemed)
    $stmt = $db->prepare("
        SELECT pm.id AS player_mission_id, pm.mission_id, pm.character_name, pm.status,
               pm.accepted_at, pm.completed_at,
               m.title, m.description, m.goal_type, m.goal_target, m.reward_item_string
        FROM player_missions pm
        JOIN missions m ON pm.mission_id = m.id
        WHERE pm.account_id = :accId 
          AND pm.status IN ('in_progress', 'completed')
        ORDER BY CASE pm.status WHEN 'completed' THEN 0 ELSE 1 END, pm.accepted_at DESC
    ");
    $stmt->bindValue(':accId', $account_id, SQLITE3_INTEGER);
    $res = $stmt->execute();
    
    $bounties = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        // Decode reward_item_string hex into readable item name using canonical function
        if (!empty($row['reward_item_string'])) {
            $row['reward_decoded'] = renderRewardString($row['reward_item_string']);
        }
        $bounties[] = $row;
    }
    
    // Active community events with user contribution
    $events = [];
    $ce_res = $db->query("SELECT id, title, description, goal_type, goal_target, target_amount, current_progress, reward_item_string, status, created_at FROM community_events WHERE status = 'active' ORDER BY created_at DESC");
    if ($ce_res) {
        while ($row = $ce_res->fetchArray(SQLITE3_ASSOC)) {
            // Get user's contribution to this event
            $p_stmt = $db->prepare("SELECT contribution_count FROM community_event_participants WHERE event_id = :eid AND account_id = :aid");
            $p_stmt->bindValue(':eid', $row['id'], SQLITE3_INTEGER);
            $p_stmt->bindValue(':aid', $account_id, SQLITE3_INTEGER);
            $p_res = $p_stmt->execute();
            $p_row = $p_res->fetchArray(SQLITE3_ASSOC);
            $row['user_contribution'] = $p_row ? (int)$p_row['contribution_count'] : 0;
            $row['progress_pct'] = $row['target_amount'] > 0 ? round(($row['current_progress'] / $row['target_amount']) * 100, 1) : 0;
            $events[] = $row;
        }
    }
    
    // Claimable community events (completed, user participated, not yet claimed)
    $claimable_events = [];
    $cl_res = $db->prepare("
        SELECT ce.id as event_id, ce.title, ce.reward_item_string, ce.top_3_reward_item_string, 
               cep.contribution_count
        FROM community_events ce
        JOIN community_event_participants cep ON ce.id = cep.event_id
        WHERE ce.status = 'completed' 
          AND cep.account_id = :aid
          AND cep.reward_claimed = 0
    ");
    $cl_res->bindValue(':aid', $account_id, SQLITE3_INTEGER);
    $cl_exec = $cl_res->execute();
    if ($cl_exec) {
        while ($row = $cl_exec->fetchArray(SQLITE3_ASSOC)) {
            if (!empty($row['reward_item_string'])) {
                $row['reward_decoded'] = renderRewardString($row['reward_item_string']);
            }
            $claimable_events[] = $row;
        }
    }
    
    echo json_encode([
        "success" => true,
        "bounties" => $bounties,
        "community_events" => $events,
        "claimable_events" => $claimable_events
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
