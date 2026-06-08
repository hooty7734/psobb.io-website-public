<?php
/**
 * Admin: Username Search
 * Searches the users table by username prefix and returns matches.
 */
require_once 'config.php';
require_once 'db.php';
start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$db   = get_db();
$stmt = $db->prepare("
    SELECT username, account_id, discord_id
    FROM users
    WHERE username LIKE :q COLLATE NOCASE
    ORDER BY username ASC
    LIMIT 15
");
$stmt->bindValue(':q', $q . '%', SQLITE3_TEXT);
$res = $stmt->execute();

$results = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $results[] = [
        'username'   => $row['username'],
        'account_id' => $row['account_id'],
        'linked'     => !empty($row['discord_id']),
    ];
}

echo json_encode($results);
