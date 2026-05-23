<?php
/**
 * PSOBB.io - Wiki Agent Ingest (wiki_ingest.php)
 * 
 * Secure wrapper to accept the Python Wiki Agent's JSON POST requests 
 * and directly append Markdown data to the live Docsify files.
 */

// 1. Define your secure webhook token here (Must match 'CHANGE_ME' in wiki_agent_cli.py)
define('AGENT_SECRET', 'CHANGE_ME');

// 2. Setup Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// 3. Authenticate the Python script's POST request
$headers = getallheaders();
$auth = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if ($auth !== 'Bearer ' . AGENT_SECRET) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// 4. Capture and validate the JSON payload
$json_data = file_get_contents("php://input");
$decoded = json_decode($json_data, true);

if (!$decoded || !isset($decoded['category']) || !isset($decoded['content'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload or missing parameters"]);
    exit;
}

$category = $decoded['category'];
$content = base64_decode($decoded['content']);
$mode = isset($decoded['mode']) ? $decoded['mode'] : 'overwrite';

if ($content === false) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid base64 payload"]);
    exit;
}

// 5. Security: Sanitize category to prevent directory traversal (allow paths)
// Remove any .. to prevent escaping the wiki directory
$category = str_replace('..', '', $category);
// Only allow alphanumerics, slashes, underscores, dashes, and dots
$category = preg_replace('/[^a-zA-Z0-9_\/.-]/', '', $category);

// Ensure it ends with .md
if (substr($category, -3) !== '.md') {
    $category .= '.md';
}

// 6. Define the target Markdown file
$base_dir = realpath(__DIR__ . '/../decryption/wiki');
$target_file = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $category);

// Check if directory exists, if not, create it
$target_dir = dirname($target_file);
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}
if (strpos($target_file, $base_dir) !== 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid target path"]);
    exit;
}

// 7. Write the content based on mode
if ($mode === 'append') {
    $result = file_put_contents($target_file, "\n\n" . $content . "\n", FILE_APPEND | LOCK_EX);
} else {
    $result = file_put_contents($target_file, $content, LOCK_EX);
}

if ($result === false) {
    $error = error_get_last();
    $user = get_current_user();
    $exec_user = exec('whoami');
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Permission denied: Could not write to $target_file",
        "php_user" => $user,
        "exec_user" => $exec_user,
        "system_error" => $error ? $error['message'] : "Unknown error"
    ]);
    exit;
}

// 8. Return success
echo json_encode(["status" => "success", "message" => "Markdown successfully published to {$category}"]);
?>
