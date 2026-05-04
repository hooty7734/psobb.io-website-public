#!/usr/bin/env php
<?php
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.");
}

if ($argc < 2) {
    echo "Usage: php promote_admin.php <username>\n";
    exit(1);
}

$username = $argv[1];

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';

echo "Creating/Promoting user '$username' to Administrator...\n";

// 1. Database Lookup
$db = get_db();
$stmt = $db->prepare('SELECT account_id FROM users WHERE username = :u COLLATE NOCASE');
$stmt->bindValue(':u', $username, SQLITE3_TEXT);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    echo "Error: User '$username' not found in local database (users table).\n";
    echo "Please allow the user to register via the website first.\n";
    exit(1);
}

$accId = sprintf('%08X', $row['account_id']);
echo "Found Account ID: $accId (Hex)\n";

// 2. Shell Execution
$cmd = "update-account $accId flags=ROOT";
echo "Executing: $cmd\n";

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

$response = @file_get_contents($url, false, stream_context_create($opts));

if ($response === false) {
    echo "Error: Failed to connect to Newserv API at $url\n";
    exit(1);
}

$json = json_decode($response, true);
if ($json && isset($json['result'])) {
    echo "Server Response: " . trim($json['result']) . "\n";
    if (stripos($json['result'], 'updated') !== false) {
        echo "SUCCESS: User '$username' is now a ROOT Administrator.\n";
    }
} else {
    echo "Error: " . ($json['error'] ?? 'Unknown error') . "\n";
}
?>
