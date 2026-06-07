<?php
/**
 * PSOBB API: Admin Special Item Delivery
 *
 * Allows administrators to queue hand-crafted item deliveries for specific players.
 * Uses the same item_string format as mission rewards (parse_and_drop_items).
 *
 * Actions: list, create, revoke
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
// LIST — GET ?action=list
// ----------------------------------------------------------------
if ($action === 'list') {
    $res = $db->query("
        SELECT sd.id, sd.recipient_id, sd.recipient_name, sd.item_name,
               sd.item_string, sd.admin_note, sd.status,
               sd.created_at, sd.redeemed_at,
               u.username AS created_by_username
        FROM special_deliveries sd
        LEFT JOIN users u ON u.account_id = sd.created_by
        ORDER BY sd.created_at DESC
    ");
    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = [
            'id'            => (int)$row['id'],
            'recipient_id'  => (int)$row['recipient_id'],
            'recipient_name'=> $row['recipient_name'],
            'item_name'     => $row['item_name'],
            'item_string'   => $row['item_string'],
            'admin_note'    => $row['admin_note'],
            'status'        => $row['status'],
            'created_by'    => $row['created_by_username'] ?? 'Unknown',
            'created_at'    => $row['created_at'],
            'redeemed_at'   => $row['redeemed_at'],
        ];
    }
    echo json_encode(['success' => true, 'deliveries' => $rows]);
    exit;
}

// ----------------------------------------------------------------
// CREATE — POST ?action=create  body: { username, item_name, item_string, admin_note? }
// ----------------------------------------------------------------
if ($action === 'create') {
    $username   = trim($input['username'] ?? '');
    $item_name  = trim($input['item_name'] ?? '');
    $item_string= trim($input['item_string'] ?? '');
    $admin_note = trim($input['admin_note'] ?? '');

    if (!$username || !$item_name || !$item_string) {
        http_response_code(400);
        echo json_encode(['error' => 'username, item_name, and item_string are required']);
        exit;
    }

    // Resolve account_id for the given username
    $stmt = $db->prepare("SELECT account_id, username FROM users WHERE username = :u COLLATE NOCASE LIMIT 1");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $res = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => "Player '$username' not found"]);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO special_deliveries
            (recipient_id, recipient_name, item_name, item_string, admin_note, created_by)
        VALUES (:rid, :rname, :iname, :istr, :note, :cby)
    ");
    $stmt->bindValue(':rid',   (int)$user['account_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':rname', $user['username'],         SQLITE3_TEXT);
    $stmt->bindValue(':iname', $item_name,                SQLITE3_TEXT);
    $stmt->bindValue(':istr',  $item_string,              SQLITE3_TEXT);
    $stmt->bindValue(':note',  $admin_note ?: null,       $admin_note ? SQLITE3_TEXT : SQLITE3_NULL);
    $stmt->bindValue(':cby',   $adminId,                  SQLITE3_INTEGER);
    $stmt->execute();

    $newId = $db->lastInsertRowID();
    echo json_encode([
        'success'        => true,
        'id'             => (int)$newId,
        'recipient_name' => $user['username'],
        'item_name'      => $item_name,
    ]);
    exit;
}

// ----------------------------------------------------------------
// REVOKE — POST ?action=revoke  body: { id }
// ----------------------------------------------------------------
if ($action === 'revoke') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Delivery ID required']);
        exit;
    }

    $stmt = $db->prepare("UPDATE special_deliveries SET status = 'revoked' WHERE id = :id AND status = 'pending'");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();

    if ($db->changes() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Delivery not found or already processed']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action. Supported: list, create, revoke']);
