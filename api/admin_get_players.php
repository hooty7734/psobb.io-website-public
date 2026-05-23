<?php
require_once 'config.php';
start_secure_session();
header('Content-Type: application/json');

// Check Admin Session
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

// Reuse connection logic from admin_exec.php or similar
function run_shell_command($cmd) {
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
    $result = file_get_contents($url, false, $ctx);
    if ($result === false) return null;
    
    $json = json_decode($result, true);
    return $json['result'] ?? null;
}

// Execute 'show-slots' to get players
$output = run_shell_command("show-slots");

if ($output === null) {
    echo json_encode(['error' => 'Failed to retrieve player list']);
    exit;
}

// Parse output
// Example output:
// Slots:
//   0: PlayerName (GC: 12345678, ID: 1, ...)
//   1: AnotherPlayer (GC: ...)
$players = [];
$lines = explode("\n", $output);
foreach ($lines as $line) {
    $line = trim($line);
    if (preg_match('/^\d+:\s+(.*?)\s+\(GC:/', $line, $matches)) {
        $players[] = $matches[1];
    }
}

echo json_encode(['players' => $players]);
?>
