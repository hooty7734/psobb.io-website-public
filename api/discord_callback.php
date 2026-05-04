<?php
require_once 'config.php';
require_once 'db.php';
start_secure_session();

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['code']) || !isset($_GET['state']) || $_GET['state'] !== $_SESSION['discord_state']) {
    die("Invalid OAuth State or request. Please try linking again.");
}

$code = $_GET['code'];

// Exchange code for token
$token_url = "https://discord.com/api/oauth2/token";
$data = [
    'client_id' => $DISCORD_CLIENT_ID,
    'client_secret' => $DISCORD_CLIENT_SECRET,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $DISCORD_REDIRECT_URI
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    ]
];
$context  = stream_context_create($options);
$response = @file_get_contents($token_url, false, $context);

if ($response === FALSE) {
    die("Failed to exchange Discord authorization code. Ensure your client secret and redirect URI match in config.php.");
}

$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    die("Discord didn't provide an access token.");
}

$access_token = $token_data['access_token'];

// Fetch user info
$user_url = "https://discord.com/api/users/@me";
$user_options = [
    'http' => [
        'header' => "Authorization: Bearer {$access_token}\r\n",
        'method' => 'GET'
    ]
];
$user_context = stream_context_create($user_options);
$user_response = @file_get_contents($user_url, false, $user_context);

if ($user_response === FALSE) {
    die("Failed to fetch Discord user information.");
}

$discord_user = json_decode($user_response, true);

if (!isset($discord_user['id'])) {
    die("Invalid Discord user data received.");
}

$discord_id = $discord_user['id'];
$username = $_SESSION['user']['username'];

// Update database
try {
    $db = get_db();
    
    // Check if the user exists in the web database (legacy/in-game accounts might not)
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    
    if (!$row) {
        // Create a stub row so we can store their Discord ID integration
        $ins = $db->prepare("INSERT INTO users (username, email, account_id, discord_id) VALUES (:u, :e, :aid, :did)");
        $ins->bindValue(':u', $username, SQLITE3_TEXT);
        // Use a dummy email for legacy accounts to satisfy the UNIQUE NOT NULL constraint
        $ins->bindValue(':e', $username . "_legacy@psobb.io", SQLITE3_TEXT);
        $ins->bindValue(':aid', $_SESSION['user']['account_id'], SQLITE3_INTEGER);
        $ins->bindValue(':did', $discord_id, SQLITE3_TEXT);
        $ins->execute();
    } else {
        // Update existing row
        $stmt = $db->prepare("UPDATE users SET discord_id = :discord_id WHERE username = :username");
        $stmt->bindValue(':discord_id', $discord_id, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    // Unset the state to prevent replay
    unset($_SESSION['discord_state']);

    // Redirect to frontend with success flag
    header("Location: ../login.php?discord_linked=1");
    exit;
} catch (Exception $e) {
    die("Database error while linking Discord ID.");
}
?>
