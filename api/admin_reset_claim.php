<?php
/**
 * PSOBB API: Admin Reset Claim
 * 
 * Allows an administrator to revoke recent milestone/reward claims for a specific
 * account and character, effectively rolling back accidental redemptions.
 */
require_once 'config.php';
require_once 'db.php';

start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$accountId = $input['account_id'] ?? null;
$characterName = $input['character_name'] ?? null;
$count = intval($input['count'] ?? 1);

if (!$accountId || !$characterName || $count < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing or invalid parameters."]);
    exit;
}

try {
    $db = get_db();
    
    // SQLite doesn't natively support DELETE with LIMIT directly in subqueries depending on compile options.
    // So we select the IDs first, then delete them.
    $stmt = $db->prepare("SELECT id FROM rewards_claimed WHERE account_id = :aid AND character_name = :cname ORDER BY claimed_at DESC LIMIT :cnt");
    $stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
    $stmt->bindValue(':cname', $characterName, SQLITE3_TEXT);
    $stmt->bindValue(':cnt', $count, SQLITE3_INTEGER);
    $res = $stmt->execute();
    
    $idsToDelete = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $idsToDelete[] = $row['id'];
    }
    
    if (empty($idsToDelete)) {
        echo json_encode(["success" => false, "error" => "No matching claims found to reset."]);
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    $delStmt = $db->prepare("DELETE FROM rewards_claimed WHERE id IN ($placeholders)");
    foreach ($idsToDelete as $index => $id) {
        $delStmt->bindValue($index + 1, $id, SQLITE3_INTEGER);
    }
    $delStmt->execute();
    
    $deleted = $db->changes();
    echo json_encode(["success" => true, "message" => "Successfully reverted $deleted recent claim(s) for $characterName (Account $accountId)."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
