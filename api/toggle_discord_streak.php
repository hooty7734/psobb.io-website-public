<?php
/**
 * PSOBB API: Toggle Discord Streak Alerts
 * 
 * Secure endpoint that toggles the 'receive_discord_streak_msg' parameter in the users table
 * to allow players to easily opt-in or opt-out of Discord DM streak alerts.
 */
require_once 'config.php';
require_once 'db.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// 1. Verify Authentication
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];

// 2. Validate Input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['receive_discord_streak_msg'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing setting parameter"]);
    exit;
}

$enabled = $input['receive_discord_streak_msg'] ? 1 : 0;

try {
    $db = get_db();
    
    // Update setting in users table
    $stmt = $db->prepare("UPDATE users SET receive_discord_streak_msg = :enabled WHERE account_id = :accId");
    $stmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
    $stmt->bindValue(':accId', $accountId, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Update active session memory
    $_SESSION['user']['receive_discord_streak_msg'] = $enabled;
    
    echo json_encode([
        "success" => true,
        "message" => $enabled ? "Discord DM streak alerts enabled!" : "Discord DM streak alerts disabled!"
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
