<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$password = trim($input['password'] ?? '');

if (!$token || !$password) {
    echo json_encode(['error' => 'Missing token or password']);
    exit;
}

if (strlen($password) > 16 || preg_match('/\s/', $password)) {
    echo json_encode(['error' => 'Password must be max 16 chars and no spaces']);
    exit;
}

$db = get_db();

// 1. Verify Token
$stmt = $db->prepare("SELECT email FROM password_resets WHERE token = :t");
$stmt->bindValue(':t', $token);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

$email = $row['email'];

// 2. Get Account Info
$stmt = $db->prepare("SELECT account_id, username FROM users WHERE email = :e");
$stmt->bindValue(':e', $email);
$res = $stmt->execute();
$userRow = $res->fetchArray(SQLITE3_ASSOC);

if (!$userRow) {
    echo json_encode(['error' => 'User not found (email mismatch)']);
    exit;
}

$username = strtolower($userRow['username']);
$account_id = $userRow['account_id'];
$hexId = sprintf('%08X', $account_id);

// 3. Update Password in Newserv
function run_shell($cmd) {
    global $NEWSERV_API_URL;
    $url = $NEWSERV_API_URL . "/y/shell-exec";
    $body = json_encode(['command' => $cmd]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $body,
            'ignore_errors' => true
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    return file_get_contents($url, false, stream_context_create($opts));
}

// Delete old license (admin force)
run_shell("delete-license $hexId BB $username");

// Add new license
$res = run_shell("add-license $hexId BB $username $password");
$json = json_decode($res, true);

// 4. Cleanup and Respond
if ($json && isset($json['result']) && stripos($json['result'], 'updated') !== false) {
    // Delete used token

    $delStmt = $db->prepare("DELETE FROM password_resets WHERE token = :t");
    $delStmt->bindValue(':t', $token);
    $delStmt->execute();

    // Verify
    send_email($email, "Password Changed", "Your password has been successfully reset.");
    
    echo json_encode(['success' => true]);
} else {
    // Determine specific error
    echo json_encode(['error' => 'Server failed to update password. Check admin logs.', 'debug' => $res]);
}
?>
