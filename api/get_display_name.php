<?php
/**
 * PSOBB API: Get Display Name
 * 
 * Returns the current display name for the logged-in user.
 */
require_once 'config.php';
require_once 'db.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT display_name FROM users WHERE account_id = :aid");
$stmt->bindValue(':aid', $_SESSION['user']['account_id'], SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

echo json_encode([
    "display_name" => $result['display_name'] ?? null
]);
