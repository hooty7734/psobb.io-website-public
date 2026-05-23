<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'db.php';

try {
    $db = get_db();
    
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    
    if ($status !== 'all') {
        $stmt = $db->prepare("SELECT * FROM community_events WHERE status = :status ORDER BY created_at DESC");
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $result = $stmt->execute();
    } else {
        $result = $db->query("SELECT * FROM community_events ORDER BY created_at DESC");
    }
    
    $events = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $events[] = [
            "id" => $row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "goalType" => $row['goal_type'],
            "goalTarget" => $row['goal_target'],
            "targetAmount" => (int)$row['target_amount'],
            "currentProgress" => (int)$row['current_progress'],
            "rewardItemString" => $row['reward_item_string'],
            "status" => $row['status'],
            "createdAt" => $row['created_at'],
            "completedAt" => $row['completed_at']
        ];
    }
    
    echo json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
