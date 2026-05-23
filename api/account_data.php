<?php
require_once 'config.php';
// Helper to clean output
if (ob_get_length()) ob_clean();

// Start Session securely
start_secure_session();
header('Content-Type: application/json');

// Check if user is logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$targetAccountId = $_SESSION['user']['account_id'];

// Fetch all accounts from newserv
$url = $NEWSERV_API_URL . "/y/accounts";
$data = @file_get_contents($url);

if ($data === FALSE) {
    http_response_code(500);
    echo json_encode(["error" => "Server offline"]);
    exit;
}

$accounts = json_decode($data, true);
$foundAccount = null;

if (is_array($accounts)) {
    foreach ($accounts as $account) {
        // Check if this account matches the session account ID
        if (isset($account['AccountID']) && $account['AccountID'] == $targetAccountId) {
            $foundAccount = $account;
            break;
        }
    }
}

if ($foundAccount) {
    // Sanitize credentials from response
    if (isset($foundAccount['BBLicenses'])) {
        foreach ($foundAccount['BBLicenses'] as &$license) {
            unset($license['Password']);
        }
    }
    if (isset($foundAccount['GCLicenses'])) {
        foreach ($foundAccount['GCLicenses'] as &$license) {
            unset($license['Password']);
        }
    }
    
    // Check Admin Flags again to ensure freshness
    $flags = $foundAccount['flags'] ?? 0;
    // 0xFF = Administrator, 0x7FFFFFFF = Root
    // Checking for any admin-like bits (KICK/BAN/SILENCE/MOD/ADMIN/ROOT)
    // Common masks: MODERATOR (0x07), ADMINISTRATOR (0xFF), ROOT (0x7FFFFFFF)
    $isAdmin = ($flags & 0x07) !== 0;     
    $foundAccount['isAdmin'] = $isAdmin;

    echo json_encode($foundAccount);
} else {
    // Account ID from session not found in current server list
    http_response_code(404);
    echo json_encode(["error" => "Account not found"]);
}
?>
