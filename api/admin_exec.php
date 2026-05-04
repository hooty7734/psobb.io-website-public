<?php
/**
 * PSOBB API: Admin Shell Execution
 * 
 * A highly privileged endpoint that allows administrators to send
 * arbitrary raw commands directly to the NewServ `shell-exec` API.
 * Protected by strict session role checks.
 */
require_once 'config.php';
start_secure_session();
// Clean output
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
$cmd = $input['command'] ?? '';

if (!$cmd) {
    echo json_encode(['error' => 'No command provided']);
    exit;
}

// Function run_shell (Duplicated for standalone security)
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

// Execute
// Logic to prevent running potentially destructive commands indiscriminately?
// Admin is trusted.
$res = run_shell_admin($cmd);

if ($res === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to connect to game server shell API.']);
} else {
    // Ensure we are returning valid JSON
    header('Content-Type: application/json');
    echo $res;
}
?>
