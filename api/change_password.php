<?php
/**
 * PSOBB API: Change Password
 * 
 * Allows an authenticated user to change their account password.
 * Uses the NewServ shell-exec API to delete and recreate the license.
 * Expects JSON payload with 'username', 'old_password', and 'new_password'.
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
// Clean output
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
start_secure_session();
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

$input = json_decode(file_get_contents('php://input'), true);
$username = strtolower(trim($input['username'] ?? ''));
$old_password = trim($input['old_password'] ?? '');
$new_password = trim($input['new_password'] ?? '');

if (!$username || !$old_password || !$new_password) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields required']);
    exit;
}
if (strlen($new_password) > 16 || preg_match('/\s/', $new_password)) {
    http_response_code(400);
    echo json_encode(['error' => 'New password invalid (max 16 chars, no spaces)']);
    exit;
}

// 1. Authenticate (Find Account ID)
$url = $NEWSERV_API_URL . "/y/accounts";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Server offline"]);
    exit;
}

$accounts = json_decode($data, true);
$target_id = null;

if (is_array($accounts)) {
    foreach ($accounts as $account) {
        if (isset($account['BBLicenses']) && is_array($account['BBLicenses'])) {
            foreach ($account['BBLicenses'] as $license) {
                if ((strtolower($license['UserName'] ?? '')) === $username && ($license['Password'] ?? '') === $old_password) {
                    $target_id = $account['AccountID'];
                    break 2;
                }
            }
        }
    }
}

if (!$target_id) {
    http_response_code(401);
    echo json_encode(["error" => "Incorrect old password"]);
    exit;
}

// 2. Shell Execution Helper
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

$hexId = is_numeric($target_id) ? sprintf('%08X', $target_id) : $target_id;

// 3. Update Password (Delete old license, add new)
run_shell("delete-license $hexId BB $username");

$res = run_shell("add-license $hexId BB $username $new_password");
$json = json_decode($res, true);

if ($json && isset($json['result']) && stripos($json['result'], 'updated') !== false) {
    // Send Confirmation Email
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT email, language FROM users WHERE username = :u COLLATE NOCASE");
        $stmt->bindValue(':u', $username);
        $dbRes = $stmt->execute();
        $row = $dbRes->fetchArray(SQLITE3_ASSOC);
        
        if ($row && !empty($row['email'])) {
            $lang_pref = $row['language'] ?? 'en';
            if ($lang_pref === 'jp') {
                send_email($row['email'], "パスワード変更完了 - PSOBB.IO", "$username さん、\n\nパスワードが正常に変更されました。\n心当たりがない場合は、直ちに管理者にご連絡ください。");
            } else {
                send_email($row['email'], "Password Changed - PSOBB.IO", "Hello $username,\n\nYour password was successfully changed.\nIf this wasn't you, please contact an admin immediately.");
            }
        }
    } catch (Exception $e) {
        // Ignore DB/Mail errors for password change success
    }

    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Password update failed on server.']);
}
?>
