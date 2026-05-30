<?php
/**
 * PSOBB API: Download Character Data (ZIP)
 * 
 * Packages the player's binary .psochar and .psobank files into a ZIP archive
 * and streams it as a download. Also includes the shared bank if it exists.
 * 
 * Only the authenticated user's own files can be downloaded.
 */
require_once __DIR__ . '/config.php';

if (ob_get_length()) ob_clean();
start_secure_session();

// Verify login
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$accountId = (int)$_SESSION['user']['account_id'];
$slot = isset($_GET['slot']) ? max(0, min(3, (int)$_GET['slot'])) : 0;

// Resolve BB username
$username = '';
if (isset($_SESSION['user']['BBLicenses']) && is_array($_SESSION['user']['BBLicenses']) && count($_SESSION['user']['BBLicenses']) > 0) {
    $username = $_SESSION['user']['BBLicenses'][0]['UserName'] ?? '';
}
if (empty($username)) {
    $username = $_SESSION['user']['LastPlayerName'] ?? $_SESSION['user']['username'] ?? '';
}
$username = strtolower(trim($username));

if (empty($username)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Could not resolve your username. Please log in-game first."]);
    exit;
}

// Locate player files directory
$playersDir = '/opt/newserv/system/players/';
if (!is_dir($playersDir)) {
    $playersDir = __DIR__ . '/../../newserv/system/players/';
}

// Helper to resolve files case-insensitively
function resolve_file($dir, $filename) {
    $fullPath = $dir . $filename;
    if (file_exists($fullPath)) return $fullPath;
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if (strcasecmp($f, $filename) === 0) {
                return $dir . $f;
            }
        }
    }
    return null;
}

// Resolve all relevant files for this slot
$psocharFile = resolve_file($playersDir, "player_{$username}_{$slot}.psochar");
$psobankFile = resolve_file($playersDir, "player_{$username}_{$slot}.psobank");
$sharedBankFile = resolve_file($playersDir, "shared_bank_{$username}.psobank");

if (!$psocharFile) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Character file for Slot " . ($slot + 1) . " not found. Please create a character in-game first."]);
    exit;
}

// Read character name from the binary for the filename
$charName = "Slot" . ($slot + 1);
$charData = @file_get_contents($psocharFile);
if ($charData !== false && strlen($charData) >= 1284) {
    // Display block starts at offset 852, name at +116 within it (UTF-16LE, 32 bytes)
    $nameBytes = substr($charData, 852 + 116, 32);
    $parsed = mb_convert_encoding($nameBytes, 'UTF-8', 'UTF-16LE');
    $parsed = trim(str_replace("\x00", "", $parsed));
    if (!empty($parsed)) {
        $charName = $parsed;
    }
}

// Build ZIP
$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'psobb_dl_');

if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Failed to create archive."]);
    exit;
}

// Add character file
$zip->addFile($psocharFile, basename($psocharFile));

// Add character bank if it exists
if ($psobankFile) {
    $zip->addFile($psobankFile, basename($psobankFile));
}

// Add shared bank if it exists
if ($sharedBankFile) {
    $zip->addFile($sharedBankFile, basename($sharedBankFile));
}

// Add a readme for context
$readme = "PSOBB.io Character Export\n";
$readme .= "========================\n\n";
$readme .= "Character: {$charName}\n";
$readme .= "Slot: " . ($slot + 1) . "\n";
$readme .= "Username: {$username}\n";
$readme .= "Exported: " . date('Y-m-d H:i:s T') . "\n\n";
$readme .= "Files included:\n";
$readme .= "  - " . basename($psocharFile) . " (Character data)\n";
if ($psobankFile) $readme .= "  - " . basename($psobankFile) . " (Character bank)\n";
if ($sharedBankFile) $readme .= "  - " . basename($sharedBankFile) . " (Shared bank)\n";
$readme .= "\nThese are raw binary game files compatible with newserv/Tethealla player data formats.\n";

$zip->addFromString("README.txt", $readme);
$zip->close();

// Stream the ZIP
$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $charName);
$dlFilename = "psobb_{$safeName}_slot" . ($slot + 1) . ".zip";

header('Content-Type: application/zip');
header("Content-Disposition: attachment; filename=\"{$dlFilename}\"");
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache, no-store, must-revalidate');

readfile($tmpFile);
unlink($tmpFile);
exit;
?>
