<?php
require_once 'config.php';
start_secure_session();
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once 'db.php';

header('Content-Type: application/json');

try {
    $db = get_db();
    
    // 1. Fetch SQLite data
    $results = $db->query("SELECT id, username, email, account_id, created_at, discord_id FROM users");
    $db_users = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        if (!empty($row['account_id'])) {
            $db_users[$row['account_id']] = $row;
        }
    }

    // 2. Fetch Newserv Data
    $url = $NEWSERV_API_URL . "/y/accounts";
    // Increase timeout for the API request
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $data = @file_get_contents($url, false, $ctx);
    
    $accounts = [];
    if ($data !== FALSE) {
        $parsed = json_decode($data, true);
        if (is_array($parsed)) {
            foreach ($parsed as $acc) {
                // Strip passwords
                if (isset($acc['BBLicenses']) && is_array($acc['BBLicenses'])) {
                    foreach ($acc['BBLicenses'] as &$lic) {
                        unset($lic['Password']);
                    }
                }
                if (isset($acc['GCLicenses']) && is_array($acc['GCLicenses'])) {
                    foreach ($acc['GCLicenses'] as &$lic) {
                        unset($lic['Password']);
                    }
                }
                
                // Merge SQLite data if available
                $accId = (isset($acc['AccountID']) && is_numeric($acc['AccountID'])) ? (int)$acc['AccountID'] : null;
                if ($accId !== null && isset($db_users[$accId])) {
                    $acc['WebEmail'] = $db_users[$accId]['email'];
                    $acc['WebCreatedAt'] = $db_users[$accId]['created_at'];
                    $acc['WebUsername'] = $db_users[$accId]['username'];
                    $acc['WebDiscordID'] = $db_users[$accId]['discord_id'];
                } else {
                    $acc['WebEmail'] = null;
                    $acc['WebCreatedAt'] = null;
                    $acc['WebUsername'] = null;
                    $acc['WebDiscordID'] = null;
                }

                $accounts[] = $acc;
            }
        }
    } else {
        // Fallback: Just return the sqlite users if newserv is offline entirely
        foreach ($db_users as $accId => $dbu) {
            $accounts[] = [
                'AccountID' => $accId,
                'WebEmail' => $dbu['email'],
                'WebCreatedAt' => $dbu['created_at'],
                'WebUsername' => $dbu['username'],
                'Flags' => 0,
                'LastPlayerName' => 'Unknown (Server Offline)',
                'BBLicenses' => [['UserName' => $dbu['username']]]
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'accounts' => $accounts
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
