<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
require_once 'db.php';
start_secure_session();

header('Content-Type: text/plain');

if (empty($_SESSION['user'])) { echo "Not logged in\n"; exit; }
$username = strtolower($_SESSION['user']['username'] ?? '');

echo "Username: $username\n\n";

$playersDir = '/opt/newserv/system/players/';

// Check all possible shared bank filenames
$candidates = [
    "shared_bank_{$username}.psobank",
    "shared_bank_" . strtoupper($username) . ".psobank",
    "shared_bank_{$username}.psobb",
    "account_{$username}_shared.psobank",
];

echo "=== Checking shared bank files ===\n";
foreach ($candidates as $fn) {
    $path = $playersDir . $fn;
    echo "$fn => " . (file_exists($path) ? "EXISTS (" . filesize($path) . " bytes)" : "NOT FOUND") . "\n";
}

echo "\n=== All files matching '*shared*' or '*bank*' for user ===\n";
if (is_dir($playersDir)) {
    $files = scandir($playersDir);
    foreach ($files as $f) {
        if (stripos($f, $username) !== false || stripos($f, 'shared') !== false) {
            if (stripos($f, 'bank') !== false || stripos($f, 'shared') !== false) {
                $fullPath = $playersDir . $f;
                echo "$f => " . filesize($fullPath) . " bytes\n";
            }
        }
    }
}

echo "\n=== All files for user ===\n";
if (is_dir($playersDir)) {
    $files = scandir($playersDir);
    foreach ($files as $f) {
        if (stripos($f, $username) !== false) {
            $fullPath = $playersDir . $f;
            echo "$f => " . filesize($fullPath) . " bytes\n";
        }
    }
}
?>
