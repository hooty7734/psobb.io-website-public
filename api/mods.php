<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Disable error reporting in output to prevent JSON corruption
error_reporting(0);

require_once __DIR__ . '/db.php';

try {
    $db = get_db();
    
    // Fetch mods that have been approved, along with their rating stats
    $stmt = $db->prepare("
        SELECT 
            m.mod_id as id, 
            m.name, 
            m.author, 
            m.version, 
            m.description, 
            m.category, 
            m.file_path as downloadUrl, 
            m.image_path as imageUrl, 
            m.file_size as fileSize, 
            m.published_at as publishedAt,
            COALESCE(AVG(r.rating), 0) as averageRating,
            COUNT(r.id) as ratingCount
        FROM mods m
        LEFT JOIN mod_ratings r ON m.mod_id = r.mod_id
        WHERE m.status = 'approved' 
        GROUP BY m.mod_id
        ORDER BY m.published_at DESC
    ");
    $result = $stmt->execute();
    
    $mods = [];
    $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . "/"; // Adjust if needed
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Ensure URLs are absolute for the launcher
        $row['downloadUrl'] = $baseUrl . $row['downloadUrl'];
        
        $imageUrls = [];
        if (!empty($row['imageUrl'])) {
            $decoded = json_decode($row['imageUrl'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $path) {
                    $imageUrls[] = $baseUrl . $path;
                }
            } else {
                // Legacy string support
                $imageUrls[] = $baseUrl . $row['imageUrl'];
            }
        }
        $row['imageUrls'] = $imageUrls;
        unset($row['imageUrl']); // Use the array version for the website
        $row['averageRating'] = round((float)$row['averageRating'], 1);
        $row['ratingCount'] = (int)$row['ratingCount'];
        $mods[] = $row;
    }
    
    echo json_encode($mods, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Internal Server Error']);
}
?>
