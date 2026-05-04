<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
start_secure_session();

$lang = $_GET['lang'] ?? 'en';
if (in_array($lang, ['en', 'jp'])) {
    setcookie('psobb_lang', $lang, time() + 31536000, '/');
    
    // Sync with DB if logged in
    if (!empty($_SESSION['user']['account_id'])) {
        $db = get_db();
        $stmt = $db->prepare("UPDATE users SET language = :lang WHERE account_id = :accId");
        $stmt->bindValue(':lang', $lang, SQLITE3_TEXT);
        $stmt->bindValue(':accId', $_SESSION['user']['account_id'], SQLITE3_INTEGER);
        $stmt->execute();
    }
}
// Only redirect to local paths to prevent open redirect
$referer = $_SERVER['HTTP_REFERER'] ?? '/';
$parsed = parse_url($referer);
$redirect = (isset($parsed['path']) && strpos($parsed['path'], '/') === 0) ? $parsed['path'] : '/';
header("Location: " . $redirect);

exit;
?>
