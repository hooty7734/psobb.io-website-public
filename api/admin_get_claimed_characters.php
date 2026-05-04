<?php
require_once 'config.php';
require_once 'db.php';

start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

try {
    $db = get_db();
    // Get unique combinations of character and account from recent claims
    $stmt = $db->prepare("SELECT DISTINCT account_id, character_name FROM rewards_claimed ORDER BY claimed_at DESC LIMIT 50");
    $res = $stmt->execute();
    
    $characters = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $characters[] = [
            'account_id' => $row['account_id'],
            'character_name' => $row['character_name']
        ];
    }
    
    echo json_encode(["success" => true, "characters" => $characters]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
