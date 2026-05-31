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

    // Calculate total account playtime across all 4 character files
    $playersDir = '/opt/newserv/system/players/';
    if (!is_dir($playersDir)) {
        $playersDir = __DIR__ . '/../../newserv/system/players/';
    }
    
    $total_play_time = 0;
    $username = strtolower(trim($foundAccount['username'] ?? $_SESSION['user']['username'] ?? ''));
    $usernames = [$username];
    if (empty($username) && isset($foundAccount['BBLicenses']) && is_array($foundAccount['BBLicenses']) && count($foundAccount['BBLicenses']) > 0) {
        $usernames[] = strtolower(trim($foundAccount['BBLicenses'][0]['UserName'] ?? ''));
    }
    
    foreach ($usernames as $u) {
        $u = strtolower(trim($u));
        if (empty($u)) continue;
        
        for ($slot = 0; $slot < 20; $slot++) {
            $charFilename = "player_{$u}_{$slot}.psochar";
            $charPath = $playersDir . $charFilename;
            if (!file_exists($charPath)) {
                if (is_dir($playersDir)) {
                    $files = scandir($playersDir);
                    foreach ($files as $f) {
                        if (strcasecmp($f, $charFilename) === 0) {
                            $charPath = $playersDir . $f;
                            break;
                        }
                    }
                }
            }
            if (file_exists($charPath)) {
                $charData = @file_get_contents($charPath);
                if ($charData !== false && strlen($charData) >= (8 + 0x04E8 + 4)) {
                    $playTime = unpack('V', substr($charData, 8 + 0x04E8, 4))[1];
                    $total_play_time += $playTime;
                }
            }
        }
    }
    
    $foundAccount['total_play_time_hours'] = round($total_play_time / 3600, 1);

    echo json_encode($foundAccount);
} else {
    // Account ID from session not found in current server list
    http_response_code(404);
    echo json_encode(["error" => "Account not found"]);
}
?>
