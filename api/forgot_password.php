<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
require_once 'db.php';
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

$db = get_db();

// Verify Table Exists (Lazy Init)
$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    token TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");


// Check if email exists (Case Insensitive)
$stmt = $db->prepare("SELECT username, email, language FROM users WHERE email = :e COLLATE NOCASE");
$stmt->bindValue(':e', $email);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    // Return success to prevent email enumeration
    echo json_encode(['success' => true, 'message' => 'If this email exists, a reset link has been sent.']);
    exit;
}

$username = strtolower($row['username']);
$token = bin2hex(random_bytes(32));

// Store Token
$stmt = $db->prepare("INSERT INTO password_resets (email, token) VALUES (:e, :t)");
$stmt->bindValue(':e', $email);
$stmt->bindValue(':t', $token);
$stmt->execute();

// Send Email
// Construct Link
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$link = "$protocol://$host/reset_password.php?token=$token";

$lang_pref = $row['language'] ?? 'en';
if ($lang_pref === 'jp') {
    $subject = "パスワード再設定リクエスト - PSOBB.IO";
    $message = "$username さん、\n\nPSOBB.IOアカウントのパスワード再設定リクエストを受け付けました。\n\n以下のリンクをクリックして新しいパスワードを設定してください：\n$link\n\nこのリンクは1時間有効です。\n\n心当たりがない場合は、このメールを無視してください。";
} else {
    $subject = "Password Reset Request - PSOBB.IO";
    $message = "Hello $username,\n\nWe received a request to reset your password for your PSOBB.IO account.\n\nClick the link below to verify your email and set a new password:\n$link\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";
}

if (send_email($email, $subject, $message)) {
    echo json_encode(['success' => true, 'message' => 'Reset link sent to your email. Check your spam folder.']);
} else {
    echo json_encode(['error' => 'Failed to send email. Server configuration issue.']);
}
?>
