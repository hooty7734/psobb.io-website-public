<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'db.php';

try {
    $db = get_db();
    
    $category = isset($_GET['category']) ? $_GET['category'] : 'all';
    
    if ($category !== 'all') {
        $stmt = $db->prepare("SELECT * FROM mods WHERE category = :category AND status = 'approved' ORDER BY published_at DESC");
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $result = $stmt->execute();
    } else {
        $result = $db->query("SELECT * FROM mods WHERE status = 'approved' ORDER BY published_at DESC");
    }
    
    $mods = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $imageUrl = $row['image_path'];
        if (!empty($imageUrl)) {
            $decoded = json_decode($imageUrl, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $imageUrl = $decoded[0]; // Only send the first image to the launcher
            }
        }
        
        $mods[] = [
            "id" => $row['mod_id'],
            "name" => $row['name'],
            "author" => $row['author'],
            "version" => $row['version'],
            "description" => $row['description'],
            "category" => $row['category'],
            "downloadUrl" => $row['file_path'],
            "imageUrl" => $imageUrl,
            "fileSize" => (int)$row['file_size'],
            "publishedAt" => $row['published_at']
        ];
    }
    
    // Output JSON array directly to match expected API format
    echo json_encode($mods, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
