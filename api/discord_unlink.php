<?php
require_once 'config.php';
require_once 'db.php';
start_secure_session();

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php?error=session_expired");
    exit;
}

$username = $_SESSION['user']['username'];

try {
    $db = get_db();
    $stmt = $db->prepare("UPDATE users SET discord_id = NULL WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->execute();
    
    // Redirect to frontend with unlink flag
    header("Location: ../login.php?discord_unlinked=1");
    exit;
} catch (Exception $e) {
    die("Database error while unlinking Discord ID.");
}
?>
