<?php
// Suppress warnings (like PHP 8.x http_response_header deprecation)
// to prevent JSON response corruption
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'db.php';
// Clean any output (whitespace) from included files
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$username = strtolower(trim($input['username'] ?? ''));
$password = trim($input['password'] ?? '');
$email = trim($input['email'] ?? '');

// Validation
if (empty($username) || empty($password) || empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields required.']);
    exit;
}
if (strlen($username) > 16 || strlen($password) > 16) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password must be 16 characters or fewer']);
    exit;
}
if (preg_match('/\s/', $username) || preg_match('/\s/', $password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password cannot contain spaces']);
    exit;
}
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
     http_response_code(400);
     echo json_encode(['error' => 'Username contains invalid characters']);
     exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
     http_response_code(400);
     echo json_encode(['error' => 'Invalid email address']);
     exit;
}

// Database Check (Uniqueness)
$db = get_db();
$stmt = $db->prepare('SELECT id FROM users WHERE email = :email OR username = :username');
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$res = $stmt->execute();
if ($res->fetchArray()) {
    http_response_code(400);
    echo json_encode(['error' => 'Username or Email already registered.']);
    exit;
}

// Helper to run shell command
function run_shell($cmd) {
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
    $ctx = stream_context_create($opts);
    // Return result directly, do not access deprecated $http_response_header
    return file_get_contents($url, false, $ctx);
}

// 1. Create Account Container
$res = run_shell('add-account flags=NONE');
$json = json_decode($res, true);

if (!$json || !isset($json['result']) || !preg_match('/Account ([0-9A-Fa-f]+) added/i', $json['result'], $m)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to initialize account on server.']);
    exit;
}

$accountIdHex = $m[1];
$accountIdInt = hexdec($accountIdHex);

// 2. Add License
$res2 = run_shell("add-license $accountIdHex BB $username $password");
$json2 = json_decode($res2, true);

if (!$json2 || !isset($json2['result']) || stripos($json2['result'], 'updated') === false) {
    // Failure (likely duplicate username on server side, unhandled by DB check logic?)
    run_shell("delete-account $accountIdHex");
    
    $errMsg = 'Registration failed (Username check).';
    if (isset($json2['error'])) $errMsg = $json2['error'];
    http_response_code(400);
    echo json_encode(['error' => $errMsg]);
    exit;
}

// 3. Insert into DB
$lang_pref = $_COOKIE['psobb_lang'] ?? 'en';
$stmt = $db->prepare('INSERT INTO users (username, email, account_id, language) VALUES (:username, :email, :accId, :lang)');
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$stmt->bindValue(':accId', $accountIdInt, SQLITE3_INTEGER);
$stmt->bindValue(':lang', $lang_pref, SQLITE3_TEXT);

try {
    $stmt->execute();
} catch (Exception $e) {
    // Rollback: delete account we just created
    run_shell("delete-account $accountIdHex");
    http_response_code(500);
    echo json_encode(['error' => 'Database error during registration.']);
    exit;
}

if ($lang_pref === 'jp') {
    send_email($email, "PSOBB.IOへようこそ", "$username さん、\n\nPSOBB.IOへようこそ！アカウントが正常に作成されました。\n\nアカウント情報は以下の通りです：\nユーザー名: $username\nギルドカード: $accountIdInt\n\nアカウント管理はこちら: https://psobb.io/login.php\n\n良い狩りを！\nPSOBB.IO チーム");
} else {
    send_email($email, "Welcome to PSOBB.IO", "Hello $username,\n\nWelcome to PSOBB.IO! Your account has been created successfully.\n\nHere are your account details:\nUsername: $username\nGuild Card: $accountIdInt\n\nYou can manage your account at: https://psobb.io/login.php\n\nHappy Hunting,\nPSOBB.IO Team");
}

echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
