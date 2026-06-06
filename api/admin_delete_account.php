<?php
/**
 * PSOBB API: Admin Delete Account
 * 
 * Allows a logged-in administrator to permanently delete any user account.
 * Drops the account via NewServ shell-exec and deletes the database record.
 */
require_once 'config.php';
start_secure_session();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

// Check Admin Session
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$target_account_id = $input['account_id'] ?? null;
$username = strtolower(trim($input['username'] ?? ''));

if (!$target_account_id && !$username) {
    http_response_code(400);
    echo json_encode(["error" => "Account ID or Username required"]);
    exit;
}

// 1. Perform Deletion via Shell Command
function run_shell_admin($cmd) {
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
    $ctx = stream_context_create($opts);
    return file_get_contents($url, false, $ctx);
}

$success = false;
$shell_result = null;

if ($target_account_id) {
    // Convert ID to Hex String
    $hexId = is_numeric($target_account_id) ? sprintf('%08X', $target_account_id) : $target_account_id;
    $res = run_shell_admin("delete-account $hexId");
    $jsonRes = json_decode($res, true);
    $shell_result = $jsonRes['result'] ?? ($jsonRes['error'] ?? 'Unknown response');
    
    if ($jsonRes && isset($jsonRes['result']) && stripos($jsonRes['result'], 'deleted') !== false) {
        $success = true;
    }
} else {
    // If we only have username, find account ID first from newserv accounts API
    $url = $NEWSERV_API_URL . "/y/accounts";
    $data = @file_get_contents($url);
    if ($data !== FALSE) {
        $accounts = json_decode($data, true);
        if (is_array($accounts)) {
            foreach ($accounts as $account) {
                if (isset($account['BBLicenses']) && is_array($account['BBLicenses'])) {
                    foreach ($account['BBLicenses'] as $license) {
                        if (strtolower($license['UserName'] ?? '') === $username) {
                            $target_account_id = $account['AccountID'];
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    if ($target_account_id) {
        $hexId = is_numeric($target_account_id) ? sprintf('%08X', $target_account_id) : $target_account_id;
        $res = run_shell_admin("delete-account $hexId");
        $jsonRes = json_decode($res, true);
        $shell_result = $jsonRes['result'] ?? ($jsonRes['error'] ?? 'Unknown response');
        if ($jsonRes && isset($jsonRes['result']) && stripos($jsonRes['result'], 'deleted') !== false) {
            $success = true;
        }
    } else {
        // If we still don't have account ID (e.g. server offline or account doesn't exist in newserv),
        // we can fallback to only deleting from SQLite if the admin requests it.
        $success = true; // We'll proceed to database cleanup
    }
}

// 2. Delete from SQLite DB
require_once 'db.php';
$db = get_db();

if ($target_account_id && $username) {
    $stmt = $db->prepare('DELETE FROM users WHERE account_id = :account_id OR username = :username COLLATE NOCASE');
    $stmt->bindValue(':account_id', $target_account_id, SQLITE3_INTEGER);
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
} else if ($target_account_id) {
    $stmt = $db->prepare('DELETE FROM users WHERE account_id = :account_id');
    $stmt->bindValue(':account_id', $target_account_id, SQLITE3_INTEGER);
} else {
    $stmt = $db->prepare('DELETE FROM users WHERE username = :username COLLATE NOCASE');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
}

try {
    $stmt->execute();
} catch (Exception $e) {
    // Log error
}

if ($success) {
    echo json_encode(['success' => true, 'message' => "Account deleted successfully. Server response: $shell_result"]);
} else {
    http_response_code(500);
    echo json_encode(['error' => "Deletion failed on game server. Response: $shell_result"]);
}
?>
