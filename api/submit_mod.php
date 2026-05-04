<?php
/**
 * PSOBB API: Submit Mod
 * 
 * Handles multipart/form-data uploads for user-submitted mods.
 * Performs basic sanitization to prevent Stored XSS, validates the 
 * ZIP archive and media files, and inserts the pending mod into the database.
 */
require_once __DIR__ . '/config.php';
start_secure_session();

header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in to submit a mod.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$name = strip_tags(trim($_POST['name'] ?? ''));
$author = strip_tags(trim($_POST['author'] ?? ''));
$submitted_by = $_SESSION['user']['username'] ?? 'UnknownUser';
$version = strip_tags(trim($_POST['version'] ?? ''));
$description = strip_tags(trim($_POST['description'] ?? ''));
$purpose = strip_tags(trim($_POST['purpose'] ?? ''));
$category = strip_tags(trim($_POST['category'] ?? ''));

if (empty($name) || empty($author) || empty($version) || empty($description) || empty($category)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!isset($_FILES['mod_file']) || $_FILES['mod_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Mod archive is required and must be valid']);
    exit;
}

// Generate base slug
$slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
$slug = trim($slug, '-');
$safe_version = strtolower(preg_replace('/[^a-zA-Z0-9.]+/', '-', $version));

require_once __DIR__ . '/db.php';
$db = get_db();

// Check if mod exists and validate ownership
$stmt = $db->prepare("SELECT mod_id, submitted_by FROM mods WHERE name = :name COLLATE NOCASE LIMIT 1");
$stmt->bindValue(':name', $name);
$res = $stmt->execute();
$existing = $res->fetchArray(SQLITE3_ASSOC);

if ($existing) {
    if ($existing['submitted_by'] !== $submitted_by) {
        echo json_encode(['success' => false, 'error' => "A mod with this name already exists and was created by {$existing['submitted_by']}. Only they can upload new versions."]);
        exit;
    }
    $mod_id = $existing['mod_id'];
    $is_update = true;
} else {
    $mod_id = $slug . '-' . time();
    $is_update = false;
}

// Create unique directory for this mod
$mod_dir = __DIR__ . '/../uploads/mods/' . $slug;
if (!is_dir($mod_dir)) {
    mkdir($mod_dir, 0777, true);
}

// Handle zip upload
$zip_ext = strtolower(pathinfo($_FILES['mod_file']['name'], PATHINFO_EXTENSION));
if ($zip_ext !== 'zip') {
    echo json_encode(['success' => false, 'error' => 'Mod must be a .zip file']);
    exit;
}
$zip_size = $_FILES['mod_file']['size'];
if ($zip_size > 200 * 1024 * 1024) { // 200MB max
    echo json_encode(['success' => false, 'error' => 'Mod file too large (max 200MB)']);
    exit;
}
$zip_dest = 'uploads/mods/' . $slug . '/v' . $safe_version . '_mod.zip';
if (!move_uploaded_file($_FILES['mod_file']['tmp_name'], __DIR__ . '/../' . $zip_dest)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save mod archive']);
    exit;
}

// Handle optional multiple image/video uploads
$uploaded_media_paths = [];
$max_media = 6;

if (isset($_FILES['mod_images']) && is_array($_FILES['mod_images']['error'])) {
    $count = count($_FILES['mod_images']['error']);
    if ($count > $max_media) $count = $max_media;
    
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['mod_images']['error'][$i] === UPLOAD_ERR_OK) {
            $img_ext = strtolower(pathinfo($_FILES['mod_images']['name'][$i], PATHINFO_EXTENSION));
            if (in_array($img_ext, ['jpg', 'jpeg', 'png', 'mp4', 'webm'])) {
                $file_dest = 'uploads/mods/' . $slug . '/v' . $safe_version . '_media_' . $i . '.' . $img_ext;
                if (move_uploaded_file($_FILES['mod_images']['tmp_name'][$i], __DIR__ . '/../' . $file_dest)) {
                    $uploaded_media_paths[] = $file_dest;
                }
            }
        }
    }
}

$img_dest = count($uploaded_media_paths) > 0 ? json_encode($uploaded_media_paths) : null;

try {
    if ($is_update) {
        // If they didn't upload a new image, keep the old one
        $updateSql = "UPDATE mods SET author = :author, version = :ver, description = :desc, purpose = :purp, category = :cat, file_path = :fp, file_size = :sz, status = 'pending'";
        if ($img_dest) {
            $updateSql .= ", image_path = :ip";
        }
        $updateSql .= " WHERE mod_id = :id";
        
        $upd = $db->prepare($updateSql);
        $upd->bindValue(':id', $mod_id);
        $upd->bindValue(':author', $author);
        $upd->bindValue(':ver', $version);
        $upd->bindValue(':desc', $description);
        $upd->bindValue(':purp', $purpose);
        $upd->bindValue(':cat', $category);
        $upd->bindValue(':fp', $zip_dest);
        $upd->bindValue(':sz', $zip_size);
        if ($img_dest) {
            $upd->bindValue(':ip', $img_dest);
        }
        $upd->execute();
    } else {
        $ins = $db->prepare("INSERT INTO mods (mod_id, name, author, submitted_by, version, description, purpose, category, file_path, image_path, file_size, status) VALUES (:id, :name, :author, :sub, :ver, :desc, :purp, :cat, :fp, :ip, :sz, 'pending')");
        $ins->bindValue(':id', $mod_id);
        $ins->bindValue(':name', $name);
        $ins->bindValue(':author', $author);
        $ins->bindValue(':sub', $submitted_by);
        $ins->bindValue(':ver', $version);
        $ins->bindValue(':desc', $description);
        $ins->bindValue(':purp', $purpose);
        $ins->bindValue(':cat', $category);
        $ins->bindValue(':fp', $zip_dest);
        $ins->bindValue(':ip', $img_dest);
        $ins->bindValue(':sz', $zip_size);
        $ins->execute();
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
