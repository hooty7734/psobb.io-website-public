<?php
/**
 * Lightweight bounty change-detection endpoint.
 * Returns the mtime of the user's bounty marker file.
 * The cron touches this file when any bounty status changes.
 * No DB queries — just a stat() call on a tiny file.
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
start_secure_session();

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    echo json_encode(["ts" => 0]);
    exit;
}

$account_id = $_SESSION['user']['account_id'] ?? 0;
if (!$account_id) {
    echo json_encode(["ts" => 0]);
    exit;
}

$marker = __DIR__ . '/../db/.bounty_' . $account_id;
$ts = file_exists($marker) ? filemtime($marker) : 0;

echo json_encode(["ts" => $ts]);
