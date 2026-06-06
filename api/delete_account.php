<?php
/**
 * PSOBB API: Delete Account
 * 
 * Allows an authenticated user to permanently delete their account.
 * Requires the user to confirm their username and password.
 * This action is irreversible and drops all associated characters and data.
 */
require_once 'config.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
start_secure_session();
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

$input = json_decode(file_get_contents('php://input'), true);
$username = strtolower($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(["error" => "Username and password required"]);
    exit;
}

// 1. Authenticate against newserv
$url = $NEWSERV_API_URL . "/y/accounts";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Server offline"]);
    exit;
}

$accounts = json_decode($data, true);
$target_account_id = null;

if (is_array($accounts)) {
    foreach ($accounts as $account) {
        if (isset($account['BBLicenses']) && is_array($account['BBLicenses'])) {
            foreach ($account['BBLicenses'] as $license) {
                if ((strtolower($license['UserName'] ?? '')) === $username && ($license['Password'] ?? '') === $password) {
                    $target_account_id = $account['AccountID'];
                    break 2;
                }
            }
        }
    }
}

if (!$target_account_id) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
    exit;
}

// 2. Perform Deletion via Shell Command
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
        ]
    ];
    $ctx = stream_context_create($opts);
    return file_get_contents($url, false, $ctx);
}

// Convert ID to Hex String
if (is_numeric($target_account_id)) {
    $hexId = sprintf('%08X', $target_account_id);
} else {
    $hexId = $target_account_id;
}

$res = run_shell("delete-account $hexId");
$jsonRes = json_decode($res, true);

if ($jsonRes && isset($jsonRes['result']) && stripos($jsonRes['result'], 'deleted') !== false) {
    // 3. Delete from DB (Secondary)
    // We ignore errors here because the account is already gone from newserv
    $db = get_db();
    $stmt = $db->prepare('DELETE FROM users WHERE username = :username COLLATE NOCASE');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    try {
        $stmt->execute();
    } catch(Exception $e) {
        // Log?
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Deletion failed: ' . ($jsonRes['error'] ?? ($jsonRes['result'] ?? 'Unknown error'))]);
}
