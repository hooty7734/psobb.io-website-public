<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
start_secure_session();

if (empty($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

send_personal_mail(
    (int)$_SESSION['user']['account_id'],
    "Hunter's Guild",
    "This is a test notification.\nYour in-game mail is working correctly!"
);

echo json_encode(['success' => true]);
