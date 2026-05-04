<?php
/**
 * --------------------------------------------------------------------------
 * PSOBB Account Authentication Endpoint
 * --------------------------------------------------------------------------
 * This endpoint processes standard web-based credential logins. Since the system
 * does not traditionally store passwords in a separate web database, it authenticates
 * directly against the live game server's (NewServ) internal state.
 *
 * It validates credentials, initiates secure PHP sessions, pulls linked Discord IDs 
 * from the web DB, and handles automatic login streak updates.
 */
require_once 'config.php';

// Clear any buffered output ahead of JSON generation to prevent header corruption
if (ob_get_length()) ob_clean();

// Secure session configuration: Prevent JavaScript from hijacking the PHPSESSID cookie
start_secure_session();
header('Content-Type: application/json');

// Parse incoming raw JSON body (e.g. from the dashboard's fetch() call)
$input = json_decode(file_get_contents('php://input'), true);
$username = strtolower($input['username'] ?? '');
$password = $input['password'] ?? '';

// Fast fail on malformed requests
if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(["error" => "Missing username or password"]);
    exit;
}

try {
    require_once 'db.php';
    $db = get_db();
    
    // ----------------------------------------------------------------------
    // Game Server Verification
    // ----------------------------------------------------------------------
    // We request the full account registry actively loaded by NewServ. 
    // This removes the need for database syncing; if they exist in-game, they exist here.
    $url = $NEWSERV_API_URL . "/y/accounts";
    $data = @file_get_contents($url);

    if ($data === FALSE) {
        throw new Exception("Server offline (API unreachable)");
    }

    $accounts = json_decode($data, true);
    $user_account = null;

    // Search through the nested license registry to find a matching Blue Burst (BB) credential set
    if (is_array($accounts)) {
        foreach ($accounts as $account) {
            if (isset($account['BBLicenses']) && is_array($account['BBLicenses'])) {
                foreach ($account['BBLicenses'] as $license) {
                    if ((strtolower($license['UserName'] ?? '')) === $username && ($license['Password'] ?? '') === $password) {
                        $user_account = $account;
                        $user_account['username'] = $username; 
                        break 2; // Break out of both loops immediately upon match
                    }
                }
            }
        }
    }

    if ($user_account) {
        // ----------------------------------------------------------------------
        // Session Initialization
        // ----------------------------------------------------------------------
        // Check Admin Flags. The lowest bit (0x07) in NewServ dictates basic GM/Admin roles.
        $flags = $user_account['Flags'] ?? 0;
        $isAdmin = ($flags & 0x07) !== 0; 
        
        // Populate the secure server-side session
        $_SESSION['user'] = [
            'username' => $username,
            'account_id' => $user_account['AccountID'],
            'is_admin' => $isAdmin
        ];

        // ----------------------------------------------------------------------
        // Response Sanitization
        // ----------------------------------------------------------------------
        // NEVER leak passwords back to the client, even their own. We strip them 
        // from the game server's payload before forwarding it to the web dashboard.
        if (isset($user_account['BBLicenses'])) {
            foreach ($user_account['BBLicenses'] as &$license) unset($license['Password']);
        }
        if (isset($user_account['GCLicenses'])) {
            foreach ($user_account['GCLicenses'] as &$license) unset($license['Password']);
        }

        $user_account['isAdmin'] = $isAdmin;
        
        // ----------------------------------------------------------------------
        // Supplementary Web Data
        // ----------------------------------------------------------------------
        // Fetch external integrations (like Discord OAuth limits) from the local SQLite DB
        $stmt = $db->prepare("SELECT discord_id, language FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
        $user_account['discord_id'] = $row ? $row['discord_id'] : null;
        
        $lang = $row && $row['language'] ? $row['language'] : 'en';
        setcookie('psobb_lang', $lang, time() + 31536000, '/');

        // Record website login for the player's daily streak tracking.
        // This ensures they don't lose their streak just because they didn't log into the game client.
        $streak_stmt = $db->prepare("INSERT OR IGNORE INTO daily_logins (account_id, login_date) VALUES (:aid, :date)");
        $streak_stmt->bindValue(':aid', $user_account['AccountID'], SQLITE3_INTEGER);
        $streak_stmt->bindValue(':date', date('Y-m-d'), SQLITE3_TEXT);
        $streak_stmt->execute();

        // Transmit sanitized account blob back to the frontend
        echo json_encode($user_account);
    } else {
        // Fallback for failed authentication
        http_response_code(401);
        echo json_encode([
            "error" => "Invalid credentials"
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    // Generic system error trap (e.g. DB locks or unhandled API crashes)
    echo json_encode(["error" => "System error: " . $e->getMessage()]);
    exit;
}
?>
