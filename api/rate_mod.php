<?php
/**
 * PSOBB API: Rate Mod
 * 
 * Allows authenticated users to submit a 1-5 star rating for a published mod.
 * Uses an UPSERT (ON CONFLICT) query to allow users to update their vote.
 * Returns the recalculated average and total rating count for the frontend.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

start_secure_session();

header('Content-Type: application/json');
verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');

if (empty($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'You must be logged in to rate a mod.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$mod_id = trim($_POST['mod_id'] ?? '');
$rating = (int)($_POST['rating'] ?? 0);

if (empty($mod_id) || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid mod ID or rating value (must be 1-5).']);
    exit;
}

$account_id = $_SESSION['user']['account_id'];

try {
    $db = get_db();
    
    // Use INSERT OR REPLACE to allow changing votes
    // We need to use the id if it exists to properly REPLACE without violating UNIQUE if we didn't specify id?
    // Wait, INSERT OR REPLACE replaces based on UNIQUE constraint. Since UNIQUE(mod_id, account_id) exists, it replaces safely!
    
    $stmt = $db->prepare("INSERT OR REPLACE INTO mod_ratings (id, mod_id, account_id, rating, created_at) 
                          VALUES ((SELECT id FROM mod_ratings WHERE mod_id = :mod_id AND account_id = :account_id), :mod_id, :account_id, :rating, CURRENT_TIMESTAMP)");
    $stmt->bindValue(':mod_id', $mod_id, SQLITE3_TEXT);
    $stmt->bindValue(':account_id', $account_id, SQLITE3_INTEGER);
    $stmt->bindValue(':rating', $rating, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Fetch the updated average
    $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(id) as rating_count FROM mod_ratings WHERE mod_id = :mod_id");
    $avgStmt->bindValue(':mod_id', $mod_id, SQLITE3_TEXT);
    $res = $avgStmt->execute();
    $stats = $res->fetchArray(SQLITE3_ASSOC);
    
    echo json_encode([
        'success' => true,
        'average' => round((float)$stats['avg_rating'], 1),
        'count' => (int)$stats['rating_count']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
