<?php
/**
 * PSOBB API: Admin Bot Token Management
 *
 * Allows administrators to create, list, and revoke bot API tokens.
 * Raw token values are shown ONCE at creation time and are never stored —
 * only a bcrypt hash is persisted in the database.
 */
require_once 'config.php';
require_once 'db.php';

if (ob_get_length()) ob_clean();
start_secure_session();
header('Content-Type: application/json');

verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

$adminId = (int)$_SESSION['user']['account_id'];
$db = get_db();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// ----------------------------------------------------------------
// LIST  GET ?action=list
// ----------------------------------------------------------------
if ($action === 'list') {
    $res = $db->query("
        SELECT bt.id, bt.name, bt.created_at, bt.last_used_at, bt.expires_at,
               bt.revoked, u.username AS created_by_username
        FROM bot_tokens bt
        LEFT JOIN users u ON u.account_id = bt.created_by
        ORDER BY bt.created_at DESC
    ");
    $tokens = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $tokens[] = [
            'id'                  => (int)$row['id'],
            'name'                => $row['name'],
            'created_by'          => $row['created_by_username'] ?? 'Unknown',
            'created_at'          => $row['created_at'],
            'last_used_at'        => $row['last_used_at'],
            'expires_at'          => $row['expires_at'],
            'revoked'             => (bool)$row['revoked'],
            'is_expired'          => $row['expires_at'] && strtotime($row['expires_at']) < time(),
        ];
    }
    echo json_encode(['success' => true, 'tokens' => $tokens]);
    exit;
}

// ----------------------------------------------------------------
// CREATE  POST ?action=create  body: { name, expires_days? }
// ----------------------------------------------------------------
if ($action === 'create') {
    $name = trim($input['name'] ?? '');
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token name is required']);
        exit;
    }
    if (strlen($name) > 80) {
        http_response_code(400);
        echo json_encode(['error' => 'Token name must be 80 characters or fewer']);
        exit;
    }

    $expiresDays = isset($input['expires_days']) ? (int)$input['expires_days'] : null;
    $expiresAt   = ($expiresDays && $expiresDays > 0)
        ? date('Y-m-d H:i:s', strtotime("+{$expiresDays} days"))
        : null;

    // Generate a cryptographically secure random token: psobb_<40 hex chars>
    $rawToken  = 'psobb_' . bin2hex(random_bytes(20));
    $tokenHash = password_hash($rawToken, PASSWORD_BCRYPT, ['cost' => 10]);

    $stmt = $db->prepare("
        INSERT INTO bot_tokens (name, token_hash, created_by, expires_at)
        VALUES (:name, :hash, :cby, :exp)
    ");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':hash', $tokenHash, SQLITE3_TEXT);
    $stmt->bindValue(':cby',  $adminId, SQLITE3_INTEGER);
    $stmt->bindValue(':exp',  $expiresAt, $expiresAt ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->execute();

    $newId = $db->lastInsertRowID();

    echo json_encode([
        'success'    => true,
        'id'         => (int)$newId,
        'name'       => $name,
        'token'      => $rawToken,   // ← shown ONCE, never stored in plaintext
        'expires_at' => $expiresAt,
        'warning'    => 'Copy this token now — it will never be shown again.',
    ]);
    exit;
}

// ----------------------------------------------------------------
// REVOKE  POST ?action=revoke  body: { id }
// ----------------------------------------------------------------
if ($action === 'revoke') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Token ID required']);
        exit;
    }

    $stmt = $db->prepare("UPDATE bot_tokens SET revoked = 1 WHERE id = :id AND revoked = 0");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();

    if ($db->changes() > 0) {
        echo json_encode(['success' => true, 'message' => 'Token revoked']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Token not found or already revoked']);
    }
    exit;
}

// ----------------------------------------------------------------
// DELETE  POST ?action=delete  body: { id }  (permanent purge)
// ----------------------------------------------------------------
if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Token ID required']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM bot_tokens WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action. Supported: list, create, revoke, delete']);
