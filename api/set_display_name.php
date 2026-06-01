<?php
/**
 * PSOBB API: Set Display Name (Leaderboard Alias)
 * 
 * Allows authenticated users to set a public display name for leaderboards.
 * This prevents raw usernames (which are game login credentials) from being
 * exposed on public pages where they could be targeted for brute-force attacks.
 */
require_once 'config.php';
require_once 'db.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user']) || empty($_SESSION['user']['account_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$accountId = $_SESSION['user']['account_id'];
$input = json_decode(file_get_contents('php://input'), true);
$displayName = trim($input['display_name'] ?? '');

// Validation
if (empty($displayName)) {
    http_response_code(400);
    echo json_encode(["error" => "Display name cannot be empty."]);
    exit;
}

if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 20) {
    http_response_code(400);
    echo json_encode(["error" => "Display name must be between 2 and 20 characters."]);
    exit;
}

// Only allow alphanumeric, spaces, hyphens, underscores, and common symbols
if (!preg_match('/^[a-zA-Z0-9 _\-\.]+$/u', $displayName)) {
    http_response_code(400);
    echo json_encode(["error" => "Display name can only contain letters, numbers, spaces, hyphens, underscores, and periods."]);
    exit;
}

$db = get_db();

// Check uniqueness (case-insensitive)
$stmt = $db->prepare("SELECT account_id FROM users WHERE LOWER(display_name) = LOWER(:name) AND account_id != :aid");
$stmt->bindValue(':name', $displayName, SQLITE3_TEXT);
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$result = $stmt->execute()->fetchArray();

if ($result) {
    http_response_code(409);
    echo json_encode(["error" => "That display name is already taken."]);
    exit;
}

// Save
$stmt = $db->prepare("UPDATE users SET display_name = :name WHERE account_id = :aid OR username = :username");
$stmt->bindValue(':name', $displayName, SQLITE3_TEXT);
$stmt->bindValue(':aid', $accountId, SQLITE3_INTEGER);
$stmt->bindValue(':username', $_SESSION['user']['username'], SQLITE3_TEXT);
$stmt->execute();

echo json_encode([
    "success" => true,
    "display_name" => $displayName,
    "message" => "Display name updated to: {$displayName}"
]);
