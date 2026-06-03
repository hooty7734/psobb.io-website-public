<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
start_secure_session();

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$account_id = (int)($body['account_id'] ?? 0);
$from_name  = trim($body['from_name'] ?? "Hunter's Guild");
$message    = trim($body['message']   ?? '');

if ($account_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid account ID.']);
    exit;
}
if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
    exit;
}

send_personal_mail($account_id, $from_name, $message);

echo json_encode(['success' => true, 'message' => "Test mail dispatched to account ID $account_id."]);
