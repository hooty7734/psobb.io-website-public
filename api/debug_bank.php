<?php
/**
 * Debug bank parsing — dump raw hex of bank data and compare sources
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';
start_secure_session();

header('Content-Type: text/plain');

if (empty($_SESSION['user'])) {
    echo "Not logged in\n";
    exit;
}

$username = strtolower($_SESSION['user']['username'] ?? '');
$slot = intval($_GET['slot'] ?? 0);

echo "Username: $username\n";
echo "Slot: $slot\n\n";

$playersDir = '/opt/newserv/system/players/';
if (!is_dir($playersDir)) {
    $playersDir = __DIR__ . '/../../newserv/system/players/';
}

// Helper to resolve player files case-insensitively
function resolve_player_file_dbg($dir, $filename) {
    $fullPath = $dir . $filename;
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $f) {
            if (strcasecmp($f, $filename) === 0) {
                return $dir . $f;
            }
        }
    }
    return $fullPath;
}

echo "=== PSOBANK FILE ===\n";
$psobankPath = resolve_player_file_dbg($playersDir, "player_{$username}_{$slot}.psobank");
echo "Path: $psobankPath\n";
echo "Exists: " . (file_exists($psobankPath) ? 'YES' : 'NO') . "\n";

if (file_exists($psobankPath)) {
    $bankData = file_get_contents($psobankPath);
    echo "Size: " . strlen($bankData) . " bytes\n";
    $numItems = unpack('V', substr($bankData, 0, 4))[1];
    $meseta = unpack('V', substr($bankData, 4, 4))[1];
    echo "Num items: $numItems\n";
    echo "Meseta: $meseta\n";
    echo "First 64 bytes hex: " . bin2hex(substr($bankData, 0, 64)) . "\n\n";
    
    echo "Items from .psobank:\n";
    for ($i = 0; $i < min(10, $numItems); $i++) {
        $offset = 8 + $i * 24;
        $itemHex = bin2hex(substr($bankData, $offset, 20));
        $amount = unpack('v', substr($bankData, $offset + 20, 2))[1];
        $present = unpack('v', substr($bankData, $offset + 22, 2))[1];
        echo "  Item $i: hex=$itemHex amount=$amount present=$present\n";
    }
} else {
    echo "NO .psobank file found!\n";
}

echo "\n=== EMBEDDED BANK in .psochar ===\n";
$psocharPath = resolve_player_file_dbg($playersDir, "player_{$username}_{$slot}.psochar");
echo "Path: $psocharPath\n";
echo "Exists: " . (file_exists($psocharPath) ? 'YES' : 'NO') . "\n";

if (file_exists($psocharPath)) {
    $charData = file_get_contents($psocharPath);
    echo "Size: " . strlen($charData) . " bytes\n";
    
    // Bank embedded at offset 1792 (0x700)
    $bankBlock = substr($charData, 1792, 4808);
    $numItems = unpack('V', substr($bankBlock, 0, 4))[1];
    $meseta = unpack('V', substr($bankBlock, 4, 4))[1];
    echo "Num items: $numItems\n";
    echo "Meseta: $meseta\n";
    echo "First 64 bytes hex: " . bin2hex(substr($bankBlock, 0, 64)) . "\n\n";
    
    echo "Items from embedded bank:\n";
    for ($i = 0; $i < min(10, $numItems); $i++) {
        $offset = 8 + $i * 24;
        $itemHex = bin2hex(substr($bankBlock, $offset, 20));
        $amount = unpack('v', substr($bankBlock, $offset + 20, 2))[1];
        $present = unpack('v', substr($bankBlock, $offset + 22, 2))[1];
        echo "  Item $i: hex=$itemHex amount=$amount present=$present\n";
    }
}
?>
