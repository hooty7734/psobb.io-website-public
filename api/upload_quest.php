<?php
require_once 'config.php';
require_once 'db.php';

// Helper to clean output
if (ob_get_length()) ob_clean();

// Start Session
start_secure_session();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['user']['username'];

// Validate Request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['quest_file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['quest_file'];
$filename = $file['name'];
$tmp_path = $file['tmp_name'];
$size = $file['size'];

// Validation
$allowed_extensions = ['qst'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file extension. Only .qst allowed.']);
    exit;
}

if ($size > 1024 * 1024 * 200) { // 200MB limit
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Max 200MB.']);
    exit;
}

// Destination Path
// Go up one level from api/ to root, then to quests/uploads
$upload_dir = __DIR__ . '/../quests/uploads/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $username) . '/';

if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload directory']);
        exit;
    }
}

$destination = $upload_dir . basename($filename);

if (move_uploaded_file($tmp_path, $destination)) {
    echo json_encode([
        'success' => true,
        'message' => 'Quest uploaded successfully',
        'path' => $destination
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file']);
}
?>
